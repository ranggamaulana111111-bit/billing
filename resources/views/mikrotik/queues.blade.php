@extends('layouts.app')
@section('title', 'Queue Bandwidth')
@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-gauge-high me-2" style="color:var(--primary);"></i>Queue Bandwidth</h2>
        <p class="section-subtitle mb-0 mt-1">Manajemen bandwidth — Simple Queue MikroTik</p>
    </div>
    <div class="page-actions mt-2 mt-md-0">
        <a href="{{ route('mikrotik.dashboard', ['router' => request('router')]) }}" class="btn btn-outline-secondary px-3">
            <i class="fa-solid fa-arrow-left me-1"></i>Monitor
        </a>
    </div>
</div>
@include('mikrotik._router_switcher')
@if(session('success'))
    <div class="alert alert-custom alert-success mb-4">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-custom alert-danger mb-4">{{ session('error') }}</div>
@endif
<div class="row g-4">
    {{-- FORM TAMBAH --}}
    <div class="col-lg-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <div class="d-flex align-items-center gap-2">
                    <div style="width:8px;height:8px;border-radius:50%;background:#059669;"></div>
                    <span>Tambah Queue</span>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('mikrotik.queues.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Queue</label>
                        <input type="text" name="name" class="form-control" placeholder="Pelanggan1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Target IP</label>
                        <input type="text" name="target" class="form-control" placeholder="192.168.1.100/32" required>
                        <small class="text-muted">IP address target (bisa pakai /32)</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Max Limit (tx/rx)</label>
                        <input type="text" name="max_limit" class="form-control" placeholder="10M/10M" required>
                        <small class="text-muted">Format: upload/download (contoh: 10M/10M)</small>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fa-solid fa-plus me-1"></i>Tambah Queue
                    </button>
                </form>
            </div>
        </div>
    </div>
    {{-- DAFTAR --}}
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <div class="d-flex align-items-center gap-2">
                    <div style="width:8px;height:8px;border-radius:50%;background:var(--primary);"></div>
                    <span>Daftar Simple Queue</span>
                    <span class="badge badge-premium ms-2" style="background:#eef2ff;color:var(--primary);">{{ count($queues) }}</span>
                </div>
            </div>
            <div class="card-body p-0">
                        <tr>
                            <th>Nama</th>
                            <th>Target</th>
                            <th>Max Limit</th>
                            <th class="text-center">Aksi</th>
                        </tr>

                    <tbody>
                        @forelse($queues as $q)
                            <tr>
                                <td class="fw-medium">{{ $q['name'] ?? '-' }}</td>
                                <td><code style="font-size:0.75rem;">{{ $q['target'] ?? '-' }}</code></td>
                                <td><code>{{ $q['max-limit'] ?? '-' }}</code></td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-primary px-2" title="Edit"
                                            data-bs-toggle="modal" data-bs-target="#editQueueModal"
                                            data-id="{{ $q['.id'] ?? '' }}"
                                            data-name="{{ $q['name'] ?? '' }}"
                                            data-target="{{ $q['target'] ?? '' }}"
                                            data-max-limit="{{ $q['max-limit'] ?? '' }}">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary px-2 dropdown-toggle" data-bs-toggle="dropdown" style="font-size:0.7rem;"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                            <ul class="dropdown-menu dropdown-menu-end" style="font-size:0.8rem;min-width:160px;">
                                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#editQueueModal"
                                                    data-id="{{ $q['.id'] ?? '' }}"
                                                    data-name="{{ $q['name'] ?? '' }}"
                                                    data-target="{{ $q['target'] ?? '' }}"
                                                    data-max-limit="{{ $q['max-limit'] ?? '' }}"><i class="fa-solid fa-pen me-2 text-primary"></i>Edit</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form method="POST" action="{{ route('mikrotik.queues.destroy', $q['.id'] ?? '') }}" onsubmit="return confirm('Hapus queue {{ $q['name'] ?? '' }}?')">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" class="dropdown-item text-danger"><i class="fa-solid fa-trash me-2"></i>Hapus</button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center py-4 text-muted">Belum ada queue</td></tr>
                        @endforelse
                    </tbody>
<table class="table table-hover align-middle mb-0 mon-table">
                </table>
            </div>
        </div>
    </div>
</div>
{{-- MODAL EDIT --}}
<div class="modal fade" id="editQueueModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content" id="editQueueForm">
            @csrf @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-pen me-1"></i>Edit Queue</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Nama Queue</label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Target IP</label>
                    <input type="text" name="target" id="edit_target" class="form-control" required>
                    <small class="text-muted">IP address target (bisa pakai /32)</small>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Max Limit (tx/rx)</label>
                    <input type="text" name="max_limit" id="edit_max_limit" class="form-control" required>
                    <small class="text-muted">Format: upload/download (contoh: 10M/10M)</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-warning"><i class="fa-solid fa-save me-1"></i>Simpan</button>
            </div>
        </form>
    </div>
</div>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('editQueueModal');
    modal.addEventListener('show.bs.modal', function (event) {
        var btn = event.relatedTarget;
        document.getElementById('edit_name').value = btn.getAttribute('data-name');
        document.getElementById('edit_target').value = btn.getAttribute('data-target');
        document.getElementById('edit_max_limit').value = btn.getAttribute('data-max-limit');
        document.getElementById('editQueueForm').action = '{{ route('mikrotik.queues.update', ['queueId' => '__ID__']) }}'.replace('__ID__', btn.getAttribute('data-id'));
    });
});
</script>
@endpush
