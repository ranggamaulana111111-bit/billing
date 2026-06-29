<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Faktur {{ $invoice->invoice_code }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Courier New', 'Consolas', 'Lucida Console', monospace;
            font-size: 10px;
            line-height: 1.2;
            color: #000;
            width: 46mm;
            margin: 0 auto;
            padding: 1mm 2mm;
        }
        .header {
            text-align: center;
            margin-bottom: 4px;
            padding-bottom: 4px;
            border-bottom: 1px dashed #000;
        }
        .header .company {
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .header .sub {
            font-size: 8px;
        }
        .header .title {
            font-size: 13px;
            font-weight: bold;
            margin-top: 2px;
        }
        .status {
            text-align: center;
            font-size: 10px;
            font-weight: bold;
            padding: 2px 0;
            margin-bottom: 4px;
            border-bottom: 1px dashed #000;
        }
        .info {
            width: 100%;
            margin-bottom: 4px;
            font-size: 9px;
        }
        .info td {
            padding: 1px 0;
            vertical-align: top;
        }
        .info td.label {
            width: 28%;
            font-weight: bold;
        }
        .info td.sep {
            width: 2%;
            text-align: center;
        }
        .info td.value {
            width: 70%;
        }
        .divider {
            border-top: 1px dashed #000;
            margin: 4px 0;
        }
        .divider-double {
            border-top: 2px double #000;
            margin: 4px 0;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
            margin-bottom: 4px;
        }
        table.items th {
            border-bottom: 1px solid #000;
            padding: 2px 0;
            text-align: left;
            font-size: 8px;
            text-transform: uppercase;
        }
        table.items th.r { text-align: right; }
        table.items td {
            padding: 1px 0;
            vertical-align: top;
        }
        table.items td.r { text-align: right; }
        .totals {
            width: 100%;
            font-size: 9px;
        }
        .totals td { padding: 1px 0; }
        .totals td.r { text-align: right; font-weight: bold; }
        .totals .grand td {
            font-size: 11px;
            font-weight: bold;
            padding-top: 2px;
        }
        .bank-info {
            font-size: 8px;
            margin-top: 4px;
            padding: 3px;
            border: 1px solid #000;
            text-align: center;
        }
        .footer {
            text-align: center;
            font-size: 8px;
            margin-top: 4px;
            padding-top: 4px;
            border-top: 1px dashed #000;
        }
        .paid-stamp {
            text-align: center;
            font-size: 12px;
            font-weight: bold;
            margin: 4px 0;
            padding: 3px;
            border: 2px solid #000;
            width: 100%;
        }
        .action-bar {
            text-align: center;
            margin-top: 8px;
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
            body { padding: 0; width: 46mm; }
            .action-bar { display: none !important; }
            @page {
                margin: 0;
            }
        }
        @page {
            size: 58mm auto;
            margin: 2mm;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company">{{ $settings['company_name'] ?? 'RabegNet' }}</div>
        <div class="sub">{{ $settings['company_address'] ?? 'Internet Service Provider' }}</div>
        <div class="sub">{{ $settings['company_phone'] ?? '' }}</div>
        <div class="title">F A K T U R</div>
        <div class="sub">No. {{ $invoice->invoice_code }}</div>
    </div>

    @if($invoice->payment_status === 'paid')
        <div class="paid-stamp">LUNAS</div>
    @else
        <div class="status belum">STATUS: BELUM DIBAYAR</div>
    @endif

    <table class="info">
        <tr>
            <td class="label">Kepada</td>
            <td class="sep">:</td>
            <td class="value">{{ $invoice->customer->name }}</td>
        </tr>
        @if($invoice->customer->location)
        <tr>
            <td class="label">Alamat</td>
            <td class="sep">:</td>
            <td class="value">{{ $invoice->customer->location }}</td>
        </tr>
        @endif
        @if($invoice->customer->phone)
        <tr>
            <td class="label">Telepon</td>
            <td class="sep">:</td>
            <td class="value">{{ $invoice->customer->phone }}</td>
        </tr>
        @endif
        <tr>
            <td class="label">Tanggal</td>
            <td class="sep">:</td>
            <td class="value">{{ $invoice->created_at->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="label">Jatuh Tempo</td>
            <td class="sep">:</td>
            <td class="value">{{ $invoice->customer->due_date ? \Carbon\Carbon::parse($invoice->customer->due_date)->format('d/m/Y') : '-' }}</td>
        </tr>
        @if($invoice->payment_method)
        <tr>
            <td class="label">Pembayaran</td>
            <td class="sep">:</td>
            <td class="value">{{ strtoupper($invoice->payment_method) }}</td>
        </tr>
        @endif
        @if($invoice->paid_at)
        <tr>
            <td class="label">Tgl. Bayar</td>
            <td class="sep">:</td>
            <td class="value">{{ $invoice->paid_at->format('d/m/Y H:i') }}</td>
        </tr>
        @endif
    </table>

    <div class="divider"></div>

    <table class="items">
        <thead>
            <tr>
                <th>Layanan</th>
                <th class="r">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    {{ $invoice->customer->package->name ?? 'Internet' }}
                    ({{ $invoice->customer->package->speed ?? '-' }} Mbps)
                    <br>
                    <span style="font-size:9px;">Periode {{ $invoice->created_at->format('M Y') }}</span>
                    @if($invoice->customer->odp)
                        <br><span style="font-size:9px;">ODP: {{ $invoice->customer->odp->name }}</span>
                    @endif
                </td>
                <td class="r">Rp {{ number_format($invoice->amount, 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <div class="divider-double"></div>

    <table class="totals">
        <tr class="grand">
            <td>TOTAL</td>
            <td class="r">Rp {{ number_format($invoice->amount, 0, ',', '.') }}</td>
        </tr>
    </table>

    @if($invoice->payment_status !== 'paid')
    <div class="bank-info">
        Pembayaran via Transfer:<br>
        <strong>{{ $settings['bank_name'] ?? 'Bank BCA' }}</strong><br>
        {{ $settings['bank_account'] ?? '1234567890' }} &middot; a.n. {{ $settings['bank_holder'] ?? 'RabegNet' }}
    </div>
    @endif

    <div class="footer">
        {{ $settings['invoice_footer'] ?? 'Terima kasih atas kepercayaan Anda.' }}<br>
        {{ $settings['company_name'] ?? 'RabegNet' }} &middot; Billing System
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
