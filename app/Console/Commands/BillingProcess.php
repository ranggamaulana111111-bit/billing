<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\Billing\InvoiceGenerator;
use Carbon\Carbon;
use Illuminate\Console\Command;

class BillingProcess extends Command
{
    protected $signature = 'billing:process';

    protected $description = 'Generate invoice bulanan otomatis per tenant';

    public function handle()
    {
        $today = Carbon::today();
        $day = (int) $today->format('d');

        $this->info("=== Billing Process: {$today->format('d/m/Y')} ===");

        // Generate invoice hanya di awal bulan (tanggal 1)
        if ($day !== 1) {
            $this->info('Bukan tanggal 1, lewati generate invoice.');
        }

        // Berlaku mulai periode Agustus 2026
        $billingPeriod = $today->format('Y-m');
        $startPeriod = '2026-08';

        if ($billingPeriod < $startPeriod) {
            $this->warn("Periode {$billingPeriod} belum masa berlaku (mulai {$startPeriod}), skip generate.");

            return 0;
        }

        // Tempo default semua pelanggan = tanggal 5
        $dueDay = 5;

        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('Tidak ada tenant.');

            return 0;
        }

        $generator = app(InvoiceGenerator::class);

        $globalGenerated = 0;

        foreach ($tenants as $tenant) {
            $this->info("--- Tenant: {$tenant->name} ({$tenant->id}) ---");

            $customers = Customer::where('tenant_id', $tenant->id)->with('package')->get();

            if ($customers->isEmpty()) {
                $this->warn("  Tidak ada pelanggan untuk {$tenant->name}.");

                continue;
            }

            $generated = 0;

            foreach ($customers as $customer) {
                if (! $customer->package) {
                    $this->warn("  Pelanggan {$customer->name} tidak punya paket, skip.");

                    continue;
                }

                // Override tempo ke tanggal 5 agar seragam
                $customerDueDate = $today->copy()->day($dueDay)->format('Y-m-d');
                if ($customer->due_date !== $customerDueDate) {
                    $customer->update(['due_date' => $customerDueDate]);
                }

                $existing = Invoice::withTrashed()
                    ->where('customer_id', $customer->id)
                    ->where('billing_period', $billingPeriod)
                    ->exists();

                if ($day === 1 && ! $existing) {
                    $invoiceNumber = $generator->generate($customer->customer_code, $billingPeriod);

                    Invoice::create([
                        'invoice_number' => $invoiceNumber,
                        'invoice_code' => $invoiceNumber,
                        'customer_id' => $customer->id,
                        'amount' => $customer->package->price,
                        'payment_status' => 'unpaid',
                        'billing_period' => $billingPeriod,
                        'period' => $billingPeriod,
                        'status' => 'unpaid',
                    ]);

                    $generated++;
                    $this->info("  Invoice {$invoiceNumber} untuk {$customer->name}");
                }
            }

            $this->newLine();
            $this->info("  Tenant {$tenant->name}: Invoice baru: {$generated}");

            $globalGenerated += $generated;
        }

        $this->newLine();
        $this->info("Selesai. Total invoice baru: {$globalGenerated}");

        ActivityLog::create([
            'action' => 'Billing Otomatis',
            'details' => "Generate {$globalGenerated} invoice (semua tenant)",
        ]);

        return 0;
    }
}
