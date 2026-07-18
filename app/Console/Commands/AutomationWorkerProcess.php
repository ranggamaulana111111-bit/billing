<?php

namespace App\Console\Commands;

use App\Services\Automation\AutomationWorkerService;
use Illuminate\Console\Command;

class AutomationWorkerProcess extends Command
{
    protected $signature = 'automation:worker {--once : Process queue once and exit}';

    protected $description = 'Process queued automation jobs';

    public function handle(): int
    {
        if ($this->option('once')) {
            $result = AutomationWorkerService::processQueue();
            $this->line("Worker: {$result['processed']} processed, {$result['failed']} failed ({$result['queued_found']} queued)");

            return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
        }

        $this->info('Worker started. Processing queue...');

        while (true) {
            $result = AutomationWorkerService::processQueue();

            if ($result['queued_found'] > 0) {
                $this->line('['.now()->format('H:i:s')."] {$result['processed']} processed, {$result['failed']} failed");
            }

            sleep(10);
        }
    }
}
