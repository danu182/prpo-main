@extends('layouts.app')

@push('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<style>
    .select2-container--bootstrap-5 .select2-selection { border-radius: 8px; min-height: 40px; font-size: 0.85rem; border-color: #dee2e6; }
    .select2-container--bootstrap-5.select2-container--focus .select2-selection { border-color: #0d6efd; box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15); }
    .form-input-custom { border: 1px solid #dee2e6; border-radius: 8px; font-size: 0.85rem; min-height: 40px; transition: all 0.2s; }
    .form-input-custom:focus { border-color: #0d6efd; box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15); background-color: #fff; }
    .input-group-modern { border-radius: 8px; overflow: hidden; border: 1px solid #dee2e6; display: flex; }
    .input-group-modern:focus-within { border-color: #0d6efd; box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15); }
    .input-group-modern input, .input-group-modern select, .input-group-modern .input-group-text { border: none !important; background: transparent; }
    .input-group-modern .input-group-text { background-color: #f8f9fa; color: #6c757d; font-weight: 600; border-right: 1px solid #dee2e6 !important; }
    .summary-card { position: sticky; top: 20px; border-radius: 16px; border: 1px solid #e9ecef; }
    .is-invalid { border-color: #dc3545 !important; background-color: #fff8f8 !important; }
    .ck-editor__editable_inline { min-height: 100px; font-size: 0.85rem; }
    input[type=number]::-webkit-inner-spin-button, input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
</style>
@endpush

@section('content')

<form action="{{ route('po.store_direct') }}" method="POST" id="poForm" enctype="multipart/form-data" novalidate>
    @csrf

    <div class="pb-3 mb-4 d-flex justify-content-between align-items-center border-bottom">
        <div>
            <h4 class="mb-1 fw-bolder text-dark"><i class="bi bi-cart-plus me-2 text-primary"></i> Buat PO Langsung (Direct PO)</h4>
            <div class="text-muted small">Penerbitan dokumen Purchase Order tanpa referensi Purchase Request.</div>
        </div>
        <div class="gap-2 d-flex">
            <a href="{{ route('po.index') }}" class="px-4 border btn btn-light fw-bold rounded-pill"><i class="bi bi-x-lg me-1"></i> Batal</a>
            <button type="submit" class="px-4 btn btn-primary fw-bold rounded-pill"><i class="bi bi-send-check me-1"></i> Terbitkan PO</button>
        </div>
    </div>

    @if($errors->any())
        <div class="mb-4 border-0 shadow-sm alert alert-danger rounded-4">
            <div class="mb-1 fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i>Gagal Menyimpan:</div>
            <ul class="mb-0 small">
                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-4">
        {{-- AREA KIRI (FORM UTAMA) --}}
        <div class="col-xl-8 col-lg-7">

            {{-- 1. INFORMASI VENDOR & PENAGIHAN --}}
            <div class="mb-4 border-0 shadow-sm card rounded-4">
                <div class="py-3 bg-white card-header border-bottom d-flex align-items-center">
                    <div class="p-2 bg-primary bg-opacity-10 text-primary rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;">
                        <i class="bi bi-shop"></i>
                    </div>
                    <h6 class="mb-0 fw-bold">Informasi Vendor & Penagihan</h6>
                </div>
                <div class="p-4 card-body">
                    <div class="row g-4">
                        <div class="col-md-5">
                            <label class="form-label small fw-bold text-dark">Pilih Vendor <span class="text-danger">*</span></label>
                            <select name="vendor_id" class="form-select select2-init" required>
                                <option value="">-- Pilih Vendor --</option>
                                @foreach($vendors as $v) <option value="{{ $v->id }}">{{ $v->name }}</option> @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-dark">Mata Uang <span class="text-danger">*</span></label>
                            <select name="currency" id="currencySelect" class="form-select bg-light fw-bold text-primary form-input-custom" required onchange="updateCurrencySymbol()">
                                @foreach($currencies as $curr) <option value="{{ $curr->code }}" {{ $curr->code == 'IDR' ? 'selected' : '' }}>{{ $curr->code }}</option> @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-dark">Tagihan Ke (Bill To) <span class="text-danger">*</span></label>
                            <select name="billing_company_id" id="billToSelect" class="form-select select2-init" required onchange="updateShippingAddress()">
                                <option value="">-- Pilih PT --</option>
                                @foreach($companies as $c) <option value="{{ $c->id }}" data-address="{{ $c->address ?? '' }}">{{ $c->name }}</option> @endforeach
                            </select>
                        </div>

                        {{-- 🔥 KOLOM BARU: INVOICE & REKENING 🔥 --}}
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">No. Invoice (Opsional)</label>
                            <input type="text" name="invoice_number" class="form-control form-input-custom fw-bold text-primary" placeholder="Bisa dikosongkan & diisi menyusul...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">No. Rekening (Account No)</label>
                            <input type="text" name="account_number" class="form-control form-input-custom fw-bold text-success" placeholder="Bisa dikosongkan & diisi menyusul...">
                        </div>

                        <div class="col-12">
                            <label class="mb-1 form-label small fw-bold text-dark">Lokasi Pengiriman (Ship To) <span class="text-danger">*</span></label>
                            <textarea name="shipping_address" id="shippingAddressInput" rows="2" class="form-control form-input-custom bg-light" required>{{ $defaultShippingAddress }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 2. DETAIL PESANAN BARANG --}}
            <div class="mt-5 mb-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bolder text-dark"><i class="bi bi-box-seam me-2 text-primary"></i>Daftar Barang Pesanan</h5>
                <button type="button" class="btn btn-sm btn-primary fw-bold rounded-pill" onclick="addItemRow()"><i class="bi bi-plus-lg me-1"></i>Tambah Barang</button>
            </div>

            <div id="itemsContainer"></div>

            <div class="text-center" id="emptyStateBox">
                <div class="py-5 border border-dashed rounded-4 bg-light border-secondary-subtle">
                    <i class="opacity-50 bi bi-basket text-secondary" style="font-size: 3rem;"></i>
                    <p class="mt-2 mb-0 text-muted fw-bold">Belum ada barang yang ditambahkan.</p>
                    <p class="small text-muted">Klik tombol "Tambah Barang" di atas untuk memulai.</p>
                </div>
            </div>

            {{-- 3. BIAYA LAIN & POTONGAN LAIN --}}
            <div class="mt-4 mb-4 row g-4">
                <div class="col-md-6">
                    <div class="border-0 shadow-sm card rounded-4 h-100">
                        <div class="py-3 bg-white card-header d-flex justify-content-between align-items-center border-bottom">
                            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-truck text-success me-2"></i>Biaya Tambahan (+)</h6>
                            <button type="button" class="border btn btn-sm btn-light text-primary rounded-pill fw-bold" onclick="addChargeRow()"><i class="bi bi-plus-lg"></i> Baris</button>
                        </div>
                        <div class="p-3 card-body bg-light rounded-bottom-4">
                            <table class="table mb-0 table-borderless table-sm"><tbody id="chargesContainer"></tbody></table>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="border-0 shadow-sm card rounded-4 h-100">
                        <div class="py-3 bg-white card-header d-flex justify-content-between align-items-center border-bottom">
                            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-ticket-perforated text-danger me-2"></i>Potongan Voucher (-)</h6>
                            <button type="button" class="border btn btn-sm btn-light text-danger rounded-pill fw-bold" onclick="addExtraDiscRow()"><i class="bi bi-plus-lg"></i> Baris</button>
                        </div>
                        <div class="p-3 card-body bg-light rounded-bottom-4">
                            <table class="table mb-0 table-borderless table-sm"><tbody id="extraDiscContainer"></tbody></table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 4. CATATAN & LAMPIRAN HEADER --}}
            <div class="mb-4 border-0 shadow-sm card rounded-4">
                <div class="p-4 card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark"><i class="bi bi-file-text me-2 text-primary"></i>Catatan Internal / Pesan Utama PO</label>
                            <textarea name="notes" class="form-control form-input-custom bg-light" rows="3" placeholder="Tulis instruksi pengiriman umum, referensi, dll di sini..."></textarea>
                        </div>
                        <div class="pl-4 col-md-6 border-start">
                            <label class="form-label fw-bold text-dark"><i class="bi bi-cloud-upload me-1 text-primary"></i>Upload Dokumen Header PO</label>
                            <div class="mt-1 mb-2 form-text text-muted" style="font-size: 0.75rem;">Opsional: Lampirkan dokumen global seperti Kontrak Kerja atau Penawaran Vendor.</div>
                            <input type="file" name="header_attachments[]" class="form-control form-control-sm border-secondary-subtle" multiple accept=".pdf,.jpg,.jpeg,.png,.xls,.xlsx,.doc,.docx">
                        </div>
                    </div>
                </div>
            </div>

            {{-- 5. WORKFLOW KHUSUS --}}
            <div class="mb-4 border-0 shadow-sm card rounded-4 border-start border-warning">
                <div class="py-3 bg-white card-header border-bottom d-flex align-items-center">
                    <div class="p-2 bg-warning bg-opacity-10 text-warning rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;"><i class="bi bi-diagram-3-fill"></i></div>
                    <h6 class="mb-0 fw-bold">Jalur Persetujuan Khusus (Opsional)</h6>
                </div>
                <div class="p-4 card-body bg-light rounded-bottom-4">
                    <select name="custom_workflow_id" class="form-select select2-init border-warning-subtle fw-bold text-dark">
                        <option value="">-- Ikuti Standar Departemen (Default) --</option>
                        @if(isset($customWorkflows) && count($customWorkflows) > 0)
                            @foreach($customWorkflows as $cw) <option value="{{ $cw->id }}">{{ $cw->name }}</option> @endforeach
                        @endif
                    </select>
                </div>
            </div>

        </div>

        {{-- AREA KANAN (RINGKASAN) --}}
        <div class="col-xl-4 col-lg-5">
            <div class="overflow-hidden bg-white shadow-lg card summary-card">
                <div class="p-4 text-center text-white bg-primary">
                    <div class="mb-2 small fw-bolder text-white-50 text-uppercase" style="letter-spacing: 1.5px;">Estimasi Total PO</div>
                    <div class="d-flex justify-content-center align-items-center">
                        <span class="opacity-75 currency-label fs-5 me-2 fw-bold">IDR</span>
                        <h1 class="mb-0 fw-bolder" id="lblGrandTotal" style="font-size: 2.5rem;">0</h1>
                    </div>
                </div>

                <div class="p-4 card-body">
                    {{-- Diskon & Pajak Global --}}
                    <div class="p-3 mb-3 border bg-light rounded-3 border-warning-subtle">
                        <label class="mb-2 form-label small fw-bold text-dark"><i class="bi bi-tags-fill me-1 text-danger"></i> Diskon Global</label>
                        <div class="mb-1 overflow-hidden border shadow-sm input-group input-group-sm rounded-2 input-group-modern">
                            <select name="global_discount_type" id="globalDiscType" class="px-1 text-center bg-white border-0 form-select fw-bold tax-type-select" style="flex: 0 0 85px;" onchange="calculateGrandTotal()">
                                <option value="PERCENT">%</option><option value="FIXED" class="dynamic-currency-text">IDR</option>
                            </select>
                            <input type="number" name="global_discount_value" id="globalDiscValue" class="px-2 border-0 form-control text-end fw-bold text-danger" value="0" min="0" step="any" oninput="calculateGrandTotal()">
                        </div>
                    </div>

                    <div class="p-3 mb-4 border bg-light rounded-3 border-primary-subtle">
                        <label class="mb-2 form-label small fw-bold text-dark"><i class="bi bi-bank me-1 text-primary"></i> Pajak Global (PPN)</label>
                        <div class="mb-1 overflow-hidden border shadow-sm input-group input-group-sm rounded-2 input-group-modern">
                            <select name="global_tax_type" id="globalTaxType" class="px-1 text-center bg-white border-0 form-select fw-bold text-secondary tax-type-select" style="flex: 0 0 85px;" onchange="toggleGlobalTaxUI(this); calculateGrandTotal()">
                                <option value="PERCENT">%</option><option value="FIXED" class="dynamic-currency-text">IDR</option>
                            </select>
                            <select class="bg-white border-0 form-select text-end fw-bold text-info" id="globalTaxMasterSelect" onchange="applyGlobalMasterTax(this); calculateGrandTotal()">
                                <option value="" data-rate="0">- Tanpa Pajak -</option>
                                <option value="MANUAL_PERCENT" data-rate="0">Manual (%)</option>
                                @foreach($taxes as $tax) <option value="{{ $tax->id }}" data-rate="{{ (float)$tax->percent }}">{{ $tax->name }} ({{ (float)$tax->percent }}%)</option> @endforeach
                            </select>
                            <input type="number" name="global_tax_value" id="globalTaxValue" class="px-2 border-0 form-control text-end fw-bold text-primary d-none" value="0" min="0" step="any" oninput="calculateGrandTotal()">
                        </div>
                    </div>

                    {{-- Rincian Kalkulasi --}}
                    <h6 class="pb-2 mb-3 fw-bold text-dark border-bottom">Rincian Kalkulasi</h6>
                    <div class="mb-2 d-flex justify-content-between small text-muted"><span>Total Bruto</span><span class="fw-bold text-dark" id="lblSubtotal">0</span></div>
                    <div class="mb-2 d-flex justify-content-between small text-danger"><span>Total Diskon Item (-)</span><span class="fw-bold" id="lblTotalItemDisc">0</span></div>
                    <div class="mb-2 d-flex justify-content-between small text-primary fw-bolder"><span>DPP (Dasar Pajak)</span><span id="lblDpp">0</span></div>
                    <div class="mb-2 d-flex justify-content-between small text-danger fw-bolder"><span>Diskon Global (-)</span><span id="lblGlobalDisc">0</span></div>
                    <div class="mb-2 d-flex justify-content-between small text-muted"><span>Total Pajak PPN (+)</span><span class="fw-bold text-dark" id="lblTax">0</span></div>
                    <div class="mb-2 d-flex justify-content-between small text-success"><span>Biaya Tambahan (+)</span><span class="fw-bold" id="lblCharges">0</span></div>
                    <div class="pb-3 mb-4 d-flex justify-content-between small text-danger border-bottom"><span>Potongan Voucher (-)</span><span class="fw-bold" id="lblExtraDisc">0</span></div>

                    {{-- TANGGAL & TERMIN --}}
                    <h6 class="pt-3 pb-2 mb-3 fw-bold text-dark border-bottom">Jadwal & Pembayaran</h6>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Tanggal PO <span class="text-danger">*</span></label>
                        <input type="date" name="po_date" id="poDateInput" class="form-control form-input-custom fw-bold" value="{{ date('Y-m-d') }}" required onchange="calculateDueDate()">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Termin Pembayaran <span class="text-danger">*</span></label>
                        <select name="payment_term_id" id="paymentTermSelect" class="form-select form-input-custom fw-bold select2-init" required onchange="calculateDueDate()">
                            <option value="" data-days="0">- Pilih Termin -</option>
                            @foreach($paymentTerms as $term) <option value="{{ $term->id }}" data-days="{{ $term->days }}">{{ $term->name }}</option> @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Estimasi Kirim <span class="text-danger">*</span></label>
                        <input type="date" name="delivery_date" class="form-control form-input-custom fw-bold text-primary" required>
                    </div>
                    <div>
                        <label class="form-label small fw-bold text-danger">Jatuh Tempo Bayar</label>
                        <input type="date" name="due_date" id="dueDateInput" class="form-control form-input-custom bg-danger-subtle text-danger fw-bolder border-danger-subtle" readonly>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

{{-- DATALIST --}}
<datalist id="chargeTypeList">@foreach($chargeTypes as $type) <option value="{{ $type->name }}"></option> @endforeach</datalist>
<datalist id="discountTypeList">@foreach($discountTypes as $type) <option value="{{ $type->name }}"></option> @endforeach</datalist>

{{-- MASTER ITEM LIST (UNTUK DROPDOWN CLONE) --}}
<div id="masterItemSelectTemplate" class="d-none">
    <select class="form-select select2-item item-select" required onchange="onItemSelect(this, 'INDEX')">
        <option value="">-- Pilih Barang Master --</option>
        @foreach($masterItems as $m)
            <option value="{{ $m->id }}" data-base-uom="{{ $m->unit ?? 'PCS' }}" data-uoms="{{ json_encode($m->itemUoms) }}">{{ $m->code }} - {{ $m->name }}</option>
        @endforeach
    </select>
</div>

<template id="chargeRowTemplate">
    <tr class="charge-row border-bottom">
        <td width="60%" class="p-1 pb-2"><input type="text" name="charges[INDEX][charge_type_id]" class="form-control form-input-custom" list="chargeTypeList" placeholder="Ketik Biaya..." required></td>
        <td width="30%" class="p-1 pb-2"><input type="number" name="charges[INDEX][amount]" class="form-control form-input-custom text-end fw-bold text-success charge-input" placeholder="0" min="0" step="any" oninput="calculateGrandTotal()" required></td>
        <td width="10%" class="p-1 pb-2 text-center"><button type="button" class="p-0 mt-1 btn text-danger" onclick="removeRow(this)"><i class="bi bi-trash-fill fs-5"></i></button></td>
    </tr>
</template>

<template id="extraDiscRowTemplate">
    <tr class="extradisc-row border-bottom">
        <td width="60%" class="p-1 pb-2"><input type="text" name="extra_discounts[INDEX][discount_type_id]" class="form-control form-input-custom" list="discountTypeList" placeholder="Ketik Voucher..." required></td>
        <td width="30%" class="p-1 pb-2"><input type="number" name="extra_discounts[INDEX][amount]" class="form-control form-input-custom text-end fw-bold text-danger extradisc-input" placeholder="0" min="0" step="any" oninput="calculateGrandTotal()" required></td>
        <td width="10%" class="p-1 pb-2 text-center"><button type="button" class="p-0 mt-1 btn text-danger" onclick="removeRow(this)"><i class="bi bi-trash-fill fs-5"></i></button></td>
    </tr>
</template>

{{-- TEMPLATE ITEM ROW --}}
<template id="itemRowTemplate">
    <div class="mb-4 border-0 shadow-sm card item-row" style="border-radius: 12px; overflow: hidden;" id="itemCard_INDEX">
        <div class="px-4 py-3 card-header bg-light border-bottom d-flex justify-content-between align-items-center">
            <div class="w-50" id="selectContainer_INDEX">
                <!-- Select2 akan disuntikkan ke sini -->
            </div>
            <div class="text-end d-flex align-items-center">
                <div class="me-4">
                    <div class="mb-1 small text-muted fw-bold text-uppercase">Netto Item</div>
                    <h5 class="mb-0 fw-bolder text-primary"><span class="currency-label fs-6 text-muted me-1">IDR</span><span class="subtotal-display">0</span></h5>
                    <input type="hidden" class="subtotal-input" value="0">
                </div>
                <button type="button" class="p-0 border-0 btn btn-link text-danger" onclick="removeRow(this)"><i class="bi bi-trash-fill fs-4"></i></button>
            </div>
        </div>
        <div class="p-4 card-body">
            <div class="pb-4 mb-4 row g-3 border-bottom">
                <div class="col-md-7">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Nama Barang di PO (Custom)</label>
                        <input type="text" name="po_items[INDEX][item_name_override]" id="name_override_INDEX" class="form-control form-input-custom fw-bold text-primary" placeholder="Bisa dikosongkan untuk memakai nama asli...">
                    </div>
                    <label class="form-label small fw-bold text-dark">Spesifikasi Detail (Catatan)</label>
                    <textarea name="po_items[INDEX][notes]" class="form-control form-input-custom" rows="3" placeholder="Ketik catatan spek..."></textarea>
                </div>
                <div class="col-md-5">
                    <label class="form-label small fw-bold text-dark"><i class="bi bi-paperclip text-primary"></i> Dokumen Pendukung Item</label>
                    <div class="p-3 border rounded shadow-sm bg-light border-secondary-subtle">
                        <div id="fileListContainer_INDEX" class="gap-1 mb-2 d-flex flex-column"></div>
                        <div id="hiddenFileInputs_INDEX" style="display: none;"></div>
                        <button type="button" class="py-2 bg-white btn btn-sm btn-outline-primary w-100 fw-bold" style="border-style: dashed; border-width: 2px;" onclick="triggerFilePicker('INDEX')">
                            <i class="bi bi-plus-lg me-1"></i> Upload File
                        </button>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-dark">Qty & Satuan <span class="text-danger">*</span></label>
                    <div class="mb-1 shadow-sm input-group-modern">
                        <input type="number" name="po_items[INDEX][qty]" class="text-center form-control fw-bolder qty-input text-primary" value="1" min="0.01" step="0.01" oninput="calculateRow(this)" required>
                    </div>
                    <select name="po_items[INDEX][uom_id]" id="uomSelect_INDEX" class="shadow-sm form-select border-primary text-primary fw-bold uom-selector" onchange="updateUomName(this, 'INDEX')">
                        <option value="">- Pilih Barang Dulu -</option>
                    </select>
                    <input type="hidden" name="po_items[INDEX][uom]" id="uomName_INDEX" value="PCS">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-dark">Harga Satuan <span class="text-danger">*</span></label>
                    <div class="shadow-sm input-group-modern">
                        <span class="input-group-text currency-label">IDR</span>
                        <input type="number" name="po_items[INDEX][unit_price]" class="form-control text-end fw-bold price-input" value="0" min="0" step="any" oninput="calculateRow(this)" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-dark">Diskon per Item</label>
                    <div class="shadow-sm input-group-modern">
                        <select name="po_items[INDEX][discount_type]" class="text-center form-select fw-bold text-secondary disc-type" style="flex: 0 0 85px;" onchange="calculateRow(this)">
                            <option value="PERCENT">%</option><option value="FIXED" class="dynamic-currency-text">IDR</option>
                        </select>
                        <input type="number" name="po_items[INDEX][discount_value]" class="form-control text-end fw-bold text-danger disc-val" value="0" min="0" step="any" oninput="calculateRow(this)">
                    </div>
                    <input type="hidden" name="po_items[INDEX][discount_amount]" class="disc-amt-hidden" value="0">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-dark">Pajak (VAT/PPN)</label>
                    <div class="shadow-sm input-group-modern">
                        <select name="po_items[INDEX][tax_type]" class="text-center form-select fw-bold text-secondary tax-type-select" style="flex: 0 0 85px;" onchange="toggleItemTaxUI(this); calculateRow(this)">
                            <option value="PERCENT">%</option><option value="FIXED" class="dynamic-currency-text">IDR</option>
                        </select>
                        <select name="po_items[INDEX][tax_id]" class="form-select text-end fw-bold text-info tax-master-select" onchange="applyItemMasterTax(this); calculateRow(this)">
                            <option value="" data-rate="0">- Tanpa -</option>
                            <option value="MANUAL_PERCENT" data-rate="0">Mnl(%)</option>
                            @foreach($taxes as $tax) <option value="{{ $tax->id }}" data-rate="{{ (float)$tax->percent }}">{{ $tax->name }} ({{ (float)$tax->percent }}%)</option> @endforeach
                        </select>
                        <input type="number" name="po_items[INDEX][tax_value]" class="form-control text-end fw-bold text-info tax-val-input d-none" value="0" min="0" step="any" oninput="calculateRow(this)">
                    </div>
                    <input type="hidden" name="po_items[INDEX][tax_amount]" class="tax-amt-hidden" value="0">
                </div>
            </div>
        </div>
    </div>
</template>

@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    let itemIdx = 0;
    let chargeIdx = 100;
    let discIdx = 100;

    $(document).ready(function() {
        $('.select2-init').select2({ theme: 'bootstrap-5', width: '100%' });
        updateCurrencySymbol();
        calculateDueDate();
    });

    function updateCurrencySymbol() {
        let currency = document.getElementById('currencySelect').value;
        document.querySelectorAll('.currency-label').forEach(el => el.innerText = currency);
        document.querySelectorAll('.dynamic-currency-text').forEach(opt => opt.innerText = currency);
    }

    function updateShippingAddress() {
        var select = document.getElementById('billToSelect');
        var address = select.options[select.selectedIndex]?.getAttribute('data-address');
        if (address) document.getElementById('shippingAddressInput').value = address;
    }

    function calculateDueDate() {
        let poDateVal = document.getElementById('poDateInput').value;
        let termSelect = document.getElementById('paymentTermSelect');
        let daysToAdd = parseInt(termSelect.options[termSelect.selectedIndex]?.getAttribute('data-days')) || 0;
        if(poDateVal && daysToAdd > 0) {
            let poDate = new Date(poDateVal);
            poDate.setDate(poDate.getDate() + daysToAdd);
            document.getElementById('dueDateInput').value = poDate.toISOString().split('T')[0];
        } else if(poDateVal) document.getElementById('dueDateInput').value = poDateVal;
    }

    function addChargeRow() {
        chargeIdx++;
        document.getElementById('chargesContainer').insertAdjacentHTML('beforeend', document.getElementById('chargeRowTemplate').innerHTML.replace(/INDEX/g, chargeIdx));
    }

    function addExtraDiscRow() {
        discIdx++;
        document.getElementById('extraDiscContainer').insertAdjacentHTML('beforeend', document.getElementById('extraDiscRowTemplate').innerHTML.replace(/INDEX/g, discIdx));
    }

    function removeRow(btn) {
        btn.closest('tr, .item-row').remove();
        calculateGrandTotal();
        if(document.querySelectorAll('.item-row').length === 0) {
            document.getElementById('emptyStateBox').style.display = 'block';
        }
    }

    // ==========================================
    // LOGIKA ITEM DINAMIS (DIRECT PO)
    // ==========================================
    function addItemRow() {
        document.getElementById('emptyStateBox').style.display = 'none';
        itemIdx++;

        let template = document.getElementById('itemRowTemplate').innerHTML.replace(/INDEX/g, itemIdx);
        document.getElementById('itemsContainer').insertAdjacentHTML('beforeend', template);

        // Suntikkan Dropdown Item (Select2)
        let selectHtml = document.getElementById('masterItemSelectTemplate').innerHTML.replace(/INDEX/g, itemIdx);
        // Tambahkan attribut name agar terkirim
        selectHtml = selectHtml.replace('class="form-select', `name="po_items[${itemIdx}][item_id]" class="form-select`);

        document.getElementById('selectContainer_' + itemIdx).innerHTML = selectHtml;
        $('#selectContainer_' + itemIdx + ' .select2-item').select2({ theme: 'bootstrap-5', width: '100%' });

        updateCurrencySymbol();
    }

    function onItemSelect(selectObj, index) {
        let selectedOption = selectObj.options[selectObj.selectedIndex];
        let baseUom = selectedOption.getAttribute('data-base-uom') || 'PCS';
        let uomsJson = selectedOption.getAttribute('data-uoms');

        let itemNameRaw = selectedOption.text;
        let itemNameSplit = itemNameRaw.split(' - ');
        let cleanName = itemNameSplit.length > 1 ? itemNameSplit.slice(1).join(' - ') : itemNameRaw;

        document.getElementById('name_override_' + index).value = cleanName;

        let uomSelect = document.getElementById('uomSelect_' + index);
        uomSelect.innerHTML = `<option value="" data-name="${baseUom}">${baseUom} (Dasar)</option>`;

        if (uomsJson) {
            try {
                let uoms = JSON.parse(uomsJson);
                uoms.forEach(u => {
                    uomSelect.innerHTML += `<option value="${u.id}" data-name="${u.uom_name}">${u.uom_name} (Isi: ${parseFloat(u.conversion_qty)})</option>`;
                });
            } catch(e) {}
        }

        updateUomName(uomSelect, index);
    }

    function updateUomName(selectObj, index) {
        let name = selectObj.options[selectObj.selectedIndex]?.getAttribute('data-name') || 'PCS';
        document.getElementById('uomName_' + index).value = name;
    }

    // ==========================================
    // LOGIKA PERHITUNGAN
    // ==========================================
    function calculateRow(el) {
        let row = el.closest('.item-row');
        let qty = parseFloat(row.querySelector('.qty-input').value) || 0;
        let price = parseFloat(row.querySelector('.price-input').value) || 0;
        let gross = qty * price;

        let discType = row.querySelector('.disc-type').value;
        let discVal = parseFloat(row.querySelector('.disc-val').value) || 0;
        let discAmt = (discType === 'PERCENT') ? (gross * discVal / 100) : discVal;
        row.querySelector('.disc-amt-hidden').value = discAmt;

        let dpp = gross - discAmt;

        let taxType = row.querySelector('.tax-type-select').value;
        let taxVal = parseFloat(row.querySelector('.tax-val-input').value) || 0;
        let taxAmt = (taxType === 'PERCENT') ? (dpp * taxVal / 100) : taxVal;
        row.querySelector('.tax-amt-hidden').value = taxAmt;

        let subtotal = dpp + taxAmt;
        row.querySelector('.subtotal-input').value = subtotal;
        row.querySelector('.subtotal-display').innerText = formatCurrency(subtotal);

        calculateGrandTotal();
    }

    function calculateGrandTotal() {
        let totalGross = 0; let totalItemDisc = 0; let totalTaxItem = 0;

        document.querySelectorAll('.item-row').forEach(row => {
            let qty = parseFloat(row.querySelector('.qty-input').value) || 0;
            let price = parseFloat(row.querySelector('.price-input').value) || 0;
            totalGross += (qty * price);
            totalItemDisc += parseFloat(row.querySelector('.disc-amt-hidden').value) || 0;
            totalTaxItem += parseFloat(row.querySelector('.tax-amt-hidden').value) || 0;
        });

        let totalCharges = 0; document.querySelectorAll('.charge-input').forEach(i => totalCharges += parseFloat(i.value) || 0);
        let totalExtraDisc = 0; document.querySelectorAll('.extradisc-input').forEach(i => totalExtraDisc += parseFloat(i.value) || 0);

        let dpp = totalGross - totalItemDisc;

        let globalDiscType = document.getElementById('globalDiscType').value;
        let globalDiscVal = parseFloat(document.getElementById('globalDiscValue').value) || 0;
        let globalDiscAmt = (globalDiscType === 'PERCENT') ? (dpp * globalDiscVal / 100) : globalDiscVal;
        let dppAfterGlobal = dpp - globalDiscAmt;

        let globalTaxType = document.getElementById('globalTaxType').value;
        let globalTaxVal = parseFloat(document.getElementById('globalTaxValue').value) || 0;
        let globalTaxAmt = (globalTaxType === 'PERCENT') ? (dppAfterGlobal * globalTaxVal / 100) : globalTaxVal;

        let grandTotal = dppAfterGlobal + totalTaxItem + globalTaxAmt + totalCharges - totalExtraDisc;

        document.getElementById('lblSubtotal').innerText = formatCurrency(totalGross);
        document.getElementById('lblTotalItemDisc').innerText = "-" + formatCurrency(totalItemDisc);
        document.getElementById('lblDpp').innerText = formatCurrency(dpp);
        document.getElementById('lblGlobalDisc').innerText = "-" + formatCurrency(globalDiscAmt);
        document.getElementById('lblTax').innerText = "+" + formatCurrency(totalTaxItem + globalTaxAmt);
        document.getElementById('lblCharges').innerText = "+" + formatCurrency(totalCharges);
        document.getElementById('lblExtraDisc').innerText = "-" + formatCurrency(totalExtraDisc);
        document.getElementById('lblGrandTotal').innerText = formatCurrency(grandTotal);
    }

    function formatCurrency(amount) { return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(amount); }

    // ==========================================
    // PAJAK HYBRID UI
    // ==========================================
    function toggleItemTaxUI(typeSelect) {
        let container = typeSelect.closest('.input-group-modern');
        let masterSelect = container.querySelector('.tax-master-select');
        let valInput = container.querySelector('.tax-val-input');
        if (typeSelect.value === 'PERCENT') {
            masterSelect.classList.remove('d-none');
            if(masterSelect.value === 'MANUAL_PERCENT') { masterSelect.style.flex = '0 0 110px'; valInput.classList.remove('d-none'); }
            else { masterSelect.style.flex = '1 1 auto'; valInput.classList.add('d-none'); valInput.value = masterSelect.options[masterSelect.selectedIndex].getAttribute('data-rate'); }
        } else {
            masterSelect.classList.add('d-none'); valInput.classList.remove('d-none'); valInput.value = 0;
        }
    }
    function applyItemMasterTax(masterSelect) {
        let container = masterSelect.closest('.input-group-modern');
        let valInput = container.querySelector('.tax-val-input');
        if (masterSelect.value === 'MANUAL_PERCENT') {
            masterSelect.style.flex = '0 0 110px'; valInput.classList.remove('d-none'); valInput.value = 0; valInput.focus();
        } else {
            masterSelect.style.flex = '1 1 auto'; valInput.classList.add('d-none'); valInput.value = masterSelect.options[masterSelect.selectedIndex].getAttribute('data-rate');
        }
    }

    function toggleGlobalTaxUI(typeSelect) {
        let masterSelect = document.getElementById('globalTaxMasterSelect');
        let valInput = document.getElementById('globalTaxValue');
        if (typeSelect.value === 'PERCENT') {
            masterSelect.classList.remove('d-none');
            if(masterSelect.value === 'MANUAL_PERCENT') { valInput.classList.remove('d-none'); }
            else { valInput.classList.add('d-none'); valInput.value = masterSelect.options[masterSelect.selectedIndex].getAttribute('data-rate'); }
        } else {
            masterSelect.classList.add('d-none'); valInput.classList.remove('d-none'); valInput.value = 0;
        }
    }
    function applyGlobalMasterTax(masterSelect) {
        let valInput = document.getElementById('globalTaxValue');
        if (masterSelect.value === 'MANUAL_PERCENT') {
            valInput.classList.remove('d-none'); valInput.value = 0; valInput.focus();
        } else {
            valInput.classList.add('d-none'); valInput.value = masterSelect.options[masterSelect.selectedIndex].getAttribute('data-rate');
        }
    }

    // ==========================================
    // FILE UPLOAD & SUBMIT
    // ==========================================
    function triggerFilePicker(index) {
        let fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.name = `po_items[${index}][attachments][]`;
        fileInput.multiple = true;
        fileInput.accept = '.pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx';

        fileInput.onchange = function() {
            if(this.files.length > 0) {
                let hiddenContainer = document.getElementById('hiddenFileInputs_' + index);
                let listContainer = document.getElementById('fileListContainer_' + index);
                let inputId = 'fileInput_' + Date.now() + Math.random().toString(36).substr(2, 5);
                this.id = inputId;
                hiddenContainer.appendChild(this);

                Array.from(this.files).forEach((file, fileIndex) => {
                    let pillId = 'pill_' + inputId + '_' + fileIndex;
                    let pillHTML = `
                        <div id="${pillId}" class="p-1 mt-1 bg-white border shadow-sm file-pill d-flex align-items-center justify-content-between rounded-3">
                            <div class="overflow-hidden d-flex align-items-center">
                                <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex justify-content-center align-items-center me-2" style="width: 25px; height: 25px; font-size: 0.7rem;"><i class="bi bi-file-earmark-text-fill"></i></div>
                                <div class="text-truncate"><div class="fw-bold text-dark text-truncate" style="max-width: 100px; font-size: 0.65rem;" title="${file.name}">${file.name}</div></div>
                            </div>
                            <button type="button" class="p-0 btn btn-link text-danger ms-1" onclick="removeSpecificFile('${inputId}', ${fileIndex}, '${pillId}')"><i class="bi bi-x-circle-fill"></i></button>
                        </div>
                    `;
                    listContainer.insertAdjacentHTML('beforeend', pillHTML);
                });
            }
        };
        fileInput.click();
    }

    function removeSpecificFile(inputId, fileIndexToRemove, pillId) {
        let inputEle = document.getElementById(inputId);
        if(inputEle) {
            let dt = new DataTransfer();
            let files = inputEle.files;
            for(let i = 0; i < files.length; i++) { if(i !== fileIndexToRemove) dt.items.add(files[i]); }
            inputEle.files = dt.files;
            if(inputEle.files.length === 0) inputEle.remove();
        }
        document.getElementById(pillId).remove();
    }

    document.getElementById('poForm').addEventListener('submit', function(e) {
        e.preventDefault();

        if(document.querySelectorAll('.item-row').length === 0) {
            Swal.fire('Ups!', 'Anda belum menambahkan satupun barang pesanan.', 'warning');
            return;
        }

        if(!this.checkValidity()) {
            let invalidElements = this.querySelectorAll(':invalid');
            if(invalidElements.length > 0) {
                invalidElements.forEach(el => el.classList.add('is-invalid'));
                Swal.fire('Data Belum Lengkap!', 'Periksa kotak bergaris merah.', 'error');
            }
            return;
        }

        Swal.fire({
            title: 'Terbitkan Direct PO?',
            text: "Pastikan nominal dan vendor sudah benar.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#0d6efd',
            confirmButtonText: 'Ya, Terbitkan!'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({ title: 'Memproses...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                this.submit();
            }
        });
    });
</script>
@endpush
