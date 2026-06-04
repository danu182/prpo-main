@extends('layouts.app')

@push('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<style>
    /* Styling Select2 */
    .select2-container--bootstrap-5 .select2-selection {
        border-radius: 8px;
        min-height: 40px;
        font-size: 0.85rem;
        border-color: #dee2e6;
    }
    .select2-container--bootstrap-5.select2-container--focus .select2-selection {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
    }
    
    /* Modern Input Styling */
    .form-input-custom {
        border: 1px solid #dee2e6;
        border-radius: 8px;
        font-size: 0.85rem;
        min-height: 40px;
        transition: all 0.2s;
    }
    .form-input-custom:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
        background-color: #fff;
    }

    /* Input Group Modern */
    .input-group-modern {
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid #dee2e6;
        display: flex;
    }
    .input-group-modern:focus-within {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
    }
    .input-group-modern input, .input-group-modern select, .input-group-modern .input-group-text {
        border: none !important;
        background: transparent;
    }
    .input-group-modern .input-group-text {
        background-color: #f8f9fa;
        color: #6c757d;
        font-weight: 600;
        border-right: 1px solid #dee2e6 !important;
    }

    /* Fixed Sidebar Summary */
    .summary-card {
        position: sticky;
        top: 20px;
        border-radius: 16px;
        border: 1px solid #e9ecef;
    }

    /* CSS VALIDASI ERROR */
    .is-invalid {
        border-color: #dc3545 !important;
        background-color: #fff8f8 !important;
    }
    .input-group-modern:has(.is-invalid) {
        border-color: #dc3545 !important;
        box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25) !important;
    }

    /* 🔥 STYLING KHUSUS CKEDITOR 🔥 */
    .ck-editor__editable_inline {
        min-height: 120px;
        font-size: 0.85rem;
    }

    input[type=number]::-webkit-inner-spin-button,
    input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
</style>
@endpush

@section('content')

{{-- Pastikan method di web.php untuk rute ini adalah PUT (Atau hapus @method('PUT') jika di web.php pakai POST) --}}
<form action="{{ route('po.update', $po->po_number) }}" method="POST" id="poForm" enctype="multipart/form-data" novalidate>
    @csrf

    {{-- HEADER --}}
    <div class="mb-4 d-flex justify-content-between align-items-center border-bottom pb-3">
        <div>
            <h4 class="mb-1 fw-bolder text-dark"><i class="bi bi-pencil-square me-2 text-warning"></i> Edit Purchase Order</h4>
            <div class="text-muted small">Mengubah dokumen PO <strong class="text-primary">{{ $po->po_number }}</strong>.</div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('po.index') }}" class="btn btn-light border fw-bold rounded-pill px-4"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
            <button type="submit" class="btn btn-success fw-bold rounded-pill px-4"><i class="bi bi-save-fill me-1"></i> Simpan Perubahan</button>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger rounded-4 shadow-sm border-0 mb-4">
            <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i>Gagal Menyimpan:</div>
            <ul class="mb-0 small">
                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-4">
        {{-- ================= AREA KIRI (FORM UTAMA) ================= --}}
        <div class="col-xl-8 col-lg-7">

            {{-- 1. INFORMASI VENDOR & PENAGIHAN --}}
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-2 me-3 d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;">
                        <i class="bi bi-geo-alt-fill"></i>
                    </div>
                    <h6 class="mb-0 fw-bold">Penagihan & Pengiriman</h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        <div class="col-md-5">
                            <label class="form-label small fw-bold text-dark">Vendor Penerima PO</label>
                            <div class="p-2 border rounded bg-light fw-bold text-primary"><i class="bi bi-shop me-1"></i> {{ optional($po->vendor)->name ?? 'Vendor Tidak Ditemukan' }}</div>
                            <div class="form-text text-danger mt-1" style="font-size: 0.65rem;">*Vendor tidak bisa diubah. Jika ganti vendor, silakan Batalkan PO ini.</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-dark">Mata Uang <span class="text-danger">*</span></label>
                            <select name="currency" id="currencySelect" class="form-select bg-light fw-bold text-primary form-input-custom" required onchange="updateCurrencySymbol()">
                                @foreach($currencies as $curr)
                                    <option value="{{ $curr->code }}" {{ $curr->code == $po->currency ? 'selected' : '' }}>{{ $curr->code }} - {{ $curr->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-dark">Tagihan Ke (Bill To) <span class="text-danger">*</span></label>
                            <select name="billing_company_id" id="billToSelect" class="form-select select2-init" required onchange="updateShippingAddress()">
                                @foreach($companies as $c)
                                    <option value="{{ $c->id }}" data-address="{{ $c->address ?? '' }}" {{ $c->id == $po->bill_to_company_id ? 'selected' : '' }}>{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-end mb-2">
                                <label class="form-label small fw-bold text-dark mb-0">Lokasi Pengiriman (Ship To) <span class="text-danger">*</span></label>
                                <button type="button" class="btn btn-sm btn-link text-decoration-none p-0" onclick="updateShippingAddress(true)"><i class="bi bi-arrow-counterclockwise"></i> Reset ke Alamat PT</button>
                            </div>
                            <textarea name="shipping_address" id="shippingAddressInput" rows="2" class="form-control form-input-custom bg-light" required>{{ old('shipping_address', $po->shipping_address) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 2. DETAIL PESANAN BARANG --}}
            <h5 class="fw-bolder text-dark mb-3 mt-5"><i class="bi bi-box-seam me-2 text-primary"></i>Daftar Barang Pesanan</h5>
            
            <div id="itemsContainer">
                @foreach($po->items as $item)
                    @php
                        $prItem = \App\Models\PurchaseRequestItem::with('item.itemUoms')->find($item->purchase_request_item_id);
                        $masterItem = $item->item;
                        $baseUomName = optional($masterItem->uom)->name ?? 'PCS';

                        // CARI UOM ASLI PR & KONVERSINYA
                        $prConvRate = 1;
                        $safePrUom = $baseUomName;

                        if ($prItem) {
                            if (!empty($prItem->uom_id)) {
                                $altUom = collect(optional($masterItem)->itemUoms)->where('id', $prItem->uom_id)->first();
                                if ($altUom) {
                                    $prConvRate = (float) $altUom->conversion_qty;
                                    $safePrUom = $altUom->uom_name . ' (Isi: ' . $prConvRate . ')';
                                } else {
                                    $safePrUom = optional(optional($masterItem)->uom)->name ?? 'PCS';
                                }
                            } else {
                                $raw = $prItem->getRawOriginal('uom') ?? $baseUomName;
                                if (is_string($raw) && !str_starts_with(trim($raw), '{')) {
                                    $safePrUom = $raw;
                                    if (preg_match('/\(Isi:\s*([0-9.]+)\)/i', $raw, $matches)) {
                                        $prConvRate = (float) $matches[1];
                                    }
                                }
                            }
                        }

                        // CARI UOM PO SAAT INI & KONVERSINYA
                        $currentConvRate = 1;
                        $safeCurrentUom = $item->getRawOriginal('uom') ?? $baseUomName;
                        
                        if (is_string($safeCurrentUom) && str_starts_with(trim($safeCurrentUom), '{')) {
                            $safeCurrentUom = $baseUomName;
                        }

                        if (preg_match('/\(Isi:\s*([0-9.]+)\)/i', $safeCurrentUom, $matches)) {
                            $currentConvRate = (float) $matches[1];
                        } else {
                            $cleanUom = trim(preg_replace('/ \(Isi:.*\)/i', '', $safeCurrentUom));
                            $alt = collect(optional($masterItem)->itemUoms)->where('uom_name', $cleanUom)->first();
                            if ($alt) {
                                $currentConvRate = (float) $alt->conversion_qty;
                                $safeCurrentUom = $alt->uom_name . ' (Isi: ' . $currentConvRate . ')';
                            }
                        }

                        // KALKULATOR SISA JATAH PR
                        $targetBaseQty = $prItem ? ((float)$prItem->qty * $prConvRate) : ((float)$item->qty_ordered * $currentConvRate);
                        $orderedBaseQty = $prItem ? (float)($prItem->ordered_qty ?? 0) : ((float)$item->qty_ordered * $currentConvRate);
                        $currentPoBaseQty = (float)$item->qty_ordered * $currentConvRate;
                        
                        $sisaBaseQty = max(0, $targetBaseQty - $orderedBaseQty) + $currentPoBaseQty;
                        $remainingNominal = $currentConvRate > 0 ? ($sisaBaseQty / $currentConvRate) : 0;
                        $remainingNominal = round($remainingNominal, 2);
                    @endphp

                    <div class="card mb-4 shadow-sm item-row border-0" style="border-radius: 12px; overflow: hidden;">
                        <div class="card-header bg-light border-bottom d-flex justify-content-between align-items-center py-3 px-4">
                            <div>
                                <div class="fw-bolder text-dark fs-6">{{ optional($item->item)->name ?? 'Item Terhapus' }}</div>
                                <span class="badge bg-secondary-subtle text-secondary border mt-1">{{ optional($item->item)->code }}</span>
                                <input type="hidden" name="po_items[{{ $item->id }}][pr_item_id]" value="{{ $item->purchase_request_item_id }}">
                            </div>
                            <div class="text-end">
                                <div class="small text-muted fw-bold text-uppercase mb-1">Netto Item</div>
                                <h5 class="fw-bolder text-primary mb-0"><span class="currency-label fs-6 text-muted me-1">{{ $po->currency }}</span><span class="subtotal-display">{{ number_format($item->subtotal + $item->tax_amount, 2, '.', '') }}</span></h5>
                                <input type="hidden" class="subtotal-input" value="{{ $item->subtotal + $item->tax_amount }}">
                            </div>
                        </div>

                        <div class="card-body p-4">
                            
                            {{-- BARIS 1: Spesifikasi & File Upload --}}
                            <div class="row g-3 mb-4 pb-4 border-bottom">
                                <div class="col-md-7">
                                    <label class="form-label small fw-bold text-dark">Spesifikasi Detail (Bisa diedit)</label>
                                    <textarea name="po_items[{{ $item->id }}][notes]" id="spec_{{ $item->id }}" class="form-control ckeditor-spec">{!! $item->description !!}</textarea>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small fw-bold text-dark"><i class="bi bi-paperclip text-primary"></i> Dokumen Pendukung Item</label>
                                    <div class="p-3 border rounded bg-light border-secondary-subtle shadow-sm">
                                        
                                        <div class="mb-2">
                                            <span class="small fw-bold text-muted">File Lampiran PO:</span>
                                        </div>
                                        
                                        {{-- 🔥 TAMPILKAN FILE YANG SUDAH TERSIMPAN DI SINI 🔥 --}}
                                        @if(isset($item->raw_attachments) && count($item->raw_attachments) > 0)
                                            <div class="d-flex flex-column gap-1 mb-2">
                                                @foreach($item->raw_attachments as $file)
                                                    <div class="bg-white border border-info border-opacity-50 rounded p-1 shadow-sm d-flex justify-content-between align-items-center">
                                                        <a href="{{ asset('storage/' . $file->file_path) }}" target="_blank" class="text-truncate small text-decoration-none text-primary fw-bold ms-1" style="font-size: 0.7rem; max-width: 80%;" title="{{ $file->file_name }}">
                                                            <i class="bi bi-file-earmark-text-fill text-danger me-1"></i> {{ $file->file_name }}
                                                        </a>
                                                        {{-- 🔥 TOMBOL HAPUS LAMPIRAN ITEM 🔥 --}}
                                                        <a href="{{ route('po.po.delete_item_attachment', $file->id) }}" class="btn btn-sm text-danger p-0 px-1" onclick="return confirm('Hapus lampiran ini secara permanen?')">
                                                            <i class="bi bi-trash-fill"></i>
                                                        </a>
                                                    </div>
                                                @endforeach
                                            </div>
                                            <hr class="my-2 border-secondary-subtle">
                                        @endif

                                        {{-- Tempat file baru yang akan diupload --}}
                                        <div id="fileListContainer_{{ $item->id }}" class="d-flex flex-column gap-1 mb-2"></div>
                                        <div id="hiddenFileInputs_{{ $item->id }}" style="display: none;"></div>
                                        
                                        <button type="button" class="btn btn-sm btn-outline-primary w-100 fw-bold py-2 mb-3 bg-white" style="border-style: dashed; border-width: 2px;" onclick="triggerFilePicker('{{ $item->id }}')">
                                            <i class="bi bi-plus-lg me-1"></i> Tambah File Baru
                                        </button>

                                        {{-- AREA REFERENSI PR (KARTU MINI ELEGAN) --}}
                                        @php
                                            $prQuotes = \App\Models\PurchaseRequestItemVendor::with('vendor', 'attachments')->where('pr_item_id', $item->purchase_request_item_id)->get();
                                        @endphp
                                        @if($prQuotes && $prQuotes->count() > 0)
                                        <div class="pt-2 border-top">
                                            <button class="btn btn-outline-secondary btn-sm w-100 rounded-3 fw-bold d-flex justify-content-between align-items-center bg-white shadow-sm" type="button" data-bs-toggle="collapse" data-bs-target="#vendorData{{ $item->id }}" aria-expanded="false">
                                                <span class="text-primary"><i class="bi bi-search me-1"></i> Intip Penawaran PR</span>
                                                <span class="badge bg-primary rounded-pill">{{ $prQuotes->count() }} Vendor</span>
                                            </button>
                                            
                                            <div class="collapse mt-2" id="vendorData{{ $item->id }}">
                                                <div class="d-flex flex-column gap-2">
                                                    @foreach($prQuotes as $vq)
                                                        @php
                                                            $vqCurr = 'IDR';
                                                            if($vq->currency_id) {
                                                                $currObj = \App\Models\Currency::find($vq->currency_id);
                                                                if($currObj) $vqCurr = $currObj->code;
                                                            }
                                                        @endphp
                                                        
                                                        <div class="bg-white border border-info border-opacity-25 rounded-3 p-2 shadow-sm relative">
                                                            <div class="d-flex justify-content-between border-bottom border-light pb-1 mb-1">
                                                                <span class="fw-bold text-dark text-truncate pe-2" style="font-size: 0.75rem;"><i class="bi bi-shop text-muted me-1"></i>{{ optional($vq->vendor)->name }}</span>
                                                                <span class="text-success fw-bolder" style="font-size: 0.75rem;">{{ $vqCurr }} {{ number_format($vq->quoted_price ?? $vq->price ?? 0, 0, ',', '.') }}</span>
                                                            </div>
                                                            <div style="font-size: 0.7rem; line-height: 1.4;">
                                                                @if($vq->reference_link)
                                                                    <a href="{{ $vq->reference_link }}" target="_blank" onclick="event.stopPropagation();" class="text-decoration-none fw-bold me-2"><i class="bi bi-link-45deg"></i> Link Toko</a>
                                                                @endif
                                                                <span class="text-muted fst-italic">{{ $vq->notes ?? 'Tidak ada catatan.' }}</span>
                                                                
                                                                @if($vq->attachments && $vq->attachments->count() > 0)
                                                                    <div class="mt-1 pt-1 border-top border-light d-flex flex-wrap gap-1">
                                                                        @foreach($vq->attachments as $idx => $vFile)
                                                                            <a href="{{ asset('storage/' . $vFile->file_path) }}" target="_blank" class="badge bg-info-subtle text-info-emphasis text-decoration-none border border-info-subtle"><i class="bi bi-file-earmark-pdf-fill"></i> File {{ $idx + 1 }}</a>
                                                                        @endforeach
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                        @endif
                                        
                                    </div>
                                </div>
                            </div>

                            {{-- BARIS 2: QTY, Satuan, Harga, Diskon, Pajak --}}
                            <div class="row g-3">
                                {{-- QTY & SATUAN --}}
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-dark">Qty & Satuan <span class="text-danger">*</span></label>
                                    <div class="input-group-modern shadow-sm mb-1">
                                        <input type="number" name="po_items[{{ $item->id }}][qty]" id="qty-input-{{ $item->id }}" class="form-control fw-bolder text-center qty-input text-primary" value="{{ (float)$item->qty_ordered }}" max="{{ $remainingNominal }}" min="0.01" step="0.01" data-base-remaining="{{ $sisaBaseQty }}" oninput="calculateRow(this)" required>
                                    </div>
                                    
                                    <select name="po_items[{{ $item->id }}][uom]" class="form-select border-primary text-primary fw-bold uom-selector shadow-sm" data-current-conv="{{ $currentConvRate }}" onchange="updateRowUom(this, {{ $item->id }})">
                                        <option value="{{ $safeCurrentUom }}" data-conv="{{ $currentConvRate }}" selected>{{ $safeCurrentUom }} [Dipilih]</option>
                                        
                                        @if($prItem && strtolower($safeCurrentUom) !== strtolower($safePrUom))
                                            <option value="{{ $safePrUom }}" data-conv="{{ $prConvRate }}">{{ $safePrUom }} [PR]</option>
                                        @endif
                                
                                        @if(strtolower($baseUomName) !== strtolower(trim(preg_replace('/ \(Isi:.*\)/i', '', $safeCurrentUom))) && strtolower($baseUomName) !== strtolower(trim(preg_replace('/ \(Isi:.*\)/i', '', $safePrUom))))
                                            <option value="{{ $baseUomName }}" data-conv="1">{{ $baseUomName }} (Eceran Dasar)</option>
                                        @endif
                                        
                                        @if(optional($item->item)->itemUoms)
                                            @foreach($item->item->itemUoms as $altUom)
                                                @php $valString = $altUom->uom_name . ' (Isi: ' . (float)$altUom->conversion_qty . ')'; @endphp
                                                @if(strtolower($valString) !== strtolower($safeCurrentUom) && strtolower($valString) !== strtolower($safePrUom))
                                                    <option value="{{ $valString }}" data-conv="{{ (float)$altUom->conversion_qty }}">{{ $altUom->uom_name }} (Isi: {{ (float)$altUom->conversion_qty }})</option>
                                                @endif
                                            @endforeach
                                        @endif
                                    </select>
                                    
                                    <div class="text-muted mt-1" style="font-size: 0.7rem;" id="max-help-{{ $item->id }}">
                                        Batas Max PR: <strong class="text-danger" id="max-val-{{ $item->id }}">{{ $remainingNominal }}</strong>
                                    </div>
                                </div>

                                {{-- HARGA --}}
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-dark">Harga Satuan <span class="text-danger">*</span></label>
                                    <div class="input-group-modern shadow-sm">
                                        <span class="input-group-text currency-label">{{ $po->currency }}</span>
                                        <input type="number" name="po_items[{{ $item->id }}][unit_price]" class="form-control text-end fw-bold price-input" value="{{ (float)$item->unit_price }}" min="0" step="any" oninput="calculateRow(this)" required>
                                    </div>
                                </div>

                                {{-- DISKON --}}
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-dark">Diskon per Item</label>
                                    <div class="input-group-modern shadow-sm">
                                        <select name="po_items[{{ $item->id }}][discount_type]" class="form-select text-center fw-bold text-secondary disc-type" style="max-width: 65px;" onchange="calculateRow(this)">
                                            <option value="PERCENT" {{ $item->discount_type == 'PERCENT' ? 'selected' : '' }}>%</option>
                                            <option value="FIXED" {{ $item->discount_type == 'FIXED' ? 'selected' : '' }}>Nom</option>
                                        </select>
                                        <input type="number" name="po_items[{{ $item->id }}][discount_value]" class="form-control text-end fw-bold text-danger disc-val" value="{{ (float)$item->discount_value }}" min="0" step="any" oninput="calculateRow(this)">
                                    </div>
                                    <input type="hidden" name="po_items[{{ $item->id }}][discount_amount]" class="disc-amt-hidden" value="{{ (float)$item->discount_amount }}">
                                </div>

                                {{-- PAJAK --}}
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-dark">Pajak (VAT/PPN)</label>
                                    <select name="po_items[{{ $item->id }}][tax_id]" class="form-select form-input-custom shadow-sm tax-select fw-bold text-muted" onchange="calculateRow(this)">
                                        <option value="" data-percent="0">- Tanpa Pajak -</option>
                                        @foreach($taxes as $tax)
                                            <option value="{{ $tax->id }}" data-percent="{{ $tax->percent }}" {{ $item->tax_id == $tax->id ? 'selected' : '' }}>+ {{ $tax->name }} ({{ $tax->percent }}%)</option>
                                        @endforeach
                                    </select>
                                    <input type="hidden" name="po_items[{{ $item->id }}][tax_amount]" class="tax-amt-hidden" value="{{ (float)$item->tax_amount }}">
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- 3. BIAYA LAIN & POTONGAN LAIN --}}
            <div class="row g-4 mb-4 mt-2">
                <div class="col-md-6">
                    <div class="card shadow-sm border-0 rounded-4 h-100">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-truck text-success me-2"></i>Biaya Tambahan (+)</h6>
                            <button type="button" class="btn btn-sm btn-light border text-primary rounded-pill fw-bold" onclick="addChargeRow()"><i class="bi bi-plus-lg"></i> Baris</button>
                        </div>
                        <div class="card-body p-3 bg-light rounded-bottom-4">
                            <table class="table table-borderless table-sm mb-0">
                                <tbody id="chargesContainer">
                                    @foreach($charges as $idx => $charge)
                                        <tr class="charge-row border-bottom">
                                            <td width="55%" class="p-1 pb-2">
                                                <input type="text" name="charges[{{ $idx }}][charge_type_id]" class="form-control form-input-custom" list="chargeTypeList" value="{{ $charge->name }}" required>
                                            </td>
                                            <td width="35%" class="p-1 pb-2">
                                                <input type="number" name="charges[{{ $idx }}][amount]" class="form-control form-input-custom text-end fw-bold text-success charge-input" value="{{ (float)$charge->amount }}" min="0" step="any" oninput="calculateGrandTotal()" required>
                                            </td>
                                            <td width="10%" class="p-1 pb-2 text-center">
                                                <button type="button" class="btn text-danger p-0 mt-1" onclick="removeRow(this)"><i class="bi bi-trash-fill fs-5"></i></button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card shadow-sm border-0 rounded-4 h-100">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-ticket-perforated text-danger me-2"></i>Potongan Voucher (-)</h6>
                            <button type="button" class="btn btn-sm btn-light border text-danger rounded-pill fw-bold" onclick="addExtraDiscRow()"><i class="bi bi-plus-lg"></i> Baris</button>
                        </div>
                        <div class="card-body p-3 bg-light rounded-bottom-4">
                            <table class="table table-borderless table-sm mb-0">
                                <tbody id="extraDiscContainer">
                                    @foreach($extraDiscounts as $idx => $disc)
                                        <tr class="extradisc-row border-bottom">
                                            <td width="55%" class="p-1 pb-2">
                                                <input type="text" name="extra_discounts[{{ $idx }}][discount_type_id]" class="form-control form-input-custom" list="discountTypeList" value="{{ $disc->name }}" required>
                                            </td>
                                            <td width="35%" class="p-1 pb-2">
                                                <input type="number" name="extra_discounts[{{ $idx }}][amount]" class="form-control form-input-custom text-end fw-bold text-danger extradisc-input" value="{{ (float)$disc->amount }}" min="0" step="any" oninput="calculateGrandTotal()" required>
                                            </td>
                                            <td width="10%" class="p-1 pb-2 text-center">
                                                <button type="button" class="btn text-danger p-0 mt-1" onclick="removeRow(this)"><i class="bi bi-trash-fill fs-5"></i></button>
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
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-body p-4">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark"><i class="bi bi-file-text me-2 text-primary"></i>Catatan Internal / Pesan Utama PO</label>
                            <textarea name="notes" class="form-control form-input-custom bg-light" rows="3">{{ $po->notes }}</textarea>
                        </div>
                        <div class="col-md-6 border-start pl-4">
                            <label class="form-label fw-bold text-dark"><i class="bi bi-paperclip me-2 text-primary"></i>File Master PO (Eksisting)</label>
                            @if($po->attachments && $po->attachments->count() > 0)
                                <div class="mb-2">
                                    @foreach($po->attachments as $file)
                                        <div class="d-flex justify-content-between border rounded p-2 mb-1 bg-light">
                                            <a href="{{ asset('storage/' . $file->file_path) }}" target="_blank" class="small text-decoration-none fw-bold"><i class="bi bi-search text-success me-1"></i> {{ $file->file_name }}</a>
                                            {{-- 🔥 TOMBOL HAPUS LAMPIRAN HEADER 🔥 --}}
                                            <a href="{{ route('po.delete_header_attachment', $file->id) }}" class="text-danger small" onclick="return confirm('Hapus file master ini secara permanen?')"><i class="bi bi-trash"></i></a>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-muted small fst-italic py-2">Belum ada file terlampir.</div>
                            @endif
                            
                            <label class="form-label fw-bold text-dark mt-3"><i class="bi bi-cloud-upload me-1 text-primary"></i>Upload Tambahan Header PO</label>
                            <input type="file" name="header_attachments[]" class="form-control form-control-sm border-secondary-subtle" multiple accept=".pdf,.jpg,.jpeg,.png,.xls,.xlsx,.doc,.docx">
                        </div>
                    </div>
                </div>
            </div>

        </div>

        {{-- ================= AREA KANAN (RINGKASAN & JADWAL) ================= --}}
        <div class="col-xl-4 col-lg-5">
            <div class="card summary-card shadow-lg bg-white overflow-hidden">
                <div class="bg-primary p-4 text-white text-center">
                    <div class="small fw-bolder text-white-50 text-uppercase mb-2" style="letter-spacing: 1.5px;">Estimasi Total PO</div>
                    <div class="d-flex justify-content-center align-items-center">
                        <span class="currency-label fs-5 me-2 opacity-75 fw-bold">{{ $po->currency }}</span>
                        <h1 class="mb-0 fw-bolder" id="lblGrandTotal" style="font-size: 2.5rem;">0</h1>
                    </div>
                </div>

                <div class="card-body p-4">
                    <div class="mb-4 bg-light p-3 rounded-3 border border-warning-subtle">
                        <label class="form-label small fw-bold text-dark mb-2">Diskon Global (Header PO)</label>
                        <div class="input-group input-group-sm mb-1 shadow-sm rounded-2 overflow-hidden border">
                            <select name="global_discount_type" id="globalDiscType" class="px-1 text-center form-select border-0 bg-white fw-bold" style="max-width: 60px;" onchange="calculateGrandTotal()">
                                <option value="PERCENT" {{ $po->global_discount_type == 'PERCENT' ? 'selected' : '' }}>%</option>
                                <option value="FIXED" {{ $po->global_discount_type == 'FIXED' ? 'selected' : '' }}>Nom</option>
                            </select>
                            <input type="number" name="global_discount_value" id="globalDiscValue" class="px-2 form-control text-end border-0 fw-bold text-danger" value="{{ (float)$po->global_discount_value }}" min="0" step="any" oninput="calculateGrandTotal()">
                        </div>
                    </div>

                    <div class="mb-4 bg-light p-3 rounded-3 border border-primary-subtle">
                        <label class="form-label small fw-bold text-dark mb-2"><i class="bi bi-magic me-1 text-primary"></i> Terapkan Pajak ke Semua Item</label>
                        <select id="globalTaxSelect" class="form-select form-select-sm border-0 fw-bold text-muted shadow-sm" onchange="applyGlobalTax(this)">
                            <option value="">-- Pilih Pajak --</option>
                            <option value="RESET">Hapus Semua Pajak</option>
                            @foreach($taxes as $tax)
                                <option value="{{ $tax->id }}">{{ $tax->name }} ({{ $tax->percent }}%)</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Rincian Hitungan --}}
                    <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">Rincian Kalkulasi</h6>
                    <div class="d-flex justify-content-between mb-2 small text-muted"><span>Total Bruto (Item)</span><span class="fw-bold text-dark" id="lblSubtotal">0</span></div>
                    <div class="d-flex justify-content-between mb-2 small text-danger"><span>Total Diskon Item (-)</span><span class="fw-bold" id="lblTotalItemDisc">0</span></div>
                    <div class="d-flex justify-content-between mb-2 small text-primary fw-bolder"><span>DPP (Dasar Pajak)</span><span id="lblDpp">0</span></div>
                    <div class="d-flex justify-content-between mb-2 small text-danger fw-bolder"><span>Diskon Global (-)</span><span id="lblGlobalDisc">0</span></div>
                    <div class="d-flex justify-content-between mb-2 small text-muted"><span>Total Pajak PPN (+)</span><span class="fw-bold text-dark" id="lblTax">0</span></div>
                    <div class="d-flex justify-content-between mb-2 small text-success"><span>Biaya Tambahan (+)</span><span class="fw-bold" id="lblCharges">0</span></div>
                    <div class="d-flex justify-content-between mb-4 small text-danger border-bottom pb-3"><span>Potongan Voucher (-)</span><span class="fw-bold" id="lblExtraDisc">0</span></div>

                    {{-- TANGGAL & TERMIN --}}
                    <h6 class="fw-bold text-dark mb-3 border-bottom pb-2 pt-3">Jadwal & Pembayaran</h6>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Tanggal PO <span class="text-danger">*</span></label>
                        <input type="date" name="po_date" id="poDateInput" class="form-control form-input-custom fw-bold" value="{{ \Carbon\Carbon::parse($po->po_date)->format('Y-m-d') }}" required onchange="calculateDueDate()">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Termin Pembayaran <span class="text-danger">*</span></label>
                        <select name="payment_term_id" id="paymentTermSelect" class="form-select form-input-custom fw-bold select2-init" required onchange="calculateDueDate()">
                            <option value="" data-days="0">- Pilih Termin -</option>
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
    <tr class="charge-row border-bottom">
        <td width="55%" class="p-1 pb-2"><input type="text" name="charges[INDEX][charge_type_id]" class="form-control form-input-custom" list="chargeTypeList" placeholder="Ketik Biaya..." required></td>
        <td width="35%" class="p-1 pb-2"><input type="number" name="charges[INDEX][amount]" class="form-control form-input-custom text-end fw-bold text-success charge-input" placeholder="0" min="0" step="any" oninput="calculateGrandTotal()" required></td>
        <td width="10%" class="p-1 pb-2 text-center"><button type="button" class="btn text-danger p-0 mt-1" onclick="removeRow(this)"><i class="bi bi-trash-fill fs-5"></i></button></td>
    </tr>
</template>

<template id="extraDiscRowTemplate">
    <tr class="extradisc-row border-bottom">
        <td width="55%" class="p-1 pb-2"><input type="text" name="extra_discounts[INDEX][discount_type_id]" class="form-control form-input-custom" list="discountTypeList" placeholder="Ketik Voucher..." required></td>
        <td width="35%" class="p-1 pb-2"><input type="number" name="extra_discounts[INDEX][amount]" class="form-control form-input-custom text-end fw-bold text-danger extradisc-input" placeholder="0" min="0" step="any" oninput="calculateGrandTotal()" required></td>
        <td width="10%" class="p-1 pb-2 text-center"><button type="button" class="btn text-danger p-0 mt-1" onclick="removeRow(this)"><i class="bi bi-trash-fill fs-5"></i></button></td>
    </tr>
</template>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>

<script>
    let chargeIdx = 500; let discIdx = 500;
    let myEditors = {};

    function initCKEditor(selectorId) {
        let domElement = document.querySelector('#' + selectorId);
        if (domElement && !domElement.ckeditorInstance) { 
            ClassicEditor.create(domElement, { toolbar: [ 'heading', '|', 'bold', 'italic', 'bulletedList', 'numberedList', 'blockQuote', '|', 'undo', 'redo' ] })
                .then(editor => { myEditors[selectorId] = editor; domElement.ckeditorInstance = editor; })
                .catch(err => console.error(err));
        }
    }

    $(document).ready(function() {
        $('.select2-init').select2({ theme: 'bootstrap-5', width: '100%' });
        
        document.querySelectorAll('.ckeditor-spec').forEach(ta => initCKEditor(ta.id));
        
        document.querySelectorAll('.item-row').forEach(row => calculateRow(row.querySelector('.qty-input')));
        updateCurrencySymbol();
        calculateGrandTotal();
    });

    function updateShippingAddress() {
        var select = document.getElementById('billToSelect');
        var address = select.options[select.selectedIndex].getAttribute('data-address');
        if (address) document.getElementById('shippingAddressInput').value = address;
    }

    function calculateDueDate() {
        let poDateVal = document.getElementById('poDateInput').value;
        let termSelect = document.getElementById('paymentTermSelect');
        let daysToAdd = parseInt(termSelect.options[termSelect.selectedIndex].getAttribute('data-days')) || 0;
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

    function removeRow(btn) { btn.closest('tr').remove(); calculateGrandTotal(); }

    function updateRowUom(selectEl, index) {
        let selectedOption = selectEl.options[selectEl.selectedIndex];
        let newConvRate = parseFloat(selectedOption.getAttribute('data-conv')) || 1;
        let oldConvRate = parseFloat(selectEl.getAttribute('data-current-conv')) || 1;
        
        let qtyInput = document.getElementById(`qty-input-${index}`);
        let currentQty = parseFloat(qtyInput.value) || 0;
        let sisaBaseQty = parseFloat(qtyInput.getAttribute('data-base-remaining')) || 0;
        
        let newQty = (currentQty * oldConvRate) / newConvRate;
        let newMaxVal = sisaBaseQty / newConvRate;
        
        qtyInput.max = newMaxVal;
        qtyInput.value = parseFloat(newQty.toFixed(2));
        
        if(parseFloat(qtyInput.value) > newMaxVal) {
            qtyInput.value = newMaxVal;
        }

        selectEl.setAttribute('data-current-conv', newConvRate);

        let helpText = document.getElementById(`max-val-${index}`);
        if(helpText) helpText.innerText = newMaxVal;

        calculateRow(qtyInput);
    }

    // 🔥 JURUS NAMA FLAT ARRAY: item_attachments_X[] 🔥
    function triggerFilePicker(index) {
        let fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.name = `item_attachments_${index}[]`; 
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
                        <div id="${pillId}" class="file-pill d-flex align-items-center justify-content-between bg-white border rounded-3 p-1 shadow-sm mt-1">
                            <div class="d-flex align-items-center overflow-hidden">
                                <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex justify-content-center align-items-center me-2" style="width: 25px; height: 25px; font-size: 0.7rem;"><i class="bi bi-file-earmark-text-fill"></i></div>
                                <div class="text-truncate"><div class="fw-bold text-dark text-truncate" style="max-width: 100px; font-size: 0.65rem;" title="${file.name}">${file.name}</div></div>
                            </div>
                            <button type="button" class="btn btn-link text-danger p-0 ms-1" onclick="removeSpecificFile('${inputId}', ${fileIndex}, '${pillId}')" title="Hapus File"><i class="bi bi-x-circle-fill"></i></button>
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
            for(let i = 0; i < files.length; i++) {
                if(i !== fileIndexToRemove) dt.items.add(files[i]); 
            }
            inputEle.files = dt.files;
            if(inputEle.files.length === 0) inputEle.remove();
        }
        let pill = document.getElementById(pillId);
        pill.style.opacity = '0';
        setTimeout(() => pill.remove(), 200);
    }

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
        let taxSelect = row.querySelector('.tax-select');
        let taxPercent = parseFloat(taxSelect.options[taxSelect.selectedIndex].getAttribute('data-percent')) || 0;
        let taxAmt = dpp * (taxPercent / 100);
        row.querySelector('.tax-amt-hidden').value = taxAmt;

        let subtotal = dpp + taxAmt;
        row.querySelector('.subtotal-input').value = subtotal;
        row.querySelector('.subtotal-display').innerText = formatCurrency(subtotal);
        calculateGrandTotal();
    }

    function calculateGrandTotal() {
        let totalGross = 0; let totalItemDisc = 0; let totalTax = 0;
        document.querySelectorAll('.item-row').forEach(row => {
            totalGross += (parseFloat(row.querySelector('.qty-input').value) || 0) * (parseFloat(row.querySelector('.price-input').value) || 0);
            totalItemDisc += parseFloat(row.querySelector('.disc-amt-hidden').value) || 0;
            totalTax += parseFloat(row.querySelector('.tax-amt-hidden').value) || 0;
        });

        let totalCharges = 0; document.querySelectorAll('.charge-input').forEach(i => totalCharges += parseFloat(i.value) || 0);
        let totalExtraDisc = 0; document.querySelectorAll('.extradisc-input').forEach(i => totalExtraDisc += parseFloat(i.value) || 0);

        let dpp = totalGross - totalItemDisc;
        let globalDiscType = document.getElementById('globalDiscType').value;
        let globalDiscVal = parseFloat(document.getElementById('globalDiscValue').value) || 0;
        let globalDiscAmt = (globalDiscType === 'PERCENT') ? (dpp * globalDiscVal / 100) : globalDiscVal;

        let grandTotal = dpp - globalDiscAmt + totalTax + totalCharges - totalExtraDisc;

        document.getElementById('lblSubtotal').innerText = formatCurrency(totalGross);
        document.getElementById('lblTotalItemDisc').innerText = "-" + formatCurrency(totalItemDisc);
        document.getElementById('lblDpp').innerText = formatCurrency(dpp);
        document.getElementById('lblGlobalDisc').innerText = "-" + formatCurrency(globalDiscAmt);
        document.getElementById('lblTax').innerText = "+" + formatCurrency(totalTax);
        document.getElementById('lblCharges').innerText = "+" + formatCurrency(totalCharges);
        document.getElementById('lblExtraDisc').innerText = "-" + formatCurrency(totalExtraDisc);
        document.getElementById('lblGrandTotal').innerText = formatCurrency(grandTotal);
    }

    function applyGlobalTax(selectElement) {
        let selectedTaxId = selectElement.value;
        document.querySelectorAll('.item-row').forEach(row => {
            let itemTaxSelect = row.querySelector('.tax-select');
            if(selectedTaxId === 'RESET') itemTaxSelect.value = "";
            else if (selectedTaxId !== "") itemTaxSelect.value = selectedTaxId;
            calculateRow(itemTaxSelect);
        });
    }

    function formatCurrency(amount) { return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(amount); }

    function updateCurrencySymbol() {
        let currencySelect = document.getElementById('currencySelect');
        if(!currencySelect) return;
        
        let currency = currencySelect.value;
        
        document.querySelectorAll('.currency-label').forEach(el => {
            el.innerText = currency;
        });

        document.querySelectorAll('option[value="FIXED"]').forEach(opt => {
            opt.innerText = currency;
        });
    }

    document.getElementById('poForm').addEventListener('submit', function(e) {
        e.preventDefault();
        let form = this;

        for (let editorId in myEditors) { 
            if (myEditors[editorId]) myEditors[editorId].updateSourceElement(); 
        }
        
        if(!this.checkValidity()) { 
            let invalidElements = this.querySelectorAll(':invalid');
            if(invalidElements.length > 0) {
                let firstInvalid = invalidElements[0];
                invalidElements.forEach(el => {
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
                }).then(() => {
                    firstInvalid.scrollIntoView({behavior: 'smooth', block: 'center'});
                    firstInvalid.focus();
                });
            }
            return; 
        }

        $('input[type="file"]').each(function() { 
            if ($(this).val() === '') $(this).prop('disabled', true); 
        });

        // Validasi Sisa Jatah PR
        let prItemTotals = {};
        let isValidSisa = true;
        let errorMessage = '';

        document.querySelectorAll('.item-row').forEach(row => {
            let prItemId = row.querySelector('input[name$="[pr_item_id]"]').value;
            let itemName = row.querySelector('.fw-bolder.text-dark').innerText;
            let qtyInput = row.querySelector('.qty-input');
            let uomSelect = row.querySelector('.uom-selector');

            let qty = parseFloat(qtyInput.value) || 0;
            let convRate = parseFloat(uomSelect.getAttribute('data-current-conv')) || 1;
            let baseRemaining = parseFloat(qtyInput.getAttribute('data-base-remaining')) || 0;

            let baseQtyOrdered = qty * convRate;

            if(!prItemTotals[prItemId]) prItemTotals[prItemId] = { total: 0, max: baseRemaining, name: itemName };
            prItemTotals[prItemId].total += baseQtyOrdered;
        });

        for (const [prItemId, data] of Object.entries(prItemTotals)) {
            if (parseFloat(data.total.toFixed(4)) > parseFloat(data.max.toFixed(4))) {
                isValidSisa = false;
                errorMessage += `Kuantitas <b>${data.name}</b> melebihi batas jatah sisa PR!<br><small>Batas Maksimal Asli: Hanya ${data.max} (Eceran)</small><br><br>`;
            }
        }

        if(!isValidSisa) {
            $('input[type="file"]').prop('disabled', false); 
            Swal.fire({
                title: 'Melebihi Jatah PR!', html: errorMessage, icon: 'error', confirmButtonColor: '#dc3545', confirmButtonText: 'Oke, Saya Revisi'
            });
            return; 
        }

        Swal.fire({ 
            title: 'Simpan Perubahan?', 
            text: "Perubahan akan disimpan permanen.", 
            icon: 'question', 
            showCancelButton: true, 
            confirmButtonColor: '#198754', 
            cancelButtonColor: '#6c757d', 
            confirmButtonText: 'Ya, Simpan!' 
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({ title: 'Menyimpan...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                form.submit();
            } else {
                $('input[type="file"]').prop('disabled', false); 
            }
        });
    });
</script>
@endpush