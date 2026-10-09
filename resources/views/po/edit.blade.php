@extends('layouts.app')

@push('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<style>
    /* Styling Select2 */
    .select2-container--bootstrap-5 .select2-selection { border-radius: 8px; min-height: 40px; font-size: 0.85rem; border-color: #dee2e6; }
    .select2-container--bootstrap-5.select2-container--focus .select2-selection { border-color: #0d6efd; box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15); }

    /* Modern Input Styling */
    .form-input-custom { border: 1px solid #dee2e6; border-radius: 8px; font-size: 0.85rem; min-height: 40px; transition: all 0.2s; }
    .form-input-custom:focus { border-color: #0d6efd; box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15); background-color: #fff; }

    /* Input Group Modern */
    .input-group-modern { border-radius: 8px; overflow: hidden; border: 1px solid #dee2e6; display: flex; }
    .input-group-modern:focus-within { border-color: #0d6efd; box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15); }
    .input-group-modern input, .input-group-modern select, .input-group-modern .input-group-text { border: none !important; background: transparent; }
    .input-group-modern .input-group-text { background-color: #f8f9fa; color: #6c757d; font-weight: 600; border-right: 1px solid #dee2e6 !important; }

    /* Fixed Sidebar Summary */
    .summary-card { position: sticky; top: 20px; border-radius: 16px; border: 1px solid #e9ecef; }

    /* CSS VALIDASI ERROR */
    .is-invalid { border-color: #dc3545 !important; background-color: #fff8f8 !important; }
    .input-group-modern:has(.is-invalid) { border-color: #dc3545 !important; box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25) !important; }

    /* STYLING KHUSUS CKEDITOR */
    .ck-editor__editable_inline { min-height: 120px; font-size: 0.85rem; }
    input[type=number]::-webkit-inner-spin-button, input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
</style>
@endpush

@section('content')

<form action="{{ route('po.update', $po->po_number) }}" method="POST" id="poForm" enctype="multipart/form-data" novalidate>
    @csrf

    {{-- HEADER --}}
    <div class="pb-3 mb-4 border-bottom d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-dark fw-bolder"><i class="bi bi-pencil-square me-2 text-warning"></i> Edit Purchase Order</h4>
            <div class="text-muted small">Mengubah dokumen PO <strong class="text-primary">{{ $po->po_number }}</strong>.</div>
        </div>
        <div class="gap-2 d-flex">
            <a href="{{ route('po.index') }}" class="px-4 border btn btn-light fw-bold rounded-pill"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
            <button type="submit" class="px-4 btn btn-success fw-bold rounded-pill"><i class="bi bi-save-fill me-1"></i> Simpan Perubahan</button>
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
        {{-- ================= AREA KIRI (FORM UTAMA) ================= --}}
        <div class="col-xl-8 col-lg-7">

            {{-- 1. INFORMASI VENDOR & PENAGIHAN --}}
            <div class="mb-4 border-0 shadow-sm card rounded-4">
                <div class="py-3 bg-white border-bottom card-header d-flex align-items-center">
                    <div class="p-2 me-3 bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;">
                        <i class="bi bi-geo-alt-fill"></i>
                    </div>
                    <h6 class="mb-0 fw-bold">Penagihan & Pengiriman</h6>
                </div>
                <div class="p-4 card-body">
                    <div class="row g-4">
                        <div class="col-md-5">
                            <label class="form-label small fw-bold text-dark">Vendor Penerima PO</label>
                            <div class="p-2 border rounded bg-light fw-bold text-primary"><i class="bi bi-shop me-1"></i> {{ optional($po->vendor)->name ?? 'Vendor Tidak Ditemukan' }}</div>
                            <div class="mt-1 text-danger form-text" style="font-size: 0.65rem;">*Vendor tidak bisa diubah. Jika ganti vendor, silakan Batalkan PO ini.</div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-bold text-dark">Nama Toko Spesifik (Opsional)</label>
                            <input type="text" name="vendor_sub_name" class="form-control form-input-custom" value="{{ old('vendor_sub_name', $po->vendor_sub_name) }}" placeholder="Contoh: Vortexindo.Computer">
                            <div class="mt-1 text-muted form-text" style="font-size: 0.65rem;">Gunakan ini jika Anda memilih vendor berupa Marketplace.</div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-bold text-dark">Mata Uang <span class="text-danger">*</span></label>
                            <select name="currency" id="currencySelect" class="form-select bg-light fw-bold text-primary form-input-custom" required onchange="updateCurrencySymbol()">
                                @foreach($currencies as $curr)
                                    <option value="{{ $curr->code }}" {{ $curr->code == $po->currency ? 'selected' : '' }}>{{ $curr->code }} - {{ $curr->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-bold text-dark">Tagihan Ke (Bill To) <span class="text-danger">*</span></label>
                            <select name="billing_company_id" id="billToSelect" class="form-select select2-init" required onchange="updateShippingAddress()">
                                @foreach($companies as $c)
                                    <option value="{{ $c->id }}" data-address="{{ $c->address ?? '' }}" {{ $c->id == $po->bill_to_company_id ? 'selected' : '' }}>{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-bold text-dark">No. Invoice (Opsional)</label>
                            <input type="text" name="invoice_number" class="form-control form-input-custom fw-bold text-primary" value="{{ $po->invoice_number }}" placeholder="Bisa dikosongkan...">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-bold text-dark">No. Rekening (Account No)</label>
                            <input type="text" name="account_number" class="form-control form-input-custom fw-bold text-success" value="{{ $po->account_number }}" placeholder="Bisa dikosongkan...">
                        </div>
                        <div class="col-12">
                            <div class="mb-2 d-flex justify-content-between align-items-end">
                                <label class="mb-0 form-label small fw-bold text-dark">Lokasi Pengiriman (Ship To) <span class="text-danger">*</span></label>
                                <button type="button" class="p-0 text-decoration-none btn btn-sm btn-link" onclick="updateShippingAddress(true)"><i class="bi bi-arrow-counterclockwise"></i> Reset ke Alamat PT</button>
                            </div>
                            <textarea name="shipping_address" id="shippingAddressInput" rows="2" class="form-control form-input-custom bg-light" required>{{ $po->shipping_address }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 2. DETAIL PESANAN BARANG --}}
            <div class="mt-5 mb-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-dark fw-bolder"><i class="bi bi-box-seam me-2 text-primary"></i>Daftar Barang Pesanan</h5>

                {{-- TOMBOL TAMBAH ITEM (HANYA MUNCUL JIKA DIRECT PO / TANPA PR) --}}
                @if(empty($po->purchase_request_id))
                    <button type="button" class="px-3 shadow-sm btn btn-sm btn-primary fw-bold rounded-pill" onclick="addNewItemRow()">
                        <i class="bi bi-plus-lg me-1"></i> Tambah Item
                    </button>
                @endif
            </div>

            <div id="itemsContainer">
                @foreach($po->items as $item)
                    @php
                        $masterItem = $item->item;
                        $baseUomId = optional($masterItem)->uom_id;
                        $baseUomName = optional(optional($masterItem)->uom)->name ?? 'PCS';

                        $prItem = $item->purchaseRequestItem ?? \App\Models\PurchaseRequestItem::find($item->purchase_request_item_id);
                        $prItemConvRate = 1;
                        $prUomId = optional($prItem)->uom_id ?? $baseUomId;

                        if ($prUomId != $baseUomId && optional($masterItem)->itemUoms) {
                            $altUom = collect($masterItem->itemUoms)->where('id', $prUomId)->first()
                                   ?? collect($masterItem->itemUoms)->where('uom_id', $prUomId)->first();
                            if ($altUom) $prItemConvRate = (float) $altUom->conversion_qty;
                        }

                        $prTargetBaseQty = (float)(optional($prItem)->qty ?? 0) * $prItemConvRate;
                        $prOrderedBaseQty = (float)(optional($prItem)->ordered_qty ?? 0) * $prItemConvRate;

                        $currentConvRate = 1;
                        $savedUomId = $item->uom_id ?? $item->item_uom_id ?? $baseUomId;

                        if ($savedUomId != $baseUomId && optional($masterItem)->itemUoms) {
                            $poUomDb = collect($masterItem->itemUoms)->where('id', $savedUomId)->first()
                                    ?? collect($masterItem->itemUoms)->where('uom_id', $savedUomId)->first();
                            if ($poUomDb) $currentConvRate = (float) $poUomDb->conversion_qty;
                        }

                        $currentPoItemBaseQty = (float)($item->qty ?? $item->qty_ordered ?? 0) * $currentConvRate;

                        if(empty($po->purchase_request_id)){
                            $remainingNominal = 999999999;
                            $sisaBaseQty = 999999999;
                        } else {
                            $sisaBaseQty = max(0, $prTargetBaseQty - $prOrderedBaseQty + $currentPoItemBaseQty);
                            $remainingNominal = $currentConvRate > 0 ? ($sisaBaseQty / $currentConvRate) : 0;
                            $remainingNominal = round($remainingNominal, 2);
                        }
                    @endphp

                    <div class="mb-4 border-0 shadow-sm card item-row" style="border-radius: 12px; overflow: hidden;">
                        <div class="px-4 py-3 bg-light border-bottom card-header d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-dark fw-bolder fs-6">{{ $item->item_name ?? optional($item->item)->name ?? 'Item Terhapus' }}</div>
                                <span class="mt-1 border badge bg-secondary-subtle text-secondary">{{ optional($item->item)->code }}</span>
                                <input type="hidden" name="po_items[{{ $item->id }}][pr_item_id]" value="{{ $item->purchase_request_item_id }}">
                                <input type="hidden" name="po_items[{{ $item->id }}][vendor_id]" value="{{ $po->vendor_id }}">
                            </div>
                            <div class="gap-3 text-end d-flex align-items-center justify-content-end">
                                <div>
                                    <div class="mb-1 small text-muted fw-bold text-uppercase">Netto Item</div>
                                    <h5 class="mb-0 text-primary fw-bolder"><span class="currency-label fs-6 text-muted me-1">{{ $po->currency }}</span><span class="subtotal-display">{{ number_format($item->subtotal + $item->tax_amount, 2, '.', '') }}</span></h5>
                                    <input type="hidden" class="subtotal-input" value="{{ $item->subtotal + $item->tax_amount }}">
                                    <input type="hidden" name="po_items[{{ $item->id }}][is_deleted]" class="is-deleted-flag" value="0">
                                </div>

                                @if(empty($po->purchase_request_id))
                                    <button type="button" class="p-2 btn btn-outline-danger btn-sm rounded-circle" onclick="removeExistingItemRow(this)" title="Hapus Item Ini">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                @endif
                            </div>
                        </div>

                        <div class="p-4 card-body">
                            <div class="pb-4 mb-4 row g-3 border-bottom">
                                <div class="col-md-7">
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold text-dark">Nama Barang di PO (Opsional)</label>
                                        <input type="text" name="po_items[{{ $item->id }}][item_name_override]" class="form-control form-input-custom fw-bold text-primary" value="{{ $item->item_name ?? optional($item->item)->name }}" placeholder="Ketik nama spesifik barang...">
                                    </div>
                                    <label class="form-label small fw-bold text-dark">Spesifikasi Detail (Bisa diedit)</label>
                                    <textarea name="po_items[{{ $item->id }}][notes]" id="spec_{{ $item->id }}" class="form-control ckeditor-spec">{!! $item->description !!}</textarea>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small fw-bold text-dark"><i class="bi bi-paperclip text-primary"></i> Dokumen Pendukung Item</label>
                                    <div class="p-3 border rounded shadow-sm bg-light border-secondary-subtle">
                                        <div class="mb-2"><span class="small fw-bold text-muted">File Lampiran PO:</span></div>
                                        @if(isset($item->raw_attachments) && count($item->raw_attachments) > 0)
                                            <div class="gap-1 mb-2 d-flex flex-column">
                                                @foreach($item->raw_attachments as $file)
                                                    <div class="p-1 bg-white border border-opacity-50 rounded shadow-sm border-info d-flex justify-content-between align-items-center">
                                                        <a href="{{ asset('storage/' . $file->file_path) }}" target="_blank" class="text-truncate small text-decoration-none text-primary fw-bold ms-1" style="font-size: 0.7rem; max-width: 80%;" title="{{ $file->file_name }}">
                                                            <i class="bi bi-file-earmark-text-fill text-danger me-1"></i> {{ $file->file_name }}
                                                        </a>
                                                        <a href="{{ route('po.delete_item_attachment', $file->id) }}" class="p-0 px-1 btn btn-sm text-danger" onclick="return confirm('Hapus lampiran ini secara permanen?')">
                                                            <i class="bi bi-trash-fill"></i>
                                                        </a>
                                                    </div>
                                                @endforeach
                                            </div>
                                            <hr class="my-2 border-secondary-subtle">
                                        @endif
                                        <div id="fileListContainer_{{ $item->id }}" class="gap-1 mb-2 d-flex flex-column"></div>
                                        <div id="hiddenFileInputs_{{ $item->id }}" style="display: none;"></div>
                                        <button type="button" class="py-2 mb-3 bg-white btn btn-sm btn-outline-primary w-100 fw-bold" style="border-style: dashed; border-width: 2px;" onclick="triggerFilePicker('{{ $item->id }}')">
                                            <i class="bi bi-plus-lg me-1"></i> Tambah File Baru
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-dark">Qty & Satuan <span class="text-danger">*</span></label>
                                    <div class="mb-1 shadow-sm input-group-modern">
                                        <input type="number" name="po_items[{{ $item->id }}][qty]" id="qty-input-{{ $item->id }}" class="text-center form-control fw-bolder qty-input text-primary" value="{{ (float)($item->qty ?? $item->qty_ordered) }}" max="{{ $remainingNominal }}" min="0.01" step="0.01" data-base-remaining="{{ $sisaBaseQty }}" oninput="calculateRow(this)" required>
                                    </div>
                                    <select name="po_items[{{ $item->id }}][uom_id]" class="shadow-sm form-select border-primary text-primary fw-bold uom-selector select2-init" data-current-conv="{{ $currentConvRate }}" onchange="updateRowUom(this, {{ $item->id }})">
                                        <option value="{{ $baseUomId }} " data-name="{{ $baseUomName }}" data-conv="1" {{ ($currentConvRate == 1) ? 'selected' : '' }}>
                                            {{ $baseUomName }} (Dasar)
                                        </option>
                                        @if(optional($masterItem)->itemUoms)
                                            @foreach($masterItem->itemUoms as $altUom)
                                                @php
                                                    $altVal = $altUom->uom_id ?? $altUom->id;
                                                    $altConv = (float)$altUom->conversion_qty;
                                                    $safeAltVal = $altVal . str_repeat(' ', $loop->iteration + 1);
                                                    $isSelected = ($currentConvRate == $altConv && $altConv != 1);
                                                @endphp
                                                @if($altConv != 1)
                                                    <option value="{{ $safeAltVal }}" data-name="{{ $altUom->uom_name }} (Isi: {{ $altConv }} {{ $baseUomName }})" data-conv="{{ $altConv }}" {{ $isSelected ? 'selected' : '' }}>
                                                        {{ $altUom->uom_name }} (Isi: {{ $altConv }})
                                                    </option>
                                                @endif
                                            @endforeach
                                        @endif
                                    </select>
                                    <input type="hidden" name="po_items[{{ $item->id }}][uom]" id="uom-name-{{ $item->id }}" value="{{ $item->uom }}">
                                    @if(!empty($po->purchase_request_id))
                                        <div class="mt-1 text-muted" style="font-size: 0.7rem;" id="max-help-{{ $item->id }}">
                                            Sisa Jatah PR: <strong class="text-danger" id="max-val-{{ $item->id }}">{{ $remainingNominal }}</strong>
                                        </div>
                                    @endif
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-dark">Harga Satuan <span class="text-danger">*</span></label>
                                    <div class="shadow-sm input-group-modern">
                                        <span class="input-group-text currency-label">{{ $po->currency }}</span>
                                        <input type="number" name="po_items[{{ $item->id }}][unit_price]" class="form-control text-end fw-bold price-input" value="{{ (float)$item->unit_price }}" min="0" step="any" oninput="calculateRow(this)" required>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-dark">Diskon per Item</label>
                                    <div class="shadow-sm input-group-modern">
                                        <select name="po_items[{{ $item->id }}][discount_type]" class="text-center form-select fw-bold text-secondary disc-type" style="flex: 0 0 85px;" onchange="calculateRow(this)">
                                            <option value="PERCENT" {{ $item->discount_type == 'PERCENT' ? 'selected' : '' }}>%</option>
                                            <option value="FIXED" class="dynamic-currency-text" {{ $item->discount_type == 'FIXED' ? 'selected' : '' }}>{{ $po->currency }}</option>
                                        </select>
                                        <input type="number" name="po_items[{{ $item->id }}][discount_value]" class="form-control text-end fw-bold text-danger disc-val" value="{{ (float)$item->discount_value }}" min="0" step="any" oninput="calculateRow(this)">
                                    </div>
                                    <input type="hidden" name="po_items[{{ $item->id }}][discount_amount]" class="disc-amt-hidden" value="{{ (float)$item->discount_amount }}">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-dark">Pajak (VAT/PPN)</label>
                                    <div class="shadow-sm input-group-modern">
                                        <select name="po_items[{{ $item->id }}][tax_type]" class="text-center form-select fw-bold text-secondary tax-type-select" style="flex: 0 0 85px;" onchange="toggleTaxUI(this); calculateRow(this)">
                                            <option value="PERCENT" {{ ($item->tax_type ?? 'PERCENT') == 'PERCENT' ? 'selected' : '' }}>%</option>
                                            <option value="FIXED" class="dynamic-currency-text" {{ ($item->tax_type ?? '') == 'FIXED' ? 'selected' : '' }}>{{ $po->currency }}</option>
                                        </select>
                                        @php
                                            $isManualPct = false; $matchedTaxId = "";
                                            if (($item->tax_type ?? 'PERCENT') == 'PERCENT' && (float)($item->tax_value ?? 0) > 0) {
                                                $taxMatch = collect($taxes)->where('percent', (float)$item->tax_value)->first();
                                                if ($taxMatch) $matchedTaxId = $taxMatch->id; else $isManualPct = true;
                                            }
                                        @endphp
                                        <select name="po_items[{{ $item->id }}][tax_id]" class="form-select text-end fw-bold text-info tax-master-select" onchange="applyMasterTax(this); calculateRow(this)">
                                            <option value="" data-rate="0">Tanpa Pajak -</option>
                                            <option value="MANUAL_PERCENT" data-rate="0" {{ $isManualPct ? 'selected' : '' }}>Manual (%)</option>
                                            @foreach($taxes as $tax)
                                                <option value="{{ $tax->id }}" data-rate="{{ (float)$tax->percent }}" {{ $matchedTaxId == $tax->id ? 'selected' : '' }}>{{ $tax->name }} ({{ (float)$tax->percent }}%)</option>
                                            @endforeach
                                        </select>
                                        <input type="number" name="po_items[{{ $item->id }}][tax_value]" class="form-control text-end fw-bold text-info tax-val-input" value="{{ (float)($item->tax_value ?? 0) }}" min="0" step="any" oninput="calculateRow(this)">
                                    </div>
                                    <input type="hidden" name="po_items[{{ $item->id }}][tax_amount]" class="tax-amt-hidden" value="{{ (float)($item->tax_amount ?? 0) }}">
                                    <input type="hidden" name="po_items[{{ $item->id }}][tax_type]" class="tax-type-hidden" value="{{ $item->tax_type ?? 'NONE' }}">
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- 3. BIAYA LAIN & POTONGAN LAIN --}}
            <div class="mt-2 mb-4 row g-4">
                <div class="col-md-6">
                    <div class="border-0 shadow-sm card rounded-4 h-100">
                        <div class="py-3 bg-white border-bottom card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 text-dark fw-bold"><i class="bi bi-truck text-success me-2"></i>Biaya Tambahan (+)</h6>
                            <button type="button" class="border btn btn-sm btn-light text-primary rounded-pill fw-bold" onclick="addChargeRow()"><i class="bi bi-plus-lg"></i> Baris</button>
                        </div>
                        <div class="p-3 bg-light card-body rounded-bottom-4">
                            <table class="table mb-0 table-borderless table-sm">
                                <tbody id="chargesContainer">
                                    @foreach($charges as $idx => $charge)
                                        <tr class="border-bottom charge-row">
                                            <td width="55%" class="p-1 pb-2">
                                                <input type="text" name="charges[{{ $idx }}][charge_type_id]" class="form-control form-input-custom" list="chargeTypeList" value="{{ $charge->name }}" required>
                                            </td>
                                            <td width="35%" class="p-1 pb-2">
                                                <input type="number" name="charges[{{ $idx }}][amount]" class="form-control form-input-custom text-end fw-bold text-success charge-input" value="{{ (float)$charge->amount }}" min="0" step="any" oninput="calculateGrandTotal()" required>
                                            </td>
                                            <td width="10%" class="p-1 pb-2 text-center">
                                                <button type="button" class="p-0 mt-1 btn text-danger" onclick="removeRow(this)"><i class="bi bi-trash-fill fs-5"></i></button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="border-0 shadow-sm card rounded-4 h-100">
                        <div class="py-3 bg-white border-bottom card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 text-dark fw-bold"><i class="bi bi-ticket-perforated text-danger me-2"></i>Potongan Voucher (-)</h6>
                            <button type="button" class="border btn btn-sm btn-light text-danger rounded-pill fw-bold" onclick="addExtraDiscRow()"><i class="bi bi-plus-lg"></i> Baris</button>
                        </div>
                        <div class="p-3 bg-light card-body rounded-bottom-4">
                            <table class="table mb-0 table-borderless table-sm">
                                <tbody id="extraDiscContainer">
                                    @foreach($extraDiscounts as $idx => $disc)
                                        <tr class="border-bottom extradisc-row">
                                            <td width="55%" class="p-1 pb-2">
                                                <input type="text" name="extra_discounts[{{ $idx }}][discount_type_id]" class="form-control form-input-custom" list="discountTypeList" value="{{ $disc->name }}" required>
                                            </td>
                                            <td width="35%" class="p-1 pb-2">
                                                <input type="number" name="extra_discounts[{{ $idx }}][amount]" class="form-control form-input-custom text-end fw-bold text-danger extradisc-input" value="{{ (float)$disc->amount }}" min="0" step="any" oninput="calculateGrandTotal()" required>
                                            </td>
                                            <td width="10%" class="p-1 pb-2 text-center">
                                                <button type="button" class="p-0 mt-1 btn text-danger" onclick="removeRow(this)"><i class="bi bi-trash-fill fs-5"></i></button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Dokumen Header & Catatan --}}
            <div class="mb-4 border-0 shadow-sm card rounded-4">
                <div class="p-4 card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark"><i class="bi bi-file-text me-2 text-primary"></i>Catatan Internal / Pesan Utama PO</label>
                            <textarea name="notes" class="form-control form-input-custom bg-light" rows="3">{{ $po->notes }}</textarea>
                        </div>
                        <div class="pl-4 border-start col-md-6">
                            <label class="form-label fw-bold text-dark"><i class="bi bi-paperclip me-2 text-primary"></i>File Master PO (Eksisting)</label>
                            @if($po->attachments && $po->attachments->count() > 0)
                                <div class="mb-2">
                                    @foreach($po->attachments as $file)
                                        <div class="p-2 mb-1 border rounded d-flex justify-content-between bg-light">
                                            <a href="{{ asset('storage/' . $file->file_path) }}" target="_blank" class="small text-decoration-none fw-bold"><i class="bi bi-search text-success me-1"></i> {{ $file->file_name }}</a>
                                            <a href="{{ route('po.po.delete_header_attachment', $file->id) }}" class="small text-danger" onclick="return confirm('Hapus file master ini secara permanen?')"><i class="bi bi-trash"></i></a>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="py-2 small text-muted fst-italic">Belum ada file terlampir.</div>
                            @endif

                            <label class="mt-3 form-label fw-bold text-dark"><i class="bi bi-cloud-upload me-1 text-primary"></i>Upload Tambahan Header PO</label>
                            <input type="file" name="header_attachments[]" class="border-secondary-subtle form-control form-control-sm" multiple accept=".pdf,.jpg,.jpeg,.png,.xls,.xlsx,.doc,.docx">
                        </div>
                    </div>
                </div>
            </div>

            {{-- PENGATURAN PERSETUJUAN --}}
            <div class="mb-4 border-0 shadow-sm card rounded-4 border-start border-warning">
                <div class="py-3 bg-white border-bottom card-header d-flex align-items-center">
                    <div class="p-2 me-3 bg-warning bg-opacity-10 text-warning rounded-circle d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;">
                        <i class="bi bi-diagram-3-fill"></i>
                    </div>
                    <h6 class="mb-0 fw-bold">Jalur Persetujuan Khusus (Opsional)</h6>
                </div>
                <div class="p-4 bg-light card-body rounded-bottom-4">
                    <select name="custom_workflow_id" class="form-select select2-init border-warning-subtle fw-bold text-dark">
                        <option value="">-- Ikuti Standar Departemen (Default) --</option>
                        @if(isset($customWorkflows) && count($customWorkflows) > 0)
                            @foreach($customWorkflows as $cw)
                                <option value="{{ $cw->id }}" {{ (isset($selectedWorkflowId) && $selectedWorkflowId == $cw->id) ? 'selected' : '' }}>{{ $cw->name }}</option>
                            @endforeach
                        @endif
                    </select>
                    <div class="mt-2 form-text text-muted" style="font-size: 0.75rem;">
                        <i class="bi bi-info-circle me-1"></i> Biarkan kosong jika ingin menggunakan rute standar departemen. <strong class="text-danger">Perhatian: Mengubah rute di sini saat Edit akan me-reset persetujuan atasan dari awal!</strong>
                    </div>
                </div>
            </div>

        </div>

        {{-- ================= AREA KANAN (RINGKASAN & JADWAL) ================= --}}
        <div class="col-xl-4 col-lg-5">
            <div class="overflow-hidden bg-white shadow-lg card summary-card">
                <div class="p-4 text-center text-white bg-primary">
                    <div class="mb-2 small fw-bolder text-white-50 text-uppercase" style="letter-spacing: 1.5px;">Estimasi Total PO</div>
                    <div class="d-flex justify-content-center align-items-center">
                        <span class="opacity-75 currency-label fs-5 me-2 fw-bold">{{ $po->currency }}</span>
                        <h1 class="mb-0 fw-bolder" id="lblGrandTotal" style="font-size: 2.5rem;">0</h1>
                    </div>
                </div>

                <div class="p-4 card-body">

                    <div class="p-3 mb-4 border bg-light rounded-3 border-warning-subtle">
                        <label class="mb-2 form-label small fw-bold text-dark">
                            <i class="bi bi-tags-fill me-1 text-danger"></i> Diskon Global (Header PO)
                        </label>
                        <div class="mb-1 overflow-hidden border shadow-sm input-group input-group-sm rounded-2 input-group-modern">
                            <select name="global_discount_type" id="globalDiscType" class="px-1 text-center bg-white border-0 form-select fw-bold tax-type-select" style="flex: 0 0 85px;" onchange="calculateGrandTotal()">
                                <option value="PERCENT" {{ $po->global_discount_type == 'PERCENT' ? 'selected' : '' }}>%</option>
                                <option value="FIXED" class="dynamic-currency-text" {{ $po->global_discount_type == 'FIXED' ? 'selected' : '' }}>{{ $po->currency }}</option>
                            </select>
                            <input type="number" name="global_discount_value" id="globalDiscValue" class="px-2 border-0 form-control text-end fw-bold text-danger" value="{{ (float)$po->global_discount_value }}" min="0" step="any" oninput="calculateGrandTotal()">
                        </div>
                        <input type="hidden" name="discount_total" id="globalDiscAmountHidden" value="{{ (float)$po->discount_total }}">
                    </div>

                    <div class="p-3 mb-4 border bg-light rounded-3 border-primary-subtle">
                        <label class="mb-2 form-label small fw-bold text-dark">
                            <i class="bi bi-bank me-1 text-primary"></i> Pajak Global (Header PO)
                        </label>
                        <div class="mb-1 overflow-hidden border shadow-sm input-group input-group-sm rounded-2 input-group-modern">
                            <select name="global_tax_type" id="globalTaxType" class="px-1 text-center bg-white border-0 form-select fw-bold text-secondary tax-type-select" style="flex: 0 0 85px;" onchange="toggleTaxUI(this); calculateGrandTotal()">
                                <option value="PERCENT" {{ ($po->global_tax_type ?? 'PERCENT') == 'PERCENT' ? 'selected' : '' }}>%</option>
                                <option value="FIXED" class="dynamic-currency-text" {{ ($po->global_tax_type ?? '') == 'FIXED' ? 'selected' : '' }}>{{ $po->currency }}</option>
                            </select>

                            @php
                                $isGManualPct = false; $matchedGTaxId = "";
                                if (($po->global_tax_type ?? 'PERCENT') == 'PERCENT' && (float)($po->global_tax_value ?? 0) > 0) {
                                    $gtaxMatch = collect($taxes)->where('percent', (float)$po->global_tax_value)->first();
                                    if ($gtaxMatch) $matchedGTaxId = $gtaxMatch->id; else $isGManualPct = true;
                                }
                            @endphp

                            <select class="bg-white border-0 form-select text-end fw-bold text-info tax-master-select" onchange="applyMasterTax(this); calculateGrandTotal()">
                                <option value="" data-rate="0">Tanpa Pajak -</option>
                                <option value="MANUAL_PERCENT" data-rate="0" {{ $isGManualPct ? 'selected' : '' }}>Manual (%)</option>
                                @foreach($taxes as $tax)
                                    <option value="{{ $tax->id }}" data-rate="{{ (float)$tax->percent }}" {{ $matchedGTaxId == $tax->id ? 'selected' : '' }}>{{ $tax->name }} ({{ (float)$tax->percent }}%)</option>
                                @endforeach
                            </select>

                            <input type="number" name="global_tax_value" id="globalTaxValue" class="px-2 border-0 form-control text-end fw-bold text-primary tax-val-input global-tax-val" value="{{ (float)$po->global_tax_value }}" min="0" step="any" oninput="calculateGrandTotal()">
                            <input type="hidden" id="globalTaxTypeHidden" class="tax-type-hidden" value="{{ $po->global_tax_type ?? 'NONE' }}">
                        </div>
                        <input type="hidden" name="tax_total" id="globalTaxAmountHidden" value="{{ (float)$po->tax_total }}">
                    </div>

                    <h6 class="pb-2 mb-3 text-dark fw-bold border-bottom">Rincian Kalkulasi</h6>
                    <div class="mb-2 d-flex justify-content-between small text-muted"><span>Total Bruto (Item)</span><span class="text-dark fw-bold" id="lblSubtotal">0</span></div>
                    <div class="mb-2 d-flex justify-content-between small text-danger"><span>Total Diskon Item (-)</span><span class="fw-bold" id="lblTotalItemDisc">0</span></div>
                    <div class="mb-2 d-flex justify-content-between small text-primary fw-bolder"><span>DPP (Dasar Pajak)</span><span id="lblDpp">0</span></div>
                    <div class="mb-2 d-flex justify-content-between small text-danger fw-bolder"><span>Diskon Global (-)</span><span id="lblGlobalDisc">0</span></div>
                    <div class="mb-2 d-flex justify-content-between small text-muted"><span>Total Pajak PPN (+)</span><span class="text-dark fw-bold" id="lblTax">0</span></div>
                    <div class="mb-2 d-flex justify-content-between small text-success"><span>Biaya Tambahan (+)</span><span class="fw-bold" id="lblCharges">0</span></div>
                    <div class="pb-3 mb-4 border-bottom d-flex justify-content-between small text-danger"><span>Potongan Voucher (-)</span><span class="fw-bold" id="lblExtraDisc">0</span></div>

                    <h6 class="pt-3 pb-2 mb-3 text-dark fw-bold border-bottom">Jadwal & Pembayaran</h6>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Tanggal PO <span class="text-danger">*</span></label>
                        <input type="date" name="po_date" id="poDateInput" class="form-control form-input-custom fw-bold" value="{{ \Carbon\Carbon::parse($po->po_date)->format('Y-m-d') }}" required onchange="calculateDueDate()">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Termin Pembayaran <span class="text-danger">*</span></label>
                        <select name="payment_term_id" id="paymentTermSelect" class="form-select form-input-custom fw-bold select2-init" required onchange="calculateDueDate()">
                            <option value="" data-days="0">Pilih Termin -</option>
                            @foreach($paymentTerms as $term)
                                <option value="{{ $term->id }}" data-days="{{ $term->days }}" {{ $po->payment_terms == $term->name ? 'selected' : '' }}>{{ $term->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Estimasi Kirim <span class="text-danger">*</span></label>
                        <input type="date" name="delivery_date" class="form-control form-input-custom fw-bold text-primary" value="{{ \Carbon\Carbon::parse($po->delivery_date)->format('Y-m-d') }}" required>
                    </div>
                    <div>
                        <label class="form-label small fw-bold text-danger">Jatuh Tempo Bayar</label>
                        <input type="date" name="due_date" id="dueDateInput" class="form-control form-input-custom bg-danger-subtle text-danger fw-bolder border-danger-subtle" value="{{ \Carbon\Carbon::parse($po->due_date)->format('Y-m-d') }}" readonly>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<datalist id="chargeTypeList">
    @foreach($chargeTypes as $type) <option value="{{ $type->name }}"></option> @endforeach
</datalist>
<datalist id="discountTypeList">
    @foreach($discountTypes as $type) <option value="{{ $type->name }}"></option> @endforeach
</datalist>

<template id="chargeRowTemplate">
    <tr class="border-bottom charge-row">
        <td width="55%" class="p-1 pb-2"><input type="text" name="charges[INDEX][charge_type_id]" class="form-control form-input-custom" list="chargeTypeList" placeholder="Ketik Biaya..." required></td>
        <td width="35%" class="p-1 pb-2"><input type="number" name="charges[INDEX][amount]" class="form-control form-input-custom text-end fw-bold text-success charge-input" placeholder="0" min="0" step="any" oninput="calculateGrandTotal()" required></td>
        <td width="10%" class="p-1 pb-2 text-center"><button type="button" class="p-0 mt-1 btn text-danger" onclick="removeRow(this)"><i class="bi bi-trash-fill fs-5"></i></button></td>
    </tr>
</template>

<template id="extraDiscRowTemplate">
    <tr class="border-bottom extradisc-row">
        <td width="55%" class="p-1 pb-2"><input type="text" name="extra_discounts[INDEX][discount_type_id]" class="form-control form-input-custom" list="discountTypeList" placeholder="Ketik Voucher..." required></td>
        <td width="35%" class="p-1 pb-2"><input type="number" name="extra_discounts[INDEX][amount]" class="form-control form-input-custom text-end fw-bold text-danger extradisc-input" placeholder="0" min="0" step="any" oninput="calculateGrandTotal()" required></td>
        <td width="10%" class="p-1 pb-2 text-center"><button type="button" class="p-0 mt-1 btn text-danger" onclick="removeRow(this)"><i class="bi bi-trash-fill fs-5"></i></button></td>
    </tr>
</template>

@if(empty($po->purchase_request_id))
<template id="newItemTemplate">
    <div class="mb-4 border-0 shadow-sm card item-row new-item-row" style="border-radius: 12px; overflow: hidden;">
        <div class="px-4 py-3 bg-light border-bottom card-header d-flex justify-content-between align-items-center">
            <div class="w-50">
                <select name="new_items[NEW_INDEX][item_id]" class="form-select new-item-select" required onchange="populateNewItemData(this, 'NEW_INDEX')">
                    <option value="">-- Cari Barang --</option>
                    @if(isset($masterItems))
                        @foreach($masterItems as $mItem)
                            <option value="{{ $mItem->id }}" data-name="{{ $mItem->name }}" data-uom="{{ optional($mItem->uom)->name }}" data-uom-id="{{ $mItem->uom_id }}">{{ $mItem->name }} ({{ $mItem->code }})</option>
                        @endforeach
                    @endif
                </select>
                <input type="hidden" name="new_items[NEW_INDEX][vendor_id]" value="{{ $po->vendor_id }}">
            </div>
            <div class="gap-3 text-end d-flex align-items-center justify-content-end">
                <div>
                    <div class="mb-1 small text-muted fw-bold text-uppercase">Netto Item</div>
                    <h5 class="mb-0 text-primary fw-bolder"><span class="currency-label fs-6 text-muted me-1">{{ $po->currency }}</span><span class="subtotal-display">0.00</span></h5>
                    <input type="hidden" class="subtotal-input" value="0">
                </div>
                <button type="button" class="p-2 btn btn-outline-danger btn-sm rounded-circle" onclick="removeNewItemRow(this)"><i class="bi bi-trash-fill"></i></button>
            </div>
        </div>

        <div class="p-4 card-body">
            <div class="pb-4 mb-4 row g-3 border-bottom">
                <div class="col-md-7">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Nama Barang di PO (Opsional)</label>
                        {{-- 🔥 ID DITAMBAHKAN AGAR BISA DIISI OTOMATIS OLEH JAVASCRIPT 🔥 --}}
                        <input type="text" name="new_items[NEW_INDEX][item_name_override]" id="new_item_name_override_NEW_INDEX" class="form-control form-input-custom fw-bold text-primary" placeholder="Ketik nama spesifik barang...">
                    </div>
                    <label class="form-label small fw-bold text-dark">Spesifikasi Detail</label>
                    <textarea name="new_items[NEW_INDEX][notes]" id="new_spec_NEW_INDEX" class="form-control new-ckeditor-spec"></textarea>
                </div>

                {{-- 🔥 KOTAK UPLOAD FILE DITAMBAHKAN KEMBALI 🔥 --}}
                <div class="col-md-5">
                    <label class="form-label small fw-bold text-dark"><i class="bi bi-paperclip text-primary"></i> Dokumen Pendukung Item</label>
                    <div class="p-3 border rounded shadow-sm bg-light border-secondary-subtle">
                        <div id="fileListContainer_new_NEW_INDEX" class="gap-1 mb-2 d-flex flex-column"></div>
                        <div id="hiddenFileInputs_new_NEW_INDEX" style="display: none;"></div>
                        <button type="button" class="py-2 mb-3 bg-white btn btn-sm btn-outline-primary w-100 fw-bold" style="border-style: dashed; border-width: 2px;" onclick="triggerFilePicker('new_NEW_INDEX')">
                            <i class="bi bi-plus-lg me-1"></i> Tambah File Baru
                        </button>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-dark">Qty & Satuan <span class="text-danger">*</span></label>
                    <div class="mb-1 shadow-sm input-group-modern">
                        <input type="number" name="new_items[NEW_INDEX][qty]" class="text-center form-control fw-bolder qty-input text-primary" value="1" min="0.01" step="0.01" oninput="calculateRow(this)" required>
                    </div>
                    <select name="new_items[NEW_INDEX][uom_id]" id="new_uom_select_NEW_INDEX" class="shadow-sm form-select border-primary text-primary fw-bold uom-selector" onchange="updateRowUom(this, 'NEW_INDEX')">
                        <option value="">Satuan Dasar</option>
                    </select>
                    <input type="hidden" name="new_items[NEW_INDEX][uom]" id="uom-name-NEW_INDEX" value="">
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-bold text-dark">Harga Satuan <span class="text-danger">*</span></label>
                    <div class="shadow-sm input-group-modern">
                        <span class="input-group-text currency-label">{{ $po->currency }}</span>
                        <input type="number" name="new_items[NEW_INDEX][unit_price]" class="form-control text-end fw-bold price-input" value="0" min="0" step="any" oninput="calculateRow(this)" required>
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-bold text-dark">Diskon per Item</label>
                    <div class="shadow-sm input-group-modern">
                        <select name="new_items[NEW_INDEX][discount_type]" class="text-center form-select fw-bold text-secondary disc-type" style="flex: 0 0 85px;" onchange="calculateRow(this)">
                            <option value="PERCENT">%</option>
                            <option value="FIXED" class="dynamic-currency-text">{{ $po->currency }}</option>
                        </select>
                        <input type="number" name="new_items[NEW_INDEX][discount_value]" class="form-control text-end fw-bold text-danger disc-val" value="0" min="0" step="any" oninput="calculateRow(this)">
                    </div>
                    <input type="hidden" name="new_items[NEW_INDEX][discount_amount]" class="disc-amt-hidden" value="0">
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-bold text-dark">Pajak (VAT/PPN)</label>
                    <div class="shadow-sm input-group-modern">
                        <select name="new_items[NEW_INDEX][tax_type]" class="text-center form-select fw-bold text-secondary tax-type-select" style="flex: 0 0 85px;" onchange="toggleTaxUI(this); calculateRow(this)">
                            <option value="PERCENT">%</option>
                            <option value="FIXED" class="dynamic-currency-text">{{ $po->currency }}</option>
                        </select>
                        <select name="new_items[NEW_INDEX][tax_id]" class="form-select text-end fw-bold text-info tax-master-select" onchange="applyMasterTax(this); calculateRow(this)">
                            <option value="MANUAL_PERCENT" data-rate="0">Manual (%)</option>
                            @foreach($taxes as $tax)
                                <option value="{{ $tax->id }}" data-rate="{{ (float)$tax->percent }}">{{ $tax->name }} ({{ (float)$tax->percent }}%)</option>
                            @endforeach
                        </select>
                        <input type="number" name="new_items[NEW_INDEX][tax_value]" class="form-control text-end fw-bold text-info tax-val-input d-none" value="0" min="0" step="any" oninput="calculateRow(this)">
                    </div>
                    <input type="hidden" name="new_items[NEW_INDEX][tax_amount]" class="tax-amt-hidden" value="0">
                </div>
            </div>
        </div>
    </div>
</template>
@endif

@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>

<script>
    var chargeIdx = 500;
    var discIdx = 500;
    var newItemIdx = 1000;
    var myEditors = {};

    function initCKEditor(selectorId) {
        var domElement = document.querySelector('#' + selectorId);
        if (domElement && !domElement.ckeditorInstance) {
            ClassicEditor.create(domElement, { toolbar: [ 'heading', '|', 'bold', 'italic', 'bulletedList', 'numberedList', 'blockQuote', '|', 'undo', 'redo' ] })
                .then(function(editor) { myEditors[selectorId] = editor; domElement.ckeditorInstance = editor; })
                .catch(function(err) { console.error(err); });
        }
    }

    function updateShippingAddress() {
        var select = document.getElementById('billToSelect');
        var address = select.options[select.selectedIndex].getAttribute('data-address');
        if (address) document.getElementById('shippingAddressInput').value = address;
    }

    function calculateDueDate() {
        var poDateVal = document.getElementById('poDateInput').value;
        var termSelect = document.getElementById('paymentTermSelect');
        var daysToAdd = parseInt(termSelect.options[termSelect.selectedIndex].getAttribute('data-days')) || 0;
        if(poDateVal && daysToAdd > 0) {
            var poDate = new Date(poDateVal);
            poDate.setDate(poDate.getDate() + daysToAdd);
            document.getElementById('dueDateInput').value = poDate.toISOString().split('T')[0];
        } else if(poDateVal) document.getElementById('dueDateInput').value = poDateVal;
    }

    function addChargeRow() {
        chargeIdx++;
        var templateHtml = document.getElementById('chargeRowTemplate').innerHTML;
        var finalHtml = templateHtml.split('INDEX').join(chargeIdx);
        document.getElementById('chargesContainer').insertAdjacentHTML('beforeend', finalHtml);
    }

    function addExtraDiscRow() {
        discIdx++;
        var templateHtml = document.getElementById('extraDiscRowTemplate').innerHTML;
        var finalHtml = templateHtml.split('INDEX').join(discIdx);
        document.getElementById('extraDiscContainer').insertAdjacentHTML('beforeend', finalHtml);
    }

    function removeRow(btn) { btn.closest('tr').remove(); calculateGrandTotal(); }

    function addNewItemRow() {
        newItemIdx++;
        var templateHtml = document.getElementById('newItemTemplate').innerHTML;
        var finalHtml = templateHtml.split('NEW_INDEX').join(newItemIdx);
        document.getElementById('itemsContainer').insertAdjacentHTML('afterbegin', finalHtml);

        $('select[name="new_items[' + newItemIdx + '][item_id]"]').select2({ theme: 'bootstrap-5', width: '100%' });
        initCKEditor('new_spec_' + newItemIdx);
        updateCurrencySymbol();
    }

    function removeNewItemRow(btn) {
        btn.closest('.new-item-row').remove();
        calculateGrandTotal();
    }

    function removeExistingItemRow(btn) {
        var row = btn.closest('.item-row');
        row.querySelector('.is-deleted-flag').value = "1";
        row.querySelector('.qty-input').value = 0;
        row.querySelector('.price-input').value = 0;
        row.querySelector('.tax-val-input').value = 0;
        row.querySelector('.disc-val').value = 0;
        row.style.display = 'none';
        calculateRow(row.querySelector('.qty-input'));
    }

    // 🔥 LOGIKA UNTUK MENYALIN NAMA BARANG OTOMATIS KE KOTAK INPUT 🔥
    function populateNewItemData(selectEl, index) {
        var selected = selectEl.options[selectEl.selectedIndex];

        // Ambil data dari tag HTML <option> yang dipilih
        var baseUomName = selected.getAttribute('data-uom') || 'PCS';
        var baseUomId = selected.getAttribute('data-uom-id') || '';
        var rawItemName = selected.getAttribute('data-name') || '';

        // Set Satuan (UOM)
        document.getElementById('uom-name-' + index).value = baseUomName;
        var uomSelect = document.getElementById('new_uom_select_' + index);
        uomSelect.innerHTML = '<option value="' + baseUomId + '" data-name="' + baseUomName + '" data-conv="1">' + baseUomName + ' (Dasar)</option>';

        // Set Override Nama Barang (Agar otomatis terisi)
        var overrideInput = document.getElementById('new_item_name_override_' + index);
        if(overrideInput) {
            overrideInput.value = rawItemName;
        }
    }

    function updateRowUom(selectEl, index) {
        var selectedOption = selectEl.options[selectEl.selectedIndex];
        var newConvRate = parseFloat(selectedOption.getAttribute('data-conv')) || 1;
        var newUomName = selectedOption.getAttribute('data-name') || '';

        var uomNameInput = document.getElementById('uom-name-' + index);
        if(uomNameInput) uomNameInput.value = newUomName;

        var qtyInput = document.getElementById('qty-input-' + index);
        var sisaBaseQty = parseFloat(qtyInput.getAttribute('data-base-remaining')) || 0;

        var helpText = document.getElementById('max-val-' + index);
        if(helpText && sisaBaseQty < 999999) {
            var newMaxVal = sisaBaseQty / newConvRate;
            qtyInput.max = newMaxVal;
            qtyInput.value = parseFloat(newMaxVal.toFixed(2));
            helpText.innerText = newMaxVal.toFixed(2);
        }

        selectEl.setAttribute('data-current-conv', newConvRate);
        calculateRow(qtyInput);
    }

    function triggerFilePicker(index) {
        var fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.name = 'po_items[' + index + '][attachments][]';
        fileInput.multiple = true;
        fileInput.accept = '.pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx';

        fileInput.onchange = function() {
            if(this.files.length > 0) {
                var hiddenContainer = document.getElementById('hiddenFileInputs_' + index);
                var listContainer = document.getElementById('fileListContainer_' + index);
                var inputId = 'fileInput_' + Date.now() + Math.random().toString(36).substr(2, 5);

                this.id = inputId;
                hiddenContainer.appendChild(this);

                Array.from(this.files).forEach(function(file, fileIndex) {
                    var pillId = 'pill_' + inputId + '_' + fileIndex;
                    var pillHTML = '<div id="' + pillId + '" class="p-1 mt-1 bg-white border shadow-sm file-pill d-flex align-items-center justify-content-between rounded-3">' +
                        '<div class="overflow-hidden d-flex align-items-center">' +
                            '<div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex justify-content-center align-items-center me-2" style="width: 25px; height: 25px; font-size: 0.7rem;"><i class="bi bi-file-earmark-text-fill"></i></div>' +
                            '<div class="text-truncate"><div class="fw-bold text-dark text-truncate" style="max-width: 100px; font-size: 0.65rem;" title="' + file.name + '">' + file.name + '</div></div>' +
                        '</div>' +
                        '<button type="button" class="p-0 btn btn-link text-danger ms-1" onclick="removeSpecificFile(\'' + inputId + '\', ' + fileIndex + ', \'' + pillId + '\')" title="Hapus File"><i class="bi bi-x-circle-fill"></i></button>' +
                    '</div>';
                    listContainer.insertAdjacentHTML('beforeend', pillHTML);
                });
            }
        };
        fileInput.click();
    }

    function removeSpecificFile(inputId, fileIndexToRemove, pillId) {
        var inputEle = document.getElementById(inputId);
        if(inputEle) {
            var dt = new DataTransfer();
            var files = inputEle.files;
            for(var i = 0; i < files.length; i++) {
                if(i !== fileIndexToRemove) dt.items.add(files[i]);
            }
            inputEle.files = dt.files;
            if(inputEle.files.length === 0) inputEle.remove();
        }
        var pill = document.getElementById(pillId);
        pill.style.opacity = '0';
        setTimeout(function() { pill.remove(); }, 200);
    }

    function calculateRow(el) {
        var row = el.closest('.item-row');
        var qty = parseFloat(row.querySelector('.qty-input').value) || 0;
        var price = parseFloat(row.querySelector('.price-input').value) || 0;
        var gross = qty * price;

        var discType = row.querySelector('.disc-type').value;
        var discVal = parseFloat(row.querySelector('.disc-val').value) || 0;
        var discAmt = (discType === 'PERCENT') ? (gross * discVal / 100) : discVal;
        row.querySelector('.disc-amt-hidden').value = discAmt;

        var dpp = gross - discAmt;

        var taxTypeHidden = row.querySelector('.tax-type-hidden');
        var taxType = taxTypeHidden ? taxTypeHidden.value : 'NONE';
        var taxVal = parseFloat(row.querySelector('.tax-val-input').value) || 0;
        var taxAmt = 0;

        if (taxType === 'PERCENT') {
            taxAmt = dpp * taxVal / 100;
        } else if (taxType === 'FIXED') {
            taxAmt = taxVal;
        }
        row.querySelector('.tax-amt-hidden').value = taxAmt;

        var subtotal = dpp + taxAmt;
        row.querySelector('.subtotal-input').value = subtotal;
        row.querySelector('.subtotal-display').innerText = formatCurrency(subtotal);
        calculateGrandTotal();
    }

    function calculateGrandTotal() {
        var totalGross = 0; var totalItemDisc = 0; var totalTax = 0;
        document.querySelectorAll('.item-row').forEach(function(row) {
            var isDeletedFlag = row.querySelector('.is-deleted-flag');
            if(!isDeletedFlag || isDeletedFlag.value === "0"){
                totalGross += (parseFloat(row.querySelector('.qty-input').value) || 0) * (parseFloat(row.querySelector('.price-input').value) || 0);
                totalItemDisc += parseFloat(row.querySelector('.disc-amt-hidden').value) || 0;
                totalTax += parseFloat(row.querySelector('.tax-amt-hidden').value) || 0;
            }
        });

        var totalCharges = 0; document.querySelectorAll('.charge-input').forEach(function(i) { totalCharges += parseFloat(i.value) || 0; });
        var totalExtraDisc = 0; document.querySelectorAll('.extradisc-input').forEach(function(i) { totalExtraDisc += parseFloat(i.value) || 0; });

        var dpp = totalGross - totalItemDisc;

        var globalDiscType = document.getElementById('globalDiscType').value;
        var globalDiscVal = parseFloat(document.getElementById('globalDiscValue').value) || 0;
        var globalDiscAmt = (globalDiscType === 'PERCENT') ? (dpp * globalDiscVal / 100) : globalDiscVal;

        var hiddenDiscTotal = document.getElementById('globalDiscAmountHidden');
        if(hiddenDiscTotal) hiddenDiscTotal.value = globalDiscAmt;

        var dppAfterGlobalDisc = dpp - globalDiscAmt;

        var globalTaxTypeHidden = document.getElementById('globalTaxTypeHidden');
        var globalTaxType = globalTaxTypeHidden ? globalTaxTypeHidden.value : 'NONE';
        var globalTaxVal = parseFloat(document.getElementById('globalTaxValue').value) || 0;
        var globalTaxAmt = 0;

        if (globalTaxType === 'PERCENT') {
            globalTaxAmt = dppAfterGlobalDisc * globalTaxVal / 100;
        } else if (globalTaxType === 'FIXED') {
            globalTaxAmt = globalTaxVal;
        }

        var hiddenTaxTotal = document.getElementById('globalTaxAmountHidden');
        if(hiddenTaxTotal) hiddenTaxTotal.value = globalTaxAmt;

        var grandTotal = dppAfterGlobalDisc + totalTax + globalTaxAmt + totalCharges - totalExtraDisc;

        document.getElementById('lblSubtotal').innerText = formatCurrency(totalGross);
        document.getElementById('lblTotalItemDisc').innerText = "-" + formatCurrency(totalItemDisc);
        document.getElementById('lblDpp').innerText = formatCurrency(dpp);
        document.getElementById('lblGlobalDisc').innerText = "-" + formatCurrency(globalDiscAmt);
        document.getElementById('lblTax').innerText = "+" + formatCurrency(totalTax + globalTaxAmt);
        document.getElementById('lblCharges').innerText = "+" + formatCurrency(totalCharges);
        document.getElementById('lblExtraDisc').innerText = "-" + formatCurrency(totalExtraDisc);
        document.getElementById('lblGrandTotal').innerText = formatCurrency(grandTotal);
    }

    function formatCurrency(amount) { return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(amount); }

    function updateCurrencySymbol() {
        var currencySelect = document.getElementById('currencySelect');
        if(!currencySelect) return;
        var currency = currencySelect.value;
        document.querySelectorAll('.currency-label').forEach(function(el) { el.innerText = currency; });
        document.querySelectorAll('.dynamic-currency-text').forEach(function(opt) { opt.innerText = currency; });
    }

    function toggleTaxUI(typeSelect, isInit) {
        if (typeof isInit === 'undefined') { isInit = false; }
        var container = typeSelect.closest('.input-group-modern');
        if(!container) return;

        var masterSelect = container.querySelector('.tax-master-select');
        var valInput = container.querySelector('.tax-val-input');
        var typeHidden = container.querySelector('.tax-type-hidden');

        if(!masterSelect || !valInput) return;

        var selectedOpt = typeSelect.options[typeSelect.selectedIndex];
        var type = selectedOpt.value;

        if(typeHidden) typeHidden.value = type;

        if (type === 'PERCENT') {
            masterSelect.classList.remove('d-none');
            if (masterSelect.value === 'MANUAL_PERCENT') {
                masterSelect.style.flex = '0 0 110px';
                valInput.classList.remove('d-none');
                if(!isInit) valInput.value = 0;
            } else {
                masterSelect.style.flex = '1 1 auto';
                valInput.classList.add('d-none');
                if(!isInit) valInput.value = masterSelect.options[masterSelect.selectedIndex].getAttribute('data-rate') || 0;
            }
        } else {
            masterSelect.classList.add('d-none');
            valInput.classList.remove('d-none');
            if(!isInit) valInput.value = 0;
        }
    }

    function applyMasterTax(masterSelect) {
        var container = masterSelect.closest('.input-group-modern');
        var valInput = container.querySelector('.tax-val-input');

        if (masterSelect.value === 'MANUAL_PERCENT') {
            masterSelect.style.flex = '0 0 110px';
            valInput.classList.remove('d-none');
            valInput.value = 0;
            valInput.focus();
        } else {
            masterSelect.style.flex = '1 1 auto';
            valInput.classList.add('d-none');
            valInput.value = masterSelect.options[masterSelect.selectedIndex].getAttribute('data-rate') || 0;
        }
    }

    $(document).ready(function() {
        $('.select2-init').select2({ theme: 'bootstrap-5', width: '100%' });
        document.querySelectorAll('.ckeditor-spec').forEach(function(ta) { initCKEditor(ta.id); });
        updateCurrencySymbol();
        document.querySelectorAll('.tax-type-select').forEach(function(el) { toggleTaxUI(el, true); });
        document.querySelectorAll('.item-row').forEach(function(row) { calculateRow(row.querySelector('.qty-input')); });
        calculateGrandTotal();
    });

    document.getElementById('poForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var form = this;

        for (var editorId in myEditors) {
            if (myEditors[editorId]) myEditors[editorId].updateSourceElement();
        }

        if(!this.checkValidity()) {
            var invalidElements = this.querySelectorAll(':invalid');
            if(invalidElements.length > 0) {
                var firstInvalid = invalidElements[0];
                invalidElements.forEach(function(el) {
                    el.classList.add('is-invalid');
                    el.addEventListener('input', function() { this.classList.remove('is-invalid'); }, {once: true});
                    el.addEventListener('change', function() { this.classList.remove('is-invalid'); }, {once: true});
                });

                Swal.fire({
                    title: 'Data Belum Lengkap!',
                    text: 'Ada kolom wajib yang belum diisi.',
                    icon: 'error',
                    confirmButtonColor: '#dc3545',
                    confirmButtonText: 'Oke, Saya Perbaiki'
                }).then(function() {
                    firstInvalid.scrollIntoView({behavior: 'smooth', block: 'center'});
                    firstInvalid.focus();
                });
            }
            return;
        }

        var prItemTotals = {};
        var isValidSisa = true;
        var errorMessage = '';

        document.querySelectorAll('.item-row').forEach(function(row) {
            var isDeletedFlag = row.querySelector('.is-deleted-flag');
            if(isDeletedFlag && isDeletedFlag.value === "1") return;

            var prItemIdInput = row.querySelector('input[name$="[pr_item_id]"]');
            if (prItemIdInput && prItemIdInput.value !== "") {
                var prItemId = prItemIdInput.value;
                var itemName = row.querySelector('.fw-bolder.text-dark') ? row.querySelector('.fw-bolder.text-dark').innerText : 'Item Baru';
                var qtyInput = row.querySelector('.qty-input');
                var uomSelect = row.querySelector('.uom-selector');

                var qty = parseFloat(qtyInput.value) || 0;
                var convRate = parseFloat(uomSelect.getAttribute('data-current-conv')) || 1;
                var baseRemaining = parseFloat(qtyInput.getAttribute('data-base-remaining')) || 0;

                var baseQtyOrdered = qty * convRate;

                if(!prItemTotals[prItemId]) {
                    prItemTotals[prItemId] = { total: 0, max: baseRemaining, name: itemName };
                }
                prItemTotals[prItemId].total += baseQtyOrdered;
            }
        });

        for (var prItemId in prItemTotals) {
            var data = prItemTotals[prItemId];
            if (data.max < 999999 && parseFloat(data.total.toFixed(4)) > parseFloat(data.max.toFixed(4))) {
                isValidSisa = false;
                errorMessage += 'Barang <b>' + data.name + '</b> melebihi sisa jatah PR!<br><small>Total Input Anda: ' + data.total + ' (Eceran) <br>Jatah Tersedia: Hanya ' + data.max + ' (Eceran)</small><br><br>';
            }
        }

        if(!isValidSisa) {
            $('input[type="file"]').prop('disabled', false);
            Swal.fire({
                title: 'Kuantitas Melebihi PR!',
                html: errorMessage + '<span class="text-danger fw-bold" style="font-size:0.85rem;">Silakan kurangi angka pada item tersebut.</span>',
                icon: 'error',
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Oke, Saya Revisi'
            });
            return;
        }

        $('input[type="file"]').each(function() {
            if ($(this).val() === '') $(this).prop('disabled', true);
        });

        Swal.fire({
            title: 'Simpan Perubahan?',
            text: "Perubahan akan disimpan permanen.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#198754',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Simpan!'
        }).then(function(result) {
            if (result.isConfirmed) {
                Swal.fire({ title: 'Menyimpan...', allowOutsideClick: false, didOpen: function() { Swal.showLoading() } });
                form.submit();
            } else {
                $('input[type="file"]').prop('disabled', false);
            }
        });
    });
</script>
@endpush
