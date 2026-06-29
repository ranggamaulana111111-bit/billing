<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internet Terisolir</title>
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
        .isolir-icon { font-size: 3rem; margin-bottom: 16px; }
        .isolir-title { font-size: 1.5rem; font-weight: 800; color: #dc2626; margin-bottom: 12px; }
        .isolir-subtitle { color: #64748b; font-size: 0.9rem; margin-bottom: 24px; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="isolir-card">
        <div class="isolir-icon">🚫</div>
        <div class="isolir-title">Akses Dibatasi</div>
        <div class="isolir-subtitle">
            Koneksi internet Anda sedang dibatasi.<br>
            Silakan hubungi admin untuk informasi lebih lanjut.
        </div>
        <div class="isolir-subtitle" style="font-size:0.8rem;color:#94a3b8;">
            IP: {{ $clientIp }}
        </div>
    </div>
</body>
</html>
