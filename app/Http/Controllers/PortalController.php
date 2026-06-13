<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\User;
use App\Services\MidtransService;
use Illuminate\Http\Request;

class PortalController extends Controller
{
    public function index()
    {
        $firstUser = User::orderBy('id')->first();
        $uid = $firstUser?->id;
        $company = [
            'name' => Setting::get('company_name', 'RabegNet', $uid),
            'address' => Setting::get('company_address', '', $uid),
            'phone' => Setting::get('company_phone', '', $uid),
        ];

        return view('portal.index', compact('company'));
    }

    public function lookup(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|max:20',
        ]);

        $phone = $request->input('phone');

        $customer = Customer::allUsers()->where('phone', $phone)->first();

        if (! $customer) {
            return back()->with('error', 'Nomor telepon tidak ditemukan.')->withInput();
        }

        $invoices = Invoice::allUsers()->where('customer_id', $customer->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $company = [
            'name' => Setting::get('company_name', 'RabegNet', $customer->user_id),
            'address' => Setting::get('company_address', '', $customer->user_id),
            'phone' => Setting::get('company_phone', '', $customer->user_id),
        ];

        $midtransConfigured = (new MidtransService)->isConfigured();

        return view('portal.invoices', compact('customer', 'invoices', 'company', 'midtransConfigured'));
    }

    public function bayar(Invoice $invoice)
    {
        $invoice = Invoice::allUsers()->findOrFail($invoice->id);

        if ($invoice->payment_status === 'paid') {
            return redirect()->route('portal.index')->with('error', 'Invoice ini sudah lunas.');
        }

        $midtrans = new MidtransService($customer->user_id);

        if (! $midtrans->isConfigured()) {
            return back()->with('error', 'Pembayaran online belum tersedia.');
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
        ];

        $result = $midtrans->getSnapToken($params);

        if (! $result['success']) {
            return back()->with('error', 'Midtrans: '.$result['message']);
        }

        $invoice->update(['midtrans_order_id' => $orderId]);

        $snapToken = $result['token'];

        return view('portal.pay', compact('snapToken', 'invoice'));
    }

    public function finish(Request $request)
    {
        $orderId = $request->get('order_id');

        $invoice = Invoice::where('midtrans_order_id', $orderId)->first();

        if ($invoice && $invoice->payment_status === 'paid') {
            return redirect()->route('portal.index')->with('success', 'Pembayaran berhasil! Terima kasih.');
        }

        return redirect()->route('portal.index')->with('info', 'Pembayaran sedang diproses.');
    }
}
