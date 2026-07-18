<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Daftar tagihan internet {{ $customer->name }} di {{ $company['name'] }}. Lihat status pembayaran dan bayar online.">
    <meta name="robots" content="noindex, follow">
    <meta property="og:title" content="Tagihan Saya ~ {{ $company['name'] }}">
    <meta property="og:description" content="Daftar tagihan internet dan pembayaran online.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <link rel="canonical" href="{{ url()->current() }}">
    <title>Tagihan Saya ~ {{ $company['name'] }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; }
        .portal-card { border: none; border-radius: 20px; box-shadow: 0 4px 24px rgba(0,0,0,0.06); background: #fff; }
        .portal-header { background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; padding: 1.5rem 2rem; border-radius: 20px 20px 0 0; }
        .logo-icon { width: 40px; height: 40px; background: rgba(255,255,255,0.15); border-radius: 12px; display: flex; align-items: center; justify-content: center; }
        .badge-status { padding: 6px 14px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .btn-pay { background: #2563eb; border: none; border-radius: 12px; font-weight: 600; padding: 8px 20px; }
        .btn-pay:hover { background: #1d4ed8; }
        .btn-outline-pay { border: 2px solid #2563eb; color: #2563eb; border-radius: 12px; font-weight: 600; padding: 8px 20px; }
        .btn-outline-pay:hover { background: #2563eb; color: #fff; }
        .footer-text { color: #94a3b8; font-size: 0.8rem; }
        .invoice-item { border-bottom: 1px solid #f1f5f9; padding: 1rem 0; }
        .invoice-item:last-child { border-bottom: none; }
        .nav-pills .nav-link.active { background: #2563eb; color: #fff; }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="portal-card">
                    <div class="portal-header d-flex align-items-center gap-3">
                        <div class="logo-icon">
                            <i class="fa-solid fa-bolt" style="color:#60a5fa;font-size:1.1rem;"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0">{{ $company['name'] }}</h5>
                            <small style="opacity:0.8;">Halo, {{ $customer->name }} ({{ $customer->customer_code }})</small>
                        </div>
                        <a href="{{ route('portal.index') }}" class="btn btn-sm ms-auto" style="background:rgba(255,255,255,0.15);color:#fff;border-radius:10px;">
                            <i class="fa-solid fa-arrow-left me-1"></i>Kembali
                        </a>
                    </div>
                    <div class="p-4">
                        @if(session('error'))
                            <div class="alert alert-danger py-2 small">{{ session('error') }}</div>
                        @endif
                        @if(session('success'))
                            <div class="alert alert-success py-2 small">{{ session('success') }}</div>
                        @endif

                        <ul class="nav nav-pills mb-4" style="border-radius:12px;background:#f1f5f9;padding:4px;">
                            <li class="nav-item flex-fill">
                                <button class="nav-link active w-100" data-bs-toggle="pill" data-bs-target="#tab-invoices"
                                        style="border-radius:10px;font-weight:600;font-size:0.85rem;">
                                    <i class="fa-solid fa-file-invoice me-1"></i>Tagihan
                                </button>
                            </li>
                            <li class="nav-item flex-fill">
                                <button class="nav-link w-100" data-bs-toggle="pill" data-bs-target="#tab-gangguan"
                                        style="border-radius:10px;font-weight:600;font-size:0.85rem;">
                                    <i class="fa-solid fa-triangle-exclamation me-1"></i>Gangguan
                                    @if($incidents->isNotEmpty())
                                        <span class="badge bg-danger ms-1" style="font-size:0.65rem;">{{ $incidents->count() }}</span>
                                    @endif
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="tab-invoices">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <small class="text-muted">{{ $invoices->count() }} tagihan</small>
                                    <small class="text-muted">{{ $customer->package->name ?? '-' }} &middot; {{ $customer->package->speed ?? '-' }}Mbps</small>
                                </div>

                                @forelse($invoices as $inv)
                                    <div class="invoice-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <small class="text-muted">{{ $inv->invoice_display }}</small>
                                            <p class="fw-bold mb-0 mt-1">Rp {{ number_format($inv->amount, 0, ',', '.') }}</p>
                                            <small class="text-muted">{{ $inv->created_at->format('d/m/Y') }}</small>
                                        </div>
                                        <div class="text-end">
                                            @if($inv->payment_status === 'paid')
                                                <span class="badge-status" style="background:#f0fdf4;color:#059669;">
                                                    <i class="fa-regular fa-circle-check me-1"></i>Lunas
                                                </span>
                                            @else
                                                <span class="badge-status d-block mb-2" style="background:#fef2f2;color:#dc2626;">
                                                    <i class="fa-regular fa-clock me-1"></i>Belum
                                                </span>
                                                @if($midtransConfigured)
                                                    <a href="{{ route('portal.bayar', $inv->id) }}" class="btn btn-pay btn-sm text-white">
                                                        <i class="fa-solid fa-credit-card me-1"></i>Bayar
                                                    </a>
                                                @endif
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-center py-5 text-muted">
                                        <i class="fa-regular fa-file-lines" style="font-size:2rem;display:block;margin-bottom:12px;"></i>
                                        Belum ada tagihan
                                    </div>
                                @endforelse
                            </div>

                            <div class="tab-pane fade" id="tab-gangguan">
                                @if($incidents->isEmpty())
                                    <div class="text-center py-5 text-muted">
                                        <i class="fa-solid fa-circle-check" style="font-size:2rem;display:block;margin-bottom:12px;color:#16a34a;"></i>
                                        Tidak ada gangguan aktif di area Anda. Layanan normal.
                                    </div>
                                @else
                                    <p class="text-muted small mb-3">Berikut gangguan yang sedang terjadi di area Anda:</p>
                                    @foreach($incidents as $inc)
                                        @php
                                            $sevBg = match($inc->severity) {
                                                'critical' => '#fef2f2',
                                                'high' => '#fff7ed',
                                                'medium' => '#fefce8',
                                                'low' => '#f9fafb',
                                                default => '#f9fafb',
                                            };
                                            $sevColor = match($inc->severity) {
                                                'critical' => '#dc2626',
                                                'high' => '#ea580c',
                                                'medium' => '#ca8a04',
                                                'low' => '#6b7280',
                                                default => '#6b7280',
                                            };
                                            $statusLabel = match($inc->status) {
                                                'open' => 'Dilaporkan',
                                                'investigating' => 'Sedang Ditangani',
                                                default => ucfirst($inc->status),
                                            };
                                        @endphp
                                        <div style="background:{{ $sevBg }};border-radius:12px;padding:1rem;margin-bottom:0.75rem;border-left:4px solid {{ $sevColor }};">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <div class="fw-bold" style="color:{{ $sevColor }};">{{ $inc->title }}</div>
                                                    <small class="text-muted">{{ $statusLabel }} &middot; {{ $inc->detected_at?->diffForHumans() }}</small>
                                                </div>
                                                <span class="badge" style="background:{{ $sevColor }};color:#fff;border-radius:6px;font-size:0.7rem;">
                                                    {{ strtoupper($inc->severity) }}
                                                </span>
                                            </div>
                                            @if($inc->sla_deadline)
                                                <small class="text-muted mt-1 d-block">Estimasi perbaikan: {{ $inc->sla_deadline->format('d/m/Y H:i') }}</small>
                                            @endif
                                        </div>
                                    @endforeach
                                @endif
                            </div>
                        </div>

                        <hr class="my-3">
                        <p class="text-center footer-text mb-0">
                            {{ $company['name'] }} @if($company['address']) &middot; {{ $company['address'] }} @endif
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
