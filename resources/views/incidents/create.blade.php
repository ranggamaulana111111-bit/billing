@extends('layouts.app')

@section('title', 'Buat Incident Baru')

@section('content')
<div class="p-4">
    <div class="mb-4">
        <a href="{{ route('incidents.index') }}" class="text-muted text-decoration-none" style="font-size:0.85rem;">
            <i class="fa-solid fa-arrow-left me-1"></i>Kembali ke Daftar Incident
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-7">
            <div class="card border-0 shadow-sm" style="border-radius:16px;">
                <div class="card-header bg-white border-0 py-4" style="border-radius:16px 16px 0 0;">
                    <h5 class="fw-bold mb-1">Buat Incident Baru</h5>
                    <small class="text-muted">Laporkan gangguan jaringan yang terjadi</small>
                </div>
                <div class="card-body px-4 pb-4">
                    @if($errors->any())
                        <div class="alert alert-danger py-2 small">
                            @foreach($errors->all() as $e)
                                <div>{{ $e }}</div>
                            @endforeach
                        </div>
                    @endif

                    <form method="POST" action="{{ route('incidents.store') }}" id="incidentForm">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Judul Gangguan <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" value="{{ old('title') }}"
                                   placeholder="Contoh: Kabel putus ODP Sentul 1" required
                                   style="border-radius:10px;border:2px solid #e2e8f0;">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Deskripsi</label>
                            <textarea name="description" class="form-control" rows="3"
                                      placeholder="Detail gangguan (opsional)"
                                      style="border-radius:10px;border:2px solid #e2e8f0;">{{ old('description') }}</textarea>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Severity <span class="text-danger">*</span></label>
                                <select name="severity" class="form-select" required
                                        style="border-radius:10px;border:2px solid #e2e8f0;">
                                    <option value="low" {{ old('severity') === 'low' ? 'selected' : '' }}>🟢 Low — Gangguan ringan</option>
                                    <option value="medium" {{ old('severity', 'medium') === 'medium' ? 'selected' : '' }}>🟡 Medium — Gangguan sedang</option>
                                    <option value="high" {{ old('severity') === 'high' ? 'selected' : '' }}>🟠 High — Gangguan besar</option>
                                    <option value="critical" {{ old('severity') === 'critical' ? 'selected' : '' }}>🔴 Critical — Gangguan total</option>
                                </select>
                                <small class="text-muted">Menentukan SLA: Critical=4j, High=8j, Medium=24j, Low=72j</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">ODP Terdampak</label>
                                <select name="odp_id" class="form-select" id="odpSelect"
                                        style="border-radius:10px;border:2px solid #e2e8f0;">
                                    <option value="">— Pilih ODP —</option>
                                    @foreach($odps as $odp)
                                        <option value="{{ $odp->id }}" data-customers="{{ json_encode($odp->customers->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'phone' => $c->phone])) }}" {{ old('odp_id') == $odp->id ? 'selected' : '' }}>
                                            {{ $odp->nama_odp }} ({{ $odp->odc?->nama_odc ?? '-' }}) — {{ $odp->customers->count() }} pelanggan
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Pelanggan di ODP ini akan menerima notifikasi WA</small>
                            </div>
                        </div>

                        {{-- Customer Selector --}}
                        <div class="mb-3" id="customerSection" style="display:none;">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <label class="form-label fw-semibold small mb-0">
                                    Pilih Pelanggan yang Diberitahu
                                </label>
                                <div class="d-flex align-items-center gap-2">
                                    <span id="customerCount" class="badge bg-primary" style="font-size:0.7rem;">0 dipilih</span>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnSelectAll" style="font-size:0.7rem;padding:2px 8px;border-radius:6px;">
                                        Pilih Semua
                                    </button>
                                </div>
                            </div>
                            <div id="customerList" class="border rounded-3 p-2" style="max-height:200px;overflow-y:auto;background:#f8fafc;border-color:#e2e8f0 !important;">
                                <div class="text-center text-muted py-3" style="font-size:0.8rem;">Pilih ODP terlebih dahulu</div>
                            </div>
                            <small class="text-muted">Cosongkan untuk memberitahu semua pelanggan di ODP ini</small>
                        </div>

                        <div class="d-flex gap-2 mt-4">
                            <a href="{{ route('incidents.index') }}" class="btn btn-light flex-fill" style="border-radius:10px;">Batal</a>
                            <button type="submit" class="btn btn-primary flex-fill" style="border-radius:10px;">
                                <i class="fa-solid fa-paper-plane me-1"></i>Buat & Kirim Notifikasi
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const odpSelect = document.getElementById('odpSelect');
    const customerSection = document.getElementById('customerSection');
    const customerList = document.getElementById('customerList');
    const customerCount = document.getElementById('customerCount');
    const btnSelectAll = document.getElementById('btnSelectAll');
    let allSelected = true;

    function getSelectedOdpCustomers() {
        const selected = odpSelect.options[odpSelect.selectedIndex];
        if (!selected || !selected.value) return [];
        try {
            return JSON.parse(selected.dataset.customers || '[]');
        } catch(e) {
            return [];
        }
    }

    function renderCustomers(customers) {
        if (customers.length === 0) {
            customerList.innerHTML = '<div class="text-center text-muted py-3" style="font-size:0.8rem;">Tidak ada pelanggan aktif dengan nomor HP di ODP ini</div>';
            customerSection.style.display = 'none';
            return;
        }

        customerSection.style.display = 'block';
        allSelected = true;

        let html = '<div class="form-check mb-1 pb-1" style="border-bottom:1px solid #e2e8f0;">' +
            '<input class="form-check-input" type="checkbox" id="checkAll" checked>' +
            '<label class="form-check-label fw-semibold small" for="checkAll" style="font-size:0.78rem;">Pilih Semua (' + customers.length + ')</label>' +
            '</div>';

        customers.forEach(function(c) {
            const phone = c.phone || '';
            const maskedPhone = phone.length > 4 ? phone.substring(0, 3) + '***' + phone.substring(phone.length - 2) : phone;
            html += '<div class="form-check py-1">' +
                '<input class="form-check-input customer-cb" type="checkbox" name="customer_ids[]" value="' + c.id + '" id="cust_' + c.id + '" checked>' +
                '<label class="form-check-label" for="cust_' + c.id + '" style="font-size:0.78rem;">' +
                '<span class="fw-medium">' + c.name + '</span>' +
                '<small class="text-muted ms-1">' + maskedPhone + '</small>' +
                '</label></div>';
        });

        customerList.innerHTML = html;
        updateCount();

        document.getElementById('checkAll').addEventListener('change', function() {
            const checkboxes = customerList.querySelectorAll('.customer-cb');
            checkboxes.forEach(function(cb) { cb.checked = this.checked; }.bind(this));
            allSelected = this.checked;
            updateCount();
        });

        customerList.querySelectorAll('.customer-cb').forEach(function(cb) {
            cb.addEventListener('change', function() {
                updateCount();
                const total = customerList.querySelectorAll('.customer-cb').length;
                const checked = customerList.querySelectorAll('.customer-cb:checked').length;
                document.getElementById('checkAll').checked = checked === total;
            });
        });
    }

    function updateCount() {
        const count = customerList.querySelectorAll('.customer-cb:checked').length;
        customerCount.textContent = count + ' dipilih';
    }

    odpSelect.addEventListener('change', function() {
        const customers = getSelectedOdpCustomers();
        renderCustomers(customers);
    });

    if (odpSelect.value) {
        odpSelect.dispatchEvent(new Event('change'));
    }
});
</script>
@endpush
