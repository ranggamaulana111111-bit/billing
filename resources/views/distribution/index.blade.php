@extends('layouts.app')

@section('title', 'Distribusi ODP')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-map-location-dot me-2" style="color:var(--primary);"></i>Distribusi ODP</h2>
        <p class="section-subtitle mb-0 mt-1">Visualisasi sebaran titik ODP dan penggunaan port</p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary px-3 py-2" data-bs-toggle="modal" data-bs-target="#createOdcModal">
            <i class="fa-solid fa-server me-1"></i>Tambah ODC
        </button>
        <button type="button" class="btn btn-outline-primary px-3 py-2" data-bs-toggle="modal" data-bs-target="#createRouteModal">
            <i class="fa-solid fa-route me-1"></i>Tambah Route
        </button>
        <button type="button" class="btn btn-primary px-3 py-2" data-bs-toggle="modal" data-bs-target="#createOdpModal">
            <i class="fa-solid fa-location-dot me-1"></i>Tambah ODP
        </button>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-custom alert-success mb-4">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-custom alert-danger mb-4">{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-custom alert-danger mb-4">{{ $errors->first() }}</div>
@endif

{{-- STATS --}}
<div class="row g-4 mb-4">
    <div class="col-md-3 fade-in" style="animation-delay:0.05s">
        <div class="card stat-card stat-card-gradient-blue text-white">
            <div class="stat-bg"><i class="fa-solid fa-tower-cell"></i></div>
            <div class="card-body position-relative">
                <div class="stat-number">{{ $totalOdps }}</div>
                <div class="stat-label">Total ODP</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 fade-in" style="animation-delay:0.1s">
        <div class="card stat-card stat-card-gradient-green text-white">
            <div class="stat-bg"><i class="fa-solid fa-plug"></i></div>
            <div class="card-body position-relative">
                <div class="stat-number">{{ $usedPorts }}</div>
                <div class="stat-label">Port Terpakai</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 fade-in" style="animation-delay:0.15s">
        <div class="card" style="background:linear-gradient(135deg,#f59e0b,#d97706);min-height:130px;border-radius:16px;overflow:hidden;">
            <div class="stat-bg" style="position:absolute;top:-30px;right:-30px;font-size:7rem;opacity:0.08;color:#fff;"><i class="fa-solid fa-circle"></i></div>
            <div class="card-body position-relative" style="color:#fff;">
                <div class="stat-number">{{ $availablePorts }}</div>
                <div class="stat-label">Port Tersedia</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 fade-in" style="animation-delay:0.2s">
        <div class="card stat-card stat-card-gradient-red text-white">
            <div class="stat-bg"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div class="card-body position-relative">
                <div class="stat-number">{{ $fullOdps }}</div>
                <div class="stat-label">ODP Penuh</div>
            </div>
        </div>
    </div>
    @isset($downOdps)
    <div class="col-md-3 fade-in" style="animation-delay:0.25s">
        <div class="card" style="background:linear-gradient(135deg,#1e293b,#0f172a);min-height:130px;border-radius:16px;overflow:hidden;">
            <div class="stat-bg" style="position:absolute;top:-30px;right:-30px;font-size:7rem;opacity:0.08;color:#fff;"><i class="fa-solid fa-road"></i></div>
            <div class="card-body position-relative" style="color:#fff;">
                <div class="stat-number">{{ $downOdps }}</div>
                <div class="stat-label">Jalur Putus</div>
            </div>
        </div>
    </div>
    @endisset
</div>

{{-- CHARTS ROW --}}
<div class="row g-4 mb-4">
    <div class="col-lg-8 fade-in" style="animation-delay:0.25s">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white d-flex align-items-center gap-2">
                <div style="width:8px;height:8px;border-radius:50%;background:var(--primary);"></div>
                <span>Penggunaan Port per ODP</span>
            </div>
            <div class="card-body">
                <canvas id="odpChart" height="280"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4 fade-in" style="animation-delay:0.3s">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white d-flex align-items-center gap-2">
                <div style="width:8px;height:8px;border-radius:50%;background:#059669;"></div>
                <span>Status Port</span>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <canvas id="portChart" height="260" style="max-height:260px;"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- MAP --}}
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <div style="width:8px;height:8px;border-radius:50%;background:var(--primary);"></div>
            <span>Peta Sebaran ODC & ODP</span>
            <span class="badge badge-premium ms-2" style="background:#eef2ff;color:var(--primary);">{{ $totalOdps }} titik</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge" style="background:#ecfdf5;color:#059669;font-weight:600;">
                <span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#059669;margin-right:4px;"></span>Online
            </span>
        </div>
    </div>
    <div class="card-body p-0">
        <div id="map" style="height:460px;width:100%;border-radius:0 0 16px 16px;"></div>
    </div>
</div>

{{-- ODC TABLE --}}
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white">
        <div class="d-flex align-items-center gap-2">
            <div style="width:8px;height:8px;border-radius:50%;background:#0f172a;"></div>
            <span>ODC Induk</span>
            <span class="badge badge-premium ms-2" style="background:#f1f5f9;color:#0f172a;">{{ $odcs->count() }}</span>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>ODC</th>
                        <th>Koordinat</th>
                        <th class="text-center">Kapasitas</th>
                        <th class="text-center">ODP</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($odcs as $odc)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $odc->nama_odc }}</div>
                            </td>
                            <td class="text-muted small">{{ $odc->koordinat ?? '-' }}</td>
                            <td class="text-center">{{ $odc->kapasitas_port }}</td>
                            <td class="text-center">{{ $odc->odps_count ?? $odc->odps->count() }}</td>
                            <td class="text-center">
                                <a href="{{ route('odc.show', $odc) }}" class="btn btn-sm btn-outline-info px-2" title="Detail ODC">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-primary px-2" data-bs-toggle="modal" data-bs-target="#editOdcModal{{ $odc->id }}" title="Edit ODC">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <form method="POST" action="{{ route('distribution.odcs.destroy', $odc) }}" class="d-inline" onsubmit="return confirm('Hapus ODC {{ $odc->nama_odc }}?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger px-2" title="Hapus ODC">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-4 text-muted">Belum ada ODC induk</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ROUTES TABLE --}}
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white">
        <div class="d-flex align-items-center gap-2">
            <div style="width:8px;height:8px;border-radius:50%;background:var(--primary);"></div>
            <span>Route Distribusi</span>
            <span class="badge badge-premium ms-2" style="background:#eef2ff;color:var(--primary);">{{ $routes->count() }}</span>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Route</th>
                        <th>ODC</th>
                        <th>Deskripsi</th>
                        <th class="text-center">Warna</th>
                        <th class="text-center">Titik</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($routes as $route)
                        <tr>
                            <td class="fw-semibold">{{ $route->name }}</td>
                            <td>{{ $route->odc->name ?? '-' }}</td>
                            <td class="text-muted small">{{ $route->description ?? '-' }}</td>
                            <td class="text-center">
                                <span style="display:inline-block;width:18px;height:18px;border-radius:50%;background:{{ $route->color }};border:2px solid #fff;box-shadow:0 0 0 1px #e2e8f0;"></span>
                            </td>
                            <td class="text-center">{{ $route->points->count() }}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-primary px-2" data-bs-toggle="modal" data-bs-target="#editRouteModal{{ $route->id }}" title="Edit route">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <form method="POST" action="{{ route('distribution.routes.destroy', $route) }}" class="d-inline" onsubmit="return confirm('Hapus route {{ $route->name }}?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger px-2" title="Hapus route">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-4 text-muted">Belum ada route distribusi</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- TABLE --}}
<div class="card shadow-sm border-0">
    <div class="card-header bg-white">
        <div class="d-flex align-items-center gap-2">
            <div style="width:8px;height:8px;border-radius:50%;background:#64748b;"></div>
            <span>Detail Titik ODP</span>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>ODP</th>
                        <th>ODC</th>
                        <th>Tube/Core</th>
                        <th class="text-center">Port</th>
                        <th class="text-end">Utilisasi</th>
                        <th class="text-center">Jalur</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($newOdps as $o)
                        @php
                            $used = $o->usedPortsCount();
                            $available = $o->availablePortsCount();
                            $cap = $o->kapasitas_port;
                            $pct = $cap > 0 ? round(($used / $cap) * 100) : 0;
                            $barColor = $pct >= 80 ? '#dc2626' : ($pct >= 50 ? '#d97706' : '#059669');
                        @endphp
                        <tr>
                            <td class="fw-semibold">{{ $o->nama_odp }}</td>
                            <td>{{ $o->odc->nama_odc ?? '-' }}</td>
                            <td>
                                <span class="badge bg-secondary">{{ $o->kabel_tube_color }}</span>
                                <span class="badge bg-dark">{{ $o->kabel_core_number }}</span>
                            </td>
                            <td class="text-center">
                                <span class="fw-bold">{{ $used }}</span>
                                <span class="text-muted">/{{ $cap }}</span>
                                @if($available > 0)
                                    <br><small class="text-success" style="font-size:0.65rem;">Sisa {{ $available }}</small>
                                @else
                                    <br><small class="text-muted" style="font-size:0.65rem;">Penuh</small>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="d-flex align-items-center justify-content-end gap-2">
                                    <small style="font-weight:600;color:{{ $barColor }};">{{ $pct }}%</small>
                                    <div class="progress-mini" style="width:70px;">
                                        <div class="progress-bar-fill" style="width:{{ $pct }}%;background:{{ $barColor }};"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-premium" style="background:{{ $o->kondisi_jalur === 'UP' ? '#f0fdf4' : '#fef2f2' }};color:{{ $o->kondisi_jalur === 'UP' ? '#059669' : '#dc2626' }};">
                                    {{ $o->kondisi_jalur === 'UP' ? 'NORMAL' : 'PUTUS' }}
                                </span>
                            </td>
                            <td class="text-center">
                                <a href="{{ route('odp.show', $o) }}" class="btn btn-sm btn-outline-info px-2" title="Detail ODP">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-4 text-muted">Belum ada titik ODP</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- CREATE ODC MODAL --}}
<div class="modal fade" id="createOdcModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('distribution.odcs.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Tambah ODC</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama ODC</label>
                        <input type="text" name="nama_odc" class="form-control" placeholder="contoh: ODC Kumpay Utama" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Koordinat</label>
                        <input type="text" name="koordinat" class="form-control" placeholder="-6.4760000,106.0140000">
                        <small class="text-muted">Format: lat,lng (opsional)</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Kapasitas Port</label>
                        <select name="kapasitas_port" class="form-select" required>
                            <option value="">Pilih kapasitas</option>
                            <option value="4">4 Port</option>
                            <option value="8">8 Port</option>
                            <option value="16">16 Port</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan ODC</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- CREATE ROUTE MODAL --}}
<div class="modal fade" id="createRouteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('distribution.routes.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Route ODP</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">ODC Induk</label>
                        <select name="odc_id" class="form-select">
                            <option value="">Tanpa ODC</option>
                            @foreach($odcs as $odc)
                                <option value="{{ $odc->id }}">{{ $odc->nama_odc }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Route</label>
                        <input type="text" name="name" class="form-control" placeholder="contoh: Jalur Barat" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Deskripsi</label>
                        <input type="text" name="description" class="form-control" placeholder="Area atau catatan route">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Warna</label>
                        <input type="color" name="color" class="form-control form-control-color" value="#2563eb" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Koordinat Route JSON</label>
                        <textarea name="coordinates" class="form-control" rows="3" placeholder='[[[-6.476,106.014],[-6.477,106.015]]]'></textarea>
                        <small class="text-muted">Opsional. Kosongkan jika belum ada jalur polyline.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Route</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- CREATE ODP MODAL (new fiber model) --}}
<div class="modal fade" id="createOdpModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('distribution.odps.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Tambah ODP</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">ODC Induk</label>
                        <select name="odc_id" class="form-select">
                            <option value="">Tanpa ODC</option>
                            @foreach($odcs as $odc)
                                <option value="{{ $odc->id }}">{{ $odc->nama_odc }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama ODP</label>
                        <input type="text" name="nama_odp" class="form-control" placeholder="contoh: ODP-001" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Koordinat</label>
                        <input type="text" name="koordinat" class="form-control" placeholder="-6.4760000,106.0140000">
                        <small class="text-muted">Format: lat,lng (opsional)</small>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Kapasitas Port</label>
                            <select name="kapasitas_port" class="form-select" required>
                                <option value="8">8 Port</option>
                                <option value="16">16 Port</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Warna Tube</label>
                            <select name="kabel_tube_color" class="form-select" required>
                                <option value="Biru">Biru</option>
                                <option value="Jingga">Jingga</option>
                                <option value="Hijau">Hijau</option>
                                <option value="Cokelat">Cokelat</option>
                                <option value="Abu-abu">Abu-abu</option>
                                <option value="Putih">Putih</option>
                                <option value="Merah">Merah</option>
                                <option value="Hitam">Hitam</option>
                                <option value="Kuning">Kuning</option>
                                <option value="Ungu">Ungu</option>
                                <option value="Pink">Pink</option>
                                <option value="Toska">Toska</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nomor Core</label>
                        <input type="number" name="kabel_core_number" class="form-control" value="1" min="1" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan ODP</button>
                </div>
            </form>
        </div>
    </div>
</div>

@foreach($odcs as $odc)
<div class="modal fade" id="editOdcModal{{ $odc->id }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('distribution.odcs.update', $odc) }}">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit ODC</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama ODC</label>
                        <input type="text" name="nama_odc" class="form-control" value="{{ $odc->nama_odc }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Koordinat</label>
                        <input type="text" name="koordinat" class="form-control" value="{{ $odc->koordinat }}" placeholder="-6.4760000,106.0140000">
                        <small class="text-muted">Format: lat,lng (opsional)</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kapasitas Port</label>
                        <select name="kapasitas_port" class="form-select" required>
                            <option value="4" {{ $odc->kapasitas_port === 4 ? 'selected' : '' }}>4 Port</option>
                            <option value="8" {{ $odc->kapasitas_port === 8 ? 'selected' : '' }}>8 Port</option>
                            <option value="16" {{ $odc->kapasitas_port === 16 ? 'selected' : '' }}>16 Port</option>
                        </select>
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
@endforeach

@foreach($routes as $route)
<div class="modal fade" id="editRouteModal{{ $route->id }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('distribution.routes.update', $route) }}">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit Route ODP</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">ODC Induk</label>
                        <select name="odc_id" class="form-select">
                            <option value="">Tanpa ODC</option>
                            @foreach($odcs as $odc)
                                <option value="{{ $odc->id }}" {{ $route->odc_id === $odc->id ? 'selected' : '' }}>{{ $odc->nama_odc }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Route</label>
                        <input type="text" name="name" class="form-control" value="{{ $route->name }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi</label>
                        <input type="text" name="description" class="form-control" value="{{ $route->description }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Warna</label>
                        <input type="color" name="color" class="form-control form-control-color" value="{{ $route->color }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Koordinat Route JSON</label>
                        <textarea name="coordinates" class="form-control" rows="3">{{ json_encode($route->coordinates) }}</textarea>
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
@endforeach

@foreach($odps as $o)
<div class="modal fade" id="editPointModal{{ $o->id }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('distribution.points.update', $o) }}">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit Titik ODP</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Route</label>
                        <select name="odp_route_id" class="form-select" required>
                            @foreach($routes as $route)
                                <option value="{{ $route->id }}" {{ $o->odp_route_id === $route->id ? 'selected' : '' }}>{{ $route->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama ODP</label>
                        <input type="text" name="name" class="form-control" value="{{ $o->name }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Alamat</label>
                        <input type="text" name="address" class="form-control" value="{{ $o->address }}">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Latitude</label>
                            <input type="number" step="0.0000001" name="latitude" class="form-control" value="{{ $o->latitude }}" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Longitude</label>
                            <input type="number" step="0.0000001" name="longitude" class="form-control" value="{{ $o->longitude }}" required>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kapasitas Port</label>
                            <input type="number" name="port_capacity" class="form-control" value="{{ $o->port_capacity }}" min="1" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" required>
                                <option value="active" {{ $o->status === 'active' ? 'selected' : '' }}>Aktif</option>
                                <option value="maintenance" {{ $o->status === 'maintenance' ? 'selected' : '' }}>Maintenance</option>
                                <option value="inactive" {{ $o->status === 'inactive' ? 'selected' : '' }}>Nonaktif</option>
                            </select>
                        </div>
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
@endforeach
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var labels = @json($chartLabels);
        var used = @json($chartUsed);
        var capacity = @json($chartCapacity);

        var primary = '#2563eb';
        var accent = '#6366f1';
        var danger = '#dc2626';
        var warning = '#d97706';
        var green = '#10b981';

        new Chart(document.getElementById('odpChart'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Terpakai',
                        data: used,
                        backgroundColor: used.map(function(v, i) {
                            var pct = capacity[i] > 0 ? (v / capacity[i]) * 100 : 0;
                            return pct >= 80 ? danger : (pct >= 50 ? warning : primary);
                        }),
                        borderRadius: 6,
                        barPercentage: 0.5,
                    },
                    {
                        label: 'Kapasitas',
                        data: capacity,
                        backgroundColor: '#e2e8f0',
                        borderRadius: 6,
                        barPercentage: 0.5,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { usePointStyle: true, font: { size: 12 }, color: '#475569', padding: 16 }
                    },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(ctx) {
                                return ctx.dataset.label + ': ' + ctx.raw + ' port';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                        ticks: { color: '#94a3b8', font: { size: 11 } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#94a3b8', font: { size: 11 } }
                    }
                }
            }
        });

        var usedPorts = {{ $usedPorts }};
        var available = {{ $availablePorts }};

        new Chart(document.getElementById('portChart'), {
            type: 'doughnut',
            data: {
                labels: ['Terpakai', 'Tersedia'],
                datasets: [{
                    data: [usedPorts, available],
                    backgroundColor: ['#3b82f6', '#e2e8f0'],
                    borderWidth: 0,
                    hoverOffset: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 16,
                            usePointStyle: true,
                            font: { size: 11 },
                            color: '#475569'
                        }
                    },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(ctx) {
                                return ctx.label + ': ' + ctx.raw + ' port';
                            }
                        }
                    }
                }
            }
        });

        // Map
        var map = L.map('map').setView([-6.476, 106.014], 14);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            className: 'map-tiles'
        }).addTo(map);

        var odpsData = @json($odps);
        var odcsData = @json($odcs);
        var newOdpsData = @json($newOdpsJson);
        var routesData = @json($routes);
        var markerBounds = [];

        var icons = {
            green: L.divIcon({
                className: 'custom-marker',
                html: '<div style="width:18px;height:18px;background:#059669;border:3px solid #fff;border-radius:50%;box-shadow:0 2px 8px rgba(0,0,0,0.3);"></div>',
                iconSize: [18, 18],
                iconAnchor: [9, 9]
            }),
            orange: L.divIcon({
                className: 'custom-marker',
                html: '<div style="width:18px;height:18px;background:#d97706;border:3px solid #fff;border-radius:50%;box-shadow:0 2px 8px rgba(0,0,0,0.3);"></div>',
                iconSize: [18, 18],
                iconAnchor: [9, 9]
            }),
            red: L.divIcon({
                className: 'custom-marker',
                html: '<div style="width:18px;height:18px;background:#dc2626;border:3px solid #fff;border-radius:50%;box-shadow:0 2px 8px rgba(0,0,0,0.3);"></div>',
                iconSize: [18, 18],
                iconAnchor: [9, 9]
            })
        };

        var odcIcon = L.divIcon({
            className: 'custom-marker',
            html: '<div style="width:24px;height:24px;background:#0f172a;border:3px solid #fff;border-radius:6px;box-shadow:0 2px 10px rgba(0,0,0,0.35);display:flex;align-items:center;justify-content:center;color:#fff;font-size:10px;"><i class="fa-solid fa-server"></i></div>',
            iconSize: [24, 24],
            iconAnchor: [12, 12]
        });

        {{-- ROUTE POLYLINES --}}
        routesData.forEach(function(route) {
            if (route.coordinates && route.coordinates.length > 0) {
                var polyline = L.polyline(route.coordinates, {
                    color: route.color || '#2563eb',
                    weight: 3,
                    opacity: 0.7,
                    dashArray: '8, 6'
                }).addTo(map);

                polyline.bindPopup(`
                    <div style="font-family:'Inter',sans-serif;min-width:160px;">
                        <h6 style="margin:0;font-weight:700;font-size:13px;color:#0f172a;">
                            <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${route.color};vertical-align:middle;margin-right:6px;"></span>
                            ${route.name}
                        </h6>
                        ${route.description ? '<small style="color:#64748b;">'+route.description+'</small>' : ''}
                        <div style="margin-top:6px;font-size:11px;color:#475569;">
                            ODC: <strong>${route.odc?.name || '-'}</strong>
                        </div>
                    </div>
                `, { className: 'custom-popup' });

                {{-- Add to markerBounds if coordinates have points --}}
                route.coordinates.forEach(function(coord) {
                    if (coord && coord.length === 2) {
                        markerBounds.push([coord[0], coord[1]]);
                    }
                });
            }
        });

        {{-- ODC TO ODP CONNECTION LINES --}}
        {{-- Draw lines from ODC to its ODPs --}}
        var odcMap = {};
        odcsData.forEach(function(odc) {
            odcMap[odc.id] = odc;
        });

        newOdpsData.forEach(function(odp) {
            if (odp.latitude && odp.longitude && odp.odc_id && odcMap[odp.odc_id] && odcMap[odp.odc_id].latitude) {
                L.polyline([
                    [odcMap[odp.odc_id].latitude, odcMap[odp.odc_id].longitude],
                    [odp.latitude, odp.longitude]
                ], {
                    color: '#94a3b8',
                    weight: 1.5,
                    opacity: 0.3,
                    dashArray: '4, 4'
                }).addTo(map);
            }
        });

        odcsData.forEach(function(odc) {
            if (odc.latitude && odc.longitude) {
                var marker = L.marker([odc.latitude, odc.longitude], { icon: odcIcon }).addTo(map);
                marker.bindPopup(`
                    <div style="font-family:'Inter',sans-serif;min-width:180px;">
                        <h6 style="margin:0 0 6px;font-weight:800;color:#0f172a;"><i class="fa-solid fa-server"></i> ${odc.name}</h6>
                        <small style="color:#64748b;"><i class="fa-solid fa-location-dot"></i> ${odc.address ?? '-'}</small>
                        <div style="margin-top:8px;padding-top:8px;border-top:1px solid #f1f5f9;font-size:12px;color:#475569;">
                            Kapasitas: <strong>${odc.capacity ?? 0}</strong><br>
                            Status: <strong>${odc.status ?? '-'}</strong>
                        </div>
                    </div>
                `, { className: 'custom-popup' });
                markerBounds.push([odc.latitude, odc.longitude]);
            }
        });

        odpsData.forEach(function(odp) {
            if (odp.latitude && odp.longitude) {
                var terpakai = odp.customers ? odp.customers.length : 0;
                var totalCapacity = odp.port_capacity ?? 16;
                var sisaPort = totalCapacity - terpakai;
                var pct = totalCapacity > 0 ? Math.round((terpakai / totalCapacity) * 100) : 0;

                var iconColor = pct >= 80 ? 'red' : (pct >= 50 ? 'orange' : 'green');

                var marker = L.marker([odp.latitude, odp.longitude], {
                    icon: icons[iconColor]
                }).addTo(map);

                var customerList = odp.customers && odp.customers.length > 0
                    ? '<div style="margin-top:6px;padding-top:6px;border-top:1px solid #f1f5f9;font-size:11px;"><strong style="color:#0f172a;">Pelanggan:</strong><br>' + odp.customers.map(function(c) { return '<span style="color:#475569;">&bull; ' + c + '</span>'; }).join('<br>') + '</div>'
                    : '';

                var popupContent = `
                    <div style="font-family:'Inter',sans-serif;min-width:200px;">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                            <div style="width:10px;height:10px;border-radius:50%;background:${pct >= 80 ? '#dc2626' : (pct >= 50 ? '#d97706' : '#059669')};"></div>
                            <h6 style="margin:0;font-weight:700;font-size:14px;color:#0f172a;">${odp.name}</h6>
                        </div>
                        <small style="color:#64748b;"><i class="fa-solid fa-location-dot"></i> ${odp.address ?? '-'}</small>
                        <div style="margin-top:8px;padding-top:8px;border-top:1px solid #f1f5f9;">
                            <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px;">
                                <span style="color:#475569;">Terpakai</span>
                                <span style="font-weight:600;">${terpakai}/${totalCapacity}</span>
                            </div>
                            <div style="height:4px;background:#e2e8f0;border-radius:2px;overflow:hidden;">
                                <div style="height:100%;width:${pct}%;background:${pct >= 80 ? '#dc2626' : (pct >= 50 ? '#d97706' : '#059669')};border-radius:2px;"></div>
                            </div>
                            <div style="display:flex;justify-content:space-between;font-size:11px;margin-top:4px;">
                                <span style="font-weight:600;color:${pct >= 80 ? '#dc2626' : (pct >= 50 ? '#d97706' : '#059669')};">Sisa ${sisaPort} port</span>
                                <span style="color:#94a3b8;">${pct}%</span>
                            </div>
                            ${customerList}
                        </div>
                    </div>
                `;
                marker.bindPopup(popupContent, { className: 'custom-popup' });
                markerBounds.push([odp.latitude, odp.longitude]);
            }
        });

        {{-- NEW ODP (odps table) markers with customer list & port detail --}}
        newOdpsData.forEach(function(odp) {
            if (odp.latitude && odp.longitude) {
                var terpakai = odp.used ?? 0;
                var totalCapacity = odp.port_capacity ?? 16;
                var sisaPort = totalCapacity - terpakai;
                var pct = totalCapacity > 0 ? Math.round((terpakai / totalCapacity) * 100) : 0;

                var iconColor = pct >= 80 ? 'red' : (pct >= 50 ? 'orange' : 'green');
                var kondisi = odp.kondisi_jalur === 'DOWN_LINK_FAILURE';
                var kondisiLabel = kondisi ? 'PUTUS' : (odp.kondisi_jalur ?? 'NORMAL');
                var kondisiColor = kondisi ? '#dc2626' : '#059669';

                var marker = L.marker([odp.latitude, odp.longitude], {
                    icon: icons[kondisi ? 'red' : iconColor]
                }).addTo(map);

                var customerList = odp.customers && odp.customers.length > 0
                    ? '<div style="margin-top:6px;padding-top:6px;border-top:1px solid #f1f5f9;font-size:11px;"><strong style="color:#0f172a;">Pelanggan ('+odp.customers.length+'):</strong><br>' + odp.customers.map(function(c) { return '<span style="color:#475569;">&bull; ' + c + '</span>'; }).join('<br>') + '</div>'
                    : '<div style="margin-top:6px;padding-top:6px;border-top:1px solid #f1f5f9;font-size:11px;color:#94a3b8;">Belum ada pelanggan</div>';

                var onuInfo = '';
                if (odp.onu_total !== undefined) {
                    onuInfo = '<div style="display:flex;justify-content:space-between;font-size:11px;margin-top:2px;">' +
                        '<span style="color:#475569;">ONU</span>' +
                        '<span style="font-weight:600;"><span style="color:#059669;">' + (odp.onu_online || 0) + ' online</span> / <span style="color:#94a3b8;">' + odp.onu_total + ' total</span></span>' +
                        '</div>';
                }

                var popupContent = `
                    <div style="font-family:'Inter',sans-serif;min-width:200px;">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                            <div style="width:10px;height:10px;border-radius:50%;background:${kondisi ? kondisiColor : (pct >= 80 ? '#dc2626' : (pct >= 50 ? '#d97706' : '#059669'))};"></div>
                            <h6 style="margin:0;font-weight:700;font-size:14px;color:#0f172a;">${odp.name}</h6>
                        </div>
                        <small style="color:#64748b;"><i class="fa-solid fa-server"></i> ${odp.address ?? '-'}</small>
                        <div style="margin-top:8px;padding-top:8px;border-top:1px solid #f1f5f9;">
                            <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px;">
                                <span style="color:#475569;">Terpakai</span>
                                <span style="font-weight:600;">${terpakai}/${totalCapacity}</span>
                            </div>
                            <div style="height:4px;background:#e2e8f0;border-radius:2px;overflow:hidden;">
                                <div style="height:100%;width:${pct}%;background:${pct >= 80 ? '#dc2626' : (pct >= 50 ? '#d97706' : '#059669')};border-radius:2px;"></div>
                            </div>
                            <div style="display:flex;justify-content:space-between;font-size:11px;margin-top:4px;">
                                <span style="color:#475569;">Sisa ${sisaPort} port</span>
                                <span style="color:#94a3b8;">${pct}%</span>
                            </div>
                            <div style="display:flex;justify-content:space-between;font-size:11px;margin-top:2px;">
                                <span style="color:#475569;">Jalur</span>
                                <span style="font-weight:600;color:${kondisiColor};">${kondisiLabel}</span>
                            </div>
                            ${onuInfo}
                            ${customerList}
                        </div>
                        <div style="margin-top:6px;">
                            <a href="/odp/${odp.id}" style="font-size:11px;">&rarr; Detail ODP</a>
                        </div>
                    </div>
                `;
                marker.bindPopup(popupContent, { className: 'custom-popup' });
                markerBounds.push([odp.latitude, odp.longitude]);
            }
        });

        if (markerBounds.length > 0) {
            var bounds = L.latLngBounds(markerBounds);
            map.fitBounds(bounds, { padding: [40, 40] });
        }
    });
</script>
@endpush
