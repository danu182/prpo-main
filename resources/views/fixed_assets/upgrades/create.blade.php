@extends('layouts.app')

@push('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
<style>
    .select2-container .select2-selection--single { height: 38px !important; border: 1px solid #cbd5e1 !important; border-radius: 8px !important; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 36px !important; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px !important; }
    .treatment-card { cursor: pointer; border: 2px solid #e2e8f0; transition: all 0.2s; }
    .treatment-card:hover { border-color: #93c5fd; }
    .treatment-card.active { border-color: #0d6efd; background-color: #eff6ff; }
</style>
@endpush

@section('content')
<div class="pb-5 container-fluid text-dark">
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-cpu-fill text-warning me-2"></i> Pengeluaran Barang Gudang untuk Upgrade Aset</h4>
            <div class="text-muted small">Keluarkan sparepart dari stok gudang langsung ke unit aset tertentu, lengkap dengan nama varian PO & kontrol dampak nilai/umur aset.</div>
        </div>
        <a href="{{ route('fixed-assets.upgrades.index') }}" class="btn btn-outline-secondary rounded-pill fw-bold">
            <i class="bi bi-arrow-left me-1"></i> Riwayat Upgrade
        </a>
    </div>

    @if(session('error'))
        <div class="border-0 border-4 shadow-sm alert alert-danger rounded-4 border-start border-danger">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="border-0 border-4 shadow-sm alert alert-danger rounded-4 border-start border-danger">
            <ul class="mb-0 small">@foreach($errors->all() as $err) <li>{{ $err }}</li> @endforeach</ul>
        </div>
    @endif

    <form action="{{ route('fixed-assets.upgrades.store') }}" method="POST" id="formAssetUpgrade">
        @csrf
        <div class="row g-4">
            {{-- KOLOM KIRI: PILIH ASET & PART UPGRADE --}}
            <div class="col-lg-8">

                {{-- 1. PILIH UNIT ASET TARGET --}}
                <div class="mb-4 border-0 shadow-sm card rounded-4">
                    <div class="py-3 bg-white card-header border-bottom">
                        <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-1-circle-fill me-2"></i>Pilih Unit Aset yang Akan Di-Upgrade</h6>
                    </div>
                    <div class="p-4 card-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label small fw-bold text-muted text-uppercase">Unit Aset Target <span class="text-danger">*</span></label>
                                <select name="fixed_asset_id" id="selectTargetAsset" class="form-select select2-asset" required>
                                    <option value="">-- Cari No. Aset / Varian / Nama / Label FA / Pemegang --</option>
                                    @foreach($assets as $ast)
                                        @php
                                            $astVariant = !empty($ast->item_alias_name) ? " [{$ast->item_alias_name}]" : "";
                                        @endphp
                                        <option value="{{ $ast->id }}"
                                            data-number="{{ $ast->asset_number }}"
                                            data-name="{{ $ast->name }}"
                                            data-variant="{{ $ast->item_alias_name ?? '' }}"
                                            data-fa="{{ $ast->accounting_asset_number ?? '-' }}"
                                            data-holder="{{ optional($ast->assignee)->name ?? 'Di Gudang' }}"
                                            data-company="{{ optional($ast->company)->name ?? '-' }}"
                                            data-acq-date="{{ $ast->acquisition_date }}"
                                            data-initial="{{ (float)$ast->purchase_price }}"
                                            data-nbv="{{ $ast->computed_nbv }}"
                                            data-rem-months="{{ $ast->computed_remaining_months }}"
                                            data-life-years="{{ optional($ast->assetCategory)->useful_life_years ?? 4 }}"
                                            {{ ($selectedAssetId == $ast->id || old('fixed_asset_id') == $ast->id) ? 'selected' : '' }}>
                                            [{{ $ast->asset_number }}]{{ $astVariant }} {{ $ast->name }} — ({{ optional($ast->assignee)->name ?? 'Gudang' }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted text-uppercase">Tanggal Pemasangan <span class="text-danger">*</span></label>
                                <input type="date" name="upgrade_date" id="upgradeDateInput" class="form-control fw-bold border-primary" value="{{ old('upgrade_date', date('Y-m-d')) }}" required>
                            </div>
                        </div>

                        {{-- INFO POSISI ASET SAAT INI --}}
                        <div id="assetInfoPanel" class="p-3 mt-3 border rounded-3 bg-light" style="display: none;">
                            <div class="row g-2 small">
                                <div class="col-md-4">
                                    <span class="text-muted d-block">Label Akuntansi / Pemegang:</span>
                                    <strong class="text-dark" id="infoAssetHolder">-</strong>
                                </div>
                                <div class="col-md-4">
                                    <span class="text-muted d-block">Harga Perolehan Awal:</span>
                                    <strong class="text-dark" id="infoInitialPrice">Rp 0</strong>
                                </div>
                                <div class="col-md-4">
                                    <span class="text-muted d-block">Nilai Buku Saat Ini (NBV):</span>
                                    <strong class="text-primary fs-6" id="infoCurrentNbv">Rp 0</strong>
                                </div>
                                <div class="col-12 pt-1 border-top mt-2 d-flex justify-content-between align-items-center">
                                    <span>Tanggal Beli: <strong id="infoAcqDate">-</strong> (Kategori Umur: <strong id="infoLifeYears">4</strong> Thn)</span>
                                    <span id="infoRemainingBadge" class="badge bg-info text-dark">Sisa Umur: - Bulan</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 2. DAFTAR PART / KOMPONEN YANG DIPASANG --}}
                <div class="mb-4 border-0 shadow-sm card rounded-4">
                    <div class="py-3 bg-white card-header border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-2-circle-fill me-2"></i>Daftar Part / Komponen Upgrade</h6>
                        <div class="gap-2 d-flex">
                            <button type="button" class="btn btn-sm btn-primary rounded-pill fw-bold" id="btnAddWarehousePart">
                                <i class="bi bi-box-seam me-1"></i> + Ambil Part dari Gudang
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill fw-bold" id="btnAddServicePart">
                                <i class="bi bi-wrench me-1"></i> + Jasa / Non-Stok
                            </button>
                        </div>
                    </div>
                    <div class="p-3 card-body bg-light">
                        <div id="partsContainer" class="gap-3 d-flex flex-column"></div>
                        <div id="emptyPartsNotice" class="p-4 text-center bg-white border border-dashed rounded-3 text-muted small">
                            Klik <strong>"+ Ambil Part dari Gudang"</strong> untuk memilih sparepart (RAM, SSD, NVMe, dll) yang sudah dibeli lewat PO dan tersedia di gudang.
                        </div>
                    </div>
                </div>

                {{-- 3. PERLAKUAN AKUNTANSI (PILIHAN EKSPLISIT USER) --}}
                <div class="border-0 shadow-sm card rounded-4">
                    <div class="py-3 bg-white card-header border-bottom">
                        <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-3-circle-fill me-2"></i>Perlakuan Nilai & Umur Aset (Wajib Ditentukan)</h6>
                    </div>
                    <div class="p-4 card-body">
                        <div class="alert alert-warning small mb-3">
                            <i class="bi bi-info-circle-fill me-1"></i>
                            <strong>Penting:</strong> Sistem tidak mengubah nilai buku maupun umur aset tanpa pilihan Anda. Tentukan dampak biaya upgrade ini terhadap pembukuan aset:
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="p-3 rounded-3 treatment-card h-100 d-block active" id="card_EXPENSE">
                                    <input type="radio" name="accounting_treatment" value="EXPENSE" class="form-check-input me-1 treatment-radio" checked>
                                    <span class="fw-bold text-dark">1. Beban Pemeliharaan (Expense)</span>
                                    <div class="mt-2 small text-muted">
                                        Part tetap keluar dari gudang & tercatat di riwayat aset, namun <strong>Nilai Buku tidak bertambah</strong> dan <strong>Umur tetap</strong>.
                                    </div>
                                </label>
                            </div>

                            <div class="col-md-4">
                                <label class="p-3 rounded-3 treatment-card h-100 d-block" id="card_CAPITALIZE_VALUE">
                                    <input type="radio" name="accounting_treatment" value="CAPITALIZE_VALUE" id="radioCapValue" class="form-check-input me-1 treatment-radio">
                                    <span class="fw-bold text-primary">2. Tambah Nilai Aset (Sisa Umur Tetap)</span>
                                    <div class="mt-2 small text-muted">
                                        Biaya part <strong>menambah Nilai Buku saat ini</strong>, dan disusutkan mengikuti <strong>sisa bulan umur ekonomis yang ada</strong>.
                                    </div>
                                    <div id="capValueExpiredWarn" class="mt-1 text-danger fw-bold" style="font-size: 0.72rem; display: none;">
                                        *Tidak tersedia karena sisa umur aset sudah 0 bulan. Gunakan Opsi 3.
                                    </div>
                                </label>
                            </div>

                            <div class="col-md-4">
                                <label class="p-3 rounded-3 treatment-card h-100 d-block" id="card_CAPITALIZE_EXTEND">
                                    <input type="radio" name="accounting_treatment" value="CAPITALIZE_EXTEND" id="radioCapExtend" class="form-check-input me-1 treatment-radio">
                                    <span class="fw-bold text-success">3. Tambah Nilai + Setel Sisa Umur Baru</span>
                                    <div class="mt-2 small text-muted">
                                        Biaya part <strong>menambah Nilai Buku</strong> DAN menetapkan <strong>sisa umur ekonomis baru (bulan)</strong> sejak tgl upgrade. Cocok untuk aset yang umurnya sudah habis (Nilai Buku 0).
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div id="extendMonthsWrapper" class="p-3 mt-3 border rounded-3 bg-success-subtle border-success" style="display: none;">
                            <label class="form-label small fw-bold text-success-emphasis">
                                Estimasi Sisa Umur Ekonomis Baru Terhitung Sejak Tanggal Upgrade (dalam Bulan) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group" style="max-width: 280px;">
                                <input type="number" name="new_remaining_months" id="newRemainingMonthsInput" class="form-control fw-bold border-success" min="1" max="600" placeholder="Cth: 12 atau 24">
                                <span class="input-group-text bg-success text-white fw-bold">Bulan</span>
                            </div>
                            <div class="form-text text-dark small mt-1">
                                Contoh: Jika laptop berumur 10 tahun (nilai buku Rp 0) di-upgrade Rp 5.000.000 dan diperkirakan layak pakai 2 tahun lagi, isi <strong>24 Bulan</strong>.
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="form-label small fw-bold text-muted">Keterangan / Alasan Upgrade (Tercatat Permanen di Log Aset)</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Cth: Upgrade RAM 4GB ke 16GB & SSD 512GB ke 1TB untuk kebutuhan desain...">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            {{-- KOLOM KANAN: SIMULASI DAMPAK NILAI & PENYUSUTAN --}}
            <div class="col-lg-4">
                <div class="border-0 shadow-lg card rounded-4 sticky-top" style="top: 20px;">
                    <div class="p-3 text-white card-header bg-dark rounded-top-4">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-calculator me-2 text-warning"></i>Simulasi Dampak Akuntansi Aset</h6>
                    </div>
                    <div class="p-4 card-body">
                        <div class="mb-2 d-flex justify-content-between small">
                            <span class="text-muted">Nilai Buku Sebelum Upgrade:</span>
                            <strong id="simNbvBefore">Rp 0</strong>
                        </div>
                        <div class="mb-2 d-flex justify-content-between small">
                            <span class="text-muted">Total Biaya Part Upgrade:</span>
                            <strong class="text-warning-emphasis" id="simTotalUpgrade">Rp 0</strong>
                        </div>
                        <hr>
                        <div class="mb-2 d-flex justify-content-between">
                            <span class="fw-bold text-dark">Nilai Buku Setelah Upgrade:</span>
                            <strong class="text-primary fs-5" id="simNbvAfter">Rp 0</strong>
                        </div>
                        <div class="mb-2 d-flex justify-content-between small">
                            <span class="text-muted">Sisa Umur Penyusutan:</span>
                            <strong id="simRemMonths">0 Bulan</strong>
                        </div>
                        <div class="p-3 mt-3 border rounded-3 bg-light">
                            <div class="small text-muted">Estimasi Beban Penyusutan Baru:</div>
                            <div class="fs-5 fw-bolder text-success" id="simMonthlyDep">Rp 0 / bulan</div>
                            <div class="mt-1 text-muted" style="font-size: 0.72rem;" id="simNoteText">
                                Pilih aset dan part upgrade untuk melihat simulasi.
                            </div>
                        </div>

                        <button type="submit" class="py-2 mt-4 btn btn-primary w-100 fw-bold rounded-pill shadow-sm">
                            <i class="bi bi-check2-circle me-1"></i> Proses Pengeluaran & Upgrade Aset
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    const WAREHOUSES = @json($warehouses);
    let warehouseItemsCache = {};
    let partIdx = 0;

    function formatRp(num) {
        return 'Rp ' + Math.round(num || 0).toLocaleString('id-ID');
    }

    function escapeAttr(str) {
        return String(str || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    $(document).ready(function () {
        $('.select2-asset').select2({ theme: 'bootstrap-5', width: '100%' });

        $('#selectTargetAsset').on('change', function () {
            let opt = $(this).find('option:selected');
            if (!opt.val()) {
                $('#assetInfoPanel').slideUp();
                recalcSimulation();
                return;
            }

            let nbv = parseFloat(opt.data('nbv')) || 0;
            let remMonths = parseInt(opt.data('rem-months')) || 0;
            let initPrice = parseFloat(opt.data('initial')) || 0;

            $('#infoAssetHolder').text(opt.data('fa') + ' — ' + opt.data('holder') + ' (' + opt.data('company') + ')');
            $('#infoInitialPrice').text(formatRp(initPrice));
            $('#infoCurrentNbv').text(formatRp(nbv));
            $('#infoAcqDate').text(opt.data('acq-date'));
            $('#infoLifeYears').text(opt.data('life-years'));

            if (remMonths <= 0) {
                $('#infoRemainingBadge').removeClass('bg-info text-dark').addClass('bg-danger text-white')
                    .text('Umur Ekonomis Habis (Sisa 0 Bulan)');
                $('#radioCapValue').prop('disabled', true);
                $('#capValueExpiredWarn').show();
                if ($('#radioCapValue').is(':checked')) {
                    $('#radioCapExtend').prop('checked', true).trigger('change');
                }
            } else {
                $('#infoRemainingBadge').removeClass('bg-danger text-white').addClass('bg-info text-dark')
                    .text('Sisa Umur: ' + remMonths + ' Bulan');
                $('#radioCapValue').prop('disabled', false);
                $('#capValueExpiredWarn').hide();
            }

            $('#assetInfoPanel').slideDown();
            recalcSimulation();
        });

        $('.treatment-radio').on('change', function () {
            $('.treatment-card').removeClass('active');
            $(this).closest('.treatment-card').addClass('active');

            if ($(this).val() === 'CAPITALIZE_EXTEND') {
                $('#extendMonthsWrapper').slideDown();
                $('#newRemainingMonthsInput').prop('required', true);
            } else {
                $('#extendMonthsWrapper').slideUp();
                $('#newRemainingMonthsInput').prop('required', false);
            }
            recalcSimulation();
        });

        $('#newRemainingMonthsInput').on('input', recalcSimulation);

        $('#btnAddWarehousePart').on('click', function () {
            addPartRow('WAREHOUSE');
        });

        $('#btnAddServicePart').on('click', function () {
            addPartRow('DIRECT_SERVICE');
        });

        if ($('#selectTargetAsset').val()) {
            $('#selectTargetAsset').trigger('change');
        }
    });

    function addPartRow(sourceType) {
        $('#emptyPartsNotice').hide();
        let idx = partIdx++;

        let whOptions = '<option value="">-- Pilih Gudang --</option>';
        WAREHOUSES.forEach(w => {
            whOptions += `<option value="${w.id}">${w.name}</option>`;
        });

        let html = '';
        if (sourceType === 'WAREHOUSE') {
            html = `
                <div class="p-3 bg-white border rounded-3 shadow-sm part-row" data-idx="${idx}">
                    <input type="hidden" name="parts[${idx}][source_type]" value="WAREHOUSE">
                    <div class="mb-2 d-flex justify-content-between align-items-center border-bottom pb-2">
                        <span class="badge bg-primary-subtle text-primary fw-bold"><i class="bi bi-box-seam me-1"></i>Pengeluaran Part dari Stok Gudang</span>
                        <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 btn-remove-part"><i class="bi bi-trash"></i></button>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">1. Gudang Asal <span class="text-danger">*</span></label>
                            <select name="parts[${idx}][warehouse_id]" class="form-select form-select-sm wh-select" required>
                                ${whOptions}
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small fw-bold">2. Pilih Barang / Sparepart Tersedia (Master & Varian PO) <span class="text-danger">*</span></label>
                            <select name="parts[${idx}][item_id]" class="form-select form-select-sm item-wh-select" required>
                                <option value="">-- Pilih Gudang Dulu --</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-bold">3. Referensi PO & GR Pembelian Part</label>
                            <select class="form-select form-select-sm po-ref-select">
                                <option value="">-- Otomatis dari PO Terakhir --</option>
                            </select>
                            <input type="hidden" name="parts[${idx}][purchase_order_id]" class="po-id-hidden">
                            <input type="hidden" name="parts[${idx}][goods_receipt_id]" class="gr-id-hidden">
                        </div>
                        <div class="col-md-7">
                            <label class="form-label small fw-bold">Nama Komponen / Varian Tercatat di Aset <span class="text-danger">*</span></label>
                            <input type="text" name="parts[${idx}][component_name]" class="form-control form-control-sm comp-name-input" placeholder="Cth: Hard Disk NvME Internal 512 GB" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Qty Keluar <span class="text-danger">*</span></label>
                            <input type="number" name="parts[${idx}][qty]" class="form-control form-control-sm text-center part-qty" value="1" min="0.01" step="0.01" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Harga Satuan (dari PO) <span class="text-danger">*</span></label>
                            <input type="number" name="parts[${idx}][unit_cost]" class="form-control form-control-sm text-end part-unit-cost" value="0" min="0" step="any" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-bold">Subtotal Biaya Part</label>
                            <input type="text" class="form-control form-control-sm text-end fw-bold bg-light text-primary part-subtotal" value="Rp 0" readonly>
                        </div>
                    </div>
                </div>
            `;
        } else {
            html = `
                <div class="p-3 bg-white border rounded-3 shadow-sm part-row" data-idx="${idx}">
                    <input type="hidden" name="parts[${idx}][source_type]" value="DIRECT_SERVICE">
                    <div class="mb-2 d-flex justify-content-between align-items-center border-bottom pb-2">
                        <span class="badge bg-secondary-subtle text-secondary fw-bold"><i class="bi bi-wrench me-1"></i>Jasa / Part Non-Stok Gudang</span>
                        <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 btn-remove-part"><i class="bi bi-trash"></i></button>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-7">
                            <label class="form-label small fw-bold">Nama Komponen / Jasa Upgrade <span class="text-danger">*</span></label>
                            <input type="text" name="parts[${idx}][component_name]" class="form-control form-control-sm" placeholder="Cth: Jasa Pasang & Thermal Paste / SSD Vendor" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-bold">Vendor / Pelaksana</label>
                            <input type="text" name="parts[${idx}][vendor_name]" class="form-control form-control-sm" placeholder="Nama Toko / Teknisi">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Qty <span class="text-danger">*</span></label>
                            <input type="number" name="parts[${idx}][qty]" class="form-control form-control-sm text-center part-qty" value="1" min="0.01" step="0.01" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Biaya Satuan <span class="text-danger">*</span></label>
                            <input type="number" name="parts[${idx}][unit_cost]" class="form-control form-control-sm text-end part-unit-cost" value="0" min="0" step="any" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-bold">Subtotal Biaya</label>
                            <input type="text" class="form-control form-control-sm text-end fw-bold bg-light text-primary part-subtotal" value="Rp 0" readonly>
                        </div>
                    </div>
                </div>
            `;
        }

        $('#partsContainer').append(html);
        recalcSimulation();
    }

    $(document).on('click', '.btn-remove-part', function () {
        $(this).closest('.part-row').remove();
        if ($('.part-row').length === 0) {
            $('#emptyPartsNotice').show();
        }
        recalcSimulation();
    });

    // Tarik item stok gudang + nama alternatif/varian PO saat gudang dipilih
    $(document).on('change', '.wh-select', function () {
        let whId = $(this).val();
        let row = $(this).closest('.part-row');
        let itemSelect = row.find('.item-wh-select');

        itemSelect.html('<option value="">Memuat stok gudang...</option>');
        if (!whId) {
            itemSelect.html('<option value="">-- Pilih Gudang Dulu --</option>');
            return;
        }

        const populateItems = (items) => {
            let opts = '<option value="">-- Pilih Sparepart (Stok Tersedia) --</option>';
            items.forEach(it => {
                let variantLabel = it.variant_summary ? ` — [${it.variant_summary}]` : '';
                let defaultComp  = it.default_comp_name || it.name;
                opts += `<option value="${it.item_id}"
                    data-price="${it.default_price}"
                    data-max="${it.available_qty}"
                    data-name="${escapeAttr(defaultComp)}"
                    data-porefs='${escapeAttr(JSON.stringify(it.po_refs))}'>
                    [${it.code}] ${it.name}${variantLabel} (Stok: ${it.available_qty} ${it.uom})
                </option>`;
            });
            itemSelect.html(opts);
        };

        if (warehouseItemsCache[whId]) {
            populateItems(warehouseItemsCache[whId]);
        } else {
            $.get("{{ url('/fixed-assets/upgrades/warehouse-items') }}/" + whId, function (data) {
                warehouseItemsCache[whId] = data;
                populateItems(data);
            });
        }
    });

    // Isi otomatis nama komponen spesifik/varian (dari PO), harga dari PO, dan pilihan referensi PO/GR saat part dipilih
    $(document).on('change', '.item-wh-select', function () {
        let opt = $(this).find('option:selected');
        let row = $(this).closest('.part-row');
        let poSelect = row.find('.po-ref-select');

        if (!opt.val()) return;

        let maxQty   = parseFloat(opt.data('max')) || 1;
        let defPrice = parseFloat(opt.data('price')) || 0;
        let compName = opt.data('name') || '';
        let poRefs   = opt.data('porefs') || [];

        row.find('.comp-name-input').val(compName);
        row.find('.part-qty').attr('max', maxQty).val(1);
        row.find('.part-unit-cost').val(defPrice);

        let poOpts = '';
        if (poRefs.length > 0) {
            poRefs.forEach((p, i) => {
                let specInfo = p.specific_name ? ` — ${p.specific_name}` : '';
                poOpts += `<option value="${i}"
                    data-poid="${p.po_id || ''}"
                    data-grid="${p.gr_id || ''}"
                    data-price="${p.price}"
                    data-specname="${escapeAttr(p.specific_name || compName)}">
                    ${p.po_number} | GR: ${p.gr_number}${specInfo} (@ Rp ${Math.round(p.price).toLocaleString('id-ID')})
                </option>`;
            });
            row.find('.po-id-hidden').val(poRefs[0].po_id || '');
            row.find('.gr-id-hidden').val(poRefs[0].gr_id || '');
            if (poRefs[0].specific_name) {
                row.find('.comp-name-input').val(poRefs[0].specific_name);
            }
        } else {
            poOpts = '<option value="">-- Tidak ada referensi PO --</option>';
            row.find('.po-id-hidden').val('');
            row.find('.gr-id-hidden').val('');
        }
        poSelect.html(poOpts);

        recalcRow(row);
    });

    $(document).on('change', '.po-ref-select', function () {
        let opt = $(this).find('option:selected');
        let row = $(this).closest('.part-row');
        row.find('.po-id-hidden').val(opt.data('poid') || '');
        row.find('.gr-id-hidden').val(opt.data('grid') || '');
        if (opt.data('price') !== undefined) {
            row.find('.part-unit-cost').val(opt.data('price'));
        }
        if (opt.data('specname')) {
            row.find('.comp-name-input').val(opt.data('specname'));
        }
        recalcRow(row);
    });

    $(document).on('input', '.part-qty, .part-unit-cost', function () {
        recalcRow($(this).closest('.part-row'));
    });

    function recalcRow(row) {
        let qty = parseFloat(row.find('.part-qty').val()) || 0;
        let cost = parseFloat(row.find('.part-unit-cost').val()) || 0;
        let sub = qty * cost;
        row.find('.part-subtotal').val(formatRp(sub));
        recalcSimulation();
    }

    function recalcSimulation() {
        let assetOpt = $('#selectTargetAsset').find('option:selected');
        let nbvBefore = assetOpt.val() ? (parseFloat(assetOpt.data('nbv')) || 0) : 0;
        let remBefore = assetOpt.val() ? (parseInt(assetOpt.data('rem-months')) || 0) : 0;

        let totalUpgrade = 0;
        $('.part-row').each(function () {
            let q = parseFloat($(this).find('.part-qty').val()) || 0;
            let c = parseFloat($(this).find('.part-unit-cost').val()) || 0;
            totalUpgrade += (q * c);
        });

        let treatment = $('.treatment-radio:checked').val();
        let nbvAfter = nbvBefore;
        let remAfter = remBefore;
        let note = '';

        if (treatment === 'EXPENSE') {
            nbvAfter = nbvBefore;
            remAfter = remBefore;
            note = 'Biaya dicatat sebagai Beban Pemeliharaan. Nilai buku dan umur penyusutan aset tidak berubah.';
        } else if (treatment === 'CAPITALIZE_VALUE') {
            nbvAfter = nbvBefore + totalUpgrade;
            remAfter = remBefore;
            note = 'Nilai buku bertambah sebesar biaya upgrade, dan disusutkan pada sisa umur ekonomis yang berjalan.';
        } else if (treatment === 'CAPITALIZE_EXTEND') {
            nbvAfter = nbvBefore + totalUpgrade;
            remAfter = parseInt($('#newRemainingMonthsInput').val()) || 0;
            note = 'Nilai buku bertambah sebesar biaya upgrade, dan disusutkan prospektif selama ' + remAfter + ' bulan ke depan.';
        }

        let monthlyDep = (remAfter > 0 && nbvAfter > 0) ? (nbvAfter / remAfter) : 0;

        $('#simNbvBefore').text(formatRp(nbvBefore));
        $('#simTotalUpgrade').text(formatRp(totalUpgrade));
        $('#simNbvAfter').text(formatRp(nbvAfter));
        $('#simRemMonths').text(remAfter + ' Bulan');
        $('#simMonthlyDep').text(formatRp(monthlyDep) + ' / bulan');
        $('#simNoteText').text(note);
    }
</script>
@endpush