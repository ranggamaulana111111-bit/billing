<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class BillingProcess extends Command
{
    protected $signature = 'billing:process';

    protected $description = 'Generate invoice bulanan & kirim WA reminder otomatis';

    public function handle()
    {
        $today = Carbon::today();
        $month = $today->format('m');
        $year = $today->format('Y');
        $day = (int) $today->format('d');

        $this->info("=== Billing Process: {$today->format('d/m/Y')} ===");

        $customers = Customer::with('package')->get();

        if ($customers->isEmpty()) {
            $this->warn('Tidak ada pelanggan.');

            return 0;
        }

        $generated = 0;
        $reminders = 0;

        foreach ($customers as $customer) {
            if (! $customer->package) {
                $this->warn("Pelanggan {$customer->name} tidak punya paket, skip.");

                continue;
            }

            $dueDay = $customer->due_date ? (int) Carbon::parse($customer->due_date)->format('d') : null;

            // Generate invoice bulan ini jika belum ada
            $existing = Invoice::where('customer_id', $customer->id)
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->exists();

            if (! $existing) {
                $invoiceCode = 'INV-'.str_pad($customer->id, 4, '0', STR_PAD_LEFT).'-'.$month;
                $counter = 1;
                while (Invoice::where('invoice_code', $invoiceCode)->exists()) {
                    $invoiceCode = 'INV-'.str_pad($customer->id, 4, '0', STR_PAD_LEFT).'-'.$month.'-'.$counter;
                    $counter++;
                }

                Invoice::create([
                    'invoice_code' => $invoiceCode,
                    'customer_id' => $customer->id,
                    'amount' => $customer->package->price,
                    'payment_status' => 'unpaid',
                    'created_at' => $today,
                ]);

                $generated++;
                $this->info("Invoice {$invoiceCode} untuk {$customer->name}");
            }

            // Cek apakah perlu kirim reminder
            $unpaidInvoice = Invoice::where('customer_id', $customer->id)
                ->where('payment_status', 'unpaid')
                ->latest()
                ->first();

            if (! $unpaidInvoice || ! $customer->phone) {
                continue;
            }

            $shouldRemind = false;
            $reminderType = '';

            if ($dueDay) {
                $daysUntilDue = $dueDay - $day;

                // H-3
                if ($daysUntilDue === 3) {
                    $shouldRemind = true;
                    $reminderType = 'H-3';
                }
                // H-1
                if ($daysUntilDue === 1) {
                    $shouldRemind = true;
                    $reminderType = 'H-1';
                }
                // Hari H (jatuh tempo)
                if ($daysUntilDue === 0) {
                    $shouldRemind = true;
                    $reminderType = 'Jatuh Tempo';
                }
                // Telat 1, 3, 7 hari
                if (in_array(abs($daysUntilDue), [1, 3, 7]) && $daysUntilDue < 0) {
                    $shouldRemind = true;
                    $reminderType = 'Telat '.abs($daysUntilDue).' hari';
                }
            }

            if ($shouldRemind) {
                $this->sendWa($customer, $unpaidInvoice, $reminderType, $dueDay);
                $reminders++;
            }
        }

        $this->newLine();
        $this->info("Selesai. Invoice baru: {$generated}, Reminder: {$reminders}");

        ActivityLog::create([
            'action' => 'Billing Otomatis',
            'details' => "Generate {$generated} invoice, kirim {$reminders} reminder WA",
        ]);

        return 0;
    }

    private function sendWa(Customer $customer, Invoice $invoice, string $type, ?int $dueDay): void
    {
        $packageName = $customer->package->name ?? '-';
        $amount = 'Rp '.number_format($invoice->amount, 0, ',', '.');

        $typeLabel = match ($type) {
            'H-3' => '📅 *3 Hari Lagi Jatuh Tempo*',
            'H-1' => '⚠️ *Besok Jatuh Tempo*',
            'Jatuh Tempo' => '🔴 *Jatuh Tempo Hari Ini*',
            default => "🔔 *{$type}*",
        };

        $message = "━━━ *RABEGNET BILLING* ━━━\n\n"
            ."{$typeLabel}\n\n"
            ."Halo *{$customer->name}*\n\n"
            ."📋 *Tagihan Bulan Ini*\n"
            ."━━━━━━━━━━━━━━━━\n"
            ."Invoice : {$invoice->invoice_code}\n"
            ."Paket   : {$packageName}\n"
            ."Total   : {$amount}\n"
            ."Jatuh Tiap Tgl : {$dueDay}\n"
            ."Status  : ⏳ BELUM DIBAYAR\n"
            ."━━━━━━━━━━━━━━━━\n\n"
            ."Segera lakukan pembayaran untuk menikmati layanan tanpa putus.\n"
            ."Bayar via: QRIS / Transfer Bank\n\n"
            ."Terima kasih 🙏\n\n"
            .'━━━ *RabegNet* ━━━';

        try {
            Http::withHeaders([
                'Authorization' => Setting::get('fonnte_token') ?: config('services.fonnte.token'),
            ])->post('https://api.fonnte.com/send', [
                'target' => $customer->phone,
                'message' => $message,
                'countryCode' => '62',
            ]);

            $this->info("WA reminder {$type} ke {$customer->name} ({$customer->phone})");
        } catch (\Exception $e) {
            $this->error("Gagal WA ke {$customer->name}: {$e->getMessage()}");
        }
    }
}
