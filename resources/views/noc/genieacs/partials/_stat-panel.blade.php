@props([
    'title' => '',
    'icon' => 'fa-chart-pie',
    'total' => 0,
    'items' => [],
])
@php
    $pct = 0.0;
    $stops = [];
    $hasData = false;

    foreach ($items as $_it) {
        $value = (float) ($_it['pct'] ?? 0);

        if ($value > 0) {
            $hasData = true;
        }

        $start = $pct;
        $end = $pct + $value;
        $stops[] = ($_it['color'] ?? '#94a3b8').' '.
            rtrim(rtrim(number_format($start, 2, '.', ''), '0'), '.').'% '.
            rtrim(rtrim(number_format($end, 2, '.', ''), '0'), '.').'%';
        $pct = $end;
    }

    $gradient = $hasData ? 'conic-gradient('.implode(', ', $stops).')' : 'rgba(148,163,184,0.14)';
@endphp
<div class="card border-0 shadow-sm acs-panel h-100">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h6 class="acs-panel-title mb-0">
                <i class="fa-solid {{ $icon }} me-2" style="color:var(--primary);"></i>{{ $title }}
            </h6>
            <span class="badge rounded-pill bg-light text-secondary border" style="font-size:0.68rem;letter-spacing:0.02em;">Total {{ $total }}</span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="acs-donut-wrap flex-shrink-0">
                <div class="acs-donut" style="background:{{ $gradient }};"></div>
                <div class="acs-donut-center">{{ $total }}</div>
            </div>
            <div class="acs-legend flex-grow-1">
                @foreach($items as $it)
                <div class="acs-legend-row">
                    <span class="acs-dot" style="background:{{ $it['color'] }};"></span>
                    <span class="acs-legend-label text-truncate" title="{{ $it['label'] }}">{{ $it['label'] }}:</span>
                    <span class="acs-legend-count fw-semibold">{{ $it['value'] }}</span>
                    <span class="acs-legend-pct text-muted">({{ $it['pct'] }}%)</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>