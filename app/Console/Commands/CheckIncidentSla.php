<?php

namespace App\Console\Commands;

use App\Models\Incident;
use App\Services\IncidentNotificationService;
use Illuminate\Console\Command;

class CheckIncidentSla extends Command
{
    protected $signature = 'incident:check-sla';

    protected $description = 'Cek SLA breach pada incident yang masih aktif';

    public function handle(): int
    {
        $breached = Incident::active()
            ->where('sla_deadline', '<', now())
            ->where('sla_status', '!=', 'breached')
            ->get();

        if ($breached->isEmpty()) {
            $this->info('Tidak ada SLA breach.');

            return self::SUCCESS;
        }

        foreach ($breached as $incident) {
            $incident->update(['sla_status' => 'breached']);

            (new IncidentNotificationService($incident->tenant_id))->notifySlaBreached($incident);

            $this->warn("SLA BREACHED: Incident #{$incident->id} — {$incident->title}");
        }

        $this->info("Total {$breached->count()} incident SLA breached.");

        return self::SUCCESS;
    }
}
