<?php

namespace App\Http\Controllers;

use App\Mail\InvoiceReminder;
use App\Mail\PaymentConfirmation;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Invoice::with('customer.package')->latest();

        if ($status = $request->get('status')) {
            $query->where('payment_status', $status);
        }

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_code', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($cq) => $cq->where('name', 'like', "%{$search}%"));
            });
        }

        if ($from = $request->get('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->get('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $invoices = $query->paginate(20)->withQueryString();

        return view('invoices.index', compact('invoices'));
    }

    public function create()
    {
        $customers = Customer::with('package')->where('status', '!=', 'inactive')->orderBy('name')->get();

        return view('invoices.create', compact('customers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'amount' => 'required|numeric|min:1',
            'due_date' => 'nullable|date',
        ]);

        $customer = Customer::findOrFail($validated['customer_id']);
        $month = now()->format('m');
        $invoiceCode = 'INV-'.str_pad($customer->id, 4, '0', STR_PAD_LEFT).'-'.$month;
        $counter = 1;
        while (Invoice::where('invoice_code', $invoiceCode)->exists()) {
            $invoiceCode = 'INV-'.str_pad($customer->id, 4, '0', STR_PAD_LEFT).'-'.$month.'-'.$counter;
            $counter++;
        }

        $invoice = Invoice::create([
            'invoice_code' => $invoiceCode,
            'customer_id' => $customer->id,
            'amount' => $validated['amount'],
            'payment_status' => 'unpaid',
        ]);

        ActivityLog::log('Buat Tagihan', 'Tagihan manual untuk '.$customer->name.' - Rp '.number_format($validated['amount'], 0, ',', '.'));

        return redirect()->route('invoices.index')->with('success', 'Tagihan '.$invoiceCode.' berhasil dibuat.');
    }

    public function edit(Invoice $invoice)
    {
        $customers = Customer::with('package')->orderBy('name')->get();

        return view('invoices.edit', compact('invoice', 'customers'));
    }

    public function update(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'amount' => 'required|numeric|min:1',
        ]);

        $invoice->update($validated);

        ActivityLog::log('Ubah Tagihan', 'Mengubah tagihan '.$invoice->invoice_code);

        return redirect()->route('invoices.index')->with('success', 'Tagihan '.$invoice->invoice_code.' berhasil diperbarui.');
    }

    public function destroy(Invoice $invoice)
    {
        $code = $invoice->invoice_code;
        $invoice->delete();

        ActivityLog::log('Hapus Tagihan', 'Menghapus tagihan '.$code);

        return redirect()->route('invoices.index')->with('success', 'Tagihan '.$code.' berhasil dihapus.');
    }

    public function markPaid(Invoice $invoice)
    {
        if ($invoice->payment_status === 'paid') {
            return back()->with('error', 'Invoice ini sudah lunas.');
        }

        $invoice->update(['payment_status' => 'paid']);

        ActivityLog::log('Pembayaran', 'Pembayaran dari '.$invoice->customer->name.' - Rp '.number_format($invoice->amount, 0, ',', '.'));

        $this->sendWaNotification($invoice);

        return back()->with('success', 'Invoice '.$invoice->invoice_code.' berhasil dibayar. Notifikasi WA terkirim.');
    }

    public function print(Invoice $invoice)
    {
        $settings = Setting::pluck('value', 'key')->toArray();

        return view('invoices.print', compact('invoice', 'settings'));
    }

    public function sendReminder(Invoice $invoice)
    {
        $customer = $invoice->customer;
        $phone = $customer->phone;

        if (! $phone) {
            return back()->with('error', 'Nomor WA pelanggan tidak tersedia.');
        }

        if ($invoice->payment_status === 'paid') {
            return back()->with('error', 'Invoice ini sudah lunas.');
        }

        $message = "━━━ *RABEGNET BILLING* ━━━\n\n"
            ."🔔 *PENGINGAT PEMBAYARAN*\n\n"
            ."Halo *{$customer->name}*, kami ingatkan ya!\n\n"
            ."📋 *Tagihan Anda*\n"
            ."━━━━━━━━━━━━━━━━\n"
            ."Invoice : {$invoice->invoice_code}\n"
            ."Paket   : {$customer->package->name}\n"
            .'Total   : Rp '.number_format($invoice->amount, 0, ',', '.')."\n"
            ."Status  : ⏳ BELUM DIBAYAR\n"
            ."━━━━━━━━━━━━━━━━\n\n"
            ."Segera lakukan pembayaran sebelum jatuh tempo.\n"
            ."Hubungi kami jika ada kendala.\n\n"
            ."Terima kasih 🙏\n\n"
            .'━━━ *RabegNet* ━━━';

        try {
            Http::withHeaders([
                'Authorization' => Setting::get('fonnte_token') ?: config('services.fonnte.token'),
            ])->post('https://api.fonnte.com/send', [
                'target' => $phone,
                'message' => $message,
                'countryCode' => '62',
            ]);
        } catch (\Exception $e) {
            Log::error('Fonnte WA reminder gagal: '.$e->getMessage());

            return back()->with('error', 'Gagal mengirim WA reminder.');
        }

        ActivityLog::log('Reminder WA', 'Pengiriman reminder ke '.$customer->name.' - '.$invoice->invoice_code);

        return back()->with('success', 'WA reminder berhasil dikirim ke '.$customer->name);
    }

    public function sendEmailReminder(Invoice $invoice)
    {
        $customer = $invoice->customer;

        if (! $customer->email) {
            return back()->with('error', 'Email pelanggan tidak tersedia.');
        }

        try {
            Mail::to($customer->email)->send(new InvoiceReminder($invoice));
        } catch (\Exception $e) {
            Log::error('Email reminder gagal: '.$e->getMessage());

            return back()->with('error', 'Gagal mengirim email reminder.');
        }

        ActivityLog::log('Reminder Email', 'Email reminder ke '.$customer->name.' ('.$customer->email.') - '.$invoice->invoice_code);

        return back()->with('success', 'Email reminder berhasil dikirim ke '.$customer->email);
    }

    public function sendEmailPayment(Invoice $invoice)
    {
        $customer = $invoice->customer;

        if (! $customer->email) {
            return back()->with('error', 'Email pelanggan tidak tersedia.');
        }

        try {
            Mail::to($customer->email)->send(new PaymentConfirmation($invoice));
        } catch (\Exception $e) {
            Log::error('Email payment gagal: '.$e->getMessage());

            return back()->with('error', 'Gagal mengirim email konfirmasi pembayaran.');
        }

        ActivityLog::log('Email Payment', 'Email konfirmasi pembayaran ke '.$customer->name.' ('.$customer->email.') - '.$invoice->invoice_code);

        return back()->with('success', 'Email konfirmasi pembayaran berhasil dikirim.');
    }

    public function sendWaNotification(Invoice $invoice)
    {
        $customer = $invoice->customer;
        $phone = $customer->phone;

        if (! $phone) {
            return;
        }

        $message = "━━━ *RABEGNET BILLING* ━━━\n\n"
            ."✅ *PEMBAYARAN DITERIMA*\n\n"
            ."Halo *{$customer->name}*, terima kasih!\n\n"
            ."📋 *Detail Pembayaran*\n"
            ."━━━━━━━━━━━━━━━━\n"
            ."Invoice : {$invoice->invoice_code}\n"
            ."Paket   : {$customer->package->name}\n"
            .'Total   : Rp '.number_format($invoice->amount, 0, ',', '.')."\n"
            ."Status  : ✅ LUNAS\n"
            .'Tanggal : '.now()->format('d/m/Y H:i')."\n"
            ."━━━━━━━━━━━━━━━━\n\n"
            ."Terima kasih telah melakukan pembayaran tepat waktu.\n"
            ."Nikmati layanan internet Anda!\n\n"
            .'━━━ *RabegNet* ━━━';

        try {
            Http::withHeaders([
                'Authorization' => Setting::get('fonnte_token') ?: config('services.fonnte.token'),
            ])->post('https://api.fonnte.com/send', [
                'target' => $phone,
                'message' => $message,
                'countryCode' => '62',
            ]);
        } catch (\Exception $e) {
            Log::error('Fonnte WA gagal: '.$e->getMessage());
        }
    }

    public function downloadPdf(Invoice $invoice)
    {
        $settings = Setting::pluck('value', 'key')->toArray();
        $pdf = Pdf::loadView('invoices.pdf', compact('invoice', 'settings'));

        return $pdf->download("invoice-{$invoice->invoice_code}.pdf");
    }
}
