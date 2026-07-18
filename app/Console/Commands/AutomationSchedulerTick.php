<?php

namespace App\Console\Commands;

use App\Services\Automation\AutomationSchedulerService;
use Illuminate\Console\Command;

class AutomationSchedulerTick extends Command
{
    protected $signature = 'automation:scheduler';

    protected $description = 'Evaluate scheduled jobs and dispatch due ones';

    public function handle(): int
    {
        $result = AutomationSchedulerService::tick();

        $this->line("Scheduler tick: {$result['dispatched']} dispatched, {$result['skipped']} skipped, {$result['errors']} errors ({$result['due_count']} due)");

        return $result['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
