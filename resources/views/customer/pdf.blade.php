<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Form Pelanggan - {{ $customer->name }}</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Inter', 'DejaVu Sans', sans-serif; font-size:12px; color:#1e293b; padding:30px; }
        .header { display:flex; justify-content:space-between; align-items:start; margin-bottom:30px; padding-bottom:20px; border-bottom:2px solid #2563eb; }
        .brand h1 { font-size:22px; color:#2563eb; margin:0; }
        .brand small { color:#64748b; font-size:10px; }
        .title { text-align:right; }
        .title h2 { color:#0f172a; margin:0; font-size:18px; }
        .title .code { color:#2563eb; font-size:12px; font-weight:700; margin-top:4px; }
        .info { display:flex; justify-content:space-between; margin-bottom:30px; }
        .info-box { width:48%; }
        .info-box h4 { font-size:11px; color:#64748b; text-transform:uppercase; margin-bottom:6px; }
        .info-box p { font-size:13px; font-weight:600; margin:0; }
        .info-box small { color:#64748b; font-size:11px; line-height:1.5; display:block; margin-top:2px; }
        table { width:100%; border-collapse:collapse; margin-bottom:24px; }
        th { background:#f1f5f9; padding:10px 12px; text-align:left; font-size:11px; text-transform:uppercase; color:#475569; }
        td { padding:10px 12px; border-bottom:1px solid #e2e8f0; font-size:12px; }
        .text-end { text-align:right; }
        .footer { margin-top:20px; padding-top:15px; border-top:1px solid #e2e8f0; color:#94a3b8; font-size:10px; }
        .info-table td { border:none; padding:4px 0; font-size:12px; }
        .label { color:#64748b; width:30%; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">
            @if(!empty($settings['company_logo']))
                <img src="{{ storage_path('app/public/' . $settings['company_logo']) }}" alt="Logo" style="height:56px;width:auto;border-radius:30px;margin-bottom:8px;"><br>
            @endif
            <h1>{{ $settings['company_name'] ?? 'ALKONEK' }}</h1>
            <small>{{ $settings['company_address'] ?? '' }}</small><br>
            <small>Telp: {{ $settings['company_phone'] ?? '' }}</small>
        </div>
        <div class="title">
            <h2>FORMULIR PENDAFTARAN</h2>
            <div class="code">No. Reg: {{ $customer->customer_code ?? now()->format('Ymd').'-'.str_pad($customer->id, 4, '0', STR_PAD_LEFT) }}</div>
            <div style="margin-top:4px;font-size:11px;color:#64748b;">{{ now()->format('d/m/Y') }}</div>
        </div>
    </div>

    <div class="info">
        <div class="info-box">
            <h4>Data Pelanggan</h4>
            <p>{{ $customer->name }}</p>
            <small>
                @if($customer->phone)Telepon: {{ $customer->phone }}@endif
                @if($customer->phone && $customer->email)<br>@endif
                @if($customer->email)Email: {{ $customer->email }}@endif
                @if($customer->location)<br>Alamat: {{ $customer->location }}@endif
                <br>Tgl. Daftar: {{ $customer->created_at->format('d/m/Y') }}
            </small>
        </div>
        <div class="info-box" style="text-align:right;">
            <h4>Paket Internet</h4>
            <p>{{ $customer->package->name ?? '-' }}</p>
            <small>
                Speed: {{ $customer->package->speed ?? '-' }} Mbps<br>
                Biaya: Rp {{ number_format($customer->package->price ?? 0, 0, ',', '.') }}/bln
                @if($customer->due_date)
                    <br>Jatuh Tempo: Tgl {{ \Carbon\Carbon::parse($customer->due_date)->format('d') }} setiap bulan
                @endif
            </small>
        </div>
    </div>

    @if($settings['bank_name'] ?? false)
    <div style="margin-bottom:20px;padding:15px;background:#f8fafc;border-radius:8px;">
        <h4 style="margin-bottom:8px;font-size:11px;color:#64748b;">INFORMASI PEMBAYARAN</h4>
        <table class="info-table">
            <tr><td class="label">Bank</td><td>: {{ $settings['bank_name'] }}</td></tr>
            <tr><td class="label">Rekening</td><td>: {{ $settings['bank_account'] }}</td></tr>
            <tr><td class="label">Atas Nama</td><td>: {{ $settings['bank_holder'] }}</td></tr>
        </table>
    </div>
    @endif

    <div class="footer">
        {{ $settings['invoice_footer'] ?? 'Terima kasih atas kepercayaan Anda.' }}
    </div>
</body>
</html>