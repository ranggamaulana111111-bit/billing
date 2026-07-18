@extends('layouts.app')
@section('title', "Incident #{$incident->id}")
@section('content')
<div class="p-4">
    <div class="mb-4">
        <a href="{{ route('incidents.index') }}" class="text-muted text-decoration-none" style="font-size:0.85rem;">
            <i class="fa-solid fa-arrow-left me-1"></i>Kembali ke Daftar Incident
        </a>
    </div>
    @if(session('success'))
        <div class="alert alert-success py-2 small" style="border-radius:10px;">{{ session('success') }}</div>
    @endif
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4" style="border-radius:16px;">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h4 class="fw-bold mb-1">#{{ $incident->id }} — {{ $incident->title }}</h4>
                            <small class="text-muted">
                                Dibuat {{ $incident->detected_at?->diffForHumans() }} oleh {{ $incident->creator?->name }}
                                &middot; {{ $incident->type === 'auto' ? 'Auto-detect OLT' : 'Manual' }}
                            </small>
                        </div>
                        @php
                            $statusClass = match($incident->status) {
                                'open' => 'bg-primary',
                                'investigating' => 'bg-info',
                                'resolved' => 'bg-success',
                                'closed' => 'bg-secondary',
                                default => 'bg-secondary',
                            };
                        @endphp
                        <span class="badge {{ $statusClass }} fs-6" style="border-radius:10px;">{{ ucfirst($incident->status) }}</span>
                    </div>
                    @if($incident->description)
                        <p class="text-muted mb-0">{{ $incident->description }}</p>
                    @endif
                </div>
            </div>
            <div class="card border-0 shadow-sm mb-4" style="border-radius:16px;">
                <div class="card-header bg-white border-0 py-3" style="border-radius:16px 16px 0 0;">
                    <h6 class="fw-bold mb-0">Timeline</h6>
                </div>
                <div class="card-body p-4">
                    @forelse($timeline as $event)
                        <div class="d-flex gap-3 {{ !$loop->last ? 'mb-4' : '' }}">
                            <div>
                                <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:36px;height:36px;background:{{ $event['color'] === 'primary' ? '#dbeafe' : ($event['color'] === 'info' ? '#cffafe' : ($event['color'] === 'success' ? '#dcfce7' : '#f3f4f6')) }};">
                                    <i class="{{ $event['icon'] }}" style="color:{{ $event['color'] === 'primary' ? '#2563eb' : ($event['color'] === 'info' ? '#0891b2' : ($event['color'] === 'success' ? '#16a34a' : '#6b7280')) }};font-size:0.85rem;"></i>
                                </div>
                            </div>
                            <div class="flex-fill">
                                <div class="fw-semibold">{{ $event['title'] }}</div>
                                <small class="text-muted">{{ $event['time']?->format('d/m/Y H:i') }}</small>
                                @if($event['detail'])
                                    <div class="text-muted small mt-1">{{ $event['detail'] }}</div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-muted text-center py-3">Belum ada timeline</div>
                    @endforelse
                </div>
            </div>
            <div class="card border-0 shadow-sm" style="border-radius:16px;">
                <div class="card-header bg-white border-0 py-3" style="border-radius:16px 16px 0 0;">
                    <h6 class="fw-bold mb-0">Log Notifikasi ({{ $incident->notifications->count() }})</h6>
                </div>
                <div class="card-body p-0">
                    @if($incident->notifications->isEmpty())
                        <div class="text-muted text-center py-4 small">Belum ada notifikasi terkirim</div>
                    @else
                        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
                                    <tr>
                                        <th class="border-0 px-3" style="font-size:0.8rem;">Penerima</th>
                                        <th class="border-0" style="font-size:0.8rem;">Tipe</th>
                                        <th class="border-0" style="font-size:0.8rem;">Jenis</th>
                                        <th class="border-0" style="font-size:0.8rem;">Status</th>
                                        <th class="border-0 px-3" style="font-size:0.8rem;">Waktu</th>
                                    </tr>

                                <tbody>
                                    @foreach($incident->notifications as $notif)
                                    <tr>
                                        <td class="px-3">
                                            <div class="fw-semibold small">{{ $notif->recipient_name }}</div>
                                            <small class="text-muted">{{ $notif->recipient_phone }}</small>
                                        </td>
                                        <td>
                                            <span class="badge {{ $notif->recipient_type === 'technician' ? 'bg-info' : 'bg-success' }}" style="border-radius:6px;">
                                                {{ $notif->recipient_type === 'technician' ? 'Teknisi' : 'Pelanggan' }}
                                            </span>
                                        </td>
                                        <td><small>{{ ucfirst(str_replace('_', ' ', $notif->notification_type)) }}</small></td>
                                        <td>
                                            @if($notif->status === 'sent')
                                                <span class="badge bg-success" style="border-radius:6px;">Terkirim</span>
                                            @elseif($notif->status === 'failed')
                                                <span class="badge bg-danger" style="border-radius:6px;">Gagal</span>
                                            @else
                                                <span class="badge bg-secondary" style="border-radius:6px;">Pending</span>
                                            @endif
                                        </td>
                                        <td class="px-3"><small class="text-muted">{{ $notif->sent_at?->diffForHumans() ?? '-' }}</small></td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4" style="border-radius:16px;">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3">Info Gangguan</h6>
                        <tr>
                            <td class="text-muted" style="width:40%;">Severity</td>
                            <td class="fw-semibold">
                                @php
                                    $sevClass = match($incident->severity) {
                                        'critical' => 'bg-danger',
                                        'high' => 'bg-warning text-dark',
                                        'medium' => 'bg-info',
                                        'low' => 'bg-secondary',
                                        default => 'bg-secondary',
                                    };
                                @endphp
                                <span class="badge {{ $sevClass }}" style="border-radius:6px;">{{ ucfirst($incident->severity) }}</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">ODP</td>
                            <td class="fw-semibold">{{ $incident->odp?->nama_odp ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">ODC</td>
                            <td class="fw-semibold">{{ $incident->odc?->nama_odc ?? $incident->odp?->odc?->nama_odc ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Ditangani</td>
                            <td>{{ $incident->assignee?->name ?? 'Belum ditugaskan' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Dibuat</td>
                            <td>{{ $incident->detected_at?->format('d/m/Y H:i') }}</td>
                        </tr>
                        @if($incident->sla_deadline)
                        <tr>
                            <td class="text-muted">SLA Deadline</td>
                            <td class="{{ $incident->sla_status === 'breached' ? 'text-danger fw-bold' : '' }}">
                                {{ $incident->sla_deadline->format('d/m/Y H:i') }}
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">SLA Status</td>
                            <td>
                                @if($incident->sla_status === 'breached')
                                    <span class="badge bg-danger" style="border-radius:6px;">BREACHED</span>
                                @elseif($incident->sla_status === 'met')
                                    <span class="badge bg-success" style="border-radius:6px;">MET</span>
                                @elseif(in_array($incident->status, ['open', 'investigating']))
                                    <small class="text-muted">{{ $incident->sla_remaining }}</small>
                                @else
                                    <small class="text-muted">-</small>
                                @endif
                            </td>
                        </tr>
                        @endif
                        @if(in_array($incident->status, ['open', 'investigating']) && $incident->sla_deadline)
                        <tr>
                            <td class="text-muted">Progress</td>
                            <td>
                                @php $progress = $incident->sla_progress; @endphp
                                <div class="progress" style="height:8px;border-radius:4px;">
                                    <div class="progress-bar {{ $progress > 80 ? 'bg-danger' : ($progress > 50 ? 'bg-warning' : 'bg-success') }}"
                                         style="width:{{ $progress }}%;border-radius:4px;"></div>
                                </div>
                                <small class="text-muted">{{ $progress }}%</small>
                            </td>
                        </tr>
                        @endif
<table class="table table-hover align-middle mb-0 mon-table">
                    </table>
                </div>
            </div>
            @if(in_array($incident->status, ['open', 'investigating']))
            <div class="card border-0 shadow-sm mb-4" style="border-radius:16px;">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3">Aksi</h6>
                    <div class="d-grid gap-2">
                        @if($incident->status === 'open')
                        <form method="POST" action="{{ route('incidents.investigating', $incident) }}">
                            @csrf
                            <button type="submit" class="btn btn-info text-white w-100" style="border-radius:10px;">
                                <i class="fa-solid fa-magnifying-glass me-1"></i>Mulai Investigasi
                            </button>
                        </form>
                        @endif
                        <form method="POST" action="{{ route('incidents.resolve', $incident) }}">
                            @csrf
                            <button type="submit" class="btn btn-success w-100" style="border-radius:10px;">
                                <i class="fa-solid fa-check me-1"></i>Selesaikan Incident
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            @endif
            @if($incident->status === 'resolved')
            <div class="card border-0 shadow-sm mb-4" style="border-radius:16px;">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3">Tutup Incident</h6>
                    <form method="POST" action="{{ route('incidents.close', $incident) }}">
                        @csrf
                        <button type="submit" class="btn btn-secondary w-100" style="border-radius:10px;">
                            <i class="fa-solid fa-lock me-1"></i>Tutup Incident
                        </button>
                    </form>
                </div>
            </div>
            @endif
            <div class="card border-0 shadow-sm" style="border-radius:16px;">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3">Edit Detail</h6>
                    <form method="POST" action="{{ route('incidents.update', $incident) }}">
                        @csrf
                        @method('PUT')
                        <div class="mb-2">
                            <input type="text" name="title" class="form-control form-control-sm" value="{{ $incident->title }}"
                                   style="border-radius:8px;border:2px solid #e2e8f0;">
                        </div>
                        <div class="mb-2">
                            <textarea name="description" class="form-control form-control-sm" rows="2"
                                      style="border-radius:8px;border:2px solid #e2e8f0;">{{ $incident->description }}</textarea>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <select name="severity" class="form-select form-select-sm" style="border-radius:8px;">
                                    @foreach(['low'=>'Low','medium'=>'Medium','high'=>'High','critical'=>'Critical'] as $val => $label)
                                        <option value="{{ $val }}" {{ $incident->severity === $val ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6">
                                <select name="odp_id" class="form-select form-select-sm" style="border-radius:8px;">
                                    <option value="">Tanpa ODP</option>
                                    @foreach(\App\Models\Odp::orderBy('nama_odp')->get() as $odp)
                                        <option value="{{ $odp->id }}" {{ $incident->odp_id == $odp->id ? 'selected' : '' }}>{{ $odp->nama_odp }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-outline-primary btn-sm w-100" style="border-radius:8px;">
                            <i class="fa-solid fa-save me-1"></i>Simpan Perubahan
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
