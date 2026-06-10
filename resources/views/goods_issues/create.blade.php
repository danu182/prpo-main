@extends('layouts.app')

@push('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<style>
    /* 🔥 Kustomisasi Select2 Premium 🔥 */
    .select2-container--bootstrap-5 .select2-selection {
        border-radius: 8px;
        border-color: #dee2e6;
        min-height: 38px;
        font-size: 0.875rem;
    }
    .select2-container--bootstrap-5 .select2-selection--multiple {
        padding-bottom: 2px;
    }
    /* Tag Biru untuk Aset */
    .asset-select-container .select2-selection__choice {
        background-color: #0dcaf0 !important;
        color: #fff !important;
        border: none !important;
        border-radius: 6px !important;
        font-size: 0.75rem !important;
        font-weight: 600 !important;
        margin-top: 4px !important;
    }
    /* Tag Kuning untuk SN Lacak */
    .sn-select-container .select2-selection__choice {
        background-color: #ffc107 !important;
        color: #000 !important;
        border: none !important;
        border-radius: 6px !important;
        font-size: 0.75rem !important;
        font-weight: 600 !important;
        margin-top: 4px !important;
    }
    .select2-results__option { font-size: 0.85rem; padding: 8px 12px; }

    /* Tabel Cantik */
    .table-gi th { background-color: #f8f9fa; text-transform: uppercase; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.5px; }
    .table-gi td { vertical-align: top; padding-top: 1rem; padding-bottom: 1rem; }
    .input-group-text-custom { background-color: #f8f9fa; font-weight: bold; font-size: 0.8rem; }
</style>
@endpush

@section('content')
<div class="container pb-5 text-dark">

    <div class="mb-4">
        <a href="{{ route('goods-issues.index') }}" class="mb-2 text-decoration-none text-muted small fw-bold d-inline-block">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar
        </a>
        <h4 class="mb-0 fw-bold text-dark">
            <i class="bi bi-box-arrow-up text-danger me-2"></i> Form Pengeluaran Barang
        </h4>
        <div class="mt-1 text-muted small">Serahkan stok, aset, atau inventaris kepada karyawan operasional.</div>
    </div>

    @if(session('error'))
        <div class="border-0 border-4 shadow-sm alert alert-danger rounded-3 fw-bold border-start border-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <form action="{{ route('goods-issues.store') }}" method="POST" id="form-goods-issue">
        @csrf

        {{-- 📦 CARD 1: INFORMASI HEADER --}}
        <div class="mb-4 border-0 border-4 shadow-sm card rounded-4 border-start border-danger">
            <div class="p-4 card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Tanggal Keluar <span class="text-danger">*</span></label>
                        <input type="date" name="issue_date" class="shadow-sm form-control" value="{{ date('Y-m-d') }}" required max="{{ date('Y-m-d') }}">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Penerima / Karyawan <span class="text-danger">*</span></label>
                        <select name="requester_name" id="requester_select" class="shadow-sm form-select select2-user" required>
                            <option value="">-- Cari Nama Karyawan --</option>
                           @foreach($users as $user)
                                <option value="{{ $user->name }}" data-dept="{{ $user->department ?: ($user->company->name ?? '-') }}">
                                    {{ $user->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Asal Gudang <span class="text-danger">*</span></label>
                        <select name="warehouse_id" id="warehouse_select" class="shadow-sm form-select border-danger" required>
                            <option value="">-- Pilih Gudang Asal --</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}" {{ old('warehouse_id') == $wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Departemen / Proyek</label>
                        <input type="text" name="department" id="department_input" class="shadow-sm form-control bg-light" placeholder="Terisi otomatis..." readonly>
                    </div>
                </div>
            </div>
        </div>

        {{-- 🛒 CARD 2: DAFTAR BARANG YANG DIKELUARKAN --}}
        <div class="border-0 border-4 shadow-sm card rounded-4 border-top border-danger">
            <div class="px-4 pt-4 pb-3 bg-white card-header border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-cart-dash text-danger me-2"></i>Rincian Keranjang Pengeluaran</h6>
                <button type="button" class="px-3 shadow-sm btn btn-sm btn-primary rounded-pill fw-bold" id="btn-add-item" style="display: none;">
                    <i class="bi bi-plus-lg me-1"></i> Tambah Barang
                </button>
            </div>

            {{-- Peringatan Kunci Gudang --}}
            <div class="px-4 py-3 text-center bg-warning-subtle text-warning-emphasis small fw-bold border-bottom" id="warehouse-warning">
                <i class="mb-1 bi bi-exclamation-circle fs-5 d-block"></i>
                PILIH "ASAL GUDANG" TERLEBIH DAHULU UNTUK MEMULAI TRANSAKSI.
            </div>

            <div class="p-0 card-body" id="table-container" style="display: none;">
                <div class="table-responsive" style="min-height: 250px;">
                    <table class="table mb-0 align-middle table-gi table-borderless" id="issue-table">
                        <thead>
                            <tr>
                                <th class="ps-4" width="30%">Cari Barang / Aset</th>
                                <th width="20%">Pilih Batch</th>
                                <th width="30%">Kuantitas / Pemilihan Unit (SN)</th>
                                <th width="15%">Catatan Ref.</th>
                                <th class="text-center pe-4" width="5%"><i class="bi bi-trash"></i></th>
                            </tr>
                        </thead>
                        <tbody id="item-tbody">
                            {{-- Baris dari JavaScript akan masuk ke sini --}}
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="p-4 mb-3 border-top bg-light">
                <label class="form-label small fw-bold text-muted"><i class="bi bi-chat-text me-1"></i> Catatan Umum / Tujuan Pengeluaran</label>
                <textarea name="notes" class="shadow-sm form-control" rows="2" placeholder="Cth: Pemberian fasilitas laptop baru untuk tim lapangan...">{{ old('notes') }}</textarea>
            </div>

            <div class="p-4 bg-white card-footer border-top text-end rounded-bottom-4">
                <button type="submit" id="btnSubmitGI" class="px-5 py-2 shadow-sm btn btn-danger rounded-pill fw-bold fs-6">
                    <i class="bi bi-send-check me-2"></i> Konfirmasi Pengeluaran
                </button>
            </div>
        </div>
    </form>
</div>
@endsection


@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // =========================================================================
    // 🔥 FORMATTER VISUAL UNTUK SELECT2 🔥
    // =========================================================================
    function formatAssetList(state) {
        if (!state.id) return state.text;
        let cleanText = $(`<div>${state.text}</div>`).text();
        let astNumber = cleanText.split(' (')[0] || cleanText;
        let descMatch = cleanText.match(/\((.*?)\)/);
        let snMatch = cleanText.split('| SN: ');
        return $(`
            <div class="py-1 border-bottom border-light">
                <div class="fw-bold text-dark small"><i class="bi bi-pc-display text-info me-1"></i> ${astNumber}</div>
                ${descMatch ? `<div class="text-muted" style="font-size:0.7rem;">${descMatch[1]}</div>` : ''}
                ${snMatch.length > 1 ? `<span class="mt-1 border badge bg-light text-dark"><i class="bi bi-upc-scan"></i> ${snMatch[1].trim()}</span>` : ''}
            </div>
        `);
    }

    $(document).ready(function() {
        // Init Select2 User
        $('.select2-user').select2({ theme: 'bootstrap-5' });

        $('#requester_select').on('change', function() {
            let dept = $(this).find(':selected').data('dept');
            $('#department_input').val(dept ? dept : '');
        });

        let tbody = $('#item-tbody');
        let btnAdd = $('#btn-add-item');
        let warehouseSelect = $('#warehouse_select');
        let warehouseWarning = $('#warehouse-warning');
        let tableContainer = $('#table-container');
        let rowCount = 0;

        function toggleTable() {
            if (warehouseSelect.val()) {
                warehouseWarning.hide(); tableContainer.show(); btnAdd.show();
            } else {
                warehouseWarning.show(); tableContainer.hide(); btnAdd.hide();
            }
        }
        toggleTable();

        warehouseSelect.on('change', function() {
            toggleTable();
            if (tbody.find('tr.item-row').length > 0) {
                tbody.find('select').select2('destroy');
                tbody.empty(); rowCount = 0;
            }
            if($(this).val()) addRow();
        });

        btnAdd.on('click', function() {
            if(!warehouseSelect.val()) return Swal.fire('Oops', 'Pilih Gudang Asal dulu!', 'warning');
            addRow();
        });

        function addRow() {
            let tr = `
                <tr class="border-bottom item-row">
                    <td class="ps-4">
                        <select name="items[${rowCount}][item_id]" class="shadow-sm form-select item-select-ajax" required></select>
                        <div class="mt-2 stock-display text-muted small d-none"></div>
                    </td>
                    <td>
                        <select name="items[${rowCount}][inventory_stock_id]" class="shadow-sm form-select form-select-sm batch-select bg-light" disabled>
                            <option value="">⚡ Mode FIFO (Otomatis)</option>
                        </select>
                    </td>
                    <td>
                        <div class="qty-container">
                            <label class="mb-1 small text-muted fw-bold">Kuantitas Keluar</label>
                            <div class="shadow-sm input-group input-group-sm">
                                <input type="number" name="items[${rowCount}][qty_issued]" class="text-center form-control qty-input fw-bold" step="0.01" min="0.1" required>
                                <select name="items[${rowCount}][uom_info]" class="form-select bg-light text-dark uom-select fw-bold" style="max-width: 110px;"></select>
                            </div>
                        </div>

                        <div class="p-2 mt-2 border asset-container asset-select-container d-none bg-info-subtle border-info rounded-3">
                            <label class="mb-1 small text-info-emphasis fw-bold"><i class="bi bi-pc-display me-1"></i> Pilih Unit Aset</label>
                            <select name="items[${rowCount}][asset_ids][]" class="shadow-sm form-select asset-select" multiple="multiple"></select>
                        </div>

                        <div class="p-2 mt-2 border minor-sn-container sn-select-container d-none bg-warning-subtle border-warning rounded-3">
                            <label class="mb-1 small text-warning-emphasis fw-bold"><i class="bi bi-upc-scan me-1"></i> Pilih Serial Number (Otomatis)</label>
                            <select name="items[${rowCount}][sn_list][]" class="shadow-sm form-select sn-select" multiple="multiple"></select>
                        </div>
                    </td>
                    <td>
                        <textarea name="items[${rowCount}][notes]" class="shadow-sm form-control form-control-sm general-notes" rows="2" placeholder="Catatan opsional baris ini..."></textarea>
                    </td>
                    <td class="text-center pe-4">
                        <button type="button" class="shadow-sm btn btn-sm btn-outline-danger btn-remove rounded-circle"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>
            `;
            tbody.append(tr);

            let $tr = tbody.find('tr').last();
            $tr.find('.item-select-ajax').select2({
                theme: 'bootstrap-5', placeholder: 'Ketik nama barang...', minimumInputLength: 2,
                ajax: {
                    url: "{{ route('goods-issues.search-items') }}", dataType: 'json', delay: 250,
                    data: function (p) { return { search: p.term, warehouse_id: warehouseSelect.val() }; },
                    processResults: function (data) { return { results: data }; }
                }
            });
            rowCount++;
        }

        tbody.on('click', '.btn-remove', function() {
            if (tbody.find('tr').length > 1) {
                $(this).closest('tr').find('select').each(function() { if ($(this).hasClass("select2-hidden-accessible")) $(this).select2('destroy'); });
                $(this).closest('tr').remove();
            } else {
                Swal.fire('Ups!', 'Minimal sisakan 1 barang.', 'info');
            }
        });

        // =========================================================================
        // 🔥 HELPER FUNCTION: SETUP MODE STOK BIASA & TRACKABLE 🔥
        // =========================================================================
        function setupBulkMode(tr, data) {
            let qtyContainer = tr.find('.qty-container');
            let qtyInput = tr.find('.qty-input');
            let uomSelect = tr.find('.uom-select');
            let batchSelect = tr.find('.batch-select');
            let snContainer = tr.find('.minor-sn-container');
            let snSelect = tr.find('.sn-select');
            let generalNotes = tr.find('.general-notes');

            qtyContainer.removeClass('d-none');
            batchSelect.prop('disabled', false).removeClass('bg-light').html('<option value="">⏳ Memuat Batch...</option>');

            uomSelect.empty();

            // 🔥 PERBAIKAN TYPO VARIABEL UOM DI SINI 🔥
            let baseUom = data.base_uom_name || 'PCS';
            uomSelect.append(`<option value="" data-conv="1">${baseUom}</option>`);

            let listUom = data.uoms || data.item_uoms || [];
            if (listUom.length > 0) {
                listUom.forEach(u => {
                    let namaUom = u.uom_name || u.name || 'PCS';
                    let konversi = parseFloat(u.conversion_qty || u.conversion || 1);
                    let idUom = u.id || '';
                    let teksTampil = konversi > 1 ? `${namaUom} (Isi ${konversi})` : namaUom;
                    uomSelect.append(`<option value="${idUom}" data-conv="${konversi}">${teksTampil}</option>`);
                });
            }

            qtyInput.data('stock-bulk', data.available_bulk);
            let initialConv = parseFloat(uomSelect.find(':selected').data('conv')) || 1;
            let initialMax = Math.floor(data.available_bulk / initialConv);
            qtyInput.attr('max', initialMax).attr('placeholder', 'Max: ' + initialMax);

            $.ajax({
                url: "{{ route('goods-issues.search-batches') }}", type: "GET",
                data: { item_id: data.id, warehouse_id: warehouseSelect.val() },
                success: function(res) {
                    let opts = '<option value="">⚡ Mode Otomatis (FIFO)</option>';
                    if(Array.isArray(res)) { res.forEach(b => { opts += `<option value="${b.id}">${b.text}</option>`; }); }
                    batchSelect.html(opts);
                }
            });

            // LOGIKA JIKA BARANG WAJIB LACAK SN
            if (data.is_trackable) {
                qtyInput.prop('readonly', true).val('').attr('placeholder', 'Pilih SN 👇').addClass('bg-light text-muted');
                snContainer.removeClass('d-none');

                if(snSelect.hasClass("select2-hidden-accessible")) { snSelect.select2('destroy'); }
                snSelect.empty().prop('required', true).select2({
                    theme: 'bootstrap-5', width: '100%', placeholder: 'Klik & Pilih Serial Number...',
                    ajax: {
                        url: "{{ route('goods-issues.search-sns') }}", dataType: 'json', delay: 250,
                        data: function (params) { return { item_id: data.id, search: params.term }; },
                        processResults: function (res) { return { results: res }; }
                    }
                });
            } else {
                qtyInput.prop('readonly', false).prop('required', true);
                snContainer.addClass('d-none');
                if(snSelect.hasClass("select2-hidden-accessible")) { snSelect.select2('destroy'); }
                snSelect.empty().prop('required', false);
            }

            generalNotes.removeClass('d-none');
        }

        // =========================================================================
        // 🔥 LOGIKA METAMORFOSIS FORM BERDASARKAN FISIK STOK 🔥
        // =========================================================================
        tbody.on('select2:select', '.item-select-ajax', function (e) {
            let data = e.params.data;
            let tr = $(this).closest('tr');

            tr.find('.stock-display').removeClass('d-none').html(`
                <span class="border badge bg-success-subtle text-success-emphasis border-success me-1">Stok Biasa: ${data.available_bulk}</span>
                ${data.available_asset > 0 ? `<span class="border badge bg-info-subtle text-info-emphasis border-info">Aset: ${data.available_asset}</span>` : ''}
            `);

            let qtyContainer = tr.find('.qty-container');
            let assetContainer = tr.find('.asset-container');
            let assetSelect = tr.find('.asset-select');
            let batchSelect = tr.find('.batch-select');
            let snContainer = tr.find('.minor-sn-container');
            let snSelect = tr.find('.sn-select');

            // Reset
            tr.find('.qty-input').prop('required', false);
            assetSelect.prop('required', false);
            snSelect.prop('required', false);

            if (data.available_asset > 0 && data.available_bulk <= 0) {
                qtyContainer.addClass('d-none');
                tr.find('.qty-input').val('');
                batchSelect.prop('disabled', true).addClass('bg-light').html('<option value="">🔒 Ditentukan via Aset</option>');
                snContainer.addClass('d-none');

                assetContainer.removeClass('d-none');
                if(assetSelect.hasClass("select2-hidden-accessible")) { assetSelect.select2('destroy'); }

                assetSelect.prop('required', true).select2({
                    theme: 'bootstrap-5', width: '100%', placeholder: 'Klik untuk pilih SN Aset...',
                    ajax: {
                        url: "{{ route('goods-issues.search-assets') }}", dataType: 'json', delay: 250,
                        data: function (params) { return { item_id: data.id, warehouse_id: warehouseSelect.val(), search: params.term }; },
                        processResults: function (res) { return { results: res }; }
                    },
                    templateResult: formatAssetList, templateSelection: formatAssetSelection, escapeMarkup: function(m) { return m; }
                });
                tr.find('.general-notes').removeClass('d-none');
            }
            else if (data.available_bulk > 0 && data.available_asset <= 0) {
                assetContainer.addClass('d-none');
                if(assetSelect.hasClass("select2-hidden-accessible")) { assetSelect.select2('destroy'); }
                assetSelect.empty();

                setupBulkMode(tr, data);
            }
            else if (data.available_bulk > 0 && data.available_asset > 0) {
                Swal.fire({
                    title: 'Pilih Mode Pengeluaran',
                    text: `Terdapat ${data.available_bulk} stok biasa dan ${data.available_asset} unit Aset terdaftar. Apa yang ingin Anda keluarkan?`,
                    icon: 'question', showDenyButton: true,
                    confirmButtonText: '<i class="bi bi-pc-display"></i> Mode Aset',
                    denyButtonText: '<i class="bi bi-box"></i> Mode Stok Biasa',
                    confirmButtonColor: '#0dcaf0', denyButtonColor: '#198754',
                }).then((result) => {
                    if (result.isConfirmed) {
                        qtyContainer.addClass('d-none');
                        tr.find('.qty-input').val('');
                        batchSelect.prop('disabled', true).addClass('bg-light').html('<option value="">🔒 Ditentukan via Aset</option>');
                        snContainer.addClass('d-none');

                        assetContainer.removeClass('d-none');
                        if(assetSelect.hasClass("select2-hidden-accessible")) { assetSelect.select2('destroy'); }

                        assetSelect.prop('required', true).select2({
                            theme: 'bootstrap-5', width: '100%', placeholder: 'Klik untuk pilih SN Aset...',
                            ajax: {
                                url: "{{ route('goods-issues.search-assets') }}", dataType: 'json', delay: 250,
                                data: function (params) { return { item_id: data.id, warehouse_id: warehouseSelect.val(), search: params.term }; },
                                processResults: function (res) { return { results: res }; }
                            },
                            templateResult: formatAssetList, templateSelection: formatAssetSelection, escapeMarkup: function(m) { return m; }
                        });
                        tr.find('.general-notes').removeClass('d-none');
                    } else if (result.isDenied) {
                        assetContainer.addClass('d-none');
                        if(assetSelect.hasClass("select2-hidden-accessible")) { assetSelect.select2('destroy'); }
                        assetSelect.empty();

                        setupBulkMode(tr, data);
                    }
                });
            }
        });

        // 🔥 PENGHITUNG QTY OTOMATIS JIKA PILIH SN DROPDOWN 🔥
        tbody.on('change', '.sn-select', function() {
            let tr = $(this).closest('tr');
            let qtyInput = tr.find('.qty-input');
            let selectedCount = $(this).val() ? $(this).val().length : 0;
            qtyInput.val(selectedCount);
        });

        // 🔥 HITUNG ULANG MAX QTY SAAT SATUAN UOM DIUBAH 🔥
        tbody.on('change', '.uom-select', function() {
            let tr = $(this).closest('tr');
            let qtyInput = tr.find('.qty-input');
            let bulkStock = parseFloat(qtyInput.data('stock-bulk')) || 0;
            let conv = parseFloat($(this).find(':selected').data('conv')) || 1;
            let newMax = Math.floor(bulkStock / conv);

            qtyInput.attr('max', newMax);
            if (!qtyInput.prop('readonly')) {
                qtyInput.attr('placeholder', 'Max: ' + newMax);
                let currentVal = parseFloat(qtyInput.val());
                if (currentVal > newMax) {
                    qtyInput.val(newMax);
                    Swal.fire({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, icon: 'info', title: 'Kuantitas disesuaikan (' + newMax + ')' });
                }
            }
        });

        // 🔥 VALIDASI FORM SUBMIT 🔥
        $('#form-goods-issue').on('submit', function(e) {
            e.preventDefault();
            let form = this;

            if(!warehouseSelect.val()) {
                Swal.fire('Error', 'Gudang asal belum dipilih!', 'error');
                return;
            }

            let validItemCount = 0;
            let isAssetError = false;
            let isSnError = false;

            tbody.find('tr.item-row').each(function() {
                let itemVal = $(this).find('.item-select-ajax').val();
                if (!itemVal) {
                    $(this).remove();
                } else {
                    validItemCount++;

                    // Cek error Mode Aset
                    let assetContainer = $(this).find('.asset-container');
                    if (!assetContainer.hasClass('d-none')) {
                        let selectedAssets = $(this).find('.asset-select').val();
                        if (!selectedAssets || selectedAssets.length === 0) isAssetError = true;
                    }

                    // Cek error Mode SN Trackable
                    let snContainer = $(this).find('.minor-sn-container');
                    if (!snContainer.hasClass('d-none')) {
                        let selectedSns = $(this).find('.sn-select').val();
                        if (!selectedSns || selectedSns.length === 0) isSnError = true;
                    }
                }
            });

            if (validItemCount === 0) {
                Swal.fire('Data Kosong!', 'Anda harus memasukkan minimal 1 barang untuk dikeluarkan.', 'error');
                addRow(); return;
            }

            if (isAssetError) {
                Swal.fire('Data Aset Kosong!', 'Anda memilih mode Aset Tetap, tetapi belum memilih Nomor Aset yang akan dikeluarkan.', 'error');
                return;
            }

            if (isSnError) {
                Swal.fire('Serial Number Kosong!', 'Terdapat barang Wajib Lacak (Trackable). Anda harus memilih Serial Number-nya pada kotak kuning!', 'error');
                return;
            }

            Swal.fire({
                title: 'Konfirmasi Pengeluaran',
                text: "Barang akan langsung dipotong dari sistem dan diserahkan ke Karyawan. Lanjutkan?",
                icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc3545', cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-check-lg me-1"></i> Ya, Potong Stok!', cancelButtonText: 'Cek Lagi', borderRadius: '15px'
            }).then((result) => {
                if (result.isConfirmed) {
                    let btn = document.getElementById('btnSubmitGI');
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Memproses...';
                    btn.disabled = true;
                    form.submit();
                }
            });
        });

    });
</script>
@endpush
