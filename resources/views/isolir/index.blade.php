<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internet Terisolir</title>
    @vite('resources/css/app.css')
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: system-ui, -apple-system, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .isolir-card {
            background: #fff;
            border-radius: 20px;
            padding: 40px 32px;
            max-width: 480px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .isolir-icon {
            width: 80px;
            height: 80px;
            background: #fef2f2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2rem;
        }
        .isolir-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: #dc2626;
            margin-bottom: 8px;
        }
        .isolir-subtitle {
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 28px;
            line-height: 1.5;
        }
        .info-box {
            background: #f8fafc;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            text-align: left;
        }
        .info-box .label {
            font-size: 0.75rem;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .info-box .value {
            font-size: 1rem;
            font-weight: 600;
            color: #1e293b;
        }
        .info-box .value.amount {
            color: #dc2626;
            font-size: 1.25rem;
        }
        .btn-wa {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #25D366;
            color: #fff;
            border: none;
            padding: 14px 28px;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            margin-top: 16px;
            transition: transform 0.1s;
        }
        .btn-wa:hover {
            transform: scale(1.02);
            color: #fff;
        }
        .footer-text {
            margin-top: 20px;
            font-size: 0.8rem;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="isolir-card">
        <div class="isolir-icon">
            <span>🚫</span>
        </div>
        <div class="isolir-title">Internet Terisolir</div>
        <div class="isolir-subtitle">
            Akun Anda sedang ditangguhkan.<br>
            Silakan lakukan pembayaran untuk mengaktifkan kembali.
        </div>

        <div class="info-box">
            <div class="label">Nama Pelanggan</div>
            <div class="value">{{ $customer->name }}</div>
        </div>

        @if($invoice)
            <div class="info-box">
                <div class="label">Tagihan</div>
                <div class="value">{{ $invoice->invoice_code }}</div>
            </div>
            <div class="info-box">
                <div class="label">Jumlah Tagihan</div>
                <div class="value amount">Rp {{ number_format($invoice->amount, 0, ',', '.') }}</div>
            </div>
            <div class="info-box">
                <div class="label">Jatuh Tempo</div>
                <div class="value">{{ \Carbon\Carbon::parse($invoice->due_date ?? $customer->due_date)->format('d M Y') }}</div>
            </div>
        @endif

        @if($adminPhone)
            <a href="https://wa.me/{{ $adminPhone }}?text=Halo%20{{ urlencode($adminName) }}%2C%20saya%20{{ urlencode($customer->name) }}%20mau%20konfirmasi%20pembayaran" target="_blank" class="btn-wa">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                Konfirmasi ke Admin via WhatsApp
            </a>
        @else
            <div class="info-box" style="text-align:center;">
                <div class="label">Hubungi Admin</div>
                <div class="value" style="font-size:0.9rem;">Silakan hubungi admin untuk informasi pembayaran</div>
            </div>
        @endif

        <div class="footer-text">
            RabegNet — Layanan Internet Cepat & Stabil
        </div>
    </div>
</body>
</html>
