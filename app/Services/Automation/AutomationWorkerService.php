<?php

namespace App\Services\Automation;

use App\Models\AutomationJob;
use App\Models\AutomationJobLog;
use Illuminate\Support\Facades\Log;

/**
 * Automation Worker Service — processes queued jobs.
 *
 * Runs via `automation:worker` artisan command.
 * Picks up queued jobs, executes them, handles retry/timeout.
 */
class AutomationWorkerService
{
    private const LOG_CHANNEL = 'automation';

    /**
     * Process all queued jobs.
     */
    public static function processQueue(): array
    {
        $queuedJobs = AutomationJob::where('status', 'queued')
            ->orderByDesc('priority')
            ->orderBy('updated_at')
            ->get();

        $processed = 0;
        $failed = 0;

        foreach ($queuedJobs as $job) {
            $result = self::processJob($job);
            if ($result) {
                $processed++;
            } else {
                $failed++;
            }
        }

        return [
            'queued_found' => $queuedJobs->count(),
            'processed' => $processed,
            'failed' => $failed,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Process a single job.
     */
    public static function processJob(AutomationJob $job): bool
    {
        $startedAt = now();
        $attempt = $job->current_attempt + 1;

        // Create log entry
        $log = AutomationJobLog::create([
            'tenant_id' => $job->tenant_id,
            'automation_job_id' => $job->id,
            'status' => 'running',
            'attempt' => $attempt,
            'started_at' => $startedAt,
        ]);

        // Mark job as running
        $job->update([
            'status' => 'running',
            'current_attempt' => $attempt,
            'last_run_at' => $startedAt,
        ]);

        Log::channel(self::LOG_CHANNEL)->info("Worker: processing job [{$job->id}] {$job->name} (attempt {$attempt})");

        try {
            $result = self::executeJob($job);

            $durationMs = (int) $startedAt->diffInMilliseconds(now());

            $log->update([
                'status' => 'completed',
                'message' => $result['message'] ?? 'Job completed successfully',
                'result_data' => $result['data'] ?? null,
                'duration_ms' => $durationMs,
                'completed_at' => now(),
            ]);

            $job->update([
                'status' => 'completed',
                'current_attempt' => 0,
                'last_error' => null,
            ]);

            // Recalculate next_run for scheduled jobs
            if ($job->schedule_type !== 'manual' && $job->is_active) {
                AutomationSchedulerService::reschedule($job);
            }

            Log::channel(self::LOG_CHANNEL)->info("Worker: completed job [{$job->id}] in {$durationMs}ms");

            return true;

        } catch (\Exception $e) {
            $durationMs = (int) $startedAt->diffInMilliseconds(now());

            $errorMessage = $e->getMessage();

            $log->update([
                'status' => 'failed',
                'error' => $errorMessage,
                'duration_ms' => $durationMs,
                'completed_at' => now(),
            ]);

            $job->update([
                'last_error' => $errorMessage,
            ]);

            // Check if we can retry
            if ($job->isRetryable()) {
                $job->update([
                    'status' => 'queued',
                    'current_attempt' => $attempt,
                ]);

                $log->update(['status' => 'retrying']);

                Log::channel(self::LOG_CHANNEL)->warning("Worker: job [{$job->id}] will retry (attempt {$attempt}/{$job->max_attempts})", [
                    'error' => $errorMessage,
                ]);
            } else {
                $job->update([
                    'status' => 'failed',
                    'current_attempt' => 0,
                ]);

                Log::channel(self::LOG_CHANNEL)->error("Worker: job [{$job->id}] failed permanently after {$attempt} attempts", [
                    'error' => $errorMessage,
                ]);
            }

            return false;
        }
    }

    /**
     * Execute a job based on its type.
     *
     * This is the extensible dispatch point — new job types are handled here.
     */
    private static function executeJob(AutomationJob $job): array
    {
        // Dispatch to the appropriate handler based on job type
        $handler = self::resolveHandler($job->type);

        if ($handler) {
            return $handler->handle($job);
        }

        // Default: log and return success (placeholder for future handlers)
        Log::channel(self::LOG_CHANNEL)->info("Worker: no handler for job type [{$job->type}], marking as completed");

        return [
            'message' => "Job type [{$job->type}] executed (no handler registered)",
            'data' => null,
        ];
    }

    /**
     * Resolve a handler class for a job type.
     *
     * Convention: App\Services\Automation\Handlers\{Type}JobHandler
     */
    private static function resolveHandler(string $type): ?object
    {
        $class = 'App\\Services\\Automation\\Handlers\\'.ucfirst($type).'JobHandler';

        if (class_exists($class)) {
            return new $class;
        }

        return null;
    }
}
