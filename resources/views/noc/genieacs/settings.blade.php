@extends('layouts.app')

@section('title', 'GenieACS Settings — NOC')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-gear me-2" style="color:var(--primary);"></i>GenieACS Settings</h2>
        <p class="section-subtitle mb-0 mt-1">Konfigurasi koneksi ke server GenieACS NBI</p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <a href="{{ route('noc.genieacs.dashboard') }}" class="btn btn-outline-secondary px-3 py-2">
            <i class="fa-solid fa-gauge-high me-1"></i>Dashboard
        </a>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        {{-- ═══ CONNECTION INFO ═══ --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent border-0 py-3">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-plug me-2"></i>Connection Configuration</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold" style="font-size:0.78rem;">Base URL (NBI)</label>
                    <input type="text" class="form-control" value="{{ $baseUrl }}" readonly style="font-size:0.85rem;background:rgba(0,0,0,0.1);">
                    <small class="text-muted">Format: <code>http://hostname:7557</code></small>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold" style="font-size:0.78rem;">Username</label>
                    <input type="text" class="form-control" value="{{ $username }}" readonly style="font-size:0.85rem;background:rgba(0,0,0,0.1);">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold" style="font-size:0.78rem;">Password</label>
                    <input type="password" class="form-control" value="{{ $hasPassword ? '••••••••' : '' }}" readonly style="font-size:0.85rem;background:rgba(0,0,0,0.1);">
                    <small class="text-muted">{{ $hasPassword ? 'Password configured.' : 'Not set.' }}</small>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold" style="font-size:0.78rem;">Timeout (seconds)</label>
                    <input type="text" class="form-control" value="{{ $timeout }}" readonly style="font-size:0.85rem;background:rgba(0,0,0,0.1);">
                </div>
                <div class="alert alert-info" style="font-size:0.82rem;">
                    <i class="fa-solid fa-info-circle me-1"></i>
                    Konfigurasi diambil dari file <code>.env</code>. Untuk mengubah, edit variabel <code>GENIEACS_*</code> di server.
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        {{-- ═══ TEST CONNECTION ═══ --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent border-0 py-3">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-vial me-2"></i>Test Connection</h6>
            </div>
            <div class="card-body">
                <p style="font-size:0.85rem;color:rgba(255,255,255,0.6);">
                    Klik tombol di bawah untuk menguji koneksi ke GenieACS NBI server.
                </p>
                <button type="button" class="btn btn-primary px-4 py-2" id="btnTestConnection">
                    <i class="fa-solid fa-plug-circle-bolt me-1"></i>Test Connection
                </button>
                <div id="testResult" class="mt-3" style="display:none;"></div>
            </div>
        </div>

        {{-- ═══ API ENDPOINTS REFERENCE ═══ --}}
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 py-3">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-book me-2"></i>Quick Reference</h6>
            </div>
            <div class="card-body" style="font-size:0.82rem;">
                <table class="table table-sm table-borderless mb-0">
                    <tbody>
                        <tr>
                            <td class="text-muted" style="width:180px;">NBI Endpoint</td>
                            <td><code>{{ $baseUrl }}</code></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Devices API</td>
                            <td><code>GET {{ $baseUrl }}/devices</code></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Faults API</td>
                            <td><code>GET {{ $baseUrl }}/faults</code></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Presets API</td>
                            <td><code>GET {{ $baseUrl }}/presets</code></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Provisions API</td>
                            <td><code>GET {{ $baseUrl }}/provisions</code></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('btnTestConnection').addEventListener('click', async function() {
    const btn = this;
    const resultDiv = document.getElementById('testResult');

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Testing...';
    resultDiv.style.display = 'block';
    resultDiv.innerHTML = '<div class="alert alert-info" style="font-size:0.85rem;"><i class="fa-solid fa-spinner fa-spin me-1"></i>Menghubungi GenieACS NBI...</div>';

    try {
        const res = await fetch('{{ route("noc.genieacs.test-connection") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
        });
        const data = await res.json();

        if (data.success) {
            resultDiv.innerHTML = `
                <div class="alert alert-success" style="font-size:0.85rem;">
                    <i class="fa-solid fa-circle-check me-1"></i>
                    <strong>Koneksi berhasil!</strong> ${data.message}
                    ${data.data ? '<br><small class="text-muted">Response: ' + JSON.stringify(data.data).substring(0, 200) + '</small>' : ''}
                </div>`;
        } else {
            resultDiv.innerHTML = `
                <div class="alert alert-danger" style="font-size:0.85rem;">
                    <i class="fa-solid fa-circle-xmark me-1"></i>
                    <strong>Gagal!</strong> ${data.message}
                </div>`;
        }
    } catch (e) {
        resultDiv.innerHTML = `
            <div class="alert alert-danger" style="font-size:0.85rem;">
                <i class="fa-solid fa-circle-xmark me-1"></i>
                <strong>Error:</strong> ${e.message}
            </div>`;
    }

    btn.disabled = false;
    btn.innerHTML = '<i class="fa-solid fa-plug-circle-bolt me-1"></i>Test Connection';
});
</script>
@endpush
