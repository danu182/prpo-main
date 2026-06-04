@extends('layouts.app')

@push('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<style>
    /* Desain Elegan Select2 & Chip Aset */
    .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice {
        max-width: 95%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: inline-block; vertical-align: middle;
        background-color: #0dcaf0; color: white; border: none; border-radius: 4px; padding: 2px 8px; font-size: 0.8rem; font-weight: 600;
    }
    .select2-container--bootstrap-5 .select2-selection { border-radius: 8px; border-color: #dee2e6; min-height: 38px; font-size: 0.875rem; }
    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered { color: #495057; padding-top: 2px; font-weight: 600; }
    .select2-results__option { font-size: 0.85rem; padding: 8px 12px; }
    .select2-container--bootstrap-5 .select2-selection--multiple { min-height: 38px; padding-bottom: 0px; }

    /* Scrollbar Minimalis */
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
                            {{-- Baris JS --}}
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
    $(document).ready(function() {
        let tbody = $('#item-tbody');
        let btnAdd = $('#btn-add-item');
        let fromWarehouseSelect = $('#from_warehouse_id');
        let toWarehouseSelect = $('#to_warehouse_id');
        let rowCount = 0;

        // Reset Daftar Barang Jika Gudang Asal Diubah
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
                Swal.fire('Perhatian', 'Pilih Gudang Asal terlebih dahulu sebelum menambah baris!', 'warning');
                return;
            }
            addRow();
        });

        function addRow() {
            let tr = `
                <tr class="border-bottom item-row">
                    <td class="py-3 ps-4">
                        <select name="items[${rowCount}][item_id]" class="form-select item-select-ajax" required></select>
                    </td>
                    <td class="py-3">
                        <select name="items[${rowCount}][inventory_stock_id]" class="form-select form-select-sm batch-select bg-light" disabled>
                            <option value="">⚡ Auto (FIFO)</option>
                        </select>
                        <div class="mt-1 stock-display text-muted small"><i class="bi bi-dash-circle me-1"></i> Total Stok: 0</div>
                    </td>
                    <td class="py-3">
                        <div class="qty-container">
                            <input type="number" name="items[${rowCount}][qty]" class="text-center shadow-sm form-control qty-input fw-bold text-primary border-primary" step="0.01" min="0.1" required>
                        </div>
                        <div class="asset-container d-none">
                            <select name="items[${rowCount}][asset_ids][]" class="shadow-sm form-select asset-select border-info" multiple="multiple"></select>
                            <small class="mt-1 text-info fw-bold d-block"><i class="bi bi-pc-display"></i> Mode Pilih Aset</small>
                        </div>
                    </td>
                    <td class="py-3" style="vertical-align: middle;">
                        <input type="text" name="items[${rowCount}][notes]" class="shadow-sm form-control form-control-sm general-notes" placeholder="Catatan...">
                        <div class="minor-sn-container d-none"></div>
                    </td>
                    <td class="py-3 text-center pe-4">
                        <button type="button" class="btn btn-sm btn-outline-danger btn-remove rounded-circle" title="Hapus Baris"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>
            `;
            let $tr = $(tr);
            tbody.append($tr);

            // 🔥 PERBAIKAN: MENGGUNAKAN ROUTE STOCK TRANSFER SENDIRI 🔥
            $tr.find('.item-select-ajax').select2({
                theme: 'bootstrap-5',
                placeholder: '-- Cari & Ketik Barang --',
                minimumInputLength: 2,
                ajax: {
                    url: "{{ route('stock-transfers.search-items') }}",
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return { search: params.term, warehouse_id: fromWarehouseSelect.val() };
                    },
                    processResults: function (data) { return { results: data }; },
                    cache: true
                }
            });

            rowCount++;
        }

        addRow();

        tbody.on('click', '.btn-remove', function() {
            if (tbody.find('tr').length > 1) {
                $(this).closest('tr').find('select').each(function() {
                    if ($(this).hasClass("select2-hidden-accessible")) { $(this).select2('destroy'); }
                });
                $(this).closest('tr').remove();
            } else {
                Swal.fire('Ups!', 'Minimal harus ada 1 barang untuk dipindahkan.', 'info');
            }
        });

        // 🔥 LOGIKA METAMORFOSIS FORM (MIRIP GI) 🔥
        tbody.on('select2:select', '.item-select-ajax', function (e) {
            let data = e.params.data;
            let tr = $(this).closest('tr');
            tr.data('is_trackable', data.is_trackable);

            tr.find('.stock-display').html(`<i class="bi bi-check2-circle me-1"></i> Stok di Gudang Asal: <strong>${data.stock}</strong>`).removeClass('text-muted').addClass('text-success');

            let qtyContainer = tr.find('.qty-container');
            let qtyInput = tr.find('.qty-input');
            let assetContainer = tr.find('.asset-container');
            let assetSelect = tr.find('.asset-select');
            let batchSelect = tr.find('.batch-select');
            let generalNotes = tr.find('.general-notes');
            let snContainer = tr.find('.minor-sn-container');

            if(data.is_asset) {
                qtyContainer.addClass('d-none');
                qtyInput.prop('required', false).val('');
                batchSelect.prop('disabled', true).addClass('bg-light').html('<option value="">🔒 Ditentukan via Aset</option>');

                assetContainer.removeClass('d-none');
                if(assetSelect.hasClass("select2-hidden-accessible")) { assetSelect.select2('destroy'); }
                assetSelect.prop('required', true).select2({
                    theme: 'bootstrap-5', width: '100%', placeholder: 'Klik untuk pilih SN Aset...',
                    ajax: {
                        url: "{{ route('goods-issues.search-assets') }}", dataType: 'json', delay: 250,
                        data: function (params) { return { item_id: data.id, warehouse_id: fromWarehouseSelect.val(), search: params.term }; },
                        processResults: function (res) { return { results: res }; }
                    },
                    templateSelection: function (assetData) {
                        if (!assetData.id) { return assetData.text; }
                        let shortText = assetData.text.split(' | ')[0];
                        return $('<span>' + shortText + '</span>');
                    }
                });

                generalNotes.removeClass('d-none').prop('required', false);
                snContainer.addClass('d-none').empty();

            } else {
                assetContainer.addClass('d-none');
                if(assetSelect.hasClass("select2-hidden-accessible")) { assetSelect.select2('destroy'); }
                assetSelect.prop('required', false).empty();

                qtyContainer.removeClass('d-none');
                qtyInput.attr('max', data.stock).val('').prop('required', true);
                batchSelect.prop('disabled', false).removeClass('bg-light').html('<option value="">⏳ Memuat Batch...</option>');

                $.ajax({
                    url: "{{ route('goods-issues.search-batches') }}", type: "GET",
                    data: { item_id: data.id, warehouse_id: fromWarehouseSelect.val() },
                    success: function(res) {
                        let opts = '<option value="">⚡ Mode Otomatis (FIFO)</option>';
                        if(Array.isArray(res)) { res.forEach(b => { opts += `<option value="${b.id}">${b.text}</option>`; }); }
                        batchSelect.html(opts);
                    },
                    error: function() { batchSelect.html('<option value="">⚡ Mode Otomatis (FIFO)</option>'); }
                });

                if (data.is_trackable) {
                    generalNotes.addClass('d-none').prop('required', false);
                    snContainer.removeClass('d-none').empty();
                    qtyInput.trigger('input');
                } else {
                    generalNotes.removeClass('d-none').prop('required', false);
                    snContainer.addClass('d-none').empty();
                }
            }
        });

        // 🔥 LOGIKA KOTAK SN MINOR ASSET 🔥
        tbody.on('input', '.qty-input', function() {
            let tr = $(this).closest('tr');
            if (tr.data('is_trackable')) {
                let qty = parseInt($(this).val()) || 0;
                let snContainer = tr.find('.minor-sn-container');
                let nameAttr = $(this).attr('name');
                let match = nameAttr.match(/items\[(\d+)\]/);
                let idx = match ? match[1] : 0;

                if (qty > 0) {
                    let html = `
                        <div class="gap-1 mt-1 d-flex flex-column">
                            <span class="border badge bg-warning-subtle text-warning-emphasis border-warning-subtle text-start w-100" style="font-size: 0.65rem; padding: 5px 8px;">
                                <i class="bi bi-upc-scan me-1"></i> Wajib isi SN:
                            </span>
                            <div class="custom-scrollbar" style="max-height: 105px; overflow-y: auto; padding-right: 3px;">
                    `;
                    for(let i=0; i<qty; i++) {
                        html += `<input type="text" class="mb-1 shadow-sm form-control form-control-sm border-warning minor-sn-input" placeholder="SN Unit ke-${i+1} *" required style="font-size: 0.75rem; height: 30px;">`;
                    }
                    html += `</div></div>`;
                    snContainer.removeClass('d-none').html(html);
                } else {
                    snContainer.empty().addClass('d-none');
                }
            }
        });

        // VALIDASI SUBMIT
        $('#form-stock-transfer').on('submit', function(e) {
            e.preventDefault();
            let form = this;

            if(!fromWarehouseSelect.val() || !toWarehouseSelect.val()) {
                Swal.fire('Error', 'Gudang Asal dan Tujuan harus dipilih!', 'error');
                return;
            }

            if(fromWarehouseSelect.val() === toWarehouseSelect.val()) {
                Swal.fire('Gagal', 'Gudang Tujuan tidak boleh sama dengan Gudang Asal!', 'error');
                return;
            }

            let validItemCount = 0;
            let isAssetError = false;

            tbody.find('tr.item-row').each(function() {
                let itemVal = $(this).find('.item-select-ajax').val();
                if (!itemVal) {
                    $(this).remove();
                } else {
                    validItemCount++;
                    let assetContainer = $(this).find('.asset-container');
                    if (!assetContainer.hasClass('d-none')) {
                        let selectedAssets = $(this).find('.asset-select').val();
                        if (!selectedAssets || selectedAssets.length === 0) {
                            isAssetError = true;
                        }
                    }

                    let isTrackable = $(this).data('is_trackable');
                    if (isTrackable) {
                        let snInputs = $(this).find('.minor-sn-input');
                        let snArray = [];
                        snInputs.each(function() { snArray.push($(this).val()); });
                        $(this).find('.general-notes').val(snArray.join(' | '));
                    }
                }
            });

            if (validItemCount === 0) {
                Swal.fire('Data Kosong!', 'Masukkan minimal 1 barang untuk ditransfer.', 'error');
                addRow(); return;
            }
            if (isAssetError) {
                Swal.fire('Data Aset Kosong!', 'Pilih Nomor Aset/SN yang akan ditransfer.', 'error');
                return;
            }

            Swal.fire({
                title: 'Konfirmasi Mutasi',
                text: "Barang akan dipindahkan ke Gudang Tujuan. Lanjutkan?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#0d6efd',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-truck me-1"></i> Ya, Pindahkan!',
                cancelButtonText: 'Batal',
                borderRadius: '15px'
            }).then((result) => {
                if (result.isConfirmed) {
                    let btn = document.getElementById('btnSubmitTransfer');
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Memproses...';
                    btn.disabled = true;
                    form.submit();
                }
            });
        });
    });
</script>
@endpush
