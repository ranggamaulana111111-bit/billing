@extends('layouts.app')

@section('title', 'AI Diagnosis — ' . ($onu->onu_id ?? ''))

@section('content')
<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1"><i class="fa-solid fa-stethoscope text-warning"></i> AI Rule-Based Diagnosis</h2>
        <p class="text-muted mb-0">{{ $onu->onu_id }} — {{ $onu->customer->name ?? 'Unlinked' }}</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('onu-health.detail', $onu->id) }}" class="btn btn-outline-primary btn-sm">
            <i class="fa-solid fa-circle-info"></i> Detail ONU
        </a>
        <a href="{{ route('onu-health.dashboard') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left"></i> Dashboard
        </a>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-4">
                <span class="badge bg-{{ $statusBadge['color'] }} fs-6 px-3 py-2 mb-2">{{ $statusBadge['label'] }}</span>
                <h5 class="fw-bold mb-1">{{ $onu->onu_id }}</h5>
                <small class="text-muted">{{ $onu->serial_number ?? 'No SN' }}</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-4">
                <i class="fa-solid fa-heart-pulse fa-2x text-{{ $health['color'] }} mb-2"></i>
                <h3 class="fw-bold mb-0">{{ $health['score'] }}</h3>
                <span class="badge bg-{{ $health['color'] }} fs-6">{{ $health['grade'] }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body py-4">
                <h6 class="fw-bold mb-2"><i class="fa-solid fa-signal text-primary"></i> Optical Power</h6>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">RX:</span>
                    <span class="fw-bold">{{ $onu->rx_power !== null ? number_format($onu->rx_power, 2).' dBm' : '—' }}</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">TX:</span>
                    <span class="fw-bold">{{ $onu->tx_power !== null ? number_format($onu->tx_power, 2).' dBm' : '—' }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

@if($health['factors'] !== [])
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom">
        <h6 class="mb-0 fw-bold"><i class="fa-solid fa-list-check text-primary"></i> Health Score Breakdown</h6>
    </div>
    <div class="card-body">
        <div class="row g-2">
            @foreach($health['factors'] as $factor)
                <div class="col-md-6">
                    <div class="d-flex align-items-center justify-content-between p-2 rounded-3 bg-{{ $factor['severity'] === 'critical' ? 'danger' : ($factor['severity'] === 'warning' ? 'warning' : ($factor['severity'] === 'excellent' ? 'success' : 'info') ) }} bg-opacity-10">
                        <span class="small">{{ $factor['factor'] }}</span>
                        <span class="fw-bold small text-{{ $factor['impact'] < 0 ? 'danger' : 'success' }}">{{ $factor['impact'] }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endif

@if($diagnoses !== [])
    @foreach($diagnoses as $idx => $d)
        @php
            $severityConfig = match($d['severity']) {
                'critical' => ['color' => 'danger', 'icon' => 'fa-circle-exclamation', 'bg' => 'danger'],
                'warning' => ['color' => 'warning', 'icon' => 'fa-triangle-exclamation', 'bg' => 'warning'],
                default => ['color' => 'info', 'icon' => 'fa-circle-info', 'bg' => 'info'],
            };
        @endphp
        <div class="card border-0 shadow-sm mb-4 border-start border-4 border-{{ $severityConfig['color'] }}">
            <div class="card-header bg-{{ $severityConfig['color'] }} bg-opacity-10 border-bottom">
                <div class="d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 fw-bold">
                        <i class="fa-solid {{ $severityConfig['icon'] }} text-{{ $severityConfig['color'] }}"></i>
                        Diagnosis #{{ $idx + 1 }}: {{ $d['title'] }}
                    </h6>
                    <div class="d-flex gap-2 align-items-center">
                        <span class="badge bg-{{ $severityConfig['color'] }}">{{ strtoupper($d['severity']) }}</span>
                        <span class="badge bg-secondary">Priority: {{ $d['priority'] }}</span>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-4">
                        <h6 class="fw-bold text-danger mb-2"><i class="fa-solid fa-magnifying-glass"></i> Kemungkinan Penyebab</h6>
                        <ul class="list-unstyled">
                            @foreach($d['causes'] as $cause)
                                <li class="mb-2 d-flex align-items-start">
                                    <i class="fa-solid fa-circle text-danger mt-1 me-2" style="font-size:6px;"></i>
                                    <span>{{ $cause }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="col-md-4">
                        <h6 class="fw-bold text-success mb-2"><i class="fa-solid fa-wrench"></i> Rekomendasi Tindakan</h6>
                        <ul class="list-unstyled">
                            @foreach($d['recommendations'] as $rec)
                                <li class="mb-2 d-flex align-items-start">
                                    <i class="fa-solid fa-circle text-success mt-1 me-2" style="font-size:6px;"></i>
                                    <span>{{ $rec }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="col-md-4">
                        <h6 class="fw-bold text-primary mb-2"><i class="fa-solid fa-chart-simple"></i> Confidence Level</h6>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <small>Tingkat Kepercayaan</small>
                                <small class="fw-bold">{{ $d['confidence'] }}%</small>
                            </div>
                            <div class="progress" style="height:12px;">
                                <div class="progress-bar bg-{{ $d['confidence'] >= 80 ? 'success' : ($d['confidence'] >= 60 ? 'warning' : 'secondary') }}" style="width:{{ $d['confidence'] }}%"></div>
                            </div>
                        </div>

                        <div class="p-3 rounded bg-light">
                            <h6 class="fw-bold small mb-2"><i class="fa-solid fa-clipboard-list"></i> Action Plan</h6>
                            <ol class="small mb-0 ps-3">
                                @foreach($d['recommendations'] as $idx2 => $rec)
                                    <li class="mb-1">{{ $rec }}</li>
                                @endforeach
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
@else
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="fa-solid fa-check-circle fa-3x text-success mb-3"></i>
            <h5 class="fw-bold text-success">ONU Sehat</h5>
            <p class="text-muted mb-0">Tidak ada diagnosis yang perlu ditindaklanjuti untuk ONU ini.</p>
        </div>
    </div>
@endif
@endsection
