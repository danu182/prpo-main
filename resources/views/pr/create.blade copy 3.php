@extends('layouts.app')

@push('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<style>
    :root { --pr-blue: #0d6efd; --pr-red: #ef4444; --bg-light: #f8fafc; }
    .select2-container--bootstrap-5 .select2-selection { border-radius: 8px; border-color: #dee2e6; min-height: 38px; font-size: 0.875rem; }
    .select2-container--bootstrap-5.select2-container--focus .select2-selection { border-color: #0d6efd; box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15); }
    .invalid-feedback { font-size: 0.75rem; font-weight: bold; }
    .is-invalid + .select2-container .select2-selection { border-color: #dc3545 !important; }
    .item-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px; margin-bottom: 24px; position: relative; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); border-left: 6px solid var(--pr-blue); transition: transform 0.2s ease-in-out, box-shadow 0.2s; }
    .item-card:hover { box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
    .btn-delete-item { position: absolute; top: 20px; right: 20px; color: var(--pr-red); background: #fef2f2; border: 1px solid #fca5a5; width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; transition: 0.2s; z-index: 10; cursor: pointer; }
    .btn-delete-item:hover { background: #fee2e2; transform: scale(1.1); color: #b91c1c; }
    .vendor-section { background-color: var(--bg-light); border-radius: 12px; padding: 20px; margin-top: 20px; border: 1px dashed #cbd5e1; }
    .vendor-row { background: #ffffff; border-radius: 10px; border: 1px solid #e2e8f0; padding: 16px; margin-bottom: 12px; position: relative; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
    input[type=number]::-webkit-inner-spin-button, input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
    input[type=number] { -moz-appearance: textfield; }
    .fixed-bottom-bar { background: rgba(255, 255, 255, 0.95) !important; backdrop-filter: blur(10px); border-top: 1px solid #dee2e6; z-index: 1040; }
</style>
@endpush

@section('content')

@if ($errors->any())
    <div class="mb-4 border-0 shadow-sm alert alert-danger rounded-4">
        <div class="fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i> Mohon Maaf, Terjadi Kesalahan:</div>
        <ul class="mt-2 mb-0 small">
            @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
        </ul>
    </div>
@endif

<form action="{{ route('pr.store') }}" method="POST" enctype="multipart/form-data" id="prForm">
    @csrf

    <div class="pb-3 mb-4 d-flex justify-content-between align-items-center border-bottom">
        <div>
            <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-file-earmark-plus-fill me-2 text-primary"></i>Buat Purchase Request</h4>
            <p class="mb-0 text-muted small">Ajukan permintaan pengadaan barang/jasa perusahaan.</p>
        </div>
        <div class="gap-2 d-none d-md-flex">
            <a href="{{ route('pr.index') }}" class="px-4 border shadow-sm btn btn-light rounded-pill fw-bold"><i class="bi bi-arrow-left me-1"></i> Batal</a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12">
            <div class="mb-4 border-0 shadow-sm card rounded-4">
                <div class="py-3 bg-light card-header border-bottom">
                    <h6 class="mb-0 fw-bold text-dark text-uppercase" style="font-size: 0.85rem;"><i class="bi bi-info-circle-fill me-2 text-primary"></i>Informasi Utama Dokumen PR</h6>
                </div>
                <div class="p-4 bg-white card-body">
                    <div class="row g-4">
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-dark">User Peminta <span class="text-danger">*</span></label>
                            <select name="user_id" id="user_id" class="form-select select2-user" required>
                                <option value="">-- Pilih User --</option>
                                @foreach($users as $u)
                                    <option value="{{ $u->id }}" data-company-id="{{ $u->company_id }}" {{ old('user_id', auth()->id()) == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-dark">Unit / PT Penanggung <span class="text-danger">*</span></label>
                            <select name="company_id" id="company_id" class="form-select border-secondary-subtle" required>
                                <option value="">-- Pilih Unit --</option>
                                @foreach($companies as $c) <option value="{{ $c->id }}" {{ old('company_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option> @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-dark">Tanggal Request <span class="text-danger">*</span></label>
                            <input type="date" name="request_date" class="form-control border-secondary-subtle" value="{{ old('request_date', date('Y-m-d')) }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-dark">Dibutuhkan Pada <span class="text-danger">*</span></label>
                            <input type="date" name="need_date" class="form-control border-secondary-subtle" value="{{ old('need_date') }}" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small text-dark">Tujuan / Keterangan Pengadaan <span class="text-danger">*</span></label>
                            <input type="text" name="description" class="form-control border-secondary-subtle bg-light" value="{{ old('description') }}" placeholder="Tuliskan tujuan pengadaan ini dengan jelas..." required>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="items-container">
        <div class="item-card item-row" data-index="0">
            <button type="button" class="shadow-sm btn-delete-item" onclick="removeItem(this)" title="Hapus Barang Ini"><i class="bi bi-trash-fill"></i></button>

            <h6 class="pb-3 mb-4 fw-bold text-dark border-bottom">
                <span class="px-2 py-1 shadow-sm badge bg-primary rounded-pill me-2 item-number">1</span> Permintaan Barang
            </h6>

            <div class="row g-4">
                <div class="col-md-6">
                    <label class="mb-2 small text-dark fw-bold">Pilih Barang dari Master Katalog <span class="text-danger">*</span></label>
                    <select name="items[0][item_id]" class="form-select select2-item" required>
                        <option value="">-- Cari Nama / Kode Barang --</option>
                        @foreach($items as $item)
                            @php
                                $uomList = [];
                                if($item->uom) $uomList[] = ['id' => $item->uom_id, 'name' => $item->uom->name, 'isi' => 1, 'base' => $item->uom->name];
                                if($item->itemUoms) {
                                    foreach($item->itemUoms as $alt) {
                                        $uomList[] = ['id' => $alt->id, 'name' => $alt->uom_name, 'isi' => $alt->conversion_qty, 'base' => $item->uom->name ?? 'PCS'];
                                    }
                                }
                            @endphp
                            <option value="{{ $item->id }}" data-uoms='{{ json_encode($uomList) }}'>{{ $item->code }} - {{ $item->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="mb-2 small text-dark fw-bold">Jumlah (Qty) <span class="text-danger">*</span></label>
                    <input type="number" name="items[0][qty]" class="form-control text-primary fw-bold" placeholder="0" min="0.01" step="0.01" required>
                </div>
                <div class="col-md-3">
                    <label class="mb-2 small text-dark fw-bold">Kemasan / Satuan <span class="text-danger">*</span></label>
                    <select name="items[0][uom_id]" class="form-select select-uom fw-bold" required>
                        <option value="">-- Pilih Barang Dulu --</option>
                    </select>
                </div>
            </div>

            {{-- SECTION VENDOR --}}
            <div class="shadow-sm vendor-section">
                <div class="pb-2 mb-3 d-flex justify-content-between align-items-center border-bottom">
                    <label class="small fw-bold text-dark text-uppercase"><i class="bi bi-tags-fill me-2 text-success"></i>Referensi Vendor & Harga Penawaran</label>
                    <button type="button" class="bg-white shadow-sm btn btn-sm btn-outline-success fw-bold rounded-pill" onclick="addVendor(0)">
                        <i class="bi bi-plus-lg me-1"></i> Tambah Vendor
                    </button>
                </div>

                <div class="vendor-container" id="vendor-container-0">
                    <div class="vendor-row">
                        <button type="button" class="top-0 p-1 mt-2 btn btn-sm text-danger position-absolute end-0 me-2" onclick="removeVendor(this)"><i class="bi bi-x-circle-fill fs-5"></i></button>

                        <div class="row g-3 pe-4">
                            <div class="col-lg-4 col-md-6">
                                <label class="mb-1 x-small text-muted fw-bold">Pilih Vendor</label>
                                <select name="items[0][vendors][0][vendor_id]" class="form-select form-select-sm select2-vendor">
                                    <option value="">-- Ketik / Cari Vendor --</option>
                                    @foreach($vendors as $v) <option value="{{ $v->id }}">{{ $v->name }}</option> @endforeach
                                </select>
                            </div>
                            <div class="col-lg-4 col-md-6">
                                <label class="mb-1 x-small text-muted fw-bold">Estimasi Harga Satuan</label>
                                <div class="overflow-hidden rounded shadow-sm input-group input-group-sm">
                                    <select name="items[0][vendors][0][currency]" class="bg-light fw-bold form-select text-primary" style="max-width: 85px;">
                                        @foreach($currencies as $curr) <option value="{{ $curr->code }}">{{ $curr->code }}</option> @endforeach
                                    </select>
                                    <input type="number" name="items[0][vendors][0][price]" class="form-control text-end fw-bold" placeholder="0" step="0.01">
                                </div>
                            </div>
                            
                            {{-- 🔥 TOMBOL TAMBAH MULTI-FILE LAMPIRAN 🔥 --}}
                            <div class="col-lg-4 col-md-12">
                                <div class="p-2 border rounded bg-light border-secondary-subtle">
                                    <div class="mb-2 d-flex justify-content-between align-items-center">
                                        <label class="mb-0 text-muted fw-bold" style="font-size: 0.65rem;"><i class="bi bi-paperclip"></i> Upload Penawaran:</label>
                                        <button type="button" class="px-2 py-0 btn btn-sm btn-outline-secondary btn-add-file" style="font-size: 0.65rem;" onclick="addFileRow(0, 0)">
                                            <i class="bi bi-plus"></i> Tambah File
                                        </button>
                                    </div>
                                    <div class="file-container" id="fileContainer_0_0">
                                        {{-- Row File Pertama --}}
                                        <div class="mb-1 overflow-hidden rounded shadow-sm input-group input-group-sm file-row">
                                            <input type="file" name="items[0][vendors][0][files][]" class="bg-white form-control" style="font-size: 0.65rem;" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                                            <button type="button" class="px-2 btn btn-danger" onclick="this.closest('.file-row').remove()"><i class="bi bi-x"></i></button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-6 mt-2">
                                <label class="mb-1 x-small text-muted fw-bold">Link Produk</label>
                                <input type="url" name="items[0][vendors][0][link]" class="shadow-sm form-control form-control-sm" placeholder="https://...">
                            </div>
                            <div class="mt-2 col-lg-6">
                                <label class="mb-1 x-small text-muted fw-bold">Catatan untuk Vendor Ini</label>
                                <input type="text" name="items[0][vendors][0][notes]" class="shadow-sm form-control form-control-sm" placeholder="Contoh: Pastikan warna merah, garansi 1 tahun...">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="pb-5 mt-2 mb-5 text-center">
        <button type="button" class="px-5 py-2 shadow-sm btn btn-outline-primary fw-bold rounded-pill" id="btn-add-item">
            <i class="bi bi-plus-circle-fill me-2"></i>Tambah Barang Lainnya
        </button>
    </div>

    <div class="p-3 shadow-lg fixed-bottom-bar fixed-bottom">
        <div class="container gap-3 d-flex justify-content-end align-items-center">
            <button type="submit" class="px-5 shadow btn btn-primary rounded-pill fw-bold fs-6" id="btn-submit-main">
                <i class="bi bi-send-fill me-2"></i> Ajukan Permintaan (Submit PR)
            </button>
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
        initSelect2User();
        initSelect2($('.item-row'));

        setTimeout(function() { if ($('#user_id').val()) $('#user_id').trigger('change'); }, 500);

        $('#user_id').on('change select2:select', function() {
            let companyId = $(this).find(':selected').data('company-id');
            if (companyId) {
                $('#company_id').val(companyId).trigger('change');
                $('#company_id').addClass('is-valid border-success');
                setTimeout(() => $('#company_id').removeClass('is-valid border-success'), 1500);
            }
        });

        // 🔥 LOGIKA TAMBAH BARANG (DENGAN RESET HTML BERSIH) 🔥
        $('#btn-add-item').on('click', function() {
            itemIdx++;
            let $firstRow = $('.item-row').first();
            $firstRow.find('.select2-item, .select2-vendor').select2('destroy');

            let $newRow = $firstRow.clone();
            $newRow.attr('data-index', itemIdx);
            $newRow.find('.item-number').text($('.item-row').length + 1);

            $newRow.find('input').val('');
            $newRow.find('select').each(function() {
                if ($(this).attr('name') && $(this).attr('name').includes('[currency]')) {
                    $(this).prop('selectedIndex', 0);
                } else { $(this).val(null); }
            });

            let $uomDropdown = $newRow.find('.select-uom');
            $uomDropdown.empty().append('<option value="" selected>-- Pilih Barang Dulu --</option>');

            $newRow.find('.select2-item').attr('name', `items[${itemIdx}][item_id]`);
            $newRow.find('input[name*="[qty]"]').attr('name', `items[${itemIdx}][qty]`);
            $newRow.find('.select-uom').attr('name', `items[${itemIdx}][uom_id]`);

            let $vContainer = $newRow.find('.vendor-container');
            $vContainer.attr('id', `vendor-container-${itemIdx}`);
            let $vRow = $vContainer.find('.vendor-row').first();

            // SUNTIKKAN HTML SEGAR UNTUK FILE CONTAINER AGAR TIDAK BAWA SAMPAN FILE BARIS LAMA
            $vRow.find('.file-container').attr('id', `fileContainer_${itemIdx}_0`).html(`
                <div class="mb-1 overflow-hidden rounded shadow-sm input-group input-group-sm file-row">
                    <input type="file" name="items[${itemIdx}][vendors][0][files][]" class="bg-white form-control" style="font-size: 0.65rem;" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                    <button type="button" class="px-2 btn btn-danger" onclick="this.closest('.file-row').remove()"><i class="bi bi-x"></i></button>
                </div>
            `);

            $vContainer.empty().append($vRow);

            updateVendorNaming($vRow, itemIdx, 0);
            $newRow.find('button[onclick^="addVendor"]').attr('onclick', `addVendor(${itemIdx})`);

            $('#items-container').append($newRow);
            initSelect2($('.item-row'));
        });

        $('#prForm').on('submit', function(e) {
            let form = this; e.preventDefault();
            if ($('.item-row').length === 0) return Swal.fire({ icon: 'error', title: 'Kosong!', text: 'Belum ada barang!' });
            let isBarangValid = true;
            $('.select2-item').each(function() { if ($(this).val() === "" || $(this).val() === null) isBarangValid = false; });
            if (!isBarangValid) return Swal.fire({ icon: 'warning', title: 'Data Belum Lengkap', text: 'Ada baris barang yang belum dipilih.' });

            Swal.fire({
                title: 'Ajukan Purchase Request?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#0d6efd',
                confirmButtonText: 'Ya, Kirim PR!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({ title: 'Menyimpan...', didOpen: () => { Swal.showLoading() }, allowOutsideClick: false });
                    form.submit();
                }
            });
        });
    });

    function initSelect2User() { $('.select2-user').select2({ theme: 'bootstrap-5', placeholder: "Cari Nama User...", width: '100%', allowClear: true }); }
    function initSelect2(context) {
        context.find('.select2-item').select2({ theme: 'bootstrap-5', placeholder: "-- Cari Nama Barang --", allowClear: true, width: '100%' });
        context.find('.select2-vendor').select2({ theme: 'bootstrap-5', placeholder: "-- Cari Vendor --", allowClear: true, width: '100%' });
    }

    $(document).on('change', '.select2-item', function() {
        let $row = $(this).closest('.item-row');
        let $uomSelect = $row.find('.select-uom');
        let selectedOption = $(this).find(':selected');
        let uomsData = selectedOption.data('uoms');

        $uomSelect.empty().append('<option value="">-- Pilih Kemasan --</option>');
        if (uomsData && uomsData.length > 0) {
            uomsData.forEach(function(uom) {
                let displayLabel = uom.isi > 1 ? `${uom.name} (Isi: ${uom.isi} ${uom.base})` : uom.name;
                $uomSelect.append(`<option value="${uom.id}">${displayLabel}</option>`);
            });
            $uomSelect.val(uomsData[0].id).trigger('change');
        }
    });

    // 🔥 LOGIKA TAMBAH VENDOR (DENGAN RESET HTML BERSIH) 🔥
    window.addVendor = function(iIdx) {
        let $container = $(`#vendor-container-${iIdx}`);
        let $masterVendor = $container.find('.vendor-row').first();
        if($masterVendor.length === 0) return;

        $masterVendor.find('.select2-vendor').select2('destroy');

        let $newVendor = $masterVendor.clone();
        let vIdx = $container.find('.vendor-row').length;

        $newVendor.find('input').val('');
        $newVendor.find('select').each(function() {
            if ($(this).attr('name') && $(this).attr('name').includes('[currency]')) {
                $(this).prop('selectedIndex', 0);
            } else { $(this).val(''); }
        });

        // SUNTIKKAN HTML SEGAR UNTUK FILE CONTAINER AGAR TIDAK BAWA SAMPAN FILE DARI VENDOR LAIN
        $newVendor.find('.file-container').attr('id', `fileContainer_${iIdx}_${vIdx}`).html(`
            <div class="mb-1 overflow-hidden rounded shadow-sm input-group input-group-sm file-row">
                <input type="file" name="items[${iIdx}][vendors][${vIdx}][files][]" class="bg-white form-control" style="font-size: 0.65rem;" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                <button type="button" class="px-2 btn btn-danger" onclick="this.closest('.file-row').remove()"><i class="bi bi-x"></i></button>
            </div>
        `);

        updateVendorNaming($newVendor, iIdx, vIdx);

        $newVendor.css('display', 'none');
        $container.append($newVendor);
        $newVendor.fadeIn(300);
        initSelect2($('.item-row'));
    };

    function updateVendorNaming($row, iIdx, vIdx) {
        $row.find('select[name*="[vendor_id]"]').attr('name', `items[${iIdx}][vendors][${vIdx}][vendor_id]`);
        $row.find('select[name*="[currency]"]').attr('name', `items[${iIdx}][vendors][${vIdx}][currency]`);
        $row.find('input[name*="[price]"]').attr('name', `items[${iIdx}][vendors][${vIdx}][price]`);
        $row.find('input[name*="[link]"]').attr('name', `items[${iIdx}][vendors][${vIdx}][link]`);
        $row.find('input[name*="[notes]"]').attr('name', `items[${iIdx}][vendors][${vIdx}][notes]`);

        // UPDATE PENAMAAN ARRAY FILE & TOMBOL TAMBAH FILE
        $row.find('.btn-add-file').attr('onclick', `addFileRow(${iIdx}, ${vIdx})`);
    }

    // 🔥 FUNGSI SAKTI UNTUK MENAMBAH BARIS UPLOAD FILE 🔥
    window.addFileRow = function(iIdx, vIdx) {
        const container = document.getElementById(`fileContainer_${iIdx}_${vIdx}`);
        if(!container) return;
        const div = document.createElement('div');
        div.className = 'input-group input-group-sm mb-1 file-row shadow-sm rounded overflow-hidden';
        div.innerHTML = `
            <input type="file" name="items[${iIdx}][vendors][${vIdx}][files][]" class="bg-white form-control" style="font-size: 0.65rem;" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" required>
            <button type="button" class="px-2 btn btn-danger" onclick="this.closest('.file-row').remove()"><i class="bi bi-x"></i></button>
        `;
        container.appendChild(div);
    }

    window.removeItem = function(btn) {
        if ($('.item-row').length > 1) {
            Swal.fire({
                title: 'Hapus Item?',
                icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Ya, Hapus!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $(btn).closest('.item-row').fadeOut(300, function() {
                        $(this).remove();
                        $('.item-row').each(function(i) { $(this).find('.item-number').text(i + 1); });
                    });
                }
            });
        } else { Swal.fire({ icon: 'error', title: 'Gagal', text: 'Minimal 1 barang.' }); }
    };

    window.removeVendor = function(btn) {
        let $cont = $(btn).closest('.vendor-container');
        if ($cont.find('.vendor-row').length > 1) {
            $(btn).closest('.vendor-row').fadeOut(200, function() { $(this).remove(); });
        } else {
            let $row = $(btn).closest('.vendor-row');
            $row.find('input').not('[type="hidden"]').val('');
            $row.find('select').each(function() {
                if ($(this).attr('name') && $(this).attr('name').includes('[currency]')) {
                    $(this).prop('selectedIndex', 0);
                } else { $(this).val('').trigger('change'); }
            });
            
            // RESET FILE CONTAINER JADI 1 KOSONG
            let iIdx = $row.closest('.item-row').data('index');
            $row.find('.file-container').html(`
                <div class="mb-1 overflow-hidden rounded shadow-sm input-group input-group-sm file-row">
                    <input type="file" name="items[${iIdx}][vendors][0][files][]" class="bg-white form-control" style="font-size: 0.65rem;" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                    <button type="button" class="px-2 btn btn-danger" onclick="this.closest('.file-row').remove()"><i class="bi bi-x"></i></button>
                </div>
            `);
            
            Swal.fire({ icon: 'info', title: 'Direset', timer: 1000, showConfirmButton: false });
        }
    };
</script>
@endpush