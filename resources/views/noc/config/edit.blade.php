@extends('layouts.app')

@section('title', 'Edit '.$moduleDef['label'].' — Configuration Center')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.78rem;">
                <li class="breadcrumb-item"><a href="{{ route('noc.config.modules') }}">Config Center</a></li>
                <li class="breadcrumb-item"><a href="{{ route('noc.config.module', ['module' => $module]) }}?router_id={{ $router->id }}">{{ $moduleDef['label'] }}</a></li>
                <li class="breadcrumb-item active">Edit</li>
            </ol>
        </nav>
        <h2 class="mb-0">
            <i class="fa-solid fa-pen-to-square me-2" style="color:var(--primary);"></i>Edit {{ $moduleDef['label'] }}
        </h2>
        <p class="section-subtitle mb-0 mt-1">
            <code>{{ $itemId }}</code> on {{ $router->display_identity }}
        </p>
    </div>
    <div class="page-actions mt-2 mt-md-0">
        <a href="{{ route('noc.config.detail', ['module' => $module, 'item_id' => $itemId, 'router_id' => $router->id]) }}" class="btn btn-outline-secondary px-3 py-2">
            <i class="fa-solid fa-arrow-left me-1"></i>Batal
        </a>
    </div>
</div>

@if(session('error'))
<div class="alert alert-danger d-flex align-items-center mb-4">
    <i class="fa-solid fa-circle-exclamation me-2"></i>
    <div><strong>Error:</strong> {{ session('error') }}</div>
</div>
@endif

@if($error)
<div class="alert alert-danger d-flex align-items-center mb-4">
    <i class="fa-solid fa-circle-exclamation me-2"></i>
    <div><strong>Error:</strong> {{ $error }}</div>
</div>
@endif

@if($item)
<div class="card shadow-sm border-0">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold"><i class="fa-solid fa-pen-to-square me-2"></i>Edit Item</h6>
        <span class="badge bg-secondary" style="font-size:0.72rem;"><code>{{ $itemId }}</code></span>
    </div>
    <div class="card-body">
        <form action="{{ route('noc.config.update', ['module' => $module]) }}" method="POST">
            @csrf
            <input type="hidden" name="router_id" value="{{ $router->id }}">
            <input type="hidden" name="item_id" value="{{ $itemId }}">

            @if(empty($fields))
            <div class="alert alert-info mb-0">
                <i class="fa-solid fa-circle-info me-2"></i>
                Module ini menggunakan field default dari RouterOS.
            </div>
            @else
            @foreach($fields as $field)
            <div class="mb-3">
                <label for="field_{{ $field['name'] }}" class="form-label fw-semibold" style="font-size:0.82rem;">
                    {{ $field['label'] }}
                    @if($field['required'] ?? false)<span class="text-danger">*</span>@endif
                </label>

                @php $currentValue = $item[$field['name']] ?? ''; @endphp

                @if(($field['type'] ?? 'text') === 'select')
                <select name="{{ $field['name'] }}" id="field_{{ $field['name'] }}"
                    class="form-select form-select-sm @error($field['name']) is-invalid @enderror"
                    {{ ($field['required'] ?? false) ? 'required' : '' }}>
                    @if(!($field['required'] ?? false))
                    <option value="">-- Pilih --</option>
                    @endif
                    @foreach($field['options'] as $val => $label)
                    <option value="{{ $val }}" {{ old($field['name'], $currentValue) == $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>

                @elseif(($field['type'] ?? 'text') === 'textarea')
                <textarea name="{{ $field['name'] }}" id="field_{{ $field['name'] }}"
                    class="form-control form-control-sm @error($field['name']) is-invalid @enderror"
                    rows="4"
                    placeholder="{{ $field['placeholder'] ?? '' }}"
                    {{ ($field['required'] ?? false) ? 'required' : '' }}>{{ old($field['name'], $currentValue) }}</textarea>

                @else
                <input type="{{ $field['type'] ?? 'text' }}" name="{{ $field['name'] }}" id="field_{{ $field['name'] }}"
                    class="form-control form-control-sm @error($field['name']) is-invalid @enderror"
                    value="{{ old($field['name'], $currentValue) }}"
                    placeholder="{{ $field['placeholder'] ?? '' }}"
                    {{ ($field['required'] ?? false) ? 'required' : '' }}>
                @endif

                @error($field['name'])
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            @endforeach

            <hr>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4 py-2">
                    <i class="fa-solid fa-save me-1"></i>Update Item
                </button>
                <a href="{{ route('noc.config.detail', ['module' => $module, 'item_id' => $itemId, 'router_id' => $router->id]) }}" class="btn btn-outline-secondary px-3 py-2">Batal</a>
            </div>
            @endif
        </form>
    </div>
</div>

{{-- ═══ CURRENT VALUES (read-only reference) --}}
@if(!empty($fields))
<div class="card shadow-sm border-0 mt-4">
    <div class="card-header bg-transparent" data-bs-toggle="collapse" data-bs-target="#currentValues" role="button" style="cursor:pointer;">
        <h6 class="mb-0 fw-bold"><i class="fa-solid fa-table me-2"></i>Current Router Values <i class="fa-solid fa-chevron-down float-end" style="font-size:0.7rem;"></i></h6>
    </div>
    <div class="collapse" id="currentValues">
        <div class="card-body">
            <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
                
                        <tr>
                            <th style="font-size:0.75rem;width:35%;">Field</th>
                            <th style="font-size:0.75rem;">Value</th>
                        </tr>

                    <tbody>
                        @foreach($item as $key => $value)
                        <tr>
                            <td><code class="text-muted" style="font-size:0.78rem;">{{ $key }}</code></td>
                            <td style="font-size:0.85rem;">
                                @if(is_array($value))
                                    <code style="font-size:0.75rem;">{{ json_encode($value) }}</code>
                                @else
                                    {{ $value }}
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endif
@else
<div class="text-center py-5">
    <i class="fa-solid fa-circle-exclamation d-block mb-3" style="font-size:3rem;opacity:0.2;"></i>
    <h5 class="text-muted">Item tidak ditemukan</h5>
    <a href="{{ route('noc.config.module', ['module' => $module]) }}?router_id={{ $router->id }}" class="btn btn-primary">Kembali ke daftar</a>
</div>
@endif
@endsection

