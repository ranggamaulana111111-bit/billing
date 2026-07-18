<?php

namespace App\Http\Controllers;

use App\Mail\InvoiceReminder;
use App\Mail\PaymentConfirmation;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Setting;
use App\Services\Billing\BillingService;
use App\Services\FonnteService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class InvoiceController extends Controller
{
    public function __construct(
        protected BillingService $billing,
    ) {}

    public function index(Request $request)
    {
        $query = Invoice::with('customer.package')->latest();

        $status = $request->get('status');
        if ($status) {
            $query->where('payment_status', $status);
        }

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('invoice_code', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($cq) => $cq->where('name', 'like', "%{$search}%")
                        ->orWhere('customer_code', 'like', "%{$search}%"));
            });
        }

        if ($from = $request->get('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->get('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $invoices = $query->paginate(20)->withQueryString();

        $unpaidStats = null;
        if ($status === 'unpaid') {
            $unpaidQuery = Invoice::where('payment_status', 'unpaid');
            $unpaidStats = [
                'total_amount' => (clone $unpaidQuery)->sum('amount'),
                'total_count' => (clone $unpaidQuery)->count(),
                'total_customers' => (clone $unpaidQuery)->distinct('customer_id')->count('customer_id'),
                'oldest_days' => null,
            ];
            $oldest = Invoice::where('payment_status', 'unpaid')->orderBy('created_at')->first();
            if ($oldest) {
                $unpaidStats['oldest_days'] = $oldest->created_at->diffInDays(now());
            }
        }

        $customerPaidMonths = [];
        $customerIds = $invoices->pluck('customer_id')->unique()->filter()->toArray();
        if (! empty($customerIds)) {
            $paidData = Invoice::whereIn('customer_id', $customerIds)
                ->where('payment_status', 'paid')
                ->whereNotNull('billing_period')
                ->selectRaw('customer_id, billing_period as ym')
                ->distinct()
                ->get()
                ->groupBy('customer_id')
                ->map(fn ($items) => $items->pluck('ym')->toArray())
                ->toArray();
            $customerPaidMonths = $paidData;
        }

        return view('invoices.index', compact('invoices', 'customerPaidMonths', 'unpaidStats', 'status'));
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
            'billing_period' => 'nullable|regex:/^\d{4}-\d{2}$/',
        ]);

        $customer = Customer::findOrFail($validated['customer_id']);
        $period = $validated['billing_period'] ?? now()->format('Y-m');

        $invoice = $this->billing->createInvoice($customer, $period, $validated['amount']);

        ActivityLog::log('Buat Tagihan', 'Tagihan manual untuk '.$customer->name.' - Rp '.number_format($validated['amount'], 0, ',', '.'));

        return redirect()->route('invoices.index')->with('success', 'Tagihan '.$invoice->invoice_number.' berhasil dibuat.');
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
            'billing_period' => 'nullable|regex:/^\d{4}-\d{2}$/',
        ]);

        $invoice->update($validated);

        ActivityLog::log('Ubah Tagihan', 'Mengubah tagihan '.$invoice->invoice_display);

        return redirect()->route('invoices.index')->with('success', 'Tagihan '.$invoice->invoice_display.' berhasil diperbarui.');
    }

    public function destroy(Invoice $invoice)
    {
        $number = $invoice->invoice_number;
        $invoice->delete();

        ActivityLog::log('Hapus Tagihan', 'Menghapus tagihan '.$number);

        return redirect()->route('invoices.index')->with('success', 'Tagihan '.$number.' berhasil dihapus.');
    }

    public function markPaid(Invoice $invoice)
    {
        if ($invoice->payment_status === 'paid') {
            return back()->with('error', 'Invoice ini sudah lunas.');
        }

        $this->billing->markInvoicePaid($invoice, 'manual');

        ActivityLog::log('Pembayaran', 'Pembayaran dari '.$invoice->customer->name.' - Rp '.number_format($invoice->amount, 0, ',', '.'));

        $this->sendWaNotification($invoice);

        return back()->with('success', 'Invoice '.$invoice->invoice_display.' berhasil dibayar. Notifikasi WA terkirim.');
    }

    public function print(Invoice $invoice)
    {
        $settings = Setting::pluck('value', 'key')->toArray();

        return view('invoices.print', compact('invoice', 'settings'));
    }

    public function printThermal(Invoice $invoice)
    {
        $settings = Setting::pluck('value', 'key')->toArray();

        return view('invoices.print-thermal', compact('invoice', 'settings'));
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

        $dueDay = $customer->due_date ? (int) Carbon::parse($customer->due_date)->format('d') : null;
        $graceDay = $dueDay ? $dueDay + 15 : null;
        $amount = 'Rp '.number_format($invoice->amount, 0, ',', '.');
        $packageName = $customer->package->name ?? '-';
        $graceLine = $graceDay ? "Masa Tenggang : Pada Tanggal {$graceDay} Setiap Bulan\n" : '';

        $message = "━━━ *ALKONEK BILLING* ━━━\n\n"
            .'🔔 *PEMEBERITAHUAN TEMPO TAGIHAN*'."\n\n"
            ."Halo YTH Bapak/Ibu, Mengetahui kenyamanan anda adalah prioritas kami. Kami ingin menginfokan bahwa :\n\n"
            ."📋 *Tagihan Anda Bulan ini*\n"
            ."━━━━━━━━━━━━━━━━\n"
            ."ID Pelanggan : {$customer->customer_code}\n"
            ."Invoice : {$invoice->invoice_display}\n"
            ."Paket   : {$packageName}\n"
            ."Jatuh Tempo : Pada Tanggal {$dueDay} Setiap Bulan\n"
            .$graceLine
            ."Total   : {$amount}\n"
            ."Status  : ⏳ BELUM DIBAYAR\n"
            ."━━━━━━━━━━━━━━━━\n\n"
            ."Kami Beritahukan Bahwa Layanan Anda Akan Masuk Ke Masa Tenggang, Pada Tanggal {$graceDay}. Dapat melakukan Pembayaran melalui DANA : 085770389267. atau pembayaran dapat dilakukan ditempat basecamp alkonek.\n"
            // .'Cek status tagihan anda di Portal : '.route('portal.index')."\n"
            ."Hubungi kami jika ada kendala.\n\n"
            ."Terima kasih 🙏\n\n"
            .'━━━ *PT Alkonek Network Access* ━━━';

        $result = (new FonnteService)->send($phone, $message);

        if (! $result['success']) {
            Log::error('Fonnte WA reminder gagal: '.($result['error'] ?? 'Unknown'));

            return back()->with('error', 'Gagal mengirim WA reminder: '.($result['error'] ?? ''));
        }

        ActivityLog::log('Reminder WA', 'Pengiriman reminder ke '.$customer->name.' - '.$invoice->invoice_display);

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

        ActivityLog::log('Reminder Email', 'Email reminder ke '.$customer->name.' ('.$customer->email.') - '.$invoice->invoice_display);

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

        ActivityLog::log('Email Payment', 'Email konfirmasi pembayaran ke '.$customer->name.' ('.$customer->email.') - '.$invoice->invoice_display);

        return back()->with('success', 'Email konfirmasi pembayaran berhasil dikirim.');
    }

    public function sendWaNotification(Invoice $invoice)
    {
        $customer = $invoice->customer;
        $phone = $customer->phone;

        if (! $phone) {
            return;
        }

        $message = "━━━ *ALKONEK BILLING* ━━━\n\n"
            ."✅ *PEMBAYARAN DITERIMA*\n\n"
            ."Halo YTH *{$customer->name}*, terima kasih!\n\n"
            ."📋 *Detail Pembayaran*\n"
            ."━━━━━━━━━━━━━━━━\n"
            ."Invoice : {$invoice->invoice_display}\n"
            ."Paket   : {$customer->package->name}\n"
            .'Total   : Rp '.number_format($invoice->amount, 0, ',', '.')."\n"
            ."Status  : ✅ LUNAS\n"
            .'Tanggal : '.now()->format('d/m/Y H:i')."\n"
            ."━━━━━━━━━━━━━━━━\n\n"
            ."Terima kasih telah melakukan pembayaran tepat waktu.\n"
            ."Nikmati layanan internet Anda!\n\n"
            .'━━━ *PT Alkonek Network Access* ━━━';

        (new FonnteService)->send($phone, $message);
    }

    public function downloadPdf(Invoice $invoice)
    {
        $settings = Setting::pluck('value', 'key')->toArray();

        ActivityLog::log('Download PDF Tagihan', 'Download PDF tagihan: '.$invoice->invoice_display.' - '.($invoice->customer->name ?? ''));

        $pdf = Pdf::loadView('invoices.pdf', compact('invoice', 'settings'));

        return $pdf->download("invoice-{$invoice->invoice_display}.pdf");
    }
}
