<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voucher WiFi — {{ $voucher->username }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz@14..32&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: #f1f5f9;
            display:flex; flex-direction:column; align-items:center;
            min-height:100vh;
            padding:20px;
        }
        .voucher-card {
            width:320px;
            background:#fff;
            border-radius:16px;
            overflow:hidden;
            box-shadow:0 8px 30px rgba(0,0,0,.1);
        }
        .voucher-header {
            background:linear-gradient(135deg,#2563eb,#6366f1);
            color:#fff;
            text-align:center;
            padding:24px 20px 20px;
        }
        .voucher-header .brand {
            font-size:0.75rem;
            opacity:.8;
            text-transform:uppercase;
            letter-spacing:1px;
            margin-bottom:4px;
        }
        .voucher-header h3 {
            font-size:1.1rem;
            font-weight:700;
            margin-bottom:0;
        }
        .voucher-body {
            padding:24px 20px;
        }
        .voucher-row {
            display:flex;
            justify-content:space-between;
            align-items:center;
            padding:10px 0;
            border-bottom:1px dashed #e2e8f0;
        }
        .voucher-row:last-child { border-bottom:none; }
        .voucher-row label {
            font-size:0.8rem;
            color:#64748b;
            text-transform:uppercase;
            letter-spacing:0.5px;
        }
        .voucher-row code {
            font-size:1rem;
            font-weight:700;
            color:#1e293b;
        }
        .voucher-footer {
            background:#f8fafc;
            padding:12px 20px;
            text-align:center;
            font-size:0.7rem;
            color:#94a3b8;
        }
        .voucher-stripe {
            height:6px;
            background:repeating-linear-gradient(90deg,#2563eb 0,#2563eb 10px,transparent 10px,transparent 20px);
        }
        @media print {
            body { background:#fff; padding:0; }
            .voucher-card { box-shadow:none; }
            .no-print { display:none !important; }
        }
        .print-btn {
            display:block;
            width:320px;
            margin:16px auto 0;
            padding:12px;
            background:#2563eb;
            color:#fff;
            border:none;
            border-radius:8px;
            font-size:0.95rem;
            font-weight:600;
            cursor:pointer;
        }
        .print-btn:hover { background:#1d4ed8; }
        .print-btn i { margin-right:6px; }
    </style>
</head>
<body>
    <div>
        <div class="voucher-card">
            <div class="voucher-header">
                <div class="brand">— Voucher Hotspot —</div>
                <h3>{{ $companyName }}</h3>
            </div>
            <div class="voucher-stripe"></div>
            <div class="voucher-body">
                <div class="voucher-row">
                    <label>Username</label>
                    <code>{{ $voucher->username }}</code>
                </div>
                <div class="voucher-row">
                    <label>Password</label>
                    <code>{{ $voucher->password }}</code>
                </div>
                <div class="voucher-row">
                    <label>Durasi</label>
                    <code>
                        @php
                            $days = intdiv($voucher->duration_hours, 24);
                            $hours = $voucher->duration_hours % 24;
                            $durationText = $days > 0
                                ? trim($days.' Hari '.($hours > 0 ? $hours.' Jam' : ''))
                                : $hours.' Jam';
                        @endphp
                        {{ $durationText }}
                    </code>
                </div>
                <div class="voucher-row">
                    <label>Kadaluarsa</label>
                    <code style="font-size:0.8rem;">{{ $voucher->expires_at->format('d/m/Y H:i') }}</code>
                </div>
            </div>
            <div class="voucher-footer">
                <span>
                    @switch($voucher->status)
                        @case('active')Belum terpakai @break
                        @case('used')Terpakai @break
                        @case('expired')Kadaluarsa @break
                        @default{{ $voucher->status }}
                    @endswitch
                </span>
            </div>
        </div>
        <button class="print-btn no-print" onclick="window.print()">
            <i class="fa-solid fa-print"></i> Cetak
        </button>
    </div>
</body>
</html>
