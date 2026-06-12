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
</style>
@endpush

@section('content')
<div class="container pb-5 text-dark">
    <div class="mb-4">
        <h4 class="mb-0 fw-bold text-dark">
            <i class="bi bi-magic text-info me-2"></i> Pengakuan Aset (Capitalization)
        </h4>
        <div class="mt-1 text-muted small">Ubah stok fisik menjadi Aset Tetap dengan spesifikasi unik per unit.</div>
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
                    <option value="">-- Ketik Nomor GR... --</option>
                    @foreach($grs as $gr)
                        <option value="{{ $gr->id }}">{{ $gr->gr_number }} (Tgl: {{ date('d-m-Y', strtotime($gr->received_date)) }})</option>
                    @endforeach
                </select>
                <div class="mt-2 text-muted small" id="wh-info"></div>
            </div>
        </div>

        {{-- 2. WADAH ITEM DAN FORM DINAMIS --}}
        <div id="items-container" class="d-none">
            <div class="border-0 shadow-sm card rounded-4">
                <div class="px-4 pt-4 pb-3 bg-white card-header border-bottom">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-list-check me-2 text-primary"></i> Rincian Barang & Registrasi Spesifikasi Aset</h6>
                </div>
                <div class="p-4 card-body" id="item-list-body" style="background-color: #f8f9fa;">
                    {{-- Diisi otomatis oleh JavaScript --}}
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
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>

<script>

    // 🔥 VALIDASI ANTI-KEMBAR SEBELUM FORM DISUBMIT 🔥
        $('#form-capitalize').on('submit', function(e) {
            let accNumbers = [];
            let hasDuplicate = false;

            // Kumpulkan semua nomor FA yang diketik user
            $('.acc-no-input').each(function() {
                let val = $(this).val().trim();
                if (val !== '') { // Jika tidak kosong
                    // Jika nomor sudah ada di array, berarti duplikat!
                    if (accNumbers.includes(val)) {
                        hasDuplicate = true;
                        return false; // Hentikan perulangan each
                    }
                    accNumbers.push(val);
                }
            });

            if (hasDuplicate) {
                e.preventDefault(); // Hentikan pengiriman data ke server
                Swal.fire({
                    icon: 'error',
                    title: 'Peringatan!',
                    text: 'Terdapat Nomor Akuntansi (FA) yang sama/kembar di form ini. Setiap unit harus memiliki Nomor FA yang unik, atau biarkan kosong.',
                    confirmButtonColor: '#dc3545',
                    confirmButtonText: 'Saya Perbaiki'
                });
                return false;
            }

            // Jika aman, ubah tombol jadi loading
            let btn = $('#btn-submit');
            btn.html('<span class="spinner-border spinner-border-sm me-2"></span> Menyimpan Aset...').prop('disabled', true);
        });


    $(document).ready(function() {
        $('.select2-gr').select2({ theme: 'bootstrap-5' });

        // Simpan data SN dari server ke variabel global
        let globalSnData = {};

        $('#gr_select').on('change', function() {
            let grId = $(this).val();
            let container = $('#items-container');
            let tbody = $('#item-list-body');
            let whInfo = $('#wh-info');

            if (!grId) { container.addClass('d-none'); whInfo.text(''); return; }

            whInfo.html('<span class="spinner-border spinner-border-sm text-info"></span> Mencari data barang...');

            $.ajax({
                url: `/asset-capitalizations/get-items/${grId}`,
                type: 'GET',
                success: function(res) {
                    $('#warehouse_id').val(res.warehouse_id);
                    whInfo.html(`<i class="bi bi-box-seam text-info me-1"></i> Lokasi Stok: <strong class="text-dark">${res.warehouse_name}</strong>`);

                    tbody.empty();
                    globalSnData = {}; // Reset

                    if (res.items.length === 0) {
                        tbody.append(`<div class="mb-0 alert alert-warning fw-bold"><i class="bi bi-info-circle me-2"></i>Tidak ada stok fisik yang bisa diakui sebagai aset dari GR ini.</div>`);
                    } else {
                        res.items.forEach(item => {
                            globalSnData[item.item_id] = item.available_sns || [];

                            let html = `
                            <div class="p-4 mb-4 bg-white border shadow-sm rounded-4">
                                <div class="pb-3 mb-3 row align-items-center border-bottom">
                                    <div class="col-md-5">
                                        <div class="fw-bolder fs-5 text-dark">${item.item_name}</div>
                                        <div class="mt-1 small text-muted">Kode: <span class="badge bg-secondary-subtle text-secondary">${item.item_code}</span> | Sisa Fisik: <strong class="text-danger">${item.max_capitalizable} ${item.base_uom}</strong></div>
                                    </div>
                                    <div class="mt-3 col-md-3 text-md-end mt-md-0">
                                        <label class="mb-1 small fw-bold text-muted text-uppercase">Jadikan Aset</label>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="shadow-sm input-group input-group-lg">
                                            <input type="number" name="items[${item.item_id}][qty]" class="text-center form-control fw-bold border-info text-info qty-input"
                                                data-item="${item.item_id}"
                                                data-name="${item.item_name}"
                                                data-price="${item.default_price}"
                                                data-date="${item.default_date}"
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

        // 🔥 MAGIC: Bikin Form Sebanyak Qty yang Diketik 🔥
        $(document).on('input', '.qty-input', function() {
            let qty = parseInt($(this).val()) || 0;
            let max = parseInt($(this).attr('max'));
            if (qty > max) { qty = max; $(this).val(max); }
            if (qty < 0) { qty = 0; $(this).val(0); }

            let itemId = $(this).data('item');
            let itemName = $(this).data('name');

            // 🔥 TANGKAP NILAI DEFAULT DARI CONTROLLER 🔥
            let defaultPrice = $(this).data('price');
            let defaultDate = $(this).data('date');

            let container = $(`#spec-container-${itemId}`);
            let availableSns = globalSnData[itemId] || [];

            container.empty();

            for (let i = 0; i < qty; i++) {
                let defaultSn = availableSns[i] || '';

                container.append(`
                    <div class="mb-3 col-md-6">
                        <div class="shadow-sm card asset-card h-100 rounded-3">
                            <div class="py-2 bg-white card-header d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-info"><i class="bi bi-pc-display me-1"></i> Unit #${i+1}</span>
                            </div>
                            <div class="p-3 card-body">
                                <div class="mb-3">
                                    <label class="mb-1 small text-muted fw-bold">Nama Spesifik / Merk <span class="text-danger">*</span></label>
                                    <input type="text" name="items[${itemId}][details][${i}][specific_name]" class="form-control" value="${itemName}" required>
                                </div>
                                <div class="mb-3 row g-2">
                                    <div class="col-sm-6">
                                        <label class="mb-1 small text-muted fw-bold">No. Akuntansi (FA)</label>
                                        {{-- 🔥 Tambahkan class acc-no-input di bawah ini 🔥 --}}
                                        <input type="text" name="items[${itemId}][details][${i}][accounting_no]" class="form-control form-control-sm acc-no-input" placeholder="Opsional...">
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="mb-1 small text-muted fw-bold">Serial Number (SN)</label>
                                        <input type="text" name="items[${itemId}][details][${i}][serial_number]" class="form-control form-control-sm bg-warning-subtle text-dark fw-bold border-warning" value="${defaultSn}" placeholder="Suntik SN...">
                                    </div>
                                </div>

                                {{-- 🔥 FORM TANGGAL, HARGA, DAN UMUR ASET (AUTO-FILL) 🔥 --}}
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
                                        <label class="mb-1 small text-primary-emphasis fw-bold" style="font-size:0.7rem;"><i class="bi bi-hourglass-split me-1"></i>Umur (Tahun)</label>
                                        <input type="number" name="items[${itemId}][details][${i}][useful_life]" class="form-control form-control-sm border-primary-subtle" placeholder="Cth: 5">
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

                // Init Quill
                var quill = new Quill(`#editor-${itemId}-${i}`, {
                    theme: 'snow',
                    placeholder: 'Tulis spesifikasi lengkap di sini...',
                    modules: {
                        toolbar: [ ['bold', 'italic', 'underline'], [{ 'list': 'ordered'}, { 'list': 'bullet' }], [{ 'color': [] }] ]
                    }
                });

                quill.on('text-change', function() {
                    var htmlContent = document.querySelector(`#editor-${itemId}-${i} .ql-editor`).innerHTML;
                    if(htmlContent === '<p><br></p>') htmlContent = '';
                    document.getElementById(`hidden-notes-${itemId}-${i}`).value = htmlContent;
                });
            }
        });
    });
</script>
@endpush
