<?php

namespace App\Console\Commands;

use App\Models\Incident;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Console\Command;

class PurgeIncidentHistory extends Command
{
    protected $signature = 'incidents:purge
        {--days= : Rentang waktu penyimpanan (hari). Default mengikuti setting incident_history_retention_days.}
        {--before= : Hapus history sebelum tanggal ini (Y-m-d). Mengoverride --days.}
        {--dry-run : Tampilkan jumlah yang akan dihapus tanpa menghapus.}';

    protected $description = 'Hapus history gangguan (incident resolved/closed) yang lebih lama dari rentang waktu yang diatur';

    public function handle(): int
    {
        $defaultDays = (int) Setting::get('incident_history_retention_days', '365');
        $days = $this->option('days') ? (int) $this->option('days') : $defaultDays;
        $before = $this->option('before');

        if ($before) {
            $cutoff = Carbon::parse($before)->startOfDay();
            $this->info("Menghapus history gangguan sebelum {$cutoff->format('d/m/Y')}.");
        } else {
            $cutoff = now()->subDays($days);
            $this->info("Menghapus history gangguan lebih lama dari {$days} hari (sebelum {$cutoff->format('d/m/Y')}).");
        }

        $query = Incident::withoutGlobalScopes()
            ->where('created_at', '<', $cutoff)
            ->whereIn('status', ['resolved', 'closed']);

        $count = $query->count();

        if ($this->option('dry-run')) {
            $this->warn("DRY RUN: {$count} history gangguan akan dihapus.");

            return self::SUCCESS;
        }

        if ($count === 0) {
            $this->info('Tidak ada history gangguan yang perlu dihapus.');

            return self::SUCCESS;
        }

        $query->chunkById(100, function ($incidents) {
            foreach ($incidents as $incident) {
                $incident->notifications()->delete();
                $incident->delete();
            }
        });

        $this->info("{$count} history gangguan berhasil dihapus.");

        return self::SUCCESS;
    }
}
