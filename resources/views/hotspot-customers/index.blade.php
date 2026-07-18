@extends('layouts.app')
@section('title', 'Pelanggan Hotspot')
@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-user-plus me-2" style="color:#f59e0b;"></i>Pelanggan Hotspot</h2>
        <p class="section-subtitle mb-0 mt-1">Scan ONU dari OLT, lalu daftarkan sebagai pelanggan hotspot</p>
    </div>
</div>
@if(session('success'))
    <div class="alert alert-custom alert-success mb-4">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-custom alert-danger mb-4">{{ session('error') }}</div>
@endif
{{-- STEP 1: Scan OLT --}}
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white d-flex align-items-center gap-2">
        <div style="width:8px;height:8px;border-radius:50%;background:#2563eb;"></div>
        <span class="fw-semibold" style="font-size:0.85rem;">Langkah 1 — Scan ONU dari OLT</span>
    </div>
    <div class="card-body">
        @if($oltList->isEmpty())
            <div class="text-center py-3 text-muted">
                <i class="fa-solid fa-server me-1"></i> Tidak ada OLT aktif. Tambahkan OLT terlebih dahulu.
            </div>
        @else
            <p class="text-muted mb-3" style="font-size:0.82rem;">Klik "Scan" pada OLT untuk memperbarui daftar ONU yang belum ter-link ke pelanggan.</p>
            <div class="d-flex flex-wrap gap-2">
                @foreach($oltList as $olt)
                    <form method="POST" action="{{ route('hotspot-customers.scan', $olt) }}" onsubmit="return confirm('Scan ONU dari {{ $olt->name }}?')">
                        @csrf
                        <button type="submit" class="btn btn-outline-primary btn-sm px-3">
                            <i class="fa-solid fa-rotate me-1"></i>Scan {{ $olt->name }}
                        </button>
                        <span class="text-muted ms-1" style="font-size:0.72rem;">{{ $olt->ip_address }}</span>
                    </form>
                @endforeach
            </div>
        @endif
    </div>
</div>
{{-- STEP 2: Daftar ONU --}}
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <div style="width:8px;height:8px;border-radius:50%;background:#f59e0b;"></div>
            <span class="fw-semibold" style="font-size:0.85rem;">Langkah 2 — Pilih ONU untuk Didftarkan</span>
            <span class="badge" style="background:#fef3c7;color:#d97706;font-size:0.68rem;">{{ $stats['unlinked'] }} ONU belum ter-link</span>
        </div>
        <div class="d-flex gap-3" style="font-size:0.75rem;">
            <span class="text-success"><i class="fa-solid fa-circle me-1" style="font-size:0.4rem;vertical-align:middle;"></i>{{ $stats['online'] }} Online</span>
            <span class="text-danger"><i class="fa-solid fa-circle me-1" style="font-size:0.4rem;vertical-align:middle;"></i>{{ $stats['offline'] }} Offline</span>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
                    <tr>
                        <th>OLT / Port</th>
                        <th>Serial Number</th>
                        <th>Caller ID / MAC</th>
                        <th>Vendor / Model</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">RX Power</th>
                        <th class="text-center">Terakhir Seen</th>
                        <th class="text-center">Aksi</th>
                    </tr>

                <tbody>
                    @forelse($onus as $onu)
                        <tr>
                            <td>
                                <span class="fw-semibold">{{ $onu->oltPort?->olt?->name ?? '—' }}</span>
                                <div style="font-size:0.7rem;color:#64748b;">Slot {{ $onu->slot_number }} / Port {{ $onu->port_number }}</div>
                            </td>
                            <td><code style="font-size:0.75rem;">{{ $onu->serial_number ?? '—' }}</code></td>
                            <td style="font-size:0.8rem;">{{ $onu->caller_id ?? '—' }}</td>
                            <td style="font-size:0.8rem;">{{ $onu->vendor ?? '' }} {{ $onu->model ?? '' }}</td>
                            <td class="text-center">
                                @if($onu->status === 'online')
                                    <span class="badge" style="background:#f0fdf4;color:#059669;"><i class="fa-solid fa-circle me-1" style="font-size:0.4rem;vertical-align:middle;"></i>Online</span>
                                @elseif($onu->status === 'offline')
                                    <span class="badge" style="background:#fef2f2;color:#dc2626;"><i class="fa-solid fa-circle me-1" style="font-size:0.4rem;vertical-align:middle;"></i>Offline</span>
                                @else
                                    <span class="badge" style="background:#f1f5f9;color:#64748b;">{{ ucfirst($onu->status) }}</span>
                                @endif
                            </td>
                            <td class="text-center" style="font-size:0.78rem;">
                                @if($onu->rx_power)
                                    <span style="color:{{ $onu->rx_power > -27 ? '#059669' : ($onu->rx_power > -30 ? '#d97706' : '#dc2626') }};">{{ number_format($onu->rx_power, 1) }} dBm</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-center" style="font-size:0.78rem;">{{ $onu->last_seen_at?->diffForHumans() ?? '—' }}</td>
                            <td class="text-center">
                                <a href="{{ route('hotspot-customers.create', ['onu_id' => $onu->id]) }}" class="btn btn-sm btn-warning px-3">
                                    <i class="fa-solid fa-user-plus me-1"></i>Daftarkan
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-tower-cell" style="font-size:1.5rem;display:block;margin-bottom:8px;"></i>
                                Belum ada ONU yang belum ter-link. Klik "Scan" di atas untuk memperbarui data dari OLT.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
