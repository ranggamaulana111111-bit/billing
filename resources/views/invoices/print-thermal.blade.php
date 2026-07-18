<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Struk {{ $invoice->invoice_display }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Courier New', 'Consolas', 'Lucida Console', monospace;
            font-size: 7.5px;
            line-height: 1.25;
            color: #000;
            width: 44mm;
            margin: 0;
            padding: 0 0 0 2mm;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            text-shadow: 0 0 0 #000;
        }
        .line { border-top: 2px solid #000; margin: 2px 0; }
        .line-thick { border-top: 3px solid #000; margin: 3px 0; }
        .line-thin { border-top: 1px solid #000; margin: 2px 0; }
        .center { text-align: center; }
        .bold { font-weight: 900; }
        .title-section { font-size: 8px; text-align: center; margin: 2px 0; }
        .company-name { font-size: 10px; text-align: center; }
        .company-sub { font-size: 6.5px; text-align: center; }
        .company-logo { text-align: center; margin-bottom: 2px; }
        .company-logo img { max-height: 20mm; width: auto; object-fit: contain; }
        .info td { padding: 1px 0; vertical-align: top; font-weight: 900; }
        .info td.lbl { width: 32%; }
        .info td.sep { width: 2%; text-align: center; }
        .info td.val { width: 66%; word-break: break-word; }
        .costs td { padding: 1px 0; font-weight: 900; }
        .costs td.lbl { width: 60%; }
        .costs td.val { width: 40%; text-align: right; }
        .total-row td {
            padding: 2px 0;
            font-size: 9px;
            font-weight: 900;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
        }
        .total-row td.lbl { width: 60%; }
        .total-row td.val { width: 40%; text-align: right; }
        .footer { text-align: center; font-size: 6.5px; margin-top: 3px; }
        .section-title { font-size: 7.5px; font-weight: 900; margin: 3px 0 1px; }
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
    <div class="company-logo">
        @if(!empty($settings['company_logo']))
            <img src="{{ asset('storage/' . $settings['company_logo']) }}" alt="Logo">
        @endif
    </div>
    <div class="company-name bold">{{ $settings['company_name'] ?? 'PT. ALKONEK NETWORK ACCESS' }}</div>
    <div class="company-sub">{{ $settings['company_address'] ?? 'Kp. Malangnengah Desa Bendungan, Banjarsari, Lebak-Banten, 42355' }}</div>
    <div class="company-sub">Telp: {{ $settings['company_phone'] ?? '089531559066' }} | Email: {{ $settings['company_email'] ?? 'alkoneknetworkaccess@gmail.com' }}</div>

    <div class="section-title center bold">BUKTI PEMBAYARAN TAGIHAN INTERNET</div>
    <div class="line-thick"></div>

    <table class="info">
        <tr>
            <td class="lbl">No. Struk</td>
            <td class="sep">:</td>
            <td class="val">{{ $invoice->invoice_display }}</td>
        </tr>
        <tr>
            <td class="lbl">Tanggal Bayar</td>
            <td class="sep">:</td>
            <td class="val">
                @if($invoice->paid_at)
                    {{ $invoice->paid_at->format('d') }} {{ \Carbon\Carbon::parse($invoice->paid_at)->translatedFormat('F') }} {{ $invoice->paid_at->format('Y') }}, {{ $invoice->paid_at->format('H:i') }} WIB
                @else
                    -
                @endif
            </td>
        </tr>
        <tr>
            <td class="lbl">Metode Bayar</td>
            <td class="sep">:</td>
            <td class="val">{{ $invoice->payment_method ? strtoupper($invoice->payment_method) : '-' }}</td>
        </tr>
        <tr>
            <td class="lbl">Status</td>
            <td class="sep">:</td>
            <td class="val bold">{{ $invoice->payment_status === 'paid' ? 'LUNAS' : 'BELUM DIBAYAR' }}</td>
        </tr>
    </table>

    <div class="line"></div>
    <div class="section-title bold">DATA PELANGGAN</div>
    <div class="line"></div>

    <table class="info">
        <tr>
            <td class="lbl">Nama Pelanggan</td>
            <td class="sep">:</td>
            <td class="val">{{ $invoice->customer->name }}</td>
        </tr>
        <tr>
            <td class="lbl">ID Pelanggan</td>
            <td class="sep">:</td>
            <td class="val bold">{{ $invoice->customer->customer_code }}</td>
        </tr>
        <tr>
            <td class="lbl">Paket Berlangganan</td>
            <td class="sep">:</td>
            <td class="val">{{ $invoice->customer->package->name ?? '-' }} ({{ $invoice->customer->package->speed ?? '-' }} Mbps)</td>
        </tr>
        <tr>
            <td class="lbl">Periode Tagihan</td>
            <td class="sep">:</td>
            <td class="val">{{ $invoice->billing_period ? \Carbon\Carbon::createFromFormat('Y-m', $invoice->billing_period)->format('M Y') : $invoice->created_at->format('M Y') }}</td>
        </tr>
    </table>

    <div class="line"></div>
    <div class="section-title bold">RINCIAN BIAYA</div>
    <div class="line"></div>

    @php
        $basePrice = $invoice->amount;
        $routerFee = 0;
        $ppn = 0;
    @endphp

    <table class="costs" style="width:100%;">
        <tr>
            <td class="lbl">1. Berlangganan Paket</td>
            <td class="val">Rp {{ number_format($basePrice, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="lbl">2. Biaya Sewa Router</td>
            <td class="val">Rp {{ number_format($routerFee, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="lbl">3. PPN 11%</td>
            <td class="val">Rp {{ number_format($ppn, 0, ',', '.') }}</td>
        </tr>
    </table>
    <div class="line-thin"></div>
    <table class="costs" style="width:100%;">
        <tr class="total-row">
            <td class="lbl bold">TOTAL BAYAR</td>
            <td class="val bold">Rp {{ number_format($basePrice + $routerFee + $ppn, 0, ',', '.') }}</td>
        </tr>
    </table>

    <div class="line-thick"></div>
    <div class="footer">
        Terima kasih telah menggunakan layanan kami.<br>
        Simpan struk ini sebagai bukti pembayaran yang sah.
    </div>
    <div class="line-thick"></div>

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
