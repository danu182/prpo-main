@extends('layouts.app')

@push('css')
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<style>
    .asset-card { transition: all 0.3s ease; border-left: 5px solid #0dcaf0; }
    .asset-card:hover { transform: translateY(-3px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; }
    .quill-editor { background: #fff; min-height: 100px; max-height: 200px; overflow-y: auto; border-radius: 0 0 8px 8px; }
    .ql-toolbar.ql-snow { border-radius: 8px 8px 0 0; background: #f8f9fa; }
    select option:disabled { background-color: #e9ecef; color: #6c757d; font-style: italic; }
    .photo-preview-item { transition: transform 0.2s ease-in-out; }
    .photo-preview-item:hover { transform: scale(1.1); z-index: 10; }

    /* Wizard Steps CSS */
    .step-container { display: none; animation: fadeIn 0.4s ease-in-out; }
    .step-active { display: block; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

    .table-hover tbody tr:hover { background-color: #f8f9fa; cursor: pointer; }
    .btn-dashed { border-style: dashed !important; border-width: 2px !important; }
</style>
@endpush

@section('content')
<div class="px-0 container-fluid text-dark">
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 fw-bold text-dark">
                <i class="bi bi-magic text-info me-2"></i> Pengakuan Aset (Capitalization)
            </h4>
            <div class="mt-1 text-muted small">Alur kapitalisasi aset bertahap untuk akurasi data yang lebih baik.</div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill fw-bold" data-bs-toggle="modal" data-bs-target="#modalAturan">
            <i class="bi bi-info-circle me-1"></i> Dasar Hukum & Akuntansi
        </button>
    </div>

    @if(session('success'))
        <div class="shadow-sm alert alert-success fw-bold rounded-3"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="shadow-sm alert alert-danger fw-bold rounded-3"><i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}</div>
    @endif

    {{-- ========================================================== --}}
    {{-- LANGKAH 1: TABEL PILIH DOKUMEN GR --}}
    {{-- ========================================================== --}}
    <div id="step-1" class="step-container step-active">
        <div class="mb-4 border-0 shadow-sm card rounded-4">
            <div class="flex-wrap gap-3 p-4 bg-white card-header border-bottom d-flex justify-content-between align-items-center rounded-top-4">
                <h5 class="mb-0 fw-bold text-primary"><i class="bi bi-1-circle-fill me-2"></i>Langkah 1: Pilih Dokumen Penerimaan (GR)</h5>
                <div class="input-group" style="max-width: 350px;">
                    <span class="bg-white input-group-text border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" id="search-gr" class="form-control border-start-0 ps-0" placeholder="Cari No GR, PO, atau Vendor...">
                </div>
            </div>
            <div class="p-0 card-body">
                <div class="table-responsive">
                    <table class="table mb-0 align-middle table-hover" id="table-gr">
                        <thead class="table-light">
                            <tr>
                                <th class="px-4 py-3 text-muted small text-uppercase fw-bold">Dokumen GR</th>
                                <th class="py-3 text-muted small text-uppercase fw-bold">Dokumen PO & Vendor</th>
                                <th class="py-3 text-muted small text-uppercase fw-bold">Gudang Penyimpanan</th>
                                <th class="px-4 py-3 text-muted small text-uppercase fw-bold text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($grs as $gr)
                                @php
                                    $po = $gr->po ?? $gr->purchaseOrder;
                                    $vendorName = optional($po->vendor)->name ?? 'Vendor Internal';
                                    $poNumber = $po->po_number ?? '-';
                                    $poDate = $po->po_date ? date('d M Y', strtotime($po->po_date)) : '-';
                                @endphp
                                <tr class="gr-row">
                                    <td class="px-4 py-3">
                                        <div class="fw-bold text-dark fs-6 searchable">{{ $gr->gr_number }}</div>
                                        <div class="small text-muted">Tgl Terima: {{ date('d M Y', strtotime($gr->received_date)) }}</div>
                                    </td>
                                    <td class="py-3 searchable">
                                        <div class="fw-bold text-primary">{{ $poNumber }}</div>
                                        <div class="small text-muted"><i class="bi bi-building me-1"></i>{{ $vendorName }} (Tgl: {{ $poDate }})</div>
                                    </td>
                                    <td class="py-3 searchable">
                                        <span class="border badge bg-secondary-subtle text-secondary"><i class="bi bi-box-seam me-1"></i>{{ optional($gr->warehouse)->name ?? 'Gudang Utama' }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-end">
                                        <button type="button" class="px-4 shadow-sm btn btn-primary btn-sm rounded-pill fw-bold btn-select-gr" data-id="{{ $gr->id }}" data-gr="{{ $gr->gr_number }}" data-wh="{{ optional($gr->warehouse)->name ?? 'Gudang Utama' }}">
                                            Pilih <i class="bi bi-arrow-right ms-1"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-5 text-center text-muted fw-bold">
                                        <i class="mb-2 opacity-50 bi bi-inbox fs-1 d-block text-secondary"></i>
                                        Tidak ada dokumen GR yang memiliki sisa stok untuk dijadikan aset.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ========================================================== --}}
    {{-- LANGKAH 2: TABEL PILIH ITEM DI DALAM GR --}}
    {{-- ========================================================== --}}
    <div id="step-2" class="step-container">
        <div class="mb-3 d-flex align-items-center">
            <button type="button" class="px-3 border shadow-sm btn btn-light rounded-pill fw-bold text-secondary" onclick="goToStep(1)">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar GR
            </button>
        </div>
        <div class="mb-4 border-0 shadow-sm card rounded-4">
            <div class="p-4 bg-white card-header border-bottom rounded-top-4">
                <h5 class="mb-1 fw-bold text-primary"><i class="bi bi-2-circle-fill me-2"></i>Langkah 2: Pilih Item Barang</h5>
                <div class="small text-muted">Dokumen: <strong class="text-dark" id="lbl-selected-gr"></strong> | Lokasi: <strong class="text-dark" id="lbl-selected-wh"></strong></div>
            </div>
            <div class="p-0 card-body">
                <div class="table-responsive">
                    <table class="table mb-0 align-middle table-hover">
                        <thead class="table-light">
                            <tr>
                                <th class="px-4 py-3 text-muted small text-uppercase fw-bold">Kode & Nama Barang</th>
                                <th class="py-3 text-center text-muted small text-uppercase fw-bold">Total GR</th>
                                <th class="py-3 text-center text-muted small text-uppercase fw-bold">Sisa (Siap Aset)</th>
                                <th class="px-4 py-3 text-muted small text-uppercase fw-bold text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-items">
                            <!-- Diisi oleh AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ========================================================== --}}
    {{-- LANGKAH 3: FORM KAPITALISASI UNTUK 1 ITEM --}}
    {{-- ========================================================== --}}
    <div id="step-3" class="step-container">
        <div class="mb-3 d-flex align-items-center">
            <button type="button" class="px-3 border shadow-sm btn btn-light rounded-pill fw-bold text-secondary" onclick="goToStep(2)">
                <i class="bi bi-arrow-left me-1"></i> Kembali Pilih Item
            </button>
        </div>

        <form action="{{ route('asset-capitalizations.store') }}" method="POST" id="form-capitalize" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="goods_receipt_id" id="form_gr_id">
            <input type="hidden" name="warehouse_id" id="form_warehouse_id">

            <div class="border-0 border-4 shadow-sm card border-top border-info rounded-4">
                <div class="p-4 bg-white card-header border-bottom rounded-top-4">
                    <h5 class="mb-0 fw-bold text-info"><i class="bi bi-3-circle-fill me-2"></i>Langkah 3: Detail Pengakuan Aset</h5>
                </div>

                <div class="p-4 card-body" style="background-color: #f8f9fa;">
                    <!-- Master Item Header & Qty Input -->
                    <div class="pb-4 mb-4 row align-items-center border-bottom" id="item-header-container">
                        <!-- Diisi oleh JS -->
                    </div>

                    <!-- Wadah Form Dinamis (Card per Unit) -->
                    <div id="dynamic-spec-container" class="row g-4"></div>
                </div>

                <div class="p-4 bg-white card-footer text-end border-top rounded-bottom-4">
                    <button type="submit" class="px-5 text-white shadow-sm btn btn-info fw-bold rounded-pill" id="btn-submit" disabled>
                        <i class="bi bi-save me-2"></i> Simpan Pengakuan Aset
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- MODAL EDUKASI --}}
<div class="modal fade" id="modalAturan" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="border-0 modal-content rounded-4">
            <div class="text-white modal-header bg-info rounded-top-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-book me-2"></i> Standar Pengakuan & Dasar Hukum Aset</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="p-4 modal-body text-secondary small">
                <h6 class="mb-2 fw-bold text-dark"><i class="bi bi-calculator me-1 text-info"></i> 1. Harga Perolehan (PSAK 16 & UU PPh)</h6>
                <p>Mengacu pada <strong>PSAK 16</strong> dan ditegaskan dalam <strong>Pasal 10 Ayat (1) UU Pajak Penghasilan (UU HPP No. 7 Tahun 2021)</strong>, Harga Perolehan adalah seluruh pengeluaran bersih hingga aset siap digunakan.</p>
                <div class="p-2 mb-3 border rounded bg-light text-dark fw-bold font-monospace">
                    Harga Perolehan = (Harga Beli / DPP) - Diskon + Biaya Atribusional
                </div>
            </div>
            <div class="modal-footer border-top-0">
                <button type="button" class="px-4 btn btn-secondary btn-sm rounded-pill" data-bs-dismiss="modal">Saya Mengerti</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    let categories = @json($assetCategories ?? []);
    let categoryOptions = '<option value="">-- Pilih Kategori --</option>';
    categories.forEach(function(cat) {
        categoryOptions += `<option value="${cat.id}">${cat.name} (${cat.useful_life_years} Thn)</option>`;
    });

    let currentGrItems = [];
    let globalSnData = {};
    let globalSpecData = {};

    function goToStep(step) {
        $('.step-container').removeClass('step-active');
        $(`#step-${step}`).addClass('step-active');
    }

    $('#search-gr').on('keyup', function() {
        let value = $(this).val().toLowerCase();
        $("#table-gr tbody tr.gr-row").filter(function() {
            let text = $(this).find('.searchable').text().toLowerCase();
            $(this).toggle(text.indexOf(value) > -1);
        });
    });

    $(document).on('click', '.btn-select-gr', function() {
        let grId = $(this).data('id');
        let grNum = $(this).data('gr');
        let whName = $(this).data('wh');
        let btn = $(this);
        let originalText = btn.html();

        btn.html('<span class="spinner-border spinner-border-sm"></span> Memuat...');
        btn.prop('disabled', true);

        $('#form_gr_id').val(grId);
        $('#lbl-selected-gr').text(grNum);
        $('#lbl-selected-wh').text(whName);

        $.ajax({
            url: `/asset-capitalizations/get-items/${grId}?t=` + new Date().getTime(),
            type: 'GET',
            cache: false,
            success: function(res) {
                btn.html(originalText).prop('disabled', false);
                $('#form_warehouse_id').val(res.warehouse_id);

                let tbody = $('#tbody-items');
                tbody.empty();
                currentGrItems = res.items;
                globalSnData = {};
                globalSpecData = {};

                if (res.items.length === 0) {
                    tbody.append(`<tr><td colspan="4" class="py-4 text-center text-danger fw-bold"><i class="bi bi-exclamation-circle me-1"></i> Semua item di dokumen ini sudah dikapitalisasi.</td></tr>`);
                } else {
                    res.items.forEach((item, index) => {
                        globalSnData[item.item_id] = item.available_sns || [];
                        globalSpecData[item.item_id] = item.default_spec || '';

                        let specificName = item.specific_name || item.item_name;
                        let masterName = item.master_name || item.item_name;
                        let masterHtml = specificName.toLowerCase().trim() !== masterName.toLowerCase().trim() ? `<div class="mt-1 small text-muted"><i class="bi bi-box me-1"></i>Master: ${masterName}</div>` : '';

                        tbody.append(`
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="fw-bold text-dark fs-6">${specificName}</div>
                                    ${masterHtml}
                                    <div class="mt-1 small text-muted">Kode: <span class="badge bg-secondary-subtle text-secondary">${item.item_code}</span></div>
                                </td>
                                <td class="py-3 text-center fw-bold text-secondary">${item.gr_qty} ${item.base_uom}</td>
                                <td class="py-3 text-center">
                                    <span class="px-3 py-2 badge bg-danger fs-6 rounded-pill">${item.max_capitalizable} ${item.base_uom}</span>
                                </td>
                                <td class="px-4 py-3 text-end">
                                    <button type="button" class="px-3 btn btn-outline-primary btn-sm rounded-pill fw-bold btn-select-item" data-idx="${index}">
                                        Kapitalisasi <i class="bi bi-magic ms-1"></i>
                                    </button>
                                </td>
                            </tr>
                        `);
                    });
                }
                goToStep(2);
            },
            error: function() {
                btn.html(originalText).prop('disabled', false);
                Swal.fire('Error', 'Gagal memuat data item dari server.', 'error');
            }
        });
    });

    $(document).on('click', '.btn-select-item', function() {
        let index = $(this).data('idx');
        let item = currentGrItems[index];

        let specificName = item.specific_name || item.item_name;

        $('#dynamic-spec-container').empty();
        $('#btn-submit').prop('disabled', true);

        // 🔥 PERBAIKAN: Input Group Dilebarkan & Font Diperbesar 🔥
        let headerHtml = `
            <div class="col-md-7">
                <div class="fw-bolder fs-4 text-dark text-uppercase">${specificName}</div>
                <div class="mt-2 text-muted">Kode Master: <span class="badge bg-secondary-subtle text-secondary fs-6">${item.item_code}</span></div>
            </div>
            <div class="col-md-5">
                <div class="p-3 bg-white border shadow-sm d-flex align-items-center justify-content-between justify-content-md-end border-info rounded-4">
                    <label class="mb-0 fw-bold text-info me-3 text-nowrap"><i class="bi bi-box-seam me-1"></i> Jadikan Aset :</label>
                    <div class="input-group input-group-lg" style="width: 250px; flex-wrap: nowrap;">
                        <input type="number" name="items[${item.item_id}][qty]" class="text-center form-control fw-bolder border-info text-dark qty-input fs-3"
                            data-item="${item.item_id}" data-name="${specificName}" data-price="${item.default_price}" data-date="${item.default_date}"
                            min="0" max="${item.max_capitalizable}" value="0" style="min-width: 80px;">
                        <span class="text-white input-group-text bg-info fw-bold border-info fs-5">${item.base_uom}</span>
                    </div>
                </div>
                <div class="mt-2 text-end small text-muted">Maksimal sisa: <strong class="text-danger">${item.max_capitalizable}</strong> unit.</div>
            </div>
        `;

        $('#item-header-container').html(headerHtml);
        goToStep(3);
    });

    $(document).on('input', '.qty-input', function() {
        let qty = parseInt($(this).val()) || 0;
        let max = parseInt($(this).attr('max'));
        if (qty > max) { qty = max; $(this).val(max); }
        if (qty < 0) { qty = 0; $(this).val(0); }

        let itemId = $(this).data('item');
        let itemName = $(this).data('name');
        let defaultPrice = parseFloat($(this).data('price')) || 0;
        let defaultDate = $(this).data('date');
        let container = $('#dynamic-spec-container');
        let availableSns = globalSnData[itemId] || [];
        let defaultSpec = globalSpecData[itemId] || '';

        container.empty();
        $('#btn-submit').prop('disabled', qty === 0);

        for (let i = 0; i < qty; i++) {
            let defaultSn = availableSns[i] || '';
            let snInputHtml = '';

            if (availableSns.length > 0) {
                snInputHtml = `<select name="items[${itemId}][details][${i}][serial_number]" class="form-select form-select-sm bg-warning-subtle text-dark fw-bold border-warning sn-input-select">
                    <option value="">-- Pilih Serial Number --</option>`;
                availableSns.forEach(sn => {
                    let selected = (sn === defaultSn) ? 'selected' : '';
                    snInputHtml += `<option value="${sn}" ${selected}>${sn}</option>`;
                });
                snInputHtml += `</select>`;
            } else {
                snInputHtml = `<input type="text" name="items[${itemId}][details][${i}][serial_number]" class="form-control form-control-sm bg-warning-subtle text-dark fw-bold border-warning sn-input-select" value="" placeholder="Isi manual (Opsional)...">`;
            }

            container.append(`
                <div class="col-md-6">
                    <div class="shadow-sm card asset-card h-100 rounded-3">
                        <div class="py-2 bg-white card-header d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-info"><i class="bi bi-pc-display me-1"></i> Unit #${i+1}</span>
                        </div>
                        <div class="p-3 card-body d-flex flex-column">

                            <div class="px-3 py-2 mb-3 border bg-info-subtle text-info-emphasis border-info-subtle rounded-3 small">
                                <div class="mb-1 d-flex justify-content-between align-items-center">
                                    <span><i class="bi bi-calculator me-1"></i> <strong>Harga Perolehan:</strong></span>
                                    <span class="fw-bold fs-6 text-dark">Rp ${defaultPrice.toLocaleString('id-ID')}</span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="mb-1 small text-muted fw-bold">Nama Spesifik / Merk <span class="text-danger">*</span></label>
                                <input type="text" name="items[${itemId}][details][${i}][specific_name]" class="form-control" value="${itemName}" required>
                            </div>
                            <div class="mb-3 row g-2">
                                <div class="col-sm-6">
                                    <label class="mb-1 small text-muted fw-bold">No. Akuntansi (FA)</label>
                                    <input type="text" name="items[${itemId}][details][${i}][accounting_no]" class="form-control form-control-sm acc-no-input" placeholder="Opsional...">
                                </div>
                                <div class="col-sm-6">
                                    <label class="mb-1 small text-muted fw-bold">Pilih Serial Number (SN)</label>
                                    ${snInputHtml}
                                </div>
                            </div>
                            <div class="pt-3 mt-1 mb-3 row g-2 border-top">
                                <div class="col-sm-4">
                                    <label class="mb-1 small text-primary-emphasis fw-bold" style="font-size:0.7rem;">Tgl Perolehan</label>
                                    <input type="date" name="items[${itemId}][details][${i}][acquisition_date]" class="form-control form-control-sm border-primary-subtle" value="${defaultDate}" required>
                                </div>
                                <div class="col-sm-4">
                                    <label class="mb-1 small text-primary-emphasis fw-bold" style="font-size:0.7rem;">Harga (Rp)</label>
                                    <input type="number" name="items[${itemId}][details][${i}][accounting_value]" class="form-control form-control-sm border-primary-subtle" value="${defaultPrice}">
                                </div>
                                <div class="col-sm-4">
                                    <label class="mb-1 small text-primary-emphasis fw-bold" style="font-size:0.7rem;">Kategori Pajak</label>
                                    <select name="items[${itemId}][details][${i}][asset_category_id]" class="form-select form-select-sm border-primary-subtle" required>
                                        ${categoryOptions}
                                    </select>
                                </div>
                            </div>

                            <input type="hidden" name="items[${itemId}][details][${i}][notes]" id="hidden-notes-${itemId}-${i}">
                            <div class="mb-3">
                                <label class="mb-1 small text-muted fw-bold">Spesifikasi / Catatan</label>
                                <div id="editor-${itemId}-${i}" class="quill-editor"></div>
                            </div>

                            {{-- 🔥 LAYOUT BARU UPLOAD FOTO 🔥 --}}
                            <div class="p-3 mt-auto bg-white border rounded-3 border-primary-subtle">
                                <label class="pb-2 mb-3 d-block text-primary fw-bold border-bottom" style="font-size:0.85rem;">
                                    <i class="bi bi-images me-1"></i> Lampiran Foto Fisik Aset
                                </label>

                                <div id="photo-inputs-container-${itemId}-${i}" class="gap-2 mb-3 d-flex flex-column">
                                    <div class="shadow-sm input-group input-group-sm photo-input-row">
                                        <input type="file" name="items[${itemId}][details][${i}][photos][]" class="form-control border-primary-subtle single-photo-input" accept="image/*">
                                        <button type="button" class="btn btn-danger remove-photo-row" title="Hapus baris ini"><i class="bi bi-trash"></i></button>
                                    </div>
                                </div>

                                <button type="button" class="btn btn-sm btn-light border-primary text-primary w-100 fw-bold add-photo-btn btn-dashed" data-item="${itemId}" data-unit="${i}">
                                    <i class="bi bi-plus-circle me-1"></i> Tambah File Foto Lainnya
                                </button>

                                <div id="preview-${itemId}-${i}" class="flex-wrap gap-2 p-2 mt-3 border rounded bg-light d-flex empty-preview align-items-center justify-content-center" style="min-height: 80px;">
                                    <span class="text-muted small fst-italic"><i class="bi bi-image me-1"></i> Preview foto akan muncul di sini...</span>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            `);

            var quill = new Quill(`#editor-${itemId}-${i}`, { theme: 'snow', modules: { toolbar: [ ['bold', 'italic', 'underline'], [{ 'list': 'ordered'}, { 'list': 'bullet' }], [{ 'color': [] }] ] } });
            if (defaultSpec) quill.clipboard.dangerouslyPasteHTML(defaultSpec);
            document.getElementById(`hidden-notes-${itemId}-${i}`).value = document.querySelector(`#editor-${itemId}-${i} .ql-editor`).innerHTML;
            quill.on('text-change', function() { document.getElementById(`hidden-notes-${itemId}-${i}`).value = document.querySelector(`#editor-${itemId}-${i} .ql-editor`).innerHTML; });
        }

        refreshSnDropdowns();
    });

    function refreshSnDropdowns() {
        let selectedSns = [];
        $('.sn-input-select').each(function() {
            let val = $(this).val();
            if (val && val !== '') { selectedSns.push(val); }
        });

        $('.sn-input-select').each(function() {
            let currentSelectValue = $(this).val();
            $(this).find('option').each(function() {
                let optionValue = $(this).val();
                if (!optionValue) return;

                if (selectedSns.includes(optionValue) && optionValue !== currentSelectValue) {
                    $(this).prop('disabled', true);
                    $(this).text(optionValue + " (Terpakai)");
                } else {
                    $(this).prop('disabled', false);
                    $(this).text(optionValue);
                }
            });
        });
    }

    $(document).on('change', '.sn-input-select', function() { refreshSnDropdowns(); });

    $(document).on('click', '.add-photo-btn', function() {
        let itemId = $(this).data('item');
        let unitIdx = $(this).data('unit');
        let container = $(`#photo-inputs-container-${itemId}-${unitIdx}`);

        container.append(`
        <div class="shadow-sm input-group input-group-sm photo-input-row">
            <input type="file" name="items[${itemId}][details][${unitIdx}][photos][]" class="form-control border-primary-subtle single-photo-input" accept="image/*">
            <button type="button" class="btn btn-danger remove-photo-row" title="Hapus baris ini"><i class="bi bi-trash"></i></button>
        </div>`);

        updatePhotoRemoveButtons(container);
    });

    $(document).on('click', '.remove-photo-row', function() {
        let row = $(this).closest('.photo-input-row');
        let container = row.closest('div[id^="photo-inputs-container-"]');
        row.remove();
        updatePhotoRemoveButtons(container);
        triggerPhotoPreview(container.closest('.card-body'));
    });

    function updatePhotoRemoveButtons(container) {
        // 🔥 Biarkan tombol hapus selalu aktif agar user bisa mengosongkan foto
        container.find('.remove-photo-row').prop('disabled', false);
    }
    $(document).on('change', '.single-photo-input', function() {
        triggerPhotoPreview($(this).closest('.card-body'));
    });

    function triggerPhotoPreview(cardBody) {
        let fileInputs = cardBody.find('.single-photo-input');
        let previewDiv = cardBody.find('[id^="preview-"]');

        previewDiv.empty();
        let hasFiles = false;

        fileInputs.each(function() {
            let files = this.files;
            if (files && files[0] && files[0].type.match('image.*')) {
                hasFiles = true;
                let reader = new FileReader();
                reader.onload = function(e) {
                    previewDiv.append(`
                    <div class="p-1 bg-white border shadow-sm rounded-3 position-relative photo-preview-item">
                        <img src="${e.target.result}" class="rounded" style="width: 70px; height: 70px; object-fit: cover;">
                    </div>`);
                }
                reader.readAsDataURL(files[0]);
            }
        });

        if (!hasFiles) { previewDiv.html('<span class="text-muted small fst-italic"><i class="bi bi-image me-1"></i> Preview foto akan muncul di sini...</span>'); }
    }

    $('#form-capitalize').on('submit', function(e) {
        let accNumbers = []; let hasDuplicateAcc = false;
        $('.acc-no-input').each(function() {
            let val = $(this).val().trim();
            if (val !== '') {
                if (accNumbers.includes(val)) { hasDuplicateAcc = true; return false; }
                accNumbers.push(val);
            }
        });

        if (hasDuplicateAcc) {
            e.preventDefault();
            Swal.fire({ icon: 'error', title: 'Peringatan!', text: 'Nomor Akuntansi (FA) ada yang kembar.', confirmButtonColor: '#dc3545' });
            return false;
        }

        let snNumbers = []; let hasDuplicateSn = false;
        $('.sn-input-select').each(function() {
            let val = $(this).val();
            if (val && val.trim() !== '') {
                if (snNumbers.includes(val)) { hasDuplicateSn = true; return false; }
                snNumbers.push(val);
            }
        });

        if (hasDuplicateSn) {
            e.preventDefault();
            Swal.fire({ icon: 'error', title: 'Peringatan Fatal!', text: 'Terdapat Serial Number (SN) yang kembar.', confirmButtonColor: '#dc3545' });
            return false;
        }

        $('#btn-submit').html('<span class="spinner-border spinner-border-sm me-2"></span> Menyimpan Aset...').prop('disabled', true);
    });
</script>
@endpush
