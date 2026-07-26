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

<div class="card shadow-sm border-0">
    <div class="card-header bg-white">
        <div class="d-flex align-items-center gap-2">
            <div style="width:8px;height:8px;border-radius:50%;background:var(--primary);"></div>
            <span>Formulir Pelanggan Baru</span>
        </div>
    </div>
    <div class="card-body p-4">
        <form action="{{ route('customer.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            {{-- Section: Data Diri --}}
            <div class="mb-3">
                <h6 class="text-uppercase text-muted fw-bold" style="font-size:0.7rem;letter-spacing:0.05em;">
                    <i class="fa-solid fa-user me-1"></i>Data Diri Pelanggan
                </h6>
                <hr class="mt-1 mb-3">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Nama Pelanggan <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name') }}" placeholder="Nama lengkap" required>
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Nomor Telepon / WA <span class="text-danger">*</span></label>
                        <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror"
                               value="{{ old('phone') }}" placeholder="08xxxxxxxxxx" required>
                        @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Email</label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                               value="{{ old('email') }}" placeholder="email@contoh.com">
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">NIK</label>
                        <input type="text" name="nik" class="form-control @error('nik') is-invalid @enderror"
                               value="{{ old('nik') }}" placeholder="Nomor Induk Kependudukan" maxlength="20">
                        @error('nik') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Upload KTP</label>
                        <input type="file" name="ktp_photo" class="form-control @error('ktp_photo') is-invalid @enderror"
                               accept="image/jpeg,image/png">
                        @error('ktp_photo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Lokasi / Alamat</label>
                        <input type="text" name="location" class="form-control @error('location') is-invalid @enderror"
                               value="{{ old('location') }}" placeholder="Contoh: Kp. Kumpay RT 02">
                        @error('location') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>

            {{-- Section: Konfigurasi --}}
            <div class="mb-3">
                <h6 class="text-uppercase text-muted fw-bold" style="font-size:0.7rem;letter-spacing:0.05em;">
                    <i class="fa-solid fa-gear me-1"></i>Konfigurasi Layanan
                </h6>
                <hr class="mt-1 mb-3">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Tipe Client <span class="text-danger">*</span></label>
                        <select name="type" id="type" class="form-select @error('type') is-invalid @enderror" required onchange="toggleClientType()">
                            <option value="ppp" {{ old('type') == 'hotspot' ? '' : 'selected' }}>PPPoE</option>
                            <option value="hotspot" {{ old('type') == 'hotspot' ? 'selected' : '' }}>Hotspot</option>
                        </select>
                        @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-5" id="package_wrapper">
                        <label class="form-label fw-semibold small">Paket Internet <span class="text-danger" id="package_required">*</span></label>
                        <select name="package_id" id="package_id" class="form-select @error('package_id') is-invalid @enderror">
                            <option value="">— Pilih Paket —</option>
                            @foreach($packages as $p)
                                <option value="{{ $p->id }}" {{ old('package_id') == $p->id ? 'selected' : '' }}>
                                    {{ $p->name }} — {{ $p->speed }}Mbps — Rp{{ number_format($p->price, 0, ',', '.') }}
                                </option>
                            @endforeach
                        </select>
                        @error('package_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4" id="due_date_wrapper">
                        <label class="form-label fw-semibold small">Tanggal Jatuh Tempo</label>
                        <input type="date" name="due_date" class="form-control @error('due_date') is-invalid @enderror"
                               value="{{ old('due_date') }}">
                        @error('due_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4" id="pppoe_wrapper">
                        <label class="form-label fw-semibold small">Username PPP Client <span class="text-danger" id="pppoe_required">*</span></label>
                        <input type="text" id="pppoe_search" class="form-control" placeholder="Ketik username PPPoE..." autocomplete="off">
                        <div id="pppoe_results" class="list-group mt-1" style="display:none;max-height:200px;overflow-y:auto;position:absolute;z-index:10;width:calc(100% - 2rem);"></div>
                        <input type="hidden" name="pppoe_username" id="pppoe_username" value="{{ old('pppoe_username') }}">
                        @error('pppoe_username') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4" id="pppoe_password_wrapper">
                        <label class="form-label fw-semibold small">PPPoE Password</label>
                        <div class="input-group">
                            <input type="password" name="pppoe_password" id="pppoe_password"
                                   class="form-control @error('pppoe_password') is-invalid @enderror"
                                   value="{{ old('pppoe_password') }}" placeholder="password">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('pppoe_password', this)">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                            @error('pppoe_password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Titik ODP</label>
                        <select name="odp_id" id="odp_id" class="form-select @error('odp_id') is-invalid @enderror" onchange="updatePorts()">
                            <option value="">— Pilih ODP (opsional) —</option>
                            @foreach($odps as $o)
                                <option value="{{ $o->id }}" data-ports="{{ $o->ports->where('status', 'available')->pluck('port_number')->join(',') }}" {{ old('odp_id') == $o->id ? 'selected' : '' }}>
                                    {{ $o->nama_odp }} — {{ $o->kabel_tube_color }} Core: {{ $o->kabel_core_number }}
                                </option>
                            @endforeach
                        </select>
                        @error('odp_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4" id="port_number_wrapper" style="display:none;">
                        <label class="form-label fw-semibold small">Nomor Port</label>
                        <select name="odp_port_number" id="odp_port_number" class="form-select @error('odp_port_number') is-invalid @enderror">
                            <option value="">— Pilih Port —</option>
                        </select>
                        @error('odp_port_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>

            {{-- Section: Perangkat --}}
            <div class="mb-3">
                <h6 class="text-uppercase text-muted fw-bold" style="font-size:0.7rem;letter-spacing:0.05em;">
                    <i class="fa-solid fa-tower-broadcast me-1"></i>Perangkat & OLT
                </h6>
                <hr class="mt-1 mb-3">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold small">Pilih PON SN dari OLT</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-tower-broadcast"></i></span>
                            <input type="text" id="onu_search" class="form-control" placeholder="Ketik serial number / caller ID untuk cari dari OLT..." autocomplete="off">
                        </div>
                        <div id="onu_results" class="list-group mt-1" style="display:none;max-height:200px;overflow-y:auto;position:absolute;z-index:10;width:calc(100% - 2rem);"></div>
                        <input type="hidden" name="selected_onu_id" id="selected_onu_id">
                        <small class="text-muted">Ketik untuk mencari ONU yang belum terpakai dari OLT, lalu pilih untuk auto-fill</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Serial Number ONU</label>
                        <input type="text" name="serial_number" id="serial_number" class="form-control @error('serial_number') is-invalid @enderror"
                               value="{{ old('serial_number') }}" placeholder="cth: CDTCAFCB4305">
                        @error('serial_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Caller ID</label>
                        <input type="text" name="modem_sn" id="modem_sn" class="form-control @error('modem_sn') is-invalid @enderror"
                               value="{{ old('modem_sn') }}" placeholder="Caller ID">
                        @error('modem_sn') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Foto Modem</label>
                        <input type="file" name="modem_photo" class="form-control @error('modem_photo') is-invalid @enderror"
                               accept="image/jpeg,image/png">
                        @error('modem_photo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
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
@endsection

@push('scripts')
<script>
function togglePassword(fieldId, btn) {
    const input = document.getElementById(fieldId);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

function toggleClientType() {
    const type = document.getElementById('type').value;
    const pkgWrapper = document.getElementById('package_wrapper');
    const pppoeWrapper = document.getElementById('pppoe_wrapper');
    const pppoePassWrapper = document.getElementById('pppoe_password_wrapper');
    const dueDateWrapper = document.getElementById('due_date_wrapper');
    const pkgRequired = document.getElementById('package_required');
    const pppoeRequired = document.getElementById('pppoe_required');
    const pkgSelect = document.getElementById('package_id');
    const pppoeSearchInput = document.getElementById('pppoe_search');

    if (type === 'hotspot') {
        pkgWrapper.style.display = 'none';
        pkgSelect.removeAttribute('required');
        pkgRequired.classList.add('d-none');
        pppoeWrapper.style.display = 'none';
        pppoeSearchInput.removeAttribute('required');
        pppoeRequired.classList.add('d-none');
        pppoePassWrapper.style.display = 'none';
        dueDateWrapper.style.display = 'none';
    } else {
        pkgWrapper.style.display = '';
        pkgSelect.setAttribute('required', 'required');
        pkgRequired.classList.remove('d-none');
        pppoeWrapper.style.display = '';
        pppoeSearchInput.setAttribute('required', 'required');
        pppoeRequired.classList.remove('d-none');
        pppoePassWrapper.style.display = '';
        dueDateWrapper.style.display = '';
    }
}

function updatePorts() {
    const select = document.getElementById('odp_id');
    const wrapper = document.getElementById('port_number_wrapper');
    const portSelect = document.getElementById('odp_port_number');

    const selected = select.options[select.selectedIndex];
    const ports = selected ? (selected.dataset.ports || '') : '';

    portSelect.innerHTML = '<option value="">— Pilih Port —</option>';

    if (ports) {
        wrapper.style.display = 'block';
        ports.split(',').forEach(p => {
            const opt = document.createElement('option');
            opt.value = p;
            opt.textContent = 'Port ' + p;
            portSelect.appendChild(opt);
        });
    } else {
        wrapper.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    toggleClientType();

    if (document.getElementById('odp_id').value) {
        updatePorts();
    }

    const pppoeInput = document.getElementById('pppoe_search');
    const pppoeResults = document.getElementById('pppoe_results');
    const pppoeHidden = document.getElementById('pppoe_username');
    const pppoePassInput = document.getElementById('pppoe_password');
    let pppoeTimer;

    pppoeInput.addEventListener('input', function () {
        clearTimeout(pppoeTimer);
        const q = this.value.trim();
        if (q.length < 2) { pppoeResults.style.display = 'none'; return; }

        pppoeTimer = setTimeout(() => {
            fetch('{{ route("pppoe.available") }}?search=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(data => {
                    pppoeResults.innerHTML = '';
                    if (!data.length) {
                        pppoeResults.innerHTML = '<div class="list-group-item text-muted" style="font-size:0.8rem;">Tidak ada username ditemukan</div>';
                        pppoeResults.style.display = 'block';
                        return;
                    }
                    data.forEach(secret => {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'list-group-item list-group-item-action';
                        btn.style.cssText = 'font-size:0.78rem;text-align:left;';
                        btn.innerHTML = '<i class="fa-solid fa-user me-1 text-primary"></i>' +
                            '<strong>' + secret.name + '</strong>' +
                            '<br><small class="text-muted">Profile: ' + (secret.profile || '-') +
                            (secret.service ? ' — ' + secret.service : '') + '</small>' +
                            '<br><small class="text-muted"><i class="fa-solid fa-lock me-1"></i>' +
                            (secret.password || '(tersembunyi)') + '</small>';
                        btn.addEventListener('click', function () {
                            pppoeHidden.value = secret.name;
                            pppoeInput.value = secret.name;
                            pppoePassInput.value = secret.password || '';
                            pppoeResults.style.display = 'none';
                        });
                        pppoeResults.appendChild(btn);
                    });
                    pppoeResults.style.display = 'block';
                });
        }, 300);
    });

    document.addEventListener('click', function (e) {
        if (!pppoeInput.contains(e.target) && !pppoeResults.contains(e.target)) {
            pppoeResults.style.display = 'none';
        }
    });

    const searchInput = document.getElementById('onu_search');
    const resultsBox = document.getElementById('onu_results');
    let debounceTimer;

    function renderOnus(data) {
        resultsBox.innerHTML = '';
        if (!data.length) {
            resultsBox.innerHTML = '<div class="list-group-item text-muted" style="font-size:0.8rem;">Tidak ada ONU terdeteksi ditemukan</div>';
            resultsBox.style.display = 'block';
            return;
        }
        data.forEach(onu => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'list-group-item list-group-item-action';
            btn.style.cssText = 'font-size:0.78rem;text-align:left;';
            btn.innerHTML = '<i class="fa-solid fa-tower-broadcast me-1 text-primary"></i>' +
                '<strong>' + (onu.serial_number || onu.caller_id || onu.onu_id) + '</strong>' +
                '<br><small class="text-muted">' + onu.olt_name + ' — Port ' + onu.olt_port +
                (onu.vendor ? ' — ' + onu.vendor : '') + '</small>';
            btn.addEventListener('click', function () {
                document.getElementById('serial_number').value = onu.serial_number || '';
                document.getElementById('modem_sn').value = onu.caller_id || onu.mac_address || onu.onu_id || '';
                document.getElementById('selected_onu_id').value = onu.id;
                searchInput.value = onu.serial_number || onu.caller_id || '';
                resultsBox.style.display = 'none';
            });
            resultsBox.appendChild(btn);
        });
        resultsBox.style.display = 'block';
    }

    function fetchOnus(q) {
        const url = q
            ? '{{ route("onu.available") }}?search=' + encodeURIComponent(q)
            : '{{ route("onu.available") }}';
        fetch(url)
            .then(r => r.json())
            .then(renderOnus);
    }

    searchInput.addEventListener('focus', function () {
        if (!resultsBox.innerHTML.trim()) {
            fetchOnus(this.value.trim());
        }
    });

    searchInput.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        const q = this.value.trim();
        if (q.length < 2) { resultsBox.style.display = 'none'; return; }

        debounceTimer = setTimeout(() => fetchOnus(q), 300);
    });

    document.addEventListener('click', function (e) {
        if (!searchInput.contains(e.target) && !resultsBox.contains(e.target)) {
            resultsBox.style.display = 'none';
        }
    });
});
</script>
@endpush
