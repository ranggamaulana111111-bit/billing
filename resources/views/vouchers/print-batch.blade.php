<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Voucher Batch</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz@14..32&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background:#f1f5f9;
            padding:20px;
        }
        .grid {
            display:grid;
            grid-template-columns:repeat(auto-fill,minmax(300px,1fr));
            gap:16px;
            max-width:1000px;
            margin:0 auto;
        }
        .voucher-card {
            background:#fff;
            border-radius:12px;
            overflow:hidden;
            box-shadow:0 4px 12px rgba(0,0,0,.08);
            break-inside:avoid;
        }
        .voucher-header {
            background:linear-gradient(135deg,#2563eb,#6366f1);
            color:#fff;
            text-align:center;
            padding:16px 14px 14px;
        }
        .voucher-header .brand {
            font-size:0.65rem; opacity:.8;
            text-transform:uppercase; letter-spacing:1px;
        }
        .voucher-header h3 { font-size:0.95rem; font-weight:700; margin-bottom:0; }
        .voucher-body { padding:16px 18px; }
        .voucher-row {
            display:flex;
            justify-content:space-between;
            align-items:center;
            padding:7px 0;
            border-bottom:1px dashed #e2e8f0;
            font-size:0.85rem;
        }
        .voucher-row:last-child { border-bottom:none; }
        .voucher-row label { color:#64748b; text-transform:uppercase; font-size:0.7rem; letter-spacing:0.5px; }
        .voucher-row code { font-weight:700; color:#1e293b; font-size:0.9rem; }
        .voucher-stripe { height:4px; background:repeating-linear-gradient(90deg,#2563eb 0,#2563eb 8px,transparent 8px,transparent 16px); }
        @media print {
            body { background:#fff; padding:12px; }
            .voucher-card { box-shadow:none; break-inside:avoid; }
            .no-print { display:none !important; }
        }
        .print-bar {
            text-align:center; margin-bottom:20px;
        }
        .print-bar button {
            padding:10px 30px; background:#2563eb; color:#fff;
            border:none; border-radius:8px; font-size:1rem; font-weight:600; cursor:pointer;
        }
        .print-bar button:hover { background:#1d4ed8; }
    </style>
</head>
<body>
    <div class="print-bar no-print">
        <button onclick="window.print()"><i class="fa-solid fa-print me-1"></i> Cetak Semua</button>
    </div>
    <div class="grid">
        @foreach($vouchers as $v)
            <div class="voucher-card">
                <div class="voucher-header">
                    <div class="brand">— Voucher Hotspot —</div>
                    <h3>{{ $companyName }}</h3>
                </div>
                <div class="voucher-stripe"></div>
                <div class="voucher-body">
                    <div class="voucher-row">
                        <label>Username</label>
                        <code>{{ $v->username }}</code>
                    </div>
                    <div class="voucher-row">
                        <label>Password</label>
                        <code>{{ $v->password }}</code>
                    </div>
                    <div class="voucher-row">
                        <label>Durasi</label>
                        <code>
                            @php
                                $days = intdiv($v->duration_hours, 24);
                                $hours = $v->duration_hours % 24;
                                $durText = $days > 0
                                    ? trim($days.' Hari '.($hours > 0 ? $hours.' Jam' : ''))
                                    : $hours.' Jam';
                            @endphp
                            {{ $durText }}
                        </code>
                    </div>
                    <div class="voucher-row">
                        <label>Kadaluarsa</label>
                        <code style="font-size:0.7rem;">{{ $v->expires_at->format('d/m/Y H:i') }}</code>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</body>
</html>
