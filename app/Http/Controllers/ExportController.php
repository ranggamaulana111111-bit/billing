<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class ExportController extends Controller
{
    public function invoices(Request $request)
    {
        $query = Invoice::with('customer.package');

        if ($status = $request->get('status')) {
            $query->where('payment_status', $status);
        }
        if ($from = $request->get('from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->get('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $invoices = $query->latest()->get();

        ActivityLog::log('Export Invoice', 'Export '.$invoices->count().' invoice ke CSV');

        $csv = "Invoice,Pelanggan,Paket,Tanggal,Periode,Jumlah,Status\n";
        foreach ($invoices as $inv) {
            $csv .= implode(',', [
                $inv->invoice_code,
                str_replace(',', ' ', $inv->customer->name ?? '-'),
                str_replace(',', ' ', $inv->customer->package->name ?? '-'),
                $inv->created_at->format('d/m/Y'),
                $inv->billing_period,
                $inv->amount,
                $inv->payment_status === 'paid' ? 'Lunas' : 'Belum',
            ])."\n";
        }

        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="invoices-'.now()->format('Ymd').'.csv"',
        ]);
    }

    public function payments(Request $request)
    {
        $query = Payment::with('invoice.customer');

        if ($from = $request->get('from')) {
            $query->whereDate('payment_date', '>=', $from);
        }
        if ($to = $request->get('to')) {
            $query->whereDate('payment_date', '<=', $to);
        }

        $payments = $query->latest()->get();

        ActivityLog::log('Export Pembayaran', 'Export '.$payments->count().' pembayaran ke CSV');

        $csv = "Tanggal,Invoice,Pelanggan,Jumlah,Metode,Catatan\n";
        foreach ($payments as $p) {
            $csv .= implode(',', [
                $p->payment_date->format('d/m/Y'),
                $p->invoice->invoice_code ?? '-',
                str_replace(',', ' ', $p->invoice->customer->name ?? '-'),
                $p->amount,
                $p->payment_method,
                str_replace(',', ' ', $p->notes ?? ''),
            ])."\n";
        }

        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="payments-'.now()->format('Ymd').'.csv"',
        ]);
    }
}
