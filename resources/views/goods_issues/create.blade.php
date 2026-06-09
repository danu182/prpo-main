@extends('layouts.app')

@push('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<style>
    /* 🔥 TAMBAHAN UNTUK MERAPIKAN KOTAK BIRU ASET 🔥 */
    .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice {
        max-width: 95%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        display: inline-block;
        vertical-align: middle;
    }

    .select2-container--bootstrap-5 .select2-selection {
        border-radius: 8px;
        border-color: #dee2e6;
        min-height: 38px;
        font-size: 0.875rem;
    }
    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
        color: #495057;
        padding-top: 2px;
        font-weight: 600;
    }
    .select2-results__option {
        font-size: 0.85rem;
        padding: 8px 12px;
    }
    .select2-container--bootstrap-5 .select2-selection--multiple {
        min-height: 38px;
        padding-bottom: 0px;
    }
    .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice {
        background-color: #0dcaf0;
        color: white;
        border: none;
        border-radius: 4px;
        padding: 2px 8px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    /* 🔥 Desain Scrollbar Minimalis untuk Form SN 🔥 */
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #a8a8a8; }
</style>
@endpush

@section('content')
<div class="container pb-5 text-dark">

    <div class="mb-4">
        <a href="{{ route('goods-issues.index') }}" class="mb-2 text-decoration-none text-muted small fw-bold d-inline-block">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar
        </a>
        <h4 class="mb-0 fw-bold text-dark">
            <i class="bi bi-box-arrow-up text-danger me-2"></i> Form Pengeluaran Barang (Goods Issue)
        </h4>
        <div class="mt-1 text-muted small">Keluarkan barang dari gudang untuk diserahkan ke operasional/karyawan.</div>
    </div>

    @if(session('error'))
        <div class="border-0 border-4 shadow-sm alert alert-danger rounded-3 fw-bold border-start border-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="border-0 border-4 shadow-sm alert alert-danger rounded-3 fw-bold border-start border-danger">
            <i class="bi bi-shield-exclamation me-2"></i> Ada data yang belum lengkap/salah format!
            <ul class="mt-1 mb-0 small fw-normal">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('goods-issues.store') }}" method="POST" id="form-goods-issue">
        @csrf

        {{-- CARD 1: INFORMASI PENGAMBILAN --}}
        <div class="mb-4 border-0 border-4 shadow-sm card rounded-4 border-start border-danger">
            <div class="p-4 card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Tanggal Keluar <span class="text-danger">*</span></label>
                        <input type="date" name="issue_date" class="shadow-sm form-control" value="{{ date('Y-m-d') }}" required max="{{ date('Y-m-d') }}">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Nama Penerima/Karyawan <span class="text-danger">*</span></label>
                        <select name="requester_name" id="requester_select" class="shadow-sm form-select select2-user" required>
                            <option value="">-- Pilih Karyawan --</option>
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
                        <input type="text" name="department" id="department_input" class="shadow-sm form-control bg-light" placeholder="Terisi otomatis..." readonly value="{{ old('department') }}">
                    </div>
                </div>
            </div>
        </div>

        {{-- CARD 2: DAFTAR BARANG --}}
        <div class="border-0 border-4 shadow-sm card rounded-4 border-top border-danger">
            <div class="px-4 pt-4 pb-3 bg-white card-header border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-box-seam me-2 text-danger"></i>Daftar Barang Dikeluarkan</h6>
                <button type="button" class="px-3 shadow-sm btn btn-sm btn-primary rounded-pill fw-bold" id="btn-add-item" style="display: none;">
                    <i class="bi bi-plus-lg me-1"></i> Tambah Baris
                </button>
            </div>

            {{-- ⚠️ PERINGATAN GUDANG (MUNCUL JIKA GUDANG BELUM DIPILIH) --}}
            <div class="px-4 py-3 text-center bg-warning-subtle text-warning-emphasis small fw-bold border-bottom" id="warehouse-warning">
                <i class="mb-1 bi bi-exclamation-circle fs-5 d-block"></i>
                SILAKAN PILIH "ASAL GUDANG" DI ATAS TERLEBIH DAHULU UNTUK MEMUNCULKAN DAFTAR BARANG.
            </div>

            <div class="p-0 card-body" id="table-container" style="display: none;">
                <div class="table-responsive" style="min-height: 250px;">
                    <table class="table mb-0 align-middle table-borderless" id="issue-table">
                        <thead class="bg-light text-muted small border-bottom text-uppercase">
                            <tr>
                                <th class="py-3 ps-4" width="35%">Pilih Barang</th>
                                <th class="py-3" width="25%">Batch / Lokasi</th>
                                <th class="py-3 text-center" width="20%">Qty Keluar / Pilih Aset</th>
                                <th class="py-3" width="15%">Catatan</th>
                                <th class="py-3 text-center pe-4" width="5%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="item-tbody">
                            {{-- Baris JS muncul di sini --}}
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="p-4 mb-3 border-top">
                <label class="form-label small fw-bold text-muted">Catatan Umum / Alasan Pengeluaran</label>
                <textarea name="notes" class="shadow-sm form-control" rows="2" placeholder="Cth: Untuk kebutuhan operasional kantor...">{{ old('notes') }}</textarea>
            </div>

            <div class="p-4 card-footer bg-light border-top text-end rounded-bottom-4">
                <button type="submit" id="btnSubmitGI" class="px-5 shadow-sm btn btn-danger rounded-pill fw-bold">
                    <i class="bi bi-send-check me-2"></i> Proses Pengeluaran Stok
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
    // 🔥 FUNGSI SIHIR: DESAIN KARTU DROPDOWN ASET 🔥
    // =========================================================================
    function formatAssetList(state) {
        if (!state.id) return state.text;

        // Sapu bersih tag HTML (seperti <p>) yang bocor dari database
        let tempDiv = document.createElement("div");
        tempDiv.innerHTML = state.text;
        let cleanText = tempDiv.textContent || tempDiv.innerText || "";

        // Pecah teks asli dari backend yang formatnya: "AST/2026/05/001 (solar) | SN: SKU-..."
        let astNumber = cleanText.split(' (')[0] || cleanText;

        let descMatch = cleanText.match(/\((.*?)\)/);
        let description = descMatch ? descMatch[1] : '';

        let snMatch = cleanText.split('| SN: ');
        let serialNumber = snMatch.length > 1 ? snMatch[1].trim() : '';

        // Bangun ulang UI yang sangat rapi (DENGAN TEKS WRAP AGAR SN FULL TERBACA)
        let $html = $(`
            <div class="py-1 d-flex flex-column border-bottom border-light">
                <span class="fw-bold text-dark" style="font-size: 0.85rem;">
                    <i class="bi bi-box me-1 text-primary"></i> ${astNumber}
                </span>
                ${description ? `<span class="mt-1 text-muted text-truncate" style="font-size: 0.7rem; max-width: 100%;">Spec: ${description}</span>` : ''}
                ${serialNumber ? `<div class="mt-1"><span class="border badge bg-warning-subtle text-warning-emphasis border-warning text-wrap text-start" style="font-size: 0.7rem; line-height: 1.4; word-break: break-word; width: 100%;"><i class="bi bi-upc-scan me-1"></i> SN: ${serialNumber}</span></div>` : ''}
            </div>
        `);
        return $html;
    }

    function formatAssetSelection(state) {
        if (!state.id) return state.text;

        // Bersihkan HTML
        let tempDiv = document.createElement("div");
        tempDiv.innerHTML = state.text;
        let cleanText = tempDiv.textContent || tempDiv.innerText || "";

        // Ambil SN-nya saja untuk ditampilkan di kotak yang terpilih
        let snMatch = cleanText.split('| SN: ');
        let serialNumber = snMatch.length > 1 ? snMatch[1].trim() : cleanText.split(' ')[0];

        return $(`<span class="fw-bold text-dark" style="font-size: 0.8rem;"><i class="bi bi-check-circle-fill text-success me-1"></i> ${serialNumber}</span>`);
    }

    $(document).ready(function() {

        // 🔥 FITUR AUTO-FILL DEPARTEMEN SAAT KARYAWAN DIPILIH 🔥
        $('#requester_select').on('change', function() {
            let selectedOption = $(this).find(':selected');
            let deptName = selectedOption.data('dept');
            if (deptName) {
                $('#department_input').val(deptName);
            } else {
                $('#department_input').val('');
            }
        });


        let tbody = $('#item-tbody');
        let btnAdd = $('#btn-add-item');
        let warehouseSelect = $('#warehouse_select');
        let warehouseWarning = $('#warehouse-warning');
        let tableContainer = $('#table-container');
        let rowCount = 0;

        // 🔥 LOGIKA KUNCI GUDANG: Sembunyikan form jika gudang belum dipilih
        function toggleTableVisibility() {
            if (warehouseSelect.val()) {
                warehouseWarning.hide();
                tableContainer.show();
                btnAdd.show();
            } else {
                warehouseWarning.show();
                tableContainer.hide();
                btnAdd.hide();
            }
        }

        // Jalankan saat pertama kali load
        toggleTableVisibility();

        // Reset Daftar Barang Jika Gudang Diubah
        warehouseSelect.on('change', function() {
            toggleTableVisibility();
            let currentRows = tbody.find('tr.item-row').length;
            if (currentRows > 0) {
                tbody.find('.item-select-ajax').select2('destroy');
                tbody.empty();
                rowCount = 0;
            }
            // Tambahkan 1 baris otomatis jika gudang sudah dipilih
            if($(this).val()) { addRow(); }
        });

        // Validasi Tambah Baris
        btnAdd.on('click', function() {
            if(!warehouseSelect.val()) {
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
                            <div class="shadow-sm input-group input-group-sm">
                                <input type="number" name="items[${rowCount}][qty_issued]" class="text-center form-control qty-input fw-bold text-danger border-danger" step="0.01" min="0.1" required placeholder="Qty">
                                <select name="items[${rowCount}][uom_info]" class="form-select bg-light text-dark uom-select border-danger fw-bold" style="min-width: 140px; cursor: pointer;"></select>
                            </div>
                        </div>
                        <div class="asset-container d-none">
                            <select name="items[${rowCount}][asset_ids][]" class="shadow-sm form-select asset-select border-info" multiple="multiple"></select>
                            <small class="mt-1 text-info fw-bold d-block"><i class="bi bi-pc-display"></i> Mode Pilih Aset</small>
                        </div>
                    </td>
                    <td class="py-3" style="vertical-align: middle;">
                        <input type="text" name="items[${rowCount}][notes]" class="shadow-sm form-control form-control-sm general-notes" placeholder="Ref/Catatan Umum...">
                        <div class="minor-sn-container d-none"></div>
                    </td>
                    <td class="py-3 text-center pe-4">
                        <button type="button" class="btn btn-sm btn-outline-danger btn-remove rounded-circle" title="Hapus Baris"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>
            `;
            let $tr = $(tr);
            tbody.append($tr);

            $tr.find('.item-select-ajax').select2({
                theme: 'bootstrap-5',
                placeholder: 'Ketik min. 2 huruf barang...',
                minimumInputLength: 2,
                ajax: {
                    url: "{{ route('goods-issues.search-items') }}",
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return { search: params.term, warehouse_id: warehouseSelect.val() };
                    },
                    processResults: function (data) { return { results: data }; },
                    cache: true
                }
            });

            rowCount++;
        }

        tbody.on('click', '.btn-remove', function() {
            if (tbody.find('tr').length > 1) {
                $(this).closest('tr').find('select').each(function() {
                    if ($(this).hasClass("select2-hidden-accessible")) { $(this).select2('destroy'); }
                });
                $(this).closest('tr').remove();
            } else {
                Swal.fire('Ups!', 'Minimal harus ada 1 barang untuk dikeluarkan.', 'info');
            }
        });

        // 🔥 LOGIKA METAMORFOSIS FORM (HYBRID ASET & BULK) 🔥
        tbody.on('select2:select', '.item-select-ajax', function (e) {
            let data = e.params.data;
            let tr = $(this).closest('tr');

            tr.data('is_trackable', data.is_trackable);
            tr.find('.stock-display').html(`<i class="bi bi-info-circle-fill me-1"></i> Stok Biasa: <strong>${data.available_bulk}</strong> | Aset: <strong>${data.available_asset}</strong>`).removeClass('text-muted').addClass('text-primary');

            let qtyContainer = tr.find('.qty-container');
            let qtyInput = tr.find('.qty-input');
            let assetContainer = tr.find('.asset-container');
            let assetSelect = tr.find('.asset-select');
            let batchSelect = tr.find('.batch-select');
            let generalNotes = tr.find('.general-notes');
            let snContainer = tr.find('.minor-sn-container');

            // Hapus paksaan sebelumnya
            qtyInput.prop('required', false);
            assetSelect.prop('required', false);

            // ========================================================
            // SCENARIO 1: BARANG MURNI ASET (Atau hanya ada stok aset)
            // ========================================================
            if(data.is_asset || (data.available_asset > 0 && data.available_bulk <= 0)) {
                qtyContainer.addClass('d-none');
                qtyInput.val('');
                batchSelect.prop('disabled', true).addClass('bg-light').html('<option value="">🔒 Ditentukan via Aset</option>');

                assetContainer.removeClass('d-none');
                if(assetSelect.hasClass("select2-hidden-accessible")) { assetSelect.select2('destroy'); }

                assetSelect.prop('required', true).select2({
                    theme: 'bootstrap-5', width: '100%', placeholder: 'Klik untuk pilih SN Aset...',
                    ajax: {
                        url: "{{ route('goods-issues.search-assets') }}", dataType: 'json', delay: 250,
                        data: function (params) { return { item_id: data.id, warehouse_id: warehouseSelect.val(), search: params.term }; },
                        processResults: function (res) { return { results: res }; }
                    },
                    templateResult: formatAssetList,
                    templateSelection: formatAssetSelection,
                    escapeMarkup: function(m) { return m; }
                });

                generalNotes.removeClass('d-none');
                snContainer.addClass('d-none').empty();
            }

            // ========================================================
            // SCENARIO 2: BARANG MURNI BULK (Hanya ada stok biasa)
            // ========================================================
            else if (data.available_bulk > 0 && data.available_asset <= 0) {
                assetContainer.addClass('d-none');
                if(assetSelect.hasClass("select2-hidden-accessible")) { assetSelect.select2('destroy'); }
                assetSelect.empty();

                qtyContainer.removeClass('d-none');
                batchSelect.prop('disabled', false).removeClass('bg-light').html('<option value="">⏳ Memuat Batch...</option>');

                let uomSelect = tr.find('.uom-select');
                uomSelect.empty();

                // 🔥 1. SELALU TAMBAHKAN SATUAN TERKECIL (BASE UOM) PERTAMA KALI 🔥
                // Ambil dari data relasi item (data.uom.name) jika ada, atau default ke 'PCS'
                let baseUomName = (data.uom && data.uom.name) ? data.uom.name : 'PCS';
                uomSelect.append(`<option value="" data-conv="1">${baseUomName}</option>`);

                // 🔥 2. BARU MASUKKAN SATUAN PACK/DUS JIKA ADA 🔥
                let listUom = data.uoms || data.item_uoms || [];
                if (listUom.length > 0) {
                    listUom.forEach(u => {
                        let namaUom = u.uom_name || u.name || 'PCS';
                        let konversi = parseFloat(u.conversion_qty || u.conversion || 1);
                        let idUom = u.id || '';

                        let teksTampil = konversi > 1 ? `${namaUom} (Isi ${konversi})` : namaUom;
                        uomSelect.append(`<option value="${idUom}" data-conv="${konversi}">${teksTampil}</option>`);
                    });
                } else {
                    uomSelect.append(`<option value="" data-conv="1">PCS</option>`);
                }

                // 🔥 SIMPAN STOK ASLI & HITUNG MAX BERDASARKAN SATUAN PERTAMA 🔥
                qtyInput.data('stock-bulk', data.available_bulk);
                let initialConv = parseFloat(uomSelect.find(':selected').data('conv')) || 1;
                let initialMax = Math.floor(data.available_bulk / initialConv); // Gunakan Math.floor agar tidak ada desimal aneh

                qtyInput.attr('max', initialMax).prop('required', true).attr('placeholder', 'Max: ' + initialMax);


                $.ajax({
                    url: "{{ route('goods-issues.search-batches') }}", type: "GET",
                    data: { item_id: data.id, warehouse_id: warehouseSelect.val() },
                    success: function(res) {
                        let opts = '<option value="">⚡ Mode Otomatis (FIFO)</option>';
                        if(Array.isArray(res)) { res.forEach(b => { opts += `<option value="${b.id}">${b.text}</option>`; }); }
                        batchSelect.html(opts);
                    }
                });

                generalNotes.removeClass('d-none');
            }

            // ========================================================
            // SCENARIO 3: HYBRID (Ada Stok Biasa & Ada Stok Aset)
            // ========================================================
            else if (data.available_bulk > 0 && data.available_asset > 0) {
                // Tampilkan SweetAlert untuk menyuruh User memilih
                Swal.fire({
                    title: 'Pilih Mode Pengeluaran',
                    text: `Terdapat ${data.available_bulk} stok biasa dan ${data.available_asset} unit Aset terdaftar. Apa yang ingin Anda keluarkan?`,
                    icon: 'question',
                    showDenyButton: true,
                    confirmButtonText: '<i class="bi bi-upc-scan"></i> Keluarkan Aset',
                    denyButtonText: '<i class="bi bi-box"></i> Keluarkan Stok Biasa',
                    confirmButtonColor: '#ffc107',
                    denyButtonColor: '#0dcaf0',
                }).then((result) => {
                    if (result.isConfirmed) {
                        // USER PILIH ASET
                        qtyContainer.addClass('d-none');
                        qtyInput.val('');
                        batchSelect.prop('disabled', true).addClass('bg-light').html('<option value="">🔒 Ditentukan via Aset</option>');

                        assetContainer.removeClass('d-none');
                        if(assetSelect.hasClass("select2-hidden-accessible")) { assetSelect.select2('destroy'); }

                        assetSelect.prop('required', true).select2({
                            theme: 'bootstrap-5', width: '100%', placeholder: 'Klik untuk pilih SN Aset...',
                            ajax: {
                                url: "{{ route('goods-issues.search-assets') }}", dataType: 'json', delay: 250,
                                data: function (params) { return { item_id: data.id, warehouse_id: warehouseSelect.val(), search: params.term }; },
                                processResults: function (res) { return { results: res }; }
                            },
                            templateResult: formatAssetList,
                            templateSelection: formatAssetSelection,
                            escapeMarkup: function(m) { return m; }
                        });
                        generalNotes.removeClass('d-none');
                    } else if (result.isDenied) {
                        // USER PILIH STOK BIASA
                        assetContainer.addClass('d-none');
                        if(assetSelect.hasClass("select2-hidden-accessible")) { assetSelect.select2('destroy'); }
                        assetSelect.empty();

                        qtyContainer.removeClass('d-none');
                        batchSelect.prop('disabled', false).removeClass('bg-light').html('<option value="">⏳ Memuat Batch...</option>');

                        let uomSelect = tr.find('.uom-select');
                        uomSelect.empty();

                        let listUom = data.uoms || data.item_uoms || [];

                        if (listUom.length > 0) {
                            listUom.forEach(u => {
                                let namaUom = u.uom_name || u.name || 'PCS';
                                let konversi = parseFloat(u.conversion_qty || u.conversion || 1);
                                let idUom = u.id || '';

                                let teksTampil = konversi > 1 ? `${namaUom} (Isi ${konversi})` : namaUom;
                                uomSelect.append(`<option value="${idUom}" data-conv="${konversi}">${teksTampil}</option>`);
                            });
                        } else {
                            uomSelect.append(`<option value="" data-conv="1">PCS</option>`);
                        }

                        qtyInput.attr('max', data.available_bulk).prop('required', true).attr('placeholder', 'Max: ' + data.available_bulk);

                        $.ajax({
                            url: "{{ route('goods-issues.search-batches') }}", type: "GET",
                            data: { item_id: data.id, warehouse_id: warehouseSelect.val() },
                            success: function(res) {
                                let opts = '<option value="">⚡ Mode Otomatis (FIFO)</option>';
                                if(Array.isArray(res)) { res.forEach(b => { opts += `<option value="${b.id}">${b.text}</option>`; }); }
                                batchSelect.html(opts);
                            }
                        });
                        generalNotes.removeClass('d-none');
                    }
                });
            }
        });

        // 🔥 LOGIKA AJAIB: MENGHITUNG ULANG MAX QTY SAAT SATUAN UOM DIUBAH 🔥
        tbody.on('change', '.uom-select', function() {
            let tr = $(this).closest('tr');
            let qtyInput = tr.find('.qty-input');

            // Ambil stok asli (Pcs) yang tadi kita simpan
            let bulkStock = parseFloat(qtyInput.data('stock-bulk')) || 0;

            // Ambil nilai konversi dari UOM yang baru saja dipilih
            let conv = parseFloat($(this).find(':selected').data('conv')) || 1;

            // Hitung Max yang baru (Contoh: 20 Pcs / 10 (Isi Pack) = Max 2)
            let newMax = Math.floor(bulkStock / conv);

            // Update atribut input
            qtyInput.attr('max', newMax).attr('placeholder', 'Max: ' + newMax);

            // Jika user terlanjur ngetik angka 5, tapi max-nya ternyata 2, otomatis turunkan angkanya!
            let currentVal = parseFloat(qtyInput.val());
            if (currentVal > newMax) {
                qtyInput.val(newMax);
                Swal.fire({
                    toast: true, position: 'top-end', showConfirmButton: false, timer: 3000,
                    icon: 'info', title: 'Kuantitas disesuaikan dengan sisa stok (' + newMax + ')'
                });
            }
        });

        // Validasi Form
        $('#form-goods-issue').on('submit', function(e) {
            e.preventDefault();
            let form = this;

            if(!warehouseSelect.val()) {
                Swal.fire('Error', 'Gudang asal belum dipilih!', 'error');
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
                }
            });

            if (validItemCount === 0) {
                Swal.fire('Data Kosong!', 'Anda harus memasukkan minimal 1 barang untuk dikeluarkan.', 'error');
                addRow();
                return;
            }

            if (isAssetError) {
                Swal.fire('Data Aset Kosong!', 'Anda memilih barang berupa Aset Tetap, tetapi belum memilih Nomor Aset/SN yang akan dikeluarkan.', 'error');
                return;
            }

            Swal.fire({
                title: 'Konfirmasi Pengeluaran',
                text: "Barang akan langsung dipotong dari sistem dan status aset akan diperbarui. Lanjutkan?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-check-lg me-1"></i> Ya, Potong Stok!',
                cancelButtonText: 'Cek Lagi',
                borderRadius: '15px'
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
