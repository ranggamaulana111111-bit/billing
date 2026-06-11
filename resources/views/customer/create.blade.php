@extends('layouts.app')

@section('title', 'Pasang Baru')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-user-plus me-2" style="color:var(--primary);"></i>Pasang Baru</h2>
        <p class="section-subtitle mb-0 mt-1">Tambahkan pelanggan baru ke sistem</p>
    </div>
    <div class="page-actions mt-2 mt-md-0">
        <a href="{{ route('dashboard') }}" class="btn btn-outline-premium px-4 py-2">
            <i class="fa-solid fa-arrow-left me-2"></i>Kembali
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <div class="d-flex align-items-center gap-2">
                    <div style="width:8px;height:8px;border-radius:50%;background:var(--primary);"></div>
                    <span>Formulir Pelanggan Baru</span>
                </div>
            </div>
            <div class="card-body p-4">
                <form action="{{ route('customer.store') }}" method="POST">
                    @csrf

                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Nama Pelanggan <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name') }}" placeholder="Nama lengkap" required>
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Nomor Telepon / WA <span class="text-danger">*</span></label>
                            <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror"
                                   value="{{ old('phone') }}" placeholder="08xxxxxxxxxx" required>
                            @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Email</label>
                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                                   value="{{ old('email') }}" placeholder="email@contoh.com">
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Lokasi / Alamat</label>
                            <input type="text" name="location" class="form-control @error('location') is-invalid @enderror"
                                   value="{{ old('location') }}" placeholder="Contoh: Kp. Kumpay RT 02">
                            @error('location') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Paket Internet <span class="text-danger">*</span></label>
                            <select name="package_id" class="form-select @error('package_id') is-invalid @enderror" required>
                                <option value="">— Pilih Paket —</option>
                                @foreach($packages as $p)
                                    <option value="{{ $p->id }}" {{ old('package_id') == $p->id ? 'selected' : '' }}>
                                        {{ $p->name }} — {{ $p->speed }}Mbps — Rp{{ number_format($p->price, 0, ',', '.') }}
                                    </option>
                                @endforeach
                            </select>
                            @error('package_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Titik ODP</label>
                            <select name="odp_point_id" class="form-select @error('odp_point_id') is-invalid @enderror">
                                <option value="">— Pilih ODP (opsional) —</option>
                                @foreach($odps as $o)
                                    <option value="{{ $o->id }}" {{ old('odp_point_id') == $o->id ? 'selected' : '' }}>
                                        {{ $o->name }} — {{ $o->address }}
                                    </option>
                                @endforeach
                            </select>
                            @error('odp_point_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">PPPoE Username</label>
                            <input type="text" name="pppoe_username" class="form-control @error('pppoe_username') is-invalid @enderror"
                                   value="{{ old('pppoe_username') }}" placeholder="username">
                            @error('pppoe_username') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Tanggal Jatuh Tempo</label>
                            <input type="date" name="due_date" class="form-control @error('due_date') is-invalid @enderror"
                                   value="{{ old('due_date') }}">
                            @error('due_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                        <a href="{{ route('dashboard') }}" class="btn btn-light px-4">Batal</a>
                        <button type="submit" class="btn btn-primary px-5">
                            <i class="fa-solid fa-save me-2"></i>Simpan Pelanggan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
