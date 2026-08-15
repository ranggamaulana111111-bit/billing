<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Setting;
use App\Services\Billing\BillingService;
use App\Services\Payment\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class XenditController extends Controller
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

        $result = $this->paymentService->createTransaction('xendit', $invoice);

        if (! $result['success']) {
            return back()->with('error', 'Xendit: '.$result['message']);
        }

        if (! empty($result['invoice_id'])) {
            $invoice->update(['xendit_invoice_id' => $result['invoice_id']]);
        }

        ActivityLog::log('Xendit', 'Redirect pembayaran Xendit: '.$invoice->invoice_display);

        return redirect()->away($result['invoice_url']);
    }

    public function notification(Request $request)
    {
        $rawBody = $request->getContent();
        $data = json_decode($rawBody, true);

        if (! is_array($data)) {
            Log::warning('Xendit webhook: payload bukan JSON array', ['body' => substr((string) $rawBody, 0, 500)]);

            return response('OK', 200);
        }

        if (array_is_list($data)) {
            $data = ['invoice' => $data[0] ?? []];
        }

        $data['callback_token'] = $request->header('x-callback-token');

        $result = $this->paymentService->processWebhook('xendit', $data);

        if (! $result['success']) {
            Log::warning('Xendit webhook failed', ['data' => $data, 'result' => $result]);

            return response('OK', 200);
        }

        if (isset($result['invoice'])) {
            $invoice = $result['invoice'];
            $notification = $result['notification'];

            $this->billing->processPayment($invoice, 'xendit', [
                'transaction_id' => $notification['transaction_id'] ?? null,
                'order_id' => $notification['order_id'] ?? null,
                'gross_amount' => $notification['gross_amount'] ?? $invoice->amount,
                'payment_type' => 'xendit',
            ]);
        }

        return response('OK', 200);
    }

    public function finish(Request $request)
    {
        $orderId = $request->get('order_id');

        $invoice = Invoice::allTenants()
            ->where('invoice_number', $orderId)
            ->orWhere('xendit_invoice_id', $orderId)
            ->first();

        if ($invoice && $invoice->payment_status === 'paid') {
            return redirect()->route('portal.index')->with('success', 'Pembayaran via Xendit berhasil!');
        }

        return redirect()->route('portal.index')->with('info', 'Pembayaran sedang diproses. Silakan cek kembali nanti.');
    }

    public function settings()
    {
        $config = [
            'xendit_secret_key' => Setting::get('xendit_secret_key', config('services.xendit.secret_key', '')),
            'xendit_webhook_token' => Setting::get('xendit_webhook_token', config('services.xendit.webhook_token', '')),
            'xendit_is_production' => Setting::get('xendit_is_production', '0'),
            'xendit_enabled' => Setting::get('xendit_enabled', 'false'),
        ];

        return view('payments.gateway-xendit', $config);
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'xendit_secret_key' => 'nullable|string|max:255',
            'xendit_webhook_token' => 'nullable|string|max:255',
            'xendit_is_production' => 'required|in:0,1',
            'xendit_enabled' => 'required|in:true,false',
        ]);

        foreach ($validated as $key => $value) {
            Setting::set($key, $value);
        }

        ActivityLog::log('Payment Gateway', 'Update konfigurasi Xendit Payment Gateway');

        return back()->with('success', 'Pengaturan Payment Gateway Xendit berhasil disimpan.');
    }
}
