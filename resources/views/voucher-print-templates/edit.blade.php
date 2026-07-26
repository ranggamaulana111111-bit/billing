@extends('layouts.app')

@section('title', $template->exists ? 'Edit Template Cetak' : 'Template Cetak Baru')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h2 class="mb-0"><i class="fa-solid fa-pen me-2" style="color:#e11d48;"></i>{{ $template->exists ? 'Edit Template' : 'Template Baru' }}</h2>
            <p class="text-muted mb-0">Edit desain struk voucher & preview langsung.</p>
        </div>
        <a href="{{ route('voucher-print-templates.index') }}" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i>Kembali
        </a>
    </div>

    <form method="POST" action="{{ $template->exists ? route('voucher-print-templates.update', $template) : route('voucher-print-templates.store') }}">
        @csrf
        @if($template->exists) @method('PUT') @endif

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><h5 class="mb-0"><i class="fa-solid fa-sliders me-2"></i>Pengaturan</h5></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label fw-semibold">Nama Template</label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                       value="{{ old('name', $template->name) }}" placeholder="Mis. Struk Thermal 80mm" required>
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Ukuran Kertas</label>
                                <select name="paper_size" class="form-select @error('paper_size') is-invalid @enderror">
                                    <option value="58mm" {{ old('paper_size', $template->paper_size) === '58mm' ? 'selected' : '' }}>58 mm</option>
                                    <option value="80mm" {{ old('paper_size', $template->paper_size) === '80mm' ? 'selected' : '' }}>80 mm</option>
                                    <option value="A4" {{ old('paper_size', $template->paper_size) === 'A4' ? 'selected' : '' }}>A4</option>
                                </select>
                                @error('paper_size') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="activeChk"
                                   {{ old('is_active', $template->is_active) ? 'checked' : '' }}>
                            <label class="form-check-label" for="activeChk">Jadikan template aktif (dipakai saat cetak voucher)</label>
                        </div>

                        <hr>
                        <h6 class="fw-bold">Placeholder yang Didukung</h6>
                        <ul class="small text-muted mb-0" style="line-height:1.9;">
                            <li><code>{COMPANY}</code> — Nama perusahaan</li>
                            <li><code>{USERNAME}</code> — Username voucher</li>
                            <li><code>{PASSWORD}</code> — Password voucher</li>
                            <li><code>{DURATION}</code> — Masa aktif</li>
                            <li><code>{HOTSPOT_SERVER}</code> — Nama server hotspot</li>
                            <li><code>{ADMIN_PHONE}</code> — Telepon admin</li>
                            <li><code>{ADMIN_NAME}</code> — Nama admin</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fa-solid fa-code me-2"></i>Desain (HTML)</h5>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="updatePreview()">
                            <i class="fa-solid fa-rotate me-1"></i>Preview
                        </button>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <textarea name="content" id="content" class="form-control font-monospace @error('content') is-invalid @enderror"
                                  rows="14" style="font-size:12px;" required>{{ old('content', $template->content) }}</textarea>
                        @error('content') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="mt-3">
                            <div class="small text-muted mb-1">Hasil render (contoh):</div>
                            <iframe id="previewFrame" class="w-100 border rounded" style="height:320px;background:#fff;"></iframe>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary px-4">
                <i class="fa-solid fa-floppy-disk me-1"></i>Simpan
            </button>
            <a href="{{ route('voucher-print-templates.index') }}" class="btn btn-outline-secondary">Batal</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
function escapeHtml(s){return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function buildSample(content){
    var map={COMPANY:'ALKONEK',USERNAME:'ALK12345',PASSWORD:'pass123',DURATION:'7 Hari',HOTSPOT_SERVER:'hotspot1',ADMIN_PHONE:'62812xxxxxxx',ADMIN_NAME:'Admin'};
    var html=content;
    Object.keys(map).forEach(function(k){html=html.split('{'+k+'}').join(map[k]);});
    return '<!doctype html><html><head><style>body{font-family:monospace;padding:16px;margin:0;}.voucher{border:1px dashed #999;padding:12px;border-radius:8px}.v-header{font-weight:bold;text-align:center}.v-title{text-align:center;font-size:18px;font-weight:bold;margin:6px 0}.v-row{display:flex;justify-content:space-between;border-bottom:1px dotted #ccc;padding:3px 0}.v-footer{text-align:center;font-size:12px;margin-top:8px}</style></head><body>'+html+'</body></html>';
}
function updatePreview(){
    var doc=document.getElementById('previewFrame').contentDocument;
    doc.open();doc.write(buildSample(document.getElementById('content').value));doc.close();
}
document.addEventListener('DOMContentLoaded',updatePreview);
document.getElementById('content').addEventListener('input',function(){clearTimeout(window.__pt);window.__pt=setTimeout(updatePreview,400);});
</script>
@endpush
