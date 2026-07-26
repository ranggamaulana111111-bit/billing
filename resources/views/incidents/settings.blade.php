@extends('layouts.app')
@section('title', 'Pengaturan History Gangguan')
@section('content')
<div class="p-4">
    <div class="mb-4">
        <a href="{{ route('incidents.index') }}" class="text-muted text-decoration-none" style="font-size:0.85rem;">
            <i class="fa-solid fa-arrow-left me-1"></i>Kembali ke Daftar Incident
        </a>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">Pengaturan History Gangguan</h4>
            <small class="text-muted">Atur rentang waktu penyimpanan & hapus history insiden yang sudah selesai</small>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success py-2 small" style="border-radius:10px;">{{ session('success') }}</div>
    @endif

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm" style="border-radius:12px;">
                <div class="card-header alarm-head border-0 py-3" style="border-radius:12px 12px 0 0;">
                    <span class="fw-semibold"><i class="fa-solid fa-clock-rotate-left me-2"></i>Rentang Waktu Penyimpanan</span>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('incidents.settings.update') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Simpan history gangguan selama (hari)</label>
                            <input type="number" name="retention_days" class="form-control" min="1" max="3650"
                                value="{{ old('retention_days', $retentionDays) }}" required style="border-radius:8px;">
                            <small class="text-muted">Incident yang berstatus <strong>resolved</strong> atau <strong>closed</strong> yang lebih tua dari rentang ini akan dihapus otomatis setiap bulan, atau manual lewat tombol di samping.</small>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-floppy-disk me-1"></i>Simpan Pengaturan
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm" style="border-radius:12px;">
                <div class="card-header alarm-head border-0 py-3" style="border-radius:12px 12px 0 0;">
                    <span class="fw-semibold"><i class="fa-solid fa-trash-can me-2"></i>Hapus History Manual</span>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('incidents.purge') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Hapus sebelum tanggal (opsional)</label>
                            <input type="date" name="before" class="form-control" style="border-radius:8px;">
                            <small class="text-muted">Kosongkan untuk menggunakan rentang waktu di atas ({{ $retentionDays }} hari). Hanya incident <strong>resolved/closed</strong> yang dihapus.</small>
                        </div>
                        <button type="submit" class="btn btn-danger"
                            onclick="return confirm('Yakin hapus history gangguan sesuai rentang waktu? Tindakan tidak dapat dibatalkan.')">
                            <i class="fa-solid fa-trash-can me-1"></i>Hapus History Sekarang
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
