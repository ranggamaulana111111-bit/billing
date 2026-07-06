<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Voucher Batch</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family:'Inter',-apple-system,sans-serif;
            background:#fff;
            padding:5mm;
        }
        @page { size:A4; margin:5mm; }

        .grid {
            display:grid;
            grid-template-columns:repeat(8,1fr);
            gap:1.5mm;
            width:100%;
        }

        .voucher-card {
            background:#fff;
            border:0.5px solid #cbd5e1;
            border-radius:2px;
            display:flex;
            flex-direction:column;
            break-inside:avoid;
        }

        .voucher-header {
            background:linear-gradient(135deg,#1e40af,#4f46e5);
            color:#fff;
            text-align:center;
            padding:1.5mm 1mm 1mm;
            flex-shrink:0;
        }

        .voucher-header .brand {
            font-size:4pt; font-weight:600; letter-spacing:.4px;
            text-transform:uppercase; opacity:.6;
        }

        .voucher-header h3 {
            font-size:5.5pt; font-weight:700;
            margin-top:.5px; line-height:1.2;
        }

        .voucher-body {
            flex:1;
            padding:1.5mm 1.8mm 1.2mm;
        }

        .voucher-body table {
            width:100%;
            border-collapse:collapse;
        }

        .voucher-body table td {
            padding:0.3mm 0;
            vertical-align:middle;
        }

        .voucher-body table td:first-child {
            font-size:4pt; font-weight:600; letter-spacing:.3px;
            text-transform:uppercase; color:#94a3b8;
            width:28%;
        }

        .voucher-body table td:last-child {
            font-weight:700; color:#0f172a;
            font-size:6.5pt; letter-spacing:.2px;
            word-break:break-all;
        }

        .price { color:#059669; font-weight:700; }
        .exp { color:#64748b; font-weight:600; }

        @media print {
            body { padding:0; }
            .grid { width:100%; }
            .no-print { display:none !important; }
        }

        .no-print {
            text-align:center; margin-bottom:12px;
        }

        .no-print button {
            padding:8px 24px; background:#2563eb; color:#fff;
            border:none; border-radius:6px; font-size:14px; font-weight:600; cursor:pointer;
        }
        .no-print button:hover { background:#1d4ed8; }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">&#128424; Cetak (48/lbr)</button>
    </div>
    <div class="grid">
        @foreach($vouchers as $v)
            @php $same = $v->username === $v->password; @endphp
            <div class="voucher-card">
                <div class="voucher-header">
                    <div class="brand">Voucher Hotspot</div>
                    <h3>{{ $companyName }}</h3>
                </div>
                <div class="voucher-body">
                    <table>
                        <tr>
                            <td>{{ $same ? 'User/Pass' : 'User' }}</td>
                            <td>{{ $v->username }}</td>
                        </tr>
                        @if(!$same)
                        <tr>
                            <td>Pass</td>
                            <td>{{ $v->password }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td>Durasi</td>
                            <td>
                                @php
                                    $d = intdiv($v->duration_hours,24);
                                    $h = $v->duration_hours%24;
                                    echo $d>0 ? $d.'h'.($h>0?' '.$h.'j':'') : $h.'j';
                                @endphp
                            </td>
                        </tr>
                        @if($v->price)
                        <tr>
                            <td>Harga</td>
                            <td class="price">Rp{{ number_format($v->price,0,',','.') }}</td>
                        </tr>
                        @endif
                        @if($v->expires_at)
                        <tr>
                            <td>Exp</td>
                            <td class="exp">{{ $v->expires_at->format('d/m/y') }}</td>
                        </tr>
                        @endif
                    </table>
                </div>
            </div>
        @endforeach
    </div>
</body>
</html>
