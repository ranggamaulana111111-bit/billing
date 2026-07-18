@extends('layouts.app')

@section('title', 'Speed Test — ONU Throughput')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1"><i class="fa-solid fa-speedometer text-warning"></i> Speed Test</h2>
        <p class="text-muted mb-0">Estimasi throughput berdasarkan kualitas optical power ONU</p>
    </div>
    <a href="{{ route('onu-health.dashboard') }}" class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-arrow-left"></i> Dashboard
    </a>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-tower-broadcast text-primary"></i> Pilih ONU Online</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-bold small">Cari Pelanggan</label>
                    <div class="input-group input-group-sm mb-2">
                        <span class="input-group-text"><i class="fa-solid fa-search"></i></span>
                        <input type="text" class="form-control" id="searchCustomer" placeholder="Nama / kode pelanggan..." oninput="filterOnuList()">
                    </div>
                    <select class="form-select" id="onuSelect" size="8" style="font-size:0.85rem;" onchange="loadOnuEstimate()">
                        @forelse($onus as $onu)
                            <option value="{{ $onu['id'] }}">
                                {{ $onu['customer_name'] }} ({{ $onu['customer_code'] }})
                            </option>
                        @empty
                            <option value="" disabled>Tidak ada ONU online</option>
                        @endforelse
                    </select>
                    <small class="text-muted">{{ $onus->count() }} ONU online tersedia</small>
                </div>

                <div id="onuInfo" class="d-none">
                    <hr>
                    <div class="small">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">ONU ID</span>
                            <strong id="infoOnuId">—</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">RX Power</span>
                            <strong id="infoRx" class="fw-bold">—</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">TX Power</span>
                            <strong id="infoTx">—</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="alert alert-info mb-3">
                    <i class="fa-solid fa-circle-info me-2"></i>
                    Speed test dilakukan berdasarkan pengukuran kondisi optical power (RX/TX) dan status link ONU.
                    Hasil adalah estimasi dari kualitas sinyal, bukan throughput aktual dari perangkat.
                </div>

                <div id="speedResult" class="d-none">
                    <div class="row g-3 mb-4">
                        <div class="col-md-3 text-center">
                            <div class="p-3 rounded-3 bg-success bg-opacity-10">
                                <i class="fa-solid fa-arrow-down fa-2x text-success mb-2"></i>
                                <h6 class="text-muted mb-1">Download</h6>
                                <h3 class="fw-bold text-success mb-0" id="dlSpeed">—</h3>
                                <small class="text-muted">Mbps</small>
                            </div>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="p-3 rounded-3 bg-primary bg-opacity-10">
                                <i class="fa-solid fa-arrow-up fa-2x text-primary mb-2"></i>
                                <h6 class="text-muted mb-1">Upload</h6>
                                <h3 class="fw-bold text-primary mb-0" id="ulSpeed">—</h3>
                                <small class="text-muted">Mbps</small>
                            </div>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="p-3 rounded-3 bg-info bg-opacity-10">
                                <i class="fa-solid fa-clock fa-2x text-info mb-2"></i>
                                <h6 class="text-muted mb-1">Latency</h6>
                                <h3 class="fw-bold text-info mb-0" id="latencySpeed">—</h3>
                                <small class="text-muted">ms</small>
                            </div>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="p-3 rounded-3 bg-secondary bg-opacity-10">
                                <i class="fa-solid fa-wave-square fa-2x text-secondary mb-2"></i>
                                <h6 class="text-muted mb-1">Packet Loss</h6>
                                <h3 class="fw-bold text-secondary mb-0" id="lossSpeed">—</h3>
                                <small class="text-muted">%</small>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Jitter</label>
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1" style="height:10px;">
                                    <div class="progress-bar bg-warning" id="jitterBar" style="width:0%"></div>
                                </div>
                                <span class="fw-bold small" id="jitterSpeed">— ms</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Bandwidth Utilization</label>
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1" style="height:10px;">
                                    <div class="progress-bar bg-primary" id="bwBar" style="width:0%"></div>
                                </div>
                                <span class="fw-bold small" id="bwSpeed">— %</span>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-secondary mb-0">
                        <i class="fa-solid fa-circle-info me-2"></i>
                        <span id="estimateMsg">Estimasi berdasarkan kondisi optical</span>
                    </div>
                </div>

                <div id="speedPlaceholder" class="text-center py-5">
                    <i class="fa-solid fa-speedometer fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Pilih pelanggan di panel kiri untuk melihat estimasi throughput</h5>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function filterOnuList() {
    const search = document.getElementById('searchCustomer').value.toLowerCase();
    const select = document.getElementById('onuSelect');
    Array.from(select.options).forEach(opt => {
        opt.style.display = opt.text.toLowerCase().includes(search) ? '' : 'none';
    });
}

async function loadOnuEstimate() {
    const select = document.getElementById('onuSelect');
    const onuId = select.value;
    if (!onuId) {
        document.getElementById('speedResult').classList.add('d-none');
        document.getElementById('speedPlaceholder').classList.remove('d-none');
        document.getElementById('onuInfo').classList.add('d-none');
        return;
    }

    try {
        const resp = await fetch(`{{ url('onu-health/speedtest') }}?onu_id=${onuId}`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await resp.json();

        if (!data.success) return;

        const onu = data.onu;
        const est = data.estimate;

        document.getElementById('infoOnuId').textContent = onu.onu_id;
        document.getElementById('infoRx').textContent = onu.rx_power !== null ? onu.rx_power + ' dBm' : '—';
        document.getElementById('infoTx').textContent = onu.tx_power !== null ? onu.tx_power + ' dBm' : '—';
        document.getElementById('onuInfo').classList.remove('d-none');

        document.getElementById('dlSpeed').textContent = est.download_mbps ?? 0;
        document.getElementById('ulSpeed').textContent = est.upload_mbps ?? 0;
        document.getElementById('latencySpeed').textContent = est.latency_ms ?? '—';
        document.getElementById('lossSpeed').textContent = est.packet_loss ?? 0;
        document.getElementById('jitterSpeed').textContent = (est.jitter ?? 0) + ' ms';
        document.getElementById('jitterBar').style.width = Math.min(100, (est.jitter ?? 0) * 10) + '%';
        document.getElementById('bwSpeed').textContent = (est.bandwidth_utilization ?? 0) + ' %';
        document.getElementById('bwBar').style.width = (est.bandwidth_utilization ?? 0) + '%';
        document.getElementById('estimateMsg').textContent = est.message || 'Estimasi berdasarkan kondisi optical';

        document.getElementById('speedResult').classList.remove('d-none');
        document.getElementById('speedPlaceholder').classList.add('d-none');
    } catch (e) {
        console.error('Speed test error:', e);
    }
}
</script>
@endpush
