<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Form Pelanggan - {{ $customer->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Courier New', 'Consolas', 'Lucida Console', monospace;
            font-size: 7px;
            line-height: 1.1;
            color: #000;
            width: 44mm;
            margin: 0;
            padding: 0 0 0 2mm;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            text-shadow: 0 0 0 #000;
        }
        .header {
            text-align: center;
            margin-bottom: 2px;
            padding-bottom: 2px;
            border-bottom: 2px solid #000;
        }
        .header .company {
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
        }
        .header .sub {
            font-size: 7px;
            font-weight: 900;
        }
        .header .title {
            font-size: 9px;
            font-weight: 900;
            margin-top: 1px;
        }
        .reg-number {
            text-align: center;
            font-size: 7px;
            font-weight: 900;
            margin-bottom: 2px;
        }
        .divider-dashed {
            border-top: 1px dashed #000;
            margin: 2px 0;
        }
        .section-title {
            font-size: 7px;
            font-weight: 900;
            text-align: center;
            margin: 2px 0 1px;
            padding: 1px 0;
            border-bottom: 1px solid #000;
        }
        table.info {
            width: 100%;
            margin-bottom: 1px;
            font-size: 7px;
            font-weight: 900;
        }
        table.info td {
            padding: 0;
            vertical-align: top;
            font-weight: 900;
        }
        table.info td.label {
            width: 28%;
        }
        table.info td.sep {
            width: 2%;
            text-align: center;
        }
        table.info td.value {
            width: 70%;
            word-break: break-word;
        }
        .bank-info {
            font-size: 7px;
            font-weight: 900;
            margin-top: 2px;
            padding: 2px;
            border: 2px solid #000;
            text-align: center;
            word-break: break-word;
        }
        .footer {
            text-align: center;
            font-size: 6.5px;
            font-weight: 900;
            margin-top: 2px;
            padding-top: 2px;
            border-top: 2px solid #000;
        }
        .action-bar {
            text-align: center;
            margin-top: 6px;
        }
        .action-bar button {
            font-family: 'Courier New', monospace;
            font-size: 10px;
            padding: 4px 16px;
            cursor: pointer;
            background: #000;
            color: #fff;
            border: none;
        }
        @media print {
            body { width: 44mm; }
            .action-bar { display: none !important; }
        }
        @page {
            size: 58mm auto;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company">{{ $settings['company_name'] ?? 'ALKONEKbill' }}</div>
        <div class="sub">{{ $settings['company_address'] ?? 'Internet Service Provider' }}</div>
        <div class="sub">{{ $settings['company_phone'] ?? '' }}</div>
        <div class="title">FORMULIR PENDAFTARAN PELANGGAN BARU</div>
    </div>

    <div class="reg-number">
        No. Reg: {{ $customer->customer_code ?? now()->format('Ymd').'-'.str_pad($customer->id, 4, '0', STR_PAD_LEFT) }} &middot; {{ now()->format('d/m/Y') }}
    </div>

    <div class="section-title">DATA PELANGGAN</div>

    <table class="info">
        <tr>
            <td class="label">Nama</td>
            <td class="sep">:</td>
            <td class="value">{{ $customer->name }}</td>
        </tr>
        <tr>
            <td class="label">Telepon</td>
            <td class="sep">:</td>
            <td class="value">{{ $customer->phone ?? '-' }}</td>
        </tr>
        @if($customer->email)
        <tr>
            <td class="label">Email</td>
            <td class="sep">:</td>
            <td class="value">{{ $customer->email }}</td>
        </tr>
        @endif
        @if($customer->location)
        <tr>
            <td class="label">Alamat</td>
            <td class="sep">:</td>
            <td class="value">{{ $customer->location }}</td>
        </tr>
        @endif
        <tr>
            <td class="label">Tgl. Daftar</td>
            <td class="sep">:</td>
            <td class="value">{{ $customer->created_at->format('d/m/Y') }}</td>
        </tr>
    </table>

    <div class="divider-dashed"></div>

    <div class="section-title">PAKET INTERNET</div>

    <table class="info">
        <tr>
            <td class="label">Paket</td>
            <td class="sep">:</td>
            <td class="value">{{ $customer->package->name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Speed</td>
            <td class="sep">:</td>
            <td class="value">{{ $customer->package->speed ?? '-' }} Mbps</td>
        </tr>
        <tr>
            <td class="label">Biaya/bln</td>
            <td class="sep">:</td>
            <td class="value">Rp {{ number_format($customer->package->price ?? 0, 0, ',', '.') }}</td>
        </tr>
        @if($customer->due_date)
        <tr>
            <td class="label">Jatuh Tempo</td>
            <td class="sep">:</td>
            <td class="value">Tgl {{ \Carbon\Carbon::parse($customer->due_date)->format('d') }} setiap bulan</td>
        </tr>
        @endif
    </table>

    <div class="bank-info">
        Pembayaran via Transfer:<br>
        <strong>{{ $settings['bank_name'] ?? 'Bank BCA' }}</strong><br>
        {{ $settings['bank_account'] ?? '1234567890' }} &middot; a.n. {{ $settings['bank_holder'] ?? ($settings['company_name'] ?? 'ALKONEKbill') }}
    </div>

    <div class="footer">
        {{ $settings['invoice_footer'] ?? 'Terima kasih atas kepercayaan Anda.' }}<br>
        {{ $settings['company_name'] ?? 'ALKONEKbill' }} &middot; Billing System
    </div>

    <div class="action-bar no-print">
        <button onclick="window.close()">TUTUP</button>
        <button onclick="window.print()">CETAK THERMAL</button>
    </div>

    <script>
        window.onload = function() {
            if (window.location.search.includes('auto')) {
                window.print();
            }
        };
    </script>
</body>
</html>
