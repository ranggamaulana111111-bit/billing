<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\MikrotikRouter;
use App\Models\Voucher;
use App\Services\MikrotikService;
use Illuminate\Console\Command;

class SyncVoucherMikrotik extends Command
{
    protected $signature = 'voucher:sync-mikrotik';

    protected $description = 'Sinkronasi status voucher dengan MikroTik (auto-mark used jika ada session aktif)';

    public function handle()
    {
        Voucher::where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);

        $routers = MikrotikRouter::where('is_active', true)
            ->whereIn('type', ['general'])
            ->get();

        if ($routers->isEmpty()) {
            $this->warn('Tidak ada router aktif. Coba fallback ke konfigurasi setting...');

            $mikrotik = new MikrotikService;
            if ($mikrotik->isConfigured()) {
                $this->processRouter($mikrotik);
            } else {
                $this->warn('MikroTik tidak dikonfigurasi.');
            }

            return;
        }

        foreach ($routers as $router) {
            $this->info("Memproses router: {$router->name} ({$router->host})");
            $mikrotik = new MikrotikService($router);
            $this->processRouter($mikrotik);
        }

        $this->info('Sinkronasi selesai.');
    }

    protected function processRouter(MikrotikService $mikrotik): void
    {
        $activeVouchers = Voucher::where('status', 'active')->get();
        $markedUsed = 0;
        $pushed = 0;

        foreach ($activeVouchers as $voucher) {
            $sessions = $mikrotik->getUserActiveSessions($voucher->username);

            if (! empty($sessions)) {
                $now = now();
                $updateData = [
                    'status' => 'used',
                    'used_at' => $now,
                ];
                if (! $voucher->expires_at && $voucher->duration_hours > 0) {
                    $updateData['expires_at'] = $now->copy()->addHours($voucher->duration_hours);
                }
                $voucher->update($updateData);
                $markedUsed++;
                $this->info("{$voucher->username} -> used (ada session aktif)");
            }
        }

        $activeVouchers = Voucher::where('status', 'active')->get();

        foreach ($activeVouchers as $voucher) {
            $user = $mikrotik->getUserByUsername($voucher->username);

            if ($user === null) {
                $mikrotik->addHotspotUser(
                    $voucher->username,
                    $voucher->password,
                    null,
                    $voucher->duration_hours
                );
                $pushed++;
                $this->info("{$voucher->username} -> push ulang ke MikroTik");
            }
        }

        if ($markedUsed > 0 || $pushed > 0) {
            ActivityLog::create([
                'action' => 'Sync MikroTik (otomatis)',
                'details' => "Auto-sync: {$markedUsed} voucher ditandai terpakai, {$pushed} di-push ulang",
            ]);
        }

        $this->info("Selesai. {$markedUsed} voucher ditandai terpakai, {$pushed} di-push ulang.");
    }
}
