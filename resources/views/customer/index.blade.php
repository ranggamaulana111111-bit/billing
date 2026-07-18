@extends('layouts.app')
@section('title', 'Pelanggan')
@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-users me-2" style="color:var(--primary);"></i>Pelanggan</h2>
        <p class="section-subtitle mb-0 mt-1">Daftar semua pelanggan — kelola data, status, koneksi ONU & PPPoE</p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <a href="{{ route('customer.create') }}" class="btn btn-primary px-4 py-2">
            <i class="fa-solid fa-user-plus me-2"></i>Pasang Baru
        </a>
        <form action="{{ route('customers.sync-pppoe') }}" method="POST" class="d-inline" onsubmit="return confirm('Sync PPPoE semua pelanggan aktif ke MikroTik?')">
            @csrf
            <button type="submit" class="btn btn-outline-info px-4 py-2">
                <i class="fa-solid fa-network-wired me-2"></i>Sync PPPoE
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
{{-- STATS --}}
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card shadow-sm border-0 p-3 text-center">
            <div class="stat-number" style="color:var(--primary);">{{ $stats['total'] }}</div>
            <small class="text-muted">Total Pelanggan</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm border-0 p-3 text-center">
            <div class="stat-number" style="color:#059669;">{{ $stats['active'] }}</div>
            <small class="text-muted"><i class="fa-regular fa-circle-check me-1"></i>Aktif</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm border-0 p-3 text-center">
            <div class="stat-number" style="color:#d97706;">{{ $stats['suspended'] }}</div>
            <small class="text-muted"><i class="fa-solid fa-pause me-1"></i>Ditangguhkan</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm border-0 p-3 text-center">
            <div class="stat-number" style="color:#dc2626;">{{ $stats['inactive'] }}</div>
            <small class="text-muted"><i class="fa-solid fa-ban me-1"></i>Nonaktif</small>
        </div>
    </div>
</div>
{{-- TABLE --}}
<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <div style="width:8px;height:8px;border-radius:50%;background:var(--primary);"></div>
            <span>Daftar Client</span>
            <span class="badge badge-premium ms-2" style="background:#eef2ff;color:var(--primary);">{{ $customers->total() }}</span>
        </div>
        @if($totalOlts > 0)
            <form action="{{ route('olt.sync-all-onu') }}" method="POST" class="d-inline" onsubmit="return confirm('Polling OLT untuk sync status semua ONU?')">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-success">
                    <i class="fa-solid fa-rotate me-1"></i>Sync Semua ONU
                </button>
            </form>
        @endif
    </div>
    <div class="card-body p-0">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
                    <thead class="mon-thead-gradient">
                    <tr>
                        <th>Client</th>
                        <th>KTP</th>
                        <th>Paket / ODP</th>
                        <th>Type Client</th>
                        <th>ONU / OLT</th>
                        <th>Status Akun</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                    </thead>

                <tbody>
                    @forelse($customers as $c)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg, var(--primary), var(--accent));color:#fff;display:flex;align-items:center;justify-content:center;font-size:0.8rem;font-weight:700;flex-shrink:0;">
                                        {{ strtoupper(substr($c->name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="fw-semibold" style="font-size:0.85rem;">{{ $c->name }}</div>
                                        <small style="font-size:0.7rem;color:var(--primary);font-weight:600;">{{ $c->customer_code }}</small>
                                        <small class="text-muted ms-1">{{ $c->phone ?? '-' }}</small>
                                    </div>
                                </div>
                            </td>
                            <td class="text-center">
                                @if($c->ktp_photo)
                                    <a href="{{ Storage::url($c->ktp_photo) }}" target="_blank" title="Lihat KTP">
                                        <img src="{{ Storage::url($c->ktp_photo) }}" alt="KTP"
                                             style="width:40px;height:40px;object-fit:cover;border-radius:4px;border:1px solid #e2e8f0;cursor:pointer;">
                                    </a>
                                @else
                                    <span class="text-muted" style="font-size:0.75rem;">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-premium" style="background:#f1f5f9;color:#475569;">{{ $c->package->name ?? '-' }}</span>
                                @if($c->odp)
                                    <br><small class="text-muted">{{ $c->odp->nama_odp }} @if($c->odpPort) · Port {{ $c->odpPort->port_number }}@endif</small>
                                @endif
                            </td>
                            <td>
                                <div>
                                    @if($c->type === 'hotspot')
                                        <span class="badge badge-premium" style="background:#fef3c7;color:#d97706;font-size:0.65rem;">Hotspot</span>
                                    @else
                                        <span class="badge badge-premium" style="background:#eef2ff;color:var(--primary);font-size:0.65rem;">PPPoE</span>
                                    @endif
                                </div>
                                @if($c->pppoe_username)
                                    <code style="font-size:0.72rem;">{{ $c->pppoe_username }}</code>
                                @endif
                            </td>
                            <td>
                                @php $onu = $c->onus->first(); @endphp
                                @if($onu && $onu->oltPort?->olt)
                                    <div style="font-size:0.8rem;">
                                        <div><i class="fa-solid fa-tower-broadcast me-1" style="color:var(--primary);"></i>{{ $onu->oltPort->olt->name }}</div>
                                        <div class="text-muted">Port {{ $onu->slot_number }}/{{ $onu->port_number }}</div>
                                        <div>
                                            @if($onu->status === 'online')
                                                <span class="badge badge-premium" style="background:#f0fdf4;color:#059669;font-size:0.65rem;">
                                                    <i class="fa-regular fa-circle-check"></i> online
                                                </span>
                                            @else
                                                <span class="badge badge-premium" style="background:#fef2f2;color:#dc2626;font-size:0.65rem;">
                                                    <i class="fa-solid fa-circle"></i> offline
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                @else
                                    <span class="text-muted" style="font-size:0.8rem;">Belum sync</span>
                                @endif
                            </td>
                            <td>
                                @if($c->status === 'active')
                                    <span class="badge badge-premium" style="background:#f0fdf4;color:#059669;">
                                        <i class="fa-regular fa-circle-check me-1"></i>Aktif
                                    </span>
                                @elseif($c->status === 'suspended')
                                    <span class="badge badge-premium" style="background:#fef3c7;color:#d97706;">
                                        <i class="fa-solid fa-pause me-1"></i>Ditangguhkan
                                    </span>
                                @else
                                    <span class="badge badge-premium" style="background:#fef2f2;color:#dc2626;">
                                        <i class="fa-solid fa-ban me-1"></i>Nonaktif
                                    </span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <a href="{{ route('customer.edit', $c->customer_code) }}" class="btn btn-sm btn-outline-primary px-2" title="Edit">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>
                                    @if($totalOlts > 0)
                                        <form method="POST" action="{{ route('customer.sync-single-onu', $c->customer_code) }}" class="d-inline" title="Sync ONU">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-success px-2">
                                                <i class="fa-solid fa-rotate"></i>
                                            </button>
                                        </form>
                                    @endif
                                    <div class="dropdown" style="position:static;">
                                        <button class="btn btn-sm btn-outline-secondary px-2 dropdown-toggle" data-bs-toggle="dropdown" data-bs-boundary="viewport" title="Lainnya" style="font-size:0.7rem;">
                                            <i class="fa-solid fa-ellipsis-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end" style="font-size:0.8rem;min-width:160px;">
                                            <li><a class="dropdown-item" href="{{ route('customer.edit', $c->customer_code) }}"><i class="fa-solid fa-pen me-2 text-primary"></i>Edit Pelanggan</a></li>
                                            @if($totalOlts > 0)
                                                <li>
                                                    <form method="POST" action="{{ route('customer.sync-single-onu', $c->customer_code) }}">
                                                        @csrf
                                                        <button type="submit" class="dropdown-item"><i class="fa-solid fa-rotate me-2 text-success"></i>Sync ONU</button>
                                                    </form>
                                                </li>
                                            @endif
                                            <li><hr class="dropdown-divider"></li>
                                            @if($c->status === 'active')
                                                <li>
                                                    <form method="POST" action="{{ route('customer.suspend', $c->customer_code) }}" onsubmit="return confirm('Isolir {{ $c->name }}?')">
                                                        @csrf
                                                        <button type="submit" class="dropdown-item"><i class="fa-solid fa-pause me-2 text-warning"></i>Isolir</button>
                                                    </form>
                                                </li>
                                            @else
                                                <li>
                                                    <form method="POST" action="{{ route('customer.activate', $c->customer_code) }}">
                                                        @csrf
                                                        <button type="submit" class="dropdown-item"><i class="fa-solid fa-play me-2 text-success"></i>Aktifkan</button>
                                                    </form>
                                                </li>
                                            @endif
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="POST" action="{{ route('customer.destroy', $c->customer_code) }}" onsubmit="return confirm('Hapus {{ $c->name }}? Semua data tagihan ikut terhapus!')">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger"><i class="fa-solid fa-trash me-2"></i>Hapus</button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fa-regular fa-users" style="font-size:1.5rem;display:block;margin-bottom:8px;"></i>
                                Belum ada pelanggan
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($customers->hasPages())
        <div class="card-footer bg-white d-flex justify-content-center">
            {{ $customers->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection
