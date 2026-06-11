<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Services\MidtransService;
use Illuminate\Http\Request;

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
        $midtrans = new MidtransService;

        if (! $midtrans->isConfigured()) {
            return response('Midtrans not configured', 500);
        }

        $notif = $midtrans->handleNotification();

        if (! $notif['success']) {
            return response('OK', 200);
        }

        $orderId = $notif['order_id'];

        $invoice = Invoice::where('midtrans_order_id', $orderId)->first();

        if (! $invoice || $invoice['payment_status'] === 'paid') {
            return response('OK', 200);
        }

        $invoice->update([
            'payment_status' => 'paid',
            'paid_at' => now(),
            'payment_method' => 'midtrans',
        ]);

        ActivityLog::log('Pembayaran Online', 'Pembayaran via Midtrans: '.$invoice->invoice_code.' - Rp '.number_format($invoice->amount, 0, ',', '.'));

        return response('OK', 200);
    }

    public function finish(Request $request)
    {
        $orderId = $request->get('order_id');

        $invoice = Invoice::where('midtrans_order_id', $orderId)->first();

        if ($invoice && $invoice->payment_status === 'paid') {
            return redirect()->route('invoices.index')->with('success', 'Pembayaran via Midtrans berhasil!');
        }

        return redirect()->route('invoices.index')->with('info', 'Pembayaran sedang diproses. Silakan cek kembali nanti.');
    }
}
