@extends('layouts.app')

@push('css')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <style>
        .select2-container--bootstrap-5 .select2-selection { border-radius: 0.375rem; font-size: 0.875rem; }
    </style>
@endpush

@section('content')
<div class="container py-2">

    {{-- HEADER --}}
    <div class="mb-4 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <a href="{{ route('bills.index') }}" class="bg-white border shadow-sm btn btn-white rounded-circle me-3 text-primary hover-light" style="width: 45px; height: 45px; display: flex; align-items: center; justify-content: center;">
                <i class="bi bi-arrow-left fs-5"></i>
            </a>
            <div>
                <h4 class="mb-0 fw-bold text-dark">Buat Tagihan Baru (Opex)</h4>
                <div class="text-muted small">Input pengeluaran operasional perusahaan</div>
            </div>
        </div>
    </div>

    @if ($errors->any())
        <div class="mb-4 border-0 shadow-sm alert alert-danger alert-dismissible fade show rounded-4" role="alert">
            <div class="mb-1 fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i>Terdapat Kesalahan:</div>
            <ul class="mb-0 small">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <form action="{{ route('bills.store') }}" method="POST" enctype="multipart/form-data" id="billForm">
        @csrf
        <div class="row g-4">
            {{-- KOLOM KIRI: FORM UTAMA --}}
            <div class="col-lg-8">

                {{-- CARD 1: INFORMASI UMUM --}}
                <div class="mb-4 border-0 shadow-sm card rounded-4">
                    <div class="py-3 bg-white card-header border-bottom-0 rounded-top-4">
                        <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-info-circle me-2"></i>Informasi Tagihan</h6>
                    </div>
                    <div class="p-4 pt-2 card-body">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label fw-bold small text-muted text-uppercase">PT Penanggung Jawab <span class="text-danger">*</span></label>
                                <select name="paid_by_company_id" class="form-select select2-single" required>
                                    <option value="">-- Ketik untuk mencari PT --</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}">{{ $company->code ? '['.$company->code.'] ' : '' }}{{ $company->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-bold small text-muted text-uppercase">Mata Uang <span class="text-danger">*</span></label>
                                <select name="currency_id" id="currency_select" class="form-select select2-single" required>
                                    @foreach($currencies as $curr)
                                        <option value="{{ $curr->id }}" data-symbol="{{ $curr->symbol }}">
                                            {{ $curr->code }} - {{ $curr->name }} ({{ $curr->symbol }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted text-uppercase">Tanggal Tagihan <span class="text-danger">*</span></label>
                                <input type="date" name="bill_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted text-uppercase">Jatuh Tempo (Due Date) <span class="text-danger">*</span></label>
                                <input type="date" name="due_date" class="form-control" value="{{ date('Y-m-d', strtotime('+14 days')) }}" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted text-uppercase">Nama Vendor / Supplier <span class="text-danger">*</span></label>
                                <select name="vendor_name" class="form-select select2-vendor" required>
                                    <option value="">-- Cari Vendor dari Master Data --</option>
                                    @foreach($vendors as $vendor)
                                        <option value="{{ $vendor->name }}">{{ $vendor->name }}</option>
                                    @endforeach
                                </select>
                                <div class="mt-1 form-text small text-primary">
                                    <i class="bi bi-lightbulb me-1"></i>Jika belum ada, <strong>ketik lalu tekan Enter</strong>.
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted text-uppercase">No. Invoice Vendor <span class="text-muted text-lowercase">(Opsional)</span></label>
                                <input type="text" name="vendor_invoice_number" class="form-control" value="{{ old('vendor_invoice_number') }}" placeholder="Contoh: INV-2026-001">
                                <div class="mt-1 form-text small">
                                    Akan muncul di kolom Invoices No pada Form BPR PDF.
                                </div>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-bold small text-muted text-uppercase">Catatan Tagihan</label>
                                <textarea name="note" class="form-control rounded-3" rows="2" placeholder="Tuliskan keterangan jika ada..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- CARD 2: PENGATURAN BERULANG (RECURRING) --}}
                <div class="mb-4 border border-0 shadow-sm card rounded-4 bg-light border-info-subtle">
                    <div class="card-body">
                        <h6 class="mb-3 fw-bold text-dark"><i class="bi bi-arrow-repeat me-2 text-primary"></i>Pengaturan Tagihan Berulang (Otomatis)</h6>
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-muted">Jenis Tagihan</label>
                                <select name="is_recurring" id="is_recurring" class="form-select">
                                    <option value="0">Sekali Saja (One-time)</option>
                                    <option value="1">Berulang (Recurring)</option>
                                </select>
                            </div>
                            <div id="recurring_setup" class="col-md-8 d-none">
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold small text-muted">Setiap</label>
                                        <input type="number" name="recurring_interval" class="form-control" value="1" min="1">
                                    </div>
                                    <div class="col-md-8">
                                        <label class="form-label fw-bold small text-muted">Periode</label>
                                        <select name="recurring_period" class="form-select">
                                            <option value="days">Hari</option>
                                            <option value="weeks">Minggu</option>
                                            <option value="months" selected>Bulan</option>
                                            <option value="years">Tahun</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- CARD 3: RINCIAN ITEM --}}
                <div class="mb-4 border-0 shadow-sm card rounded-4">
                    {{-- 🔥 HEADER DENGAN FITUR SET PAJAK MASSAL (BARU) 🔥 --}}
                    <div class="py-3 bg-white card-header border-bottom-0 rounded-top-4 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-list-check me-2"></i>Rincian Barang / Jasa Opex</h6>
                        <div class="input-group input-group-sm" style="width: 320px;">
                            <span class="input-group-text bg-light border-primary-subtle" style="font-size: 0.75rem;">Pajak Semua:</span>
                            <input type="text" id="global_tax_val" class="form-control price-display border-primary-subtle" value="0">
                            <select id="global_tax_type" class="form-select border-primary-subtle" style="max-width: 60px;">
                                <option value="fixed">Rp</option>
                                <option value="percent" selected>%</option>
                            </select>
                            <button class="btn btn-primary" type="button" id="btnApplyGlobalTax" style="font-size: 0.75rem;">
                                <i class="bi bi-check-all"></i>
                            </button>
                        </div>
                    </div>

                    <div class="p-4 pt-0 card-body table-responsive">
                        <table class="table align-middle table-borderless" id="itemTable">
                            <thead class="bg-secondary bg-opacity-10 text-muted small fw-bold text-uppercase rounded-3">
                                <tr>
                                    <th width="35%" class="rounded-start">Master Item</th>
                                    <th width="10%">Qty</th>
                                    <th width="20%">Harga Satuan</th>
                                    <th width="25%">Pajak & Diskon Item</th>
                                    <th width="5%" class="rounded-end"></th>
                                </tr>
                            </thead>
                            <tbody id="itemContainer">
                                <tr class="item-row border-bottom">
                                    <td class="pt-3">
                                        <select name="items[0][name]" class="mb-2 form-select select2-item" required>
                                            <option value="">-- Pilih Item Opex --</option>
                                            @foreach($opexItems as $opx)
                                                <option value="{{ $opx->name }}">{{ $opx->code }} - {{ $opx->name }}</option>
                                            @endforeach
                                        </select>
                                        <textarea name="items[0][description]" class="form-control form-control-sm" rows="1" placeholder="Deskripsi/Keterangan tambahan..."></textarea>
                                    </td>
                                    <td class="pt-3">
                                        <input type="number" name="items[0][qty]" class="text-center form-control form-control-sm qty" value="1" min="1">
                                    </td>
                                    <td class="pt-3">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text curr-symbol">Rp</span>
                                            <input type="text" class="form-control price-display text-end" value="0">
                                            <input type="hidden" name="items[0][price]" class="price-real" value="0">
                                        </div>
                                    </td>
                                    <td class="pt-3">
                                        {{-- 🔥 INPUT PAJAK BARU (Bisa Nominal/Persen) 🔥 --}}
                                        <div class="mb-1 input-group input-group-sm" title="Pajak per-item">
                                            <span class="input-group-text bg-info bg-opacity-10 text-info fw-bold" style="font-size: 0.7rem;">+Pajak</span>
                                            <input type="text" class="form-control tax-val-display" value="0">
                                            <input type="hidden" name="items[0][tax_value]" class="tax-val-real" value="0">
                                            <select name="items[0][tax_type]" class="form-select tax-type" style="max-width: 60px;">
                                                <option value="fixed">Rp</option>
                                                <option value="percent" selected>%</option>
                                            </select>
                                        </div>

                                        {{-- Input Diskon --}}
                                        <div class="input-group input-group-sm" title="Diskon per-item">
                                            <span class="input-group-text bg-danger bg-opacity-10 text-danger fw-bold" style="font-size: 0.7rem;">-Disc&nbsp;&nbsp;</span>
                                            <input type="text" class="form-control disc-val-display" value="0">
                                            <input type="hidden" name="items[0][discount_value]" class="disc-val-real" value="0">
                                            <select name="items[0][discount_type]" class="form-select disc-type" style="max-width: 60px;">
                                                <option value="fixed">Rp</option>
                                                <option value="percent" selected>%</option>
                                            </select>
                                        </div>
                                    </td>
                                    <td class="pt-3 text-end">
                                        <button type="button" class="btn btn-outline-danger btn-sm remove-item rounded-circle" title="Hapus Baris"><i class="bi bi-trash"></i></button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <button type="button" class="px-3 mt-2 btn btn-primary btn-sm rounded-pill fw-bold" id="addItem">
                            <i class="bi bi-plus-lg me-1"></i> Tambah Item
                        </button>
                    </div>
                </div>

                {{-- PEMBAGIAN 2 KOLOM UNTUK CHARGE & DISCOUNT --}}
                <div class="mb-4 row g-4">
                    {{-- CARD 4A: BIAYA TAMBAHAN --}}
                    <div class="col-md-6">
                        <div class="border-0 border-opacity-50 shadow-sm card rounded-4 border-warning h-100">
                            <div class="py-3 bg-white card-header border-bottom-0 rounded-top-4">
                                <h6 class="mb-0 fw-bold text-warning-emphasis"><i class="bi bi-plus-circle-dotted me-2"></i>Biaya Tambahan</h6>
                            </div>
                            <div class="p-3 pt-0 card-body">
                                <div id="chargeContainer"></div>
                                <button type="button" class="mt-2 btn btn-warning btn-sm rounded-pill fw-bold text-dark w-100" id="addCharge">
                                    <i class="bi bi-plus-lg me-1"></i> Tambah Biaya Ekstra
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- CARD 4B: POTONGAN BIAYA --}}
                    <div class="col-md-6">
                        <div class="border-0 border-opacity-50 shadow-sm card rounded-4 border-danger h-100">
                            <div class="py-3 bg-white card-header border-bottom-0 rounded-top-4">
                                <h6 class="mb-0 fw-bold text-danger"><i class="bi bi-dash-circle-dotted me-2"></i>Potongan Tambahan</h6>
                            </div>
                            <div class="p-3 pt-0 card-body">
                                <div id="discountContainer"></div>
                                <button type="button" class="mt-2 btn btn-outline-danger btn-sm rounded-pill fw-bold w-100" id="addDiscount">
                                    <i class="bi bi-plus-lg me-1"></i> Tambah Potongan
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- CARD 5: LAMPIRAN DOKUMEN --}}
                <div class="mb-4 border-0 shadow-sm card rounded-4">
                    <div class="py-3 bg-white card-header border-bottom-0 rounded-top-4">
                        <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-paperclip me-2"></i>Lampiran Dokumen</h6>
                    </div>
                    <div class="p-4 pt-0 card-body">
                        <div id="attachmentContainer">
                            <div class="mb-2 input-group">
                                <input type="file" name="attachments[]" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                                <button class="btn btn-outline-danger remove-file" type="button"><i class="bi bi-x-lg"></i></button>
                            </div>
                        </div>
                        <button type="button" class="mt-1 btn btn-outline-secondary btn-sm rounded-pill" id="addFile">
                            <i class="bi bi-plus-lg me-1"></i> Tambah File
                        </button>
                        <div class="mt-2 form-text small"><i class="bi bi-info-circle me-1"></i>Mendukung file PDF, JPG, PNG (Max 5MB per file).</div>
                    </div>
                </div>
            </div>

            {{-- KOLOM KANAN: RINGKASAN HARGA (STICKY) --}}
            <div class="col-lg-4">
                <div class="border-0 shadow-sm card rounded-4 sticky-top" style="top: 20px;">
                    <div class="p-4 card-body">
                        <h6 class="mb-4 fw-bold text-secondary small text-uppercase"><i class="bi bi-calculator me-2"></i>Ringkasan Tagihan</h6>

                        <div class="mb-3 d-flex justify-content-between small">
                            <span class="text-muted">Subtotal Item</span>
                            <span class="fw-bold text-dark"><span class="curr-symbol-display">Rp</span> <span id="display_subtotal">0</span></span>
                        </div>
                        <div class="mb-3 d-flex justify-content-between small">
                            <span class="text-muted">Diskon Per-Item</span>
                            <span class="fw-bold text-danger">- <span class="curr-symbol-display">Rp</span> <span id="display_item_discount">0</span></span>
                        </div>
                        <div class="pb-3 mb-3 d-flex justify-content-between small border-bottom border-secondary">
                            <span class="text-muted">Total Pajak Item</span>
                            <span class="fw-bold text-info">+ <span class="curr-symbol-display">Rp</span> <span id="display_tax">0</span></span>
                        </div>

                        {{-- Total Ekstra --}}
                        <div class="mb-3 d-flex justify-content-between small">
                            <span class="text-muted fw-bold">Biaya Tambahan</span>
                            <span class="fw-bold text-warning-emphasis">+ <span class="curr-symbol-display">Rp</span> <span id="display_charges">0</span></span>
                        </div>
                        <div class="mb-4 d-flex justify-content-between small">
                            <span class="text-muted fw-bold">Potongan Ekstra</span>
                            <span class="fw-bold text-danger">- <span class="curr-symbol-display">Rp</span> <span id="display_extra_discounts">0</span></span>
                        </div>

                        <div class="p-3 mb-4 text-center border bg-primary bg-opacity-10 border-primary-subtle rounded-4">
                            <div class="mb-1 small text-primary fw-bold text-uppercase">TOTAL TAGIHAN BERSIH</div>
                            <h3 class="mb-0 fw-bold text-primary"><span class="curr-symbol-display">Rp</span> <span id="display_grand_total">0</span></h3>
                        </div>

                        <button type="button" id="btnSubmitForm" class="py-3 shadow-sm btn btn-primary w-100 rounded-pill fw-bold d-flex align-items-center justify-content-center">
                            <i class="bi bi-save2 fs-5 me-2"></i> Simpan Tagihan
                        </button>
                        <a href="{{ route('bills.index') }}" class="mt-2 btn btn-light w-100 rounded-pill fw-bold text-muted">Batal</a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

{{-- TEMPLATE CLONING --}}
<div id="hiddenSelectTemplate" style="display: none;">
    <select class="mb-2 form-select select2-item-template" required>
        <option value="">-- Pilih Item Opex --</option>
        @foreach($opexItems as $opx)
            <option value="{{ $opx->name }}">{{ $opx->code }} - {{ $opx->name }}</option>
        @endforeach
    </select>
</div>
<div id="hiddenChargeTemplate" style="display: none;">
    <select class="mb-2 form-select select2-charge-template" required>
        <option value="">-- Pilih Master Biaya --</option>
        @foreach($chargeTypes as $charge)
            <option value="{{ $charge->id }}">{{ $charge->name }}</option>
        @endforeach
    </select>
</div>
<div id="hiddenDiscountTemplate" style="display: none;">
    <select class="mb-2 form-select select2-discount-template" required>
        <option value="">-- Pilih Master Potongan --</option>
        @foreach($discountTypes as $disc)
            <option value="{{ $disc->id }}">{{ $disc->name }}</option>
        @endforeach
    </select>
</div>

@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {

    function initSelect2() {
        $('.select2-single').select2({ theme: 'bootstrap-5', width: '100%' });
        $('.select2-item').select2({ theme: 'bootstrap-5', width: '100%' });
        $('.select2-vendor').select2({ theme: 'bootstrap-5', width: '100%', tags: true, placeholder: "Cari atau ketik Vendor..." });
    }
    initSelect2();

    function formatNumber(num) { return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, "."); }
    function unformatNumber(str) { return parseInt(str.toString().replace(/[^0-9]/g, '')) || 0; }

    // 🔥 Update pemicu KeyUp untuk memproses Pajak & Diskon 🔥
    $(document).on('keyup', '.price-display, .disc-val-display, .tax-val-display, .charge-display, .ext-disc-display', function() {
        let isPercent = false;

        if($(this).hasClass('disc-val-display')) {
            isPercent = $(this).closest('.input-group').find('.disc-type').val() === 'percent';
        } else if($(this).hasClass('tax-val-display')) {
            isPercent = $(this).closest('.input-group').find('.tax-type').val() === 'percent';
        } else if($(this).attr('id') === 'global_tax_val') {
            isPercent = $('#global_tax_type').val() === 'percent';
        }

        let val = unformatNumber($(this).val());
        if(isPercent && val > 100) val = 100;

        $(this).val(isPercent ? val : formatNumber(val));
        $(this).siblings('input[type="hidden"]').val(val);
        calculate();
    });

    $(document).on('change', '.disc-type, .tax-type, #global_tax_type', function() {
        let displayInput = $(this).closest('.input-group').find('input[type="text"]');
        let hiddenInput = $(this).closest('.input-group').find('input[type="hidden"]');
        displayInput.val(0); hiddenInput.val(0);
        calculate();
    });

    $('#addItem').click(function() {
        const container = document.getElementById('itemContainer');
        const index = container.querySelectorAll('.item-row').length;
        const templateSelect = $('#hiddenSelectTemplate').html();

        const tr = document.createElement('tr');
        tr.className = 'item-row border-bottom';
        tr.innerHTML = `
            <td class="pt-3">
                ${templateSelect}
                <textarea name="items[${index}][description]" class="mt-2 form-control form-control-sm" rows="1" placeholder="Catatan..."></textarea>
            </td>
            <td class="pt-3"><input type="number" name="items[${index}][qty]" class="text-center form-control form-control-sm qty" value="1" min="1"></td>
            <td class="pt-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text curr-symbol">Rp</span>
                    <input type="text" class="form-control price-display text-end" value="0">
                    <input type="hidden" name="items[${index}][price]" class="price-real" value="0">
                </div>
            </td>
            <td class="pt-3">
                <div class="mb-1 input-group input-group-sm">
                    <span class="input-group-text bg-info bg-opacity-10 text-info fw-bold" style="font-size: 0.7rem;">+Pajak</span>
                    <input type="text" class="form-control tax-val-display" value="0">
                    <input type="hidden" name="items[${index}][tax_value]" class="tax-val-real" value="0">
                    <select name="items[${index}][tax_type]" class="form-select tax-type" style="max-width: 60px;">
                        <option value="fixed">Rp</option><option value="percent" selected>%</option>
                    </select>
                </div>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-danger bg-opacity-10 text-danger fw-bold" style="font-size: 0.7rem;">-Disc&nbsp;&nbsp;</span>
                    <input type="text" class="form-control disc-val-display" value="0">
                    <input type="hidden" name="items[${index}][discount_value]" class="disc-val-real" value="0">
                    <select name="items[${index}][discount_type]" class="form-select disc-type" style="max-width: 60px;">
                        <option value="fixed">Rp</option><option value="percent" selected>%</option>
                    </select>
                </div>
            </td>
            <td class="pt-3 text-end"><button type="button" class="btn btn-outline-danger btn-sm remove-item rounded-circle" title="Hapus Baris"><i class="bi bi-trash"></i></button></td>
        `;
        container.appendChild(tr);
        $(tr).find('.select2-item-template').removeClass('select2-item-template').addClass('select2-item').attr('name', `items[${index}][name]`);
        $(tr).find('.select2-item').select2({ theme: 'bootstrap-5', width: '100%' });
        updateSymbols();
    });

    $('#addCharge').click(function() {
        const container = document.getElementById('chargeContainer');
        const index = container.querySelectorAll('.charge-row').length;
        const templateSelect = $('#hiddenChargeTemplate').html();

        const div = document.createElement('div');
        div.className = 'charge-row mb-3 p-2 border rounded bg-white';
        div.innerHTML = `
            ${templateSelect}
            <div class="mb-2 input-group input-group-sm">
                <span class="input-group-text curr-symbol">Rp</span>
                <input type="text" class="form-control charge-display text-end" value="0">
                <input type="hidden" name="charges[${index}][amount]" class="charge-real" value="0">
            </div>
            <div class="d-flex">
                <input type="text" name="charges[${index}][note]" class="form-control form-control-sm me-2" placeholder="Catatan opsional...">
                <button type="button" class="btn btn-danger btn-sm remove-extra"><i class="bi bi-trash"></i></button>
            </div>
        `;
        container.appendChild(div);
        $(div).find('.select2-charge-template').removeClass('select2-charge-template').addClass('select2-charge').attr('name', `charges[${index}][charge_type_id]`);
        $(div).find('.select2-charge').select2({ theme: 'bootstrap-5', width: '100%' });
        updateSymbols();
    });

    $('#addDiscount').click(function() {
        const container = document.getElementById('discountContainer');
        const index = container.querySelectorAll('.discount-row').length;
        const templateSelect = $('#hiddenDiscountTemplate').html();

        const div = document.createElement('div');
        div.className = 'discount-row mb-3 p-2 border border-danger border-opacity-50 rounded bg-white';
        div.innerHTML = `
            ${templateSelect}
            <div class="mb-2 input-group input-group-sm">
                <span class="text-white input-group-text bg-danger curr-symbol">Rp</span>
                <input type="text" class="form-control ext-disc-display text-end" value="0">
                <input type="hidden" name="discounts[${index}][amount]" class="ext-disc-real" value="0">
            </div>
            <div class="d-flex">
                <input type="text" name="discounts[${index}][note]" class="form-control form-control-sm me-2" placeholder="Catatan potongan...">
                <button type="button" class="btn btn-danger btn-sm remove-extra"><i class="bi bi-trash"></i></button>
            </div>
        `;
        container.appendChild(div);
        $(div).find('.select2-discount-template').removeClass('select2-discount-template').addClass('select2-discount').attr('name', `discounts[${index}][discount_type_id]`);
        $(div).find('.select2-discount').select2({ theme: 'bootstrap-5', width: '100%' });
        updateSymbols();
    });

    $(document).on('click', '.remove-item', function() {
        if ($('.item-row').length > 1) { $(this).closest('.item-row').remove(); calculate(); }
        else { Swal.fire('Oops!', 'Minimal harus 1 item.', 'warning'); }
    });
    $(document).on('click', '.remove-extra', function() {
        $(this).closest('.charge-row, .discount-row').remove(); calculate();
    });

    $('#addFile').click(function() {
        $('#attachmentContainer').append(`<div class="mb-2 input-group"><input type="file" name="attachments[]" class="form-control" accept=".pdf,.jpg,.jpeg,.png"><button class="btn btn-outline-danger remove-file" type="button"><i class="bi bi-x-lg"></i></button></div>`);
    });
    $(document).on('click', '.remove-file', function() { $(this).closest('.input-group').remove(); });

    $('#currency_select').change(updateSymbols);
    function updateSymbols() {
        const symbol = $('#currency_select option:selected').data('symbol');
        $('.curr-symbol').text(symbol); $('.curr-symbol-display').text(symbol);
        $('.disc-type option[value="fixed"], .tax-type option[value="fixed"], #global_tax_type option[value="fixed"]').text(symbol);
    }

    $('#is_recurring').change(function() {
        if ($(this).val() == '1') $('#recurring_setup').removeClass('d-none');
        else $('#recurring_setup').addClass('d-none');
    });

    // 🔥 9. KALKULATOR UTAMA DIPERBARUI 🔥
    function calculate() {
        let totalGross = 0, totalItemDisc = 0, totalTax = 0, totalCharge = 0, totalExtDiscount = 0;

        $('.item-row').each(function() {
            const qty = parseFloat($(this).find('.qty').val()) || 0;
            const price = parseFloat($(this).find('.price-real').val()) || 0;
            const gross = qty * price;

            // Diskon
            const discVal = parseFloat($(this).find('.disc-val-real').val()) || 0;
            const discType = $(this).find('.disc-type').val();
            const itemDisc = (discType === 'fixed') ? discVal : (gross * discVal / 100);
            const dpp = gross - itemDisc;

            // Pajak (Baru) -> Pajak biasanya dihitung dari DPP (setelah diskon)
            const taxVal = parseFloat($(this).find('.tax-val-real').val()) || 0;
            const taxType = $(this).find('.tax-type').val();
            const itemTax = (taxType === 'fixed') ? taxVal : (dpp * taxVal / 100);

            totalGross += gross; totalItemDisc += itemDisc; totalTax += itemTax;
        });

        $('.charge-row').each(function() { totalCharge += parseFloat($(this).find('.charge-real').val()) || 0; });
        $('.discount-row').each(function() { totalExtDiscount += parseFloat($(this).find('.ext-disc-real').val()) || 0; });

        const subtotal = totalGross - totalItemDisc + totalTax;
        let grandTotal = subtotal + totalCharge - totalExtDiscount;
        if(grandTotal < 0) grandTotal = 0;

        $('#display_subtotal').text(formatNumber(totalGross));
        $('#display_item_discount').text(formatNumber(totalItemDisc));
        $('#display_tax').text(formatNumber(totalTax));
        $('#display_charges').text(formatNumber(totalCharge));
        $('#display_extra_discounts').text(formatNumber(totalExtDiscount));
        $('#display_grand_total').text(formatNumber(grandTotal));
    }

    $(document).on('input', '.qty', calculate);

    // 🔥 10. TERAPKAN PAJAK MASSAL (BARU) 🔥
    $('#btnApplyGlobalTax').click(function() {
        let globalVal = $('#global_tax_val').val();
        let globalRealVal = unformatNumber(globalVal);
        let globalType = $('#global_tax_type').val();

        if($('.item-row').length === 0) return;

        $('.tax-val-display').val(globalVal);
        $('.tax-val-real').val(globalRealVal);
        $('.tax-type').val(globalType);

        calculate();

        Swal.fire({
            toast: true, position: 'top-end', icon: 'success',
            title: 'Pajak berhasil diterapkan ke semua baris!',
            showConfirmButton: false, timer: 2000
        });
    });

    $('#btnSubmitForm').click(function() {
        const form = document.getElementById('billForm');
        if (!form.checkValidity()) { form.reportValidity(); return; }

        Swal.fire({
            title: 'Simpan Tagihan?', text: "Pastikan nominal sudah sesuai.", icon: 'question',
            showCancelButton: true, confirmButtonColor: '#0d6efd', cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Simpan!', reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span> Menyimpan...');
                Swal.fire({ title: 'Memproses Data...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
                form.submit();
            }
        });
    });

    updateSymbols();
});
</script>
@endpush
