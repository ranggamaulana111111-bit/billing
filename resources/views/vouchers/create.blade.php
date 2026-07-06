@extends('layouts.app')

@section('title', 'Buat Voucher WiFi')

@section('head')
<style>
.mt-label { font-size:.85rem; font-weight:600; color:var(--text); margin-bottom:.25rem; }
.gen-info { background:#f8fafc; border-radius:8px; padding:.75rem 1rem; font-size:.85rem; }
.gen-info dt { color:var(--text-muted); font-weight:500; }
.gen-info dd { font-weight:600; margin-bottom:.25rem; }
</style>
@endsection

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-plus me-2" style="color:var(--primary);"></i>Generate User</h2>
        <p class="section-subtitle mb-0 mt-1">Buat user hotspot / voucher WiFi baru</p>
    </div>
    <div class="page-actions mt-2 mt-md-0">
        <a href="{{ route('vouchers.index') }}" class="btn btn-outline-premium px-3 py-2">
            <i class="fa-solid fa-arrow-left me-1"></i>Kembali
        </a>
    </div>
</div>

<form method="POST" action="{{ route('vouchers.store') }}">
    @csrf
    <div class="row g-4">
        {{-- FORM --}}
        <div class="col-lg-7">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">

                    {{-- Qty & Server --}}
                    <div class="row g-3 mb-3">
                        <div class="col-4">
                            <div class="mt-label">Qty</div>
                            <input type="number" name="count" class="form-control" placeholder="1" min="1" max="100" value="{{ old('count', 1) }}" required>
                            @error('count')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-8">
                            <div class="mt-label">Server</div>
                            <select name="hotspot_server" class="form-select">
                                <option value="">Default</option>
                                @foreach($hotspotServers as $srv)
                                    <option value="{{ $srv['name'] ?? $srv }}" {{ old('hotspot_server') == ($srv['name'] ?? $srv) ? 'selected' : '' }}>
                                        {{ $srv['name'] ?? $srv }}
                                    </option>
                                @endforeach
                            </select>
                            @error('hotspot_server')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    {{-- User Mode --}}
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="password_same_as_username" class="form-check-input" id="chkPwdSame" value="1" {{ old('password_same_as_username') ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold" for="chkPwdSame">Username = Password</label>
                        </div>
                    </div>

                    {{-- Name Length & Prefix --}}
                    <div class="row g-3 mb-3">
                        <div class="col-4">
                            <div class="mt-label">Name Length</div>
                            <input type="number" name="name_length" class="form-control" placeholder="4" min="3" max="20" value="{{ old('name_length', $defaultNameLength) }}">
                            @error('name_length')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-8">
                            <div class="mt-label">Prefix</div>
                            <input type="text" name="prefix" class="form-control" placeholder="Contoh: RBN" maxlength="10" value="{{ old('prefix') }}">
                            @error('prefix')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    {{-- Character --}}
                    <div class="mb-3">
                        <div class="mt-label">Character</div>
                        <div class="d-flex gap-4">
                            <div class="form-check">
                                <input type="radio" name="character_type" class="form-check-input" id="charRandom" value="random" {{ old('character_type', 'random') === 'random' ? 'checked' : '' }}>
                                <label class="form-check-label" for="charRandom">Random</label>
                            </div>
                            <div class="form-check">
                                <input type="radio" name="character_type" class="form-check-input" id="charNumeric" value="numeric" {{ old('character_type') === 'numeric' ? 'checked' : '' }}>
                                <label class="form-check-label" for="charNumeric">1234</label>
                            </div>
                        </div>
                    </div>

                    {{-- Profile --}}
                    <div class="mb-3">
                        <div class="mt-label">Profile</div>
                        <select name="mikrotik_profile_name" class="form-select">
                            <option value="">-- Profile MikroTik --</option>
                            @foreach($mikrotikProfiles as $mp)
                                <option value="{{ $mp['name'] }}" {{ old('mikrotik_profile_name') == $mp['name'] ? 'selected' : '' }} data-speed="{{ $mp['speed'] }}">
                                    {{ $mp['name'] }} @if($mp['speed'])({{ $mp['speed'] }})@endif
                                </option>
                            @endforeach
                        </select>
                        @error('mikrotik_profile_name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>

                    {{-- Time Limit & Data Limit --}}
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <div class="mt-label">Time Limit</div>
                            <input type="text" name="time_limit_wdhm" class="form-control" placeholder="12h" value="{{ old('time_limit_wdhm') }}">
                            <div class="form-text mt-1">Format: <code>[wdhm]</code> Contoh: <code>30d</code>, <code>12h</code>, <code>4w3d</code></div>
                            @error('time_limit_wdhm')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-6">
                            <div class="mt-label">Data Limit</div>
                            <div class="input-group">
                                <input type="number" name="data_limit" class="form-control" placeholder="0" min="0" value="{{ old('data_limit') }}">
                                <select name="data_unit" class="form-select" style="max-width:80px">
                                    <option value="MB" {{ old('data_unit', 'MB') === 'MB' ? 'selected' : '' }}>MB</option>
                                    <option value="GB" {{ old('data_unit') === 'GB' ? 'selected' : '' }}>GB</option>
                                </select>
                            </div>
                            @error('data_limit')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    {{-- Router --}}
                    <div class="mb-3">
                        <div class="mt-label">Router</div>
                        <select class="form-select" disabled>
                            <option value="">-- Otomatis dari Profile --</option>
                            @foreach($routers as $router)
                                <option value="{{ $router->id }}" {{ old('router_id') == $router->id ? 'selected' : '' }}>
                                    {{ $router->name }} ({{ $router->host }}:{{ $router->port }})
                                </option>
                            @endforeach
                        </select>
                        <input type="hidden" name="router_id" id="routerIdInput" value="{{ old('router_id') }}">
                        @error('router_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>

                    {{-- Comment --}}
                    <div class="mb-3">
                        <div class="mt-label">Comment</div>
                        <textarea name="description" class="form-control" rows="2" placeholder="Keterangan opsional">{{ old('description') }}</textarea>
                        @error('description')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>

                    {{-- Hidden fields --}}
                    <input type="hidden" name="duration" value="1">
                    <input type="hidden" name="duration_unit" value="hours">

                    {{-- Submit --}}
                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary btn-lg py-3">
                            <i class="fa-solid fa-wand-magic-sparkles me-2"></i>Generate User
                        </button>
                    </div>
                    <div class="form-text mt-2 text-center">
                        Tambah User dengan Time Limit.<br>
                        <small class="text-muted">Time Limit harus lebih kecil dari Validity.</small>
                    </div>

                </div>
            </div>
        </div>

        {{-- INFO SIDE --}}
        <div class="col-lg-5">
            {{-- Last Generate --}}
            @if($lastVoucher)
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="fa-solid fa-clock-rotate-left me-1"></i>Terakhir Generate</h6>
                    <dl class="gen-info row mb-0">
                        <dt class="col-5">Kode Generate</dt>
                        <dd class="col-7">{{ $lastVoucher->username }}</dd>
                        <dt class="col-5">Tanggal</dt>
                        <dd class="col-7">{{ $lastVoucher->created_at->format('d.m.y') }}</dd>
                        <dt class="col-5">Profile</dt>
                        <dd class="col-7">{{ $lastVoucher->speed ?? '-' }}</dd>
                        <dt class="col-5">Masa Berlaku</dt>
                        <dd class="col-7">{{ $lastVoucher->duration_hours }}h</dd>
                        <dt class="col-5">Time Limit</dt>
                        <dd class="col-7">-</dd>
                        <dt class="col-5">Batas Data</dt>
                        <dd class="col-7">{{ $lastVoucher->quota_limit ? number_format($lastVoucher->quota_limit).' MB' : '-' }}</dd>
                        <dt class="col-5">Harga</dt>
                        <dd class="col-7">Rp {{ number_format($lastVoucher->price, 0, ',', '.') }}</dd>
                        <dt class="col-5">Harga Jual</dt>
                        <dd class="col-7">Rp {{ number_format($lastVoucher->price, 0, ',', '.') }}</dd>
                        <dt class="col-5">Kunci User</dt>
                        <dd class="col-7">Nonaktif</dd>
                    </dl>
                </div>
            </div>
            @endif

            {{-- Selected Profile Info --}}
            @if($mikrotikProfiles->isNotEmpty())
            <div class="card shadow-sm border-0 mb-4" id="mikrotikProfileInfoCard" style="display:none;">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="fa-solid fa-tag me-1"></i>Profile Info</h6>
                    <dl class="gen-info row mb-0" id="mikrotikProfileInfoDetails">
                    </dl>
                </div>
            </div>
            @endif

            {{-- Format Time Limit Info --}}
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-2"><i class="fa-solid fa-clock me-1"></i>Format Time Limit</h6>
                    <div class="small text-muted" style="line-height:1.7">
                        <code>[w]</code> = minggu, <code>[d]</code> = hari, <code>[h]</code> = jam, <code>[m]</code> = menit<br>
                        Contoh: <code>30d</code> = 30 hari, <code>24h</code> = 24 jam, <code>12h</code> = 12 jam,<br>
                        <code>1w</code> = 1 minggu, <code>4w3d</code> = 31 hari
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    @if($mikrotikProfiles->isNotEmpty())
    const mtSelect = document.querySelector('select[name="mikrotik_profile_name"]');
    const infoCard = document.getElementById('mikrotikProfileInfoCard');
    const infoDetails = document.getElementById('mikrotikProfileInfoDetails');
    const profiles = @json($mikrotikProfiles->keyBy('name'));

    function updateMikrotikProfileInfo() {
        const name = mtSelect.value;
        const routerSelect = document.querySelector('select[disabled]');
        const routerInput = document.getElementById('routerIdInput');

        if (!name || !profiles[name]) {
            infoCard.style.display = 'none';
            if (routerSelect) routerSelect.value = '';
            if (routerInput) routerInput.value = '';
            return;
        }
        const p = profiles[name];
        infoDetails.innerHTML = `
            <dt class="col-5">Name</dt>
            <dd class="col-7">${p.name}</dd>
            <dt class="col-5">Speed</dt>
            <dd class="col-7">${p.speed || '-'}</dd>
            <dt class="col-5">Router</dt>
            <dd class="col-7">${p.router}</dd>
        `;
        infoCard.style.display = '';

        if (routerSelect && routerInput && p.router_id) {
            routerSelect.value = p.router_id;
            routerInput.value = p.router_id;
        }
    }

    mtSelect.addEventListener('change', updateMikrotikProfileInfo);
    updateMikrotikProfileInfo();
    @endif
});
</script>
@endsection