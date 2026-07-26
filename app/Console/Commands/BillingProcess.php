<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\Tenant;
use App\Services\Billing\InvoiceGenerator;
use App\Services\FonnteService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class BillingProcess extends Command
{
    protected $signature = 'billing:process';

    protected $description = 'Generate invoice bulanan & kirim WA reminder otomatis per tenant';

    public function handle()
    {
        $today = Carbon::today();
        $day = (int) $today->format('d');

        $this->info("=== Billing Process: {$today->format('d/m/Y')} ===");

        // Generate invoice hanya di awal bulan (tanggal 1)
        if ($day !== 1) {
            $this->info('Bukan tanggal 1, lewati generate invoice. (Reminder tetap diproses.)');
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
        $globalReminders = 0;

        foreach ($tenants as $tenant) {
            $this->info("--- Tenant: {$tenant->name} ({$tenant->id}) ---");

            $customers = Customer::where('tenant_id', $tenant->id)->with('package')->get();

            if ($customers->isEmpty()) {
                $this->warn("  Tidak ada pelanggan untuk {$tenant->name}.");

                continue;
            }

            $generated = 0;
            $reminders = 0;

            foreach ($customers as $customer) {
                if (! $customer->package) {
                    $this->warn("  Pelanggan {$customer->name} tidak punya paket, skip.");

                    continue;
                }

                // Override tempo ke tanggal 5 agar seragam
                $customerDueDate = $today->copy()->day(5)->format('Y-m-d');
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

                $unpaidInvoice = Invoice::where('customer_id', $customer->id)
                    ->where('payment_status', 'unpaid')
                    ->latest()
                    ->first();

                if (! $unpaidInvoice || ! $customer->phone) {
                    continue;
                }

                // Kirim WA reminder otomatis setiap tanggal 15-20
                $shouldRemind = $day >= 15 && $day <= 20;
                $reminderType = $shouldRemind ? 'Pengingat Tagihan' : '';

                if ($shouldRemind) {
                    $this->sendWa($tenant->id, $customer, $unpaidInvoice, $reminderType, $dueDay);
                    $reminders++;
                }
            }

            $this->newLine();
            $this->info("  Tenant {$tenant->name}: Invoice baru: {$generated}, Reminder: {$reminders}");

            $globalGenerated += $generated;
            $globalReminders += $reminders;
        }

        $this->newLine();
        $this->info("Selesai. Total invoice baru: {$globalGenerated}, Total reminder: {$globalReminders}");

        ActivityLog::create([
            'action' => 'Billing Otomatis',
            'details' => "Generate {$globalGenerated} invoice, kirim {$globalReminders} reminder WA (semua tenant)",
        ]);

        return 0;
    }

    private function sendWa(int $userId, Customer $customer, Invoice $invoice, string $type, ?int $dueDay): void
    {
        $packageName = $customer->package->name ?? '-';
        $amount = 'Rp '.number_format($invoice->amount, 0, ',', '.');
        $graceDay = $dueDay ? $dueDay + 15 : null;

        $typeLabel = match ($type) {
            'H-3' => '📅 *3 Hari Lagi Jatuh Tempo*',
            'H-1' => '⚠️ *Besok Jatuh Tempo*',
            'Jatuh Tempo' => '🔴 *Jatuh Tempo Hari Ini*',
            default => "🔔 *{$type}*",
        };

        $graceLine = $graceDay ? "Masa Tenggang : Pada Tanggal {$graceDay} Setiap Bulan\n" : '';

        $message = "━━━ *ALKONEK BILLING* ━━━\n\n"
            ."{$typeLabel}\n\n"
            ."Halo YTH Bapak/Ibu, Mengetahui kenyamanan anda adalah prioritas kami. Kami ingin menginfokan bahwa :\n\n"
            ."📋 *Tagihan Anda Bulan ini*\n"
            ."━━━━━━━━━━━━━━━━\n"
            ."ID Pelanggan : {$customer->customer_code}\n"
            ."Invoice : {$invoice->invoice_display}\n"
            ."Paket   : {$packageName}\n"
            ."Jatuh Tempo : Pada Tanggal {$dueDay} Setiap Bulan\n"
            .$graceLine
            ."Total   : {$amount}\n"
            ."Status  : ⏳ BELUM DIBAYAR\n"
            ."━━━━━━━━━━━━━━━━\n\n"
            ."Kami Beritahukan Bahwa Layanan Anda Akan Masuk Ke Masa Tenggang, Pada Tanggal {$graceDay}. Dapat melakukan Pembayaran melalui DANA : 089531559066. atau pembayaran dapat dilakukan ditempat basecamp alkonek.\n"
            .'Cek status tagihan anda di Portal : '.route('portal.index')."\n"
            ."Hubungi kami jika ada kendala.\n\n"
            ."Terima kasih 🙏\n\n"
            .'━━━ *PT Alkonek Network Access* ━━━';

        try {
            $token = Setting::get('fonnte_token', null, $userId);

            if (! $token) {
                $this->warn("  Token WA tidak dikonfigurasi untuk tenant ID {$userId}, skip WA");

                return;
            }

            $result = (new FonnteService($userId))->send($customer->phone, $message);

            if ($result['success']) {
                $this->info("  WA reminder {$type} ke {$customer->name} ({$customer->phone})");
            } else {
                $this->warn("  WA reminder {$type} ke {$customer->name} gagal: {$result['error']}");
            }
        } catch (\Exception $e) {
            $this->error("  Gagal WA ke {$customer->name}: {$e->getMessage()}");
        }
    }
}
