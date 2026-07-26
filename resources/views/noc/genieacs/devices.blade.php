@extends('layouts.app')

@section('title', 'GenieACS Devices — NOC')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-hard-drive me-2" style="color:var(--primary);"></i>CPE Devices</h2>
        <p class="section-subtitle mb-0 mt-1">{{ $total }} perangkat terdaftar di GenieACS</p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <a href="{{ route('noc.genieacs.dashboard') }}" class="btn btn-outline-secondary px-3 py-2">
            <i class="fa-solid fa-gauge-high me-1"></i>Dashboard
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-custom alert-success mb-4">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-custom alert-danger mb-4">{{ session('error') }}</div>
@endif

{{-- ═══ FILTER BAR ═══ --}}
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Pencarian</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Device ID, serial, model..." value="{{ $filters['search'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Model</label>
                <input type="text" name="model" class="form-control form-control-sm" placeholder="e.g. HG8245H" value="{{ $filters['model'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Manufacturer</label>
                <input type="text" name="manufacturer" class="form-control form-control-sm" placeholder="e.g. Huawei" value="{{ $filters['manufacturer'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Software Ver.</label>
                <input type="text" name="software_version" class="form-control form-control-sm" placeholder="e.g. V300R013" value="{{ $filters['software_version'] ?? '' }}">
            </div>
            <div class="col-md-1">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Limit</label>
                <select name="limit" class="form-select form-select-sm">
                    @foreach([25, 50, 100, 200] as $l)
                        <option value="{{ $l }}" {{ ($limit ?? 50) == $l ? 'selected' : '' }}>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                    <i class="fa-solid fa-filter me-1"></i>Filter
                </button>
                <a href="{{ route('noc.genieacs.devices') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-xmark"></i>
                </a>
            </div>
        </form>
    </div>
</div>

{{-- ═══ DEVICE LIST ═══ --}}
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        @if(empty($devices))
        <div class="text-center py-5">
            <i class="fa-solid fa-satellite-dish fa-3x mb-3" style="color:rgba(255,255,255,0.1);"></i>
            <h5 class="text-muted">Tidak ada device ditemukan</h5>
            <p class="text-muted mb-0" style="font-size:0.85rem;">Pastikan GenieACS server aktif dan konfigurasi benar.</p>
        </div>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr style="font-size:0.78rem;">
                        <th class="ps-3">Device ID</th>
                        <th>Model</th>
                        <th>Manufacturer</th>
                        <th>Software</th>
                        <th>Last Inform</th>
                        <th>Status</th>
                        <th class="pe-3 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($devices as $device)
                    @php
                        $deviceId = $device['_id'] ?? 'unknown';
                        $model = $device['InternetGatewayDevice']['DeviceInfo']['ModelName'] ?? '-';
                        $manufacturer = $device['InternetGatewayDevice']['DeviceInfo']['Manufacturer'] ?? '-';
                        $software = $device['InternetGatewayDevice']['DeviceInfo']['SoftwareVersion'] ?? '-';
                        $lastInform = $device['_lastInform'] ?? null;
                        $isOnline = $lastInform && (time() - strtotime($lastInform)) < 600;
                    @endphp
                    <tr style="font-size:0.85rem;">
                        <td class="ps-3">
                            <a href="{{ route('noc.genieacs.device-detail', $deviceId) }}" class="text-decoration-none fw-semibold" style="color:var(--primary);">
                                {{ Str::limit($deviceId, 40) }}
                            </a>
                        </td>
                        <td>{{ $model }}</td>
                        <td>{{ $manufacturer }}</td>
                        <td><code style="font-size:0.78rem;">{{ $software }}</code></td>
                        <td>
                            @if($lastInform)
                                <span title="{{ $lastInform }}">{{ \Carbon\Carbon::parse($lastInform)->diffForHumans() }}</span>
                            @else
                                <span class="text-muted">never</span>
                            @endif
                        </td>
                        <td>
                            @if($isOnline)
                                <span class="badge bg-success" style="font-size:0.7rem;">Online</span>
                            @else
                                <span class="badge bg-secondary" style="font-size:0.7rem;">Offline</span>
                            @endif
                        </td>
                        <td class="pe-3 text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('noc.genieacs.device-detail', $deviceId) }}" class="btn btn-outline-primary" title="Detail">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                                <button class="btn btn-outline-warning btn-reboot" data-device="{{ $deviceId }}" title="Reboot">
                                    <i class="fa-solid fa-rotate"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Pagination / Navigation --}}
        @if($total > $limit)
        <div class="d-flex justify-content-between align-items-center px-3 py-3" style="font-size:0.85rem;">
            <span class="text-muted">Menampilkan {{ $skip + 1 }}-{{ min($skip + $limit, $total) }} dari {{ $total }}</span>
            <div class="d-flex gap-1">
                @if($skip > 0)
                <a href="{{ route('noc.genieacs.devices', array_merge($filters, ['skip' => max(0, $skip - $limit), 'limit' => $limit])) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-chevron-left me-1"></i>Prev
                </a>
                @endif
                @if($skip + $limit < $total)
                <a href="{{ route('noc.genieacs.devices', array_merge($filters, ['skip' => $skip + $limit, 'limit' => $limit])) }}" class="btn btn-outline-secondary btn-sm">
                    Next<i class="fa-solid fa-chevron-right ms-1"></i>
                </a>
                @endif
            </div>
        </div>
        @endif
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.btn-reboot').forEach(btn => {
    btn.addEventListener('click', function() {
        const deviceId = this.dataset.device;
        if (!confirm('Reboot device ' + deviceId + '?')) return;

        fetch('/noc/genieacs/' + encodeURIComponent(deviceId) + '/reboot', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
        }).then(r => r.json()).then(data => {
            alert(data.message || (data.success ? 'Reboot sent' : 'Failed'));
        }).catch(e => alert('Error: ' + e.message));
    });
});
</script>
@endpush
