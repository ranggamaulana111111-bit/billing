<?php

namespace App\Services\Billing;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Payment\PaymentService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BillingService
{
    public function __construct(
        protected CustomerCodeGenerator $customerCodeGenerator,
        protected InvoiceGenerator $invoiceGenerator,
        protected PaymentService $paymentService,
    ) {}

    public function createCustomer(array $data): Customer
    {
        $data['customer_code'] = $this->customerCodeGenerator->generate();

        $customer = Customer::create($data);

        Log::info('Customer created', [
            'customer_id' => $customer->id,
            'customer_code' => $customer->customer_code,
        ]);

        return $customer;
    }

    public function createInvoice(Customer $customer, ?string $period = null, ?float $amount = null): Invoice
    {
        $period = $period ?? now()->format('Y-m');
        $amount = $amount ?? ($customer->package->price ?? 0);

        $invoiceNumber = $this->invoiceGenerator->generate($customer->customer_code, $period);
        $dueDay = $customer->due_date ? Carbon::parse($customer->due_date)->format('Y-m-d') : now()->endOfMonth()->format('Y-m-d');

        $invoice = Invoice::create([
            'invoice_number' => $invoiceNumber,
            'invoice_code' => $invoiceNumber,
            'customer_id' => $customer->id,
            'amount' => $amount,
            'payment_status' => 'unpaid',
            'billing_period' => $period,
            'period' => $period,
            'status' => 'unpaid',
        ]);

        Log::info('Invoice created', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoiceNumber,
            'customer_code' => $customer->customer_code,
            'amount' => $amount,
        ]);

        return $invoice;
    }

    public function processPayment(Invoice $invoice, string $gateway, array $gatewayData): Payment
    {
        return DB::transaction(function () use ($invoice, $gateway, $gatewayData) {
            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'gateway' => $gateway,
                'gateway_transaction_id' => $gatewayData['transaction_id'] ?? null,
                'gateway_order_id' => $gatewayData['order_id'] ?? null,
                'amount' => $gatewayData['gross_amount'] ?? $invoice->amount,
                'payment_method' => $gatewayData['payment_type'] ?? $gateway,
                'payment_date' => now(),
                'paid_at' => now(),
                'status' => 'paid',
                'notes' => 'Payment via '.$gateway,
            ]);

            $this->markInvoicePaid($invoice, $gateway, $gatewayData);

            ActivityLog::log('Pembayaran Online', 'Pembayaran via '.$gateway.': '.$invoice->invoice_number.' - Rp '.number_format($invoice->amount, 0, ',', '.'));

            return $payment;
        });
    }

    public function markInvoicePaid(Invoice $invoice, ?string $method = null, ?array $meta = null): void
    {
        $invoice->update([
            'payment_status' => 'paid',
            'status' => 'paid',
            'paid_at' => now(),
            'payment_method' => $method,
        ]);

        if (! $invoice->payments()->where('status', 'paid')->exists()) {
            Payment::create([
                'invoice_id' => $invoice->id,
                'payment_method' => $method ?? 'manual',
                'amount' => $invoice->amount,
                'payment_date' => now(),
                'paid_at' => now(),
                'status' => 'paid',
                'notes' => 'Marked paid manually',
            ]);
        }
    }

    public function generateMonthlyInvoices(?Carbon $date = null): int
    {
        $date = $date ?? now();
        $period = $date->format('Y-m');
        $generated = 0;

        $customers = Customer::with('package')
            ->where('status', 'active')
            ->whereNotNull('package_id')
            ->get();

        foreach ($customers as $customer) {
            $exists = Invoice::where('customer_id', $customer->id)
                ->where('billing_period', $period)
                ->exists();

            if (! $exists) {
                $this->createInvoice($customer, $period);
                $generated++;
            }
        }

        return $generated;
    }

    public function getUnpaidInvoices(): Collection
    {
        return Invoice::with('customer.package')
            ->where('payment_status', 'unpaid')
            ->get();
    }

    public function getInvoiceSummary(Invoice $invoice): array
    {
        $customer = $invoice->customer;
        $dueDay = $customer?->due_date ? (int) Carbon::parse($customer->due_date)->format('d') : null;
        $graceDay = $dueDay ? $dueDay + 15 : null;

        return [
            'invoice' => $invoice,
            'customer' => $customer,
            'due_day' => $dueDay,
            'grace_day' => $graceDay,
            'is_overdue' => $invoice->payment_status === 'unpaid' && $dueDay && now()->day > $dueDay,
            'days_overdue' => $dueDay ? max(0, now()->day - $dueDay) : 0,
        ];
    }
}
