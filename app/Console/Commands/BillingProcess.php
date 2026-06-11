<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class BillingProcess extends Command
{
    protected $signature = 'billing:process';

    protected $description = 'Generate invoice bulanan & kirim WA reminder otomatis per tenant';

    public function handle()
    {
        $today = Carbon::today();
        $day = (int) $today->format('d');

        $this->info("=== Billing Process: {$today->format('d/m/Y')} ===");

        $users = User::all();

        if ($users->isEmpty()) {
            $this->warn('Tidak ada user.');

            return 0;
        }

        $globalGenerated = 0;
        $globalReminders = 0;

        foreach ($users as $user) {
            $this->info("--- Tenant: {$user->name} ({$user->email}) ---");

            $month = $today->format('m');
            $year = $today->format('Y');

            $customers = Customer::where('user_id', $user->id)->with('package')->get();

            if ($customers->isEmpty()) {
                $this->warn("  Tidak ada pelanggan untuk {$user->name}.");

                continue;
            }

            $generated = 0;
            $reminders = 0;

            foreach ($customers as $customer) {
                if (! $customer->package) {
                    $this->warn("  Pelanggan {$customer->name} tidak punya paket, skip.");

                    continue;
                }

                $dueDay = $customer->due_date ? (int) Carbon::parse($customer->due_date)->format('d') : null;

                $existing = Invoice::where('user_id', $user->id)
                    ->where('customer_id', $customer->id)
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
                        'user_id' => $user->id,
                        'invoice_code' => $invoiceCode,
                        'customer_id' => $customer->id,
                        'amount' => $customer->package->price,
                        'payment_status' => 'unpaid',
                        'created_at' => $today,
                    ]);

                    $generated++;
                    $this->info("  Invoice {$invoiceCode} untuk {$customer->name}");
                }

                $unpaidInvoice = Invoice::where('user_id', $user->id)
                    ->where('customer_id', $customer->id)
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

                    if ($daysUntilDue === 3) {
                        $shouldRemind = true;
                        $reminderType = 'H-3';
                    }
                    if ($daysUntilDue === 1) {
                        $shouldRemind = true;
                        $reminderType = 'H-1';
                    }
                    if ($daysUntilDue === 0) {
                        $shouldRemind = true;
                        $reminderType = 'Jatuh Tempo';
                    }
                    if (in_array(abs($daysUntilDue), [1, 3, 7]) && $daysUntilDue < 0) {
                        $shouldRemind = true;
                        $reminderType = 'Telat '.abs($daysUntilDue).' hari';
                    }
                }

                if ($shouldRemind) {
                    $this->sendWa($user->id, $customer, $unpaidInvoice, $reminderType, $dueDay);
                    $reminders++;
                }
            }

            $this->newLine();
            $this->info("  Tenant {$user->name}: Invoice baru: {$generated}, Reminder: {$reminders}");

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
            $token = Setting::get('fonnte_token', null, $userId);

            if (! $token) {
                $this->warn("  Token WA tidak dikonfigurasi untuk tenant ID {$userId}, skip WA");

                return;
            }

            Http::withHeaders([
                'Authorization' => $token,
            ])->post('https://api.fonnte.com/send', [
                'target' => $customer->phone,
                'message' => $message,
                'countryCode' => '62',
            ]);

            $this->info("  WA reminder {$type} ke {$customer->name} ({$customer->phone})");
        } catch (\Exception $e) {
            $this->error("  Gagal WA ke {$customer->name}: {$e->getMessage()}");
        }
    }
}
