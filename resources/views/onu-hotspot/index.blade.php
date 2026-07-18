@extends('layouts.app')
@section('title', 'ONU Hotspot')
@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-tower-cell me-2" style="color:#2563eb;"></i>ONU Hotspot</h2>
        <p class="section-subtitle mb-0 mt-1">Daftar ONU pelanggan hotspot dari OLT</p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <form method="POST" action="{{ route('onu-hotspot.sync') }}" onsubmit="return confirm('Sync ONU dari semua OLT aktif?')">
            @csrf
            <button type="submit" class="btn btn-outline-success px-3 py-2">
                <i class="fa-solid fa-rotate me-1"></i>Sync dari OLT
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
<div class="d-flex gap-3 mb-4 flex-wrap">
    <div class="card flex-fill" style="min-width:140px;min-height:80px;border-radius:12px;background:linear-gradient(135deg,#2563eb,#1d4ed8);">
        <div class="card-body py-2 px-3" style="color:#fff;">
            <div class="stat-number" style="font-size:1.5rem;">{{ $stats['total'] }}</div>
            <div class="stat-label" style="font-size:0.7rem;">Total ONU</div>
        </div>
    </div>
    <div class="card flex-fill" style="min-width:140px;min-height:80px;border-radius:12px;background:linear-gradient(135deg,#059669,#047857);">
        <div class="card-body py-2 px-3" style="color:#fff;">
            <div class="stat-number" style="font-size:1.5rem;">{{ $stats['online'] }}</div>
            <div class="stat-label" style="font-size:0.7rem;">Online</div>
        </div>
    </div>
    <div class="card flex-fill" style="min-width:140px;min-height:80px;border-radius:12px;background:linear-gradient(135deg,#dc2626,#b91c1c);">
        <div class="card-body py-2 px-3" style="color:#fff;">
            <div class="stat-number" style="font-size:1.5rem;">{{ $stats['offline'] }}</div>
            <div class="stat-label" style="font-size:0.7rem;">Offline</div>
        </div>
    </div>
    <div class="card flex-fill" style="min-width:140px;min-height:80px;border-radius:12px;background:linear-gradient(135deg,#64748b,#475569);">
        <div class="card-body py-2 px-3" style="color:#fff;">
            <div class="stat-number" style="font-size:1.5rem;">{{ $stats['unknown'] }}</div>
            <div class="stat-label" style="font-size:0.7rem;">Unknown</div>
        </div>
    </div>
</div>
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control" placeholder="Cari nama, kode, serial, MAC..." value="{{ $search ?? '' }}">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">Semua Status</option>
                    <option value="online" {{ ($statusFilter ?? '') === 'online' ? 'selected' : '' }}>Online</option>
                    <option value="offline" {{ ($statusFilter ?? '') === 'offline' ? 'selected' : '' }}>Offline</option>
                    <option value="unknown" {{ ($statusFilter ?? '') === 'unknown' ? 'selected' : '' }}>Unknown</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-search me-1"></i>Filter</button>
            </div>
            <div class="col-md-2">
                <a href="{{ route('onu-hotspot.index') }}" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
</div>
<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <div style="width:8px;height:8px;border-radius:50%;background:#2563eb;"></div>
            <span>Daftar ONU Hotspot</span>
            <span class="badge badge-premium ms-2" style="background:#eef2ff;color:#2563eb;">{{ $onus->total() }}</span>
        </div>
        <small class="text-muted">Halaman {{ $onus->currentPage() }} dari {{ $onus->lastPage() }}</small>
    </div>
    <div class="card-body p-0">
        <div class="mon-table-wrap">
            <table class="table table-hover align-middle mb-0 mon-table">
                <thead class="mon-thead">
                    <tr>
                        <th>Pelanggan</th>
                        <th>Serial Number</th>
                        <th>Caller ID</th>
                        <th>Vendor/Model</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">OLT</th>
                        <th class="text-center">RX Power</th>
                        <th class="text-center">Terakhir Seen</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($onus as $onu)
                        <tr>
                            <td>
                                @if($onu->customer)
                                    <a href="{{ route('customer.edit', $onu->customer->customer_code) }}" class="fw-semibold text-decoration-none" style="color:#2563eb;">
                                        {{ $onu->customer->name }}
                                    </a>
                                    <div style="font-size:0.7rem;color:#64748b;">{{ $onu->customer->customer_code }}</div>
                                @else
                                    <span class="text-muted fst-italic">Belum di-link</span>
                                @endif
                            </td>
                            <td><code style="font-size:0.75rem;">{{ $onu->serial_number ?? '—' }}</code></td>
                            <td style="font-size:0.8rem;">{{ $onu->caller_id ?? '—' }}</td>
                            <td style="font-size:0.8rem;">{{ $onu->vendor ?? '' }} {{ $onu->model ?? '' }}</td>
                            <td class="text-center">
                                @if($onu->status === 'online')
                                    <span class="badge" style="background:#f0fdf4;color:#059669;"><i class="fa-solid fa-circle me-1" style="font-size:0.4rem;vertical-align:middle;"></i>Online</span>
                                @elseif($onu->status === 'offline')
                                    <span class="badge" style="background:#fef2f2;color:#dc2626;"><i class="fa-solid fa-circle me-1" style="font-size:0.4rem;vertical-align:middle;"></i>Offline</span>
                                @else
                                    <span class="badge" style="background:#f1f5f9;color:#64748b;">{{ ucfirst($onu->status) }}</span>
                                @endif
                            </td>
                            <td class="text-center" style="font-size:0.78rem;">{{ $onu->oltPort?->olt?->name ?? '—' }}</td>
                            <td class="text-center" style="font-size:0.78rem;">
                                @if($onu->rx_power)
                                    <span style="color:{{ $onu->rx_power > -27 ? '#059669' : ($onu->rx_power > -30 ? '#d97706' : '#dc2626') }};">{{ number_format($onu->rx_power, 1) }} dBm</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-center" style="font-size:0.78rem;">{{ $onu->last_seen_at?->diffForHumans() ?? '—' }}</td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <button class="btn btn-sm btn-outline-primary px-2" title="Edit" data-bs-toggle="modal" data-bs-target="#editOnu{{ $onu->id }}">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary px-2 dropdown-toggle" data-bs-toggle="dropdown" style="font-size:0.7rem;"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                        <ul class="dropdown-menu dropdown-menu-end" style="font-size:0.8rem;min-width:180px;">
                                            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#editOnu{{ $onu->id }}"><i class="fa-solid fa-pen me-2 text-primary"></i>Edit ONU</a></li>
                                            @if($onu->customer)
                                                <li>
                                                    <form method="POST" action="{{ route('onu-hotspot.unlink', $onu) }}" onsubmit="return confirm('Unlink ONU dari {{ $onu->customer->name }}?')">
                                                        @csrf
                                                        <button type="submit" class="dropdown-item"><i class="fa-solid fa-link-slash me-2 text-warning"></i>Unlink</button>
                                                    </form>
                                                </li>
                                            @else
                                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#linkModal{{ $onu->id }}"><i class="fa-solid fa-link me-2 text-success"></i>Link Pelanggan</a></li>
                                            @endif
                                        </ul>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-tower-cell" style="font-size:1.5rem;display:block;margin-bottom:8px;"></i>
                                Belum ada ONU hotspot ditemukan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($onus->hasPages())
    <div class="card-footer bg-white">
        {{ $onus->links() }}
    </div>
    @endif
</div>

{{-- MODALS dipindah ke luar .card agar tidak ter-clip/tertutup oleh stacking context card --}}
@foreach($onus as $onu)
    {{-- EDIT MODAL --}}
    <div class="modal fade" id="editOnu{{ $onu->id }}" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('onu-hotspot.update', $onu) }}">
                    @csrf @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">Edit ONU #{{ $onu->onu_id }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Serial Number</label>
                            <input type="text" name="serial_number" class="form-control" value="{{ $onu->serial_number }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Caller ID</label>
                            <input type="text" name="caller_id" class="form-control" value="{{ $onu->caller_id }}">
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Vendor</label>
                                <input type="text" name="vendor" class="form-control" value="{{ $onu->vendor }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Model</label>
                                <input type="text" name="model" class="form-control" value="{{ $onu->model }}">
                            </div>
                        </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">MAC Address</label>
                                <input type="text" name="mac_address" class="form-control" value="{{ $onu->mac_address }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">ODP Port</label>
                                <select name="odp_port_id" class="form-select">
                                    <option value="">— Belum dipilih —</option>
                                    @foreach($odps as $odp)
                                        @if($odp->ports->isNotEmpty())
                                        <optgroup label="{{ $odp->nama_odp }}">
                                            @foreach($odp->ports as $port)
                                                <option value="{{ $port->id }}" {{ $onu->odp_port_id == $port->id ? 'selected' : '' }}>
                                                    Port {{ $port->port_number }} ({{ $port->status }})
                                                </option>
                                            @endforeach
                                        </optgroup>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Notes</label>
                                <textarea name="notes" class="form-control" rows="2">{{ $onu->notes }}</textarea>
                            </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    {{-- LINK CUSTOMER MODAL --}}
    @if(! $onu->customer)
    <div class="modal fade" id="linkModal{{ $onu->id }}" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('onu-hotspot.link-customer', $onu) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">Link ke Pelanggan</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted mb-3">ONU {{ $onu->serial_number ?? '#' . $onu->onu_id }}</p>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Pelanggan Hotspot</label>
                            <select name="customer_id" class="form-select" required>
                                <option value="">Pilih pelanggan...</option>
                                @foreach(\App\Models\Customer::where('type', 'hotspot')->orderBy('name')->get() as $c)
                                    <option value="{{ $c->id }}">{{ $c->customer_code }} - {{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Link</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
@endforeach
@endsection
