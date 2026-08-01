<div class="pm-head">
    <div class="pm-brand">
        <span class="logo-chip" style="width:40px;height:40px;border-radius:10px;">
            <img src="{{ asset('images/logo-alkonek.gif') }}" alt="Logo ALKONEK">
        </span>
        <div>
            <div class="pm-brand-name">{{ $company['name'] }}</div>
            <div class="pm-brand-sub">Halo, {{ $customer->name }} ({{ $customer->customer_code }})</div>
        </div>
    </div>
    <button type="button" class="pm-close" data-close aria-label="Tutup"><i class="fa-solid fa-xmark"></i></button>
</div>

@php
    $unpaid = $invoices->where('payment_status', '!=', 'paid');
    $unpaidTotal = $unpaid->sum('amount');
@endphp

<div class="pm-stats">
    <div class="pm-stat">
        <div class="pm-stat-num">{{ $invoices->count() }}</div>
        <div class="pm-stat-label">Total Tagihan</div>
    </div>
    <div class="pm-stat">
        <div class="pm-stat-num">{{ $unpaid->count() }}</div>
        <div class="pm-stat-label">Belum Bayar</div>
    </div>
    <div class="pm-stat">
        <div class="pm-stat-num pm-stat-money">Rp {{ number_format($unpaidTotal, 0, ',', '.') }}</div>
        <div class="pm-stat-label">Total Belum Bayar</div>
    </div>
</div>

@if($incidents->isNotEmpty())
    <div class="pm-section">
        <div class="pm-section-title"><i class="fa-solid fa-triangle-exclamation"></i> Gangguan Aktif</div>
        @foreach($incidents as $inc)
            @php
                $sevColor = match($inc->severity) {
                    'critical' => '#fb7185',
                    'high' => '#fb923c',
                    'medium' => '#facc15',
                    default => '#94a3b8',
                };
                $statusLabel = match($inc->status) {
                    'open' => 'Dilaporkan',
                    'investigating' => 'Sedang Ditangani',
                    default => ucfirst($inc->status),
                };
            @endphp
            <div class="pm-incident" style="border-left:4px solid {{ $sevColor }};">
                <div class="pm-incident-title">{{ $inc->title }}</div>
                <div class="pm-incident-meta">{{ $statusLabel }} &middot; {{ $inc->detected_at?->diffForHumans() }}</div>
                @if($inc->sla_deadline)
                    <div class="pm-incident-meta">Estimasi perbaikan: {{ $inc->sla_deadline->format('d/m/Y H:i') }}</div>
                @endif
            </div>
        @endforeach
    </div>
@endif

@if($invoices->isNotEmpty())
    <div class="pm-history">
        <button type="button" class="pm-history-toggle" data-history-toggle aria-expanded="true">
            <i class="fa-solid fa-clock-rotate-left"></i>
            <span>Riwayat Pembayaran</span>
            <span class="pm-history-count">{{ $invoices->count() }} tagihan</span>
            <i class="fa-solid fa-chevron-down pm-caret"></i>
        </button>
        <div class="pm-history-body">
            @php
                $idMonths = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
                $fmtDate = function ($d) use ($idMonths) {
                    return $d->format('d ').$idMonths[$d->format('n') - 1].' '.$d->format('Y');
                };
                $fmtMonth = function ($d) use ($idMonths) {
                    return $idMonths[$d->format('n') - 1].' '.$d->format('Y');
                };
                $grouped = $invoices->groupBy(function ($inv) use ($fmtMonth) {
                    return $fmtMonth($inv->created_at);
                });
            @endphp
            @foreach($grouped as $month => $list)
                @php
                    $paidCount = $list->where('payment_status', 'paid')->count();
                    $unpaidCount = $list->where('payment_status', '!=', 'paid')->count();
                @endphp
                <div class="pm-month">
                    <div class="pm-month-head">
                        <span>{{ $month }}</span>
                        <span class="pm-month-sub">
                            {{ $paidCount }} lunas &middot; {{ $unpaidCount }} belum
                            &middot; Rp {{ number_format($list->sum('amount'), 0, ',', '.') }}
                        </span>
                    </div>
                    @foreach($list as $inv)
                        <div class="pm-invoice">
                            <div>
                                <div class="pm-inv-id">{{ $inv->invoice_display }}</div>
                                <div class="pm-inv-date"><i class="fa-regular fa-calendar"></i> {{ $fmtDate($inv->created_at) }}</div>
                            </div>
                            <div class="pm-inv-right">
                                <div class="pm-inv-amount">Rp {{ number_format($inv->amount, 0, ',', '.') }}</div>
                                @if($inv->payment_status === 'paid')
                                    <span class="pm-badge pm-paid"><i class="fa-regular fa-circle-check"></i> Lunas</span>
                                @else
                                    <span class="pm-badge pm-unpaid"><i class="fa-regular fa-clock"></i> Belum Bayar</span>
                                    @if($midtransConfigured)
                                        <a href="{{ route('portal.bayar', $inv->id) }}" class="pm-pay-btn">
                                            <i class="fa-solid fa-credit-card"></i> Bayar
                                        </a>
                                    @endif
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>
@else
    <div class="pm-section">
        <div class="pm-section-title"><i class="fa-solid fa-file-invoice"></i> Riwayat Pembayaran</div>
        <div class="pm-empty">
            <i class="fa-regular fa-file-lines"></i>
            <p>Belum ada tagihan.</p>
        </div>
    </div>
@endif

<div class="pm-foot">
    {{ $company['name'] }} @if($company['address']) &middot; {{ $company['address'] }} @endif
</div>
