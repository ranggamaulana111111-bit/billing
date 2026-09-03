@extends('layouts.app')

@section('title', 'GenieACS Settings — NOC')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-gear me-2" style="color:var(--primary);"></i>GenieACS Settings</h2>
        <p class="section-subtitle mb-0 mt-1">Konfigurasi koneksi ke server GenieACS NBI</p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <a href="{{ route('noc.genieacs.settings') }}" class="btn btn-outline-secondary px-3 py-2" id="btnRefresh">
            <i class="fa-solid fa-rotate me-1"></i>Refresh
        </a>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent border-0 py-3">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-plug me-2"></i>Connection Configuration</h6>
            </div>
            <div class="card-body">
                <form id="formGenieacsSettings">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold" style="font-size:0.78rem;">Base URL (NBI)</label>
                        <input type="text" class="form-control" name="base_url" value="{{ $baseUrl }}" placeholder="http://192.168.1.10:7557" style="font-size:0.85rem;">
                        <small class="text-muted">Format: <code>http://hostname:7557</code></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" style="font-size:0.78rem;">Username</label>
                        <input type="text" class="form-control" name="username" value="{{ $username }}" placeholder="admin" style="font-size:0.85rem;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" style="font-size:0.78rem;">Password</label>
                        <input type="password" class="form-control" name="password" placeholder="{{ $hasPassword ? '•••••••• (terisi)' : 'Kosongkan jika tidak diubah' }}" style="font-size:0.85rem;">
                        <small class="text-muted">{{ $hasPassword ? 'Password sudah dikonfigurasi. Kosongkan field ini jika tidak ingin mengubah.' : 'Masukkan password GenieACS NBI.' }}</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" style="font-size:0.78rem;">Timeout (seconds)</label>
                        <input type="text" class="form-control" value="{{ $timeout }}" readonly style="font-size:0.85rem;background:rgba(0,0,0,0.1);">
                        <small class="text-muted">Dari <code>.env</code> (<code>GENIEACS_TIMEOUT</code>)</small>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button type="submit" class="btn btn-primary px-4 py-2" id="btnSaveSettings">
                            <i class="fa-solid fa-floppy-disk me-1"></i>Simpan
                        </button>
                        <span id="saveStatus" class="text-muted" style="font-size:0.85rem;"></span>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
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

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 py-3">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-book me-2"></i>Quick Reference</h6>
            </div>
            <div class="card-body" style="font-size:0.82rem;">
                <table class="table table-sm table-borderless mb-0">
                    <tbody>
                        <tr>
                            <td class="text-muted" style="width:180px;">NBI Endpoint</td>
                            <td><code id="refBaseUrl">{{ $baseUrl }}</code></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Devices API</td>
                            <td><code>GET /devices</code></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Faults API</td>
                            <td><code>GET /faults</code></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Presets API</td>
                            <td><code>GET /presets</code></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Provisions API</td>
                            <td><code>GET /provisions</code></td>
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
document.getElementById('formGenieacsSettings').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSaveSettings');
    const status = document.getElementById('saveStatus');
    const formData = new FormData(this);
    const data = {
        base_url: formData.get('base_url') || '',
        username: formData.get('username') || '',
    };
    const password = formData.get('password');
    if (password) data.password = password;

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Menyimpan...';
    status.textContent = '';
    status.className = 'text-muted';

    try {
        const res = await fetch('{{ route("noc.genieacs.save-settings") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify(data),
        });
        const result = await res.json();

        if (result.ok) {
            status.textContent = result.message || 'Tersimpan';
            status.className = 'text-success';
            document.getElementById('refBaseUrl').textContent = data.base_url;
            if (password) {
                document.querySelector('input[name="password"]').value = '';
                document.querySelector('input[name="password"]').placeholder = '•••••••• (terisi)';
                document.querySelector('input[name="password"]').previousElementSibling.nextElementSibling.textContent = 'Password sudah dikonfigurasi. Kosongkan field ini jika tidak ingin mengubah.';
            }
        } else {
            status.textContent = result.message || 'Gagal menyimpan';
            status.className = 'text-danger';
        }
    } catch (err) {
        status.textContent = 'Error: ' + err.message;
        status.className = 'text-danger';
    }

    btn.disabled = false;
    btn.innerHTML = '<i class="fa-solid fa-floppy-disk me-1"></i>Simpan';
});

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
