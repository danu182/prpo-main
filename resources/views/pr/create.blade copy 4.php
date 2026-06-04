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

<form action="{{ route('po.store_from_pr', $pr->pr_number) }}" method="POST" id="poForm" enctype="multipart/form-data" novalidate>
    @csrf

    {{-- HEADER --}}
    <div class="mb-4 d-flex justify-content-between align-items-center border-bottom pb-3">
        <div>
            <h4 class="mb-1 fw-bolder text-dark"><i class="bi bi-cart-plus me-2 text-primary"></i> Terbitkan Purchase Order</h4>
            <div class="text-muted small">Konversi PR <strong class="text-primary">{{ $pr->pr_number }}</strong> menjadi dokumen PO resmi.</div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('po.index') }}" class="btn btn-light border fw-bold rounded-pill px-4"><i class="bi bi-x-lg me-1"></i> Batal</a>
            <button type="submit" class="btn btn-primary fw-bold rounded-pill px-4"><i class="bi bi-send-check me-1"></i> Terbitkan PO</button>
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
            
            {{-- 📜 TRACK RECORD: PO YANG SUDAH TERBIT DARI PR INI 📜 --}}
            @if(isset($existingPos) && $existingPos->count() > 0)
            <div class="card shadow-sm border-0 rounded-4 mb-4 border-start border-4 border-info">
                <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <div class="bg-info bg-opacity-10 text-info rounded-circle p-2 me-3 d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <h6 class="mb-0 fw-bold text-dark">Riwayat PO Terkait (PR ini sudah pernah diproses)</h6>
                    </div>
                    <span class="badge bg-info-subtle text-info rounded-pill px-3">{{ $existingPos->count() }} PO Terdeteksi</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                            <thead class="bg-light text-muted">
                                <tr>
                                    <th class="ps-4">No. PO</th>
                                    <th>Vendor</th>
                                    <th>Item yang Dipesan</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-end pe-4">Total Harga</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($existingPos as $oldPo)
                                <tr>
                                    <td class="ps-4">
                                        <a href="{{ route('po.show', $oldPo->po_number) }}" target="_blank" class="fw-bold text-decoration-none">
                                            {{ $oldPo->po_number }} <i class="bi bi-box-arrow-up-right ms-1" style="font-size: 0.7rem;"></i>
                                        </a>
                                        <div class="text-muted" style="font-size: 0.7rem;">{{ \Carbon\Carbon::parse($oldPo->po_date)->format('d/m/Y') }}</div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark">{{ $oldPo->vendor->name ?? '-' }}</div>
                                    </td>
                                    <td>
                                        <ul class="list-unstyled mb-0" style="font-size: 0.75rem;">
                                            @foreach($oldPo->items as $pItem)
                                            <li>
                                                <i class="bi bi-dot"></i> {{ $pItem->qty_ordered }} {{ $pItem->uom }} - 
                                                <span class="text-muted">{{ $pItem->item->name ?? $pItem->description }}</span> 
                                            </li>
                                            @endforeach
                                        </ul>
                                    </td>
                                    <td class="text-center">
                                        @php $color = $oldPo->status->color ?? 'secondary'; @endphp
                                        <span class="badge bg-{{ $color }}-subtle text-{{ $color }} border border-{{ $color }}-subtle rounded-pill px-2" style="font-size: 0.7rem;">
                                            {{ mb_strtoupper($oldPo->status->name ?? 'DRAFT') }}
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="fw-bold text-dark">{{ $oldPo->currency }} {{ number_format($oldPo->grand_total, 0, ',', '.') }}</div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            {{-- 1. INFORMASI PENAGIHAN & PENGIRIMAN --}}
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-2 me-3 d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;">
                        <i class="bi bi-geo-alt-fill"></i>
                    </div>
                    <h6 class="mb-0 fw-bold">Penagihan & Pengiriman</h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        <div class="col-md-8">
                            <label class="form-label small fw-bold text-dark">Tagihan Ke (Bill To) <span class="text-danger">*</span></label>
                            <select name="billing_company_id" id="billToSelect" class="form-select select2-init" required onchange="updateShippingAddress()">
                                <option value="">-- Pilih PT Penagih --</option>
                                @foreach($companies as $c)
                                    <option value="{{ $c->id }}" data-address="{{ $c->address ?? '' }}" {{ $c->id == $pr->company_id ? 'selected' : '' }}>{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-dark">Mata Uang <span class="text-danger">*</span></label>
                            <select name="currency" id="currencySelect" class="form-select bg-light fw-bold text-primary form-input-custom" required onchange="updateCurrencySymbol()">
                                @foreach($currencies as $curr)
                                    <option value="{{ $curr->code }}" {{ $curr->code == $defaultCurrency ? 'selected' : '' }}>{{ $curr->code }} - {{ $curr->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-end mb-2">
                                <label class="form-label small fw-bold text-dark mb-0">Lokasi Pengiriman (Ship To) <span class="text-danger">*</span></label>
                                <button type="button" class="btn btn-sm btn-link text-decoration-none p-0" onclick="updateShippingAddress(true)"><i class="bi bi-arrow-counterclockwise"></i> Reset ke Alamat PT</button>
                            </div>
                            <textarea name="shipping_address" id="shippingAddressInput" rows="2" class="form-control form-input-custom bg-light" required>{{ old('shipping_address', $defaultShippingAddress) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 2. DETAIL PESANAN BARANG (CARD-GRID) --}}
            <div class="d-flex justify-content-between align-items-center mb-3 mt-5">
                <h5 class="fw-bolder text-dark mb-0"><i class="bi bi-box-seam me-2 text-primary"></i>Daftar Barang Pesanan</h5>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="checkAllItems" checked style="cursor: pointer;">
                    <label class="form-check-label small fw-bold text-muted" for="checkAllItems">Pilih Semua</label>
                </div>
            </div>

            <div id="itemsContainer">
                @foreach($pr->items as $index => $item)
                    @php
                        // 1. CARI BASE UOM (ECERAN) DARI MASTER BARANG
                        $baseUomName = optional(optional($item->item)->uom)->name ?? 'PCS';
                        
                        // 2. AMANKAN RAW UOM DARI SERANGAN JSON
                        $rawPrUom = is_string($item->uom) ? $item->uom : (optional($item->item->uom)->name ?? 'Unit');
                        $uomShort = $item->uom_short ?? '';
                        $uomDetail = $item->uom_detail ?? '';
                        $fullUomString = trim($rawPrUom . ' ' . $uomShort . ' ' . $uomDetail);
                        
                        // 3. EKSTRAK FAKTOR KONVERSI PR
                        $prConvRate = 1;
                        $cleanPrUom = trim(preg_replace('/ \(Isi:.*\)/i', '', $rawPrUom ?: $baseUomName));
                        
                        if (!empty($item->conversion_qty) && $item->conversion_qty > 0) {
                            $prConvRate = (float) $item->conversion_qty; 
                        } elseif (!empty($item->uom_id)) {
                            $prUomModel = collect(optional($item->item)->itemUoms)->where('id', $item->uom_id)->first();
                            if ($prUomModel) {
                                $prConvRate = (float) $prUomModel->conversion_qty;
                                $cleanPrUom = $prUomModel->uom_name;
                            }
                        } elseif (preg_match('/(?:Isi:|Isi)\s*([0-9.]+)/i', $fullUomString, $matches)) {
                            $prConvRate = (float) $matches[1]; 
                        } else {
                            $prUomModel = collect(optional($item->item)->itemUoms)->where('uom_name', $cleanPrUom)->first();
                            if ($prUomModel) $prConvRate = (float) $prUomModel->conversion_qty;
                        }

                        // 4. KALKULASI SISA DALAM SATUAN DASAR (ECERAN)
                        $targetBaseQty = $item->qty * $prConvRate; 
                        $orderedBaseQty = (float)($item->ordered_qty ?? 0); 
                        $sisaBaseQty = max(0, $targetBaseQty - $orderedBaseQty); 
                        
                        $remainingNominal = $prConvRate > 0 ? ($sisaBaseQty / $prConvRate) : 0; 

                        // 5. FILTER PENAMPILAN
                        $itemStatus = strtoupper(trim($item->status ?? ''));
                        if($sisaBaseQty <= 0 || !in_array($itemStatus, ['APPROVED', 'PARTIAL', 'PARTIAL_PO'])) {
                            continue;
                        }
                        
                        $suggestedVendorId = $item->suggested_vendor_id;
                        $quote = $suggestedVendorId ? $item->vendorQuotes->where('vendor_id', $suggestedVendorId)->first() : optional($item->vendorQuotes)->first();
                        $price = $quote ? ($quote->quoted_price ?? $quote->price ?? 0) : 0;
                        
                        $quoteCurrency = 'IDR';
                        if($quote && $quote->currency_id) {
                            $currObj = \App\Models\Currency::find($quote->currency_id);
                            if($currObj) $quoteCurrency = $currObj->code;
                        }
                    @endphp
                    
                    <div class="card item-card mb-4 shadow-sm item-row border-0" data-original-idx="{{ $index }}">
                        {{-- HEADER CARD BARANG --}}
                        <div class="card-header bg-light border-bottom d-flex justify-content-between align-items-center py-3 px-4" style="border-radius: 12px 12px 0 0;">
                            <div class="d-flex align-items-center gap-3 w-75">
                                <input type="checkbox" name="po_items[{{ $index }}][is_selected]" class="form-check-input row-checkbox m-0" checked onchange="toggleRow(this)" style="transform: scale(1.4); cursor: pointer;">
                                <div>
                                    <div class="fw-bolder text-dark fs-6">{{ optional($item->item)->name ?? 'Item Terhapus' }}</div>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle mt-1">{{ optional($item->item)->code }}</span>
                                    <input type="hidden" name="po_items[{{ $index }}][item_id]" value="{{ $item->item_id }}">
                                    <input type="hidden" name="po_items[{{ $index }}][pr_item_id]" value="{{ $item->id }}">
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="small text-muted fw-bold text-uppercase mb-1">Netto Item</div>
                                <h5 class="fw-bolder text-primary mb-0"><span class="currency-label fs-6 text-muted me-1">IDR</span><span class="subtotal-display">0</span></h5>
                                <input type="hidden" class="subtotal-input" value="0">
                            </div>
                        </div>

                        {{-- BODY CARD BARANG --}}
                        <div class="card-body p-4">
                            
                            {{-- BARIS 1: Vendor Aktual, Catatan & Data Referensi PR --}}
                            <div class="row g-3 mb-4 pb-4 border-bottom">
                                <div class="col-md-5">
                                    <label class="form-label small fw-bold text-dark">Vendor Aktual <span class="text-danger">*</span></label>
                                    <select name="po_items[{{ $index }}][vendor_id]" class="form-select select2-init vendor-select" required>
                                        <option value="">- Pilih Vendor -</option>
                                        @foreach($vendors as $v)
                                            <option value="{{ $v->id }}" {{ ($quote && $quote->vendor_id == $v->id) ? 'selected' : '' }}>{{ $v->name }}</option>
                                        @endforeach
                                    </select>
                                    
                                    {{-- Tombol Aksi Item --}}
                                    <div class="mt-3 d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-outline-info rounded-pill fw-bold btn-pecah" onclick="splitItem(this)">
                                            <i class="bi bi-diagram-2-fill"></i> Pecah Vendor
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="col-md-7">
                                    <div class="row">
                                        <div class="col-md-7">
                                            {{-- 🔥 MENGGUNAKAN CKEDITOR UNTUK SPESIFIKASI 🔥 --}}
                                            <label class="form-label small fw-bold text-dark">Spesifikasi Khusus (Bisa diedit)</label>
                                            <textarea name="po_items[{{ $index }}][notes]" id="spec_{{ $index }}" class="form-control form-input-custom ckeditor-spec" placeholder="Ketik spesifikasi detail di sini...">{!! $item->specification ?? $item->notes !!}</textarea>
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label small fw-bold text-dark"><i class="bi bi-paperclip text-primary"></i> Upload Dokumen</label>
                                            <div id="fileListContainer_{{ $index }}" class="d-flex flex-column gap-1 mb-1"></div>
                                            <div id="hiddenFileInputs_{{ $index }}" style="display: none;"></div>
                                            <button type="button" class="btn btn-sm btn-outline-primary border-dashed w-100 fw-bold py-2" onclick="triggerFilePicker('{{ $index }}')">
                                                <i class="bi bi-plus"></i> Tambah File
                                            </button>

                                            @if($quote)
                                            <div class="vendor-pr-accordion mt-3">
                                                <div class="vendor-pr-header fw-bold text-primary" data-bs-toggle="collapse" data-bs-target="#vendorData{{ $index }}" style="cursor: pointer; font-size: 0.75rem;">
                                                    <i class="bi bi-search me-1"></i> Intip Penawaran PR Asli
                                                </div>
                                                <div class="collapse vendor-pr-body mt-2 p-2 border rounded bg-light" id="vendorData{{ $index }}" style="font-size: 0.75rem;">
                                                    <div class="fw-bold text-dark">{{ optional($quote->vendor)->name }}</div>
                                                    <div class="text-success fw-bold mb-1">{{ $quoteCurrency }} {{ number_format($price, 0, ',', '.') }}</div>
                                                    @if($quote->reference_link)
                                                        <div><a href="{{ $quote->reference_link }}" target="_blank" onclick="event.stopPropagation();"><i class="bi bi-link-45deg"></i> Link Bukti</a></div>
                                                    @endif
                                                    @if($quote->notes)
                                                        <div class="text-muted fst-italic mt-1">"{{ $quote->notes }}"</div>
                                                    @endif
                                                </div>
                                            </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- BARIS 2: QTY, Harga, Diskon, Pajak --}}
                            <div class="row g-3">
                                {{-- QTY & SATUAN --}}
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-dark">Kuantitas & Satuan <span class="text-danger">*</span></label>
                                    <div class="input-group-modern shadow-sm mb-1">
                                        <input type="number" name="po_items[{{ $index }}][qty]" id="qty-input-{{ $index }}" class="form-control fw-bolder text-center qty-input text-primary" value="{{ $remainingNominal }}" max="{{ $remainingNominal }}" min="0.01" step="0.01" data-base-remaining="{{ $sisaBaseQty }}" oninput="calculateRow(this)" required>
                                    </div>
                                    
                                    @php
                                        $valStringPR = $cleanPrUom . ($prConvRate > 1 ? ' (Isi: ' . (float)$prConvRate . ')' : '');
                                    @endphp
                                    
                                    <select name="po_items[{{ $index }}][uom]" class="form-select border-primary text-primary fw-bold uom-selector shadow-sm" data-current-conv="{{ $prConvRate }}" onchange="updateRowUom(this, {{ $index }})">
                                        <option value="{{ $valStringPR }}" data-conv="{{ $prConvRate }}">{{ $cleanPrUom }} @if($prConvRate>1) (Isi: {{(float)$prConvRate}}) @endif [PR]</option>
                                        @if(strtolower($baseUomName) !== strtolower($cleanPrUom))
                                            <option value="{{ $baseUomName }}" data-conv="1">{{ $baseUomName }} (Dasar)</option>
                                        @endif
                                        @if(optional($item->item)->itemUoms)
                                            @foreach($item->item->itemUoms as $altUom)
                                                @if((float)$altUom->conversion_qty != $prConvRate)
                                                    @php $valString = $altUom->uom_name . ' (Isi: ' . (float)$altUom->conversion_qty . ')'; @endphp
                                                    <option value="{{ $valString }}" data-conv="{{ (float)$altUom->conversion_qty }}">{{ $altUom->uom_name }} (Isi: {{ (float)$altUom->conversion_qty }})</option>
                                                @endif
                                            @endforeach
                                        @endif
                                    </select>
                                    <div class="text-muted mt-1" style="font-size: 0.7rem;" id="max-help-{{ $index }}">
                                        Sisa Jatah PR: <strong class="text-danger" id="max-val-{{ $index }}">{{ $remainingNominal }}</strong>
                                    </div>
                                </div>

                                {{-- HARGA --}}
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-dark">Harga Satuan <span class="text-danger">*</span></label>
                                    <div class="input-group-modern shadow-sm">
                                        <span class="input-group-text currency-label">IDR</span>
                                        <input type="number" name="po_items[{{ $index }}][unit_price]" class="form-control text-end fw-bold price-input" value="{{ $price }}" min="0" step="any" oninput="calculateRow(this)" required>
                                    </div>
                                </div>

                                {{-- DISKON --}}
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-dark">Diskon per Item</label>
                                    <div class="input-group-modern shadow-sm">
                                        <select name="po_items[{{ $index }}][discount_type]" class="form-select text-center fw-bold text-secondary disc-type" style="max-width: 65px;" onchange="calculateRow(this)">
                                            <option value="PERCENT">%</option>
                                            <option value="FIXED">Rp</option>
                                        </select>
                                        <input type="number" name="po_items[{{ $index }}][discount_value]" class="form-control text-end fw-bold text-danger disc-val" value="0" min="0" step="any" oninput="calculateRow(this)">
                                    </div>
                                    <input type="hidden" name="po_items[{{ $index }}][discount_amount]" class="disc-amt-hidden" value="0">
                                </div>

                                {{-- PAJAK --}}
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-dark">Pajak (VAT/PPN)</label>
                                    <select name="po_items[{{ $index }}][tax_id]" class="form-select form-input-custom shadow-sm tax-select fw-bold text-muted" onchange="calculateRow(this)">
                                        <option value="" data-percent="0">- Tanpa Pajak -</option>
                                        @foreach($taxes as $tax)
                                            <option value="{{ $tax->id }}" data-percent="{{ $tax->percent }}">+ {{ $tax->name }} ({{ $tax->percent }}%)</option>
                                        @endforeach
                                    </select>
                                    <input type="hidden" name="po_items[{{ $index }}][tax_amount]" class="tax-amt-hidden" value="0">
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
                                <tbody id="chargesContainer"></tbody>
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
                                <tbody id="extraDiscContainer"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            {{-- Catatan Global PO --}}
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-body p-4">
                    <label class="form-label fw-bold text-dark"><i class="bi bi-file-text me-2 text-primary"></i>Catatan Internal / Pesan Utama Dokumen PO</label>
                    <textarea name="notes" class="form-control form-input-custom bg-light" rows="3" placeholder="Tulis instruksi pengiriman umum, referensi, dll di sini...">{{ $pr->description ?? '' }}</textarea>
                </div>
            </div>

        </div>

        {{-- ================= AREA KANAN (RINGKASAN & JADWAL) ================= --}}
        <div class="col-xl-4 col-lg-5">
            <div class="card summary-card shadow-lg bg-white overflow-hidden">
                {{-- Grand Total Utama --}}
                <div class="bg-primary p-4 text-white text-center">
                    <div class="small fw-bolder text-white-50 text-uppercase mb-2" style="letter-spacing: 1.5px;">Estimasi Total PO</div>
                    <div class="d-flex justify-content-center align-items-center">
                        <span class="currency-label fs-5 me-2 opacity-75 fw-bold">IDR</span>
                        <h1 class="mb-0 fw-bolder" id="lblGrandTotal" style="font-size: 2.5rem;">0</h1>
                    </div>
                </div>

                <div class="card-body p-4">
                    
                    {{-- Set Diskon & Pajak Massal --}}
                    <div class="mb-4 bg-light p-3 rounded-3 border border-warning-subtle">
                        <label class="form-label small fw-bold text-dark mb-2">
                            <i class="bi bi-tags-fill me-1 text-danger"></i> Diskon Global (Header PO)
                        </label>
                        <div id="globalDiscContainer">
                            <div class="global-disc-row mb-2 pb-2 border-bottom border-white">
                                <div class="input-group input-group-sm mb-1 shadow-sm rounded-2 overflow-hidden border">
                                    <select name="global_discounts[0][vendor_id]" class="form-select border-0 bg-white text-dark small fw-bold" style="max-width: 120px;">
                                        <option value="ALL">Semua Vendor</option>
                                        @foreach($vendors as $v)
                                            <option value="{{ $v->id }}">{{ $v->name }}</option>
                                        @endforeach
                                    </select>
                                    <select name="global_discounts[0][type]" class="px-1 text-center form-select border-0 bg-light fw-bold" style="max-width: 60px;">
                                        <option value="PERCENT">%</option>
                                        <option value="FIXED">Nom</option>
                                    </select>
                                    <input type="number" name="global_discounts[0][value]" class="px-2 form-control text-end border-0 fw-bold text-danger global-disc-val" value="0" min="0" step="any" oninput="calculateGrandTotal()">
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-link text-decoration-none p-0 small" onclick="addGlobalDiscRow()"><i class="bi bi-plus-circle"></i> Tambah Diskon Vendor Lain</button>
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
                        <div class="form-text" style="font-size: 0.6rem;">Tips: Centang item milik Vendor A saja, lalu klik ini untuk set pajak Vendor A.</div>
                    </div>

                    {{-- Rincian Hitungan --}}
                    <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">Rincian Kalkulasi</h6>
                    
                    <div class="d-flex justify-content-between mb-2 small text-muted">
                        <span>Total Bruto (Item)</span>
                        <span class="fw-bold text-dark" id="lblSubtotal">0</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2 small text-danger">
                        <span>Total Diskon Item (-)</span>
                        <span class="fw-bold" id="lblTotalItemDisc">0</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2 small text-primary fw-bolder">
                        <span>DPP (Dasar Pajak)</span>
                        <span id="lblDpp">0</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2 small text-danger fw-bolder">
                        <span>Diskon Global (-)</span>
                        <span id="lblGlobalDisc">0</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2 small text-muted">
                        <span>Total Pajak PPN (+)</span>
                        <span class="fw-bold text-dark" id="lblTax">0</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2 small text-success">
                        <span>Biaya Tambahan (+)</span>
                        <span class="fw-bold" id="lblCharges">0</span>
                    </div>
                    <div class="d-flex justify-content-between mb-4 small text-danger border-bottom pb-3">
                        <span>Potongan Voucher (-)</span>
                        <span class="fw-bold" id="lblExtraDisc">0</span>
                    </div>

                    {{-- TANGGAL & TERMIN --}}
                    <h6 class="fw-bold text-dark mb-3 border-bottom pb-2 pt-3">Jadwal & Pembayaran</h6>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Tanggal PO <span class="text-danger">*</span></label>
                        <input type="date" name="po_date" id="poDateInput" class="form-control form-input-custom fw-bold" value="{{ date('Y-m-d') }}" required onchange="calculateDueDate()">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Termin Pembayaran <span class="text-danger">*</span></label>
                        <select name="payment_term_id" id="paymentTermSelect" class="form-select form-input-custom fw-bold select2-init" required onchange="calculateDueDate()">
                            <option value="" data-days="0">- Pilih Termin -</option>
                            @foreach($paymentTerms as $term)
                                <option value="{{ $term->id }}" data-days="{{ $term->days }}">{{ $term->name }}</option>
                            @endforeach
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

{{-- DATALIST BIAYA & DISKON --}}
<datalist id="chargeTypeList">
    @foreach($chargeTypes as $type) <option value="{{ $type->name }}"></option> @endforeach
</datalist>
<datalist id="discountTypeList">
    @foreach($discountTypes as $type) <option value="{{ $type->name }}"></option> @endforeach
</datalist>

{{-- TEMPLATE BIAYA TAMBAHAN --}}
<template id="chargeRowTemplate">
    <tr class="charge-row border-bottom">
        <td width="35%" class="p-1 pb-2">
            <input type="text" name="charges[INDEX][charge_type_id]" class="form-control form-input-custom" list="chargeTypeList" placeholder="Ketik Biaya..." required>
        </td>
        <td width="30%" class="p-1 pb-2">
            <select name="charges[INDEX][vendor_id]" class="form-select form-input-custom text-secondary" style="font-size: 0.75rem;" required>
                <option value="ALL">Semua Vendor</option>
                @foreach($vendors as $v)
                    <option value="{{ $v->id }}">{{ $v->name }}</option>
                @endforeach
            </select>
        </td>
        <td width="25%" class="p-1 pb-2">
            <input type="number" name="charges[INDEX][amount]" class="form-control form-input-custom text-end fw-bold text-success charge-input" placeholder="0" min="0" step="any" oninput="calculateGrandTotal()" required>
        </td>
        <td width="10%" class="p-1 pb-2 text-center">
            <button type="button" class="btn text-danger p-0 mt-1" onclick="removeRow(this)"><i class="bi bi-trash-fill fs-5"></i></button>
        </td>
    </tr>
</template>

{{-- TEMPLATE POTONGAN TAMBAHAN --}}
<template id="extraDiscRowTemplate">
    <tr class="extradisc-row border-bottom">
        <td width="35%" class="p-1 pb-2">
            <input type="text" name="extra_discounts[INDEX][discount_type_id]" class="form-control form-input-custom" list="discountTypeList" placeholder="Ketik Voucher..." required>
        </td>
        <td width="30%" class="p-1 pb-2">
            <select name="extra_discounts[INDEX][vendor_id]" class="form-select form-input-custom text-secondary" style="font-size: 0.75rem;" required>
                <option value="ALL">Semua Vendor</option>
                @foreach($vendors as $v)
                    <option value="{{ $v->id }}">{{ $v->name }}</option>
                @endforeach
            </select>
        </td>
        <td width="25%" class="p-1 pb-2">
            <input type="number" name="extra_discounts[INDEX][amount]" class="form-control form-input-custom text-end fw-bold text-danger extradisc-input" placeholder="0" min="0" step="any" oninput="calculateGrandTotal()" required>
        </td>
        <td width="10%" class="p-1 pb-2 text-center">
            <button type="button" class="btn text-danger p-0 mt-1" onclick="removeRow(this)"><i class="bi bi-trash-fill fs-5"></i></button>
        </td>
    </tr>
</template>

@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
{{-- 🔥 SUNTIKAN CKEDITOR 5 CDN 🔥 --}}
<script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>

<script>
    let chargeIdx = 100;
    let discIdx = 100;
    let splitIdx = 5000; 
    let gDiscIdx = 0;
    let myEditors = {}; // Tempat menampung nyawa CKEditor

    function initSelect2() {
        $('.select2-init').select2({ theme: 'bootstrap-5', width: '100%' });
    }

    // 🔥 FUNGSI INISIASI CKEDITOR 🔥
    function initCKEditor(selectorId) {
        let domElement = document.querySelector('#' + selectorId);
        if (domElement) {
            ClassicEditor
                .create(domElement, {
                    toolbar: [ 'heading', '|', 'bold', 'italic', 'bulletedList', 'numberedList', 'blockQuote', '|', 'undo', 'redo' ]
                })
                .then(editor => {
                    myEditors[selectorId] = editor;
                })
                .catch(error => {
                    console.error('Oops, CKEditor gagal jalan:', error);
                });
        }
    }

    $(document).ready(function() {
        initSelect2();
        
        // Panggil CKEditor untuk semua textarea spesifikasi saat halaman diload
        document.querySelectorAll('.ckeditor-spec').forEach(function(textarea) {
            initCKEditor(textarea.id);
        });
        
        $('#checkAllItems').change(function() {
            $('.row-checkbox').prop('checked', this.checked).trigger('change');
        });

        document.querySelectorAll('.item-row').forEach(row => calculateRow(row.querySelector('.qty-input')));
        updateCurrencySymbol();
        calculateDueDate();
    });

    function updateShippingAddress(forceReset = false) {
        var select = document.getElementById('billToSelect');
        var textarea = document.getElementById('shippingAddressInput');
        var address = select.options[select.selectedIndex].getAttribute('data-address');
        if (address && (forceReset || textarea.value.trim() === '')) {
            textarea.value = address;
        }
    }

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

    function calculateDueDate() {
        let poDateVal = document.getElementById('poDateInput').value;
        let termSelect = document.getElementById('paymentTermSelect');
        let daysToAdd = parseInt(termSelect.options[termSelect.selectedIndex].getAttribute('data-days')) || 0;
        if(poDateVal && daysToAdd > 0) {
            let poDate = new Date(poDateVal);
            poDate.setDate(poDate.getDate() + daysToAdd);
            document.getElementById('dueDateInput').value = poDate.toISOString().split('T')[0];
        } else if(poDateVal) {
            document.getElementById('dueDateInput').value = poDateVal;
        }
    }

    function addChargeRow() {
        chargeIdx++;
        let template = document.getElementById('chargeRowTemplate').innerHTML.replace(/INDEX/g, chargeIdx);
        document.getElementById('chargesContainer').insertAdjacentHTML('beforeend', template);
    }
    
    function addExtraDiscRow() {
        discIdx++;
        let template = document.getElementById('extraDiscRowTemplate').innerHTML.replace(/INDEX/g, discIdx);
        document.getElementById('extraDiscContainer').insertAdjacentHTML('beforeend', template);
    }

    function addGlobalDiscRow() {
        gDiscIdx++;
        let vendorsHtml = `<option value="ALL">Semua Vendor</option>`;
        @foreach($vendors as $v)
            vendorsHtml += `<option value="{{ $v->id }}">{{ $v->name }}</option>`;
        @endforeach

        let currentCurrency = document.getElementById('currencySelect').value || 'IDR';

        let template = `
            <div class="global-disc-row mb-2 pb-2 border-bottom border-white position-relative">
                <div class="input-group input-group-sm mb-1 shadow-sm rounded-2 overflow-hidden border">
                    <select name="global_discounts[${gDiscIdx}][vendor_id]" class="form-select border-0 bg-white text-dark small fw-bold" style="max-width: 120px;">
                        ${vendorsHtml}
                    </select>
                    <select name="global_discounts[${gDiscIdx}][type]" class="px-1 text-center form-select border-0 bg-light fw-bold" style="max-width: 70px;">
                        <option value="PERCENT">%</option>
                        <option value="FIXED">${currentCurrency}</option> 
                    </select>
                    <input type="number" name="global_discounts[${gDiscIdx}][value]" class="px-2 form-control text-end border-0 fw-bold text-danger global-disc-val" value="0" min="0" step="any" oninput="calculateGrandTotal()">
                </div>
                <button type="button" class="btn btn-sm text-danger p-0 position-absolute top-0 end-0" onclick="this.closest('.global-disc-row').remove(); calculateGrandTotal();" style="margin-top:-10px; margin-right:-5px;"><i class="bi bi-x-circle-fill"></i></button>
            </div>
        `;
        document.getElementById('globalDiscContainer').insertAdjacentHTML('beforeend', template);
    }
    
    function removeRow(btn) {
        btn.closest('tr').remove();
        calculateGrandTotal();
    }

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

    // 🔥 LOGIKA CLONING (SPLIT) DIPERBARUI UNTUK CKEDITOR 🔥
    function splitItem(btn) {
        let originalCard = $(btn).closest('.item-card');
        let originalIdx = originalCard.attr('data-original-idx'); 

        originalCard.find('.select2-init').select2('destroy');
        
        let clonedCard = originalCard.clone();
        splitIdx++;
        
        clonedCard.attr('data-parent-idx', originalIdx);
        
        // Bersihkan sampah CKEditor yang ikut ter-clone dari Induk
        clonedCard.find('.ck-editor').remove();
        let clonedTextarea = clonedCard.find('.ckeditor-spec');
        clonedTextarea.show().css('display', ''); // Munculkan kembali textarea aslinya
        
        clonedCard.find('input, select, textarea').each(function() {
            let name = $(this).attr('name');
            if(name) {
                $(this).attr('name', name.replace(/po_items\[\d+\]/, 'po_items[' + splitIdx + ']'));
            }
            $(this).removeAttr('id'); 
        });
        
        // Tanam ID baru untuk CKEditor di card Anak
        let newSpecId = 'spec_' + splitIdx;
        clonedTextarea.attr('id', newSpecId);

        clonedCard.find('[id^="fileListContainer_"]').empty().attr('id', 'fileListContainer_' + splitIdx);
        clonedCard.find('[id^="hiddenFileInputs_"]').empty().attr('id', 'hiddenFileInputs_' + splitIdx);
        clonedCard.find('button[onclick^="triggerFilePicker"]').attr('onclick', 'triggerFilePicker(' + splitIdx + ')');
        
        let accHeader = clonedCard.find('.vendor-pr-header');
        let accBody = clonedCard.find('.vendor-pr-body');
        if(accHeader.length > 0) {
            let newTarget = 'vendorDataSplit' + splitIdx;
            accHeader.attr('data-bs-target', '#' + newTarget);
            accBody.attr('id', newTarget);
        }

        clonedCard.find('.btn-pecah').replaceWith(`
            <button type="button" class="btn btn-sm btn-outline-danger rounded-pill fw-bold mt-2" onclick="removeSplitItem(this, '${newSpecId}')">
                <i class="bi bi-trash-fill"></i> Hapus Pecahan
            </button>
        `);

        clonedCard.find('.qty-input').attr('id', `qty-input-${splitIdx}`);
        clonedCard.find('.uom-selector').attr('onchange', `updateRowUom(this, ${splitIdx})`);
        
        let helpMaxText = clonedCard.find('[id^="max-help-"]');
        helpMaxText.attr('id', `max-help-${splitIdx}`);
        helpMaxText.find('strong').attr('id', `max-val-${splitIdx}`);

        let origQtyInput = originalCard.find('.qty-input');
        let origQty = parseFloat(origQtyInput.val());
        if(origQty > 0) {
            let half = parseFloat((origQty / 2).toFixed(2));
            let rest = parseFloat((origQty - half).toFixed(2));
            origQtyInput.val(rest); 
            clonedCard.find('.qty-input').val(half); 
        }

        originalCard.after(clonedCard);
        
        initSelect2();
        initCKEditor(newSpecId); // Nyalakan nyawa CKEditor di card Anak!
        
        calculateRow(origQtyInput[0]);
        calculateRow(clonedCard.find('.qty-input')[0]);
    }

    function removeSplitItem(btn, editorId) {
        let cardToDelete = $(btn).closest('.item-card');
        let parentIdx = cardToDelete.attr('data-parent-idx');
        let parentCard = $('.item-card[data-original-idx="' + parentIdx + '"]').first();
        
        if(parentCard.length > 0) {
            let deletedQtyInput = cardToDelete.find('.qty-input');
            let deletedQty = parseFloat(deletedQtyInput.val()) || 0;
            let deletedConv = parseFloat(cardToDelete.find('.uom-selector').attr('data-current-conv')) || 1;
            
            let returningBaseQty = deletedQty * deletedConv;

            let parentQtyInput = parentCard.find('.qty-input');
            let parentConv = parseFloat(parentCard.find('.uom-selector').attr('data-current-conv')) || 1;

            let qtyToAddBack = returningBaseQty / parentConv;
            let currentParentQty = parseFloat(parentQtyInput.val()) || 0;
            
            parentQtyInput.val(parseFloat((currentParentQty + qtyToAddBack).toFixed(2)));
            calculateRow(parentQtyInput[0]);
        }
        
        // Musnahkan nyawa CKEditor dari memori sebelum hapus HTML
        if (myEditors[editorId]) {
            myEditors[editorId].destroy();
            delete myEditors[editorId];
        }

        cardToDelete.remove();
        calculateGrandTotal();
    }

    function toggleRow(checkbox) {
        let row = checkbox.closest('.item-row');
        let inputs = row.querySelectorAll('input:not([type="checkbox"]), select, textarea');
        if(checkbox.checked) {
            row.classList.remove('opacity-50');
            inputs.forEach(i => i.disabled = false);
        } else {
            row.classList.add('opacity-50');
            inputs.forEach(i => i.disabled = true);
        }
        calculateGrandTotal();
    }

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
                        <div id="${pillId}" class="file-pill d-flex align-items-center justify-content-between bg-white border rounded-3 p-1 shadow-sm mt-1">
                            <div class="d-flex align-items-center overflow-hidden">
                                <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex justify-content-center align-items-center me-2" style="width: 25px; height: 25px; font-size: 0.7rem;">
                                    <i class="bi bi-file-earmark-text-fill"></i>
                                </div>
                                <div class="text-truncate">
                                    <div class="fw-bold text-dark text-truncate" style="max-width: 100px; font-size: 0.65rem;" title="${file.name}">${file.name}</div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-link text-danger p-0 ms-1" onclick="removeSpecificFile('${inputId}', ${fileIndex}, '${pillId}')" title="Hapus File">
                                <i class="bi bi-x-circle-fill"></i>
                            </button>
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
        if(row.querySelector('.row-checkbox') && !row.querySelector('.row-checkbox').checked) return;

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
        let totalGross = 0;
        let totalItemDisc = 0;
        let totalTax = 0;

        document.querySelectorAll('.item-row').forEach(row => {
            if(row.querySelector('.row-checkbox').checked) {
                let qty = parseFloat(row.querySelector('.qty-input').value) || 0;
                let price = parseFloat(row.querySelector('.price-input').value) || 0;
                totalGross += (qty * price);
                totalItemDisc += parseFloat(row.querySelector('.disc-amt-hidden').value) || 0;
                totalTax += parseFloat(row.querySelector('.tax-amt-hidden').value) || 0;
            }
        });

        let totalCharges = 0;
        document.querySelectorAll('.charge-input').forEach(i => totalCharges += parseFloat(i.value) || 0);

        let totalExtraDisc = 0;
        document.querySelectorAll('.extradisc-input').forEach(i => totalExtraDisc += parseFloat(i.value) || 0);

        let dpp = totalGross - totalItemDisc;

        let globalDiscAmt = 0;
        document.querySelectorAll('.global-disc-row').forEach(row => {
            let type = row.querySelector('select[name$="[type]"]').value;
            let val = parseFloat(row.querySelector('.global-disc-val').value) || 0;
            globalDiscAmt += (type === 'PERCENT') ? (dpp * val / 100) : val;
        });

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
            if(row.querySelector('.row-checkbox').checked) {
                let itemTaxSelect = row.querySelector('.tax-select');
                if(selectedTaxId === 'RESET') { itemTaxSelect.value = ""; }
                else if (selectedTaxId !== "") { itemTaxSelect.value = selectedTaxId; }
                calculateRow(itemTaxSelect);
            }
        });
    }

    function formatCurrency(amount) {
        return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(amount);
    }

    document.getElementById('poForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // 🔥 SINKRONISASI DATA CKEDITOR KE TEXTAREA SEBELUM VALIDASI 🔥
        for (let editorId in myEditors) {
            if (myEditors.hasOwnProperty(editorId) && myEditors[editorId]) {
                myEditors[editorId].updateSourceElement();
            }
        }
        
        // 1. Validasi Wajib Isi (HTML5 Bawaan)
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
                    text: 'Ada kolom wajib yang belum diisi atau format angkanya salah. Silakan periksa kotak yang berwarna MERAH.',
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

        // Matikan Upload Kosong Agar Tidak Error 500
        $('input[type="file"]').each(function() {
            if ($(this).val() === '') {
                $(this).prop('disabled', true);
            }
        });

        // 2. VALIDASI GABUNGAN SISA PR
        let prItemTotals = {};
        let isValidSisa = true;
        let errorMessage = '';

        document.querySelectorAll('.item-row').forEach(row => {
            let checkbox = row.querySelector('.row-checkbox');
            if(checkbox && checkbox.checked) {
                let prItemId = row.querySelector('input[name$="[pr_item_id]"]').value;
                let itemName = row.querySelector('.fw-bolder.text-dark').innerText;
                let qtyInput = row.querySelector('.qty-input');
                let uomSelect = row.querySelector('.uom-selector');

                let qty = parseFloat(qtyInput.value) || 0;
                let convRate = parseFloat(uomSelect.getAttribute('data-current-conv')) || 1;
                let baseRemaining = parseFloat(qtyInput.getAttribute('data-base-remaining')) || 0;

                let baseQtyOrdered = qty * convRate;

                if(!prItemTotals[prItemId]) {
                    prItemTotals[prItemId] = { total: 0, max: baseRemaining, name: itemName };
                }
                prItemTotals[prItemId].total += baseQtyOrdered;
            }
        });

        for (const [prItemId, data] of Object.entries(prItemTotals)) {
            if (parseFloat(data.total.toFixed(4)) > parseFloat(data.max.toFixed(4))) {
                isValidSisa = false;
                errorMessage += `Barang <b>${data.name}</b> melebihi sisa jatah PR!<br><small>Total Pecahan Anda: ${data.total} (Eceran) <br>Jatah Tersedia: Hanya ${data.max} (Eceran)</small><br><br>`;
            }
        }

        if(!isValidSisa) {
            $('input[type="file"]').prop('disabled', false); 
            Swal.fire({
                title: 'Kuantitas Melebihi PR!',
                html: errorMessage + '<span class="text-danger fw-bold" style="font-size:0.85rem;">Silakan kurangi angka pada pecahan item tersebut.</span>',
                icon: 'error',
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Oke, Saya Revisi'
            });
            return; 
        }
        
        // 3. Konfirmasi Lolos
        Swal.fire({
            title: 'Terbitkan Purchase Order?',
            text: "Sistem otomatis akan memecah PO jika Anda memilih vendor yang berbeda untuk barang yang berbeda.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#0d6efd',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Terbitkan PO!'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({ title: 'Menerbitkan Dokumen PO...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                this.submit();
            } else {
                $('input[type="file"]').prop('disabled', false); 
            }
        });
    });
</script>
@endpush