@extends('layouts.app')

@section('title', 'Edit Pelanggan')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-pen me-2" style="color:var(--primary);"></i>Edit Pelanggan</h2>
        <p class="section-subtitle mb-0 mt-1">{{ $customer->customer_code }} — {{ $customer->name }}</p>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <form method="POST" action="{{ route('customer.update', $customer->customer_code) }}" enctype="multipart/form-data">
                    @csrf @method('PUT')

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Lengkap</label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', $customer->name) }}" required>
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Lokasi</label>
                        <input type="text" name="location" class="form-control @error('location') is-invalid @enderror"
                               value="{{ old('location', $customer->location) }}">
                        @error('location') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nomor Telepon / WA</label>
                        <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror"
                               value="{{ old('phone', $customer->phone) }}" required>
                        @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email</label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                               value="{{ old('email', $customer->email) }}" placeholder="email@contoh.com">
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">NIK</label>
                        <input type="text" name="nik" class="form-control @error('nik') is-invalid @enderror"
                               value="{{ old('nik', $customer->nik) }}" placeholder="Nomor Induk Kependudukan" maxlength="20">
                        @error('nik') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Upload KTP</label>
                        @if($customer->ktp_photo)
                            <div class="mb-2">
                                <img src="{{ Storage::url($customer->ktp_photo) }}" alt="KTP {{ $customer->name }}"
                                     class="img-thumbnail" style="max-height:150px;">
                                <div class="form-check mt-1">
                                    <input type="checkbox" name="delete_ktp" id="delete_ktp" class="form-check-input" value="1">
                                    <label class="form-check-label text-danger small" for="delete_ktp">Hapus foto KTP</label>
                                </div>
                            </div>
                        @endif
                        <input type="file" name="ktp_photo" class="form-control @error('ktp_photo') is-invalid @enderror"
                               accept="image/jpeg,image/png">
                        @error('ktp_photo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <small class="text-muted">Format: JPG/PNG, maks 2MB. Upload ulang untuk mengganti foto lama.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tipe Client <span class="text-danger">*</span></label>
                        <select name="type" id="type" class="form-select @error('type') is-invalid @enderror" required onchange="toggleClientType()">
                            <option value="ppp" {{ old('type', $customer->type) == 'ppp' ? 'selected' : '' }}>PPPoE</option>
                            <option value="hotspot" {{ old('type', $customer->type) == 'hotspot' ? 'selected' : '' }}>Hotspot</option>
                        </select>
                        @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3" id="package_wrapper">
                        <label class="form-label fw-semibold">Paket Internet <span class="text-danger d-none" id="package_required">*</span></label>
                        <select name="package_id" id="package_id" class="form-select @error('package_id') is-invalid @enderror">
                            <option value="">-- Pilih Paket --</option>
                            @foreach($packages as $p)
                                <option value="{{ $p->id }}" {{ old('package_id', $customer->package_id) == $p->id ? 'selected' : '' }}>
                                    {{ $p->name }} ({{ $p->speed }} Mbps - Rp{{ number_format($p->price, 0, ',', '.') }})
                                </option>
                            @endforeach
                        </select>
                        @error('package_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Titik ODP</label>
                        <select name="odp_id" id="odp_id" class="form-select @error('odp_id') is-invalid @enderror" onchange="updatePorts()">
                            <option value="">-- Pilih ODP --</option>
                            @foreach($odps as $o)
                                <option value="{{ $o->id }}"
                                        data-ports="{{ $o->ports->where('status', 'available')->pluck('port_number')->join(',') }}"
                                        data-current-port="{{ ($customer->odp_id == $o->id && $customer->odpPort) ? $customer->odpPort->port_number : '' }}"
                                        {{ old('odp_id', $customer->odp_id) == $o->id ? 'selected' : '' }}>
                                    {{ $o->nama_odp }} — Tube: {{ $o->kabel_tube_color }} Core: {{ $o->kabel_core_number }}
                                </option>
                            @endforeach
                        </select>
                        @error('odp_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3" id="port_number_wrapper" style="display:none;">
                        <label class="form-label fw-semibold">Nomor Port</label>
                        <select name="odp_port_number" id="odp_port_number" class="form-select @error('odp_port_number') is-invalid @enderror">
                            <option value="">— Pilih Port —</option>
                        </select>
                        @error('odp_port_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <small class="text-muted">Pilih port fisik sesuai instalasi di lapangan</small>
                    </div>

                    <div class="mb-3" id="pppoe_wrapper">
                        <label class="form-label fw-semibold">Username PPP Client <span class="text-danger" id="pppoe_required">*</span></label>
                        <input type="text" name="pppoe_username" id="pppoe_username" class="form-control @error('pppoe_username') is-invalid @enderror"
                               value="{{ old('pppoe_username', $customer->pppoe_username) }}">
                        @error('pppoe_username') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Pilih PON SN dari OLT</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-tower-broadcast"></i></span>
                            <input type="text" id="onu_search" class="form-control" placeholder="Ketik serial number / caller ID untuk cari dari OLT..." autocomplete="off">
                        </div>
                        <div id="onu_results" class="list-group mt-1" style="display:none;max-height:200px;overflow-y:auto;position:absolute;z-index:10;width:calc(100% - 2rem);"></div>
                        <input type="hidden" name="selected_onu_id" id="selected_onu_id">
                        <small class="text-muted">Klik kolom untuk menampilkan ONU terdeteksi (online & belum terpakai), atau ketik untuk filter</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Serial Number ONU</label>
                        <input type="text" name="serial_number" id="serial_number" class="form-control @error('serial_number') is-invalid @enderror"
                               value="{{ old('serial_number', $customer->serial_number) }}" placeholder="cth: CDTCAFCB4305">
                        @error('serial_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <small class="text-muted">Serial ONT/ONU dari stiker perangkat (untuk auto-link monitoring)</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Caller ID</label>
                        <input type="text" name="modem_sn" id="modem_sn" class="form-control @error('modem_sn') is-invalid @enderror"
                               value="{{ old('modem_sn', $customer->modem_sn) }}" placeholder="Caller ID">
                        @error('modem_sn') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Foto Modem</label>
                        @if($customer->modem_photo)
                            <div class="mb-2">
                                <img src="{{ Storage::url($customer->modem_photo) }}" alt="Foto Modem" class="img-thumbnail" style="max-height:120px;">
                            </div>
                        @endif
                        <input type="file" name="modem_photo" class="form-control @error('modem_photo') is-invalid @enderror"
                               accept="image/jpeg,image/png">
                        @error('modem_photo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <small class="text-muted">Format: JPG/PNG, maks 2MB</small>
                    </div>

                    <div class="mb-3" id="due_date_wrapper">
                        <label class="form-label fw-semibold">Tanggal Jatuh Tempo</label>
                        <input type="date" name="due_date" class="form-control @error('due_date') is-invalid @enderror"
                               value="{{ old('due_date', $customer->due_date ? \Carbon\Carbon::parse($customer->due_date)->format('Y-m-d') : '') }}">
                        @error('due_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary px-4">
                            <i class="fa-solid fa-arrow-left me-2"></i>Kembali
                        </a>
                        <button type="submit" class="btn btn-primary px-5">
                            <i class="fa-solid fa-floppy-disk me-2"></i>Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function toggleClientType() {
    const type = document.getElementById('type').value;
    const pkgWrapper = document.getElementById('package_wrapper');
    const pppoeWrapper = document.getElementById('pppoe_wrapper');
    const dueDateWrapper = document.getElementById('due_date_wrapper');
    const pkgRequired = document.getElementById('package_required');
    const pppoeRequired = document.getElementById('pppoe_required');
    const pkgSelect = document.getElementById('package_id');
    const pppoeInput = document.getElementById('pppoe_username');

    if (type === 'hotspot') {
        pkgWrapper.style.display = 'none';
        pkgSelect.removeAttribute('required');
        pkgRequired.classList.add('d-none');
        pppoeWrapper.style.display = 'none';
        pppoeInput.removeAttribute('required');
        dueDateWrapper.style.display = 'none';
    } else {
        pkgWrapper.style.display = '';
        pkgSelect.setAttribute('required', 'required');
        pkgRequired.classList.remove('d-none');
        pppoeWrapper.style.display = '';
        pppoeInput.setAttribute('required', 'required');
        pppoeRequired.classList.remove('d-none');
        dueDateWrapper.style.display = '';
    }
}

function updatePorts() {
    const select = document.getElementById('odp_id');
    const wrapper = document.getElementById('port_number_wrapper');
    const portSelect = document.getElementById('odp_port_number');

    const selected = select.options[select.selectedIndex];
    const ports = selected ? (selected.dataset.ports || '') : '';
    const currentPort = selected ? (selected.dataset.currentPort || '') : '';

    portSelect.innerHTML = '<option value="">— Pilih Port —</option>';

    if (ports || currentPort) {
        wrapper.style.display = 'block';
        ports.split(',').forEach(p => {
            if (!p) return;
            const opt = document.createElement('option');
            opt.value = p;
            opt.textContent = 'Port ' + p;
            portSelect.appendChild(opt);
        });

        if (currentPort && !ports.split(',').includes(currentPort)) {
            const opt = document.createElement('option');
            opt.value = currentPort;
            opt.textContent = 'Port ' + currentPort + ' (sedang dipakai)';
            portSelect.appendChild(opt);
        }
    } else {
        wrapper.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    toggleClientType();

    if (document.getElementById('odp_id').value) {
        updatePorts();
        @if($customer->odpPort)
            document.getElementById('odp_port_number').value = '{{ $customer->odpPort->port_number }}';
        @endif
    }

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
