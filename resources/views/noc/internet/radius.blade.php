@extends('layouts.app')

@section('title', 'Radius Preparation — Internet Service Center')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.78rem;">
                <li class="breadcrumb-item"><a href="{{ route('noc.internet.dashboard') }}">Internet Service Center</a></li>
                <li class="breadcrumb-item active">Radius Preparation</li>
            </ol>
        </nav>
        <h2 class="mb-0">
            <i class="fa-solid fa-tower-broadcast me-2" style="color:var(--primary);"></i>Radius Preparation
        </h2>
    </div>
    <div class="page-actions mt-2 mt-md-0">
        <a href="{{ route('noc.internet.dashboard') }}" class="btn btn-outline-secondary px-3 py-2">
            <i class="fa-solid fa-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="row g-4">
    {{-- ═══ OVERVIEW ═══ --}}
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-transparent">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-circle-info me-2"></i>Radius Integration Status</h6>
            </div>
            <div class="card-body">
                <div class="alert alert-info mb-4">
                    <i class="fa-solid fa-circle-info me-2"></i>
                    <strong>Architecture:</strong> FreeRADIUS/MikroTik User Manager handles PPPoE & Hotspot authentication. RouterOS acts as NAS (Network Access Server), forwarding auth requests to the RADIUS server. Billing system controls user enable/disable via RADIUS attributes.
                </div>

                <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
                    
                            <tr>
                                <th style="font-size:0.75rem;">Component</th>
                                <th style="font-size:0.75rem;">Status</th>
                                <th style="font-size:0.75rem;">Description</th>
                            </tr>

                        <tbody>
                            <tr>
                                <td style="font-size:0.85rem;"><i class="fa-solid fa-server me-2"></i>FreeRADIUS 3.x</td>
                                <td><span class="badge bg-secondary">Planned</span></td>
                                <td style="font-size:0.82rem;">RADIUS server for PPPoE/Hotspot authentication, accounting, and authorization</td>
                            </tr>
                            <tr>
                                <td style="font-size:0.85rem;"><i class="fa-solid fa-database me-2"></i>MikroTik User Manager</td>
                                <td><span class="badge bg-secondary">Planned</span></td>
                                <td style="font-size:0.82rem;">Alternative: MikroTik built-in RADIUS with User Manager package</td>
                            </tr>
                            <tr>
                                <td style="font-size:0.85rem;"><i class="fa-solid fa-plug me-2"></i>RouterOS NAS Config</td>
                                <td><span class="badge bg-info">Configurable</span></td>
                                <td style="font-size:0.82rem;">PPP/Hotspot RADIUS settings via Internet Service Center → MikroTik Center</td>
                            </tr>
                            <tr>
                                <td style="font-size:0.85rem;"><i class="fa-solid fa-users me-2"></i>PPP/Hotspot Secrets</td>
                                <td><span class="badge bg-success">Ready</span></td>
                                <td style="font-size:0.82rem;">Customer credentials managed via Internet Service Center, RADIUS-syncable</td>
                            </tr>
                            <tr>
                                <td style="font-size:0.85rem;"><i class="fa-solid fa-receipt me-2"></i>Billing Auth Link</td>
                                <td><span class="badge bg-secondary">Planned</span></td>
                                <td style="font-size:0.82rem;">Link billing payment status → RADIUS enable/disable (Active/Isolir/Suspended)</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ═══ ARCHITECTURE DIAGRAM ═══ --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-transparent">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-diagram-project me-2"></i>Integration Architecture</h6>
            </div>
            <div class="card-body">
                <div class="row g-3 text-center" style="font-size:0.82rem;">
                    <div class="col-md-3">
                        <div class="card border-2 border-primary h-100">
                            <div class="card-body py-3">
                                <i class="fa-solid fa-server fa-2x text-primary mb-2"></i>
                                <div class="fw-bold">MikroTik Router</div>
                                <small class="text-muted">NAS (AAA Client)<br>PPPoE / Hotspot Server</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-1 d-flex align-items-center justify-content-center">
                        <i class="fa-solid fa-arrows-left-right fa-lg text-muted"></i>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-2 border-warning h-100">
                            <div class="card-body py-3">
                                <i class="fa-solid fa-tower-broadcast fa-2x text-warning mb-2"></i>
                                <div class="fw-bold">FreeRADIUS / UM</div>
                                <small class="text-muted">Auth, Acct, CoA<br>User Management</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-1 d-flex align-items-center justify-content-center">
                        <i class="fa-solid fa-arrows-left-right fa-lg text-muted"></i>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-2 border-success h-100">
                            <div class="card-body py-3">
                                <i class="fa-solid fa-database fa-2x text-success mb-2"></i>
                                <div class="fw-bold">ALKONEK Billing</div>
                                <small class="text-muted">MySQL DB<br>Users, Profiles, Payment</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══ SAMPLE CONFIG ═══ --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-transparent">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-code me-2"></i>Sample RouterOS RADIUS Config</h6>
            </div>
            <div class="card-body">
                <pre class="bg-dark text-light p-3 rounded" style="font-size:0.78rem;overflow-x:auto;"><code>/radius
add address=RADIUS_SERVER_IP secret=YOUR_SHARED_SECRET service=pppoe \
    authentication-port=1812 accounting-port=1813

/ppp aaa
set use-radius=yes accounting=yes interim-update=5m</code></pre>
                <small class="text-muted d-block mt-2"><i class="fa-solid fa-triangle-exclamation me-1"></i>Replace RADIUS_SERVER_IP and YOUR_SHARED_SECRET with actual values.</small>
            </div>
        </div>
    </div>

    {{-- ═══ INTEGRATION POINTS ═══ --}}
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-transparent">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-plug me-2"></i>Integration Points</h6>
            </div>
            <div class="card-body" style="font-size:0.82rem;">
                <div class="d-flex align-items-start mb-3">
                    <div class="rounded-circle bg-primary bg-opacity-10 p-2 me-3">
                        <i class="fa-solid fa-check text-primary" style="font-size:0.7rem;"></i>
                    </div>
                    <div>
                        <strong>RouterOS API</strong>
                        <div class="text-muted">All RouterOS commands via unified API Service</div>
                    </div>
                </div>
                <div class="d-flex align-items-start mb-3">
                    <div class="rounded-circle bg-primary bg-opacity-10 p-2 me-3">
                        <i class="fa-solid fa-check text-primary" style="font-size:0.7rem;"></i>
                    </div>
                    <div>
                        <strong>PPP Secrets</strong>
                        <div class="text-muted">Customer credentials ready for RADIUS sync</div>
                    </div>
                </div>
                <div class="d-flex align-items-start mb-3">
                    <div class="rounded-circle bg-primary bg-opacity-10 p-2 me-3">
                        <i class="fa-solid fa-check text-primary" style="font-size:0.7rem;"></i>
                    </div>
                    <div>
                        <strong>Hotspot Users</strong>
                        <div class="text-muted">Voucher/user accounts ready for RADIUS integration</div>
                    </div>
                </div>
                <div class="d-flex align-items-start mb-3">
                    <div class="rounded-circle bg-warning bg-opacity-10 p-2 me-3">
                        <i class="fa-solid fa-clock text-warning" style="font-size:0.7rem;"></i>
                    </div>
                    <div>
                        <strong>FreeRADIUS Server</strong>
                        <div class="text-muted">Server setup and configuration pending</div>
                    </div>
                </div>
                <div class="d-flex align-items-start">
                    <div class="rounded-circle bg-warning bg-opacity-10 p-2 me-3">
                        <i class="fa-solid fa-clock text-warning" style="font-size:0.7rem;"></i>
                    </div>
                    <div>
                        <strong>Billing Auth Link</strong>
                        <div class="text-muted">Link billing payment status to RADIUS enable/disable</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-transparent">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-link me-2"></i>Related Modules</h6>
            </div>
            <div class="card-body" style="font-size:0.82rem;">
                <a href="{{ route('noc.internet.pppsecret', ['router_id' => request('router_id')]) }}" class="d-flex align-items-center mb-2 text-decoration-none">
                    <i class="fa-solid fa-key me-2 text-primary"></i> PPP Secret Manager
                </a>
                <a href="{{ route('noc.internet.pppprofile', ['router_id' => request('router_id')]) }}" class="d-flex align-items-center mb-2 text-decoration-none">
                    <i class="fa-solid fa-id-badge me-2 text-primary"></i> PPP Profile Manager
                </a>
                <a href="{{ route('noc.internet.hotspotuser', ['router_id' => request('router_id')]) }}" class="d-flex align-items-center mb-2 text-decoration-none">
                    <i class="fa-solid fa-users me-2 text-primary"></i> Hotspot User Manager
                </a>
                <a href="{{ route('noc.internet.active', ['router_id' => request('router_id')]) }}" class="d-flex align-items-center text-decoration-none">
                    <i class="fa-solid fa-bolt me-2 text-primary"></i> Active Sessions
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

