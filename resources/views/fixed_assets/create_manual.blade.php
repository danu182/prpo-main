@extends('layouts.app')

@push('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
<style>
    .select2-container .select2-selection--single { height: 38px !important; border: 1px solid #e2e8f0 !important; border-radius: 8px !important; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 36px !important; color: #475569 !important; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px !important; }
    .ck-editor__editable_inline { min-height: 150px; border-bottom-left-radius: 8px !important; border-bottom-right-radius: 8px !important; }
    .ck-toolbar { border-top-left-radius: 8px !important; border-top-right-radius: 8px !important; background-color: #f8fafc !important; }
    .photo-preview-item { transition: transform 0.2s ease-in-out; }
    .photo-preview-item:hover { transform: scale(1.1); z-index: 10; }
    .btn-dashed { border-style: dashed !important; border-width: 2px !important; }

    .btn-delete-existing {
        position: absolute;
        top: -8px;
        right: -8px;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        z-index: 20;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        transition: transform 0.2s;
    }
    .btn-delete-existing:hover { transform: scale(1.2); }
</style>
@endpush

@section('content')
<div class="pb-5 container-fluid text-dark">
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-pencil-square me-2 text-primary"></i> Input Aset Manual (Hibah / Registrasi Lama)</h4>
            <div class="mt-1 text-muted small">Registrasi aset yang tidak melalui proses Purchase Order (PO), lengkap dengan Nama Varian, Nama Akuntansi, dan Komponen Upgrade.</div>
        </div>
        <a href="{{ route('fixed-assets.index') }}" class="btn btn-outline-secondary rounded-pill fw-bold"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
    </div>

    @if($errors->any())
        <div class="border-0 border-4 shadow-sm alert alert-danger alert-dismissible fade show rounded-4 border-start border-danger">
            <i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i> <strong>Gagal Memproses:</strong>
            <ul class="mt-1 mb-0 small">@foreach($errors->all() as $error) <li>{{ $error }}</li> @endforeach</ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="border-0 border-4 shadow-sm card rounded-4 border-top border-primary">
        <form action="{{ route('fixed-assets.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="p-4 card-body">
                <div class="row g-5">
                    {{-- KOLOM KIRI: INFORMASI FISIK & PENAMAAN 3 LAPIS --}}
                    <div class="col-md-6 border-end pe-md-5">
                        <h6 class="pb-2 mb-4 border-bottom text-primary fw-bold"><i class="bi bi-box-seam me-2"></i>Informasi Fisik & Penamaan Aset</h6>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted text-uppercase">Jenis Perolehan <span class="text-danger">*</span></label>
                            <select name="acquisition_type_id" class="shadow-sm form-select border-info" required>
                                <option value="">-- Pilih Jenis Perolehan --</option>
                                @foreach($acquisitionTypes as $type)
                                    <option value="{{ $type->id }}" {{ old('acquisition_type_id') == $type->id ? 'selected' : '' }}>
                                        {{ $type->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted text-uppercase">Gudang Penerima <span class="text-danger">*</span></label>
                            <select name="warehouse_id" class="shadow-sm form-select" required>
                                <option value="">-- Pilih Gudang Lokasi Aset --</option>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}" {{ old('warehouse_id') == $wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- LAPIS 1: MASTER ITEM --}}
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted text-uppercase">1. Barang Master (SKU) <span class="text-danger">*</span></label>
                            <select name="item_id" id="item_id_select" class="form-select select2-item-ajax" style="width: 100%;" required>
                                <option value="">-- Ketik Nama / Kode Barang --</option>
                            </select>
                        </div>

                        {{-- LAPIS 2: NAMA ALTERNATIF / VARIAN --}}
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-dark">
                                2. Nama Alternatif / Varian Barang (Opsional)
                                <span class="border badge bg-info-subtle text-info-emphasis border-info-subtle ms-1" style="font-size: 0.68rem;">Auto-Simpan Kamus Varian</span>
                            </label>
                            <input type="text"
                                   name="item_alias_name"
                                   id="item_alias_name"
                                   list="manual_alias_list"
                                   class="shadow-sm form-control border-info fw-semibold text-dark"
                                   value="{{ old('item_alias_name') }}"
                                   placeholder="Cth: Mini PC, Komputer Rakitan, Komputer All in One...">
                            <datalist id="manual_alias_list"></datalist>
                            <div class="form-text" style="font-size: 0.72rem;" id="manual_alias_hint">
                                <i class="bi bi-lightbulb text-warning me-1"></i>Pilih barang master terlebih dahulu untuk melihat daftar nama alternatif yang pernah dipakai, atau ketik varian baru.
                            </div>
                        </div>

                        {{-- LAPIS 3: NAMA ASET AKUNTANSI --}}
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-primary">3. Nama Aset Akuntansi (Sesuai Pembukuan / Invoice)</label>
                            <input type="text"
                                   name="asset_name"
                                   id="asset_name_input"
                                   class="shadow-sm form-control border-primary fw-bold text-primary"
                                   value="{{ old('asset_name') }}"
                                   placeholder="Cth: 1 SET Lenovo AIO 24IRH9 - i5, 16GB, 512GB for Arie HRD...">
                            <div class="form-text" style="font-size: 0.72rem;"><i class="bi bi-info-circle"></i> Nama resmi yang tercetak di Buku Besar Akuntansi & Berita Acara (BAST/BAPA). Kosongkan jika memakai nama Master Barang.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted text-uppercase">Kategori Penyusutan <span class="text-danger">*</span></label>
                            <select name="asset_category_id" class="shadow-sm form-select border-primary" required>
                                <option value="">-- Pilih Kategori Aset --</option>
                                @foreach($assetCategories as $category)
                                    <option value="{{ $category->id }}" {{ old('asset_category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }} (Umur: {{ $category->useful_life_years }} Thn)</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="row">
                            <div class="mb-3 col-6">
                                <label class="form-label fw-bold small text-muted">Tgl Diterima / Beli <span class="text-danger">*</span></label>
                                <input type="date" name="acquisition_date" class="shadow-sm form-control border-info" value="{{ old('acquisition_date', date('Y-m-d')) }}" required>
                            </div>
                            <div class="mb-3 col-6">
                                <label class="form-label fw-bold small text-muted">Jumlah Unit <span class="text-danger">*</span></label>
                                <input type="number" name="quantity" class="shadow-sm form-control border-warning" value="{{ old('quantity', 1) }}" min="1" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Spesifikasi Detail / Merek <span class="text-danger">*</span></label>
                            <textarea name="spesifikasi_detail" id="spesifikasi_editor_add" class="shadow-sm form-control" rows="4">{{ old('spesifikasi_detail') }}</textarea>
                        </div>

                        {{-- AREA UPLOAD FOTO FISIK ASET --}}
                        <div class="p-3 mt-4 border rounded-3 bg-light border-primary-subtle">
                            <label class="pb-2 mb-3 d-block text-primary fw-bold border-bottom" style="font-size:0.85rem;">
                                <i class="bi bi-images me-1"></i> Upload Foto Fisik Aset
                            </label>

                            <div id="photo-inputs-container" class="gap-2 mb-3 d-flex flex-column">
                                <div class="shadow-sm input-group input-group-sm photo-input-row">
                                    <input type="file" name="photos[]" class="form-control border-primary-subtle single-photo-input" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.zip,.rar">
                                    <button type="button" class="btn btn-danger remove-photo-row" title="Hapus baris ini" disabled><i class="bi bi-trash"></i></button>
                                </div>
                            </div>

                            <button type="button" class="btn btn-sm btn-light border-primary text-primary w-100 fw-bold add-photo-btn btn-dashed">
                                <i class="bi bi-plus-circle me-1"></i> Tambah File Foto Lainnya
                            </button>

                            <div id="preview-photos" class="flex-wrap gap-2 p-2 mt-3 bg-white border rounded d-flex empty-preview align-items-center justify-content-center" style="min-height: 80px;">
                                <span class="text-muted small fst-italic"><i class="bi bi-image me-1"></i> Preview foto fisik akan muncul di sini...</span>
                            </div>
                        </div>
                    </div>

                    {{-- KOLOM KANAN: STATUS, ADMINISTRASI & RINCIAN UPGRADE MULTI-BARIS --}}
                    <div class="col-md-6 ps-md-5">
                        <h6 class="pb-2 mb-4 border-bottom text-primary fw-bold"><i class="bi bi-buildings me-2"></i>Status & Administrasi</h6>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted text-uppercase">Aset Milik PT <span class="text-danger">*</span></label>
                            <select name="company_id" class="shadow-sm form-select" required>
                                <option value="">-- Pilih Pemilik Aset --</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}" {{ old('company_id') == $company->id ? 'selected' : '' }}>{{ $company->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted text-uppercase">Status <span class="text-danger">*</span></label>
                            <select name="status_id" id="status_id" class="shadow-sm form-select" required>
                                <option value="">-- Pilih Status Aset --</option>
                                @foreach($statuses as $status)
                                    @if(in_array($status->slug, ['available', 'in_use']))
                                        <option value="{{ $status->id }}" data-slug="{{ $status->slug }}" {{ old('status_id') == $status->id ? 'selected' : '' }}>{{ $status->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3" id="wrapper-assigned-to" style="display: none;">
                            <label class="form-label fw-bold small text-muted text-uppercase text-primary">Ditugaskan Kepada (User) <span class="text-danger">*</span></label>
                            <select name="assigned_to" id="assigned_to" class="shadow-sm form-select select2-user" style="width: 100%;">
                                <option value="">-- Pilih Karyawan Pemakai --</option>
                                @if(isset($users))
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}" {{ old('assigned_to') == $user->id ? 'selected' : '' }}>
                                            {{ $user->name }} — {{ optional($user->company)->name ?? 'Tanpa PT' }} ({{ optional($user->department)->name ?? 'Tanpa Dept' }})
                                        </option>
                                    @endforeach
                                @endif
                            </select>
                            <div class="form-text" style="font-size: 0.7rem;"><i class="bi bi-info-circle"></i> Wajib diisi karena status aset adalah In Use (Dipakai).</div>
                        </div>

                        <div class="row">
                            <div class="mb-3 col-4 pe-1">
                                <label class="form-label fw-bold small text-muted">Mata Uang <span class="text-danger">*</span></label>
                                <select name="currency_id" class="shadow-sm form-select border-success" required>
                                    @foreach($currencies as $currency)
                                        <option value="{{ $currency->id }}" {{ old('currency_id', ($currency->code == 'IDR' ? $currency->id : '')) == $currency->id ? 'selected' : '' }}>{{ $currency->code }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3 col-8 ps-1">
                                <label class="form-label fw-bold small text-muted">Nilai Wajar / Harga Perolehan</label>
                                <input type="text" class="shadow-sm form-control border-success price-format-input" value="{{ old('purchase_price') ? number_format(old('purchase_price'), 0, ',', '.') : '' }}" placeholder="Estimasi harga perolehan...">
                                <input type="hidden" name="purchase_price" id="real_purchase_price" value="{{ old('purchase_price') }}">
                            </div>
                        </div>

                        <div class="p-3 mt-2 border row bg-light rounded-3">
                            <div class="mb-2 col-12"><span class="mb-1 badge bg-secondary">Hanya Berlaku Jika Qty = 1</span></div>
                            <div class="mb-2 col-md-6 pe-md-3">
                                <label class="form-label fw-bold small text-muted">Serial Number (S/N)</label>
                                <input type="text" name="serial_number" class="form-control form-control-sm" value="{{ old('serial_number') }}" placeholder="Kosongkan jika massal">
                            </div>
                            <div class="mb-2 col-md-6 ps-md-3">
                                <label class="form-label fw-bold small text-muted">Label Akuntansi</label>
                                <input type="text" name="accounting_asset_number" class="form-control form-control-sm" value="{{ old('accounting_asset_number') }}" placeholder="FA-XXX...">
                            </div>
                        </div>

                        {{-- 🔥 TABEL DINAMIS MULTI-BARIS KOMPONEN UPGRADE 🔥 --}}
                        <div class="p-3 mt-4 border rounded-3 bg-warning-subtle border-warning">
                            <div class="pb-2 mb-2 d-flex justify-content-between align-items-center border-bottom border-warning">
                                <span class="fw-bold text-dark" style="font-size: 0.85rem;">
                                    <i class="bi bi-cpu-fill text-warning-emphasis me-1"></i> Rincian Upgrade Komponen (Opsional — Bisa Lebih dari 1 Baris)
                                </span>
                                <button type="button" class="px-3 btn btn-sm btn-warning fw-bold rounded-pill" id="btnAddUpgradeRow">
                                    <i class="bi bi-plus-circle me-1"></i> Tambah Baris
                                </button>
                            </div>
                            <div class="mb-2 text-muted" style="font-size: 0.72rem;">
                                Catat komponen upgrade seperti RAM, SSD, LCD Tape, Enclosure beserta biayanya per baris.
                            </div>

                            <div class="bg-white border table-responsive rounded-3">
                                <table class="table mb-0 align-middle table-sm table-bordered" id="upgradesTable">
                                    <thead class="table-light small text-muted">
                                        <tr>
                                            <th style="width: 38%;">Komponen Upgrade</th>
                                            <th style="width: 24%;">Biaya</th>
                                            <th style="width: 28%;">Tgl / Pelaksana</th>
                                            <th style="width: 10%;" class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody id="upgradesContainer">
                                        {{-- Baris upgrade ditambahkan dinamis di sini --}}
                                    </tbody>
                                    <tfoot class="table-light small fw-bold">
                                        <tr>
                                            <td class="text-end">Total Biaya Upgrade:</td>
                                            <td colspan="3" class="text-primary" id="totalUpgradeDisplay">0</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <div class="mt-4 row">
                            <div class="mb-3 col-md-6 pe-md-3">
                                <label class="form-label fw-bold small text-muted">Catatan Asal Usul / Remarks</label>
                                <textarea name="notes" class="shadow-sm form-control" rows="2" placeholder="Cth: New PC (Upgrade RAM), Hibah CSR...">{{ old('notes') }}</textarea>
                            </div>
                            <div class="mb-3 col-md-6 ps-md-3">
                                <label class="form-label fw-bold small text-muted">Dokumen Pendukung</label>
                                <input type="file" name="supporting_document" class="shadow-sm form-control border-secondary" accept=".pdf,.jpg,.jpeg,.png">
                                <div class="form-text" style="font-size: 0.7rem;"><i class="bi bi-paperclip"></i> Maks 5MB (PDF/JPG/PNG).</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="p-4 bg-white card-footer border-top text-end rounded-bottom-4">
                <a href="{{ route('fixed-assets.index') }}" class="px-4 btn btn-light fw-bold rounded-pill me-2">Batal</a>
                <button type="submit" class="px-5 btn btn-primary fw-bold rounded-pill"><i class="bi bi-save me-2"></i> Registrasi Aset Sekarang</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>
<script>
    $(document).ready(function() {
        // Init Select2 untuk Barang
        $('.select2-item-ajax').select2({
            theme: 'bootstrap-5',
            placeholder: '-- Ketik minimal 2 huruf --',
            minimumInputLength: 2,
            ajax: {
                url: "{{ route('fixed-assets.search-items') }}",
                dataType: 'json',
                delay: 250,
                data: function (params) { return { search: params.term }; },
                processResults: function (data) { return { results: data }; },
                cache: true
            }
        });

        // Tarik daftar Nama Alternatif (ItemAlias) saat Master Item dipilih
        $('#item_id_select').on('change', function () {
            let itemId = $(this).val();
            let datalist = $('#manual_alias_list');
            let hint = $('#manual_alias_hint');
            datalist.empty();

            if (!itemId) return;

            $.get("{{ url('/fixed-assets/item-aliases') }}/" + itemId, function (aliases) {
                if (Array.isArray(aliases) && aliases.length > 0) {
                    aliases.forEach(function (name) {
                        datalist.append(`<option value="${name.replace(/"/g, '&quot;')}">`);
                    });
                    hint.html(`<i class="bi bi-lightbulb-fill text-warning me-1"></i>Tersedia <strong>${aliases.length} saran nama alternatif</strong> tersimpan. Klik kolom di atas untuk memilih atau ketik nama baru.`);
                } else {
                    hint.html(`<i class="bi bi-info-circle text-primary me-1"></i>Belum ada nama alternatif tersimpan untuk item ini. Ketik nama baru untuk menyimpannya otomatis.`);
                }
            });
        });

        // Init Select2 untuk Karyawan
        $('.select2-user').select2({
            theme: 'bootstrap-5',
            placeholder: '-- Ketik / Pilih Nama Karyawan --'
        });

        // Smart Toggle Status In Use
        $('#status_id').on('change', function(e) {
            let slug = $(this).find('option:selected').data('slug');

            if (slug === 'in_use') {
                $('#wrapper-assigned-to').slideDown();
                $('#assigned_to').prop('required', true);
            } else {
                $('#wrapper-assigned-to').slideUp();
                if(e && e.originalEvent !== undefined) {
                    $('#assigned_to').val('').trigger('change');
                }
                $('#assigned_to').prop('required', false);
            }
        });

        $('#status_id').trigger('change');

        // Init CKEditor
        ClassicEditor.create(document.querySelector('#spesifikasi_editor_add'), {
            toolbar: [ 'heading', '|', 'bold', 'italic', 'bulletedList', 'numberedList', 'blockQuote', '|', 'undo', 'redo' ]
        }).then(editor => {
            editor.model.document.on('change:data', () => { document.querySelector('#spesifikasi_editor_add').value = editor.getData(); });
        });

        // ==========================================
        // Logika Tambah & Hapus Baris Upgrade Multi-Baris
        // ==========================================
        let upgradeRowIdx = 0;

        function addUpgradeRow(comp = '', cost = '', date = '', vendor = '') {
            let idx = upgradeRowIdx++;
            let defaultDate = date || $('input[name="acquisition_date"]').val() || "{{ date('Y-m-d') }}";
            let formattedCost = cost ? parseInt(cost, 10).toLocaleString('id-ID') : '';

            let rowHtml = `
                <tr class="upgrade-row">
                    <td>
                        <input type="text" name="upgrades[${idx}][component_name]" class="form-control form-control-sm" value="${comp}" placeholder="Cth: RAM DDR3 16GB / SSD 512GB">
                    </td>
                    <td>
                        <input type="text" class="form-control form-control-sm text-end upgrade-cost-display" value="${formattedCost}" placeholder="0">
                        <input type="hidden" name="upgrades[${idx}][cost]" class="upgrade-cost-real" value="${cost || 0}">
                    </td>
                    <td>
                        <input type="date" name="upgrades[${idx}][upgrade_date]" class="mb-1 form-control form-control-sm" value="${defaultDate}">
                        <input type="text" name="upgrades[${idx}][vendor]" class="form-control form-control-sm" value="${vendor}" placeholder="Vendor / IT">
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-outline-danger btn-remove-upgrade" title="Hapus Baris">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
            $('#upgradesContainer').append(rowHtml);
            recalcTotalUpgrade();
        }

        $('#btnAddUpgradeRow').on('click', function () {
            addUpgradeRow();
        });

        $(document).on('click', '.btn-remove-upgrade', function () {
            $(this).closest('tr').remove();
            recalcTotalUpgrade();
        });

        $(document).on('input', '.upgrade-cost-display', function () {
            let raw = $(this).val().replace(/[^0-9]/g, '');
            let tr = $(this).closest('tr');
            tr.find('.upgrade-cost-real').val(raw || 0);
            $(this).val(raw ? parseInt(raw, 10).toLocaleString('id-ID') : '');
            recalcTotalUpgrade();
        });

        function recalcTotalUpgrade() {
            let total = 0;
            $('.upgrade-cost-real').each(function () {
                total += parseFloat($(this).val()) || 0;
            });
            $('#totalUpgradeDisplay').text(total.toLocaleString('id-ID'));
        }

        // ==========================================
        // Logika Tambah & Hapus Baris Foto
        // ==========================================
        $('.add-photo-btn').on('click', function() {
            let container = $('#photo-inputs-container');
            container.append(`
            <div class="mb-2 shadow-sm input-group input-group-sm photo-input-row">
                <input type="file" name="photos[]" class="form-control border-primary-subtle single-photo-input" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.zip,.rar">
                <button type="button" class="btn btn-danger remove-photo-row" title="Hapus baris ini"><i class="bi bi-trash"></i></button>
            </div>`);
        });

        $(document).on('click', '.remove-photo-row', function() {
            $(this).closest('.photo-input-row').remove();
            triggerPhotoPreview();
        });

        $(document).on('change', '.single-photo-input', function() {
            triggerPhotoPreview();
        });

        // Format Titik Ribuan Otomatis Saat Mengetik Harga Perolehan
        $(document).on('input', '.price-format-input', function() {
            let rawValue = $(this).val().replace(/[^0-9]/g, '');
            $('#real_purchase_price').val(rawValue);
            if(rawValue) {
                $(this).val(parseInt(rawValue, 10).toLocaleString('id-ID'));
            } else {
                $(this).val('');
            }
        });

        function triggerPhotoPreview() {
            let fileInputs = $('.single-photo-input');
            let previewDiv = $('#preview-photos');

            previewDiv.empty();
            let hasFiles = false;

            fileInputs.each(function() {
                let files = this.files;
                if (files && files[0]) {
                    hasFiles = true;
                    let fileName = files[0].name;

                    if (files[0].type.match('image.*')) {
                        let reader = new FileReader();
                        reader.onload = function(e) {
                            previewDiv.append(`
                            <div class="p-1 bg-white border shadow-sm rounded-3 position-relative photo-preview-item" title="${fileName}">
                                <img src="${e.target.result}" class="rounded" style="width: 70px; height: 70px; object-fit: cover;">
                            </div>`);
                        }
                        reader.readAsDataURL(files[0]);
                    } else {
                        previewDiv.append(`
                        <div class="p-2 text-center bg-white border shadow-sm rounded-3 photo-preview-item d-flex flex-column justify-content-center align-items-center" style="width: 70px; height: 70px;" title="${fileName}">
                            <i class="bi bi-file-earmark-text text-primary fs-3"></i>
                            <span style="font-size: 0.5rem;" class="text-truncate w-100">${fileName}</span>
                        </div>`);
                    }
                }
            });

            if (!hasFiles) {
                previewDiv.html('<span class="text-muted small fst-italic"><i class="bi bi-image me-1"></i> Preview lampiran akan muncul di sini...</span>');
            }
        }
    });
</script>
@endpush
