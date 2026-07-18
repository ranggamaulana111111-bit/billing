<?php

namespace App\Services\Automation;

use App\Models\AutomationJob;
use App\Models\AutomationJobLog;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Automation Job Service — CRUD, dispatch, and lifecycle management.
 */
class AutomationJobService
{
    /**
     * Create a new automation job.
     */
    public static function create(array $data, ?int $userId = null): AutomationJob
    {
        $job = AutomationJob::create([
            'tenant_id' => tenant()->id ?? auth()->user()->tenant_id,
            'name' => $data['name'],
            'type' => $data['type'],
            'parameters' => $data['parameters'] ?? null,
            'schedule_type' => $data['schedule_type'] ?? 'manual',
            'schedule_config' => $data['schedule_config'] ?? null,
            'priority' => $data['priority'] ?? 5,
            'max_attempts' => $data['max_attempts'] ?? 3,
            'timeout_seconds' => $data['timeout_seconds'] ?? 300,
            'is_active' => $data['is_active'] ?? true,
            'status' => 'idle',
            'created_by' => $userId,
        ]);

        // Calculate initial next_run_at for scheduled jobs
        if ($job->schedule_type !== 'manual' && $job->is_active) {
            $job->update(['next_run_at' => self::calculateNextRun($job)]);
        }

        return $job;
    }

    /**
     * Update an automation job.
     */
    public static function update(AutomationJob $job, array $data): AutomationJob
    {
        $job->update($data);

        // Recalculate next_run_at if schedule changed
        if ($job->wasChanged(['schedule_type', 'schedule_config', 'is_active'])) {
            if ($job->schedule_type !== 'manual' && $job->is_active) {
                $job->update(['next_run_at' => self::calculateNextRun($job)]);
            } else {
                $job->update(['next_run_at' => null]);
            }
        }

        return $job;
    }

    /**
     * Dispatch a job immediately (set to queued).
     */
    public static function dispatch(AutomationJob $job, ?int $userId = null): AutomationJobLog
    {
        $job->update([
            'status' => 'queued',
            'current_attempt' => 0,
            'last_error' => null,
        ]);

        return AutomationJobLog::create([
            'tenant_id' => $job->tenant_id,
            'automation_job_id' => $job->id,
            'status' => 'queued',
            'attempt' => 1,
            'started_at' => now(),
        ]);
    }

    /**
     * Cancel a running or queued job.
     */
    public static function cancel(AutomationJob $job): bool
    {
        $job->update([
            'status' => 'cancelled',
            'current_attempt' => 0,
        ]);

        return true;
    }

    /**
     * Retry a failed job.
     */
    public static function retry(AutomationJob $job): AutomationJobLog
    {
        return self::dispatch($job);
    }

    /**
     * Reset a completed or failed job back to idle.
     */
    public static function reset(AutomationJob $job): bool
    {
        $job->update([
            'status' => 'idle',
            'current_attempt' => 0,
            'last_error' => null,
        ]);

        return true;
    }

    /**
     * Delete a job and its logs.
     */
    public static function delete(AutomationJob $job): bool
    {
        $job->logs()->delete();
        $job->triggers()->delete();
        $job->delete();

        return true;
    }

    /**
     * Get all jobs with pagination and optional filters.
     */
    public static function list(
        ?string $type = null,
        ?string $status = null,
        ?string $search = null,
        int $limit = 25,
    ): LengthAwarePaginator {
        $query = AutomationJob::with(['creator', 'triggers']);

        if ($type) {
            $query->where('type', $type);
        }
        if ($status) {
            $query->where('status', $status);
        }
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%");
            });
        }

        return $query->orderByDesc('priority')
            ->orderByDesc('updated_at')
            ->paginate($limit);
    }

    /**
     * Get dashboard statistics.
     */
    public static function getStats(): array
    {
        $base = AutomationJob::query();

        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('is_active', true)->count(),
            'idle' => (clone $base)->where('status', 'idle')->count(),
            'queued' => (clone $base)->where('status', 'queued')->count(),
            'running' => (clone $base)->where('status', 'running')->count(),
            'completed' => (clone $base)->where('status', 'completed')->count(),
            'failed' => (clone $base)->where('status', 'failed')->count(),
            'cancelled' => (clone $base)->where('status', 'cancelled')->count(),
            'due' => (clone $base)->due()->count(),
            'recent_logs_24h' => AutomationJobLog::where('started_at', '>=', now()->subHours(24))->count(),
            'failed_logs_24h' => AutomationJobLog::where('status', 'failed')
                ->where('started_at', '>=', now()->subHours(24))
                ->count(),
        ];
    }

    /**
     * Get recent job logs with pagination.
     */
    public static function getRecentLogs(int $limit = 30): LengthAwarePaginator
    {
        return AutomationJobLog::with(['job'])
            ->latest('started_at')
            ->paginate($limit);
    }

    /**
     * Get due jobs for the scheduler to process.
     */
    public static function getDueJobs(): Collection
    {
        return AutomationJob::due()->orderBy('priority')->get();
    }

    /**
     * Calculate next run time based on schedule.
     */
    public static function calculateNextRun(AutomationJob $job): ?Carbon
    {
        return match ($job->schedule_type) {
            'interval' => self::calcInterval($job->schedule_config),
            'daily' => self::calcDaily($job->schedule_config),
            'weekly' => self::calcWeekly($job->schedule_config),
            'monthly' => self::calcMonthly($job->schedule_config),
            'cron' => self::calcCron($job->schedule_config),
            default => null,
        };
    }

    private static function calcInterval(?string $config): ?Carbon
    {
        if (! $config || ! preg_match('/^(\d+)(s|m|h|d)$/', $config, $m)) {
            return null;
        }

        $amount = (int) $m[1];
        $unit = $m[2];

        return match ($unit) {
            's' => now()->addSeconds($amount),
            'm' => now()->addMinutes($amount),
            'h' => now()->addHours($amount),
            'd' => now()->addDays($amount),
            default => null,
        };
    }

    private static function calcDaily(?string $config): ?Carbon
    {
        if (! $config || ! preg_match('/^(\d{2}):(\d{2})$/', $config, $m)) {
            return null;
        }

        $next = now()->setTime((int) $m[1], (int) $m[2], 0);

        if ($next->isPast()) {
            $next->addDay();
        }

        return $next;
    }

    private static function calcWeekly(?string $config): ?Carbon
    {
        // Format: "08:00,1,3,5" (time + days of week)
        if (! $config || ! preg_match('/^(\d{2}):(\d{2}),([0-6,]+)$/', $config, $m)) {
            return null;
        }

        $time = $m[1].':'.$m[2];
        $days = array_map('intval', explode(',', $m[3]));

        $next = now()->setTime((int) $m[1], (int) $m[2], 0);
        while (! in_array($next->dayOfWeek, $days) || $next->isPast()) {
            $next->addDay();
        }

        return $next;
    }

    private static function calcMonthly(?string $config): ?Carbon
    {
        // Format: "08:00,1" (time + day of month)
        if (! $config || ! preg_match('/^(\d{2}):(\d{2}),(\d{1,2})$/', $config, $m)) {
            return null;
        }

        $next = now()->setTime((int) $m[1], (int) $m[2], 0)->day((int) $m[3]);

        if ($next->isPast()) {
            $next->addMonthNoOverflow();
        }

        return $next;
    }

    private static function calcCron(?string $config): ?Carbon
    {
        // Simple cron-like: "*/15 * * * *" — use Laravel's Schedule support
        // For simplicity, parse basic cron patterns
        if (! $config) {
            return null;
        }

        // Use Laravel's cron expression parser
        $parts = explode(' ', $config);
        if (count($parts) !== 5) {
            return null;
        }

        try {
            // Delegate to Carbon's parse for next occurrence
            // For now, use a simplified approach: parse minutes and hours
            $minutes = self::parseCronField($parts[0], 0, 59);
            $hours = self::parseCronField($parts[1], 0, 23);

            $next = now()->startOfMinute();
            $found = false;

            // Search up to 32 days ahead
            for ($dayOffset = 0; $dayOffset < 32; $dayOffset++) {
                $checkDay = now()->addDays($dayOffset)->startOfDay();
                foreach ($hours as $hour) {
                    foreach ($minutes as $minute) {
                        $candidate = $checkDay->copy()->hour($hour)->minute($minute);
                        if ($candidate->isFuture()) {
                            return $candidate;
                        }
                    }
                }
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private static function parseCronField(string $field, int $min, int $max): array
    {
        $values = [];

        if ($field === '*') {
            return range($min, $max);
        }

        if (str_contains($field, '/')) {
            [$range, $step] = explode('/', $field, 2);
            $step = max(1, (int) $step);
            if ($range === '*') {
                return range($min, $max, $step);
            }
        }

        if (str_contains($field, ',')) {
            return array_map('intval', explode(',', $field));
        }

        if (str_contains($field, '-')) {
            [$from, $to] = explode('-', $field, 2);

            return range((int) $from, (int) $to);
        }

        return [(int) $field];
    }
}
