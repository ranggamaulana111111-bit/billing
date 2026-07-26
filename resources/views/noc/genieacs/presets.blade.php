@extends('layouts.app')

@section('title', 'GenieACS Presets — NOC')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-file-code me-2" style="color:var(--primary);"></i>Presets</h2>
        <p class="section-subtitle mb-0 mt-1">{{ count($presets) }} preset provisioning di GenieACS</p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <a href="{{ route('noc.genieacs.dashboard') }}" class="btn btn-outline-secondary px-3 py-2">
            <i class="fa-solid fa-gauge-high me-1"></i>Dashboard
        </a>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        @if(empty($presets))
        <div class="text-center py-5">
            <i class="fa-solid fa-file-code fa-3x mb-3" style="color:rgba(255,255,255,0.1);"></i>
            <h5 class="text-muted">Tidak ada preset ditemukan</h5>
            <p class="text-muted mb-0" style="font-size:0.85rem;">Buat preset di GenieACS untuk provisioning otomatis.</p>
        </div>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr style="font-size:0.78rem;">
                        <th class="ps-3">Name</th>
                        <th>Channel</th>
                        <th>Weight</th>
                        <th>Precondition</th>
                        <th>Provisions</th>
                        <th>Arguments</th>
                        <th class="pe-3 text-end">Order</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($presets as $preset)
                    @php
                        $name = $preset['name'] ?? '-';
                        $channel = $preset['channel'] ?? '-';
                        $weight = $preset['weight'] ?? 0;
                        $precondition = $preset['precondition'] ?? [];
                        $provisions = $preset['provisions'] ?? [];
                        $args = $preset['args'] ?? [];
                    @endphp
                    <tr style="font-size:0.85rem;">
                        <td class="ps-3 fw-semibold">{{ $name }}</td>
                        <td><span class="badge bg-info" style="font-size:0.72rem;">{{ $channel }}</span></td>
                        <td>{{ $weight }}</td>
                        <td>
                            @if(!empty($precondition))
                                @foreach($precondition as $pc)
                                    <code style="font-size:0.72rem;" class="d-block">{{ is_array($pc) ? json_encode($pc) : $pc }}</code>
                                @endforeach
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            @if(!empty($provisions))
                                @foreach($provisions as $prov)
                                    <code style="font-size:0.72rem;" class="d-block">{{ $prov }}</code>
                                @endforeach
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            @if(!empty($args))
                                <span style="font-size:0.72rem;">{{ count($args) }} arg(s)</span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td class="pe-3 text-end">{{ $preset['_index'] ?? '-' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>
@endsection
