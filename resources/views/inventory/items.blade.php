@extends('layouts.app')
@section('title', 'Daftar Barang')
@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-boxes-stacked me-2" style="color:var(--primary);"></i>Daftar Barang</h2>
        <p class="section-subtitle mb-0 mt-1">Kelola data inventaris perangkat ISP</p>
    </div>
    <div class="page-actions mt-2 mt-md-0 d-flex gap-2">
        <button class="btn btn-primary px-4 py-2" data-bs-toggle="modal" data-bs-target="#addItemModal">
            <i class="fa-solid fa-plus me-1"></i>Tambah Barang
        </button>
    </div>
</div>
@if(session('success'))
    <div class="alert alert-custom alert-success mb-4">{{ session('success') }}</div>
@endif
{{-- Filter --}}
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label fw-semibold" style="font-size:0.8rem;">Cari Barang</label>
                <input type="text" name="search" class="form-control form-control-sm" value="{{ request('search') }}" placeholder="Nama, merek, serial number...">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold" style="font-size:0.8rem;">Kategori</label>
                <select name="category" class="form-select form-select-sm">
                    <option value="">Semua Kategori</option>
                    @foreach(\App\Models\InventoryItem::CATEGORIES as $key => $label)
                        <option value="{{ $key }}" {{ request('category') === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm px-4">
                    <i class="fa-solid fa-filter me-1"></i>Filter
                </button>
                <a href="{{ route('inventory.items') }}" class="btn btn-outline-secondary btn-sm px-3">
                    <i class="fa-solid fa-rotate me-1"></i>Reset
                </a>
            </div>
        </form>
    </div>
</div>
{{-- Tabel Barang --}}
<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex align-items-center gap-2">
        <div style="width:8px;height:8px;border-radius:50%;background:var(--primary);"></div>
        <span class="fw-semibold">Data Inventaris</span>
        <span class="badge bg-primary ms-auto">{{ $items->total() }} barang</span>
    </div>
    <div class="card-body p-0">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
                    <tr>
                        <th class="ps-3">No</th>
                        <th>Nama Barang</th>
                        <th>Kategori</th>
                        <th>Merek / Type</th>
                        <th>Serial Number</th>
                        <th>Port</th>
                        <th>Stok</th>
                        <th class="text-end pe-3">Aksi</th>
                    </tr>

                <tbody style="font-size:0.82rem;">
                    @forelse($items as $item)
                        <tr>
                            <td class="ps-3 text-muted">{{ $items->firstItem + $loop->index }}</td>
                            <td class="fw-semibold">{{ $item->name }}</td>
                            <td>
                                <span class="badge" style="background:#eff6ff;color:#2563eb;font-weight:500;">
                                    {{ $item->category_label }}
                                </span>
                            </td>
                            <td>{{ $item->brand }} {{ $item->type }}</td>
                            <td><code style="font-size:0.75rem;">{{ $item->serial_number ?? '-' }}</code></td>
                            <td>
                                @if($item->category === 'olt')
                                    <span title="PON Ports">{{ $item->pon_port_count ?? '-' }} PON</span>
                                @elseif(in_array($item->category, ['otb', 'odc', 'odp']))
                                    <span title="Ports">{{ $item->port_count ?? '-' }} Port</span>
                                @elseif($item->cable_type)
                                    <span>{{ $item->cable_type }}</span>
                                @else
                                    -
                                @endif
                            </td>
                            <td>
                                @if($item->stock <= 0)
                                    <span class="badge bg-danger">0</span>
                                @elseif($item->stock <= 5)
                                    <span class="badge bg-warning text-dark">{{ $item->stock }}</span>
                                @else
                                    <span class="badge bg-success">{{ $item->stock }}</span>
                                @endif
                                <small class="text-muted">{{ $item->unit_label }}</small>
                            </td>
                            <td class="text-end pe-3">
                                <button class="btn btn-sm btn-outline-primary" title="Edit"
                                        data-bs-toggle="modal" data-bs-target="#editItemModal{{ $item->id }}">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <form method="POST" action="{{ route('inventory.items.destroy', $item->id) }}" class="d-inline"
                                      onsubmit="return confirm('Hapus barang {{ $item->name }}?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        {{-- Edit Modal per item --}}
                        <div class="modal fade" id="editItemModal{{ $item->id }}" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <form method="POST" action="{{ route('inventory.items.update', $item->id) }}">
                                        @csrf @method('PUT')
                                        <div class="modal-header">
                                            <h5 class="modal-title fw-bold">Edit: {{ $item->name }}</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            @include('inventory._form_fields', ['item' => $item])
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i>Simpan</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-box-open fa-2x mb-3 d-block" style="opacity:0.3;"></i>
                                Belum ada data inventaris.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($items->hasPages())
        <div class="card-footer bg-white">{{ $items->withQueryString()->links() }}</div>
    @endif
</div>
{{-- Add Item Modal --}}
<div class="modal fade" id="addItemModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ route('inventory.items.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-plus me-2"></i>Tambah Barang Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @include('inventory._form_fields', ['item' => null])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i>Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const catSelect = document.getElementById('add_category');
    if (catSelect) {
        catSelect.dispatchEvent(new Event('change'));
    }
});
</script>
@endpush
