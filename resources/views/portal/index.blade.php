<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Portal pelanggan {{ $company['name'] }} — cek tagihan internet dan bayar online via Midtrans. Mudah, cepat, dan aman.">
    <meta name="robots" content="index, follow">
    <meta property="og:title" content="Portal Pelanggan ~ {{ $company['name'] }}">
    <meta property="og:description" content="Cek tagihan internet dan bayar online via Midtrans.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ asset('images/og-image.svg') }}">
    <link rel="canonical" href="{{ url()->current() }}">
    <title>Portal Pelanggan ~ {{ $company['name'] }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; }
        .portal-card { border: none; border-radius: 20px; box-shadow: 0 4px 24px rgba(0,0,0,0.06); background: #fff; }
        .portal-header { background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; padding: 2rem; border-radius: 20px 20px 0 0; }
        .logo-icon { width: 48px; height: 48px; background: rgba(255,255,255,0.15); border-radius: 14px; display: flex; align-items: center; justify-content: center; }
        .btn-primary { background: #2563eb; border: none; padding: 12px 32px; border-radius: 12px; font-weight: 600; }
        .btn-primary:hover { background: #1d4ed8; }
        .form-control { border-radius: 12px; padding: 14px 18px; border: 2px solid #e2e8f0; }
        .form-control:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
        .footer-text { color: #94a3b8; font-size: 0.8rem; }
    </style>
</head>
<body class="d-flex align-items-center min-vh-100">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="portal-card">
                    <div class="portal-header text-center">
                        <div class="logo-icon mx-auto mb-3">
                            <i class="fa-solid fa-bolt" style="color:#60a5fa;font-size:1.3rem;"></i>
                        </div>
                        <h4 class="fw-bold mb-1">{{ $company['name'] }}</h4>
                        <p style="opacity:0.8;font-size:0.9rem;">Portal Pelanggan</p>
                    </div>
                    <div class="p-4">
                        @if(session('error'))
                            <div class="alert alert-danger py-2 small">{{ session('error') }}</div>
                        @endif
                        <p class="text-muted small mb-4">Masukkan nomor telepon untuk cek tagihan dan bayar online.</p>
                        <form method="POST" action="{{ route('portal.lookup') }}">
                            @csrf
                            <div class="mb-4">
                                <label class="form-label fw-semibold small">Nomor Telepon / WA</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-2 border-end-0" style="border-radius:12px 0 0 12px;">
                                        <i class="fa-solid fa-phone text-muted"></i>
                                    </span>
                                    <input type="text" name="phone" class="form-control border-2 @error('phone') is-invalid @enderror"
                                           value="{{ old('phone') }}" placeholder="08xxxxxxxxxx" required>
                                </div>
                                @error('phone') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fa-solid fa-search me-2"></i>Cari Tagihan
                            </button>
                        </form>
                        @if($company['address'])
                            <hr class="my-4">
                            <p class="text-center footer-text mb-0">
                                <i class="fa-solid fa-location-dot me-1"></i>{{ $company['address'] }}
                                @if($company['phone']) &middot; {{ $company['phone'] }} @endif
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
