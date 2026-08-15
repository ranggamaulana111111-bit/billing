@extends('layouts.app')
@section('title', ($status ?? '') === 'unpaid' ? 'Tagihan Belum Dibayar' : 'Invoice')
@section('content')
@if(($status ?? '') === 'unpaid')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-file-circle-exclamation me-2" style="color:#dc2626;"></i>Tagihan Belum Dibayar</h2>
        <p class="section-subtitle mb-0 mt-1">Daftar tagihan yang belum dibayar pelanggan</p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <form method="POST" action="{{ route('packages.mass-bill') }}" class="d-inline" onsubmit="return confirm('Buat tagihan untuk semua pelanggan aktif?')">
            @csrf
            <button type="submit" class="btn btn-outline-warning px-3 py-2">
                <i class="fa-solid fa-users-gear me-1"></i>Tagih Massal
            </button>
        </form>
        <a href="{{ route('invoices.create') }}" class="btn btn-primary px-4 py-2">
            <i class="fa-solid fa-plus me-2"></i>Buat Tagihan
        </a>
    </div>
</div>
@if($unpaidStats)
<div class="d-flex gap-3 mb-4 flex-wrap">
    <div class="card flex-fill" style="min-width:180px;min-height:90px;border-radius:12px;background:linear-gradient(135deg,#dc2626,#b91c1c);">
        <div class="card-body py-2 px-3 position-relative" style="color:#fff;">
            <div class="stat-number" style="font-size:1.5rem;">Rp {{ number_format($unpaidStats['total_amount'], 0, ',', '.') }}</div>
            <div class="stat-label" style="font-size:0.7rem;">Total Belum Dibayar</div>
        </div>
    </div>
    <div class="card flex-fill" style="min-width:160px;min-height:90px;border-radius:12px;background:linear-gradient(135deg,#f59e0b,#d97706);">
        <div class="card-body py-2 px-3 position-relative" style="color:#fff;">
            <div class="stat-number" style="font-size:1.5rem;">{{ $unpaidStats['total_count'] }}</div>
            <div class="stat-label" style="font-size:0.7rem;">Tagihan Terutang</div>
        </div>
    </div>
    <div class="card flex-fill" style="min-width:160px;min-height:90px;border-radius:12px;background:linear-gradient(135deg,#2563eb,#1d4ed8);">
        <div class="card-body py-2 px-3 position-relative" style="color:#fff;">
            <div class="stat-number" style="font-size:1.5rem;">{{ $unpaidStats['total_customers'] }}</div>
            <div class="stat-label" style="font-size:0.7rem;">Pelanggan Belum Bayar</div>
        </div>
    </div>
    @if($unpaidStats['oldest_days'] !== null)
    <div class="card flex-fill" style="min-width:160px;min-height:90px;border-radius:12px;background:linear-gradient(135deg,#1e293b,#0f172a);">
        <div class="card-body py-2 px-3 position-relative" style="color:#fff;">
            <div class="stat-number" style="font-size:1.5rem;">{{ $unpaidStats['oldest_days'] }} hari</div>
            <div class="stat-label" style="font-size:0.7rem;">Tagihan Tertua</div>
        </div>
    </div>
    @endif
</div>
@endif
@else
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-file-invoice me-2" style="color:var(--primary);"></i>Invoice</h2>
        <p class="section-subtitle mb-0 mt-1">Kelola semua invoice pelanggan</p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <button type="button" class="btn btn-outline-info px-4 py-2" data-bs-toggle="modal" data-bs-target="#printCustomerModal">
            <i class="fa-solid fa-id-card me-2"></i>Cetak Form Pelanggan
        </button>
        <a href="{{ route('invoices.create') }}" class="btn btn-primary px-4 py-2">
            <i class="fa-solid fa-plus me-2"></i>Buat Tagihan
        </a>
    </div>
</div>
@endif
@if(session('success'))
    <div class="alert alert-custom alert-success mb-4">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-custom alert-danger mb-4">{{ session('error') }}</div>
@endif
{{-- FILTERS --}}
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold" style="font-size:0.8rem;">Cari</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Invoice / Pelanggan..."
                       value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.8rem;">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="unpaid" {{ request('status') === 'unpaid' ? 'selected' : '' }}>Belum Dibayar</option>
                    <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>Lunas</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.8rem;">Dari</label>
                <input type="date" name="from" class="form-control form-control-sm" value="{{ request('from') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.8rem;">Sampai</label>
                <input type="date" name="to" class="form-control form-control-sm" value="{{ request('to') }}">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm px-4">
                    <i class="fa-solid fa-filter me-1"></i>Filter
                </button>
                <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary btn-sm px-3">
                    <i class="fa-solid fa-rotate me-1"></i>Reset
                </a>
            </div>
        </form>
    </div>
</div>
{{-- TABLE --}}
<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <div style="width:8px;height:8px;border-radius:50%;background:{{ ($status ?? '') === 'unpaid' ? '#dc2626' : 'var(--primary)' }};"></div>
            <span>{{ ($status ?? '') === 'unpaid' ? 'Tagihan Terutang' : 'Daftar Invoice' }}</span>
            <span class="badge {{ ($status ?? '') === 'unpaid' ? 'badge-soft-red' : 'badge-soft-primary' }} ms-2">{{ $invoices->total() }}</span>
        </div>
        <small class="text-muted">Halaman {{ $invoices->currentPage() }} dari {{ $invoices->lastPage() }}</small>
    </div>
    <div class="card-body p-0">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
                    <tr>
                        <th>Invoice</th>
                        <th>Pelanggan</th>
                        <th>Paket</th>
                        <th>Periode</th>
                        <th class="text-end">Total</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>

                <tbody>
                    @forelse($invoices as $inv)
                        <tr>
                            <td>
                                <span class="badge badge-soft-primary" style="font-size:0.72rem;">
                                    {{ $inv->invoice_display }}
                                </span>
                            </td>
                            <td class="fw-medium">
                                {{ $inv->customer->name ?? '-' }}
                                @if($inv->customer && !empty($customerPaidMonths[$inv->customer_id]))
                                    <div style="font-size:0.7rem;color:#64748b;margin-top:2px;">
                                        @foreach($customerPaidMonths[$inv->customer_id] as $ym)
                                            @php $monthName = \Carbon\Carbon::createFromFormat('Y-m', $ym)->format('M'); @endphp
                                            <span title="Lunas {{ \Carbon\Carbon::createFromFormat('Y-m', $ym)->format('F Y') }}" style="display:inline-block;background:#f0fdf4;color:#059669;padding:0 6px;border-radius:4px;margin-right:2px;">{{ $monthName }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td>{{ $inv->customer->package->name ?? '-' }}</td>
                            <td style="font-size:0.8rem;">
                                @if($inv->billing_period)
                                    {{ \Carbon\Carbon::createFromFormat('Y-m', $inv->billing_period)->format('M Y') }}
                                @else
                                    {{ $inv->created_at->format('M Y') }}
                                @endif
                            </td>
                            <td class="fw-bold text-end">Rp{{ number_format($inv->amount, 0, ',', '.') }}</td>
                            <td class="text-center">
                                @if($inv->payment_status === 'paid')
                                    <span class="badge badge-soft-green">
                                        <i class="fa-regular fa-circle-check me-1"></i>Lunas
                                    </span>
                                @else
                                    <span class="badge badge-soft-red">
                                        <i class="fa-regular fa-clock me-1"></i>Belum
                                    </span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <a href="{{ route('invoice.print', $inv->id) }}" class="btn btn-sm btn-outline-secondary px-2" title="Cetak A4" target="_blank">
                                        <i class="fa-solid fa-print"></i>
                                    </a>
                                    <a href="{{ route('invoice.print-thermal', $inv->id) }}" class="btn btn-sm btn-outline-dark px-2" title="Cetak Thermal" target="_blank">
                                        <i class="fa-solid fa-receipt"></i>
                                    </a>
                                    @if($inv->payment_status === 'unpaid')
                                        <a href="{{ route('payment.create', $inv->id) }}" class="btn btn-sm btn-info text-white px-2" title="Bayar">
                                            <i class="fa-solid fa-money-bill-wave"></i>
                                        </a>
                                    @endif
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary px-2 dropdown-toggle" data-bs-toggle="dropdown" title="Lainnya" style="font-size:0.7rem;">
                                            <i class="fa-solid fa-ellipsis-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end" style="font-size:0.8rem;min-width:180px;">
                                            <li><a class="dropdown-item" href="{{ route('invoice.pdf', $inv->id) }}"><i class="fa-solid fa-file-pdf me-2 text-danger"></i>Download PDF</a></li>
                                            @if($inv->payment_status === 'unpaid')
                                                <li><a class="dropdown-item" href="{{ route('invoice.paid', $inv->id) }}" onclick="return confirm('Tandai lunas?')"><i class="fa-solid fa-check me-2 text-success"></i>Tandai Lunas</a></li>
                                                @if(\App\Models\Setting::get('midtrans_server_key'))
                                                    <li><a class="dropdown-item" href="{{ route('midtrans.pay', $inv->id) }}"><i class="fa-solid fa-credit-card me-2 text-warning"></i>Bayar via Midtrans</a></li>
                                                @endif
                                                @if(\App\Models\Setting::get('xendit_secret_key'))
                                                    <li><a class="dropdown-item" href="{{ route('xendit.pay', $inv->id) }}"><i class="fa-solid fa-wallet me-2" style="color:#6366f1;"></i>Bayar via Xendit</a></li>
                                                @endif
                                                <li><a class="dropdown-item" href="{{ route('invoice.edit', $inv->id) }}"><i class="fa-solid fa-pen me-2 text-primary"></i>Edit</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                @if($inv->customer?->email)
                                                    <li><a class="dropdown-item" href="{{ route('invoice.email-reminder', $inv->id) }}" onclick="return confirm('Kirim email reminder?')"><i class="fa-solid fa-envelope me-2 text-primary"></i>Email Reminder</a></li>
                                                @endif
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form method="POST" action="{{ route('invoice.destroy', $inv->id) }}" onsubmit="return confirm('Hapus tagihan {{ $inv->invoice_display }}?')">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" class="dropdown-item text-danger"><i class="fa-solid fa-trash me-2"></i>Hapus</button>
                                                    </form>
                                                </li>
                                            @else
                                                <li><a class="dropdown-item" href="{{ route('payment.history', $inv->id) }}"><i class="fa-solid fa-clock-rotate-left me-2 text-info"></i>Riwayat Pembayaran</a></li>
                                                @if($inv->customer?->email)
                                                    <li><a class="dropdown-item" href="{{ route('invoice.email-payment', $inv->id) }}" onclick="return confirm('Kirim email konfirmasi?')"><i class="fa-solid fa-envelope me-2 text-primary"></i>Email Konfirmasi</a></li>
                                                @endif
                                            @endif
                                        </ul>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fa-regular fa-file-lines" style="font-size:1.5rem;display:block;margin-bottom:8px;"></i>
                                Belum ada tagihan
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($invoices->hasPages())
        <div class="card-footer bg-white d-flex justify-content-center">
            {{ $invoices->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
{{-- MODAL CETAK FORM PELANGGAN --}}
<div class="modal fade" id="printCustomerModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold"><i class="fa-solid fa-id-card me-2" style="color:var(--primary);"></i>Cetak Form Pelanggan</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="printCustomerBody">
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Cari Pelanggan</label>
                    <input type="text" id="customerSearchInput" class="form-control" placeholder="Ketik nama atau nomor telepon..." autofocus>
                </div>
                <div id="customerSearchLoading" class="text-center py-3 d-none">
                    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                    <small class="text-muted ms-2">Mencari...</small>
                </div>
                <div id="customerSearchResults" class="list-group d-none" style="max-height:250px;overflow-y:auto;"></div>
                <div id="customerSearchEmpty" class="text-center py-3 text-muted d-none">
                    <i class="fa-regular fa-face-frown me-1"></i>Pelanggan tidak ditemukan
                </div>
                <div id="customerSearchIdle" class="text-center py-3 text-muted">
                    <small>Ketik nama atau nomor telepon untuk mencari</small>
                </div>
                <div id="customerSearchSelected" class="text-center py-3 d-none">
                    <div class="fw-semibold mb-2" id="selectedCustomerName"></div>
                    <div class="d-flex gap-2 justify-content-center flex-wrap">
                        <a href="#" id="btnThermal" target="_blank" class="btn btn-dark px-4 py-2">
                            <i class="fa-solid fa-receipt me-1"></i> Thermal 58mm
                        </a>
                        <a href="#" id="btnA4" target="_blank" class="btn btn-outline-primary px-4 py-2">
                            <i class="fa-solid fa-print me-1"></i> Print A4
                        </a>
                        <a href="#" id="btnPdf" target="_blank" class="btn btn-outline-danger px-4 py-2">
                            <i class="fa-solid fa-file-pdf me-1"></i> Download PDF
                        </a>
                    </div>
                    <button class="btn btn-sm btn-link text-muted mt-2" id="btnBack">
                        <i class="fa-solid fa-arrow-left me-1"></i> Pilih pelanggan lain
                    </button>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light btn-sm px-4" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@push('scripts')
<script>
(function() {
    const input = document.getElementById('customerSearchInput');
    const loading = document.getElementById('customerSearchLoading');
    const results = document.getElementById('customerSearchResults');
    const empty = document.getElementById('customerSearchEmpty');
    const idle = document.getElementById('customerSearchIdle');
    const selected = document.getElementById('customerSearchSelected');
    const selectedName = document.getElementById('selectedCustomerName');
    const btnThermal = document.getElementById('btnThermal');
    const btnA4 = document.getElementById('btnA4');
    const btnPdf = document.getElementById('btnPdf');
    const btnBack = document.getElementById('btnBack');
    let searchTimeout = null;
    let selectedCustomer = null;
    function show(el) { el.classList.remove('d-none'); }
    function hide(el) { el.classList.add('d-none'); }
    function resetView() {
        hide(loading);
        hide(results);
        hide(empty);
        hide(selected);
        show(idle);
    }
    function showSelected(customer) {
        selectedCustomer = customer;
        hide(loading);
        hide(results);
        hide(empty);
        hide(idle);
        selectedName.textContent = 'Pilih cetakan untuk: ' + customer.name;
        const base = '/customer/' + customer.id;
        btnThermal.href = base + '/print-thermal';
        btnA4.href = base + '/print-a4';
        btnPdf.href = base + '/pdf';
        show(selected);
    }
    function doSearch(query) {
        if (query.length < 1) {
            hide(loading);
            hide(results);
            hide(empty);
            hide(selected);
            show(idle);
            return;
        }
        show(loading);
        hide(results);
        hide(empty);
        hide(selected);
        hide(idle);
        fetch('{{ route("api.customers.search") }}?q=' + encodeURIComponent(query))
            .then(r => r.json())
            .then(data => {
                hide(loading);
                if (data.length > 0) {
                    results.innerHTML = '';
                    data.forEach(c => {
                        const a = document.createElement('a');
                        a.href = '#';
                        a.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
                        a.innerHTML = '<div><div class="fw-semibold">' + c.name + '</div><small class="text-muted">' + (c.phone || '-') + '</small></div><i class="fa-solid fa-chevron-right text-muted" style="font-size:0.7rem;"></i>';
                        a.addEventListener('click', function(e) {
                            e.preventDefault();
                            showSelected(c);
                            input.value = '';
                            selectedCustomer = c;
                        });
                        results.appendChild(a);
                    });
                    show(results);
                } else {
                    show(empty);
                }
            })
            .catch(() => {
                hide(loading);
                show(empty);
            });
    }
    input.addEventListener('input', function() {
        if (searchTimeout) clearTimeout(searchTimeout);
        const q = this.value.trim();
        searchTimeout = setTimeout(function() { doSearch(q); }, 300);
    });
    btnBack.addEventListener('click', function() {
        selectedCustomer = null;
        input.value = '';
        input.focus();
        resetView();
    });
    document.getElementById('printCustomerModal').addEventListener('hidden.bs.modal', function() {
        selectedCustomer = null;
        input.value = '';
        resetView();
    });
    resetView();
})();
</script>
@endpush
@endsection
