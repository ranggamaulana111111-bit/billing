@php
    $routers = App\Models\MikrotikRouter::where('is_active', true)->orderBy('type')->orderBy('name')->get();
    $currentRouterId = request('router');
    $currentRouter = $routers->firstWhere('id', $currentRouterId);
    $typeLabels = ['pppoe' => 'PPPoE', 'bandwidth' => 'Bandwidth', 'general' => 'General'];
    $typeColors = ['pppoe' => '#2563eb', 'bandwidth' => '#dc2626', 'general' => '#475569'];
    $typeBgs = ['pppoe' => '#eff6ff', 'bandwidth' => '#fef2f2', 'general' => '#f1f5f9'];
@endphp

<div class="d-flex flex-wrap align-items-center gap-2 mb-3 pb-2 border-bottom">
    <span class="text-muted small fw-semibold me-1">Router:</span>
    <a href="{{ url()->current() }}"
       class="btn btn-sm rounded-pill px-3 {{ !$currentRouterId ? 'btn-primary' : 'btn-outline-secondary' }}">
        <i class="fa-solid fa-server me-1"></i>Semua
    </a>
    @forelse ($routers as $router)
        @php $isActive = $currentRouterId && $currentRouterId == $router->id; @endphp
        <a href="{{ url()->current() }}?router={{ $router->id }}"
           class="btn btn-sm rounded-pill px-3 {{ $isActive ? 'btn-primary' : 'btn-outline-secondary' }}"
           style="{{ $isActive ? '' : 'border-color:' . ($typeColors[$router->type] ?? '#ccc') . ';color:' . ($typeColors[$router->type] ?? '#333') }}">
            <i class="fa-solid fa-router me-1"></i>{{ $router->name }}
            <small class="ms-1 opacity-75">({{ $typeLabels[$router->type] ?? 'General' }})</small>
        </a>
    @empty
        <span class="btn btn-sm rounded-pill px-3 btn-primary">
            <i class="fa-solid fa-server me-1"></i>Semua (default)
        </span>
        <a href="{{ route('mikrotik-routers.index') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">
            <i class="fa-solid fa-plus me-1"></i>Kelola Router
        </a>
    @endforelse
</div>
