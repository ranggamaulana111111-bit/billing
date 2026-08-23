@extends('layouts.app')
@section('title', 'Kelola Akun')
@section('content')
{{-- ═══════════════════════════════════════════════
     MODE 1: USER LIST
     ═══════════════════════════════════════════════ --}}
<div id="listMode">
    <div class="uc-list-header">
        <div>
            <h2><i class="fa-solid fa-user-shield"></i>Kelola Akun</h2>
            <p class="uc-list-subtitle">Manajemen akun administrator & teknisi dalam sistem</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('settings.index') }}" class="btn btn-outline-secondary px-3 py-2">
                <i class="fa-solid fa-gear me-1"></i>Pengaturan
            </a>
            <button type="button" class="btn btn-primary px-4 py-2" onclick="showCreateMode()">
                <i class="fa-solid fa-user-plus me-1"></i>Tambah Akun
            </button>
        </div>
    </div>
    @if(session('success'))
        <div class="alert alert-custom alert-success mb-4">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-custom alert-danger mb-4">{{ session('error') }}</div>
    @endif
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="mon-table-wrap">
<table class="table table-hover align-middle mb-0 mon-table">
                        <tr>
                            <th>Pengguna</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Terdaftar</th>
                            <th class="text-center">Aksi</th>
                        </tr>

                    <tbody>
                        @forelse($users as $user)
                            @php
                                $initials = strtoupper(substr($user->name, 0, 1));
                                $colors = [
                                    'admin' => ['#2563eb','#6366f1'],
                                    'teknisi' => ['#059669','#10b981'],
                                    'noc' => ['#7c3aed','#a855f7'],
                                ];
                                $c = $colors[$user->role] ?? ['#64748b','#94a3b8'];
                            @endphp
                            <tr>
                                <td>
                                    <div class="uc-user-cell">
                                        <div class="uc-user-avatar" style="background:linear-gradient(135deg,{{$c[0]}},{{$c[1]}});">
                                            {{ $initials }}
                                        </div>
                                        <div>
                                            <div class="uc-user-name">
                                                {{ $user->name }}
                                                @if($user->id === auth()->id())
                                                    <span class="badge" style="background:#e0f2fe;color:#0284c7;font-size:9px;margin-left:4px;">Anda</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td style="color:var(--text-tertiary);font-size:0.82rem;">{{ $user->username }}</td>
                                <td>
                                    @if($user->role === 'admin')
                                        <span class="badge" style="background:linear-gradient(135deg,#fef3c7,#fff7e6);color:#d97706;font-weight:600;font-size:0.7rem;padding:4px 10px;border-radius:999px;border:1px solid rgba(217,119,6,0.12);">
                                            <i class="fa-solid fa-crown" style="font-size:0.6rem;margin-right:3px;"></i>Admin
                                        </span>
                                    @elseif($user->role === 'noc')
                                        <span class="badge" style="background:linear-gradient(135deg,#ede9fe,#f3e8ff);color:#7c3aed;font-weight:600;font-size:0.7rem;padding:4px 10px;border-radius:999px;border:1px solid rgba(124,58,237,0.12);">
                                            <i class="fa-solid fa-satellite-dish" style="font-size:0.6rem;margin-right:3px;"></i>NOC
                                        </span>
                                    @else
                                        <span class="badge" style="background:linear-gradient(135deg,#f0fdf4,#ecfdf5);color:#059669;font-weight:600;font-size:0.7rem;padding:4px 10px;border-radius:999px;border:1px solid rgba(5,150,105,0.12);">
                                            <i class="fa-solid fa-wrench" style="font-size:0.6rem;margin-right:3px;"></i>Teknisi
                                        </span>
                                    @endif
                                </td>
                                <td style="color:var(--text-muted);font-size:0.8rem;">{{ $user->created_at->format('d M Y') }}</td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-secondary px-2" data-bs-toggle="modal" data-bs-target="#detailModal{{ $user->id }}" title="Detail">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-primary px-2" data-bs-toggle="modal" data-bs-target="#editModal{{ $user->id }}">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    @if($user->id !== auth()->id())
                                        <form method="POST" action="{{ route('settings.users.destroy', $user) }}" class="d-inline" onsubmit="return confirm('Hapus akun {{ $user->name }}?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger px-2"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center py-4 text-muted">Belum ada akun</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
{{-- ═══════════════════════════════════════════════
     MODE 2: CREATE USER (Full-Page Form)
     ═══════════════════════════════════════════════ --}}
<div id="createMode" class="uc-hidden">
    <div class="uc-page">
        {{-- HERO HEADER --}}
        <div class="uc-hero">
            <div class="uc-breadcrumb">
                <a href="{{ route('dashboard') }}">Dashboard</a>
                <span class="uc-bc-sep"><i class="fa-solid fa-chevron-right"></i></span>
                <a href="{{ route('settings.users') }}">Pengguna</a>
                <span class="uc-bc-sep"><i class="fa-solid fa-chevron-right"></i></span>
                <span class="uc-bc-current">Tambah Pengguna</span>
            </div>
            <div class="uc-hero-row">
                <div class="uc-hero-text">
                    <h2>Tambah Pengguna Baru</h2>
                    <p>Tambahkan pelanggan, administrator, operator, maupun teknisi ke dalam sistem.</p>
                </div>
                <div class="uc-hero-badge">
                    <i class="fa-solid fa-shield-halved"></i>
                    Enterprise User Management
                </div>
            </div>
            {{-- STEP INDICATOR --}}
            <div class="uc-steps">
                <div class="uc-step active" data-step="1">
                    <span class="uc-step-num">1</span>
                    <span>Identitas</span>
                </div>
                <div class="uc-step-connector"></div>
                <div class="uc-step" data-step="2">
                    <span class="uc-step-num">2</span>
                    <span>Akun</span>
                </div>
                <div class="uc-step-connector"></div>
                <div class="uc-step" data-step="3">
                    <span class="uc-step-num">3</span>
                    <span>Hak Akses</span>
                </div>
                <div class="uc-step-connector"></div>
                <div class="uc-step" data-step="4">
                    <span class="uc-step-num">4</span>
                    <span>Review</span>
                </div>
            </div>
        </div>
        {{-- FORM --}}
        <form method="POST" action="{{ route('settings.users.store') }}" id="createUserForm">
            @csrf
            <div class="uc-form-grid">
                {{-- ─── LEFT: FORM ─── --}}
                <div class="uc-form-main">
                    {{-- CARD 1: INFORMASI PRIBADI --}}
                    <div class="uc-card" style="--card-accent:linear-gradient(90deg,var(--primary),#60a5fa);">
                        <div class="uc-card-header">
                            <div class="uc-card-icon uc-card-icon-blue"><i class="fa-solid fa-user"></i></div>
                            <div class="uc-card-title">
                                <h5>Informasi Pribadi</h5>
                                <small>Data identitas pengguna baru</small>
                            </div>
                        </div>
                        <div class="uc-card-body">
                            <div class="uc-field" data-step="1">
                                <label>
                                    Nama Lengkap <span class="uc-required">*</span>
                                </label>
                                <div class="uc-input-wrap">
                                    <i class="fa-solid fa-user uc-input-icon"></i>
                                    <input type="text" name="name" id="ucName" class="form-control @error('name') is-invalid @enderror"
                                           placeholder="Masukkan nama lengkap" value="{{ old('name') }}" required
                                           oninput="ucUpdatePreview()" onfocus="ucSetStep(1)">
                                </div>
                                <div class="uc-field-help">Masukkan nama lengkap sesuai identitas.</div>
                                @error('name') <div class="uc-field-msg msg-err"><i class="fa-solid fa-circle-exclamation"></i>{{ $message }}</div> @enderror
                            </div>
                            <div class="uc-field" data-step="1">
                                <label>
                                    Username <span class="uc-required">*</span>
                                </label>
                                <div class="uc-input-wrap">
                                    <i class="fa-solid fa-user uc-input-icon"></i>
                                    <input type="text" name="username" id="ucUsername" class="form-control @error('username') is-invalid @enderror"
                                           placeholder="username.unik" value="{{ old('username') }}" required
                                           oninput="ucUpdatePreview()" onfocus="ucSetStep(1)">
                                </div>
                                <div class="uc-field-help">Username untuk login ke sistem.</div>
                                @error('username') <div class="uc-field-msg msg-err"><i class="fa-solid fa-circle-exclamation"></i>{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                    {{-- CARD 2: KEAMANAN AKUN --}}
                    <div class="uc-card" style="--card-accent:linear-gradient(90deg,var(--success),#34d399);">
                        <div class="uc-card-header">
                            <div class="uc-card-icon uc-card-icon-green"><i class="fa-solid fa-lock"></i></div>
                            <div class="uc-card-title">
                                <h5>Keamanan Akun</h5>
                                <small>Pengaturan password & keamanan</small>
                            </div>
                        </div>
                        <div class="uc-card-body">
                            <div class="uc-field" data-step="2">
                                <label>
                                    Password <span class="uc-required">*</span>
                                </label>
                                <div class="uc-input-wrap">
                                    <i class="fa-solid fa-shield-halved uc-input-icon"></i>
                                    <input type="password" name="password" id="ucPassword" class="form-control @error('password') is-invalid @enderror"
                                           placeholder="Minimal 8 karakter" minlength="8" required
                                           oninput="ucCheckPassword()" onfocus="ucSetStep(2)">
                                    <button type="button" class="uc-pw-toggle" onclick="ucTogglePassword()" title="Tampilkan/Sembunyikan">
                                        <i class="fa-solid fa-eye" id="ucPwEye"></i>
                                    </button>
                                </div>
                                <div class="uc-pw-actions">
                                    <button type="button" class="uc-pw-btn" onclick="ucGeneratePassword()">
                                        <i class="fa-solid fa-wand-magic-sparkles"></i>Generate Password
                                    </button>
                                    <button type="button" class="uc-pw-btn" onclick="ucCopyPassword()" id="ucCopyBtn" style="display:none;">
                                        <i class="fa-solid fa-copy"></i>Salin
                                    </button>
                                </div>
                                {{-- Strength Meter --}}
                                <div class="uc-pw-strength" id="ucPwStrength" style="display:none;">
                                    <div class="uc-pw-bar"><div class="uc-pw-bar-fill" id="ucPwBar"></div></div>
                                    <div class="uc-pw-label" id="ucPwLabel">Kekuatan password</div>
                                </div>
                                <div class="uc-pw-checks" id="ucPwChecks">
                                    <span class="uc-pw-check" id="chkLen"><i class="fa-solid fa-check"></i>8+ karakter</span>
                                    <span class="uc-pw-check" id="chkUpper"><i class="fa-solid fa-check"></i>Huruf besar</span>
                                    <span class="uc-pw-check" id="chkLower"><i class="fa-solid fa-check"></i>Huruf kecil</span>
                                    <span class="uc-pw-check" id="chkNum"><i class="fa-solid fa-check"></i>Angka</span>
                                    <span class="uc-pw-check" id="chkSym"><i class="fa-solid fa-check"></i>Simbol</span>
                                </div>
                                @error('password') <div class="uc-field-msg msg-err"><i class="fa-solid fa-circle-exclamation"></i>{{ $message }}</div> @enderror
                            </div>
                            <div class="uc-field" data-step="2">
                                <label>
                                    Konfirmasi Password <span class="uc-required">*</span>
                                </label>
                                <div class="uc-input-wrap">
                                    <i class="fa-solid fa-lock uc-input-icon"></i>
                                    <input type="password" name="password_confirmation" id="ucPasswordConfirm" class="form-control"
                                           placeholder="Ulangi password" required
                                           oninput="ucCheckConfirm()" onfocus="ucSetStep(2)">
                                </div>
                                <div class="uc-pw-check" id="ucPwMatch" style="margin-top:6px;display:none;">
                                    <i class="fa-solid fa-check"></i>
                                    <span>Password cocok</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- CARD 3: HAK AKSES --}}
                    <div class="uc-card" style="--card-accent:linear-gradient(90deg,var(--accent),#a78bfa);">
                        <div class="uc-card-header">
                            <div class="uc-card-icon uc-card-icon-purple"><i class="fa-solid fa-user-shield"></i></div>
                            <div class="uc-card-title">
                                <h5>Hak Akses</h5>
                                <small>Tentukan peran pengguna dalam sistem</small>
                            </div>
                        </div>
                        <div class="uc-card-body">
                            <div class="uc-field" data-step="3">
                                <label>
                                    Pilih Role <span class="uc-required">*</span>
                                </label>
                                <div class="uc-role-grid" id="ucRoleGrid">
                                    <label class="uc-role-card {{ old('role','teknisi') === 'admin' ? 'selected' : '' }}" onclick="ucSelectRole(this,'admin')">
                                        <input type="radio" name="role" value="admin" {{ old('role') === 'admin' ? 'checked' : '' }}>
                                        <div class="uc-role-icon" style="background:linear-gradient(135deg,#d97706,#fbbf24);">
                                            <i class="fa-solid fa-crown"></i>
                                        </div>
                                        <div class="uc-role-info">
                                            <h6>Administrator</h6>
                                            <p>Memiliki akses penuh ke seluruh fitur sistem.</p>
                                        </div>
                                    </label>
                                    <label class="uc-role-card {{ old('role','teknisi') === 'teknisi' ? 'selected' : (empty(old('role')) ? 'selected' : '') }}" onclick="ucSelectRole(this,'teknisi')">
                                        <input type="radio" name="role" value="teknisi" {{ old('role','teknisi') === 'teknisi' ? 'checked' : '' }}>
                                        <div class="uc-role-icon" style="background:linear-gradient(135deg,#059669,#34d399);">
                                            <i class="fa-solid fa-wrench"></i>
                                        </div>
                                        <div class="uc-role-info">
                                            <h6>Teknisi</h6>
                                            <p>Mengelola jaringan, OLT, dan perangkat MikroTik.</p>
                                        </div>
                                    </label>
                                    <label class="uc-role-card {{ old('role') === 'noc' ? 'selected' : '' }}" onclick="ucSelectRole(this,'noc')">
                                        <input type="radio" name="role" value="noc" {{ old('role') === 'noc' ? 'checked' : '' }}>
                                        <div class="uc-role-icon" style="background:linear-gradient(135deg,#2563eb,#60a5fa);">
                                            <i class="fa-solid fa-satellite-dish"></i>
                                        </div>
                                        <div class="uc-role-info">
                                            <h6>NOC</h6>
                                            <p>Memantau jaringan, OLT, router, dan insiden secara real-time.</p>
                                        </div>
                                    </label>
                                    <label class="uc-role-card {{ old('role') === 'sales' ? 'selected' : '' }}" onclick="ucSelectRole(this,'sales')">
                                        <input type="radio" name="role" value="sales" {{ old('role') === 'sales' ? 'checked' : '' }}>
                                        <div class="uc-role-icon" style="background:linear-gradient(135deg,#ea580c,#fb923c);">
                                            <i class="fa-solid fa-handshake"></i>
                                        </div>
                                        <div class="uc-role-info">
                                            <h6>Sales</h6>
                                            <p>Akses terbatas sesuai hak akses yang diberikan.</p>
                                        </div>
                                    </label>
                                </div>
                                @error('role') <div class="uc-field-msg msg-err" style="margin-top:10px;"><i class="fa-solid fa-circle-exclamation"></i>{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                    {{-- CARD 4: REVIEW --}}
                    <div class="uc-card" style="--card-accent:linear-gradient(90deg,var(--warning),#fbbf24);">
                        <div class="uc-card-header">
                            <div class="uc-card-icon uc-card-icon-amber"><i class="fa-solid fa-clipboard-check"></i></div>
                            <div class="uc-card-title">
                                <h5>Review</h5>
                                <small>Pastikan semua data sudah benar sebelum disimpan</small>
                            </div>
                        </div>
                        <div class="uc-card-body">
                            <div class="uc-review-grid">
                                <div class="uc-review-item">
                                    <div class="uc-review-item-label">Nama</div>
                                    <div class="uc-review-item-value" id="ucRevName">-</div>
                                </div>
                                <div class="uc-review-item">
                                    <div class="uc-review-item-label">Username</div>
                                    <div class="uc-review-item-value" id="ucRevUsername">-</div>
                                </div>
                                <div class="uc-review-item">
                                    <div class="uc-review-item-label">Role</div>
                                    <div class="uc-review-item-value" id="ucRevRole">Teknisi</div>
                                </div>
                                <div class="uc-review-item">
                                    <div class="uc-review-item-label">Password</div>
                                    <div class="uc-review-item-value" id="ucRevPw">-</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- STICKY FOOTER --}}
                    <div class="uc-sticky-footer">
                        <div class="uc-footer-left">
                            <button type="button" class="btn btn-outline-secondary" onclick="showListMode()">
                                <i class="fa-solid fa-arrow-left me-1"></i>Batal
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="ucResetForm()">
                                <i class="fa-solid fa-rotate-left me-1"></i>Reset
                            </button>
                        </div>
                        <button type="submit" class="btn btn-primary px-5 py-2" id="ucSubmitBtn">
                            <i class="fa-solid fa-check me-1"></i>Simpan Pengguna
                        </button>
                    </div>
                </div>
                {{-- ─── RIGHT: SIDEBAR ─── --}}
                <div class="uc-form-side">
                    {{-- PREVIEW CARD --}}
                    <div class="uc-side-card">
                        <div class="uc-side-header">
                            <i class="fa-solid fa-eye"></i>Preview Pengguna
                        </div>
                        <div class="uc-side-body" style="text-align:center;">
                            <div class="uc-preview-avatar" id="ucPreviewAvatar">?</div>
                            <div class="uc-preview-name" id="ucPreviewName">Nama Pengguna</div>
                            <div class="uc-preview-role" id="ucPreviewRole">
                                <span class="badge" style="background:linear-gradient(135deg,#f0fdf4,#ecfdf5);color:#059669;font-weight:600;font-size:0.68rem;padding:3px 10px;border-radius:999px;border:1px solid rgba(5,150,105,0.12);">Teknisi</span>
                            </div>
                            <div class="uc-preview-email" id="ucPreviewUsername">username.unik</div>
                            <div class="uc-preview-status">
                                <span class="uc-dot"></span>Belum Aktif
                            </div>
                        </div>
                    </div>
                    {{-- TIPS CARD --}}
                    <div class="uc-side-card">
                        <div class="uc-side-header">
                            <i class="fa-solid fa-lightbulb"></i>Tips Keamanan
                        </div>
                        <div class="uc-side-body">
                            <ul class="uc-tip-list">
                                <li>
                                    <i class="fa-solid fa-check-circle"></i>
                                    Gunakan email aktif untuk notifikasi login & reset password.
                                </li>
                                <li>
                                    <i class="fa-solid fa-check-circle"></i>
                                    Gunakan password minimal 8 karakter dengan kombinasi huruf, angka & simbol.
                                </li>
                                <li>
                                    <i class="fa-solid fa-check-circle"></i>
                                    Berikan role sesuai tugas & tanggung jawab pengguna.
                                </li>
                            </ul>
                        </div>
                    </div>
                    {{-- AFTER CREATE CARD --}}
                    <div class="uc-side-card">
                        <div class="uc-side-header">
                            <i class="fa-solid fa-circle-info"></i>Setelah Akun Dibuat
                        </div>
                        <div class="uc-side-body">
                            <ul class="uc-activity-list">
                                <li>
                                    <div class="uc-activity-icon" style="background:linear-gradient(135deg,var(--primary),#60a5fa);">
                                        <i class="fa-solid fa-right-to-bracket"></i>
                                    </div>
                                    Pengguna dapat login ke sistem
                                </li>
                                <li>
                                    <div class="uc-activity-icon" style="background:linear-gradient(135deg,var(--warning),#fbbf24);">
                                        <i class="fa-solid fa-key"></i>
                                    </div>
                                    Reset password jika diperlukan
                                </li>
                                <li>
                                    <div class="uc-activity-icon" style="background:linear-gradient(135deg,var(--accent),#a78bfa);">
                                        <i class="fa-solid fa-envelope"></i>
                                    </div>
                                    Menerima email notifikasi
                                </li>
                                <li>
                                    <div class="uc-activity-icon" style="background:linear-gradient(135deg,var(--success),#34d399);">
                                        <i class="fa-solid fa-gauge-high"></i>
                                    </div>
                                    Mengakses dashboard sesuai role
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
{{-- ═══════════════════════════════════════════════
     EDIT MODALS (unchanged)
     ═══════════════════════════════════════════════ --}}
@foreach($users as $user)
<div class="modal fade" id="editModal{{ $user->id }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('settings.users.update', $user) }}">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit Akun</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Lengkap</label>
                        <input type="text" name="name" class="form-control" value="{{ $user->name }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Username</label>
                        <input type="text" name="username" class="form-control" value="{{ $user->username }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Role</label>
                        <select name="role" class="form-select" required>
                            <option value="teknisi" {{ $user->role === 'teknisi' ? 'selected' : '' }}>Teknisi</option>
                            <option value="admin" {{ $user->role === 'admin' ? 'selected' : '' }}>Admin</option>
                            <option value="noc" {{ $user->role === 'noc' ? 'selected' : '' }}>NOC</option>
                            <option value="sales" {{ $user->role === 'sales' ? 'selected' : '' }}>Sales</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Password Baru <small class="text-muted">(kosongkan jika tidak diubah)</small></label>
                        <input type="password" name="password" class="form-control" placeholder="Minimal 8 karakter" minlength="8">
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Konfirmasi Password</label>
                        <input type="password" name="password_confirmation" class="form-control" placeholder="Ulangi password">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="detailModal{{ $user->id }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Akun</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Nama Lengkap</label>
                    <div class="form-control bg-light">{{ $user->name }}</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Username</label>
                    <div class="form-control bg-light">{{ $user->username }}</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Role</label>
                    <div class="form-control bg-light">{{ $user->role === 'noc' ? 'NOC' : ucfirst($user->role) }}</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Terdaftar</label>
                    <div class="form-control bg-light">{{ $user->created_at->format('d M Y') }}</div>
                </div>
                <div class="mb-0">
                    <label class="form-label fw-semibold">Password <small class="text-muted">(klik mata untuk melihat password asli)</small></label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="pwOriginal{{ $user->id }}" value="••••••••••" data-url="{{ route('settings.users.password', $user) }}" data-loaded="0" readonly>
                        <button type="button" class="btn btn-outline-secondary" onclick="revealPw({{ $user->id }})" title="Tampilkan Password Asli">
                            <i class="fa-solid fa-eye" id="pwEye{{ $user->id }}"></i>
                        </button>
                        <button type="button" class="btn btn-outline-primary" onclick="copyPwOriginal({{ $user->id }})" title="Salin">
                            <i class="fa-solid fa-copy"></i>
                        </button>
                    </div>
                    <small class="text-muted" id="pwHint{{ $user->id }}">Password asli hanya bisa dilihat untuk akun yang dibuat/diubah setelah fitur ini aktif.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endforeach
@endsection
@push('scripts')
<script>
(function() {
    const listMode = document.getElementById('listMode');
    const createMode = document.getElementById('createMode');
    /* ─── Mode Toggle ─── */
    window.showCreateMode = function() {
        listMode.classList.add('uc-hidden');
        createMode.classList.remove('uc-hidden');
        window.scrollTo({ top: 0, behavior: 'smooth' });
        ucUpdatePreview();
    };
    window.showListMode = function() {
        listMode.classList.remove('uc-hidden');
        createMode.classList.add('uc-hidden');
    };
    /* ─── Step Indicator ─── */
    let currentStep = 1;
    const steps = document.querySelectorAll('.uc-step');
    const connectors = document.querySelectorAll('.uc-step-connector');
    window.ucSetStep = function(n) {
        if (n === currentStep) return;
        currentStep = n;
        steps.forEach((s, i) => {
            const sn = i + 1;
            s.classList.remove('active', 'completed');
            if (sn < n) s.classList.add('completed');
            else if (sn === n) s.classList.add('active');
        });
        connectors.forEach((c, i) => {
            c.classList.toggle('done', i + 1 < n);
        });
    };
    /* Auto-detect step from focus */
    document.querySelectorAll('#createUserForm .uc-field[data-step]').forEach(field => {
        field.addEventListener('focusin', function() {
            const s = parseInt(this.dataset.step);
            if (s) ucSetStep(s);
        });
    });
    /* ─── Preview Update ─── */
    const nameEl = document.getElementById('ucName');
    const usernameEl = document.getElementById('ucUsername');
    const roleCards = document.querySelectorAll('#ucRoleGrid .uc-role-card');
    window.ucUpdatePreview = function() {
        const name = nameEl.value.trim();
        const username = usernameEl.value.trim();
        const role = document.querySelector('#ucRoleGrid input:checked')?.value || 'teknisi';
        /* Avatar */
        const avatar = document.getElementById('ucPreviewAvatar');
        avatar.textContent = name ? name.charAt(0).toUpperCase() : '?';
        avatar.classList.toggle('has-name', !!name);
        /* Name */
        document.getElementById('ucPreviewName').textContent = name || 'Nama Pengguna';
        /* Username */
        document.getElementById('ucPreviewUsername').textContent = username || 'username.unik';
        /* Role */
        const roleEl = document.getElementById('ucPreviewRole');
        if (role === 'admin') {
            roleEl.innerHTML = '<span class="badge" style="background:linear-gradient(135deg,#fef3c7,#fff7e6);color:#d97706;font-weight:600;font-size:0.68rem;padding:3px 10px;border-radius:999px;border:1px solid rgba(217,119,6,0.12);"><i class="fa-solid fa-crown" style="font-size:0.55rem;margin-right:2px;"></i>Administrator</span>';
        } else if (role === 'noc') {
            roleEl.innerHTML = '<span class="badge" style="background:linear-gradient(135deg,#ede9fe,#f3e8ff);color:#7c3aed;font-weight:600;font-size:0.68rem;padding:3px 10px;border-radius:999px;border:1px solid rgba(124,58,237,0.12);"><i class="fa-solid fa-satellite-dish" style="font-size:0.55rem;margin-right:2px;"></i>NOC</span>';
        } else if (role === 'sales') {
            roleEl.innerHTML = '<span class="badge" style="background:linear-gradient(135deg,#ffedd5,#fff7ed);color:#ea580c;font-weight:600;font-size:0.68rem;padding:3px 10px;border-radius:999px;border:1px solid rgba(234,88,12,0.12);"><i class="fa-solid fa-handshake" style="font-size:0.55rem;margin-right:2px;"></i>Sales</span>';
        } else {
            roleEl.innerHTML = '<span class="badge" style="background:linear-gradient(135deg,#f0fdf4,#ecfdf5);color:#059669;font-weight:600;font-size:0.68rem;padding:3px 10px;border-radius:999px;border:1px solid rgba(5,150,105,0.12);"><i class="fa-solid fa-wrench" style="font-size:0.55rem;margin-right:2px;"></i>Teknisi</span>';
        }
        /* Review */
        document.getElementById('ucRevName').textContent = name || '-';
        document.getElementById('ucRevUsername').textContent = username || '-';
        document.getElementById('ucRevRole').textContent = role === 'admin' ? 'Administrator' : (role === 'noc' ? 'NOC' : (role === 'sales' ? 'Sales' : 'Teknisi'));
        /* Step completion */
        if (name && username) ucSetStep(Math.max(currentStep, 1));
    };
    nameEl.addEventListener('input', ucUpdatePreview);
    usernameEl.addEventListener('input', ucUpdatePreview);
    roleCards.forEach(card => card.addEventListener('click', ucUpdatePreview));
    /* ─── Role Selection ─── */
    window.ucSelectRole = function(el, value) {
        roleCards.forEach(c => c.classList.remove('selected'));
        el.classList.add('selected');
        el.querySelector('input').checked = true;
        ucUpdatePreview();
        ucSetStep(3);
        /* brief review jump */
        setTimeout(() => ucSetStep(4), 600);
    };
    /* ─── Password Toggle ─── */
    window.ucTogglePassword = function() {
        const pw = document.getElementById('ucPassword');
        const eye = document.getElementById('ucPwEye');
        const isPassword = pw.type === 'password';
        pw.type = isPassword ? 'text' : 'password';
        eye.classList.toggle('fa-eye', !isPassword);
        eye.classList.toggle('fa-eye-slash', isPassword);
    };
    /* ─── Password Generate ─── */
    window.ucGeneratePassword = function() {
        const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#$%&*';
        let pw = '';
        const arr = new Uint32Array(16);
        crypto.getRandomValues(arr);
        for (let i = 0; i < 16; i++) pw += chars[arr[i] % chars.length];
        /* ensure at least one of each type */
        const ensure = [
            'ABCDEFGHJKLMNPQRSTUVWXYZ',
            'abcdefghjkmnpqrstuvwxyz',
            '23456789',
            '!@#$%&*'
        ];
        ensure.forEach((set, i) => {
            const rnd = new Uint32Array(1);
            crypto.getRandomValues(rnd);
            pw = pw.substring(0, i + 3) + set[rnd[0] % set.length] + pw.substring(i + 4);
        });
        const pwField = document.getElementById('ucPassword');
        pwField.value = pw;
        pwField.type = 'text';
        document.getElementById('ucPwEye').classList.replace('fa-eye', 'fa-eye-slash');
        ucCheckPassword();
        ucCheckConfirm();
        document.getElementById('ucCopyBtn').style.display = 'inline-flex';
        document.getElementById('ucRevPw').textContent = '••••••••••••';
        ucSetStep(2);
    };
    /* ─── Copy Password ─── */
    window.ucCopyPassword = function() {
        const pw = document.getElementById('ucPassword').value;
        navigator.clipboard.writeText(pw).then(() => {
            const btn = document.getElementById('ucCopyBtn');
            btn.innerHTML = '<i class="fa-solid fa-check"></i>Tersalin';
            setTimeout(() => { btn.innerHTML = '<i class="fa-solid fa-copy"></i>Salin'; }, 1500);
        });
    };
    /* ─── Password Strength ─── */
    window.ucCheckPassword = function() {
        const pw = document.getElementById('ucPassword').value;
        const strengthEl = document.getElementById('ucPwStrength');
        const bar = document.getElementById('ucPwBar');
        const label = document.getElementById('ucPwLabel');
        if (!pw) {
            strengthEl.style.display = 'none';
            resetChecks();
            document.getElementById('ucRevPw').textContent = '-';
            return;
        }
        strengthEl.style.display = 'block';
        document.getElementById('ucRevPw').textContent = '••••••••••••';
        const checks = {
            len: pw.length >= 8,
            upper: /[A-Z]/.test(pw),
            lower: /[a-z]/.test(pw),
            num: /[0-9]/.test(pw),
            sym: /[^A-Za-z0-9]/.test(pw)
        };
        Object.entries(checks).forEach(([k, v]) => {
            const el = document.getElementById('chk' + k.charAt(0).toUpperCase() + k.slice(1));
            if (el) el.classList.toggle('passed', v);
        });
        const score = Object.values(checks).filter(Boolean).length;
        bar.className = 'uc-pw-bar-fill';
        label.className = 'uc-pw-label';
        if (score <= 2) { bar.classList.add('weak'); label.classList.add('weak'); label.textContent = 'Lemah'; }
        else if (score === 3) { bar.classList.add('medium'); label.classList.add('medium'); label.textContent = 'Sedang'; }
        else if (score === 4) { bar.classList.add('strong'); label.classList.add('strong'); label.textContent = 'Kuat'; }
        else { bar.classList.add('very-strong'); label.classList.add('very-strong'); label.textContent = 'Sangat Kuat'; }
    };
    function resetChecks() {
        ['chkLen','chkUpper','chkLower','chkNum','chkSym'].forEach(id => {
            document.getElementById(id)?.classList.remove('passed');
        });
        const bar = document.getElementById('ucPwBar');
        bar.className = 'uc-pw-bar-fill';
        const label = document.getElementById('ucPwLabel');
        label.className = 'uc-pw-label';
        label.textContent = 'Kekuatan password';
    }
    /* ─── Confirm Password ─── */
    window.ucCheckConfirm = function() {
        const pw = document.getElementById('ucPassword').value;
        const cpw = document.getElementById('ucPasswordConfirm').value;
        const matchEl = document.getElementById('ucPwMatch');
        if (!cpw) { matchEl.style.display = 'none'; return; }
        matchEl.style.display = 'flex';
        if (pw === cpw) {
            matchEl.className = 'uc-pw-check passed';
            matchEl.querySelector('i').className = 'fa-solid fa-check';
            matchEl.querySelector('span').textContent = 'Password cocok';
        } else {
            matchEl.className = 'uc-pw-check';
            matchEl.querySelector('i').className = 'fa-solid fa-xmark';
            matchEl.querySelector('span').textContent = 'Password tidak cocok';
            matchEl.style.color = '#ef4444';
        }
    };
    /* ─── Reset Form ─── */
    window.ucResetForm = function() {
        document.getElementById('createUserForm').reset();
        document.getElementById('ucPassword').type = 'password';
        document.getElementById('ucPwEye').classList.replace('fa-eye-slash', 'fa-eye');
        document.getElementById('ucCopyBtn').style.display = 'none';
        document.getElementById('ucPwMatch').style.display = 'none';
        document.getElementById('ucRevPw').textContent = '-';
        resetChecks();
        document.getElementById('ucPwStrength').style.display = 'none';
        ucUpdatePreview();
        ucSetStep(1);
    };
    /* ─── Detail Modal: Reveal Original Password (AJAX) & Copy ─── */
    window.revealPw = function(id) {
        const input = document.getElementById('pwOriginal' + id);
        if (!input) return;
        const eye = document.getElementById('pwEye' + id);
        const hint = document.getElementById('pwHint' + id);
        if (input.dataset.loaded === '1') {
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            eye.classList.toggle('fa-eye', !isPassword);
            eye.classList.toggle('fa-eye-slash', isPassword);
            return;
        }
        input.value = 'Memuat...';
        fetch(input.dataset.url)
            .then(r => r.json())
            .then(data => {
                input.dataset.loaded = '1';
                if (data.password) {
                    input.value = data.password;
                    input.type = 'text';
                    eye.classList.replace('fa-eye', 'fa-eye-slash');
                    if (hint) hint.textContent = 'Password asli untuk akun ini.';
                } else {
                    input.value = data.message || 'Tidak tersedia';
                    eye.classList.replace('fa-eye', 'fa-eye-slash');
                }
            })
            .catch(() => {
                input.value = 'Gagal memuat password';
            });
    };
    window.copyPwOriginal = function(id) {
        const input = document.getElementById('pwOriginal' + id);
        if (!input) return;
        const btn = input.parentElement.querySelector('button:last-child');
        navigator.clipboard.writeText(input.value).then(() => {
            btn.innerHTML = '<i class="fa-solid fa-check"></i>';
            setTimeout(() => { btn.innerHTML = '<i class="fa-solid fa-copy"></i>'; }, 1500);
        });
    };
    /* ─── Auto-show create mode if there are validation errors on name/email ─── */
    @error('name') showCreateMode(); @enderror
    @error('username') showCreateMode(); @enderror
    @error('password') showCreateMode(); @enderror
    @error('role') showCreateMode(); @enderror
    /* Init preview */
    ucUpdatePreview();
    ucCheckPassword();
})();
</script>
@endpush
