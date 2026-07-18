@extends('layouts.app')

@section('title', 'Tambah '.$moduleDef['label'].' — Configuration Center')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.78rem;">
                <li class="breadcrumb-item"><a href="{{ route('noc.config.modules') }}">Config Center</a></li>
                <li class="breadcrumb-item"><a href="{{ route('noc.config.module', ['module' => $module]) }}?router_id={{ $router->id }}">{{ $moduleDef['label'] }}</a></li>
                <li class="breadcrumb-item active">Tambah Baru</li>
            </ol>
        </nav>
        <h2 class="mb-0">
            <i class="fa-solid fa-plus-circle me-2" style="color:var(--primary);"></i>Tambah {{ $moduleDef['label'] }}
        </h2>
        <p class="section-subtitle mb-0 mt-1">
            {{ $router->display_identity }} — <code style="font-size:0.78rem;">{{ $router->host }}</code>
        </p>
    </div>
    <div class="page-actions mt-2 mt-md-0">
        <a href="{{ route('noc.config.module', ['module' => $module]) }}?router_id={{ $router->id }}" class="btn btn-outline-secondary px-3 py-2">
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

<div class="card shadow-sm border-0">
    <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-bold"><i class="fa-solid fa-plus me-2"></i>New {{ $moduleDef['label'] }} Item</h6>
    </div>
    <div class="card-body">
        <form action="{{ route('noc.config.store', ['module' => $module]) }}" method="POST">
            @csrf
            <input type="hidden" name="router_id" value="{{ $router->id }}">

            @if(empty($fields))
            <div class="alert alert-info mb-0">
                <i class="fa-solid fa-circle-info me-2"></i>
                Module ini tidak memerlukan field input (singleton atau read-only fields). RouterOS akan membuat item dengan default values.
            </div>
            @else
            @foreach($fields as $field)
            <div class="mb-3">
                <label for="field_{{ $field['name'] }}" class="form-label fw-semibold" style="font-size:0.82rem;">
                    {{ $field['label'] }}
                    @if($field['required'] ?? false)<span class="text-danger">*</span>@endif
                </label>

                @if(($field['type'] ?? 'text') === 'select')
                <select name="{{ $field['name'] }}" id="field_{{ $field['name'] }}"
                    class="form-select form-select-sm @error($field['name']) is-invalid @enderror"
                    {{ ($field['required'] ?? false) ? 'required' : '' }}>
                    @if(!($field['required'] ?? false))
                    <option value="">-- Pilih --</option>
                    @endif
                    @foreach($field['options'] as $val => $label)
                    <option value="{{ $val }}" {{ old($field['name']) == $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>

                @elseif(($field['type'] ?? 'text') === 'textarea')
                <textarea name="{{ $field['name'] }}" id="field_{{ $field['name'] }}"
                    class="form-control form-control-sm @error($field['name']) is-invalid @enderror"
                    rows="4"
                    placeholder="{{ $field['placeholder'] ?? '' }}"
                    {{ ($field['required'] ?? false) ? 'required' : '' }}>{{ old($field['name']) }}</textarea>

                @else
                <input type="{{ $field['type'] ?? 'text' }}" name="{{ $field['name'] }}" id="field_{{ $field['name'] }}"
                    class="form-control form-control-sm @error($field['name']) is-invalid @enderror"
                    value="{{ old($field['name']) }}"
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
                    <i class="fa-solid fa-save me-1"></i>Buat Item
                </button>
                <a href="{{ route('noc.config.module', ['module' => $module]) }}?router_id={{ $router->id }}" class="btn btn-outline-secondary px-3 py-2">Batal</a>
            </div>
            @endif
        </form>
    </div>
</div>
@endsection
