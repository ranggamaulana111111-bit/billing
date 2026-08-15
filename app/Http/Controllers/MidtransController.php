<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Setting;
use App\Services\Billing\BillingService;
use App\Services\Payment\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MidtransController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService,
        protected BillingService $billing,
    ) {}

    public function pay(Invoice $invoice)
    {
        if ($invoice->payment_status === 'paid') {
            return back()->with('error', 'Invoice ini sudah lunas.');
        }

        $result = $this->paymentService->createTransaction('midtrans', $invoice);

        if (! $result['success']) {
            return back()->with('error', 'Midtrans: '.$result['message']);
        }

        $snapToken = $result['token'];

        ActivityLog::log('Midtrans', 'Redirect pembayaran Midtrans: '.$invoice->invoice_display);

        return view('midtrans.pay', compact('snapToken', 'invoice'));
    }

    public function notification(Request $request)
    {
        $rawBody = $request->getContent();
        $data = json_decode($rawBody, true);

        $result = $this->paymentService->processWebhook('midtrans', $data);

        if (! $result['success']) {
            Log::warning('Midtrans webhook failed', ['data' => $data, 'result' => $result]);

            return response('OK', 200);
        }

        if (isset($result['invoice'])) {
            $invoice = $result['invoice'];
            $notification = $result['notification'];

            $this->billing->processPayment($invoice, 'midtrans', [
                'transaction_id' => $notification['transaction_id'] ?? null,
                'order_id' => $notification['order_id'] ?? null,
                'gross_amount' => $notification['gross_amount'] ?? $invoice->amount,
                'payment_type' => $notification['payment_type'] ?? 'midtrans',
            ]);
        }

        return response('OK', 200);
    }

    public function finish(Request $request)
    {
        $orderId = $request->get('order_id');
        $invoice = Invoice::allTenants()
            ->where('invoice_number', $orderId)
            ->orWhere('midtrans_order_id', $orderId)
            ->first();

        if ($invoice && $invoice->payment_status === 'paid') {
            return redirect()->route('invoices.index')->with('success', 'Pembayaran via Midtrans berhasil!');
        }

        return redirect()->route('invoices.index')->with('info', 'Pembayaran sedang diproses. Silakan cek kembali nanti.');
    }

    public function settings()
    {
        $config = [
            'midtrans_server_key' => Setting::get('midtrans_server_key', config('services.midtrans.server_key', '')),
            'midtrans_client_key' => Setting::get('midtrans_client_key', config('services.midtrans.client_key', '')),
            'midtrans_merchant_id' => Setting::get('midtrans_merchant_id', config('services.midtrans.merchant_id', '')),
            'midtrans_production' => Setting::get('midtrans_production', 'false'),
            'midtrans_enabled' => Setting::get('midtrans_enabled', 'false'),
        ];

        return view('payments.gateway', $config);
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'midtrans_server_key' => 'nullable|string|max:255',
            'midtrans_client_key' => 'nullable|string|max:255',
            'midtrans_merchant_id' => 'nullable|string|max:255',
            'midtrans_production' => 'required|in:true,false',
            'midtrans_enabled' => 'required|in:true,false',
        ]);

        foreach ($validated as $key => $value) {
            Setting::set($key, $value);
        }

        ActivityLog::log('Payment Gateway', 'Update konfigurasi Midtrans Payment Gateway');

        return back()->with('success', 'Pengaturan Payment Gateway berhasil disimpan.');
    }
}
