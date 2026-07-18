@php
    $item = $item ?? null;
    $categories = \App\Models\InventoryItem::CATEGORIES;
    $units = \App\Models\InventoryItem::UNITS;
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label fw-semibold small">Kategori <span class="text-danger">*</span></label>
        <select name="category" id="add_category" class="form-select @error('category') is-invalid @enderror" required onchange="toggleFields(this.value)">
            <option value="">Pilih Kategori</option>
            @foreach($categories as $key => $label)
                <option value="{{ $key }}" {{ ($item && $item->category === $key) ? 'selected' : (old('category') === $key ? 'selected' : '') }}>{{ $label }}</option>
            @endforeach
        </select>
        @error('category') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold small">Nama Barang <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name', $item->name ?? '') }}" placeholder="Contoh: OLT C-DATA FD1601S" required>
        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold small">Merek</label>
        <input type="text" name="brand" class="form-control @error('brand') is-invalid @enderror"
               value="{{ old('brand', $item->brand ?? '') }}" placeholder="Contoh: C-DATA, Huawei, MikroTik">
        @error('brand') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold small">Type / Model</label>
        <input type="text" name="type" class="form-control @error('type') is-invalid @enderror"
               value="{{ old('type', $item->type ?? '') }}" placeholder="Contoh: FD1601S, RB3011, HG8245H5">
        @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    {{-- Serial Number (untuk OLT & ONT/Modem) --}}
    <div class="col-md-6 field-sn" style="display:none;">
        <label class="form-label fw-semibold small">Serial Number</label>
        <input type="text" name="serial_number" class="form-control @error('serial_number') is-invalid @enderror"
               value="{{ old('serial_number', $item->serial_number ?? '') }}" placeholder="SN perangkat">
        @error('serial_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    {{-- OLT: Port PON --}}
    <div class="col-md-6 field-olt" style="display:none;">
        <label class="form-label fw-semibold small">Jumlah Port PON</label>
        <input type="number" name="pon_port_count" class="form-control @error('pon_port_count') is-invalid @enderror"
               value="{{ old('pon_port_count', $item->pon_port_count ?? '') }}" placeholder="Contoh: 16" min="0">
        @error('pon_port_count') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    {{-- OTB/ODC/ODP: Port Count --}}
    <div class="col-md-6 field-port" style="display:none;">
        <label class="form-label fw-semibold small">Jumlah Port</label>
        <input type="number" name="port_count" class="form-control @error('port_count') is-invalid @enderror"
               value="{{ old('port_count', $item->port_count ?? '') }}" placeholder="Contoh: 8, 16, 32" min="0">
        @error('port_count') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    {{-- Kabel: Tipe Kabel --}}
    <div class="col-md-6 field-cable" style="display:none;">
        <label class="form-label fw-semibold small">Tipe Kabel</label>
        <input type="text" name="cable_type" class="form-control @error('cable_type') is-invalid @enderror"
               value="{{ old('cable_type', $item->cable_type ?? '') }}" placeholder="Contoh: CAT5e, CAT6, Fiber Optic, Drop Core">
        @error('cable_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label fw-semibold small">Satuan <span class="text-danger">*</span></label>
        <select name="unit" class="form-select @error('unit') is-invalid @enderror" required>
            @foreach($units as $key => $label)
                <option value="{{ $key }}" {{ ($item && $item->unit === $key) ? 'selected' : (old('unit', 'pcs') === $key ? 'selected' : '') }}>{{ $label }}</option>
            @endforeach
        </select>
        @error('unit') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3">
        <label class="form-label fw-semibold small">Stok Awal <span class="text-danger">*</span></label>
        <input type="number" name="stock" class="form-control @error('stock') is-invalid @enderror"
               value="{{ old('stock', $item->stock ?? 0) }}" min="0" required>
        @error('stock') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-12">
        <label class="form-label fw-semibold small">Keterangan</label>
        <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="2"
                  placeholder="Catatan tambahan...">{{ old('description', $item->description ?? '') }}</textarea>
        @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<script>
function toggleFields(cat) {
    var showSn = ['olt', 'ont_modem'].includes(cat);
    document.querySelectorAll('.field-sn').forEach(function(el) { el.style.display = showSn ? '' : 'none'; });
    document.querySelectorAll('.field-olt').forEach(function(el) { el.style.display = (cat === 'olt') ? '' : 'none'; });
    document.querySelectorAll('.field-port').forEach(function(el) { el.style.display = ['otb','odc','odp'].includes(cat) ? '' : 'none'; });
    document.querySelectorAll('.field-cable').forEach(function(el) { el.style.display = ['kabel_rj45','kabel'].includes(cat) ? '' : 'none'; });
}
</script>
