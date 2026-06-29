<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MidtransController extends Controller
{
    public function pay(Invoice $invoice)
    {
        if ($invoice->payment_status === 'paid') {
            return back()->with('error', 'Invoice ini sudah lunas.');
        }

        $midtrans = new MidtransService;

        if (! $midtrans->isConfigured()) {
            return back()->with('error', 'Midtrans belum dikonfigurasi. Isi Server Key di Pengaturan.');
        }

        $customer = $invoice->customer;
        $orderId = $invoice->invoice_code.'-'.time();

        $params = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => (int) $invoice->amount,
            ],
            'customer_details' => [
                'first_name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
            ],
            'item_details' => [
                [
                    'id' => $invoice->invoice_code,
                    'price' => (int) $invoice->amount,
                    'quantity' => 1,
                    'name' => 'Invoice '.$invoice->invoice_code.' - '.($customer->package->name ?? 'Internet'),
                ],
            ],
        ];

        $result = $midtrans->getSnapToken($params);

        if (! $result['success']) {
            return back()->with('error', 'Midtrans: '.$result['message']);
        }

        $snapToken = $result['token'];

        $invoice->update(['midtrans_order_id' => $orderId]);

        ActivityLog::log('Midtrans', 'Redirect pembayaran Midtrans: '.$invoice->invoice_code);

        return view('midtrans.pay', compact('snapToken', 'invoice'));
    }

    public function notification(Request $request)
    {
        $rawBody = $request->getContent();
        $data = json_decode($rawBody, true);
        $orderId = $data['order_id'] ?? null;

        if (! $orderId) {
            return response('OK', 200);
        }

        $invoice = Invoice::allTenants()->where('midtrans_order_id', $orderId)->first();

        if (! $invoice) {
            return response('OK', 200);
        }

        // Idempotensi: jika sudah paid, potong langsung
        if ($invoice->payment_status === 'paid') {
            return response('OK', 200);
        }

        $midtrans = new MidtransService($invoice->tenant_id);

        if (! $midtrans->isConfigured()) {
            return response('Midtrans not configured', 500);
        }

        // Verifikasi signature_key SHA512 (Midtrans keamanan)
        $serverKey = config('services.midtrans.server_key') ?: $midtrans->getServerKey();
        $signature = $data['signature_key'] ?? '';
        $expectedSignature = hash('sha512', $orderId.$data['status_code'].$data['gross_amount'].$serverKey);

        if (! hash_equals($expectedSignature, $signature)) {
            return response('Invalid signature', 403);
        }

        $notif = $midtrans->handleNotification();

        if (! $notif['success']) {
            return response('OK', 200);
        }

        DB::transaction(function () use ($invoice) {
            $invoice->update([
                'payment_status' => 'paid',
                'paid_at' => now(),
                'payment_method' => 'midtrans',
            ]);

            ActivityLog::log('Pembayaran Online', 'Pembayaran via Midtrans: '.$invoice->invoice_code.' - Rp '.number_format($invoice->amount, 0, ',', '.'));
        });

        return response('OK', 200);
    }

    public function finish(Request $request)
    {
        $orderId = $request->get('order_id');

        $invoice = Invoice::allTenants()->where('midtrans_order_id', $orderId)->first();

        if ($invoice && $invoice->payment_status === 'paid') {
            return redirect()->route('invoices.index')->with('success', 'Pembayaran via Midtrans berhasil!');
        }

        return redirect()->route('invoices.index')->with('info', 'Pembayaran sedang diproses. Silakan cek kembali nanti.');
    }
}
