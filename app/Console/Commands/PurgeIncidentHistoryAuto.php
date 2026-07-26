<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Incident;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Console\Command;

class PurgeIncidentHistoryAuto extends Command
{
    protected $signature = 'incidents:purge-auto';

    protected $description = 'Hapus otomatis history gangguan yang dibuat sebelum waktu incident_history_purge_at';

    public function handle(): int
    {
        $purgeAt = Setting::get('incident_history_purge_at');

        if (! $purgeAt) {
            return self::SUCCESS;
        }

        $cutoff = Carbon::parse($purgeAt);

        if (now()->lt($cutoff)) {
            return self::SUCCESS;
        }

        $deleted = 0;
        Incident::withoutGlobalScopes()
            ->where('created_at', '<', $cutoff)
            ->chunkById(100, function ($incidents) use (&$deleted) {
                foreach ($incidents as $incident) {
                    $incident->notifications()->delete();
                    $incident->delete();
                    $deleted++;
                }
            });

        if ($deleted > 0) {
            ActivityLog::log('Incident Purge Auto', "{$deleted} history gangguan dihapus otomatis (sebelum {$cutoff}).");
            $this->info("{$deleted} history gangguan dihapus otomatis (sebelum {$cutoff}).");

            Setting::set('incident_history_purge_at', null);

            return self::SUCCESS;
        }

        Setting::set('incident_history_purge_at', null);

        return self::SUCCESS;
    }
}
