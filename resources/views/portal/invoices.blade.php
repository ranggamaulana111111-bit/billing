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
                            <small style="opacity:0.8;">Halo, {{ $customer->name }}</small>
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

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <small class="text-muted">{{ $invoices->count() }} tagihan</small>
                            <small class="text-muted">{{ $customer->package->name ?? '-' }} &middot; {{ $customer->package->speed ?? '-' }}Mbps</small>
                        </div>

                        @forelse($invoices as $inv)
                            <div class="invoice-item d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted">{{ $inv->invoice_code }}</small>
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

                        <hr class="my-3">
                        <p class="text-center footer-text mb-0">
                            {{ $company['name'] }} @if($company['address']) &middot; {{ $company['address'] }} @endif
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
