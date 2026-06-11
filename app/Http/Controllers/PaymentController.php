<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function create(Invoice $invoice)
    {
        if ($invoice->payment_status === 'paid') {
            return back()->with('error', 'Invoice ini sudah lunas.');
        }

        return view('payments.create', compact('invoice'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|in:cash,transfer,qris,midtrans',
            'payment_date' => 'required|date',
            'notes' => 'nullable|string|max:500',
        ]);

        $invoice = Invoice::findOrFail($validated['invoice_id']);

        if ($invoice->payment_status === 'paid') {
            return back()->with('error', 'Invoice ini sudah lunas.');
        }

        $payment = Payment::create($validated);

        $invoice->update([
            'payment_status' => 'paid',
            'paid_at' => $validated['payment_date'].' '.now()->format('H:i:s'),
            'payment_method' => $validated['payment_method'],
        ]);

        ActivityLog::log('Pembayaran', 'Pembayaran Rp '.number_format($payment->amount, 0, ',', '.').' dari '.$invoice->customer->name.' ('.$validated['payment_method'].')');

        return redirect()->route('invoices.index')->with('success', 'Pembayaran berhasil dicatat untuk invoice '.$invoice->invoice_code);
    }

    public function destroy(Payment $payment)
    {
        $invoice = $payment->invoice;
        $payment->delete();

        $totalPayments = $invoice->payments()->sum('amount');
        if ($totalPayments <= 0) {
            $invoice->update(['payment_status' => 'unpaid', 'paid_at' => null, 'payment_method' => null]);
        }

        ActivityLog::log('Hapus Pembayaran', 'Menghapus pembayaran invoice '.$invoice->invoice_code);

        return back()->with('success', 'Pembayaran berhasil dihapus.');
    }

    public function history(Invoice $invoice)
    {
        $payments = $invoice->payments()->latest()->get();

        return view('payments.history', compact('invoice', 'payments'));
    }
}
