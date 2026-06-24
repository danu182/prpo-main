@extends('layouts.app')

@push('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<style>
    .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice {
        max-width: 95%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: inline-block; vertical-align: middle;
        background-color: #0dcaf0; color: white; border: none; border-radius: 4px; padding: 2px 8px; font-size: 0.8rem; font-weight: 600;
    }
    .select2-container--bootstrap-5 .select2-selection { border-radius: 8px; border-color: #dee2e6; min-height: 38px; font-size: 0.875rem; }
    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered { color: #495057; padding-top: 2px; font-weight: 600; }
    .select2-results__option { font-size: 0.85rem; padding: 8px 12px; }
    .select2-container--bootstrap-5 .select2-selection--multiple { min-height: 38px; padding-bottom: 0px; }
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #a8a8a8; }
</style>
@endpush

@section('content')
<div class="container pb-5 text-dark">

    <div class="mb-4">
        <a href="{{ route('stock-transfers.index') }}" class="mb-2 text-decoration-none text-muted small fw-bold d-inline-block">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar Transfer
        </a>
        <h4 class="mb-0 fw-bold text-dark">
            <i class="bi bi-truck text-primary me-2"></i> Form Mutasi Antar Gudang
        </h4>
        <div class="mt-1 text-muted small">Pindahkan stok fisik dari satu Gudang ke Gudang lainnya.</div>
    </div>

    @if(session('error'))
        <div class="border-0 border-4 shadow-sm alert alert-danger rounded-3 fw-bold border-start border-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <form action="{{ route('stock-transfers.store') }}" method="POST" id="form-stock-transfer">
        @csrf

        {{-- CARD 1: INFORMASI GUDANG --}}
        <div class="mb-4 border-0 border-4 shadow-sm card rounded-4 border-start border-primary">
            <div class="p-4 card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Tanggal Mutasi <span class="text-danger">*</span></label>
                        <input type="date" name="transfer_date" class="shadow-sm form-control fw-bold text-primary" value="{{ date('Y-m-d') }}" required max="{{ date('Y-m-d') }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Dari Gudang (Asal) <span class="text-danger">*</span></label>
                        <select name="from_warehouse_id" id="from_warehouse_id" class="shadow-sm form-select border-danger text-danger fw-bold" required>
                            <option value="">-- Pilih Gudang Asal --</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="pb-2 text-center col-md-1 d-flex flex-column justify-content-end align-items-center">
                        <i class="bi bi-arrow-right-circle-fill text-muted fs-4"></i>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Ke Gudang (Tujuan) <span class="text-danger">*</span></label>
                        <select name="to_warehouse_id" id="to_warehouse_id" class="shadow-sm form-select border-success text-success fw-bold" required>
                            <option value="">-- Pilih Gudang Tujuan --</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        {{-- CARD 2: DAFTAR BARANG YANG DIPINDAH --}}
        <div class="border-0 border-4 shadow-sm card rounded-4 border-top border-primary">
            <div class="px-4 pt-4 pb-3 bg-white card-header border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-box-seam me-2 text-primary"></i>Daftar Barang Mutasi</h6>
                <button type="button" class="px-3 shadow-sm btn btn-sm btn-primary rounded-pill fw-bold" id="btn-add-item">
                    <i class="bi bi-plus-lg me-1"></i> Tambah Baris
                </button>
            </div>

            <div class="px-4 py-2 bg-info-subtle text-info-emphasis small fw-bold border-bottom">
                <i class="bi bi-info-circle me-1"></i> Pilih Gudang Asal terlebih dahulu untuk memuat stok yang tersedia.
            </div>

            <div class="p-0 card-body">
                <div class="table-responsive" style="min-height: 250px;">
                    <table class="table mb-0 align-middle table-borderless" id="transfer-table">
                        <thead class="bg-light text-muted small border-bottom text-uppercase">
                            <tr>
                                <th class="py-3 ps-4" width="35%">Pilih Barang</th>
                                <th class="py-3" width="25%">Ambil Dari Batch</th>
                                <th class="py-3 text-center" width="20%">Qty / Pilih Aset</th>
                                <th class="py-3" width="15%">Catatan SN</th>
                                <th class="py-3 text-center pe-4" width="5%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="item-tbody">
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="p-4 mb-3">
                <label class="form-label small fw-bold text-muted">Catatan Mutasi / Alasan Pemindahan</label>
                <textarea name="notes" class="shadow-sm form-control" rows="2" placeholder="Cth: Restock barang ke gudang operasional..."></textarea>
            </div>

            <div class="p-4 card-footer bg-light border-top text-end rounded-bottom-4">
                <button type="submit" id="btnSubmitTransfer" class="px-5 shadow-sm btn btn-primary rounded-pill fw-bold">
                    <i class="bi bi-send-check me-2"></i> Proses Mutasi Gudang
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
    // 🔥 FORMATTER VISUAL UNTUK ASET 🔥
    function formatAssetList(state) {
        if (!state.id) return state.text;
        let cleanText = $(`<div>${state.text}</div>`).text();
        let astNumber = cleanText.split(' (')[0] || cleanText;
        return $(`<div class="py-1"><div class="fw-bold text-dark small"><i class="bi bi-pc-display text-info me-1"></i> ${astNumber}</div></div>`);
    }
    function formatAssetSelection(state) {
        if (!state.id) return state.text;
        return state.text.split(' (')[0] || state.text;
    }

    $(document).ready(function() {
        let tbody = $('#item-tbody');
        let btnAdd = $('#btn-add-item');
        let fromWarehouseSelect = $('#from_warehouse_id');
        let toWarehouseSelect = $('#to_warehouse_id');
        let rowCount = 0;

        fromWarehouseSelect.on('change', function() {
            let currentRows = tbody.find('tr.item-row').length;
            if (currentRows > 0) {
                tbody.find('.item-select-ajax').select2('destroy');
                tbody.empty();
                rowCount = 0;
                addRow();
            }
        });

        btnAdd.on('click', function() {
            if(!fromWarehouseSelect.val()) {
                Swal.fire('Perhatian', 'Pilih Gudang Asal terlebih dahulu!', 'warning'); return;
            }
            addRow();
        });

        function addRow() {
            let tr = `
                <tr class="border-bottom item-row">
                    <td class="py-3 ps-4">
                        <select name="items[${rowCount}][item_id]" class="form-select item-select-ajax" required></select>
                        <div class="mt-2 stock-display text-muted small d-none"></div>
                    </td>
                    <td class="py-3">
                        <select name="items[${rowCount}][inventory_stock_id]" class="form-select form-select-sm batch-select bg-light" disabled>
                            <option value="">⚡ Mode FIFO (Otomatis)</option>
                        </select>
                    </td>
                    <td class="py-3">
                        <div class="qty-container">
                            <div class="mb-1 shadow-sm input-group input-group-sm">
                                <input type="number" name="items[${rowCount}][qty]" class="text-center form-control qty-input fw-bold text-primary border-primary" step="any" min="0.1" required>
                                <select name="items[${rowCount}][uom_info]" class="form-select bg-light text-dark uom-select border-primary fw-bold" style="min-width: 110px;"></select>
                            </div>
                        </div>

                        <div class="p-2 mt-2 border asset-container d-none bg-info-subtle border-info rounded-3">
                            <label class="mb-1 small text-info-emphasis fw-bold d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-pc-display me-1"></i> Pilih Unit Aset</span>
                                <span class="asset-count badge bg-info text-dark shadow-sm">0 Dipilih</span>
                            </label>
                            <select name="items[${rowCount}][asset_ids][]" class="shadow-sm form-select asset-select" multiple="multiple"></select>
                        </div>

                        <div class="p-2 mt-2 border minor-sn-container d-none bg-warning-subtle border-warning rounded-3"></div>
                    </td>
                    <td class="py-3">
                        <input type="text" name="items[${rowCount}][notes]" class="shadow-sm form-control form-control-sm general-notes" placeholder="Catatan opsional...">
                    </td>
                    <td class="py-3 text-center pe-4">
                        <button type="button" class="btn btn-sm btn-outline-danger btn-remove rounded-circle"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>
            `;
            let $tr = $(tr);
            tbody.append($tr);

            $tr.find('.item-select-ajax').select2({
                theme: 'bootstrap-5', placeholder: '-- Cari Barang --', minimumInputLength: 2,
                ajax: {
                    url: "{{ route('stock-transfers.search-items') }}", dataType: 'json', delay: 250,
                    data: function (params) { return { search: params.term, warehouse_id: fromWarehouseSelect.val() }; },
                    processResults: function (data) { return { results: data }; }
                }
            });
            rowCount++;
        }

        addRow();

        tbody.on('click', '.btn-remove', function() {
            if (tbody.find('tr').length > 1) {
                $(this).closest('tr').find('select').each(function() { if ($(this).hasClass("select2-hidden-accessible")) $(this).select2('destroy'); });
                $(this).closest('tr').remove();
            } else { Swal.fire('Ups!', 'Minimal sisakan 1 barang.', 'info'); }
        });

        // 🔥 FUNGSI OTOMATIS: MENGHITUNG JUMLAH ASET YANG DIPILIH 🔥
        tbody.on('change', '.asset-select', function() {
            let count = $(this).val() ? $(this).val().length : 0;
            $(this).closest('.asset-container').find('.asset-count').text(count + ' Dipilih');
        });

        // 🔥 FUNGSI UTAMA: MENGUBAH TAMPILAN MODE UI 🔥
        function applyRowMode(tr, mode, data) {
            let qtyContainer = tr.find('.qty-container');
            let qtyInput = tr.find('.qty-input');
            let uomSelect = tr.find('.uom-select');
            let assetContainer = tr.find('.asset-container');
            let assetSelect = tr.find('.asset-select');
            let batchSelect = tr.find('.batch-select');
            let snContainer = tr.find('.minor-sn-container');

            // Reset
            qtyInput.prop('required', false).val('');
            assetSelect.prop('required', false);

            if (mode === 'ASSET') {
                qtyContainer.addClass('d-none');
                batchSelect.prop('disabled', true).addClass('bg-light').html('<option value="">🔒 Mode Aset</option>');
                snContainer.addClass('d-none').empty();

                assetContainer.removeClass('d-none');
                tr.find('.asset-count').text('0 Dipilih'); // Reset counter

                if(assetSelect.hasClass("select2-hidden-accessible")) { assetSelect.select2('destroy'); }
                assetSelect.prop('required', true).select2({
                    theme: 'bootstrap-5', width: '100%', placeholder: 'Klik untuk pilih SN Aset...',
                    ajax: {
                        url: "{{ route('goods-issues.search-assets') }}", dataType: 'json', delay: 250,
                        data: function (params) { return { item_id: data.id, warehouse_id: fromWarehouseSelect.val(), search: params.term }; },
                        processResults: function (res) { return { results: res }; }
                    },
                    templateResult: formatAssetList, templateSelection: formatAssetSelection, escapeMarkup: function(m) { return m; }
                });
            } else {
                // BULK MODE
                assetContainer.addClass('d-none');
                qtyContainer.removeClass('d-none');
                qtyInput.prop('required', true).data('stock-bulk', data.available_bulk).attr('max', data.available_bulk).attr('placeholder', 'Max: ' + data.available_bulk);

                uomSelect.empty().append(`<option value="" data-conv="1">${data.base_uom}</option>`);
                if (data.uoms && data.uoms.length > 0) {
                    data.uoms.forEach(u => {
                        uomSelect.append(`<option value="${u.id}" data-conv="${u.conversion_qty}">${u.uom_name} (Isi ${u.conversion_qty})</option>`);
                    });
                }

                batchSelect.prop('disabled', false).removeClass('bg-light').html('<option value="">⚡ Auto (FIFO)</option>');

                if (data.is_trackable) {
                    snContainer.removeClass('d-none').empty();
                    qtyInput.trigger('input'); // Trigger form SN
                } else {
                    snContainer.addClass('d-none').empty();
                }
            }
        }

        // 🔥 LOGIKA METAMORFOSIS & POPUP PILIHAN 🔥
        tbody.on('select2:select', '.item-select-ajax', function (e) {
            let data = e.params.data;
            let tr = $(this).closest('tr');
            tr.data('is_trackable', data.is_trackable);

            // Tampilkan Stok Aset vs Stok Biasa
            tr.find('.stock-display').removeClass('d-none').html(`
                <span class="border badge bg-success-subtle text-success-emphasis border-success me-1">Stok Biasa: ${data.available_bulk}</span>
                ${data.available_asset > 0 ? `<span class="border badge bg-info-subtle text-info-emphasis border-info">Aset: ${data.available_asset}</span>` : ''}
            `);

            // JIKA BARANG PUNYA KEDUANYA (Stok Biasa & Aset), MUNCULKAN POPUP!
            if (data.available_asset > 0 && data.available_bulk > 0) {
                Swal.fire({
                    title: 'Pilih Mode Mutasi',
                    text: `Sistem mendeteksi [${data.text}] memiliki Stok Fisik Biasa dan terdaftar sebagai Aset. Transfer mana yang ingin Anda lakukan?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#0dcaf0',
                    cancelButtonColor: '#198754',
                    confirmButtonText: '<i class="bi bi-pc-display me-1"></i> Transfer Aset',
                    cancelButtonText: '<i class="bi bi-box-seam me-1"></i> Transfer Stok',
                    allowOutsideClick: false,
                    allowEscapeKey: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        applyRowMode(tr, 'ASSET', data);
                    } else {
                        applyRowMode(tr, 'BULK', data);
                    }
                });
            }
            // JIKA HANYA ASET
            else if (data.available_asset > 0) {
                applyRowMode(tr, 'ASSET', data);
            }
            // JIKA HANYA STOK BIASA
            else {
                applyRowMode(tr, 'BULK', data);
            }
        });

        // 🔥 LOGIKA UOM MAX QTY 🔥
        tbody.on('change', '.uom-select', function() {
            let tr = $(this).closest('tr');
            let qtyInput = tr.find('.qty-input');
            let bulkStock = parseFloat(qtyInput.data('stock-bulk')) || 0;
            let conv = parseFloat($(this).find(':selected').data('conv')) || 1;
            let newMax = Math.floor(bulkStock / conv);

            qtyInput.attr('max', newMax).attr('placeholder', 'Max: ' + newMax);
            if (parseFloat(qtyInput.val()) > newMax) {
                qtyInput.val(newMax);
                Swal.fire({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, icon: 'info', title: 'Qty disesuaikan dengan stok (' + newMax + ')' });
            }
        });

        // 🔥 LOGIKA KOTAK SN KUNING 🔥
        tbody.on('input', '.qty-input', function() {
            let tr = $(this).closest('tr');
            if (tr.data('is_trackable')) {
                let qty = parseInt($(this).val()) || 0;
                let snContainer = tr.find('.minor-sn-container');
                if (qty > 0) {
                    let html = `<span class="badge bg-warning text-dark w-100 mb-2"><i class="bi bi-upc-scan"></i> Ketik/Scan SN:</span><div class="custom-scrollbar" style="max-height: 100px; overflow-y: auto;">`;
                    for(let i=0; i<qty; i++) {
                        html += `<input type="text" class="form-control form-control-sm border-warning mb-1 minor-sn-input" placeholder="SN Unit ke-${i+1}" required>`;
                    }
                    html += `</div>`;
                    snContainer.removeClass('d-none').html(html);
                } else { snContainer.empty().addClass('d-none'); }
            }
        });

        // SUBMIT FORM VALIDATION
        $('#form-stock-transfer').on('submit', function(e) {
            e.preventDefault();
            let form = this;

            if (!form.checkValidity()) { form.reportValidity(); return; }

            if(fromWarehouseSelect.val() === toWarehouseSelect.val()) {
                Swal.fire('Gagal', 'Gudang Tujuan tidak boleh sama dengan Gudang Asal!', 'error'); return;
            }

            // Gabungkan SN Lacak ke dalam Keterangan
            tbody.find('tr.item-row').each(function() {
                let isTrackable = $(this).data('is_trackable');
                if (isTrackable) {
                    let snArray = [];
                    $(this).find('.minor-sn-input').each(function() { snArray.push('SN: ' + $(this).val()); });
                    $(this).find('.general-notes').val(snArray.join(' | '));
                }
            });

            Swal.fire({
                title: 'Proses Mutasi?',
                text: "Barang akan dipindahkan ke gudang tujuan.",
                icon: 'question', showCancelButton: true, confirmButtonText: 'Ya, Pindahkan!'
            }).then((result) => {
                if (result.isConfirmed) {
                    let btn = document.getElementById('btnSubmitTransfer');
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Memproses...';
                    btn.disabled = true;
                    form.submit();
                }
            });
        });
    });
</script>


@endpush
