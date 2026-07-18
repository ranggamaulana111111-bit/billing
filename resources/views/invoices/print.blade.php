<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Struk {{ $invoice->invoice_display }}</title>
    <style>
        :root { --primary: #000; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: #f0f2f5;
            padding: 40px 20px;
            color: #0f172a;
        }
        .wrapper { max-width: 210mm; margin: 0 auto; }
        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .header {
            padding: 30px 40px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header-left .title { font-size: 20px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.02em; color: #0f172a; }
        .header-left .sub { font-size: 11px; color: #64748b; margin-top: 2px; font-style: italic; }
        .header-right img { max-height: 120px; width: auto; object-fit: contain; }
        .section-title {
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin: 0;
            padding: 10px 40px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
        }
        .body-section { padding: 24px 40px; }
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .info-table td { padding: 6px 0; font-size: 0.88rem; vertical-align: top; }
        .info-table td.lbl { width: 200px; font-weight: 600; color: #475569; }
        .info-table td.sep { width: 16px; text-align: center; color: #94a3b8; }
        .info-table td.val { color: #0f172a; }
        .costs-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .costs-table td { padding: 8px 0; font-size: 0.88rem; border-bottom: 1px solid #f1f5f9; }
        .costs-table td.lbl { font-weight: 500; color: #475569; }
        .costs-table td.val { text-align: right; font-weight: 600; color: #0f172a; }
        .total-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 0;
            border-top: 3px solid #000;
            border-bottom: 3px solid #000;
            margin-bottom: 24px;
        }
        .total-bar .label { font-size: 1rem; font-weight: 800; text-transform: uppercase; }
        .total-bar .amount { font-size: 1.4rem; font-weight: 800; color: #000; }
        .bank-box {
            padding: 16px 20px;
            background: #f8fafc;
            border-radius: 8px;
            font-size: 0.85rem;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }
        .bank-box strong { color: #0f172a; }
        .footer-section {
            padding: 20px 40px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
        }
        .footer-section .msg { font-size: 0.85rem; color: #475569; margin-bottom: 4px; }
        .footer-section .sub-msg { font-size: 0.75rem; color: #94a3b8; }
        .action-bar {
            padding: 20px 40px 30px;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }
        .btn {
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary { background: #000; color: #fff; }
        .btn-primary:hover { background: #1a1a1a; }
        .btn-outline { border: 1px solid #e2e8f0; color: #475569; background: #fff; }
        .btn-outline:hover { background: #f8fafc; }
        @media print {
            body { background: #fff; padding: 0; }
            .card { border-radius: 0; box-shadow: none; }
            .action-bar { display: none !important; }
            .wrapper { max-width: 100%; }
            .header { padding: 24px 30px 16px; }
            .body-section { padding: 20px 30px; }
            .section-title { padding: 8px 30px; }
            .footer-section { padding: 16px 30px; }
        }
        @page { margin: 10mm; size: A4; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="card">
            <div class="header">
                <div class="header-left">
                    <div class="title">INFORMASI TAGIHAN ALKONEK</div>
                    <div class="sub">Alkonek-Billing Statement</div>
                </div>
                <div class="header-right">
                    @if(!empty($settings['company_logo']))
                        <img src="{{ asset('storage/' . $settings['company_logo']) }}" alt="Logo">
                    @endif
                </div>
            </div>

            <div class="section-title" style="text-align:center;">BUKTI PEMBAYARAN TAGIHAN INTERNET</div>

            <div class="body-section">
                <table class="info-table">
                    <tr>
                        <td class="lbl">No. Struk</td>
                        <td class="sep">:</td>
                        <td class="val"><strong>{{ $invoice->invoice_display }}</strong></td>
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
                        <td class="val">
                            @if($invoice->payment_status === 'paid')
                                <strong style="color:#059669;">LUNAS</strong>
                            @else
                                <strong style="color:#dc2626;">BELUM DIBAYAR</strong>
                            @endif
                        </td>
                    </tr>
                </table>

                <div class="section-title" style="padding:8px 0;background:transparent;border:1px solid #e2e8f0;border-radius:6px;margin-bottom:16px;">DATA PELANGGAN</div>

                <table class="info-table">
                    <tr>
                        <td class="lbl">Nama Pelanggan</td>
                        <td class="sep">:</td>
                        <td class="val">{{ $invoice->customer->name }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">ID Pelanggan</td>
                        <td class="sep">:</td>
                        <td class="val"><strong style="color:var(--primary);">{{ $invoice->customer->customer_code }}</strong></td>
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

                <div class="section-title" style="padding:8px 0;background:transparent;border:1px solid #e2e8f0;border-radius:6px;margin-bottom:16px;">RINCIAN BIAYA</div>

                @php
                    $basePrice = $invoice->amount;
                    $routerFee = 0;
                    $ppn = 0;
                @endphp

                <table class="costs-table">
                    <tr>
                        <td class="lbl">1. Berlangganan Paket</td>
                        <td class="val">Rp {{ number_format($basePrice, 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">2. Biaya Sewa Router</td>
                        <td class="val">Rp {{ number_format($routerFee, 0, ',', '.') }}</td>
                    </tr>
                    <tr style="border-bottom:none;">
                        <td class="lbl">3. PPN 11%</td>
                        <td class="val">Rp {{ number_format($ppn, 0, ',', '.') }}</td>
                    </tr>
                </table>

                <div class="total-bar">
                    <span class="label">TOTAL BAYAR</span>
                    <span class="amount">Rp {{ number_format($basePrice + $routerFee + $ppn, 0, ',', '.') }}</span>
                </div>

                <div class="bank-box">
                    <strong style="color:#0f172a;">Pembayaran dapat dilakukan melalui transfer ke rekening:</strong><br>
                    {{ $settings['bank_name'] ?? 'Bank BCA' }} &middot; {{ $settings['bank_account'] ?? '1234567890' }} &middot; a.n. {{ $settings['bank_holder'] ?? ($settings['company_name'] ?? 'ALKONEK') }}
                </div>
            </div>

            <div class="footer-section">
                <div class="msg">Terima kasih telah menggunakan layanan kami.</div>
                <div class="sub-msg">Simpan struk ini sebagai bukti pembayaran yang sah.</div>
            </div>
        </div>

        <div class="action-bar no-print">
            <button class="btn btn-outline" onclick="window.close()">
                <i class="fa-solid fa-times"></i> Tutup
            </button>
            <button class="btn btn-primary" onclick="window.print()">
                <i class="fa-solid fa-print"></i> Cetak
            </button>
        </div>
    </div>
</body>
</html>
