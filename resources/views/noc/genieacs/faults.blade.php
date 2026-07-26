@extends('layouts.app')

@section('title', 'GenieACS Faults — NOC')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-triangle-exclamation me-2" style="color:var(--primary);"></i>Faults</h2>
        <p class="section-subtitle mb-0 mt-1">Error & fault dari perangkat CPE</p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <a href="{{ route('noc.genieacs.dashboard') }}" class="btn btn-outline-secondary px-3 py-2">
            <i class="fa-solid fa-gauge-high me-1"></i>Dashboard
        </a>
    </div>
</div>

{{-- ═══ FILTER BAR ═══ --}}
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Device ID</label>
                <input type="text" name="device" class="form-control form-control-sm" placeholder="Filter by device ID..." value="{{ $filters['device'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem;">Fault Code</label>
                <input type="number" name="code" class="form-control form-control-sm" placeholder="e.g. 9003" value="{{ $filters['code'] ?? '' }}">
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
                <a href="{{ route('noc.genieacs.faults') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-xmark"></i>
                </a>
            </div>
        </form>
    </div>
</div>

{{-- ═══ FAULT LIST ═══ --}}
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        @if(empty($faults))
        <div class="text-center py-5">
            <i class="fa-solid fa-check-circle fa-3x mb-3" style="color:rgba(34,197,94,0.3);"></i>
            <h5 class="text-muted">Tidak ada fault ditemukan</h5>
            <p class="text-muted mb-0" style="font-size:0.85rem;">Semua perangkat dalam kondisi normal.</p>
        </div>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr style="font-size:0.78rem;">
                        <th class="ps-3">Device</th>
                        <th>Code</th>
                        <th>Message</th>
                        <th>Timestamp</th>
                        <th>Updated</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($faults as $fault)
                    @php
                        $device = $fault['device'] ?? '-';
                        $code = $fault['code'] ?? '-';
                        $message = $fault['message'] ?? '-';
                        $timestamp = $fault['timestamp'] ?? null;
                        $updated = $fault['updated'] ?? null;
                    @endphp
                    <tr style="font-size:0.85rem;">
                        <td class="ps-3">
                            <a href="{{ route('noc.genieacs.device-detail', $device) }}" class="text-decoration-none" style="color:var(--primary);">
                                <code style="font-size:0.78rem;">{{ Str::limit($device, 35) }}</code>
                            </a>
                        </td>
                        <td>
                            @php
                                $codeClass = match(true) {
                                    $code >= 9000 && $code < 9100 => 'bg-danger',
                                    $code >= 9100 && $code < 9200 => 'bg-warning text-dark',
                                    default => 'bg-secondary',
                                };
                            @endphp
                            <span class="badge {{ $codeClass }}" style="font-size:0.72rem;">{{ $code }}</span>
                        </td>
                        <td style="max-width:300px;" title="{{ $message }}">{{ Str::limit($message, 80) }}</td>
                        <td>{{ $timestamp ? \Carbon\Carbon::parse($timestamp)->diffForHumans() : '-' }}</td>
                        <td>{{ $updated ? \Carbon\Carbon::parse($updated)->diffForHumans() : '-' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if(count($faults) >= $limit)
        <div class="d-flex justify-content-between align-items-center px-3 py-3" style="font-size:0.85rem;">
            <span class="text-muted">Menampilkan {{ $skip + 1 }}-{{ $skip + count($faults) }}</span>
            <div class="d-flex gap-1">
                @if($skip > 0)
                <a href="{{ route('noc.genieacs.faults', array_merge($filters, ['skip' => max(0, $skip - $limit), 'limit' => $limit])) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-chevron-left me-1"></i>Prev
                </a>
                @endif
                <a href="{{ route('noc.genieacs.faults', array_merge($filters, ['skip' => $skip + $limit, 'limit' => $limit])) }}" class="btn btn-outline-secondary btn-sm">
                    Next<i class="fa-solid fa-chevron-right ms-1"></i>
                </a>
            </div>
        </div>
        @endif
        @endif
    </div>
</div>
@endsection
