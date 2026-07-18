<?php

namespace App\Services\Automation;

use App\Models\AutomationTrigger;
use Illuminate\Support\Facades\Log;

/**
 * Automation Trigger Service — evaluates and fires triggers.
 *
 * Supports event-based triggers:
 * - schedule: handled by SchedulerService
 * - device_status: fire when device goes online/offline
 * - monitoring_alert: fire when monitoring threshold is breached
 * - config_change: fire when config version is created
 * - internal_event: fire on arbitrary internal events
 */
class AutomationTriggerService
{
    private const LOG_CHANNEL = 'automation';

    /**
     * Fire all active triggers matching an event type.
     *
     * @param  string  $eventType  The event type identifier
     * @param  array  $context  Event-specific context data
     */
    public static function fireEvent(string $eventType, array $context = []): array
    {
        $triggers = AutomationTrigger::active()
            ->byEvent($eventType)
            ->with('job')
            ->get();

        $fired = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($triggers as $trigger) {
            try {
                // Check if trigger conditions match context
                if (! self::matchesContext($trigger, $context)) {
                    $skipped++;

                    continue;
                }

                // Check cooldown (don't fire same trigger within 60 seconds)
                if ($trigger->last_fired_at && $trigger->last_fired_at->diffInSeconds(now()) < 60) {
                    $skipped++;

                    continue;
                }

                // Dispatch the associated job
                if ($trigger->job && $trigger->job->is_active) {
                    AutomationJobService::dispatch($trigger->job);

                    $trigger->update([
                        'fire_count' => $trigger->fire_count + 1,
                        'last_fired_at' => now(),
                    ]);

                    $fired++;
                    Log::channel(self::LOG_CHANNEL)->info("Trigger [{$trigger->id}] fired: {$trigger->name} → job [{$trigger->job_id}]");
                } else {
                    $skipped++;
                }
            } catch (\Exception $e) {
                $errors++;
                Log::channel(self::LOG_CHANNEL)->error("Trigger [{$trigger->id}] error", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'event_type' => $eventType,
            'triggers_found' => $triggers->count(),
            'fired' => $fired,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Check if trigger event_config matches the context.
     */
    private static function matchesContext(AutomationTrigger $trigger, array $context): bool
    {
        $config = $trigger->event_config ?? [];

        // If no config, always match
        if (empty($config)) {
            return true;
        }

        // Check specific filters based on event type
        foreach ($config as $key => $expectedValue) {
            if (! array_key_exists($key, $context)) {
                return false;
            }

            // Support array of allowed values
            if (is_array($expectedValue)) {
                if (! in_array($context[$key], $expectedValue, true)) {
                    return false;
                }
            } elseif ($context[$key] !== $expectedValue) {
                return false;
            }
        }

        return true;
    }

    /**
     * Convenience: fire a device status change event.
     */
    public static function onDeviceStatusChange(int $routerId, string $oldStatus, string $newStatus): array
    {
        return self::fireEvent('device_status', [
            'router_id' => $routerId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ]);
    }

    /**
     * Convenience: fire a monitoring alert event.
     */
    public static function onMonitoringAlert(string $metric, float $value, float $threshold): array
    {
        return self::fireEvent('monitoring_alert', [
            'metric' => $metric,
            'value' => $value,
            'threshold' => $threshold,
        ]);
    }

    /**
     * Convenience: fire a config change event.
     */
    public static function onConfigChange(int $routerId, string $module, string $itemId): array
    {
        return self::fireEvent('config_change', [
            'router_id' => $routerId,
            'module' => $module,
            'item_id' => $itemId,
        ]);
    }

    /**
     * Convenience: fire an internal event.
     */
    public static function onInternalEvent(string $eventName, array $data = []): array
    {
        return self::fireEvent('internal_event', array_merge(['event_name' => $eventName], $data));
    }
}
