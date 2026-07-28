@extends('layouts.app')

@push('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<style>
    .asset-card { transition: all 0.3s ease; border-left: 5px solid #0dcaf0; }
    .asset-card:hover { transform: translateY(-3px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; }
    .quill-editor { background: #fff; min-height: 100px; max-height: 200px; overflow-y: auto; border-radius: 0 0 8px 8px; }
    .ql-toolbar.ql-snow { border-radius: 8px 8px 0 0; background: #f8f9fa; }
    /* Memperjelas opsi yang di-disable agar user tahu SN sudah dipakai */
    select option:disabled { background-color: #e9ecef; color: #6c757d; font-style: italic; }
</style>
@endpush

@section('content')
<div class="px-0 container-fluid text-dark">
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 fw-bold text-dark">
                <i class="bi bi-magic text-info me-2"></i> Pengakuan Aset (Capitalization)
            </h4>
            <div class="mt-1 text-muted small">Ubah stok fisik menjadi Aset Tetap dengan spesifikasi unik per unit.</div>
        </div>
    </div>

    @if(session('success'))
        <div class="shadow-sm alert alert-success fw-bold rounded-3"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="shadow-sm alert alert-danger fw-bold rounded-3"><i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}</div>
    @endif

    <form action="{{ route('asset-capitalizations.store') }}" method="POST" id="form-capitalize">
        @csrf
        <input type="hidden" name="warehouse_id" id="warehouse_id">

        {{-- 1. PILIH DOKUMEN GR --}}
        <div class="mb-4 border-0 border-4 shadow-sm card border-start border-info rounded-4">
            <div class="p-4 card-body">
                <label class="form-label fw-bold text-muted small">Pilih Dokumen Penerimaan (GR) <span class="text-danger">*</span></label>
                <select name="goods_receipt_id" id="gr_select" class="form-select select2-gr" required>
                    <option value="">-- Ketik Nomor GR, Vendor, Kode atau Nama Barang... --</option>
                    @foreach($grs as $gr)
                        @php
                            $vendorName = optional(optional($gr->po)->vendor)->name ?? 'Vendor Internal';
                            $itemDetails = $gr->items->map(function($i) {
                                return optional($i->item)->code . ' ' . optional($i->item)->name;
                            })->filter()->implode(', ');
                        @endphp
                        <option value="{{ $gr->id }}">
                            {{ $gr->gr_number }} | Tgl: {{ date('d-m-Y', strtotime($gr->received_date)) }} | Vendor: {{ $vendorName }} | Item: {{ \Illuminate\Support\Str::limit($itemDetails, 80) }}
                        </option>
                    @endforeach
                </select>
                <div class="mt-2 text-muted small" id="wh-info"></div>
            </div>
        </div>

        {{-- 2. WADAH ITEM DAN FORM DINAMIS --}}
        <div id="items-container" class="d-none">
            <div class="border-0 shadow-sm card rounded-4">
                <div class="px-4 pt-4 pb-3 bg-white card-header border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-list-check me-2 text-primary"></i> Rincian Barang & Registrasi Spesifikasi Aset</h6>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" data-bs-toggle="modal" data-bs-target="#modalAturan">
                        <i class="bi bi-info-circle me-1"></i> Dasar Hukum & Akuntansi
                    </button>
                </div>
                <div class="p-4 card-body" id="item-list-body" style="background-color: #f8f9fa;">
                </div>
                <div class="p-4 bg-white card-footer text-end border-top rounded-bottom-4">
                    <button type="submit" class="px-5 text-white shadow-sm btn btn-info fw-bold rounded-pill" id="btn-submit">
                        <i class="bi bi-save me-2"></i> Simpan Pengakuan Aset
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

{{-- MODAL EDUKASI DASAR HUKUM --}}
<div class="modal fade" id="modalAturan" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="border-0 modal-content rounded-4">
            <div class="text-white modal-header bg-info rounded-top-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-book me-2"></i> Standar Pengakuan & Dasar Hukum Aset</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="p-4 modal-body text-secondary small">
                <h6 class="mb-2 fw-bold text-dark"><i class="bi bi-calculator me-1 text-info"></i> 1. Harga Perolehan (PSAK 16 & UU PPh)</h6>
                <p>Mengacu pada <strong>PSAK 16</strong> (Standar Akuntansi Keuangan) dan ditegaskan dalam <strong>Pasal 10 Ayat (1) UU Pajak Penghasilan (UU HPP No. 7 Tahun 2021)</strong>, Harga Perolehan adalah seluruh pengeluaran bersih hingga aset siap digunakan:</p>
                <div class="p-2 mb-3 border rounded bg-light text-dark fw-bold font-monospace">
                    Harga Perolehan = (Harga Beli / DPP) - Diskon + Biaya Atribusional (Ongkir/Instalasi)
                </div>

                <h6 class="mb-2 fw-bold text-dark"><i class="bi bi-diagram-3 me-1 text-info"></i> 2. Masa Manfaat Aset (PMK No. 72 Tahun 2023)</h6>
                <p class="mb-2">Ketentuan Kelompok Aset Fiskal berdasarkan Aturan Pajak Kementerian Keuangan RI:</p>
                <div class="table-responsive">
                    <table class="table text-center align-middle table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Kelompok</th>
                                <th>Masa Manfaat</th>
                                <th>Tarif Penyusutan</th>
                                <th>Contoh Barang</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            <tr>
                                <td class="fw-bold">Kelompok 1</td>
                                <td>4 Tahun</td>
                                <td>25% / Thn</td>
                                <td class="text-start">Sepeda Motor, Komputer, HP, Printer, Mebel Kayu, Perkakas.</td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Kelompok 2</td>
                                <td>8 Tahun</td>
                                <td>12.5% / Thn</td>
                                <td class="text-start">Mobil Penumpang, Bus, Truk, AC, Mebel Logam.</td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Kelompok 3</td>
                                <td>16 Tahun</td>
                                <td>6.25% / Thn</td>
                                <td class="text-start">Mesin-mesin Pabrik, Alat Berat, Kapal.</td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Kelompok 4</td>
                                <td>20 Tahun</td>
                                <td>5% / Thn</td>
                                <td class="text-start">Bangunan Gedung Permanen.</td>
                            </tr>
                        </tbody>
                    </table>
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
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>

<script>
    let categories = @json($assetCategories ?? []);
    let categoryOptions = '<option value="">-- Pilih Kategori (PMK 72/2023) --</option>';
    categories.forEach(function(cat) {
        categoryOptions += `<option value="${cat.id}">${cat.name} (${cat.useful_life_years} Thn)</option>`;
    });

    // =========================================================================
    // 🔥 FUNGSI CERDAS: MENGUNCI OPSI SN YANG SUDAH DIPILIH DI DROPDOWN LAIN
    // =========================================================================
    function refreshSnDropdowns() {
        let selectedSns = [];

        // 1. Kumpulkan semua SN yang saat ini sedang dipilih oleh user
        $('.sn-input-select').each(function() {
            let val = $(this).val();
            if (val && val !== '') {
                selectedSns.push(val);
            }
        });

        // 2. Kunci (Disable) opsi di dropdown lain jika SN tersebut sudah dipakai
        $('.sn-input-select').each(function() {
            let currentSelectValue = $(this).val();

            $(this).find('option').each(function() {
                let optionValue = $(this).val();
                if (!optionValue) return; // Abaikan opsi kosong "-- Pilih --"

                // Jika opsi ini ada di daftar terpakai DAN bukan milik dropdown ini sendiri
                if (selectedSns.includes(optionValue) && optionValue !== currentSelectValue) {
                    $(this).prop('disabled', true);
                    $(this).text(optionValue + " (Terpakai)");
                } else {
                    $(this).prop('disabled', false);
                    $(this).text(optionValue); // Kembalikan teks normal
                }
            });
        });
    }

    // Panggil fungsi saat dropdown SN diubah
    $(document).on('change', '.sn-input-select', function() {
        refreshSnDropdowns();
    });
    // =========================================================================

    $('#form-capitalize').on('submit', function(e) {
        let accNumbers = [];
        let hasDuplicateAcc = false;

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

        let snNumbers = [];
        let hasDuplicateSn = false;
        $('.sn-input-select').each(function() {
            let val = $(this).val();
            if (val && val.trim() !== '') {
                if (snNumbers.includes(val)) { hasDuplicateSn = true; return false; }
                snNumbers.push(val);
            }
        });

        if (hasDuplicateSn) {
            e.preventDefault();
            Swal.fire({ icon: 'error', title: 'Peringatan Fatal!', text: 'Terdapat Serial Number (SN) yang kembar. Sistem telah mencegah duplikasi!', confirmButtonColor: '#dc3545' });
            return false;
        }

        $('#btn-submit').html('<span class="spinner-border spinner-border-sm me-2"></span> Menyimpan Aset...').prop('disabled', true);
    });

    $(document).ready(function() {
        $('.select2-gr').select2({ theme: 'bootstrap-5' });
        let globalSnData = {};
        let globalSpecData = {};

        $('#gr_select').on('change', function() {
            let grId = $(this).val();
            let container = $('#items-container');
            let tbody = $('#item-list-body');
            let whInfo = $('#wh-info');

            if (!grId) { container.addClass('d-none'); whInfo.text(''); return; }
            whInfo.html('<span class="spinner-border spinner-border-sm text-info"></span> Mencari data barang...');

            let noCacheUrl = `/asset-capitalizations/get-items/${grId}?t=` + new Date().getTime();

            $.ajax({
                url: noCacheUrl,
                type: 'GET',
                cache: false,
                success: function(res) {
                    $('#warehouse_id').val(res.warehouse_id);
                    whInfo.html(`<i class="bi bi-box-seam text-info me-1"></i> Lokasi Stok: <strong class="text-dark">${res.warehouse_name}</strong>`);
                    tbody.empty();
                    globalSnData = {};
                    globalSpecData = {};

                    if (res.items.length === 0) {
                        tbody.append(`<div class="mb-0 alert alert-warning fw-bold"><i class="bi bi-info-circle me-2"></i>Tidak ada stok fisik.</div>`);
                    } else {
                        res.items.forEach(item => {
                            globalSnData[item.item_id] = item.available_sns || [];
                            globalSpecData[item.item_id] = item.default_spec || '';

                            let specificName = item.specific_name || item.item_name;
                            let masterName = item.master_name || item.item_name;
                            let masterHtml = specificName.toLowerCase().trim() !== masterName.toLowerCase().trim() ? `<div class="mb-1 text-muted" style="font-size: 0.75rem;"><i class="bi bi-box me-1"></i>Master: ${masterName}</div>` : '';

                            let html = `
                            <div class="p-4 mb-4 bg-white border shadow-sm rounded-4">
                                <div class="pb-3 mb-3 row align-items-center border-bottom">
                                    <div class="col-md-5">
                                        <div class="fw-bolder fs-5 text-dark text-uppercase">${specificName}</div>
                                        ${masterHtml}
                                        <div class="mt-1 small text-muted">Kode: <span class="badge bg-secondary-subtle text-secondary">${item.item_code}</span> | Sisa Fisik: <strong class="text-danger">${item.max_capitalizable} ${item.base_uom}</strong></div>
                                    </div>
                                    <div class="mt-3 col-md-3 text-md-end mt-md-0"><label class="mb-1 small fw-bold text-muted text-uppercase">Jadikan Aset</label></div>
                                    <div class="col-md-4">
                                        <div class="shadow-sm input-group input-group-lg">
                                            <input type="number" name="items[${item.item_id}][qty]" class="text-center form-control fw-bold border-info text-info qty-input"
                                                data-item="${item.item_id}" data-name="${specificName}" data-price="${item.default_price}" data-date="${item.default_date}"
                                                min="0" max="${item.max_capitalizable}" value="0">
                                            <span class="bg-info-subtle input-group-text fw-bold text-info-emphasis border-info">${item.base_uom}</span>
                                        </div>
                                    </div>
                                </div>
                                <div id="spec-container-${item.item_id}" class="mt-3 row"></div>
                            </div>`;
                            tbody.append(html);
                        });
                    }
                    container.removeClass('d-none');
                }
            });
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
            let container = $(`#spec-container-${itemId}`);
            let availableSns = globalSnData[itemId] || [];
            let defaultSpec = globalSpecData[itemId] || '';

            container.empty();

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
                    <div class="mb-3 col-md-6">
                        <div class="shadow-sm card asset-card h-100 rounded-3">
                            <div class="py-2 bg-white card-header d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-info"><i class="bi bi-pc-display me-1"></i> Unit #${i+1}</span>
                            </div>
                            <div class="p-3 card-body">

                                <div class="px-3 py-2 mb-3 border bg-info-subtle text-info-emphasis border-info-subtle rounded-3 small">
                                    <div class="mb-1 d-flex justify-content-between align-items-center">
                                        <span><i class="bi bi-calculator me-1"></i> <strong>Nilai / Harga Perolehan:</strong></span>
                                        <span class="fw-bold fs-6 text-dark">Rp ${defaultPrice.toLocaleString('id-ID')}</span>
                                    </div>
                                    <div class="mt-1 text-muted" style="font-size: 0.72rem; line-height: 1.3;">
                                        <i class="bi bi-bookmark-check text-info"></i> <strong>Dasar:</strong> PSAK 16 &amp; Pasal 10 UU PPh (UU HPP No. 7/2021). <br>
                                        *Hitungan: (Harga DPP) - Diskon + Biaya Langsung. PPN dikreditkan terpisah.
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
                                <div class="pt-2 mt-1 mb-3 row g-2 border-top">
                                    <div class="col-sm-4">
                                        <label class="mb-1 small text-primary-emphasis fw-bold" style="font-size:0.7rem;"><i class="bi bi-calendar-check me-1"></i>Tgl Perolehan</label>
                                        <input type="date" name="items[${itemId}][details][${i}][acquisition_date]" class="form-control form-control-sm border-primary-subtle" value="${defaultDate}" required>
                                    </div>
                                    <div class="col-sm-4">
                                        <label class="mb-1 small text-primary-emphasis fw-bold" style="font-size:0.7rem;"><i class="bi bi-cash me-1"></i>Harga (Rp)</label>
                                        <input type="number" name="items[${itemId}][details][${i}][accounting_value]" class="form-control form-control-sm border-primary-subtle" value="${defaultPrice}">
                                    </div>
                                    <div class="col-sm-4">
                                        <label class="mb-1 small text-primary-emphasis fw-bold" style="font-size:0.7rem;" data-bs-toggle="tooltip" title="Sesuai PMK No. 72/2023">
                                            <i class="bi bi-tags me-1"></i>Kategori (Pajak) <i class="bi bi-question-circle text-info"></i>
                                        </label>
                                        <select name="items[${itemId}][details][${i}][asset_category_id]" class="form-select form-select-sm border-primary-subtle" required>
                                            ${categoryOptions}
                                        </select>
                                    </div>
                                </div>
                                <input type="hidden" name="items[${itemId}][details][${i}][notes]" id="hidden-notes-${itemId}-${i}">
                                <div>
                                    <label class="mb-1 small text-muted fw-bold">Spesifikasi Detail / Catatan</label>
                                    <div id="editor-${itemId}-${i}" class="quill-editor"></div>
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

            // 🔥 SETELAH SEMUA UNIT DIRENDER, LANGSUNG KUNCI SN YANG KEMBAR 🔥
            refreshSnDropdowns();
        });
    });
</script>
@endpush
