@extends('layouts.app')

@push('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<style>
    /* Styling Card & Input */
    .item-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 24px;
        margin-bottom: 24px;
        position: relative;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        border-left: 5px solid #0d6efd; /* Biru untuk Create */
    }
    .btn-delete-item {
        position: absolute; top: 15px; right: 15px;
        color: #ef4444; background: #fef2f2; border: none;
        width: 32px; height: 32px; border-radius: 8px;
        display: flex; align-items: center; justify-content: center;
        transition: 0.2s; z-index: 10;
    }
    .btn-delete-item:hover { background: #fee2e2; transform: scale(1.1); }
    .vendor-section {
        background-color: #f8fafc; border-radius: 12px;
        padding: 15px; margin-top: 15px; border: 1px dashed #cbd5e1;
    }
    .form-control, .form-select {
        border-radius: 8px; border-color: #e2e8f0; padding: 0.6rem 0.8rem;
    }
    .form-control:focus, .form-select:focus {
        border-color: #0d6efd; box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1);
    }
    .custom-file-label { cursor: pointer; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }

    /* Penyesuaian Select2 agar serasi dengan Bootstrap */
    .select2-container--bootstrap-5 .select2-selection {
        border-radius: 8px;
        border-color: #e2e8f0;
        min-height: calc(1.5em + 1.2rem + 2px);
        padding: 0.3rem 0.75rem;
    }
</style>
@endpush

@section('content')

<form action="{{ route('pr.store') }}" method="POST" enctype="multipart/form-data" id="prForm">
    @csrf

    <div class="mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 fw-bold text-dark">Buat PR Baru</h4>
            <p class="mb-0 text-muted small">Isi form di bawah untuk membuat permintaan pembelian.</p>
        </div>
        <div class="gap-2 d-none d-md-flex">
            <a href="{{ route('pr.index') }}" class="px-4 border btn btn-light rounded-pill fw-bold text-secondary">
                Batal
            </a>
            <button type="submit" class="px-5 shadow-sm btn btn-primary rounded-pill fw-bold">
                <i class="bi bi-send me-1"></i> Submit PR
            </button>
        </div>
    </div>

    <div class="mb-4 border-0 shadow-sm card rounded-4">
        <div class="p-4 card-body">
            <h6 class="mb-3 fw-bold text-dark"><i class="bi bi-info-circle me-2 text-primary"></i>Informasi Umum</h6>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted">Tanggal Request</label>
                    <input type="date" name="request_date" class="form-control" value="{{ isset($pr) ? $pr->request_date : date('Y-m-d') }}" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted">Tanggal Dibutuhkan</label>
                    <input type="date" name="need_date" class="form-control" value="{{ isset($pr) ? $pr->need_date : '' }}" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted">Departemen / PT</label>
                    <select name="company_id" class="form-select" required>
                        <option value="">-- Pilih Departemen --</option>
                        @foreach($companies as $c)
                            <option value="{{ $c->id }}" {{ (isset($pr) && $pr->company_id == $c->id) || (!isset($pr) && auth()->user()->company_id == $c->id) ? 'selected' : '' }}>
                                {{ $c->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted">Keterangan</label>
                    <input type="text" name="description" class="form-control" placeholder="Contoh: Pengadaan Laptop Q1..." value="{{ isset($pr) ? $pr->description : '' }}" required>
                </div>
            </div>
        </div>
    </div>

    <div id="items-container">
        <div class="item-card item-row" data-index="0">
            <button type="button" class="btn-delete-item" onclick="removeItem(this)" title="Hapus Barang"><i class="bi bi-trash"></i></button>

            <h6 class="mb-3 fw-bold text-primary d-flex align-items-center">
                <span class="badge bg-primary rounded-circle me-2">1</span> Detail Barang
            </h6>

            <div class="row g-3">
                {{-- Kolom 1: Pilih Barang (6) --}}
                <div class="col-md-6">
                    <label class="small text-muted fw-bold">Pilih Barang dari Katalog <span class="text-danger">*</span></label>
                    <select name="items[0][item_id]" class="form-select select2-item" required>
                        <option value="">-- Cari Nama / Kode Barang --</option>
                        @foreach($items as $item)
                            {{-- 🔥 Kita simpan daftar semua satuan (Base + Alt) di dalam data-uoms 🔥 --}}
                            @php
                                $allUoms = collect([['id' => $item->uom_id, 'name' => optional($item->uom)->name]]);
                                if($item->itemUoms) {
                                    foreach($item->itemUoms as $alt) {
                                        $allUoms->push(['id' => null, 'name' => $alt->uom_name]); // Alt UOM biasanya teks atau ID kemasan
                                    }
                                }
                            @endphp
                            <option value="{{ $item->id }}" data-uoms="{{ json_encode($allUoms) }}">
                                {{ $item->code }} - {{ $item->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Kolom 2: Kuantitas (3) --}}
                <div class="col-md-3">
                    <label class="small text-muted fw-bold">Kuantitas (Qty) <span class="text-danger">*</span></label>
                    <input type="number" name="items[0][qty]" class="form-control" placeholder="0" min="0.01" step="0.01" required>
                </div>

                {{-- Kolom 3: Satuan / UOM (3) --}}
                <div class="col-md-3">
                    <label class="small text-muted fw-bold">Satuan <span class="text-danger">*</span></label>
                    <select name="items[0][uom_id]" class="form-select select-uom" required>
                        <option value="">-- Pilih --</option>
                    </div>
                </div>
            </div>

            <div class="vendor-section">
                <div class="mb-2 d-flex justify-content-between align-items-center">
                    <label class="small fw-bold text-dark"><i class="bi bi-shop me-1"></i> Referensi Vendor</label>
                    <span class="border badge bg-light text-muted">Opsional</span>
                </div>

                <div class="vendor-container" id="vendor-container-0">
                    <div class="p-3 mb-3 bg-white border shadow-sm vendor-row rounded-3 position-relative">
                        <div class="top-0 p-2 position-absolute end-0">
                            <button type="button" class="btn btn-sm text-danger" onclick="removeVendor(this)"><i class="bi bi-x-lg"></i></button>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="mb-1 small text-muted">Nama Vendor</label>
                                {{-- Tambahkan class select2-vendor --}}
                                <select name="items[0][vendors][0][vendor_id]" class="form-select form-select-sm select2-vendor">
                                    <option value="">- Pilih Vendor -</option>
                                    @foreach($vendors as $v) <option value="{{ $v->id }}">{{ $v->name }}</option> @endforeach
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="mb-1 small text-muted">Harga & Mata Uang</label>
                                <div class="input-group input-group-sm">
                                    <select name="items[0][vendors][0][currency]" class="text-center form-select bg-light fw-bold" style="max-width: 80px;">
                                        @foreach($currencies as $curr)
                                            <option value="{{ $curr->code }}">{{ $curr->code }}</option>
                                        @endforeach
                                    </select>
                                    <input type="number" name="items[0][vendors][0][price]" class="form-control" placeholder="0">
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="mb-1 small text-muted">Lampiran</label>
                                <div class="input-group input-group-sm">
                                    <input type="file" name="items[0][vendors][0][file]" class="form-control d-none" id="file-0-0" onchange="updateFileName(this)">
                                    <label for="file-0-0" class="btn btn-outline-secondary w-100 text-start custom-file-label d-flex align-items-center">
                                        <i class="bi bi-paperclip me-2"></i> <span class="file-text">Upload PDF/Img</span>
                                    </label>
                                </div>
                            </div>
                            <div class="mt-2 col-md-6">
                                <input type="url" name="items[0][vendors][0][link]" class="form-control form-control-sm" placeholder="Link URL...">
                            </div>
                            <div class="mt-2 col-md-6">
                                <input type="text" name="items[0][vendors][0][notes]" class="form-control form-control-sm" placeholder="Catatan...">
                            </div>
                        </div>
                    </div>
                </div>

                <button type="button" class="mt-2 border btn btn-sm btn-light text-primary fw-bold" onclick="addVendor(0)">
                    <i class="bi bi-plus-lg"></i> Tambah Vendor Lain
                </button>
            </div>
        </div>
    </div>

    <div class="pb-5 mb-5 text-center">
        <button type="button" class="px-4 py-2 border shadow-sm btn btn-white border-primary text-primary fw-bold rounded-pill" id="btn-add-item">
            <i class="bi bi-plus-circle-fill me-1"></i> Tambah Barang Lain
        </button>
    </div>

    <div class="px-3 py-3 bg-white shadow-lg fixed-bottom border-top d-md-none" style="z-index: 1050;">
        <div class="gap-2 d-flex">
            <a href="{{ route('pr.index') }}" class="border btn btn-light fw-bold w-100">Batal</a>
            <button type="submit" class="shadow-sm btn btn-primary fw-bold w-100">Submit</button>
        </div>
    </div>
    <div style="height: 80px;"></div>

</form>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    let itemIdx = 0;

    // --- INISIALISASI SELECT2 ---
    $(document).ready(function() {
        initSelect2();
    });

    function initSelect2() {
        // 🔥 1. UPGRADE PENCARIAN BARANG (WAJIB KETIK 2 HURUF) 🔥
        $('.select2-item').select2({
            theme: 'bootstrap-5',
            placeholder: "-- Ketik Nama / Kode Barang --",
            allowClear: true,
            width: '100%',
            minimumInputLength: 2, // Wajib ketik 2 huruf dulu
            language: {
                inputTooShort: function () {
                    return "Please enter 2 or more characters";
                },
                noResults: function () {
                    return "Barang tidak ditemukan di Katalog.";
                }
            }
        });

        // 2. Inisialisasi Vendor (Normal)
        $('.select2-vendor').select2({
            theme: 'bootstrap-5',
            placeholder: "- Cari / Pilih Vendor -",
            allowClear: true,
            width: '100%'
        });
    }

    // --- UPDATE NAMA FILE ---
    window.updateFileName = function(input) {
        let label = input.nextElementSibling;
        let textSpan = label.querySelector('.file-text');
        if (input.files && input.files[0]) {
            textSpan.textContent = input.files[0].name;
            label.classList.remove('btn-outline-secondary');
            label.classList.add('btn-success', 'text-white', 'border-success');
        } else {
            textSpan.textContent = "Upload PDF/Img";
            label.classList.remove('btn-success', 'text-white', 'border-success');
            label.classList.add('btn-outline-secondary');
        }
    }

    // --- TAMBAH ITEM BARU ---
    document.getElementById('btn-add-item').addEventListener('click', function() {
        // PENTING: Hancurkan dulu Select2 sebelum di-clone agar script tidak error/ganda
        $('.select2-item, .select2-vendor').select2('destroy');

        itemIdx++;
        let container = document.getElementById('items-container');
        let template = container.querySelector('.item-row').cloneNode(true);

        template.setAttribute('data-index', itemIdx);
        template.querySelector('.badge').textContent = itemIdx + 1;

        // Reset Input Barang
        let selectItem = template.querySelector('select[name^="items"]');
        selectItem.name = `items[${itemIdx}][item_id]`;
        selectItem.value = "";

        let qtyInput = template.querySelector('input[name^="items"]');
        if(qtyInput) {
            qtyInput.name = `items[${itemIdx}][qty]`;
            qtyInput.value = "";
        }

        // Reset Vendor Container (Sisakan 1 row bersih)
        let vendorContainer = template.querySelector('.vendor-container');
        if(vendorContainer) {
            vendorContainer.id = `vendor-container-${itemIdx}`;
            let firstVendorRow = vendorContainer.querySelector('.vendor-row').cloneNode(true);
            vendorContainer.innerHTML = '';
            vendorContainer.appendChild(firstVendorRow);

            resetVendorRowValues(firstVendorRow);
            updateVendorAttributes(firstVendorRow, itemIdx, 0);
        }

        // Update Tombol Add Vendor
        let btnAddVendor = template.querySelector('button[onclick^="addVendor"]');
        if(btnAddVendor) {
            btnAddVendor.setAttribute('onclick', `addVendor(${itemIdx})`);
        }

        container.appendChild(template);

        // PENTING: Hidupkan kembali Select2 di semua elemen
        initSelect2();
    });

    // --- TAMBAH VENDOR ---
    window.addVendor = function(parentIndex) {
        $('.select2-vendor').select2('destroy');

        let container = document.getElementById(`vendor-container-${parentIndex}`);
        let newRow = container.querySelector('.vendor-row').cloneNode(true);
        let newVendorIdx = container.children.length;

        resetVendorRowValues(newRow);
        updateVendorAttributes(newRow, parentIndex, newVendorIdx);

        container.appendChild(newRow);

        initSelect2();
    }

    // --- RESET VALUES ---
    function resetVendorRowValues(row) {
        row.querySelectorAll('input:not([type=checkbox]):not([type=radio])').forEach(i => i.value = '');

        row.querySelectorAll('select').forEach(s => {
            if (s.name && s.name.includes('[currency]')) {
                s.selectedIndex = 0; // Kembalikan mata uang ke default (CTH: IDR)
            } else {
                s.value = '';
            }
        });

        let fileLabel = row.querySelector('.custom-file-label');
        if(fileLabel) {
            fileLabel.classList.remove('btn-success', 'text-white', 'border-success');
            fileLabel.classList.add('btn-outline-secondary');
            fileLabel.querySelector('.file-text').textContent = "Upload PDF/Img";
        }
    }

    // --- UPDATE ID & NAME ---
    function updateVendorAttributes(row, iIdx, vIdx) {
        let vendorSelect = row.querySelector('select[name*="[vendor_id]"]');
        if(vendorSelect) vendorSelect.name = `items[${iIdx}][vendors][${vIdx}][vendor_id]`;

        let currencySelect = row.querySelector('select[name*="[currency]"]');
        if(currencySelect) currencySelect.name = `items[${iIdx}][vendors][${vIdx}][currency]`;

        let priceInput = row.querySelector('input[name*="[price]"]');
        if(priceInput) priceInput.name = `items[${iIdx}][vendors][${vIdx}][price]`;

        let linkInput = row.querySelector('input[type="url"]');
        if(linkInput) linkInput.name = `items[${iIdx}][vendors][${vIdx}][link]`;

        let notesInput = row.querySelector('input[placeholder*="Catatan"]');
        if(notesInput) notesInput.name = `items[${iIdx}][vendors][${vIdx}][notes]`;

        let fileInput = row.querySelector('input[type="file"]');
        let fileLabel = row.querySelector('.custom-file-label');
        if (fileInput && fileLabel) {
            let uniqueID = `file-${iIdx}-${vIdx}`;
            fileInput.name = `items[${iIdx}][vendors][${vIdx}][file]`;
            fileInput.id = uniqueID;
            fileLabel.setAttribute('for', uniqueID);
        }
    }

    // --- REMOVE FUNGSI ---
    window.removeItem = function(btn) {
        if(document.querySelectorAll('.item-row').length > 1 && confirm('Hapus barang ini dari daftar PR?')) {
            $(btn).closest('.item-row').find('.select2-item, .select2-vendor').select2('destroy');
            btn.closest('.item-row').remove();

            // Re-index badge (opsional tapi bagus untuk UX)
            document.querySelectorAll('.item-row').forEach((row, index) => {
                let badge = row.querySelector('.badge');
                if(badge) badge.textContent = index + 1;
            });
        } else if(document.querySelectorAll('.item-row').length <= 1) {
            alert("Minimal satu barang harus ada dalam PR.");
        }
    }

    window.removeVendor = function(btn) {
        let container = btn.closest('.vendor-container');
        if(container.querySelectorAll('.vendor-row').length > 1) {
            $(btn).closest('.vendor-row').find('.select2-vendor').select2('destroy');
            btn.closest('.vendor-row').remove();
        } else {
            resetVendorRowValues(btn.closest('.vendor-row'));
        }
    }


    // --- LOGIKA AUTO-POPULATE SATUAN ---
    $(document).on('change', '.select2-item', function() {
        let $row = $(this).closest('.item-row');
        let $uomSelect = $row.find('.select-uom');

        // Ambil data UOM dari atribut data-uoms yang kita buat di HTML tadi
        let uomsData = $(this).find(':selected').data('uoms');

        // Kosongkan dropdown satuan
        $uomSelect.empty().append('<option value="">-- Pilih --</option>');

        if (uomsData) {
            uomsData.forEach(function(uom) {
                // Gunakan uom.name sebagai value jika uom_id di detail PR menggunakan string,
                // atau uom.id jika menggunakan foreign key
                $uomSelect.append(`<option value="${uom.name}">${uom.name}</option>`);
            });

            // Pilih satuan pertama (biasanya Satuan Dasar) secara otomatis
            if(uomsData.length > 0) {
                $uomSelect.val(uomsData[0].name).trigger('change');
            }
        }
    });
</script>
@endpush
