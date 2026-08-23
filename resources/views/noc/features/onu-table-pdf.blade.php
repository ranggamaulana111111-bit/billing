<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tabel ONU</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', 'DejaVu Sans', sans-serif; font-size: 11px; color: #1e293b; padding: 24px; }
        .header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 20px; padding-bottom: 14px; border-bottom: 2px solid #2563eb; }
        .brand h1 { font-size: 18px; color: #2563eb; margin: 0; }
        .brand small { color: #64748b; font-size: 9px; }
        .title { text-align: right; }
        .title h2 { color: #0f172a; margin: 0; font-size: 15px; }
        .title .meta { color: #64748b; font-size: 10px; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f1f5f9; padding: 7px 8px; text-align: left; font-size: 9px; text-transform: uppercase; color: #475569; border-bottom: 1px solid #cbd5e1; }
        td { padding: 7px 8px; border-bottom: 1px solid #e2e8f0; font-size: 10.5px; }
        tr:nth-child(even) td { background: #f8fafc; }
        .foot { margin-top: 12px; color: #94a3b8; font-size: 9px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">
            @if (! empty($settings['company_logo']))
                <img src="{{ storage_path('app/public/'.$settings['company_logo']) }}" alt="Logo" style="height:40px;width:auto;border-radius:20px;margin-bottom:4px;"><br>
            @endif
            <h1>{{ $settings['company_name'] }}</h1>
            <small>{{ $settings['company_address'] }}</small><br>
            <small>Telp: {{ $settings['company_phone'] }}</small>
        </div>
        <div class="title">
            <h2>TABEL ONU{{ $typeFilter === 'ppp' ? ' — PPPoE' : ($typeFilter === 'hotspot' ? ' — HOTSPOT' : '') }}</h2>
            <div class="meta">Dicetak {{ now()->format('d/m/Y H:i') }} · {{ count($rows) }} data</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Nama</th>
                <th>Type</th>
                <th>Akun PPPoE</th>
                <th>IP Address</th>
                <th>Koordinat</th>
                <th>HTB</th>
                <th>ODP</th>
                <th>OLT</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $i => $r)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $r['nama'] }}</td>
                    <td>{{ $r['type_onu'] }}</td>
                    <td>{{ $r['pppoe_username'] }}</td>
                    <td>{{ $r['ip_address'] ?? '-' }}</td>
                    <td>{{ $r['koordinat'] ?? '-' }}</td>
                    <td>{{ $r['htb'] }}</td>
                    <td>{{ $r['odp'] ?? '-' }}</td>
                    <td>{{ $r['olt'] ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="9" style="text-align:center;padding:24px;">Belum ada data ONU.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="foot">{{ $settings['invoice_footer'] ?? 'Alkonek Network Access — NOC' }}</div>
</body>
</html>
