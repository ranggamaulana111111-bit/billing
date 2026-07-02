<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family:'Segoe UI',Arial,sans-serif; background:#f4f4f4; margin:0; padding:0; }
        .container { max-width:600px; margin:30px auto; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,0.08); }
        .header { background:linear-gradient(135deg,#2563eb,#6366f1); color:#fff; padding:30px; text-align:center; }
        .header img { height:32px; width:auto; border-radius:6px; margin-bottom:10px; }
        .header h1 { margin:0; font-size:22px; }
        .body { padding:30px; }
        .body p { color:#475569; line-height:1.6; margin:0 0 16px; }
        .invoice-detail { background:#f8fafc; border-radius:8px; padding:16px; margin:16px 0; }
        .invoice-detail table { width:100%; font-size:14px; }
        .invoice-detail td { padding:6px 0; }
        .invoice-detail .label { color:#94a3b8; }
        .invoice-detail .value { font-weight:600; text-align:right; }
        .btn { display:inline-block; padding:12px 24px; background:linear-gradient(135deg,#2563eb,#6366f1); color:#fff; text-decoration:none; border-radius:8px; font-weight:600; font-size:14px; }
        .footer { padding:20px 30px; text-align:center; color:#94a3b8; font-size:12px; border-top:1px solid #f1f5f9; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            @if(!empty($settings['company_logo']))
                <img src="{{ asset('storage/' . $settings['company_logo']) }}" alt="{{ $settings['company_name'] ?? 'ALKONEK' }}">
            @endif
            <h1>🔔 Reminder Pembayaran</h1>
        </div>
        <div class="body">
            <p>Halo <strong>{{ $invoice->customer->name }}</strong>,</p>
            <p>Kami mengingatkan bahwa Anda memiliki tagihan yang belum dibayar. Segera lakukan pembayaran sebelum jatuh tempo.</p>

            <div class="invoice-detail">
                <table>
                    <tr><td class="label">Invoice</td><td class="value">{{ $invoice->invoice_code }}</td></tr>
                    <tr><td class="label">Paket</td><td class="value">{{ $invoice->customer->package?->name ?? '-' }}</td></tr>
                    <tr><td class="label">Total</td><td class="value">Rp {{ number_format($invoice->amount, 0, ',', '.') }}</td></tr>
                    <tr><td class="label">Status</td><td class="value" style="color:#dc2626;">Belum Dibayar</td></tr>
                </table>
            </div>

            @if(!empty($settings['bank_name']))
            <p style="margin-top:16px;"><strong>Pembayaran dapat ditransfer ke:</strong></p>
            <div class="invoice-detail">
                <table>
                    <tr><td class="label">Bank</td><td class="value">{{ $settings['bank_name'] }}</td></tr>
                    <tr><td class="label">No. Rekening</td><td class="value">{{ $settings['bank_account'] }}</td></tr>
                    <tr><td class="label">Atas Nama</td><td class="value">{{ $settings['bank_holder'] }}</td></tr>
                </table>
            </div>
            @endif
        </div>
        <div class="footer">
            <p>{{ $settings['company_name'] ?? 'ALKONEK' }} &mdash; {{ $settings['company_address'] ?? '' }}</p>
            <p>Email ini dikirim otomatis oleh sistem billing.</p>
        </div>
    </div>
</body>
</html>
