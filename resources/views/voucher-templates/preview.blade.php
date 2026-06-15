<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview {{ ucfirst($page) }}: {{ $template->name }} — {{ $company }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz@14..32&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: #f1f5f9;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
        }
        .toolbar {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
            padding: 10px 18px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.06);
            width: 100%;
            max-width: 460px;
            flex-wrap: wrap;
        }
        .toolbar h5 {
            flex: 1;
            font-size: 0.85rem;
            font-weight: 600;
            color: #1e293b;
            min-width: 100px;
        }
        .toolbar .nav-pills {
            display: flex;
            gap: 4px;
        }
        .toolbar .nav-pills a {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            text-decoration: none;
            background: #f1f5f9;
            color: #64748b;
            white-space: nowrap;
        }
        .toolbar .nav-pills a.active {
            background: #6366f1;
            color: #fff;
        }
        .toolbar .nav-pills a:hover:not(.active) { background: #e2e8f0; }
        .toolbar .close-btn {
            padding: 4px 10px;
            background: #f1f5f9;
            color: #64748b;
            border: none;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }
        .toolbar .close-btn:hover { background: #e2e8f0; }
        .phone-frame {
            width: 100%;
            max-width: 400px;
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 8px 40px rgba(0,0,0,.12);
            overflow: hidden;
        }
        .phone-notch {
            height: 44px;
            background: #1e293b;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .phone-notch .notch-dot { width:8px;height:8px;border-radius:50%;background:#334155; }
        .phone-notch .notch-middle { width:80px;height:6px;border-radius:10px;background:#0f172a; }
        .phone-content { padding:0; min-height:400px; }
        .phone-content .rendered-content { padding:16px; }
        .empty-state {
            padding:60px 20px;text-align:center;color:#94a3b8;
        }
        .empty-state i { font-size:2rem;display:block;margin-bottom:12px; }
        .page-label {
            font-size:0.7rem;margin-top:12px;color:#94a3b8;text-align:center;
        }
        .page-label code { background:#f1f5f9;padding:2px 6px;border-radius:4px; }
        @media print {
            .toolbar, .phone-notch { display:none; }
            body { background:#fff; padding:0; }
            .phone-frame { box-shadow:none; border-radius:0; max-width:100%; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <h5><i class="fa-solid fa-palette me-1" style="color:#6366f1;"></i>{{ $template->name }}</h5>
        <div class="nav-pills">
            @foreach(['login','status','redirect','error','alive','logout'] as $p)
                <a href="{{ route('voucher-templates.preview-page', ['template' => $template, 'page' => $p]) }}"
                   class="{{ $page === $p ? 'active' : '' }}"
                   title="{{ $p }}.html">{{ $p }}</a>
            @endforeach
        </div>
        <button class="close-btn" onclick="window.close()"><i class="fa-solid fa-xmark"></i></button>
    </div>

    <div class="phone-frame">
        <div class="phone-notch">
            <div class="notch-dot"></div>
            <div class="notch-middle"></div>
            <div class="notch-dot"></div>
        </div>
        <div class="phone-content">
            @if($content)
                <div class="rendered-content">{!! $content !!}</div>
            @else
                <div class="empty-state">
                    <i class="fa-solid fa-file-code"></i>
                    <p><strong>{{ $page }}.html</strong> belum memiliki konten.</p>
                    <p style="font-size:0.85rem;margin-top:8px;">
                        <a href="{{ route('vouchers.index', ['tab' => 'templates']) }}" style="color:#6366f1;">Edit template</a>
                        untuk menambahkan halaman ini.
                    </p>
                </div>
            @endif
        </div>
    </div>

    <div class="page-label">
        <i class="fa-solid fa-mobile-screen me-1"></i>{{ $page }}.html
        @if($content)
            &middot; <span style="color:#059669;">{{ strlen(strip_tags($content)) }} karakter teks</span>
        @endif
    </div>
</body>
</html>
