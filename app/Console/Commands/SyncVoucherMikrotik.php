<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Voucher;
use App\Services\MikrotikService;
use Illuminate\Console\Command;

class SyncVoucherMikrotik extends Command
{
    protected $signature = 'voucher:sync-mikrotik';

    protected $description = 'Sinkronasi status voucher dengan MikroTik (auto-mark used jika ada session aktif)';

    public function handle()
    {
        $mikrotik = new MikrotikService;

        if (! $mikrotik->isConfigured()) {
            $this->warn('MikroTik tidak dikonfigurasi.');

            return;
        }

        Voucher::where('status', 'active')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);

        $activeVouchers = Voucher::where('status', 'active')->get();
        $markedUsed = 0;
        $pushed = 0;

        foreach ($activeVouchers as $voucher) {
            $sessions = $mikrotik->getUserActiveSessions($voucher->username);

            if (! empty($sessions)) {
                $voucher->update([
                    'status' => 'used',
                    'used_at' => now(),
                ]);
                $markedUsed++;
                $this->info("{$voucher->username} → used (ada session aktif)");
            }
        }

        $activeVouchers = Voucher::where('status', 'active')->get();

        foreach ($activeVouchers as $voucher) {
            $user = $mikrotik->getUserByUsername($voucher->username);

            if ($user === null) {
                $mikrotik->addHotspotUser(
                    $voucher->username,
                    $voucher->password,
                    'all',
                    $voucher->duration_hours
                );
                $pushed++;
                $this->info("{$voucher->username} → push ulang ke MikroTik");
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
