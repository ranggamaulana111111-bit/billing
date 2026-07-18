@extends('layouts.app')
@section('title', 'Editor Halaman Isolir')
@section('content')
<style>
.isolir-editor-wrap{display:flex;flex-direction:column;height:calc(100vh - 72px);margin:-1.5rem -0.75rem;background:#0f172a;}
.ie-toolbar{display:flex;align-items:center;justify-content:space-between;padding:10px 20px;background:#1e293b;border-bottom:1px solid #334155;flex-shrink:0;gap:10px;flex-wrap:wrap;}
.ie-toolbar .btn{font-size:.82rem;}
.ie-main{display:flex;flex:1;overflow:hidden;}
.ie-left{width:272px;min-width:272px;background:#1e293b;border-right:1px solid #334155;display:flex;flex-direction:column;overflow:hidden;}
.ie-left-section{padding:12px 14px;}
.ie-left-section+.ie-left-section{border-top:1px solid #334155;}
.ie-section-title{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#94a3b8;margin-bottom:10px;display:flex;align-items:center;justify-content:space-between;}
.ie-palette{display:grid;grid-template-columns:1fr 1fr;gap:6px;}
.ie-palette-item{display:flex;flex-direction:column;align-items:center;gap:4px;padding:8px 4px;background:#0f172a;border:1px solid #334155;border-radius:8px;cursor:pointer;transition:all .15s;font-size:.68rem;color:#94a3b8;user-select:none;}
.ie-palette-item:hover{background:#334155;color:#e2e8f0;border-color:#475569;}
.ie-palette-item:active{transform:scale(.95);}
.ie-palette-item i{font-size:1rem;color:#64748b;}
.ie-palette-item:hover i{color:#e2e8f0;}
.ie-elem-list{flex:1;overflow-y:auto;padding:0 14px 14px;}
.ie-elem-list::-webkit-scrollbar{width:5px;}
.ie-elem-list::-webkit-scrollbar-thumb{background:#475569;border-radius:3px;}
.ie-elem-item{display:flex;align-items:center;gap:6px;padding:7px 8px;margin-bottom:4px;background:#0f172a;border:1px solid #334155;border-radius:8px;cursor:pointer;transition:all .15s;font-size:.75rem;color:#cbd5e1;}
.ie-elem-item:hover{border-color:#475569;background:#1a2332;}
.ie-elem-item.active{border-color:#3b82f6;background:#1e3a5f;color:#fff;}
.ie-elem-item .drag-handle{cursor:grab;color:#475569;font-size:.65rem;flex-shrink:0;padding:2px;}
.ie-elem-item .drag-handle:hover{color:#94a3b8;}
.ie-elem-item .elem-icon{width:22px;height:22px;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:.65rem;flex-shrink:0;}
.ie-elem-item .elem-label{flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.ie-elem-item .elem-actions{display:flex;gap:2px;opacity:0;transition:opacity .15s;}
.ie-elem-item:hover .elem-actions{opacity:1;}
.ie-elem-item .elem-actions span{cursor:pointer;padding:2px 3px;color:#64748b;font-size:.65rem;border-radius:3px;}
.ie-elem-item .elem-actions span:hover{color:#fff;}
.ie-elem-item .elem-actions .ea-del:hover{color:#ef4444;background:rgba(239,68,68,.15);}
.ie-elem-item .elem-actions .ea-up,.ie-elem-item .elem-actions .ea-down{font-size:.6rem;}
.ie-empty-msg{text-align:center;padding:30px 10px;color:#475569;font-size:.78rem;}
.ie-center{flex:1;display:flex;align-items:flex-start;justify-content:center;background:#0f172a;padding:20px;overflow:auto;}
.preview-frame{transition:width .3s ease;overflow:hidden;background:#000;flex-shrink:0;position:relative;}
.preview-frame.phone{width:375px;min-height:600px;border-radius:40px;border:6px solid #1e293b;box-shadow:0 20px 60px rgba(0,0,0,.5);}
.preview-frame.desktop{width:100%;max-width:900px;min-height:500px;border-radius:12px;border:6px solid #1e293b;box-shadow:0 20px 60px rgba(0,0,0,.5);}
.phone-notch{width:120px;height:24px;background:#1e293b;border-radius:0 0 16px 16px;margin:0 auto;}
.preview-screen{overflow:hidden;position:relative;}
.preview-screen svg{width:100%;display:block;}
.ie-right{width:300px;min-width:300px;background:#1e293b;border-left:1px solid #334155;display:flex;flex-direction:column;overflow:hidden;}
.ie-right-scroll{flex:1;overflow-y:auto;padding:14px;}
.ie-right-scroll::-webkit-scrollbar{width:5px;}
.ie-right-scroll::-webkit-scrollbar-thumb{background:#475569;border-radius:3px;}
.ie-prop-group{margin-bottom:12px;}
.ie-prop-label{font-size:.7rem;font-weight:600;color:#94a3b8;margin-bottom:4px;display:block;}
.ie-prop-input{width:100%;padding:7px 10px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#e2e8f0;font-size:.82rem;}
.ie-prop-input:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 2px rgba(59,130,246,.15);}
.ie-prop-input[type="color"]{height:36px;padding:4px;cursor:pointer;}
.ie-prop-row{display:flex;gap:8px;}
.ie-prop-row .ie-prop-group{flex:1;}
.ie-empty-state{display:flex;flex-direction:column;align-items:center;justify-content:center;height:200px;color:#475569;text-align:center;padding:20px;}
.ie-empty-state i{font-size:2rem;margin-bottom:10px;opacity:.5;}
.ie-page-settings .ie-prop-group+.ie-prop-group{margin-top:10px;padding-top:10px;border-top:1px solid #334155;}
.ie-badge-dynamic{display:inline-block;font-size:.6rem;background:#1e3a5f;color:#60a5fa;padding:1px 5px;border-radius:3px;margin-left:4px;font-weight:600;}
.ie-badge-pos{display:inline-block;font-size:.6rem;background:#4c1d95;color:#c084fc;padding:1px 5px;border-radius:3px;margin-left:4px;font-weight:600;}
.ie-prop-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;padding-bottom:8px;border-top:1px solid #334155;padding-top:12px;}
.ie-prop-header span{font-size:.78rem;font-weight:700;color:#e2e8f0;}
.ie-prop-header button{font-size:.65rem;padding:2px 8px;}
.ie-section-label{font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b;margin:12px 0 8px;}
.sortable-ghost{opacity:.3;}
.sortable-chosen{background:#1e3a5f !important;border-color:#3b82f6 !important;}
.icon-picker-grid{display:grid;grid-template-columns:repeat(8,1fr);gap:3px;max-height:200px;overflow-y:auto;padding:4px;background:#0f172a;border:1px solid #334155;border-radius:8px;}
.icon-picker-grid span{display:flex;align-items:center;justify-content:center;width:30px;height:30px;font-size:1.1rem;cursor:pointer;border-radius:6px;border:1px solid transparent;transition:all .12s;}
.icon-picker-grid span:hover{background:#334155;border-color:#475569;transform:scale(1.15);}
.icon-picker-cat{font-size:.6rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;padding:6px 2px 3px;grid-column:1/-1;}
.icon-picker-trigger{display:flex;align-items:center;gap:6px;padding:6px 10px;background:#0f172a;border:1px solid #334155;border-radius:6px;cursor:pointer;transition:all .15s;}
.icon-picker-trigger:hover{border-color:#475569;}
.icon-picker-trigger .picked-icon{font-size:1.4rem;}
.icon-picker-trigger .picked-label{font-size:.7rem;color:#94a3b8;}
.tpl-switcher{display:flex;align-items:center;gap:6px;}
.tpl-switcher select{max-width:180px;font-size:.8rem;padding:4px 8px;background:#0f172a;border:1px solid #475569;border-radius:6px;color:#e2e8f0;}
.img-upload-zone{position:relative;border:2px dashed #475569;border-radius:8px;padding:16px;text-align:center;cursor:pointer;transition:all .2s;background:#0a0f1a;}
.img-upload-zone:hover,.img-upload-zone.dragover{border-color:#3b82f6;background:#0c1829;}
.img-upload-zone i{font-size:1.5rem;color:#475569;margin-bottom:6px;display:block;}
.img-upload-zone .uz-text{font-size:.72rem;color:#64748b;}
.img-upload-zone input[type="file"]{position:absolute;inset:0;opacity:0;cursor:pointer;}
.img-preview-thumb{width:100%;max-height:120px;object-fit:contain;border-radius:6px;border:1px solid #334155;margin-top:8px;background:#000;}

/* ── Interactive Preview Overlay ── */
.pv-el{cursor:pointer;}
.pv-el:hover .pv-el-border{stroke:#3b82f6;stroke-width:2;stroke-dasharray:4,3;fill:rgba(59,130,246,0.06);}
.pv-el.selected .pv-el-border{stroke:#3b82f6;stroke-width:2;fill:rgba(59,130,246,0.08);}
.pv-overlay{position:absolute;pointer-events:none;transition:opacity .12s;}
.pv-overlay.show{pointer-events:auto;}
.pv-overlay .pv-del{position:absolute;top:-8px;right:-8px;width:20px;height:20px;background:#ef4444;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:10px;cursor:pointer;opacity:0;transition:opacity .15s;z-index:10;box-shadow:0 1px 3px rgba(0,0,0,.4);}
.pv-el:hover .pv-del,.pv-el.selected .pv-del{opacity:1;}
.pv-overlay .pv-drag{position:absolute;top:-8px;left:-8px;width:20px;height:20px;background:#3b82f6;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:9px;cursor:grab;opacity:0;transition:opacity .15s;z-index:10;box-shadow:0 1px 3px rgba(0,0,0,.4);}
.pv-el:hover .pv-drag,.pv-el.selected .pv-drag{opacity:1;}
.pv-overlay .pv-resize{position:absolute;bottom:-5px;right:-5px;width:14px;height:14px;background:#f59e0b;border-radius:3px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:8px;cursor:nwse-resize;opacity:0;transition:opacity .15s;z-index:10;box-shadow:0 1px 3px rgba(0,0,0,.4);}
.pv-el:hover .pv-resize,.pv-el.selected .pv-resize{opacity:1;}
.pv-overlay .pv-dblclick{position:absolute;top:-8px;left:50%;transform:translateX(-50%);background:#1e293b;color:#94a3b8;font-size:9px;padding:1px 6px;border-radius:3px;opacity:0;transition:opacity .15s;white-space:nowrap;z-index:10;}
.pv-el:hover .pv-dblclick{opacity:1;}
.pv-drag-active{opacity:.5 !important;}
.pv-drop-indicator{position:absolute;left:0;right:0;height:3px;background:#3b82f6;border-radius:2px;z-index:20;pointer-events:none;}
.pv-inline-editor{position:absolute;z-index:30;background:#1e293b;border:2px solid #3b82f6;border-radius:6px;padding:6px 8px;color:#e2e8f0;font-family:system-ui,sans-serif;resize:none;overflow:hidden;outline:none;min-width:120px;}
</style>

<div class="isolir-editor-wrap">
    <div class="ie-toolbar">
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('settings.index') }}" class="btn btn-sm btn-outline-secondary text-light"><i class="fa-solid fa-arrow-left me-1"></i>Kembali</a>
            <span class="text-secondary">|</span>
            <span class="text-light fw-semibold" style="font-size:.85rem;"><i class="fa-solid fa-palette me-1"></i>Editor Halaman Isolir</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="tpl-switcher me-3">
                <select id="tplSelect" onchange="switchTemplate(this.value)" title="Pilih template">
                    @foreach($templates as $tpl)
                        <option value="{{ $tpl->id }}"{{ $tpl->id == $activeTemplateId ? ' selected' : '' }}>{{ $tpl->name }}{{ $tpl->is_active ? ' (Aktif)' : '' }}</option>
                    @endforeach
                </select>
                <button type="button" class="btn btn-sm btn-outline-info" onclick="newTemplate()" title="Template baru"><i class="fa-solid fa-plus"></i></button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="renameTemplate()" title="Ganti nama"><i class="fa-solid fa-pen"></i></button>
                <button type="button" class="btn btn-sm btn-outline-warning" onclick="duplicateTemplate()" title="Duplikat"><i class="fa-solid fa-copy"></i></button>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteTemplate()" title="Hapus"><i class="fa-solid fa-trash"></i></button>
                <button type="button" class="btn btn-sm btn-outline-success" onclick="activateTemplate()" title="Jadikan Aktif"><i class="fa-solid fa-check-circle"></i></button>
            </div>
            <div class="btn-group btn-group-sm me-2" id="viewportToggle">
                <button type="button" class="btn btn-outline-info active" onclick="setViewport('phone')" id="vpPhone"><i class="fa-solid fa-mobile-screen me-1"></i>Phone</button>
                <button type="button" class="btn btn-outline-info" onclick="setViewport('desktop')" id="vpDesktop"><i class="fa-solid fa-desktop me-1"></i>Desktop</button>
            </div>
            <button type="button" class="btn btn-sm btn-outline-warning" onclick="loadDefaultTemplate()" title="Muat template default"><i class="fa-solid fa-rotate-left me-1"></i>Default</button>
            <a href="{{ route('settings.isolir-preview') }}" target="_blank" class="btn btn-sm btn-outline-success"><i class="fa-solid fa-external-link me-1"></i>Preview</a>
            <button type="button" class="btn btn-sm btn-primary px-3" onclick="saveTemplate()" id="btnSave"><i class="fa-solid fa-floppy-disk me-1"></i>Simpan</button>
        </div>
    </div>
    <div class="ie-main">
        <div class="ie-left">
            <div class="ie-left-section">
                <div class="ie-section-title">Tambah Elemen</div>
                <div class="ie-palette">
                    <div class="ie-palette-item" onclick="addElement('icon')"><i class="fa-solid fa-face-grin-beam"></i>Icon</div>
                    <div class="ie-palette-item" onclick="addElement('heading')"><i class="fa-solid fa-heading"></i>Judul</div>
                    <div class="ie-palette-item" onclick="addElement('text')"><i class="fa-solid fa-align-left"></i>Teks</div>
                    <div class="ie-palette-item" onclick="addElement('divider')"><i class="fa-solid fa-minus"></i>Garis</div>
                    <div class="ie-palette-item" onclick="addElement('spacer')"><i class="fa-solid fa-arrows-up-down"></i>Ruang</div>
                    <div class="ie-palette-item" onclick="addElement('image')"><i class="fa-solid fa-image"></i>Gambar</div>
                    <div class="ie-palette-item" onclick="addElement('customer_name')"><i class="fa-solid fa-user"></i>Pelanggan</div>
                    <div class="ie-palette-item" onclick="addElement('invoice_box')"><i class="fa-solid fa-file-invoice"></i>Tagihan</div>
                    <div class="ie-palette-item" onclick="addElement('wa_button')"><i class="fa-brands fa-whatsapp"></i>WA Btn</div>
                    <div class="ie-palette-item" onclick="addElement('footer')"><i class="fa-solid fa-shoe-prints"></i>Footer</div>
                </div>
            </div>
            <div class="ie-left-section" style="flex-shrink:0;">
                <div class="ie-section-title"><span>Susunan Elemen</span><span id="elemCount" style="color:#475569;font-size:.65rem;font-weight:400;text-transform:none;letter-spacing:0;"></span></div>
            </div>
            <div class="ie-elem-list" id="elemList"></div>
        </div>
        <div class="ie-center">
            <div class="preview-frame phone" id="previewFrame">
                <div class="phone-notch" id="phoneNotch"></div>
                <div class="preview-screen" id="previewWrap">
                    <div id="preview"></div>
                    <div id="pvOverlays"></div>
                </div>
            </div>
        </div>
        <div class="ie-right">
            <div class="ie-left-section"><div class="ie-section-title">Panel Properti</div></div>
            <div class="ie-right-scroll">
                <div id="elemProps">
                    <div class="ie-empty-state"><i class="fa-solid fa-hand-pointer"></i><div>Pilih elemen dari daftar<br>atau klik di layar preview</div></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="iconPickerModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content" style="background:#1e293b;border:1px solid #334155;">
    <div class="modal-header" style="border-color:#334155;padding:12px 16px;">
        <h6 class="modal-title text-light" style="font-size:.85rem;"><i class="fa-solid fa-icons me-2"></i>Pilih Ikon</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body" style="padding:12px;" id="iconPickerBody"></div>
</div></div></div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
<script>
var T={background:{start:'#0f172a',end:'#1e293b',opacity:1},card:{bg:'#ffffff',radius:20,shadow:true},elements:[]};
var selIdx=-1,viewportMode='phone',sortableInstance=null,iconPickerTarget=-1;
var currentTemplateId={{ $activeTemplateId ?? 'null' }};

var EL_META={
    icon:{label:'Ikon',icon:'&#x1F6AB;',color:'#dc2626',dynamic:false},
    heading:{label:'Judul',icon:'<i class="fa-solid fa-heading"></i>',color:'#dc2626',dynamic:false},
    text:{label:'Teks',icon:'<i class="fa-solid fa-align-left"></i>',color:'#64748b',dynamic:false},
    divider:{label:'Garis',icon:'<i class="fa-solid fa-minus"></i>',color:'#e2e8f0',dynamic:false},
    spacer:{label:'Ruang',icon:'<i class="fa-solid fa-arrows-up-down"></i>',color:'#475569',dynamic:false},
    image:{label:'Gambar',icon:'<i class="fa-solid fa-image"></i>',color:'#3b82f6',dynamic:false},
    customer_name:{label:'Pelanggan',icon:'<i class="fa-solid fa-user"></i>',color:'#3b82f6',dynamic:true},
    invoice_box:{label:'Tagihan',icon:'<i class="fa-solid fa-file-invoice"></i>',color:'#f59e0b',dynamic:true},
    wa_button:{label:'WA Button',icon:'<i class="fa-brands fa-whatsapp"></i>',color:'#25D366',dynamic:false},
    footer:{label:'Footer',icon:'<i class="fa-solid fa-shoe-prints"></i>',color:'#94a3b8',dynamic:false}
};

var ICONS={
    'Status':['\u{1F6AB}','\u26A0\uFE0F','\u{1F534}','\u{1F7E1}','\u{1F7E2}','\u2705','\u274C','\u26A1','\u{1F4A5}','\u{1F4A2}','\u{1F512}','\u{1F513}','\u{1F6D1}','\u26D4','\u{1F51E}','\u{1F515}'],
    'Komunikasi':['\u{1F4F1}','\u{1F4BB}','\u{1F310}','\u{1F4E1}','\u{1F4F6}','\u260E\uFE0F','\u{1F4E7}','\u{1F4AC}','\u{1F4E2}','\u{1F514}','\u{1F517}','\u{1F510}','\u{1F5DD}','\u{1F4DC}','\u{1F4FB}','\u{1F4FA}'],
    'Bisnis':['\u{1F4B0}','\u{1F4B3}','\u{1F9FE}','\u{1F4CB}','\u{1F4CA}','\u{1F4C8}','\u{1F4BC}','\u{1F3E6}','\u{1F6D2}','\u{1F4E6}','\u{1F3F7}\uFE0F','\u{1F381}','\u{1F4EE}','\u{1F3AA}','\u{1F3AD}','\u{1F3AF}'],
    'Teknologi':['\u{1F527}','\u26A1','\u{1F50C}','\u{1F5A5}\uFE0F','\u{1F5A8}\uFE0F','\u{1F4BF}','\u{1F50B}','\u{1F4A1}','\u{1F50D}','\u{1F6E0}\uFE0F','\u2699\uFE0F','\u{1F52C}','\u{1F4E1}','\u{1F916}','\u{1F4BE}','\u{1F579}\uFE0F'],
    'Alam':['\u{1F30D}','\u{1F319}','\u2B50','\u{1F525}','\u{1F4A7}','\u{1F308}','\u{1F338}','\u{1F343}','\u2744\uFE0F','\u2600\uFE0F','\u{1F30A}','\u26F0\uFE0F','\u{1F319}','\u2728','\u{1F4AB}','\u{1F31F}'],
    'Transport':['\u{1F697}','\u2708\uFE0F','\u{1F680}','\u{1F6F9}','\u{1F68C}','\u{1F6A2}','\u{1F6E9}\uFE0F','\u{1F681}','\u{1F6F8}','\u{1F69C}','\u{1F3CE}\uFE0F','\u{1F682}','\u{1F68B}','\u{1F6B2}','\u{1F6F4}','\u{1F6F8}'],
    'Hewan':['\u{1F43E}','\u{1F981}','\u{1F430}','\u{1F431}','\u{1F436}','\u{1F98A}','\u{1F43B}','\u{1F43C}','\u{1F428}','\u{1F42F}','\u{1F438}','\u{1F435}','\u{1F414}','\u{1F984}','\u{1F432}','\u{1F98B}'],
    'Food':['\u2615','\u{1F354}','\u{1F355}','\u{1F35C}','\u{1F369}','\u{1F382}','\u{1F370}','\u{1F9CB}','\u{1F37A}','\u{1F942}','\u{1F377}','\u{1F9C3}','\u{1F964}','\u{1F366}','\u{1F36A}','\u{1F9C1}'],
    'Keren':['\u{1F48E}','\u{1F3C6}','\u{1F3AE}','\u{1F3A8}','\u{1F3B8}','\u{1F3B9}','\u{1F3BA}','\u{1F3AC}','\u{1F3AA}','\u{1F3A0}','\u{1F3A1}','\u{1F5FF}','\u{1F451}','\u{1F48E}','\u{1F52E}','\u{1F9FF}'],
    'Jaringan':['\u{1F4F6}','\u{1F310}','\u{1F4E1}','\u{1F517}','\u26A1','\u{1F6E1}\uFE0F','\u{1F50C}','\u{1F4BB}','\u{1F5A5}\uFE0F','\u{1F4E1}','\u{1F6F0}\uFE0F','\u{1F4E1}','\u{1F50B}','\u{1F50C}','\u{1F4A1}','\u2699\uFE0F']
};

function newElement(type){
    var d={type:type};
    switch(type){
        case'icon':d.emoji='\u{1F6AB}';d.bgColor='#dc2626';d.opacity=0.12;d.size=36;break;
        case'heading':d.text='Judul';d.color='#dc2626';d.size=22;d.weight=800;d.align='center';break;
        case'text':d.text='Teks deskripsi';d.color='#64748b';d.size=13;d.align='center';d.weight=400;break;
        case'divider':d.color='#e2e8f0';d.width=50;d.thickness=2;d.style='solid';break;
        case'spacer':d.height=16;break;
        case'image':d.src='';d.width=120;d.radius=8;d.imgAlign='center';d.posMode='flow';d.posX=50;d.posY=10;d.zIndex=5;d.imgOpacity=1;break;
        case'customer_name':d.label='NAMA PELANGGAN';d.labelColor='#94a3b8';d.valueColor='#1e293b';d.bgColor='#f1f5f9';d.radius=10;d.padding=12;break;
        case'invoice_box':d.label='TAGIHAN';d.labelColor='#94a3b8';d.valueColor='#1e293b';d.amountColor='#dc2626';d.bgColor='#f1f5f9';d.radius=10;d.showAmount=true;d.showDueDate=true;d.padding=12;break;
        case'wa_button':d.text='Konfirmasi via WhatsApp';d.bgColor='#25D366';d.textColor='#ffffff';d.size=13;d.weight=600;d.radius=12;break;
        case'footer':d.showCompany=true;d.companyColor='#64748b';d.companySize=12;d.text='Internet Cepat & Stabil';d.textColor='#94a3b8';d.textSize=10;break;
    }
    return d;
}

function addElement(type){T.elements.push(newElement(type));selIdx=T.elements.length-1;renderAll();}
function removeElement(i){T.elements.splice(i,1);if(selIdx>=T.elements.length)selIdx=T.elements.length-1;if(selIdx<0&&T.elements.length>0)selIdx=0;renderAll();}
function moveElement(i,dir){var ni=i+dir;if(ni<0||ni>=T.elements.length)return;var tmp=T.elements[i];T.elements[i]=T.elements[ni];T.elements[ni]=tmp;selIdx=ni;renderAll();}
function selectElement(i){selIdx=i;renderElemList();renderProps();updatePreviewSelection();}
function setViewport(m){viewportMode=m;var f=document.getElementById('previewFrame'),n=document.getElementById('phoneNotch'),bP=document.getElementById('vpPhone'),bD=document.getElementById('vpDesktop');if(m==='phone'){f.className='preview-frame phone';n.style.display='';bP.classList.add('active');bD.classList.remove('active');}else{f.className='preview-frame desktop';n.style.display='none';bD.classList.add('active');bP.classList.remove('active');}renderPreview();}

function renderAll(){renderElemList();renderProps();renderPreview();}

function renderElemList(){
    var c=document.getElementById('elemList'),cnt=document.getElementById('elemCount');
    cnt.textContent=T.elements.length>0?T.elements.length+' elemen':'';
    if(T.elements.length===0){c.innerHTML='<div class="ie-empty-msg">Klik elemen di atas untuk menambahkannya</div>';selIdx=-1;return;}
    var h='';
    T.elements.forEach(function(el,i){
        var m=EL_META[el.type]||{};var act=i===selIdx?' active':'';
        var posBadge=(el.type==='image'&&el.posMode==='absolute')?'<span class="ie-badge-pos">POS</span>':'';
        h+='<div class="ie-elem-item'+act+'" onclick="selectElement('+i+')" data-idx="'+i+'">';
        h+='<span class="drag-handle"><i class="fa-solid fa-grip-vertical"></i></span>';
        h+='<span class="elem-icon" style="background:'+(m.color||'#475569')+'22;color:'+(m.color||'#475569')+'">'+m.icon+'</span>';
        h+='<span class="elem-label">'+m.label+(m.dynamic?'<span class="ie-badge-dynamic">AUTO</span>':'')+posBadge+'</span>';
        h+='<span class="elem-actions">';
        h+='<span class="ea-up" onclick="event.stopPropagation();moveElement('+i+',-1)" title="Naik"><i class="fa-solid fa-chevron-up"></i></span>';
        h+='<span class="ea-down" onclick="event.stopPropagation();moveElement('+i+',1)" title="Turun"><i class="fa-solid fa-chevron-down"></i></span>';
        h+='<span class="ea-del" onclick="event.stopPropagation();removeElement('+i+')" title="Hapus"><i class="fa-solid fa-xmark"></i></span>';
        h+='</span></div>';
    });
    c.innerHTML=h;
    if(typeof Sortable!=='undefined'){try{if(sortableInstance)sortableInstance.destroy();sortableInstance=new Sortable(c,{handle:'.drag-handle',animation:150,ghostClass:'sortable-ghost',chosenClass:'sortable-chosen',onEnd:function(evt){if(evt.oldIndex===evt.newIndex)return;var item=T.elements.splice(evt.oldIndex,1)[0];T.elements.splice(evt.newIndex,0,item);if(selIdx===evt.oldIndex)selIdx=evt.newIndex;else if(evt.oldIndex<selIdx&&evt.newIndex>=selIdx)selIdx--;else if(evt.oldIndex>selIdx&&evt.newIndex<=selIdx)selIdx++;renderAll();}});}catch(e){}}
}

function renderProps(){
    var box=document.getElementById('elemProps');
    if(selIdx<0||selIdx>=T.elements.length){
        box.innerHTML='<div class="ie-section-label">Pengaturan Halaman</div>'+pageSettingsHtml()+'<div class="ie-empty-state" style="margin-top:16px;"><i class="fa-solid fa-hand-pointer"></i><div>Pilih elemen untuk mengedit</div></div>';
        return;
    }
    var el=T.elements[selIdx],m=EL_META[el.type]||{};
    var h='<div class="ie-prop-header"><span>'+m.label+(m.dynamic?'<span class="ie-badge-dynamic">AUTO</span>':'')+'</span>';
    h+='<button class="btn btn-sm btn-outline-danger" onclick="removeElement('+selIdx+')"><i class="fa-solid fa-trash"></i></button></div>';
    switch(el.type){
        case'icon':
            h+='<div class="ie-section-label">Pilih Ikon</div>';
            h+='<div class="icon-picker-trigger" onclick="openIconPicker('+selIdx+')"><span class="picked-icon">'+el.emoji+'</span><span class="picked-label">Klik untuk ganti ikon</span></div>';
            h+='<div class="ie-section-label">Pengaturan</div>';
            h+=propColor('Warna Lingkaran',el,'bgColor');h+=propRange('Opasitas Lingkaran',el,'opacity',0,1,0.05);h+=propNumber('Ukuran',el,'size',16,80);break;
        case'heading':h+=propText('Teks',el,'text');h+=propColor('Warna',el,'color');h+=propNumber('Ukuran Font',el,'size',12,40);h+=propSelect('Ketebalan',el,'weight',{400:'Normal',500:'Medium',600:'Semibold',700:'Bold',800:'Extra Bold',900:'Black'});h+=propSelect('Align',el,'align',{left:'Kiri',center:'Tengah',right:'Kanan'});break;
        case'text':h+=propTextarea('Teks',el,'text','Enter untuk baris baru');h+=propColor('Warna',el,'color');h+=propNumber('Ukuran Font',el,'size',8,24);h+=propSelect('Align',el,'align',{left:'Kiri',center:'Tengah',right:'Kanan'});h+=propSelect('Ketebalan',el,'weight',{400:'Normal',500:'Medium',600:'Semibold',700:'Bold'});break;
        case'divider':h+=propColor('Warna',el,'color');h+=propRange('Lebar (%)',el,'width',10,100,1);h+=propNumber('Ketebalan',el,'thickness',1,6);h+=propSelect('Gaya',el,'style',{solid:'Solid',dashed:'Dashed',dotted:'Dotted'});break;
        case'spacer':h+=propNumber('Tinggi (px)',el,'height',4,80);break;
        case'image':
            h+='<div class="ie-section-label">Gambar</div>';
            h+=imageUploadZone(el);
            h+='<div class="ie-section-label">Mode Posisi</div>';
            h+=propSelect('Posisi',el,'posMode',{flow:'Alur (Flow)',absolute:'Bebas (Absolute)'});
            if(el.posMode==='absolute'){
                h+='<div class="ie-section-label">Koordinat</div>';
                h+=propRange('Posisi X (%)',el,'posX',0,100,1);h+=propRange('Posisi Y (%)',el,'posY',0,100,1);
                h+=propNumber('Z-Index',el,'zIndex',1,20);
            }
            h+='<div class="ie-section-label">Pengaturan</div>';
            h+=propNumber('Lebar (px)',el,'width',20,900);h+=propNumber('Radius',el,'radius',0,40);h+=propRange('Opacity',el,'imgOpacity',0,1,0.05);
            if(el.src){h+='<div style="margin-top:8px;"><img src="'+escH(el.src)+'" class="img-preview-thumb" onerror="this.style.display=\'none\'"></div>';}
            break;
        case'customer_name':h+=propText('Label',el,'label');h+=propColor('Warna Label',el,'labelColor');h+=propColor('Warna Nama',el,'valueColor');h+=propColor('Background',el,'bgColor');h+=propNumber('Radius',el,'radius',0,20);break;
        case'invoice_box':h+=propText('Label',el,'label');h+=propColor('Warna Label',el,'labelColor');h+=propColor('Warna Teks',el,'valueColor');h+=propColor('Warna Nominal',el,'amountColor');h+=propColor('Background',el,'bgColor');h+=propNumber('Radius',el,'radius',0,20);h+=propToggle('Nominal',el,'showAmount');h+=propToggle('Jatuh Tempo',el,'showDueDate');break;
        case'wa_button':h+=propText('Teks Tombol',el,'text');h+=propColor('Warna Tombol',el,'bgColor');h+=propColor('Warna Teks',el,'textColor');h+=propNumber('Ukuran Font',el,'size',10,20);h+=propSelect('Ketebalan',el,'weight',{400:'Normal',500:'Medium',600:'Semibold',700:'Bold'});h+=propNumber('Radius',el,'radius',0,30);break;
        case'footer':h+=propToggle('Nama Perusahaan',el,'showCompany');h+=propColor('Warna Perusahaan',el,'companyColor');h+=propNumber('Ukuran Perusahaan',el,'companySize',8,20);h+=propText('Teks Footer',el,'text');h+=propColor('Warna Teks',el,'textColor');h+=propNumber('Ukuran Teks',el,'textSize',7,16);break;
    }
    box.innerHTML=h;
    setTimeout(initDragDrop,50);
}

function imageUploadZone(el){
    var uid='imgUpload_'+selIdx;
    var h='<div class="img-upload-zone" id="'+uid+'">';
    h+='<i class="fa-solid fa-cloud-arrow-up"></i>';
    h+='<div class="uz-text">Klik atau seret gambar ke sini<br>Format: JPG, PNG, WebP (maks 5MB)</div>';
    h+='<input type="file" accept="image/*" onchange="handleImageUpload(this,'+selIdx+')">';
    h+='</div>';
    h+=propText('Atau URL Manual',el,'src','https://...');
    return h;
}

function handleImageUpload(input,idx){
    var file=input.files[0];
    if(!file)return;
    if(file.size>5*1024*1024){showToast('Ukuran gambar maks 5MB','danger');return;}
    var fd=new FormData();fd.append('image',file);
    fetch('{{route("settings.isolir-image.upload")}}',{method:'POST',headers:{'X-CSRF-TOKEN':'{{csrf_token()}}'},body:fd})
    .then(function(r){return r.json();})
    .then(function(d){if(d.success){T.elements[idx].src=d.url;renderPreview();renderProps();showToast('Gambar berhasil diupload','success');}else{showToast('Gagal upload','danger');}})
    .catch(function(e){showToast('Error: '+e.message,'danger');});
}

function initDragDrop(){
    document.querySelectorAll('.img-upload-zone').forEach(function(zone){
        zone.addEventListener('dragover',function(e){e.preventDefault();zone.classList.add('dragover');});
        zone.addEventListener('dragleave',function(){zone.classList.remove('dragover');});
        zone.addEventListener('drop',function(e){
            e.preventDefault();zone.classList.remove('dragover');
            var file=e.dataTransfer.files[0];
            if(!file||!file.type.startsWith('image/'))return;
            if(file.size>5*1024*1024){showToast('Ukuran gambar maks 5MB','danger');return;}
            var fd=new FormData();fd.append('image',file);
            fetch('{{route("settings.isolir-image.upload")}}',{method:'POST',headers:{'X-CSRF-TOKEN':'{{csrf_token()}}'},body:fd})
            .then(function(r){return r.json();})
            .then(function(d){if(d.success){T.elements[selIdx].src=d.url;renderPreview();renderProps();showToast('Gambar berhasil diupload','success');}else{showToast('Gagal upload','danger');}})
            .catch(function(err){showToast('Error: '+err.message,'danger');});
        });
    });
}

function pageSettingsHtml(){
    var h='';
    h+='<div class="ie-prop-group"><label class="ie-prop-label">Background Atas</label><input type="color" class="ie-prop-input" value="'+T.background.start+'" onchange="T.background.start=this.value;renderPreview()"></div>';
    h+='<div class="ie-prop-group"><label class="ie-prop-label">Background Bawah</label><input type="color" class="ie-prop-input" value="'+T.background.end+'" onchange="T.background.end=this.value;renderPreview()"></div>';
    h+='<div class="ie-prop-group"><label class="ie-prop-label">Transparansi Background: <span id="rv_bgOpacity">'+Math.round((T.background.opacity||1)*100)+'%</span></label><input type="range" class="form-range" value="'+(T.background.opacity||1)+'" min="0" max="1" step="0.05" oninput="T.background.opacity=+this.value;document.getElementById(\'rv_bgOpacity\').textContent=Math.round(this.value*100)+\'%\';renderPreview()"></div>';
    h+='<div class="ie-prop-group"><label class="ie-prop-label">Warna Card</label><input type="color" class="ie-prop-input" value="'+T.card.bg+'" onchange="T.card.bg=this.value;renderPreview()"></div>';
    h+='<div class="ie-prop-row">';
    h+='<div class="ie-prop-group"><label class="ie-prop-label">Radius Card</label><input type="number" class="ie-prop-input" value="'+T.card.radius+'" min="0" max="40" onchange="T.card.radius=+this.value;renderPreview()"></div>';
    h+='<div class="ie-prop-group"><label class="ie-prop-label">Bayangan</label><div class="form-check form-switch mt-1"><input class="form-check-input" type="checkbox" '+(T.card.shadow?'checked':'')+' onchange="T.card.shadow=this.checked;renderPreview()"></div></div>';
    h+='</div>';
    return h;
}

function propText(l,el,k,ph){return'<div class="ie-prop-group"><label class="ie-prop-label">'+l+'</label><input class="ie-prop-input" type="text" value="'+escH(el[k]||'')+'" oninput="updateProp(\''+k+'\',this.value)"'+(ph?' placeholder="'+ph+'"':'')+'></div>';}
function propTextarea(l,el,k,ph){return'<div class="ie-prop-group"><label class="ie-prop-label">'+l+'</label><textarea class="ie-prop-input" rows="3" oninput="updateProp(\''+k+'\',this.value)"'+(ph?' placeholder="'+ph+'"':'')+'>'+escH(el[k]||'')+'</textarea></div>';}
function propColor(l,el,k){return'<div class="ie-prop-group"><label class="ie-prop-label">'+l+'</label><input type="color" class="ie-prop-input" value="'+(el[k]||'#000000')+'" oninput="updateProp(\''+k+'\',this.value)"></div>';}
function propNumber(l,el,k,mn,mx){return'<div class="ie-prop-group"><label class="ie-prop-label">'+l+'</label><input type="number" class="ie-prop-input" value="'+(el[k]||0)+'" min="'+mn+'" max="'+mx+'" oninput="updateProp(\''+k+'\',+this.value)"></div>';}
function propRange(l,el,k,mn,mx,st){return'<div class="ie-prop-group"><label class="ie-prop-label">'+l+': <span id="rv_'+k+'">'+(el[k]||0)+'</span></label><input type="range" class="form-range" value="'+(el[k]||0)+'" min="'+mn+'" max="'+mx+'" step="'+st+'" oninput="updateProp(\''+k+'\',+this.value);var v=this.value;if(\''+k+'\'.indexOf(\'Opacity\')>-1||\''+k+'\'.indexOf(\'opacity\')>-1)document.getElementById(\'rv_'+k+'\').textContent=Math.round(v*100)+\'%\';else document.getElementById(\'rv_'+k+'\').textContent=v"></div>';}
function propSelect(l,el,k,opts){var h='<div class="ie-prop-group"><label class="ie-prop-label">'+l+'</label><select class="ie-prop-input" onchange="updateProp(\''+k+'\',this.value)"><option value="">-- Pilih --</option>';for(var k2 in opts){h+='<option value="'+k2+'"'+(String(el[k]||'')===k2?' selected':'')+'>'+opts[k2]+'</option>';}return h+'</select></div>';}
function propToggle(l,el,k){return'<div class="ie-prop-group"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" '+(el[k]?'checked':'')+' onchange="updateProp(\''+k+'\',this.checked)"><label class="form-check-label ie-prop-label" style="margin-left:28px;">'+l+'</label></div></div>';}
function updateProp(k,v){if(selIdx<0||selIdx>=T.elements.length)return;T.elements[selIdx][k]=v;renderPreview();if(k==='posMode'||k==='src')renderProps();}
function escH(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}

function openIconPicker(idx){iconPickerTarget=idx;var el=T.elements[idx];var body=document.getElementById('iconPickerBody');var h='<div class="icon-picker-grid">';for(var cat in ICONS){h+='<div class="icon-picker-cat">'+cat+'</div>';ICONS[cat].forEach(function(ic){var sel=el.emoji===ic?' style="background:#334155;border-color:#3b82f6;"':'';h+='<span'+sel+' onclick="pickIcon(\''+ic+'\')">'+ic+'</span>';});}h+='</div>';body.innerHTML=h;new bootstrap.Modal(document.getElementById('iconPickerModal')).show();}
function pickIcon(ic){if(iconPickerTarget>=0&&iconPickerTarget<T.elements.length){T.elements[iconPickerTarget].emoji=ic;renderPreview();renderProps();}bootstrap.Modal.getInstance(document.getElementById('iconPickerModal')).hide();}

/* ══════════════════════════════════════════════════════
   SVG PREVIEW with Interactive Overlays
   ══════════════════════════════════════════════════════ */
var pvScale=1;
function renderPreview(){
    var vpW=viewportMode==='phone'?375:900,P=24,CW=vpW-P*2,CH=40;
    var bgS=T.background.start,bgE=T.background.end,bgO=T.background.opacity||1;
    var cardBg=T.card.bg,cardR=T.card.radius,cardShadow=T.card.shadow;
    var light=isLight(cardBg),defText=light?'#1e293b':'#e2e8f0',defSub=light?'#64748b':'#94a3b8',infoBg=light?'#f8fafc':'rgba(255,255,255,0.08)';
    var elRects=[];

    var svg='<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '+vpW+' 100" width="'+vpW+'">';
    svg+='<defs><linearGradient id="bgr" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="'+bgS+'"/><stop offset="100%" stop-color="'+bgE+'"/></linearGradient></defs>';
    svg+='<rect width="'+vpW+'" height="100" fill="url(#bgr)" opacity="'+bgO+'"/>';

    var flowElements=[],absImages=[];
    T.elements.forEach(function(el,idx){
        if(el.type==='image'&&el.posMode==='absolute')absImages.push({el:el,idx:idx});
        else flowElements.push({el:el,idx:idx});
    });

    if(cardShadow)svg+='<rect x="'+P+'" y="26" width="'+CW+'" height="12" rx="'+cardR+'" fill="rgba(0,0,0,0.12)"/>';

    flowElements.forEach(function(item){
        var el=item.el,idx=item.idx;
        var x=P+20,w=CW-40,cx=P+CW/2;
        var elStart=CH;
        svg+='<g class="pv-el'+(selIdx===idx?' selected':'')+'" data-idx="'+idx+'" onclick="event.stopPropagation();selectElement('+idx+')">';
        svg+='<rect class="pv-el-border" x="'+P+'" y="'+elStart+'" width="'+CW+'" height="1" fill="transparent"/>';
        switch(el.type){
            case'icon':{var sz=el.size||36;svg+='<circle cx="'+cx+'" cy="'+(CH+sz/2+4)+'" r="'+(sz/2+8)+'" fill="'+el.bgColor+'" opacity="'+el.opacity+'"/>';svg+='<text x="'+cx+'" y="'+(CH+sz/2+10)+'" text-anchor="middle" font-size="'+sz+'" font-family="system-ui,sans-serif">'+el.emoji+'</text>';CH+=sz+32;elRects.push({idx:idx,y:elStart,h:sz+32});break;}
            case'heading':{var fw=el.weight||800,fs=el.size||22,al=el.align||'center';var tx=al==='left'?x:al==='right'?(x+w):cx;var an=al==='left'?'start':al==='right'?'end':'middle';svg+='<text x="'+tx+'" y="'+(CH+fs)+'" text-anchor="'+an+'" font-weight="'+fw+'" font-size="'+fs+'" fill="'+el.color+'" font-family="system-ui,sans-serif">'+escSvg(el.text||'')+'</text>';CH+=fs+12;elRects.push({idx:idx,y:elStart,h:fs+12});break;}
            case'text':{var ls=(el.text||'').split('\n'),fs2=el.size||13,al2=el.align||'center',fw2=el.weight||400;var tx2=al2==='left'?x:al2==='right'?(x+w):cx;var an2=al2==='left'?'start':al2==='right'?'end':'middle';var txtH=0;ls.forEach(function(l){svg+='<text x="'+tx2+'" y="'+(CH+fs2+2)+'" text-anchor="'+an2+'" font-size="'+fs2+'" font-weight="'+fw2+'" fill="'+el.color+'" font-family="system-ui,sans-serif">'+escSvg(l)+'</text>';CH+=fs2+4;txtH+=fs2+4;});CH+=8;elRects.push({idx:idx,y:elStart,h:txtH+8});break;}
            case'divider':{var dw=(w*(el.width||50))/100,dx=cx-dw/2,ds=el.style||'solid';var da=ds==='dashed'?' stroke-dasharray="8,4"':ds==='dotted'?' stroke-dasharray="2,4"':'';svg+='<line x1="'+dx+'" y1="'+(CH+8)+'" x2="'+(dx+dw)+'" y2="'+(CH+8)+'" stroke="'+el.color+'" stroke-width="'+(el.thickness||2)+'"'+da+'/>';CH+=20;elRects.push({idx:idx,y:elStart,h:20});break;}
            case'spacer':var spH=(el.height||16);CH+=spH;elRects.push({idx:idx,y:elStart,h:spH});break;
            case'customer_name':{var bH=56,bR=el.radius||10,pad=el.padding||12;svg+='<rect x="'+P+'" y="'+CH+'" width="'+CW+'" height="'+bH+'" rx="'+bR+'" fill="'+el.bgColor+'"/>';svg+='<text x="'+(P+pad)+'" y="'+(CH+18)+'" font-size="9" fill="'+el.labelColor+'" font-family="system-ui,sans-serif">'+escSvg(el.label||'NAMA PELANGGAN')+'</text>';svg+='<text x="'+(P+pad)+'" y="'+(CH+38)+'" font-size="14" font-weight="600" fill="'+el.valueColor+'" font-family="system-ui,sans-serif">Contoh Pelanggan</text>';CH+=bH+10;elRects.push({idx:idx,y:elStart,h:bH+10});break;}
            case'invoice_box':{var bH2=el.showAmount?68:48,bR2=el.radius||10,pad2=el.padding||12;svg+='<rect x="'+P+'" y="'+CH+'" width="'+CW+'" height="'+bH2+'" rx="'+bR2+'" fill="'+el.bgColor+'"/>';svg+='<text x="'+(P+pad2)+'" y="'+(CH+18)+'" font-size="9" fill="'+el.labelColor+'" font-family="system-ui,sans-serif">'+escSvg(el.label||'TAGIHAN')+'</text>';svg+='<text x="'+(P+pad2)+'" y="'+(CH+36)+'" font-size="13" font-weight="600" fill="'+el.valueColor+'" font-family="system-ui,sans-serif">INV-20260701</text>';if(el.showAmount)svg+='<text x="'+(P+CW-pad2)+'" y="'+(CH+40)+'" text-anchor="end" font-size="16" font-weight="800" fill="'+el.amountColor+'" font-family="system-ui,sans-serif">Rp 150.000</text>';if(el.showDueDate)svg+='<text x="'+(P+pad2)+'" y="'+(CH+56)+'" font-size="11" fill="'+el.labelColor+'" font-family="system-ui,sans-serif">Jatuh Tempo: 05 Jul 2026</text>';CH+=bH2+10;elRects.push({idx:idx,y:elStart,h:bH2+10});break;}
            case'wa_button':{var bH3=44,bR3=el.radius||12;svg+='<rect x="'+P+'" y="'+CH+'" width="'+CW+'" height="'+bH3+'" rx="'+bR3+'" fill="'+el.bgColor+'"/>';svg+='<text x="'+cx+'" y="'+(CH+28)+'" text-anchor="middle" font-size="'+(el.size||13)+'" font-weight="'+(el.weight||600)+'" fill="'+el.textColor+'" font-family="system-ui,sans-serif">'+escSvg(el.text||'WhatsApp')+'</text>';CH+=bH3+10;elRects.push({idx:idx,y:elStart,h:bH3+10});break;}
            case'image':{var iw2=Math.min(el.width||120,w),ial2=el.imgAlign||'center';var ix2=ial2==='left'?x:ial2==='right'?(x+w-iw2):cx-iw2/2;var ih2=iw2*0.6;if(el.src){svg+='<image href="'+escSvg(el.src)+'" x="'+ix2+'" y="'+CH+'" width="'+iw2+'" height="'+ih2+'" rx="'+(el.radius||0)+'" opacity="'+(el.imgOpacity||1)+'" preserveAspectRatio="xMidYMid slice"/>';}else{svg+='<rect x="'+ix2+'" y="'+CH+'" width="'+iw2+'" height="'+ih2+'" rx="8" fill="#334155" opacity="0.4"/>';svg+='<text x="'+cx+'" y="'+(CH+ih2/2+4)+'" text-anchor="middle" font-size="11" fill="#64748b" font-family="system-ui,sans-serif">[ Gambar ]</text>';}CH+=ih2+12;elRects.push({idx:idx,y:elStart,h:ih2+12});break;}
            case'footer':{if(el.showCompany){svg+='<text x="'+cx+'" y="'+(CH+12)+'" text-anchor="middle" font-size="'+(el.companySize||12)+'" font-weight="600" fill="'+el.companyColor+'" font-family="system-ui,sans-serif">PT Alkonek</text>';CH+=el.companySize+6;}svg+='<text x="'+cx+'" y="'+(CH+10)+'" text-anchor="middle" font-size="'+(el.textSize||10)+'" fill="'+el.textColor+'" font-family="system-ui,sans-serif">'+escSvg(el.text||'')+'</text>';CH+=el.textSize+10;elRects.push({idx:idx,y:elStart,h:el.textSize+10});break;}
        }
        svg+='</g>';
    });

    CH+=20;
    absImages.forEach(function(item){
        var el=item.el,idx=item.idx;
        if(!el.src)return;
        var iw=Math.min(el.width||120,CW);
        var ix=P+(CW*(el.posX||50))/100-iw/2;
        var iy=20+(CH*(el.posY||10))/100;
        var ih=iw*0.6;
        svg+='<g class="pv-el'+(selIdx===idx?' selected':'')+'" data-idx="'+idx+'" onclick="event.stopPropagation();selectElement('+idx+')">';
        svg+='<rect class="pv-el-border" x="'+ix+'" y="'+iy+'" width="'+iw+'" height="'+ih+'" fill="transparent"/>';
        svg+='<image href="'+escSvg(el.src)+'" x="'+ix+'" y="'+iy+'" width="'+iw+'" height="'+ih+'" rx="'+(el.radius||0)+'" opacity="'+(el.imgOpacity||1)+'" preserveAspectRatio="xMidYMid slice"/>';
        svg+='</g>';
        elRects.push({idx:idx,y:iy,h:ih});
    });

    svg+='</svg>';
    svg=svg.replace('viewBox="0 0 '+vpW+' 100"','viewBox="0 0 '+vpW+' '+CH+'"').replace('height="100"','height="'+CH+'"');
    document.getElementById('preview').innerHTML=svg;

    var wrap=document.getElementById('previewWrap');
    var svgEl=wrap.querySelector('svg');
    var svgRect=svgEl.getBoundingClientRect();
    pvScale=svgRect.height/CH;

    var ov=document.getElementById('pvOverlays');
    ov.innerHTML='';
    elRects.forEach(function(r){
        var div=document.createElement('div');
        div.className='pv-overlay show';
        div.style.left='0';
        div.style.right='0';
        div.style.top=Math.round(r.y*pvScale)+'px';
        div.style.height=Math.round(r.h*pvScale)+'px';
        var realIdx=r.idx;
        var el=T.elements[realIdx];
        var m=EL_META[el.type]||{};
        var canEdit=(el.type==='heading'||el.type==='text'||el.type==='footer'||el.type==='wa_button'||el.type==='customer_name'||el.type==='invoice_box');
        var canResize=(el.type==='image'||el.type==='spacer');
        div.innerHTML='<div class="pv-del" onclick="event.stopPropagation();removeElement('+realIdx+')" title="Hapus"><i class="fa-solid fa-xmark"></i></div>'
            +'<div class="pv-drag" data-pv-drag="'+realIdx+'" title="Seret"><i class="fa-solid fa-grip-vertical"></i></div>'
            +(canResize?'<div class="pv-resize" data-pv-resize="'+realIdx+'" title="Ubah ukuran"><i class="fa-solid fa-up-right-and-down-left-from-center"></i></div>':'')
            +(canEdit?'<div class="pv-dblclick">klik 2x edit</div>':'');
        ov.appendChild(div);
    });

    initPvDrag();
    initPvResize();
    initPvDblClick();
    initPvBgClick();
}

function escSvg(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function isLight(h){h=h.replace('#','');if(h.length===3)h=h[0]+h[0]+h[1]+h[1]+h[2]+h[2];if(h.length<6)return true;var r=parseInt(h.substr(0,2),16),g=parseInt(h.substr(2,2),16),b=parseInt(h.substr(4,2),16);return(r*299+g*587+b*114)/1000>150;}

function updatePreviewSelection(){
    document.querySelectorAll('#preview .pv-el').forEach(function(g){
        var i=parseInt(g.getAttribute('data-idx'));
        g.classList.toggle('selected',i===selIdx);
    });
}

/* ── Preview: Drag to Reorder ── */
function initPvDrag(){
    document.querySelectorAll('[data-pv-drag]').forEach(function(handle){
        handle.addEventListener('mousedown',function(e){
            e.preventDefault();e.stopPropagation();
            var idx=parseInt(handle.getAttribute('data-pv-drag'));
            var startY=e.clientY,startIdx=idx;
            var el=T.elements[idx];
            handle.classList.add('pv-drag-active');
            document.body.style.cursor='grabbing';
            document.body.style.userSelect='none';

            function onMove(ev){
                var dy=ev.clientY-startY;
                var steps=Math.round(dy/(20));
                var newIdx=Math.max(0,Math.min(T.elements.length-1,startIdx+steps));
                if(newIdx!==selIdx){
                    var item=T.elements.splice(startIdx,1)[0];
                    T.elements.splice(newIdx,0,item);
                    selIdx=newIdx;
                    startIdx=newIdx;
                    startY=ev.clientY;
                    renderAll();
                }
            }
            function onUp(){
                handle.classList.remove('pv-drag-active');
                document.body.style.cursor='';
                document.body.style.userSelect='';
                document.removeEventListener('mousemove',onMove);
                document.removeEventListener('mouseup',onUp);
                renderAll();
            }
            document.addEventListener('mousemove',onMove);
            document.addEventListener('mouseup',onUp);
        });
    });
}

/* ── Preview: Resize (image & spacer) ── */
function initPvResize(){
    document.querySelectorAll('[data-pv-resize]').forEach(function(handle){
        handle.addEventListener('mousedown',function(e){
            e.preventDefault();e.stopPropagation();
            var idx=parseInt(handle.getAttribute('data-pv-resize'));
            var el=T.elements[idx];
            var startY=e.clientY,startVal;
            if(el.type==='image')startVal=el.width||120;
            else if(el.type==='spacer')startVal=el.height||16;
            else return;
            document.body.style.cursor='nwse-resize';
            document.body.style.userSelect='none';

            function onMove(ev){
                var dy=ev.clientY-startY;
                var step=Math.round(dy/2);
                if(el.type==='image'){
                    el.width=Math.max(20,Math.min(900,startVal+step));
                }else if(el.type==='spacer'){
                    el.height=Math.max(4,Math.min(80,startVal+step));
                }
                renderPreview();
            }
            function onUp(){
                document.body.style.cursor='';
                document.body.style.userSelect='';
                document.removeEventListener('mousemove',onMove);
                document.removeEventListener('mouseup',onUp);
                renderProps();
            }
            document.addEventListener('mousemove',onMove);
            document.addEventListener('mouseup',onUp);
        });
    });
}

/* ── Preview: Double-click to Edit Text Inline ── */
function initPvDblClick(){
    document.querySelectorAll('#pvOverlays .pv-dblclick').forEach(function(label){
        label.parentElement.addEventListener('dblclick',function(e){
            e.preventDefault();e.stopPropagation();
            var idx=parseInt(label.parentElement.querySelector('.pv-drag').getAttribute('data-pv-drag'));
            var el=T.elements[idx];
            var key=getEditableKey(el);
            if(!key)return;
            openInlineEditor(idx,key,label.parentElement);
        });
    });
}

function getEditableKey(el){
    switch(el.type){
        case'heading':return'text';
        case'text':return'text';
        case'footer':return'text';
        case'wa_button':return'text';
        case'customer_name':return'label';
        case'invoice_box':return'label';
        case'heading':return'text';
        default:return null;
    }
}

function openInlineEditor(idx,key,overlayEl){
    var el=T.elements[idx];
    var val=el[key]||'';
    var ta=document.createElement('textarea');
    ta.className='pv-inline-editor';
    ta.value=val;
    ta.style.left='10px';
    ta.style.right='10px';
    ta.style.top='2px';
    ta.style.minHeight=Math.max(30,overlayEl.offsetHeight-4)+'px';
    overlayEl.appendChild(ta);
    ta.focus();
    ta.select();

    function finish(){
        el[key]=ta.value;
        ta.remove();
        renderAll();
    }
    ta.addEventListener('blur',finish);
    ta.addEventListener('keydown',function(ev){
        if(ev.key==='Escape')finish();
        if(ev.key==='Enter'&&!ev.shiftKey){ev.preventDefault();finish();}
    });
}

/* ── Preview: Click Background to Deselect ── */
function initPvBgClick(){
    var pw=document.getElementById('previewWrap');
    pw.addEventListener('click',function(e){
        if(e.target===pw||e.target.id==='preview'||e.target.tagName==='svg'||e.target.closest('svg')&&!e.target.closest('.pv-el')){
            selIdx=-1;renderElemList();renderProps();updatePreviewSelection();
        }
    });
}

/* ── TEMPLATE MANAGEMENT ── */
function saveTemplate(){
    var btn=document.getElementById('btnSave');btn.innerHTML='<i class="fa-solid fa-spinner fa-spin me-1"></i>Menyimpan...';btn.disabled=true;
    var payload={template:T,id:currentTemplateId};
    fetch('{{route("settings.isolir-editor.save")}}',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{csrf_token()}}'},body:JSON.stringify(payload)})
    .then(function(r){return r.json();})
    .then(function(d){btn.innerHTML='<i class="fa-solid fa-floppy-disk me-1"></i>Simpan';btn.disabled=false;
        if(d.success){currentTemplateId=d.id;showToast(d.name?'Template "'+d.name+'" tersimpan!':'Tersimpan!','success');refreshTemplateList(d.id);}
        else showToast('Gagal: '+d.message,'danger');
    }).catch(function(e){btn.innerHTML='<i class="fa-solid fa-floppy-disk me-1"></i>Simpan';btn.disabled=false;showToast('Error: '+e.message,'danger');});
}

function refreshTemplateList(selectId){
    fetch('{{route("settings.isolir-templates.list")}}').then(function(r){return r.json();}).then(function(d){
        if(!d.success)return;
        var sel=document.getElementById('tplSelect');sel.innerHTML='';
        d.templates.forEach(function(t){var o=document.createElement('option');o.value=t.id;o.textContent=t.name+(t.is_active?' (Aktif)':'');if(t.id==selectId||t.id===currentTemplateId)o.selected=true;sel.appendChild(o);});
    });
}

function switchTemplate(id){
    if(!id)return;
    fetch('{{route("settings.isolir-templates.load")}}',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{csrf_token()}}'},body:JSON.stringify({id:id})})
    .then(function(r){return r.json();})
    .then(function(d){
        if(d.success&&d.template&&d.template.elements){T=d.template;if(!T.background)T.background={start:'#0f172a',end:'#1e293b',opacity:1};if(!T.card)T.card={bg:'#ffffff',radius:20,shadow:true};if(!T.background.opacity&&T.background.opacity!==0)T.background.opacity=1;}
        currentTemplateId=+id;selIdx=-1;renderAll();showToast('Template dimuat','info');
    });
}

function newTemplate(){
    var name=prompt('Nama template baru:','Template Baru');
    if(!name)return;
    fetch('{{route("settings.isolir-editor.save")}}',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{csrf_token()}}'},body:JSON.stringify({template:T,name:name})})
    .then(function(r){return r.json();})
    .then(function(d){if(d.success){currentTemplateId=d.id;refreshTemplateList(d.id);showToast('Template "'+d.name+'" dibuat','success');}});
}

function renameTemplate(){
    if(!currentTemplateId){showToast('Pilih template dulu','warning');return;}
    var sel=document.getElementById('tplSelect');
    var oldName=sel.options[sel.selectedIndex]?.text.replace(' (Aktif)','')||'';
    var name=prompt('Nama baru:',oldName);
    if(!name)return;
    fetch('{{route("settings.isolir-editor.save")}}',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{csrf_token()}}'},body:JSON.stringify({template:T,name:name,id:currentTemplateId})})
    .then(function(r){return r.json();})
    .then(function(d){if(d.success){refreshTemplateList(d.id);showToast('Berhasil diganti nama','success');}});
}

function duplicateTemplate(){
    if(!currentTemplateId){showToast('Pilih template dulu','warning');return;}
    fetch('{{route("settings.isolir-templates.duplicate")}}',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{csrf_token()}}'},body:JSON.stringify({id:currentTemplateId})})
    .then(function(r){return r.json();})
    .then(function(d){if(d.success){currentTemplateId=d.id;refreshTemplateList(d.id);showToast('Template diduplikat','success');}});
}

function deleteTemplate(){
    if(!currentTemplateId){showToast('Pilih template dulu','warning');return;}
    if(!confirm('Yakin hapus template ini?'))return;
    fetch('{{route("settings.isolir-templates.delete")}}',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{csrf_token()}}'},body:JSON.stringify({id:currentTemplateId})})
    .then(function(r){return r.json();})
    .then(function(d){if(d.success){currentTemplateId=null;refreshTemplateList();showToast('Template dihapus','success');}});
}

function activateTemplate(){
    if(!currentTemplateId){showToast('Pilih template dulu','warning');return;}
    fetch('{{route("settings.isolir-templates.activate")}}',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{csrf_token()}}'},body:JSON.stringify({id:currentTemplateId})})
    .then(function(r){return r.json();})
    .then(function(d){if(d.success){refreshTemplateList(currentTemplateId);showToast('Template dijadikan aktif!','success');}});
}

function loadDefaultTemplate(){
    T={background:{start:'#0f172a',end:'#1e293b',opacity:1},card:{bg:'#ffffff',radius:20,shadow:true},elements:[
        {type:'icon',emoji:'\u{1F6AB}',bgColor:'#dc2626',opacity:0.12,size:36},
        {type:'heading',text:'Internet Terisolir',color:'#dc2626',size:22,weight:800,align:'center'},
        {type:'text',text:'Akun Anda sedang ditangguhkan.\nSilakan hubungi admin untuk pembayaran.',color:'#64748b',size:13,align:'center',weight:400},
        {type:'divider',color:'#e2e8f0',width:50,thickness:2,style:'solid'},
        {type:'spacer',height:8},
        {type:'customer_name',label:'NAMA PELANGGAN',labelColor:'#94a3b8',valueColor:'#1e293b',bgColor:'#f1f5f9',radius:10,padding:12},
        {type:'invoice_box',label:'TAGIHAN',labelColor:'#94a3b8',valueColor:'#1e293b',amountColor:'#dc2626',bgColor:'#f1f5f9',radius:10,showAmount:true,showDueDate:true,padding:12},
        {type:'wa_button',text:'Konfirmasi via WhatsApp',bgColor:'#25D366',textColor:'#ffffff',size:13,weight:600,radius:12},
        {type:'footer',showCompany:true,companyColor:'#64748b',companySize:12,text:'Internet Cepat & Stabil',textColor:'#94a3b8',textSize:10}
    ]};selIdx=-1;renderAll();showToast('Template default dimuat','info');
}

function showToast(m,t){var d=document.createElement('div');d.className='alert alert-'+t+' position-fixed top-0 end-0 m-3 shadow';d.style.cssText='z-index:9999;font-size:.85rem;min-width:250px;';d.textContent=m;document.body.appendChild(d);setTimeout(function(){d.remove();},3000);}

(function(){
    var saved=@json($isolirTemplate);
    if(saved&&saved.background&&saved.elements&&saved.elements.length>0){
        if(!saved.background.opacity&&saved.background.opacity!==0)saved.background.opacity=1;
        saved.elements.forEach(function(el){if(el.type==='image'){if(!el.posMode)el.posMode='flow';if(!el.posX&&el.posX!==0)el.posX=50;if(!el.posY&&el.posY!==0)el.posY=10;if(!el.zIndex)el.zIndex=5;if(!el.imgOpacity&&el.imgOpacity!==0)el.imgOpacity=1;}});
        T=saved;
    }else{T={background:{start:'#0f172a',end:'#1e293b',opacity:1},card:{bg:'#ffffff',radius:20,shadow:true},elements:[]};}
    renderAll();
    setTimeout(function(){initDragDrop();},100);
})();
</script>
@endpush
