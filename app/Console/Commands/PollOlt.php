<?php

namespace App\Console\Commands;

use App\Jobs\PollOltJob;
use App\Models\Olt;
use Illuminate\Console\Command;

class PollOlt extends Command
{
    protected $signature = 'olt:poll {--olt=} {--queue}';

    protected $description = 'Dispatch OLT polling jobs (one per OLT)';

    public function handle(): void
    {
        $olts = Olt::query();

        if ($oltId = $this->option('olt')) {
            $olts->where('id', $oltId);
        }

        $olts = $olts->where('status', 'active')->get();

        if ($olts->isEmpty()) {
            $this->warn('No active OLT devices found.');

            return;
        }

        $dispatched = 0;

        foreach ($olts as $olt) {
            if ($this->option('queue')) {
                PollOltJob::dispatch($olt);
                $this->line("Dispatched job for {$olt->name} ({$olt->ip_address})");
            } else {
                PollOltJob::dispatchSync($olt);
                $this->line("Processed {$olt->name} ({$olt->ip_address})");
            }

            $dispatched++;
        }

        $this->info("Done. {$dispatched} OLT(s) processed.");
    }
}
