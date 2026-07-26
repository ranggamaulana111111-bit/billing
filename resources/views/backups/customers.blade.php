@extends('layouts.app')
@section('title', 'Backup Pelanggan')
@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-users me-2" style="color:var(--primary);"></i>Backup Pelanggan</h2>
        <p class="section-subtitle mb-0 mt-1">Backup otomatis seluruh pelanggan PPPoE & Hotspot (termasuk password)</p>
    </div>
    <div class="page-actions mt-2 mt-md-0">
        <form method="POST" action="{{ route('backups.customers.backup') }}" class="d-inline" onsubmit="return confirm('Buat backup pelanggan PPPoE & Hotspot sekarang?')">
            @csrf
            <button type="submit" class="btn btn-primary px-4 py-2">
                <i class="fa-solid fa-download me-2"></i>Backup Sekarang
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
<div class="alert alert-custom alert-info mb-4">
    <i class="fa-solid fa-circle-info me-1"></i>Backup otomatis berjalan setiap hari pukul 03:00. File berisi data PPPoE & Hotspot dalam format JSON.
</div>
<div class="card shadow-sm border-0">
    <div class="card-header bg-white">
        <div class="d-flex align-items-center gap-2">
            <div style="width:8px;height:8px;border-radius:50%;background:var(--primary);"></div>
            <span>Riwayat Backup Pelanggan</span>
            <span class="badge badge-premium ms-2" style="background:#eef2ff;color:var(--primary);">{{ count($files) }}</span>
        </div>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0 mon-table">
            <tr><th>Nama File</th><th>Ukuran (KB)</th><th>Tanggal</th><th class="text-center">Aksi</th></tr>
            <tbody>
                @forelse($files as $f)
                    <tr>
                        <td><code>{{ $f['name'] }}</code></td>
                        <td>{{ $f['size'] }}</td>
                        <td>{{ $f['date'] }}</td>
                        <td class="text-center">
                            <a href="{{ route('backups.customers.download', $f['name']) }}" class="btn btn-sm btn-outline-primary px-2" title="Download">
                                <i class="fa-solid fa-download"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center py-4 text-muted">Belum ada backup pelanggan</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
