<?php

namespace App\Services\Automation;

use App\Models\AutomationJob;
use Illuminate\Support\Facades\Log;

/**
 * Automation Scheduler Service — evaluates scheduled jobs and dispatches them.
 *
 * Runs every minute via `automation:scheduler` artisan command.
 * Designed to be idempotent — running it twice produces the same result.
 */
class AutomationSchedulerService
{
    private const LOG_CHANNEL = 'automation';

    /**
     * Tick: evaluate all scheduled jobs and dispatch due ones.
     */
    public static function tick(): array
    {
        $dueJobs = AutomationJobService::getDueJobs();
        $dispatched = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($dueJobs as $job) {
            try {
                // Skip if already running
                if ($job->status === 'running') {
                    $skipped++;

                    continue;
                }

                AutomationJobService::dispatch($job);
                $dispatched++;

                Log::channel(self::LOG_CHANNEL)->info("Scheduler: dispatched job [{$job->id}] {$job->name}");
            } catch (\Exception $e) {
                $errors++;
                Log::channel(self::LOG_CHANNEL)->error("Scheduler: failed to dispatch job [{$job->id}]", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'due_count' => $dueJobs->count(),
            'dispatched' => $dispatched,
            'skipped' => $skipped,
            'errors' => $errors,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Evaluate a specific job's schedule and update next_run_at.
     */
    public static function reschedule(AutomationJob $job): void
    {
        if ($job->schedule_type === 'manual' || ! $job->is_active) {
            $job->update(['next_run_at' => null]);

            return;
        }

        $nextRun = AutomationJobService::calculateNextRun($job);
        $job->update(['next_run_at' => $nextRun]);
    }

    /**
     * Bulk reschedule all active scheduled jobs.
     */
    public static function rescheduleAll(): int
    {
        $jobs = AutomationJob::active()
            ->where('schedule_type', '!=', 'manual')
            ->get();

        $count = 0;
        foreach ($jobs as $job) {
            self::reschedule($job);
            $count++;
        }

        return $count;
    }
}
