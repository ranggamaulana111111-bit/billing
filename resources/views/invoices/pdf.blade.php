<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice->invoice_code }}</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Inter', 'DejaVu Sans', sans-serif; font-size:12px; color:#1e293b; padding:30px; }
        .header { display:flex; justify-content:space-between; align-items:start; margin-bottom:30px; padding-bottom:20px; border-bottom:2px solid #2563eb; }
        .brand h1 { font-size:22px; color:#2563eb; margin:0; }
        .brand small { color:#64748b; font-size:10px; }
        .title { text-align:right; }
        .title h2 { color:#0f172a; margin:0; font-size:18px; }
        .title .code { color:#2563eb; font-size:14px; font-weight:700; }
        .info { display:flex; justify-content:space-between; margin-bottom:30px; }
        .info-box { width:48%; }
        .info-box h4 { font-size:11px; color:#64748b; text-transform:uppercase; margin-bottom:6px; }
        .info-box p { font-size:13px; font-weight:600; }
        table { width:100%; border-collapse:collapse; margin-bottom:30px; }
        th { background:#f1f5f9; padding:10px 12px; text-align:left; font-size:11px; text-transform:uppercase; color:#475569; }
        td { padding:12px; border-bottom:1px solid #e2e8f0; font-size:13px; }
        .text-end { text-align:right; }
        .total td { font-weight:700; font-size:16px; border-top:2px solid #2563eb; border-bottom:2px solid #2563eb; }
        .footer { margin-top:20px; padding-top:15px; border-top:1px solid #e2e8f0; color:#94a3b8; font-size:10px; }
        .badge { display:inline-block; padding:3px 12px; border-radius:4px; font-size:10px; font-weight:700; }
        .badge-green { background:#f0fdf4; color:#059669; }
        .badge-red { background:#fef2f2; color:#dc2626; }
        .info-table td { border:none; padding:4px 0; font-size:12px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">
            <h1>{{ $settings['company_name'] ?? 'RabegNet' }}</h1>
            <small>{{ $settings['company_address'] ?? '' }}</small><br>
            <small>Telp: {{ $settings['company_phone'] ?? '' }}</small>
        </div>
        <div class="title">
            <h2>FAKTUR TAGIHAN</h2>
            <div class="code">{{ $invoice->invoice_code }}</div>
            <div style="margin-top:4px;">
                @if($invoice->payment_status === 'paid')
                    <span class="badge badge-green">LUNAS</span>
                @else
                    <span class="badge badge-red">BELUM DIBAYAR</span>
                @endif
            </div>
        </div>
    </div>

    <div class="info">
        <div class="info-box">
            <h4>Kepada</h4>
            <p>{{ $invoice->customer->name }}</p>
            <small>{{ $invoice->customer->location ?? '-' }}</small>
        </div>
        <div class="info-box" style="text-align:right;">
            <h4>Tanggal</h4>
            <p>{{ $invoice->created_at->format('d/m/Y') }}</p>
        </div>
    </div>

    <table>
        <thead>
            <tr><th>Layanan</th><th>Periode</th><th class="text-end">Jumlah</th></tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $invoice->customer->package->name ?? 'Internet' }}</td>
                <td>{{ $invoice->created_at->format('F Y') }}</td>
                <td class="text-end">Rp{{ number_format($invoice->amount, 0, ',', '.') }}</td>
            </tr>
            <tr class="total">
                <td colspan="2" class="text-end">Total</td>
                <td class="text-end">Rp{{ number_format($invoice->amount, 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    @if($settings['bank_name'] ?? false)
    <div style="margin-bottom:20px;padding:15px;background:#f8fafc;border-radius:8px;">
        <h4 style="margin-bottom:8px;font-size:11px;color:#64748b;">INFORMASI PEMBAYARAN</h4>
        <table class="info-table">
            <tr><td>Bank</td><td>: {{ $settings['bank_name'] }}</td></tr>
            <tr><td>Rekening</td><td>: {{ $settings['bank_account'] }}</td></tr>
            <tr><td>Atas Nama</td><td>: {{ $settings['bank_holder'] }}</td></tr>
        </table>
    </div>
    @endif

    <div class="footer">
        {{ $settings['invoice_footer'] ?? 'Terima kasih telah menggunakan layanan kami.' }}
    </div>
</body>
</html>
