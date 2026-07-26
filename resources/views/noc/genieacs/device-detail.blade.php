@extends('layouts.app')

@section('title', 'Device Detail — GenieACS NOC')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-hard-drive me-2" style="color:var(--primary);"></i>Device Detail</h2>
        <p class="section-subtitle mb-0 mt-1">
            <code style="font-size:0.82rem;">{{ $deviceId }}</code>
        </p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <button type="button" class="btn btn-outline-warning px-3 py-2" id="btnReboot" title="Reboot">
            <i class="fa-solid fa-rotate me-1"></i>Reboot
        </button>
        <button type="button" class="btn btn-outline-danger px-3 py-2" id="btnFactoryReset" title="Factory Reset">
            <i class="fa-solid fa-trash-can me-1"></i>Factory Reset
        </button>
        <button type="button" class="btn btn-outline-info px-3 py-2" id="btnRefresh" title="Refresh Object">
            <i class="fa-solid fa-arrows-rotate me-1"></i>Refresh
        </button>
        <a href="{{ route('noc.genieacs.devices') }}" class="btn btn-outline-secondary px-3 py-2">
            <i class="fa-solid fa-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

@if($error ?? null)
<div class="alert alert-danger d-flex align-items-center mb-4 py-3" style="font-size:0.85rem;">
    <i class="fa-solid fa-circle-xmark me-2 fa-lg"></i>
    <div>{{ $error }}</div>
</div>
@elseif(!$device)
<div class="alert alert-warning d-flex align-items-center mb-4 py-3" style="font-size:0.85rem;">
    <i class="fa-solid fa-triangle-exclamation me-2 fa-lg"></i>
    <div>Device tidak ditemukan atau GenieACS tidak terjangkau.</div>
</div>
@else
@php
    $info = $device['InternetGatewayDevice']['DeviceInfo'] ?? [];
    $model = $info['ModelName'] ?? '-';
    $manufacturer = $info['Manufacturer'] ?? '-';
    $software = $info['SoftwareVersion'] ?? '-';
    $serial = $info['SerialNumber'] ?? '-';
    $lastInform = $device['_lastInform'] ?? null;
    $isOnline = $lastInform && (time() - strtotime($lastInform)) < 600;
    $tags = $device['_tags'] ?? [];
@endphp

{{-- ═══ DEVICE INFO CARDS ═══ --}}
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-body py-3">
                <small class="text-muted d-block" style="font-size:0.72rem;">Status</small>
                <span class="badge {{ $isOnline ? 'bg-success' : 'bg-secondary' }}" style="font-size:0.75rem;">
                    {{ $isOnline ? 'Online' : 'Offline' }}
                </span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-body py-3">
                <small class="text-muted d-block" style="font-size:0.72rem;">Model</small>
                <span class="fw-semibold" style="font-size:0.88rem;">{{ $manufacturer }} {{ $model }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-body py-3">
                <small class="text-muted d-block" style="font-size:0.72rem;">Software Version</small>
                <code style="font-size:0.85rem;">{{ $software }}</code>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-body py-3">
                <small class="text-muted d-block" style="font-size:0.72rem;">Serial Number</small>
                <code style="font-size:0.85rem;">{{ $serial }}</code>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-body py-3">
                <small class="text-muted d-block" style="font-size:0.72rem;">Last Inform</small>
                <span style="font-size:0.88rem;">{{ $lastInform ? \Carbon\Carbon::parse($lastInform)->diffForHumans().' ('.$lastInform.')' : 'never' }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-body py-3">
                <small class="text-muted d-block" style="font-size:0.72rem;">Tags</small>
                @if(count($tags) > 0)
                    @foreach($tags as $tag)
                        <span class="badge bg-primary me-1" style="font-size:0.72rem;">{{ $tag }}</span>
                    @endforeach
                @else
                    <span class="text-muted" style="font-size:0.85rem;">-</span>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ═══ CWMP PARAMETERS ═══ --}}
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-transparent border-0 py-3">
        <h6 class="mb-0 fw-bold"><i class="fa-solid fa-tree me-2"></i>CWMP Parameter Tree</h6>
    </div>
    <div class="card-body p-0">
        <div class="p-3" style="font-family:monospace;font-size:0.82rem;max-height:500px;overflow-y:auto;background:rgba(0,0,0,0.15);">
            @php
                $renderTree = function ($data, $prefix = '') use (&$renderTree) {
                    if (!is_array($data)) {
                        echo htmlspecialchars((string) $data) . "\n";
                        return;
                    }
                    foreach ($data as $key => $value) {
                        if (str_starts_with($key, '_')) continue;
                        if (is_array($value) && count(array_filter(array_keys($value), 'is_int')) === count($value)) {
                            echo htmlspecialchars($key) . " [array, " . count($value) . " items]\n";
                            continue;
                        }
                        if (is_array($value)) {
                            echo htmlspecialchars($key) . "/\n";
                            $renderTree($value, $prefix . "  ");
                        } else {
                            echo $prefix . htmlspecialchars($key) . " = " . htmlspecialchars((string) $value) . "\n";
                        }
                    }
                };
                $renderTree($device);
            @endphp
        </div>
    </div>
</div>

{{-- ═══ PENDING TASKS ═══ --}}
@if(count($tasks) > 0)
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-transparent border-0 py-3">
        <h6 class="mb-0 fw-bold"><i class="fa-solid fa-list-check me-2"></i>Pending Tasks ({{ count($tasks) }})</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size:0.82rem;">
                <thead>
                    <tr>
                        <th class="ps-3">Task ID</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tasks as $task)
                    <tr>
                        <td class="ps-3"><code>{{ $task['_id'] ?? '-' }}</code></td>
                        <td>{{ $task['name'] ?? $task['type'] ?? '-' }}</td>
                        <td>
                            @php $status = $task['status'] ?? 'unknown'; @endphp
                            <span class="badge {{ in_array($status, ['completed', 'succeeded']) ? 'bg-success' : ($status === 'failed' ? 'bg-danger' : 'bg-warning text-dark') }}" style="font-size:0.7rem;">
                                {{ $status }}
                            </span>
                        </td>
                        <td>{{ $task['createdAt'] ?? '-' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

{{-- ═══ FAULTS ═══ --}}
@if(count($faults) > 0)
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-transparent border-0 py-3">
        <h6 class="mb-0 fw-bold"><i class="fa-solid fa-triangle-exclamation me-2 text-warning"></i>Faults ({{ count($faults) }})</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size:0.82rem;">
                <thead>
                    <tr>
                        <th class="ps-3">Code</th>
                        <th>Message</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($faults as $fault)
                    <tr>
                        <td class="ps-3"><code>{{ $fault['code'] ?? '-' }}</code></td>
                        <td>{{ $fault['message'] ?? '-' }}</td>
                        <td>{{ $fault['timestamp'] ?? '-' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif
@endif
@endsection

@push('scripts')
<script>
const deviceId = @json($deviceId);
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

async function postAction(url, body = {}) {
    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(body),
        });
        const data = await res.json();
        alert(data.message || (data.success ? 'OK' : 'Failed'));
        if (data.success) location.reload();
    } catch (e) {
        alert('Error: ' + e.message);
    }
}

document.getElementById('btnReboot')?.addEventListener('click', () => {
    if (confirm('Reboot device ' + deviceId + '?'))
        postAction('/noc/genieacs/' + encodeURIComponent(deviceId) + '/reboot');
});

document.getElementById('btnFactoryReset')?.addEventListener('click', () => {
    if (confirm('FACTORY RESET device ' + deviceId + '? Semua konfigurasi akan hilang!'))
        postAction('/noc/genieacs/' + encodeURIComponent(deviceId) + '/factory-reset');
});

document.getElementById('btnRefresh')?.addEventListener('click', () => {
    const object = prompt('Object to refresh:', 'InternetGatewayDevice');
    if (object)
        postAction('/noc/genieacs/' + encodeURIComponent(deviceId) + '/refresh', { object });
});
</script>
@endpush
