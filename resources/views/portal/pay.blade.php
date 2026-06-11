<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pembayaran ~ {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; }
        .portal-card { border: none; border-radius: 20px; box-shadow: 0 4px 24px rgba(0,0,0,0.06); background: #fff; }
        .portal-header { background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; padding: 2rem; border-radius: 20px 20px 0 0; }
        .logo-icon { width: 48px; height: 48px; background: rgba(255,255,255,0.15); border-radius: 14px; display: flex; align-items: center; justify-content: center; }
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
                        <h5 class="fw-bold mb-1">Pembayaran Online</h5>
                        <p style="opacity:0.8;font-size:0.9rem;">Invoice: {{ $invoice->invoice_code }}</p>
                    </div>
                    <div class="p-4 text-center">
                        <p class="text-muted small">Total Pembayaran</p>
                        <h3 class="fw-bold mb-4">Rp {{ number_format($invoice->amount, 0, ',', '.') }}</h3>

                        <div id="midtrans-payment">
                            <p class="text-muted mb-3">Mengarahkan ke halaman pembayaran...</p>
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>

                        <p class="text-muted small mt-4 mb-0">
                            <i class="fa-solid fa-lock me-1"></i>Pembayaran aman melalui Midtrans
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @php
        $isProd = \App\Models\Setting::get('midtrans_is_production') === '1';
        $snapUrl = $isProd ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js';
        $clientKey = \App\Models\Setting::get('midtrans_client_key');
    @endphp
    <script src="{{ $snapUrl }}" data-client-key="{{ $clientKey }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            snap.pay('{{ $snapToken }}', {
                onSuccess: function(result) { window.location.href = '{{ route("portal.finish", ["order_id" => $invoice->midtrans_order_id]) }}'; },
                onPending: function(result) { window.location.href = '{{ route("portal.finish", ["order_id" => $invoice->midtrans_order_id]) }}'; },
                onError: function(result) { alert('Pembayaran gagal atau dibatalkan.'); window.location.href = '{{ route("portal.index") }}'; },
                onClose: function() { window.location.href = '{{ route("portal.index") }}'; }
            });
        });
    </script>
</body>
</html>
