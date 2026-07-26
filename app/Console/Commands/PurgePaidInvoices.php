<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Console\Command;

class PurgePaidInvoices extends Command
{
    protected $signature = 'invoices:purge-paid';

    protected $description = 'Soft-delete invoice lunas yang berumur lebih dari 1 bulan dari halaman invoice (tetap tercatat di laporan keuangan)';

    public function handle()
    {
        // Invoice lunas dengan paid_at <= 1 bulan lalu
        $cutoff = Carbon::today()->subMonth()->startOfDay();

        $invoices = Invoice::where('payment_status', 'paid')
            ->whereNotNull('paid_at')
            ->where('paid_at', '<=', $cutoff)
            ->get();

        $count = 0;
        foreach ($invoices as $invoice) {
            $invoice->delete(); // soft delete
            $count++;
        }

        $this->info("Purge selesai: {$count} invoice lunas (umur > 1 bulan) di-soft-delete.");

        if ($count > 0) {
            ActivityLog::log('Purge Invoice Lunas', "Soft-delete {$count} invoice lunas (umur > 1 bulan) dari halaman invoice.");
        }

        return 0;
    }
}
