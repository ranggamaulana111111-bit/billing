<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Faktur {{ $invoice->invoice_code }} - {{ $settings['company_name'] ?? 'RabegNet' }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --accent: #6366f1;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: #f0f2f5;
            padding: 40px 20px;
            color: #0f172a;
        }
        .invoice-wrapper {
            max-width: 210mm;
            margin: 0 auto;
        }
        .invoice-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
            overflow: hidden;
        }
        .invoice-header {
            padding: 40px 40px 30px;
            border-bottom: 2px solid #f1f5f9;
        }
        .invoice-header .brand {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .invoice-header .brand-icon {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.3rem;
        }
        .invoice-header .brand h3 {
            font-weight: 800;
            font-size: 1.4rem;
            letter-spacing: -0.03em;
            margin: 0;
        }
        .invoice-header .brand small {
            font-size: 10px;
            color: #94a3b8;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            display: block;
            margin-top: -2px;
        }
        .invoice-title {
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.04em;
            color: #0f172a;
            margin: 0;
        }
        .invoice-title span {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 18px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.8rem;
            letter-spacing: 0.02em;
        }
        .status-badge.paid {
            background: #f0fdf4;
            color: #059669;
        }
        .status-badge.unpaid {
            background: #fef2f2;
            color: #dc2626;
        }
        .invoice-body {
            padding: 30px 40px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        .info-group h6 {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #94a3b8;
            margin-bottom: 6px;
        }
        .info-group p {
            font-size: 0.95rem;
            font-weight: 600;
            margin: 0;
            color: #0f172a;
        }
        .info-group .sub-text {
            font-size: 0.8rem;
            font-weight: 400;
            color: #64748b;
            margin-top: 2px;
        }
        .detail-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        .detail-table th {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #94a3b8;
            padding: 10px 0 10px 0;
            text-align: left;
            border-bottom: 2px solid #f1f5f9;
        }
        .detail-table th.text-end {
            text-align: right;
        }
        .detail-table td {
            padding: 12px 0;
            border-bottom: 1px solid #f8fafc;
            font-size: 0.9rem;
        }
        .detail-table td.text-end {
            text-align: right;
        }
        .detail-table .item-name {
            font-weight: 600;
        }
        .detail-table .item-desc {
            font-size: 0.78rem;
            color: #64748b;
        }
        .total-section {
            border-top: 2px solid #f1f5f9;
            padding-top: 16px;
            text-align: right;
        }
        .total-row {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 40px;
            margin-bottom: 4px;
        }
        .total-row label {
            font-size: 0.8rem;
            font-weight: 500;
            color: #64748b;
        }
        .total-row .amount {
            font-weight: 700;
            font-size: 1rem;
        }
        .total-row.total label {
            font-size: 1rem;
            font-weight: 700;
            color: #0f172a;
        }
        .total-row.total .amount {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--primary);
        }
        .invoice-footer {
            padding: 20px 40px 30px;
            border-top: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .invoice-footer small {
            color: #94a3b8;
            font-size: 0.75rem;
        }
        .invoice-footer .powered {
            font-weight: 600;
            color: #64748b;
        }
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
            transition: all 0.2s ease;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: #fff;
        }
        .btn-primary:hover {
            box-shadow: 0 4px 15px rgba(37,99,235,0.3);
            transform: translateY(-1px);
        }
        .btn-outline {
            border: 1px solid #e2e8f0;
            color: #475569;
            background: #fff;
        }
        .btn-outline:hover {
            background: #f8fafc;
        }
        @media print {
            body {
                background: #fff;
                padding: 0;
            }
            .invoice-card {
                border-radius: 0;
                box-shadow: none;
                border: none;
            }
            .action-bar {
                display: none !important;
            }
            .no-print {
                display: none !important;
            }
            .invoice-wrapper {
                max-width: 100%;
            }
            .invoice-header {
                padding: 30px 30px 20px;
            }
            .invoice-body {
                padding: 20px 30px;
            }
            .invoice-footer {
                padding: 16px 30px 20px;
            }
        }
        @page {
            margin: 10mm;
            size: A4;
        }
    </style>
</head>
<body>
    <div class="invoice-wrapper">
        <div class="invoice-card">
            <div class="invoice-header">
                <div class="row align-items-center">
                    <div class="col-7">
                        <div class="brand">
                            <div class="brand-icon">
                                <i class="fa-solid fa-bolt"></i>
                            </div>
                            <div>
                                <h3>{{ $settings['company_name'] ?? 'RabegNet' }}</h3>
                                <small>Internet Service Provider</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-5 text-end">
                        <h1 class="invoice-title">FAKTUR</h1>
                        <div style="margin-top:8px;">
                            @if($invoice->payment_status === 'paid')
                                <span class="status-badge paid">LUNAS</span>
                            @else
                                <span class="status-badge unpaid">BELUM DIBAYAR</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="invoice-body">
                <div class="info-grid">
                    <div>
                        <h6>Kepada</h6>
                        <p>{{ $invoice->customer->name }}</p>
                        <div class="sub-text">
                            @if($invoice->customer->location)
                                {{ $invoice->customer->location }}<br>
                            @endif
                            @if($invoice->customer->phone)
                                {{ $invoice->customer->phone }}
                            @endif
                        </div>
                    </div>
                    <div>
                        <h6>No. Invoice</h6>
                        <p>{{ $invoice->invoice_code }}</p>
                        <div class="sub-text">
                            Tanggal: {{ $invoice->created_at->format('d/m/Y') }}<br>
                            Jatuh Tempo: {{ $invoice->customer->due_date ? \Carbon\Carbon::parse($invoice->customer->due_date)->format('d/m/Y') : '-' }}
                        </div>
                    </div>
                </div>

                <table class="detail-table">
                    <thead>
                        <tr>
                            <th style="width:60%;">Layanan</th>
                            <th style="width:20%;">Periode</th>
                            <th class="text-end" style="width:20%;">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div class="item-name">{{ $invoice->customer->package->name ?? 'Internet' }}</div>
                                <div class="item-desc">
                                    Kecepatan {{ $invoice->customer->package->speed ?? '-' }} Mbps
                                    @if($invoice->customer->odp)
                                        &middot; ODP: {{ $invoice->customer->odp->name }}
                                    @endif
                                </div>
                            </td>
                            <td>{{ $invoice->created_at->format('M Y') }}</td>
                            <td class="text-end fw-bold">Rp {{ number_format($invoice->amount, 0, ',', '.') }}</td>
                        </tr>
                    </tbody>
                </table>

                <div class="total-section">
                    <div class="total-row">
                        <label>Subtotal</label>
                        <span class="amount">Rp {{ number_format($invoice->amount, 0, ',', '.') }}</span>
                    </div>
                    <div class="total-row">
                        <label>Diskon</label>
                        <span class="amount" style="color:#059669;">Rp 0</span>
                    </div>
                    <div class="total-row total">
                        <label>TOTAL</label>
                        <span class="amount">Rp {{ number_format($invoice->amount, 0, ',', '.') }}</span>
                    </div>
                </div>

                <div style="margin-top:24px;padding:14px 18px;background:#f8fafc;border-radius:10px;font-size:0.8rem;color:#64748b;">
                    <i class="fa-solid fa-info-circle me-1" style="color:var(--primary);"></i>
                    Pembayaran dapat dilakukan melalui transfer ke rekening:<br>
                    <strong style="color:#0f172a;">{{ $settings['bank_name'] ?? 'Bank BCA' }} &middot; {{ $settings['bank_account'] ?? '1234567890' }} &middot; a.n. {{ $settings['bank_holder'] ?? 'RabegNet' }}</strong>
                </div>
            </div>

            <div class="invoice-footer">
                <small>{{ $settings['invoice_footer'] ?? 'Terima kasih atas kepercayaan Anda.' }}</small>
                <small class="powered">RabegNet Billing System</small>
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

    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
</body>
</html>
