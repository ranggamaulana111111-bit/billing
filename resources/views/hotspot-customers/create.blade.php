@extends('layouts.app')

@section('title', 'Daftar Pelanggan Hotspot')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-user-plus me-2" style="color:#f59e0b;"></i>Daftar Pelanggan Hotspot</h2>
        <p class="section-subtitle mb-0 mt-1">Isi data pelanggan hotspot baru</p>
    </div>
    <div class="page-actions mt-2 mt-md-0">
        <a href="{{ route('hotspot-customers.index') }}" class="btn btn-outline-premium px-4 py-2">
            <i class="fa-solid fa-arrow-left me-2"></i>Kembali
        </a>
    </div>
</div>

@if(session('error'))
    <div class="alert alert-custom alert-danger mb-4">{{ session('error') }}</div>
@endif

<div class="row justify-content-center">
    <div class="col-lg-6">

        {{-- ONU Info Card --}}
        @if($onu)
        <div class="card shadow-sm border-0 mb-4" style="border-left:4px solid #f59e0b;">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <i class="fa-solid fa-tower-cell" style="font-size:1.5rem;color:#f59e0b;"></i>
                    <div class="flex-fill">
                        <div class="fw-semibold" style="font-size:0.9rem;">ONU Terpilih</div>
                        <div style="font-size:0.8rem;color:#64748b;">
                            {{ $onu->oltPort?->olt?->name ?? '—' }} — Slot {{ $onu->slot_number }} / Port {{ $onu->port_number }}
                        </div>
                    </div>
                    @if($onu->status === 'online')
                        <span class="badge" style="background:#f0fdf4;color:#059669;">Online</span>
                    @else
                        <span class="badge" style="background:#fef2f2;color:#dc2626;">{{ ucfirst($onu->status) }}</span>
                    @endif
                </div>
            </div>
        </div>
        @endif

        {{-- Form --}}
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <div class="d-flex align-items-center gap-2">
                    <div style="width:8px;height:8px;border-radius:50%;background:#f59e0b;"></div>
                    <span>Formulir Pelanggan Hotspot</span>
                </div>
            </div>
            <div class="card-body p-4">
                <form action="{{ route('hotspot-customers.store') }}" method="POST">
                    @csrf

                    <input type="hidden" name="onu_id" value="{{ $onu?->id }}">

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Nama Pelanggan <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name') }}" placeholder="Nama lengkap" required>
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold small">Serial Number ONU</label>
                            <input type="text" class="form-control" value="{{ $onu->serial_number ?? '—' }}" readonly disabled
                                   style="background:#f8fafc;">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold small">Nomor WA <span class="text-danger">*</span></label>
                            <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror"
                                   value="{{ old('phone') }}" placeholder="08xxxxxxxxxx" required>
                            @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold small">Lokasi / Alamat</label>
                            <input type="text" name="location" class="form-control @error('location') is-invalid @enderror"
                                   value="{{ old('location') }}" placeholder="Contoh: Kp. Kumpay RT 02">
                            @error('location') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                        <a href="{{ route('hotspot-customers.index') }}" class="btn btn-light px-4">Batal</a>
                        <button type="submit" class="btn btn-warning px-5">
                            <i class="fa-solid fa-save me-2"></i>Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
