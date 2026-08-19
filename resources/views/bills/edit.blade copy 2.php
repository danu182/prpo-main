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
<div class="container-fluid py-2">

    {{-- HEADER --}}
    <div class="mb-4 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <a href="{{ route('bills.show', $bill->bill_number) }}" class="bg-white border shadow-sm btn btn-white rounded-circle me-3 text-warning hover-light" style="width: 45px; height: 45px; display: flex; align-items: center; justify-content: center;">
                <i class="bi bi-arrow-left fs-5"></i>
            </a>
            <div>
                <h4 class="mb-0 fw-bold text-dark">Edit Tagihan: <span class="text-warning">{{ $bill->bill_number }}</span></h4>
                <div class="text-muted small">Update pengeluaran operasional perusahaan</div>
            </div>
        </div>
    </div>

    @if ($errors->any())
        <div class="mb-4 border-0 shadow-sm alert alert-danger alert-dismissible fade show rounded-4">
            <div class="mb-1 fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i>Terdapat Kesalahan:</div>
            <ul class="mb-0 small">
                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form action="{{ route('bills.update', $bill->bill_number) }}" method="POST" enctype="multipart/form-data" id="billForm">
        @csrf
        @method('PUT')

        <div class="row g-4">
            {{-- KOLOM KIRI: FORM UTAMA --}}
            <div class="col-lg-8">

                {{-- CARD 1: INFORMASI UMUM --}}
                <div class="mb-4 border-0 shadow-sm card rounded-4">
                    <div class="py-3 bg-white card-header border-bottom-0 rounded-top-4">
                        <h6 class="mb-0 fw-bold text-warning-emphasis"><i class="bi bi-info-circle me-2"></i>Informasi Tagihan</h6>
                    </div>
                    <div class="p-4 pt-2 card-body">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label fw-bold small text-muted text-uppercase">PT Penanggung Jawab <span class="text-danger">*</span></label>
                                <select name="paid_by_company_id" class="form-select select2-single" required>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}" {{ $bill->company_id == $company->id ? 'selected' : '' }}>
                                            {{ $company->code ? '['.$company->code.'] ' : '' }}{{ $company->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-bold small text-muted text-uppercase">Mata Uang <span class="text-danger">*</span></label>
                                <select name="currency_id" id="currency_select" class="form-select select2-single" required>
                                    @foreach($currencies as $curr)
                                        <option value="{{ $curr->id }}" data-symbol="{{ $curr->symbol }}" {{ $bill->currency == $curr->code ? 'selected' : '' }}>
                                            {{ $curr->code }} - {{ $curr->name }} ({{ $curr->symbol }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted text-uppercase">Tanggal Tagihan <span class="text-danger">*</span></label>
                                <input type="date" name="bill_date" class="form-control" value="{{ date('Y-m-d', strtotime($bill->invoice_date)) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted text-uppercase">Jatuh Tempo <span class="text-danger">*</span></label>
                                <input type="date" name="due_date" class="form-control" value="{{ date('Y-m-d', strtotime($bill->due_date)) }}" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted text-uppercase">Nama Vendor / Supplier <span class="text-danger">*</span></label>
                                <select name="vendor_name" class="form-select select2-vendor" required>
                                    <option value="{{ $bill->vendor_name }}" selected>{{ $bill->vendor_name }}</option>
                                    @foreach($vendors as $vendor)
                                        @if($vendor->name != $bill->vendor_name)
                                            <option value="{{ $vendor->name }}">{{ $vendor->name }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted text-uppercase">No. Invoice Vendor <span class="text-muted text-lowercase">(Opsional)</span></label>
                                <input type="text" name="vendor_invoice_number" class="form-control" value="{{ old('vendor_invoice_number', $bill->vendor_invoice_number) }}" placeholder="Contoh: INV-2026-001">
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-bold small text-muted text-uppercase">Catatan Tagihan</label>
                                <textarea name="note" class="form-control rounded-3" rows="2">{{ $bill->description }}</textarea>
                            </div>

                            {{-- ========================================================= --}}
                            {{-- 🔥 TAMBAHAN BARU: DROPDOWN JALUR TIKUS (OVERRIDE) 🔥 --}}
                            {{-- ========================================================= --}}
                            <div class="pt-3 mt-3 col-md-12 border-top">
                                <label class="form-label fw-bold small text-warning-emphasis text-uppercase">
                                    <i class="bi bi-shuffle me-1"></i> Pilih Jalur Persetujuan Khusus (Opsional)
                                </label>
                                <select name="custom_workflow_id" class="form-select select2-single border-warning-subtle bg-warning bg-opacity-10">
                                    <option value="">-- Ikuti Standar Departemen (Default) --</option>
                                    @if(isset($customWorkflows) && count($customWorkflows) > 0)
                                        @foreach($customWorkflows as $cw)
                                            <option value="{{ $cw->id }}" {{ (isset($selectedWorkflowId) && $selectedWorkflowId == $cw->id) ? 'selected' : '' }}>
                                                {{ $cw->name }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                                <div class="mt-1 form-text small text-muted">
                                    <i class="bi bi-info-circle me-1"></i>Biarkan kosong jika ingin menggunakan rute standar departemen.
                                    <br><span class="text-danger fw-bold"><i class="bi bi-exclamation-triangle"></i> Perhatian:</span> Mengubah formasi di sini akan me-reset seluruh persetujuan yang sudah berjalan!
                                </div>
                            </div>
                            {{-- ========================================================= --}}

                        </div>
                    </div>
                </div>

                {{-- CARD 2: RECURRING --}}
                <div class="mb-4 border border-0 shadow-sm card rounded-4 bg-light border-info-subtle">
                    <div class="card-body">
                        <h6 class="mb-3 fw-bold text-dark"><i class="bi bi-arrow-repeat me-2 text-info"></i>Pengaturan Berulang</h6>
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <select name="is_recurring" id="is_recurring" class="form-select">
                                    <option value="0" {{ !$bill->is_recurring ? 'selected' : '' }}>Sekali Saja</option>
                                    <option value="1" {{ $bill->is_recurring ? 'selected' : '' }}>Berulang (Recurring)</option>
                                </select>
                            </div>
                            <div id="recurring_setup" class="col-md-8 {{ !$bill->is_recurring ? 'd-none' : '' }}">
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <input type="number" name="recurring_interval" class="form-control" value="{{ $bill->recurring_interval ?? 1 }}" min="1">
                                    </div>
                                    <div class="col-md-8">
                                        <select name="recurring_period" class="form-select">
                                            <option value="days" {{ $bill->recurring_period == 'days' ? 'selected' : '' }}>Hari</option>
                                            <option value="weeks" {{ $bill->recurring_period == 'weeks' ? 'selected' : '' }}>Minggu</option>
                                            <option value="months" {{ $bill->recurring_period == 'months' ? 'selected' : '' }}>Bulan</option>
                                            <option value="years" {{ $bill->recurring_period == 'years' ? 'selected' : '' }}>Tahun</option>
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
                        <h6 class="mb-0 fw-bold text-warning-emphasis"><i class="bi bi-list-check me-2"></i>Rincian Item Jasa / Opex</h6>
                        <div class="input-group input-group-sm" style="width: 320px;">
                            <span class="input-group-text bg-light border-warning-subtle" style="font-size: 0.75rem;">Pajak Semua:</span>
                            <input type="text" id="global_tax_val" class="form-control price-display border-warning-subtle" value="0">
                            <select id="global_tax_type" class="form-select border-warning-subtle" style="max-width: 60px;">
                                <option value="fixed">Rp</option>
                                <option value="percent" selected>%</option>
                            </select>
                            <button class="btn btn-warning" type="button" id="btnApplyGlobalTax" style="font-size: 0.75rem;">
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
                                @foreach($bill->items as $index => $item)
                                <tr class="item-row border-bottom">
                                    <td class="pt-3">
                                        <select name="items[{{ $index }}][name]" class="mb-2 form-select select2-item" required>
                                            <option value="{{ $item->name }}" selected>{{ $item->name }}</option>
                                            @foreach($opexItems as $opx)
                                                @if($opx->name != $item->name)
                                                    <option value="{{ $opx->name }}">{{ $opx->code }} - {{ $opx->name }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                        <textarea name="items[{{ $index }}][description]" class="form-control form-control-sm" rows="1">{{ $item->description }}</textarea>
                                    </td>
                                    <td class="pt-3">
                                        <input type="number" name="items[{{ $index }}][qty]" class="text-center form-control form-control-sm qty" value="{{ (int)$item->qty }}" min="1">
                                    </td>
                                    <td class="pt-3">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text curr-symbol">Rp</span>
                                            <input type="text" class="form-control price-display text-end" value="{{ number_format($item->price,0,'','') }}">
                                            <input type="hidden" name="items[{{ $index }}][price]" class="price-real" value="{{ (int)$item->price }}">
                                        </div>
                                    </td>
                                    <td class="pt-3">
                                        {{-- 🔥 INPUT PAJAK BARU (Bisa Nominal/Persen) 🔥 --}}
                                        <div class="mb-1 input-group input-group-sm">
                                            <span class="input-group-text bg-info bg-opacity-10 text-info fw-bold" style="font-size: 0.7rem;">+Pajak</span>
                                            <input type="text" class="form-control tax-val-display" value="{{ number_format($item->tax_value ?? 0,0,'','') }}">
                                            <input type="hidden" name="items[{{ $index }}][tax_value]" class="tax-val-real" value="{{ (int)($item->tax_value ?? 0) }}">
                                            <select name="items[{{ $index }}][tax_type]" class="form-select tax-type" style="max-width: 60px;">
                                                <option value="fixed" {{ ($item->tax_type ?? 'percent') == 'fixed' ? 'selected' : '' }}>Rp</option>
                                                <option value="percent" {{ ($item->tax_type ?? 'percent') == 'percent' ? 'selected' : '' }}>%</option>
                                            </select>
                                        </div>

                                        {{-- Input Diskon --}}
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-danger bg-opacity-10 text-danger fw-bold" style="font-size: 0.7rem;">-Disc&nbsp;&nbsp;</span>
                                            <input type="text" class="form-control disc-val-display" value="{{ number_format($item->discount_value,0,'','') }}">
                                            <input type="hidden" name="items[{{ $index }}][discount_value]" class="disc-val-real" value="{{ (int)$item->discount_value }}">
                                            <select name="items[{{ $index }}][discount_type]" class="form-select disc-type" style="max-width: 60px;">
                                                <option value="fixed" {{ $item->discount_type == 'fixed' ? 'selected' : '' }}>Rp</option>
                                                <option value="percent" {{ $item->discount_type == 'percent' ? 'selected' : '' }}>%</option>
                                            </select>
                                        </div>
                                    </td>
                                    <td class="pt-3 text-end">
                                        <button type="button" class="btn btn-outline-danger btn-sm remove-item rounded-circle"><i class="bi bi-trash"></i></button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <button type="button" class="px-3 mt-2 btn btn-warning btn-sm rounded-pill fw-bold" id="addItem">
                            <i class="bi bi-plus-lg me-1"></i> Tambah Baris
                        </button>
                    </div>
                </div>

                {{-- PEMBAGIAN 2 KOLOM CHARGE & DISCOUNT --}}
                <div class="mb-4 row g-4">
                    {{-- BIAYA TAMBAHAN (CHARGES) --}}
                    <div class="col-md-6">
                        <div class="border-0 border-opacity-50 shadow-sm card rounded-4 border-warning h-100">
                            <div class="py-3 bg-white card-header border-bottom-0 rounded-top-4">
                                <h6 class="mb-0 fw-bold text-warning-emphasis"><i class="bi bi-plus-circle-dotted me-2"></i>Biaya Tambahan</h6>
                            </div>
                            <div class="p-3 pt-0 card-body">
                                <div id="chargeContainer">
                                    @foreach($bill->charges as $index => $charge)
                                        <div class="p-2 mb-3 bg-white border rounded charge-row">
                                            <select name="charges[{{ $index }}][charge_type_id]" class="mb-2 form-select select2-charge" required>
                                                @foreach($chargeTypes as $ct)
                                                    <option value="{{ $ct->id }}" {{ $charge->charge_type_id == $ct->id ? 'selected' : '' }}>{{ $ct->name }}</option>
                                                @endforeach
                                            </select>
                                            <div class="mb-2 input-group input-group-sm">
                                                <span class="input-group-text curr-symbol">Rp</span>
                                                <input type="text" class="form-control charge-display text-end" value="{{ number_format($charge->amount,0,'','') }}">
                                                <input type="hidden" name="charges[{{ $index }}][amount]" class="charge-real" value="{{ (int)$charge->amount }}">
                                            </div>
                                            <div class="d-flex">
                                                <input type="text" name="charges[{{ $index }}][note]" class="form-control form-control-sm me-2" value="{{ $charge->note }}">
                                                <button type="button" class="btn btn-danger btn-sm remove-extra"><i class="bi bi-trash"></i></button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <button type="button" class="mt-2 btn btn-warning btn-sm rounded-pill fw-bold text-dark w-100" id="addCharge">
                                    <i class="bi bi-plus-lg me-1"></i> Tambah Biaya Ekstra
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- POTONGAN BIAYA (DISCOUNTS) --}}
                    <div class="col-md-6">
                        <div class="border-0 border-opacity-50 shadow-sm card rounded-4 border-danger h-100">
                            <div class="py-3 bg-white card-header border-bottom-0 rounded-top-4">
                                <h6 class="mb-0 fw-bold text-danger"><i class="bi bi-dash-circle-dotted me-2"></i>Potongan Tambahan</h6>
                            </div>
                            <div class="p-3 pt-0 card-body">
                                <div id="discountContainer">
                                    @foreach($bill->discounts as $index => $discount)
                                        <div class="p-2 mb-3 bg-white border border-opacity-50 rounded discount-row border-danger">
                                            <select name="discounts[{{ $index }}][discount_type_id]" class="mb-2 form-select select2-discount" required>
                                                @foreach($discountTypes as $dt)
                                                    <option value="{{ $dt->id }}" {{ $discount->discount_type_id == $dt->id ? 'selected' : '' }}>{{ $dt->name }}</option>
                                                @endforeach
                                            </select>
                                            <div class="mb-2 input-group input-group-sm">
                                                <span class="text-white input-group-text bg-danger curr-symbol">Rp</span>
                                                <input type="text" class="form-control ext-disc-display text-end" value="{{ number_format($discount->amount,0,'','') }}">
                                                <input type="hidden" name="discounts[{{ $index }}][amount]" class="ext-disc-real" value="{{ (int)$discount->amount }}">
                                            </div>
                                            <div class="d-flex">
                                                <input type="text" name="discounts[{{ $index }}][note]" class="form-control form-control-sm me-2" value="{{ $discount->note }}">
                                                <button type="button" class="btn btn-danger btn-sm remove-extra"><i class="bi bi-trash"></i></button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <button type="button" class="mt-2 btn btn-outline-danger btn-sm rounded-pill fw-bold w-100" id="addDiscount">
                                    <i class="bi bi-plus-lg me-1"></i> Tambah Potongan
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- CARD LAMPIRAN --}}
                <div class="mb-4 border-0 shadow-sm card rounded-4">
                    <div class="py-3 bg-white card-header border-bottom-0 rounded-top-4">
                        <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-paperclip me-2"></i>Lampiran Dokumen</h6>
                    </div>
                    <div class="p-4 pt-0 card-body">
                        @if($attachments->count() > 0)
                            <div class="p-3 mb-4 border alert alert-light rounded-4">
                                <label class="mb-3 small text-muted fw-bold d-block"><i class="bi bi-info-circle me-1"></i>File Saat Ini (Centang kotak merah untuk menghapus):</label>
                                <div class="row g-3">
                                    @foreach($attachments as $file)
                                        <div class="col-md-6">
                                            <div class="p-2 bg-white border border-opacity-25 shadow-sm border-secondary rounded-4 d-flex align-items-center">
                                                <div class="form-check ms-2 me-3">
                                                    <input class="form-check-input border-danger" type="checkbox" name="delete_media[]" value="{{ $file->id }}" id="media_{{ $file->id }}" style="transform: scale(1.3); cursor: pointer;">
                                                </div>
                                                <label class="align-middle form-check-label w-100" for="media_{{ $file->id }}" style="cursor: pointer;">
                                                    <a href="{{ asset('storage/' . $file->file_path) }}" target="_blank" class="text-decoration-none fw-bold text-dark text-truncate d-block" style="max-width: 200px;">
                                                        @if(Str::endsWith(strtolower($file->file_name), ['.jpg', '.jpeg', '.png']))
                                                            <i class="bi bi-file-image fs-5 me-1 text-primary"></i>
                                                        @elseif(Str::endsWith(strtolower($file->file_name), ['.pdf']))
                                                            <i class="bi bi-file-pdf fs-5 me-1 text-danger"></i>
                                                        @else
                                                            <i class="bi bi-file-earmark-text fs-5 me-1 text-secondary"></i>
                                                        @endif
                                                        {{ $file->file_name }}
                                                    </a>
                                                    <small class="mt-1 d-block text-danger fw-semibold"><i class="bi bi-trash"></i> Hapus file ini</small>
                                                </label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div id="attachmentContainer"></div>
                        <button type="button" class="mt-2 btn btn-outline-primary btn-sm rounded-pill fw-bold" id="addFile">
                            <i class="bi bi-plus-lg me-1"></i> Upload File Baru
                        </button>
                    </div>
                </div>
            </div>

            {{-- KOLOM KANAN: RINGKASAN (STICKY) --}}
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
                        <div class="mb-3 d-flex justify-content-between small">
                            <span class="text-muted fw-bold">Biaya Tambahan</span>
                            <span class="fw-bold text-warning-emphasis">+ <span class="curr-symbol-display">Rp</span> <span id="display_charges">0</span></span>
                        </div>
                        <div class="mb-4 d-flex justify-content-between small">
                            <span class="text-muted fw-bold">Potongan Ekstra</span>
                            <span class="fw-bold text-danger">- <span class="curr-symbol-display">Rp</span> <span id="display_extra_discounts">0</span></span>
                        </div>
                        <div class="p-3 mb-4 text-center border bg-warning bg-opacity-10 border-warning-subtle rounded-4">
                            <div class="mb-1 small text-warning-emphasis fw-bold text-uppercase">TOTAL TAGIHAN BERSIH</div>
                            <h3 class="mb-0 fw-bold text-dark"><span class="curr-symbol-display">Rp</span> <span id="display_grand_total">0</span></h3>
                        </div>

                        <button type="button" id="btnSubmitForm" class="py-3 shadow-sm btn btn-warning w-100 rounded-pill fw-bold text-dark d-flex align-items-center justify-content-center">
                            <i class="bi bi-save2 fs-5 me-2"></i> Update Tagihan
                        </button>
                        <a href="{{ route('bills.show', $bill->bill_number) }}" class="mt-2 btn btn-light w-100 rounded-pill fw-bold text-muted">Batal</a>
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
        @foreach($opexItems as $opx) <option value="{{ $opx->name }}">{{ $opx->code }} - {{ $opx->name }}</option> @endforeach
    </select>
</div>
<div id="hiddenChargeTemplate" style="display: none;">
    <select class="mb-2 form-select select2-charge-template" required>
        <option value="">-- Pilih Master Biaya --</option>
        @foreach($chargeTypes as $charge) <option value="{{ $charge->id }}">{{ $charge->name }}</option> @endforeach
    </select>
</div>
<div id="hiddenDiscountTemplate" style="display: none;">
    <select class="mb-2 form-select select2-discount-template" required>
        <option value="">-- Pilih Master Potongan --</option>
        @foreach($discountTypes as $disc) <option value="{{ $disc->id }}">{{ $disc->name }}</option> @endforeach
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
        $('.select2-single, .select2-item, .select2-charge, .select2-discount').select2({ theme: 'bootstrap-5', width: '100%' });
        $('.select2-vendor').select2({ theme: 'bootstrap-5', width: '100%', tags: true });
    }
    initSelect2();

    function formatNumber(num) { return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, "."); }
    function unformatNumber(str) { return parseInt(str.toString().replace(/[^0-9]/g, '')) || 0; }

    $('.price-display, .disc-val-display, .tax-val-display, .charge-display, .ext-disc-display').each(function() {
        let val = unformatNumber($(this).val());
        $(this).val(formatNumber(val));
    });

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
        displayInput.val(0); hiddenInput.val(0); calculate();
    });

    $('#addItem').click(function() {
        const container = document.getElementById('itemContainer');
        const index = new Date().getTime();
        const templateSelect = $('#hiddenSelectTemplate').html();
        const tr = document.createElement('tr');
        tr.className = 'item-row border-bottom';
        tr.innerHTML = `
            <td class="pt-3">${templateSelect}<textarea name="items[${index}][description]" class="mt-2 form-control form-control-sm" rows="1"></textarea></td>
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
            <td class="pt-3 text-end"><button type="button" class="btn btn-outline-danger btn-sm remove-item rounded-circle"><i class="bi bi-trash"></i></button></td>`;
        container.appendChild(tr);
        $(tr).find('.select2-item-template').removeClass('select2-item-template').addClass('select2-item').attr('name', `items[${index}][name]`);
        $(tr).find('.select2-item').select2({ theme: 'bootstrap-5', width: '100%' });
        updateSymbols();
    });

    $('#addCharge').click(function() {
        const container = document.getElementById('chargeContainer');
        const index = new Date().getTime();
        const templateSelect = $('#hiddenChargeTemplate').html();
        const div = document.createElement('div');
        div.className = 'charge-row mb-3 p-2 border rounded bg-white';
        div.innerHTML = `${templateSelect}<div class="mb-2 input-group input-group-sm"><span class="input-group-text curr-symbol">Rp</span><input type="text" class="form-control charge-display text-end" value="0"><input type="hidden" name="charges[${index}][amount]" class="charge-real" value="0"></div><div class="d-flex"><input type="text" name="charges[${index}][note]" class="form-control form-control-sm me-2"><button type="button" class="btn btn-danger btn-sm remove-extra"><i class="bi bi-trash"></i></button></div>`;
        container.appendChild(div);
        $(div).find('.select2-charge-template').removeClass('select2-charge-template').addClass('select2-charge').attr('name', `charges[${index}][charge_type_id]`);
        $(div).find('.select2-charge').select2({ theme: 'bootstrap-5', width: '100%' });
        updateSymbols();
    });

    $('#addDiscount').click(function() {
        const container = document.getElementById('discountContainer');
        const index = new Date().getTime();
        const templateSelect = $('#hiddenDiscountTemplate').html();
        const div = document.createElement('div');
        div.className = 'discount-row mb-3 p-2 border border-danger border-opacity-50 rounded bg-white';
        div.innerHTML = `${templateSelect}<div class="mb-2 input-group input-group-sm"><span class="text-white input-group-text bg-danger curr-symbol">Rp</span><input type="text" class="form-control ext-disc-display text-end" value="0"><input type="hidden" name="discounts[${index}][amount]" class="ext-disc-real" value="0"></div><div class="d-flex"><input type="text" name="discounts[${index}][note]" class="form-control form-control-sm me-2"><button type="button" class="btn btn-danger btn-sm remove-extra"><i class="bi bi-trash"></i></button></div>`;
        container.appendChild(div);
        $(div).find('.select2-discount-template').removeClass('select2-discount-template').addClass('select2-discount').attr('name', `discounts[${index}][discount_type_id]`);
        $(div).find('.select2-discount').select2({ theme: 'bootstrap-5', width: '100%' });
        updateSymbols();
    });

    $(document).on('click', '.remove-item', function() {
        if ($('.item-row').length > 1) { $(this).closest('.item-row').remove(); calculate(); } else { Swal.fire('Oops!', 'Minimal harus 1 item.', 'warning'); }
    });
    $(document).on('click', '.remove-extra', function() { $(this).closest('.charge-row, .discount-row').remove(); calculate(); });

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

    function calculate() {
        let totalGross = 0, totalItemDisc = 0, totalTax = 0, totalCharge = 0, totalExtDiscount = 0;

        $('.item-row').each(function() {
            const qty = parseFloat($(this).find('.qty').val()) || 0;
            const price = parseFloat($(this).find('.price-real').val()) || 0;
            const gross = qty * price;

            const discVal = parseFloat($(this).find('.disc-val-real').val()) || 0;
            const discType = $(this).find('.disc-type').val();
            const itemDisc = (discType === 'fixed') ? discVal : (gross * discVal / 100);
            const dpp = gross - itemDisc;

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

    $('#btnSubmitForm').click(function(e) {
        e.preventDefault();
        let btn = $(this);
        const form = document.getElementById('billForm');

        Swal.fire({
            title: 'Update Tagihan?', text: "Data lama akan ditimpa dengan rincian yang baru.", icon: 'warning',
            showCancelButton: true, confirmButtonColor: '#ffc107', cancelButtonColor: '#6c757d',
            confirmButtonText: '<span class="text-dark fw-bold">Ya, Update Data!</span>', reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span> Menyimpan...');
                Swal.fire({ title: 'Memproses Perubahan...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
                form.submit();
            }
        });
    });

    updateSymbols();
    calculate();
});
</script>
@endpush
