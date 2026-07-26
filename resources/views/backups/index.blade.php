@extends('layouts.app')
@section('title', 'Backup Database')
@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-floppy-disk me-2" style="color:var(--primary);"></i>Backup Database</h2>
        <p class="section-subtitle mb-0 mt-1">Download backup database SQLite atau lihat riwayat backup</p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2 flex-wrap">
        <a href="{{ route('backups.customers') }}" class="btn btn-outline-secondary px-4 py-2">
            <i class="fa-solid fa-users me-2"></i>Backup Pelanggan
        </a>
        <form method="POST" action="{{ route('backups.database') }}" class="d-inline" onsubmit="return confirm('Download backup database sekarang?')">
            @csrf
            <button type="submit" class="btn btn-primary px-4 py-2">
                <i class="fa-solid fa-database me-2"></i>Backup & Download
            </button>
        </form>
    </div>
</div>
@if(session('success'))
    <div class="alert alert-custom alert-success mb-4">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-custom alert-danger mb-4">{{ session('error') }}</div>
@endif
<div class="card shadow-sm border-0">
    <div class="card-header bg-white">
        <div class="d-flex align-items-center gap-2">
            <div style="width:8px;height:8px;border-radius:50%;background:var(--primary);"></div>
            <span>Riwayat Backup</span>
            <span class="badge badge-premium ms-2" style="background:#eef2ff;color:var(--primary);">{{ count($backups) }}</span>
        </div>
    </div>
    <div class="card-body p-0">
                <tr><th>Nama File</th><th>Ukuran</th><th>Tanggal</th><th class="text-center">Aksi</th></tr>

            <tbody>
                @forelse($backups as $b)
                    <tr>
                        <td><code>{{ $b['name'] }}</code></td>
                        <td>{{ $b['size'] }} MB</td>
                        <td>{{ $b['date'] }}</td>
                        <td class="text-center">
                            <div class="d-flex justify-content-center gap-1">
                                <a href="{{ route('backups.download', $b['name']) }}" class="btn btn-sm btn-outline-primary px-2" title="Download">
                                    <i class="fa-solid fa-download"></i>
                                </a>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary px-2 dropdown-toggle" data-bs-toggle="dropdown" style="font-size:0.7rem;"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end" style="font-size:0.8rem;min-width:160px;">
                                        <li><a class="dropdown-item" href="{{ route('backups.download', $b['name']) }}"><i class="fa-solid fa-download me-2 text-primary"></i>Download</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form method="POST" action="{{ route('backups.destroy', $b['name']) }}" onsubmit="return confirm('Hapus backup {{ $b['name'] }}?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="dropdown-item text-danger"><i class="fa-solid fa-trash me-2"></i>Hapus</button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center py-4 text-muted">Belum ada backup</td></tr>
                @endforelse
            </tbody>
<table class="table table-hover align-middle mb-0 mon-table">
        </table>
    </div>
</div>
@endsection
