@extends('layouts.app')

@push('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<style>
    /* Gaya untuk pesan error */
    .invalid-feedback { font-size: 0.75rem; font-weight: bold; }
    .is-invalid + .select2-container .select2-selection { border-color: #dc3545 !important; }

    :root { --pr-blue: #0d6efd; --pr-red: #ef4444; --bg-light: #f8fafc; }

    .item-card {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 16px;
        padding: 24px; margin-bottom: 24px; position: relative;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        border-left: 6px solid var(--pr-blue);
        transition: transform 0.2s;
    }

    .btn-delete-item {
        position: absolute; top: 15px; right: 15px;
        color: var(--pr-red); background: #fef2f2; border: none;
        width: 35px; height: 35px; border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        transition: 0.2s; z-index: 10;
    }
    .btn-delete-item:hover { background: #fee2e2; transform: scale(1.1) rotate(90deg); }

    .vendor-section {
        background-color: var(--bg-light); border-radius: 12px;
        padding: 20px; margin-top: 15px; border: 1px dashed #cbd5e1;
    }

    .vendor-row {
        background: #ffffff; border-radius: 10px; border: 1px solid #e2e8f0;
        padding: 15px; margin-bottom: 10px; position: relative;
    }

    .form-control, .form-select { border-radius: 8px; padding: 0.6rem 0.8rem; }

    /* Select2 UX Fix */
    .select2-container--bootstrap-5 .select2-selection {
        border-radius: 8px; border-color: #dee2e6; min-height: 45px;
    }

    .custom-file-label {
        cursor: pointer; overflow: hidden; white-space: nowrap;
        text-overflow: ellipsis; border-style: dashed;
    }

    .x-small { font-size: 0.75rem; }
</style>
@endpush

@section('content')

    {{-- 🔥 BLOK UNTUK MELIHAT ERROR VALIDASI 🔥 --}}
    @if ($errors->any())
        <div class="mb-4 border-0 shadow-sm alert alert-danger rounded-4">
            <div class="fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i> Mohon Maaf, Terjadi Kesalahan:</div>
            <ul class="mt-2 mb-0 small">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

<form action="{{ route('pr.store') }}" method="POST" enctype="multipart/form-data" id="prForm">
    @csrf

    {{-- HEADER --}}
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-file-earmark-plus me-2"></i>Buat Purchase Request</h4>
            <p class="mb-0 text-muted small">Kelola permintaan pengadaan barang/jasa perusahaan.</p>
        </div>
        <div class="gap-2 d-none d-md-flex">
            <a href="{{ route('pr.index') }}" class="px-4 border btn btn-light rounded-pill fw-bold">Batal</a>
            <button type="submit" class="px-5 shadow btn btn-primary rounded-pill fw-bold" id="btn-submit-main">
                <i class="bi bi-send-fill me-2"></i>Submit PR
            </button>
        </div>
    </div>

    {{-- INFO UMUM --}}
    <div class="mb-4 border-0 shadow-sm card rounded-4">
        <div class="p-4 card-body">
            <h6 class="mb-3 fw-bold text-dark"><i class="bi bi-info-circle-fill me-2 text-primary"></i>Informasi Utama PR</h6>
            <div class="row g-3">
                {{-- 1. PEMINTA (USER) --}}
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted">User Peminta <span class="text-danger">*</span></label>
                    <select name="user_id" id="user_id" class="form-select select2-user @error('user_id') is-invalid @enderror" required>
                        <option value="">-- Pilih User --</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" data-company-id="{{ $u->company_id }}" {{ old('user_id', auth()->id()) == $u->id ? 'selected' : '' }}>
                                {{ $u->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('user_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                {{-- 2. UNIT / PT --}}
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted">Unit / Departemen <span class="text-danger">*</span></label>
                    <select name="company_id" id="company_id" class="form-select @error('company_id') is-invalid @enderror" required>
                        <option value="">-- Pilih Unit --</option>
                        @foreach($companies as $c)
                            <option value="{{ $c->id }}" {{ old('company_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                        @endforeach
                    </select>
                    @error('company_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                {{-- 3. TANGGAL REQUEST --}}
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted">Tanggal Request <span class="text-danger">*</span></label>
                    <input type="date" name="request_date" class="form-control @error('request_date') is-invalid @enderror" value="{{ old('request_date', date('Y-m-d')) }}" required>
                    @error('request_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                {{-- 4. TANGGAL DIBUTUHKAN --}}
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted">Dibutuhkan Pada <span class="text-danger">*</span></label>
                    <input type="date" name="need_date" class="form-control @error('need_date') is-invalid @enderror" value="{{ old('need_date') }}" required>
                    @error('need_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                {{-- 5. KETERANGAN --}}
                <div class="col-12">
                    <label class="form-label fw-bold small text-muted">Keterangan Singkat <span class="text-danger">*</span></label>
                    <input type="text" name="description" class="form-control @error('description') is-invalid @enderror" value="{{ old('description') }}" placeholder="Tujuan pengadaan..." required>
                    @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>
    </div>

    {{-- CONTAINER BARANG --}}
    <div id="items-container">
        <div class="item-card item-row" data-index="0">
            <button type="button" class="btn-delete-item" onclick="removeItem(this)"><i class="bi bi-trash"></i></button>

            <h6 class="mb-4 fw-bold text-primary">
                <span class="px-3 py-1 badge bg-primary rounded-pill me-2">1</span> Rincian Barang
            </h6>

            <div class="row g-3">
                {{-- Kolom 1: Item --}}
                <div class="col-md-6">
                    <label class="small text-muted fw-bold">Pilih Barang dari Katalog <span class="text-danger">*</span></label>
                    <select name="items[0][item_id]" class="form-select select2-item @error('items.0.item_id') is-invalid @enderror" required>
                        <option value="">-- Cari Nama / Kode Barang --</option>
                        @foreach($items as $item)
                            @php
                                $uomList = [];

                                // 🔥 PERBAIKAN FATAL: Menambahkan 'id' agar tidak undefined di JS
                                // 1. Tambahkan Satuan Dasar
                                if($item->uom) {
                                    $uomList[] = [
                                        'id' => $item->uom_id,
                                        'name' => $item->uom->name,
                                        'isi' => 1,
                                        'base' => $item->uom->name
                                    ];
                                }

                                // 2. Tambahkan Satuan Alternatif dengan isi konversinya
                                if($item->itemUoms) {
                                    foreach($item->itemUoms as $alt) {
                                        $uomList[] = [
                                            'id' => $alt->id,
                                            'name' => $alt->uom_name,
                                            'isi' => $alt->conversion_qty,
                                            'base' => $item->uom->name
                                        ];
                                    }
                                }
                            @endphp
                            <option value="{{ $item->id }}" data-uoms='{{ json_encode($uomList) }}'>
                                {{ $item->code }} - {{ $item->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Kolom 2: Qty --}}
                <div class="col-md-3">
                    <label class="small text-muted fw-bold">Kuantitas (Qty) <span class="text-danger">*</span></label>
                    <input type="number" name="items[0][qty]" class="form-control" placeholder="0" min="0.01" step="0.01" required>
                </div>

                {{-- Kolom 3: Satuan --}}
                <div class="col-md-3">
                    <label class="small text-muted fw-bold">Satuan <span class="text-danger">*</span></label>
                    <select name="items[0][uom_id]" class="form-select select-uom" required>
                        <option value="">-- Pilih --</option>
                    </select>
                </div>
            </div>

            {{-- SECTION VENDOR --}}
            <div class="vendor-section">
                <div class="mb-3 d-flex justify-content-between align-items-center">
                    <label class="small fw-bold text-dark"><i class="bi bi-tags-fill me-1 text-success"></i>Referensi Vendor & Harga (Opsional)</label>
                    <button type="button" class="btn btn-sm btn-outline-success fw-bold rounded-pill" onclick="addVendor(0)">
                        <i class="bi bi-plus-lg"></i> Tambah Vendor
                    </button>
                </div>

                <div class="vendor-container" id="vendor-container-0">
                    <div class="vendor-row">
                        <button type="button" class="top-0 p-1 mt-1 btn btn-sm text-danger position-absolute end-0 me-1" onclick="removeVendor(this)"><i class="bi bi-x-circle-fill"></i></button>

                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="mb-1 x-small text-muted fw-bold">Pilih Vendor</label>
                                <select name="items[0][vendors][0][vendor_id]" class="form-select form-select-sm select2-vendor">
                                    <option value="">-- Pilih Vendor --</option>
                                    @foreach($vendors as $v) <option value="{{ $v->id }}">{{ $v->name }}</option> @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="mb-1 x-small text-muted fw-bold">Estimasi Harga</label>
                                <div class="input-group input-group-sm">
                                    <select name="items[0][vendors][0][currency]" class="bg-light fw-bold form-select" style="max-width: 85px;">
                                        @foreach($currencies as $curr) <option value="{{ $curr->code }}">{{ $curr->code }}</option> @endforeach
                                    </select>
                                    <input type="number" name="items[0][vendors][0][price]" class="form-control" placeholder="0">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="mb-1 x-small text-muted fw-bold">Lampiran Penawaran</label>
                                <input type="file" name="items[0][vendors][0][file]" class="form-control form-control-sm d-none file-input" id="file-0-0" onchange="updateFileName(this)">
                                <label for="file-0-0" class="btn btn-outline-secondary btn-sm w-100 text-start custom-file-label">
                                    <i class="bi bi-cloud-arrow-up me-2"></i><span class="file-text">Upload PDF/Image</span>
                                </label>
                            </div>
                            <div class="col-md-6">
                                <input type="url" name="items[0][vendors][0][link]" class="form-control form-control-sm" placeholder="Link Produk (URL)">
                            </div>
                            <div class="col-md-6">
                                <input type="text" name="items[0][vendors][0][notes]" class="form-control form-control-sm" placeholder="Catatan untuk vendor ini...">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- TOMBOL TAMBAH ITEM --}}
    <div class="pb-5 mb-5 text-center">
        <button type="button" class="px-4 py-2 border shadow-sm btn btn-outline-primary fw-bold rounded-pill" id="btn-add-item">
            <i class="bi bi-plus-circle-fill me-2"></i>Tambah Baris Barang Lain
        </button>
    </div>

    {{-- FIXED FOOTER MOBILE --}}
    <div class="px-3 py-3 bg-white shadow-lg fixed-bottom border-top d-md-none" style="z-index: 1050;">
        <div class="gap-2 d-flex">
            <button type="submit" class="py-2 shadow btn btn-primary fw-bold w-100" id="btn-submit-mobile">SUBMIT PR</button>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    let itemIdx = 0;

    $(document).ready(function() {
        // --- 1. INISIALISASI AWAL ---
        initSelect2User();
        initSelect2($('.item-row'));

        setTimeout(function() {
            if ($('#user_id').val()) {
                $('#user_id').trigger('change');
            }
        }, 500);

        // --- 2. LOGIKA AUTO-SWITCH PT ---
        $('#user_id').on('change select2:select', function() {
            let selectedOption = $(this).find(':selected');
            let companyId = selectedOption.data('company-id');

            if (companyId) {
                $('#company_id').val(companyId).trigger('change');
                $('#company_id').addClass('is-valid border-success');
                setTimeout(() => $('#company_id').removeClass('is-valid border-success'), 1500);
            }
        });

        // --- 3. LOGIKA TAMBAH ITEM BARU ---
        $('#btn-add-item').on('click', function() {
            itemIdx++;
            let $firstRow = $('.item-row').first();

            // Hancurkan Select2 sebelum clone
            $firstRow.find('.select2-item, .select2-vendor').select2('destroy');

            let $newRow = $firstRow.clone();

            $newRow.attr('data-index', itemIdx);
            $newRow.find('.badge').text(itemIdx + 1);

            // 🔥 PERBAIKAN: Reset Input dengan aman
            $newRow.find('input').val('');
            $newRow.find('select').each(function() {
                if ($(this).attr('name') && $(this).attr('name').includes('[currency]')) {
                    $(this).prop('selectedIndex', 0);
                } else {
                    $(this).val(null);
                }
            });

            // 🔥 PERBAIKAN: Reset Khusus Dropdown Satuan agar benar-benar kosong
            let $uomDropdown = $newRow.find('.select-uom');
            $uomDropdown.empty().append('<option value="" selected>-- Pilih --</option>');
            $uomDropdown.val('');

            // Update Naming
            $newRow.find('.select2-item').attr('name', `items[${itemIdx}][item_id]`);
            $newRow.find('input[name*="[qty]"]').attr('name', `items[${itemIdx}][qty]`);
            $newRow.find('.select-uom').attr('name', `items[${itemIdx}][uom_id]`);

            // Reset Section Vendor
            let $vContainer = $newRow.find('.vendor-container');
            $vContainer.attr('id', `vendor-container-${itemIdx}`);
            let $vRow = $vContainer.find('.vendor-row').first();
            $vContainer.empty().append($vRow);

            updateVendorNaming($vRow, itemIdx, 0);
            $newRow.find('button[onclick^="addVendor"]').attr('onclick', `addVendor(${itemIdx})`);

            $('#items-container').append($newRow);

            // Re-inisialisasi semua Select2
            initSelect2($('.item-row'));
        });

        // --- 4. SISTEM PERTAHANAN FORM ---
        $('#prForm').on('submit', function(e) {
            let form = this;
            e.preventDefault();

            if ($('.item-row').length === 0) {
                Swal.fire({ icon: 'error', title: 'Kosong!', text: 'Komandan belum memasukkan satu pun barang!' });
                return false;
            }

            let isBarangValid = true;
            $('.select2-item').each(function() {
                if ($(this).val() === "" || $(this).val() === null) isBarangValid = false;
            });

            if (!isBarangValid) {
                Swal.fire({ icon: 'warning', title: 'Data Belum Lengkap', text: 'Ada baris barang yang belum dipilih, Komandan.' });
                return false;
            }

            // Cegah double klik
            $('#btn-submit-main, #btn-submit-mobile').prop('disabled', true);

            Swal.fire({
                title: 'Kirim Permintaan?',
                text: "Dokumen akan diajukan untuk proses persetujuan.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#0d6efd',
                confirmButtonText: 'Ya, Kirim!',
                cancelButtonText: 'Cek Lagi',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Memproses...',
                        didOpen: () => { Swal.showLoading() },
                        allowOutsideClick: false
                    });
                    form.submit();
                } else {
                    $('#btn-submit-main, #btn-submit-mobile').prop('disabled', false);
                }
            });
        });
    });

    // --- FUNGSI HELPER: INIT SELECT2 USER ---
    function initSelect2User() {
        $('.select2-user').select2({
            theme: 'bootstrap-5',
            placeholder: "Cari Nama User...",
            width: '100%',
            allowClear: true
        });
    }

    // --- FUNGSI HELPER: INIT SELECT2 ITEM & VENDOR ---
    function initSelect2(context) {
        context.find('.select2-item').select2({
            theme: 'bootstrap-5',
            placeholder: "-- Cari Nama / Kode Barang --",
            allowClear: true,
            minimumInputLength: 2,
            width: '100%'
        });

        context.find('.select2-vendor').select2({
            theme: 'bootstrap-5',
            placeholder: "-- Pilih Vendor --",
            allowClear: true,
            width: '100%'
        });
    }

    // --- 7. LOGIKA AUTO-POPULATE SATUAN (UOM) ---
    $(document).on('change', '.select2-item', function() {
        let $row = $(this).closest('.item-row');
        let $uomSelect = $row.find('.select-uom');
        let selectedOption = $(this).find(':selected');
        let uomsData = selectedOption.data('uoms');

        $uomSelect.empty().append('<option value="">-- Pilih --</option>');

        if (uomsData && uomsData.length > 0) {
            uomsData.forEach(function(uom) {
                let displayLabel = uom.name;
                if (uom.isi > 1) {
                    displayLabel = `${uom.name} (${uom.isi} ${uom.base})`;
                }

                // 🔥 Karena id sudah ada di JSON, value ini sekarang akan membaca ID yang benar
                $uomSelect.append(`<option value="${uom.id}">${displayLabel}</option>`);
            });

            // Pilih otomatis baris pertama (Satuan Dasar)
            $uomSelect.val(uomsData[0].id).trigger('change');
        }
    });

    // --- LOGIKA TAMBAH VENDOR ---
    window.addVendor = function(iIdx) {
        let $container = $(`#vendor-container-${iIdx}`);
        let $masterVendor = $container.find('.vendor-row').first();
        $masterVendor.find('.select2-vendor').select2('destroy');

        let $newVendor = $masterVendor.clone();
        let vIdx = $container.find('.vendor-row').length;

        $newVendor.find('input').val('');
        $newVendor.find('select').each(function() {
            if ($(this).attr('name') && $(this).attr('name').includes('[currency]')) {
                $(this).prop('selectedIndex', 0);
            } else {
                $(this).val('');
            }
        });

        $newVendor.find('.file-text').text('Upload PDF/Image');
        $newVendor.find('.custom-file-label').removeClass('btn-success text-white border-success').addClass('btn-outline-secondary');

        updateVendorNaming($newVendor, iIdx, vIdx);
        $container.append($newVendor);
        initSelect2($('.item-row'));
    };

    // --- HELPER PENAMAAN ARRAY VENDOR ---
    function updateVendorNaming($row, iIdx, vIdx) {
        $row.find('select[name*="[vendor_id]"]').attr('name', `items[${iIdx}][vendors][${vIdx}][vendor_id]`);
        $row.find('select[name*="[currency]"]').attr('name', `items[${iIdx}][vendors][${vIdx}][currency]`);
        $row.find('input[name*="[price]"]').attr('name', `items[${iIdx}][vendors][${vIdx}][price]`);
        $row.find('input[name*="[file]"]').attr('name', `items[${iIdx}][vendors][${vIdx}][file]`).attr('id', `file-${iIdx}-${vIdx}`);
        $row.find('label[for^="file-"]').attr('for', `file-${iIdx}-${vIdx}`);
        $row.find('input[name*="[link]"]').attr('name', `items[${iIdx}][vendors][${vIdx}][link]`);
        $row.find('input[name*="[notes]"]').attr('name', `items[${iIdx}][vendors][${vIdx}][notes]`);
    }

    // --- FUNGSI UI: UPDATE FILENAME ---
    window.updateFileName = function(input) {
        let label = $(input).next('label');
        let textSpan = label.find('.file-text');
        if (input.files && input.files[0]) {
            textSpan.text(input.files[0].name);
            label.removeClass('btn-outline-secondary').addClass('btn-success text-white border-success');
        }
    };

    // --- FUNGSI UI: HAPUS ITEM ---
    window.removeItem = function(btn) {
        if ($('.item-row').length > 1) {
            Swal.fire({
                title: 'Hapus Item?',
                text: "Baris ini akan dihapus dari daftar permintaan.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'Ya, Hapus!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $(btn).closest('.item-row').fadeOut(300, function() {
                        $(this).remove();
                        $('.item-row').each(function(i) { $(this).find('.badge').text(i + 1); });
                    });
                }
            });
        } else {
            Swal.fire({ icon: 'error', title: 'Gagal', text: 'Minimal harus ada 1 barang.' });
        }
    };

    // --- FUNGSI UI: HAPUS VENDOR ---
    window.removeVendor = function(btn) {
        let $cont = $(btn).closest('.vendor-container');
        if ($cont.find('.vendor-row').length > 1) {
            $(btn).closest('.vendor-row').remove();
        } else {
            let $row = $(btn).closest('.vendor-row');
            $row.find('input').val('');
            $row.find('select').each(function() {
                if ($(this).attr('name') && $(this).attr('name').includes('[currency]')) {
                    $(this).prop('selectedIndex', 0);
                } else {
                    $(this).val('').trigger('change');
                }
            });
        }
    };
</script>
@endpush
