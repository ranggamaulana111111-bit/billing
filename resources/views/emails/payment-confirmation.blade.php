<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family:'Segoe UI',Arial,sans-serif; background:#f4f4f4; margin:0; padding:0; }
        .container { max-width:600px; margin:30px auto; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,0.08); }
        .header { background:linear-gradient(135deg,#059669,#10b981); color:#fff; padding:30px; text-align:center; }
        .header h1 { margin:0; font-size:22px; }
        .body { padding:30px; }
        .body p { color:#475569; line-height:1.6; margin:0 0 16px; }
        .invoice-detail { background:#f8fafc; border-radius:8px; padding:16px; margin:16px 0; }
        .invoice-detail table { width:100%; font-size:14px; }
        .invoice-detail td { padding:6px 0; }
        .invoice-detail .label { color:#94a3b8; }
        .invoice-detail .value { font-weight:600; text-align:right; }
        .footer { padding:20px 30px; text-align:center; color:#94a3b8; font-size:12px; border-top:1px solid #f1f5f9; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✅ Pembayaran Diterima</h1>
        </div>
        <div class="body">
            <p>Halo <strong>{{ $invoice->customer->name }}</strong>,</p>
            <p>Terima kasih! Pembayaran Anda telah kami terima dan dicatat.</p>

            <div class="invoice-detail">
                <table>
                    <tr><td class="label">Invoice</td><td class="value">{{ $invoice->invoice_code }}</td></tr>
                    <tr><td class="label">Paket</td><td class="value">{{ $invoice->customer->package?->name ?? '-' }}</td></tr>
                    <tr><td class="label">Total</td><td class="value">Rp {{ number_format($invoice->amount, 0, ',', '.') }}</td></tr>
                    <tr><td class="label">Status</td><td class="value" style="color:#059669;">✅ LUNAS</td></tr>
                    <tr><td class="label">Tanggal</td><td class="value">{{ now()->format('d/m/Y H:i') }}</td></tr>
                </table>
            </div>

            <p>Nikmati layanan internet Anda. Terima kasih telah melakukan pembayaran tepat waktu!</p>
        </div>
        <div class="footer">
            <p>{{ $settings['company_name'] ?? 'RabegNet' }} &mdash; {{ $settings['company_address'] ?? '' }}</p>
            <p>Email ini dikirim otomatis oleh sistem billing.</p>
        </div>
    </div>
</body>
</html>
