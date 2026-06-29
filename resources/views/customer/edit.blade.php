@extends('layouts.app')

@section('title', 'Edit Pelanggan')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-pen me-2" style="color:var(--primary);"></i>Edit Pelanggan</h2>
        <p class="section-subtitle mb-0 mt-1">{{ $customer->name }}</p>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <form method="POST" action="{{ route('customer.update', $customer->id) }}">
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
                        <label class="form-label fw-semibold">Paket Internet</label>
                        <select name="package_id" class="form-select @error('package_id') is-invalid @enderror" required>
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
                                <option value="{{ $o->id }}" data-ports="{{ $o->ports->where('status', 'available')->pluck('port_number')->join(',') }}" {{ old('odp_id', $customer->odp_id) == $o->id ? 'selected' : '' }}>
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

                    <div class="mb-3">
                        <label class="form-label fw-semibold">PPPoE Username</label>
                        <input type="text" name="pppoe_username" class="form-control @error('pppoe_username') is-invalid @enderror"
                               value="{{ old('pppoe_username', $customer->pppoe_username) }}">
                        @error('pppoe_username') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
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
    if (document.getElementById('odp_id').value) {
        updatePorts();
        @if($customer->odpPort)
            document.getElementById('odp_port_number').value = '{{ $customer->odpPort->port_number }}';
        @endif
    }
});
</script>
@endpush
