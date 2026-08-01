<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Incident;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\User;
use App\Services\FonnteService;
use App\Services\Payment\PaymentService;
use Illuminate\Http\Request;

class PortalController extends Controller
{
    public function index()
    {
        $firstUser = User::orderBy('id')->first();
        $uid = $firstUser?->id;
        $company = [
            'name' => Setting::get('company_name', 'ALKONEK', $uid),
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

        $input = trim($request->input('phone'));

        $customer = null;

        // Cari by ID Pelanggan (172...) atau ID numeric
        if (preg_match('/^172\d{11}$/', $input)) {
            $customer = Customer::allTenants()->where('customer_code', $input)->first();
        } elseif (ctype_digit($input) && strlen($input) < 14) {
            $customer = Customer::allTenants()->find((int) $input);
        }

        // Fallback: cari by nomor telepon (normalisasi format 0xx / 62xx / 8xx)
        if (! $customer) {
            $canonical = FonnteService::cleanPhone($input);

            if ($canonical !== '') {
                $customer = Customer::allTenants()
                    ->where(function ($q) use ($canonical) {
                        $q->where('phone', $canonical)
                            ->orWhere('phone', '0'.$canonical)
                            ->orWhere('phone', '62'.$canonical);
                    })
                    ->first();
            }
        }

        if (! $customer) {
            if ($request->wantsJson()) {
                return response()->json([
                    'found' => false,
                    'message' => 'Nomor telepon tidak ditemukan.',
                ], 404);
            }

            return back()->with('error', 'Nomor telepon tidak ditemukan.')->withInput();
        }

        $invoices = Invoice::allTenants()->where('customer_id', $customer->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $company = [
            'name' => Setting::get('company_name', 'ALKONEK', $customer->tenant_id),
            'address' => Setting::get('company_address', '', $customer->tenant_id),
            'phone' => Setting::get('company_phone', '', $customer->tenant_id),
        ];

        $midtransConfigured = app(PaymentService::class)->getGateway('midtrans')?->isConfigured() ?? false;

        $incidents = collect();
        if ($customer->odp_id) {
            $incidents = Incident::withoutGlobalScope('tenant_id')
                ->where('odp_id', $customer->odp_id)
                ->whereIn('status', ['open', 'investigating'])
                ->orderBy('created_at', 'desc')
                ->get();
        }

        if ($request->wantsJson()) {
            $html = view('portal.partials.invoice-modal', compact('customer', 'invoices', 'company', 'midtransConfigured', 'incidents'))->render();

            return response()->json([
                'found' => true,
                'html' => $html,
            ]);
        }

        return view('portal.invoices', compact('customer', 'invoices', 'company', 'midtransConfigured', 'incidents'));
    }

    public function bayar(Invoice $invoice, PaymentService $paymentService)
    {
        $invoice = Invoice::allTenants()->findOrFail($invoice->id);

        if ($invoice->payment_status === 'paid') {
            return redirect()->route('portal.index')->with('error', 'Invoice ini sudah lunas.');
        }

        $customer = $invoice->customer;

        $result = $paymentService->createTransaction('midtrans', $invoice);

        if (! $result['success']) {
            return back()->with('error', $result['message']);
        }

        ActivityLog::log('Portal Bayar', 'Pembayaran portal untuk '.$customer->name.' - '.$invoice->invoice_display);

        $snapToken = $result['token'];

        return view('portal.pay', compact('snapToken', 'invoice'));
    }

    public function finish(Request $request)
    {
        $orderId = $request->get('order_id');

        $invoice = Invoice::where('midtrans_order_id', $orderId)->first();

        if ($invoice && $invoice->payment_status === 'paid') {
            return redirect()->route('portal.index')->with('success', 'Pembayaran berhasil! Terima kasih atas kepercayaan dan kesetiaan Anda bersama PT. Alkonek Network Access. Kenyamanan Anda adalah prioritas kami.');
        }

        return redirect()->route('portal.index')->with('info', 'Pembayaran sedang diproses.');
    }
}
