@extends('layouts.app')
@section('title', 'Laporan Aset')
@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-clipboard-list me-2" style="color:var(--primary);"></i>Laporan Aset</h2>
        <p class="section-subtitle mb-0 mt-1">Ringkasan pergerakan inventaris barang</p>
    </div>
</div>
@if(session('success'))
    <div class="alert alert-custom alert-success mb-4">{{ session('success') }}</div>
@endif
{{-- Filter Tahun --}}
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold" style="font-size:0.8rem;">Tahun</label>
                <select name="year" class="form-select form-select-sm">
                    @for($y = date('Y'); $y >= date('Y') - 5; $y--)
                        <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary btn-sm px-4">
                    <i class="fa-solid fa-filter me-1"></i>Tampilkan
                </button>
            </div>
        </form>
    </div>
</div>
{{-- Ringkasan --}}
<div class="row g-3 mb-4">
    <div class="col-md">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body text-center">
                <div class="mb-2"><i class="fa-solid fa-arrow-down fa-lg" style="color:#059669;"></i></div>
                <h3 class="fw-bold mb-0" style="color:#059669;">{{ number_format($grandMasuk) }}</h3>
                <small class="text-muted">Total Masuk</small>
            </div>
        </div>
    </div>
    <div class="col-md">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body text-center">
                <div class="mb-2"><i class="fa-solid fa-arrow-up fa-lg" style="color:#2563eb;"></i></div>
                <h3 class="fw-bold mb-0" style="color:#2563eb;">{{ number_format($grandKeluar) }}</h3>
                <small class="text-muted">Total Keluar (Baik)</small>
            </div>
        </div>
    </div>
    <div class="col-md">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body text-center">
                <div class="mb-2"><i class="fa-solid fa-wrench fa-lg" style="color:#f59e0b;"></i></div>
                <h3 class="fw-bold mb-0" style="color:#f59e0b;">{{ number_format($grandTerpakai) }}</h3>
                <small class="text-muted">Terpakai / Terinstall</small>
            </div>
        </div>
    </div>
    <div class="col-md">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body text-center">
                <div class="mb-2"><i class="fa-solid fa-broken fa-lg" style="color:#dc2626;"></i></div>
                <h3 class="fw-bold mb-0" style="color:#dc2626;">{{ number_format($grandRusak) }}</h3>
                <small class="text-muted">Rusak</small>
            </div>
        </div>
    </div>
    <div class="col-md">
        <div class="card shadow-sm border-0 h-100" style="border-left:3px solid var(--primary) !important;">
            <div class="card-body text-center">
                <div class="mb-2"><i class="fa-solid fa-boxes-stacked fa-lg" style="color:var(--primary);"></i></div>
                <h3 class="fw-bold mb-0" style="color:var(--primary);">{{ number_format($grandSisa) }}</h3>
                <small class="text-muted">Sisa Stok</small>
            </div>
        </div>
    </div>
</div>
{{-- Detail per Kategori --}}
<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex align-items-center gap-2">
        <div style="width:8px;height:8px;border-radius:50%;background:var(--primary);"></div>
        <span class="fw-semibold">Detail per Kategori</span>
        <span class="badge bg-primary ms-auto">{{ count($summary) }} kategori</span>
    </div>
    <div class="card-body p-0">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
                    <tr>
                        <th class="ps-3">Kategori</th>
                        <th class="text-center">Masuk</th>
                        <th class="text-center">Keluar (Baik)</th>
                        <th class="text-center">Terpakai</th>
                        <th class="text-center">Rusak</th>
                        <th class="text-center fw-bold">Sisa Stok</th>
                    </tr>

                <tbody style="font-size:0.82rem;">
                    @forelse($summary as $key => $row)
                        <tr>
                            <td class="ps-3">
                                <span class="badge" style="background:#eff6ff;color:#2563eb;font-weight:500;">
                                    {{ $row['label'] }}
                                </span>
                            </td>
                            <td class="text-center">
                                <span style="color:#059669;font-weight:600;">+{{ number_format($row['masuk']) }}</span>
                            </td>
                            <td class="text-center">
                                <span style="color:#2563eb;font-weight:600;">-{{ number_format($row['keluar']) }}</span>
                            </td>
                            <td class="text-center">
                                <span style="color:#f59e0b;font-weight:600;">-{{ number_format($row['terpakai']) }}</span>
                            </td>
                            <td class="text-center">
                                <span style="color:#dc2626;font-weight:600;">-{{ number_format($row['rusak']) }}</span>
                            </td>
                            <td class="text-center">
                                <span class="fw-bold" style="font-size:0.95rem;color:var(--primary);">{{ number_format($row['sisa']) }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-inbox fa-2x mb-3 d-block" style="opacity:0.3;"></i>
                                Belum ada data inventaris.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if(count($summary) > 0)
                    <tfoot style="background:#f1f5f9;font-weight:700;font-size:0.82rem;">
                        <tr>
                            <td class="ps-3">TOTAL</td>
                            <td class="text-center" style="color:#059669;">+{{ number_format($grandMasuk) }}</td>
                            <td class="text-center" style="color:#2563eb;">-{{ number_format($grandKeluar) }}</td>
                            <td class="text-center" style="color:#f59e0b;">-{{ number_format($grandTerpakai) }}</td>
                            <td class="text-center" style="color:#dc2626;">-{{ number_format($grandRusak) }}</td>
                            <td class="text-center" style="color:var(--primary);font-size:1rem;">{{ number_format($grandSisa) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
