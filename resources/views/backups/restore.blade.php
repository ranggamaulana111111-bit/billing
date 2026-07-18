@extends('layouts.app')

@section('title', 'Restore Database')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-rotate-left me-2" style="color:var(--primary);"></i>Restore Database</h2>
        <p class="section-subtitle mb-0 mt-1">Restore database dari file backup yang tersedia atau upload backup baru</p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-custom alert-success mb-4">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-custom alert-danger mb-4">{{ session('error') }}</div>
@endif

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <div class="d-flex align-items-center gap-2">
                    <div style="width:8px;height:8px;border-radius:50%;background:var(--primary);"></div>
                    <span>Pilih File Backup untuk Restore</span>
                </div>
            </div>
            <div class="card-body">
                <div class="alert alert-warning d-flex align-items-center gap-2 mb-4" style="border-radius:12px;font-size:0.88rem;">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <div>
                        <strong>Peringatan:</strong> Restore akan mengganti database saat ini. Backup otomatis sebelum restore akan disimpan di folder <code>storage/app/backups/</code>.
                    </div>
                </div>

                @if(count($backups) > 0)
                    <form method="POST" action="{{ route('backups.restore') }}" onsubmit="return confirm('Yakin ingin restore database dari file ini? Semua data saat ini akan diganti.')">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Pilih File Backup</label>
                            <select name="backup_file" class="form-select @error('backup_file') is-invalid @enderror" required>
                                <option value="">-- Pilih file backup --</option>
                                @foreach($backups as $b)
                                    <option value="{{ $b['name'] }}">
                                        {{ $b['name'] }} ({{ $b['size'] }} MB) — {{ $b['date'] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('backup_file')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-primary px-4 py-2">
                            <i class="fa-solid fa-rotate-left me-2"></i>Restore Database
                        </button>
                    </form>
                @else
                    <div class="text-center py-5 text-muted">
                        <i class="fa-solid fa-inbox fa-2x mb-3 d-block"></i>
                        <p>Belum ada file backup tersedia.</p>
                        <p class="mb-0" style="font-size:0.85rem;">Buat backup terlebih dahulu dari menu <a href="{{ route('backups.index') }}">Backup</a>.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <div class="d-flex align-items-center gap-2">
                    <div style="width:8px;height:8px;border-radius:50%;background:#f59e0b;"></div>
                    <span>Upload Backup Baru</span>
                </div>
            </div>
            <div class="card-body">
                <p style="font-size:0.88rem;" class="text-muted mb-3">Upload file backup (.sqlite, .sql, .db) untuk ditambahkan ke daftar restore.</p>
                <form method="POST" action="{{ route('backups.upload') }}" enctype="multipart/form-data" onsubmit="return confirm('Upload file backup ini?')">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold">File Backup</label>
                        <input type="file" name="backup_upload" class="form-control @error('backup_upload') is-invalid @enderror"
                               accept=".sqlite,.sql,.db" required>
                        @error('backup_upload')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-outline-primary px-4 py-2">
                        <i class="fa-solid fa-cloud-arrow-up me-2"></i>Upload
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
