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

    /* ========================================= */
    /* CSS VALIDASI ERROR (KOTAK MERAH)          */
    /* ========================================= */
    .is-invalid {
        border-color: #dc3545 !important;
        background-color: #fff8f8 !important;
    }
    /* Jika error terjadi di dalam input group modern kita */
    .input-group-modern:has(.is-invalid) {
        border-color: #dc3545 !important;
        box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25) !important;
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

    /* Item Card Styling (Pengganti Tabel) */
    .item-card {
        border: 1px solid #e9ecef;
        border-radius: 12px;
        transition: all 0.3s ease;
        background: #fff;
    }
    .item-card.active-item {
        border-color: #0d6efd;
        box-shadow: 0 4px 15px rgba(13, 110, 253, 0.08);
    }
    .item-card.disabled-item {
        opacity: 0.5;
        background-color: #f8f9fa;
        border-color: #dee2e6;
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

    /* Accordion Custom untuk Data Vendor PR */
    .vendor-pr-accordion {
        background-color: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        margin-top: 10px;
        overflow: hidden;
    }
    .vendor-pr-header {
        cursor: pointer;
        padding: 8px 12px;
        font-size: 0.75rem;
        font-weight: 700;
        color: #0d6efd;
        background-color: #e9f2ff;
        transition: background 0.2s;
    }
    .vendor-pr-header:hover { background-color: #dbf0ff; }
    .vendor-pr-body {
        padding: 12px;
        border-top: 1px solid #e9ecef;
        background: #fff;
        font-size: 0.8rem;
    }

    /* Banner Permintaan Asli */
    .original-pr-banner {
        background: linear-gradient(90deg, #f1f8ff 0%, #ffffff 100%);
        border-left: 4px solid #0d6efd;
        padding: 8px 15px;
        border-radius: 4px;
    }


/* CSS Tambahan untuk Tombol Upload Modern */
    .border-dashed {
        border-style: dashed !important;
        border-width: 2px !important;
    }
    .btn-outline-dashed {
        background-color: #f8f9fa;
        transition: all 0.2s;
    }
    .btn-outline-dashed:hover {
        background-color: #e9ecef;
        border-color: #0d6efd !important;
        color: #0d6efd;
    }
    .file-pill {
        transition: all 0.2s;
    }
    .file-pill:hover {
        background-color: #f1f8ff !important;
        border-color: #0d6efd !important;
    }


    input[type=number]::-webkit-inner-spin-button,
    input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
</style>
@endpush

@section('content')

<form action="{{ route('po.store_from_pr', $pr->pr_number) }}" method="POST" id="poForm" enctype="multipart/form-data">
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


            {{-- ========================================================================= --}}
            {{-- 📜 TRACK RECORD: PO YANG SUDAH TERBIT DARI PR INI 📜 --}}
            {{-- ========================================================================= --}}
            @if($existingPos->count() > 0)
            <div class="card shadow-sm border-0 rounded-4 mb-5 border-start border-4 border-info">
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
                                                <span class="fw-bold text-primary">(@ {{ number_format($pItem->unit_price, 0, ',', '.') }})</span>
                                            </li>
                                            @endforeach
                                        </ul>
                                    </td>
                                    <td class="text-center">
                                        @php $color = $oldPo->status->color ?? 'secondary'; @endphp
                                        <span class="badge bg-{{ $color }}-subtle text-{{ $color }} border border-{{ $color }}-subtle rounded-pill px-2" style="font-size: 0.7rem;">
                                            {{ strtoupper($oldPo->status->name ?? 'DRAFT') }}
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



            {{-- 2. DETAIL PESANAN BARANG (UI BARU: CARD-GRID) --}}
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
                        if(strtolower($item->status) !== 'approved') continue;
                        
                        // Lacak UOM Master Barang
                        $baseUomName = optional(optional($item->item)->uom)->name ?? 'PCS';
                        $originalUomShort = $item->uom_short ?? (is_string($item->uom) ? $item->uom : $baseUomName);
                        $originalUomDetail = $item->uom_detail ?? '';
                        
                        // Cari faktor konversi dari PR
                        $prUomFactor = 1;
                        if ($item->item && strtolower($originalUomShort) != strtolower($baseUomName)) {
                            $alt = $item->item->itemUoms->where('uom_name', $originalUomShort)->first();
                            if ($alt) $prUomFactor = (float)($alt->conversion_qty ?? 1);
                        }

                        // KALKULASI SISA DALAM SATUAN DASAR (PCS)
                        $targetBaseQty = $item->qty * $prUomFactor;
                        $baseRemainingQty = max(0, $targetBaseQty - ($item->ordered_qty ?? 0));
                        
                        // SISA DALAM SATUAN PR (Kembali ke Pack)
                        $remaining = $baseRemainingQty / $prUomFactor;

                        // Jika sudah lunas, jangan tampilkan di form PO lagi
                        if($baseRemainingQty <= 0) continue;
                        
                        // Logika Vendor & Harga
                        $suggestedVendorId = $item->suggested_vendor_id;
                        $quote = $suggestedVendorId ? $item->vendorQuotes->where('vendor_id', $suggestedVendorId)->first() : $item->vendorQuotes->first();
                        $price = $quote ? ($quote->quoted_price ?? $quote->price ?? 0) : 0;
                        
                        $quoteCurrency = 'IDR';
                        if($quote && $quote->currency_id) {
                            $currObj = \App\Models\Currency::find($quote->currency_id);
                            if($currObj) $quoteCurrency = $currObj->code;
                        }
                    @endphp

                    <div class="item-card active-item mb-4 shadow-sm item-row" data-original-idx="{{ $index }}">
                        {{-- Header Item --}}
                        <div class="card-header bg-light border-bottom d-flex justify-content-between align-items-center py-3 px-4" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
                            <div class="d-flex align-items-start gap-3 w-75">
                                <input type="checkbox" name="po_items[{{ $index }}][is_selected]" class="form-check-input row-checkbox mt-1" checked onchange="toggleRow(this)" style="transform: scale(1.3); cursor: pointer;">
                                <div class="w-100">
                                    <div class="fw-bolder text-dark fs-6">{{ optional($item->item)->name ?? 'Item Terhapus' }}</div>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle mt-1">{{ optional($item->item)->code }}</span>
                                    
                                    {{-- TOMBOL PECAH ITEM --}}
                                    <button type="button" class="btn btn-sm btn-outline-info rounded-pill py-0 px-2 ms-2 fw-bold btn-pecah" style="font-size: 0.7rem;" onclick="splitItem(this)">
                                        <i class="bi bi-diagram-2-fill"></i> Pecah Item
                                    </button>
                                    
                                    <input type="hidden" name="po_items[{{ $index }}][item_id]" value="{{ $item->item_id }}">
                                    <input type="hidden" name="po_items[{{ $index }}][pr_item_id]" value="{{ $item->id }}">

                                    {{-- Data Vendor PR (Accordion) --}}
                                    @if($quote)
                                    <div class="vendor-pr-accordion mt-2">
                                        <div class="vendor-pr-header d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#vendorData{{ $index }}">
                                            <span><i class="bi bi-search me-1"></i> Data Referensi PR ({{ optional($quote->vendor)->name }})</span>
                                            <i class="bi bi-chevron-down"></i>
                                        </div>
                                        <div class="collapse vendor-pr-body" id="vendorData{{ $index }}">
                                            <div class="row">
                                                <div class="col-sm-6">
                                                    <div class="small text-muted">Penawaran Harga:</div>
                                                    <div class="text-success fw-bolder">{{ $quoteCurrency }} {{ number_format($price, 0, ',', '.') }}</div>
                                                </div>
                                                <div class="col-sm-6 text-sm-end">
                                                    @if($quote->reference_link)
                                                        <a href="{{ $quote->reference_link }}" target="_blank" class="btn btn-sm btn-light border text-primary rounded-pill py-0 px-2" onclick="event.stopPropagation();"><i class="bi bi-link-45deg"></i> Link Bukti</a>
                                                    @endif
                                                </div>
                                                @if($quote->notes) 
                                                    <div class="col-12 mt-2 pt-2 border-top">
                                                        <div class="small text-muted">Catatan PR:</div>
                                                        <div class="text-dark fst-italic">"{{ $quote->notes }}"</div> 
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    @endif
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="small text-muted fw-bold text-uppercase mb-1">Subtotal Netto</div>
                                <h5 class="fw-bolder text-primary mb-0"><span class="currency-label fs-6 text-muted me-1">IDR</span><span class="subtotal-display">0</span></h5>
                                <input type="hidden" class="subtotal-input" value="0">
                            </div>
                        </div>

                        {{-- Body Item (Form Input) --}}
                        <div class="card-body p-4">
                            
                            {{-- 🔥 BANNER INFO PR ASLI 🔥 --}}
                            <div class="original-pr-banner mb-4 d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="small fw-bold text-primary"><i class="bi bi-info-circle-fill me-1"></i>Permintaan Asli PR:</span>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-primary fs-6">{{ $remaining }} {{ $originalUomShort }}</span>
                                    @if($originalUomDetail)
                                        <span class="small text-muted fw-bold ms-1">{{ $originalUomDetail }}</span>
                                    @endif
                                </div>
                            </div>

                            {{-- Bagian Penentuan Vendor & Lampiran --}}
                            {{-- Bagian Penentuan Vendor & Lampiran --}}
                            <div class="row g-3 mb-4 pb-4 border-bottom">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-dark">Vendor Aktual <span class="text-danger">*</span></label>
                                    <select name="po_items[{{ $index }}][vendor_id]" class="form-select select2-init vendor-select" required>
                                        <option value="">- Pilih Vendor -</option>
                                        @foreach($vendors as $v)
                                            <option value="{{ $v->id }}" {{ ($quote && $quote->vendor_id == $v->id) ? 'selected' : '' }}>{{ $v->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-dark">Catatan Spesifikasi</label>
                                    <textarea name="po_items[{{ $index }}][notes]" class="form-control form-input-custom bg-light" rows="1" placeholder="Cth: Substitusi RAM 12GB...">{{ $item->notes }}</textarea>
                                </div>
                                
                                {{-- 🔥 INPUT MULTI-FILE (DENGAN ATRIBUT MULTIPLE) 🔥 --}}
                                {{-- 🔥 MODERN ATTACHMENT MANAGER 🔥 --}}
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-dark">
                                        <i class="bi bi-paperclip me-1 text-primary"></i> Lampiran (Penawaran, Spek)
                                    </label>
                                    
                                    {{-- Container untuk memunculkan list file yang sudah dipilih --}}
                                    <div id="fileListContainer_{{ $index }}" class="d-flex flex-column gap-2 mb-2"></div>
                                    
                                    {{-- Container tersembunyi untuk menyimpan input file asli --}}
                                    <div id="hiddenFileInputs_{{ $index }}" style="display: none;"></div>
                                    
                                    {{-- Tombol Pemicu --}}
                                    <button type="button" class="btn btn-sm border-primary border-dashed text-primary btn-outline-dashed w-100 fw-bold py-2" onclick="triggerFilePicker({{ $index }})">
                                        <i class="bi bi-cloud-arrow-up-fill fs-6 me-1"></i> Tambah File Lampiran
                                    </button>
                                </div>
                            </div>
                            

                            {{-- Baris 2: Harga, Qty, Diskon, Pajak (Grid Rapi) --}}
                            <div class="row g-3">
                                
                                {{-- QTY & SATUAN (BISA DIUBAH) --}}
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-dark">Kuantitas & Satuan <span class="text-danger">*</span></label>
                                    <div class="input-group-modern">
                                        {{-- Menyimpan sisa murni untuk kalkulasi JS --}}
                                        <input type="number" name="po_items[{{ $index }}][qty]" class="form-control fw-bolder text-center qty-input text-primary" value="{{ $remaining }}" max="{{ $remaining }}" min="0.01" step="0.01" data-base-remaining="{{ $baseRemainingQty }}" oninput="calculateRow(this)" required>
                                        
                                        {{-- Dropdown Satuan --}}
                                        {{-- Perlebar max-width agar tulisan isinya tidak terpotong --}}
                                        <select name="po_items[{{ $index }}][uom]" class="form-select text-center fw-bold uom-select bg-light" style="min-width: 120px; font-size:0.75rem;" onchange="changeUom(this)">
                                            
                                            {{-- Base UOM (Tambahkan teks Dasar) --}}
                                            <option value="{{ $baseUomName }}" data-conv="1" {{ $originalUomShort == $baseUomName ? 'selected' : '' }}>
                                                {{ $baseUomName }} (Eceran)
                                            </option>
                                            
                                            {{-- Alt UOMs (Tambahkan teks Isi Konversi) --}}
                                            @if(optional($item->item)->itemUoms)
                                                @foreach($item->item->itemUoms as $alt)
                                                    @php
                                                        $altName = $alt->uom_name ?? optional($alt->uom)->name ?? 'ALT';
                                                        $altConv = (float)($alt->conversion_qty ?? 1);
                                                    @endphp
                                                    <option value="{{ $altName }}" data-conv="{{ $altConv }}" {{ $originalUomShort == $altName ? 'selected' : '' }}>
                                                        {{ $altName }} (Isi: {{ $altConv }})
                                                    </option>
                                                @endforeach
                                            @endif
                                            
                                        </select>
                                    </div>
                                    <div class="text-muted small mt-1 max-qty-info" style="font-size: 0.7rem;">Maks: {{ $remaining }} {{ $originalUomShort }}</div>
                                </div>

                                {{-- Harga --}}
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-dark">Harga Satuan <span class="text-danger">*</span></label>
                                    <div class="input-group-modern">
                                        <span class="input-group-text currency-label">IDR</span>
                                        <input type="number" name="po_items[{{ $index }}][unit_price]" class="form-control text-end fw-bold price-input" value="{{ $price }}" min="0" step="any" oninput="calculateRow(this)" required>
                                    </div>
                                </div>

                                {{-- Diskon --}}
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-dark">Diskon per Item</label>
                                    <div class="input-group-modern">
                                        <select name="po_items[{{ $index }}][discount_type]" class="form-select text-center fw-bold text-secondary disc-type" style="max-width: 65px; font-size: 0.75rem;" onchange="calculateRow(this)">
                                            <option value="PERCENT">%</option>
                                            <option value="FIXED">Nom</option>
                                        </select>
                                        <input type="number" name="po_items[{{ $index }}][discount_value]" class="form-control text-end fw-bold disc-val text-danger" value="0" oninput="calculateRow(this)">
                                    </div>
                                    <input type="hidden" name="po_items[{{ $index }}][discount_amount]" class="disc-amt-hidden">
                                </div>

                                {{-- Pajak --}}
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-dark">Pajak (VAT/PPN)</label>
                                    <select name="po_items[{{ $index }}][tax_id]" class="form-select form-input-custom tax-select fw-bold text-muted" onchange="calculateRow(this)">
                                        <option value="" data-percent="0">Non-Pajak</option>
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
                    <div class="mb-4 bg-light p-3 rounded-3 border border-secondary-subtle">
                        {{-- DISKON GLOBAL --}}
                        <label class="form-label small fw-bold text-dark mb-2"><i class="bi bi-tags me-1 text-danger"></i> Diskon Global Keseluruhan</label>
                        <div class="input-group input-group-sm mb-3 shadow-sm rounded-2 overflow-hidden border border-secondary-subtle">
                            <select name="global_discount_type" id="globalDiscType" class="px-2 text-center form-select border-0 bg-white text-dark fw-bold" style="max-width: 70px;" onchange="calculateGrandTotal()">
                                <option value="PERCENT">%</option>
                                <option value="FIXED">Nominal</option>
                            </select>
                            <input type="number" name="global_discount_value" id="globalDiscVal" class="px-2 form-control text-end border-0 fw-bold text-danger" value="0" min="0" oninput="calculateGrandTotal()">
                        </div>

                        {{-- PAJAK MASSAL --}}
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
    <tr class="charge-row">
        <td width="55%" class="p-1"><input type="text" name="charges[INDEX][charge_type_id]" class="form-control form-input-custom" list="chargeTypeList" placeholder="Ketik Biaya..." required></td>
        <td width="35%" class="p-1"><input type="number" name="charges[INDEX][amount]" class="form-control form-input-custom text-end fw-bold text-success charge-input" placeholder="0" min="0" oninput="calculateGrandTotal()" required></td>
        <td width="10%" class="p-1 text-center"><button type="button" class="btn text-danger p-0 mt-1" onclick="removeRow(this)"><i class="bi bi-trash-fill fs-5"></i></button></td>
    </tr>
</template>

{{-- TEMPLATE POTONGAN TAMBAHAN --}}
<template id="extraDiscRowTemplate">
    <tr class="extradisc-row">
        <td width="55%" class="p-1"><input type="text" name="extra_discounts[INDEX][discount_type_id]" class="form-control form-input-custom" list="discountTypeList" placeholder="Ketik Diskon..." required></td>
        <td width="35%" class="p-1"><input type="number" name="extra_discounts[INDEX][amount]" class="form-control form-input-custom text-end fw-bold text-danger extradisc-input" placeholder="0" min="0" oninput="calculateGrandTotal()" required></td>
        <td width="10%" class="p-1 text-center"><button type="button" class="btn text-danger p-0 mt-1" onclick="removeRow(this)"><i class="bi bi-trash-fill fs-5"></i></button></td>
    </tr>
</template>

@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    let chargeIdx = 100;
    let discIdx = 100;
    let splitIdx = 5000; 

    function initSelect2() {
        $('.select2-init').select2({ theme: 'bootstrap-5', width: '100%' });
    }

    $(document).ready(function() {
        initSelect2();
        
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
        let currency = document.getElementById('currencySelect').value;
        document.querySelectorAll('.currency-label').forEach(el => el.innerText = currency);
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
    
    function removeRow(btn) {
        btn.closest('tr').remove();
        calculateGrandTotal();
    }

    // ==========================================
    // LOGIKA UOM KONVERSI (SMART UOM)
    // ==========================================
    function changeUom(selectElement) {
        let row = selectElement.closest('.item-card');
        let qtyInput = row.querySelector('.qty-input');
        let maxInfo = row.querySelector('.max-qty-info');
        
        // Ambil data-base-remaining (sisa murni dalam PCS)
        let baseRemaining = parseFloat(qtyInput.getAttribute('data-base-remaining')) || 0;
        
        // Ambil nilai konversi dari option yang dipilih (cth: Pack = 20, PCS = 1)
        let selectedOption = selectElement.options[selectElement.selectedIndex];
        let convFactor = parseFloat(selectedOption.getAttribute('data-conv')) || 1;
        
        // Hitung ulang batas maksimal berdasarkan satuan baru
        // Misal sisa = 40 Pcs. Dipilih satuan Pack (Isi 20). Maka max = 40/20 = 2 Pack.
        let newMax = parseFloat((baseRemaining / convFactor).toFixed(2));
        
        // Update input
        qtyInput.setAttribute('max', newMax);
        
        // Cegah value saat ini melebihi max baru
        let currentVal = parseFloat(qtyInput.value) || 0;
        if (currentVal > newMax) {
            qtyInput.value = newMax;
        }
        
        // Update teks info
        let uomName = selectedOption.value;
        maxInfo.innerText = `Maks: ${newMax} ${uomName}`;
        
        // Hitung ulang subtotal
        calculateRow(qtyInput);
    }

    // ==========================================
    // LOGIKA PECAH ITEM (SPLIT ITEM)
    // ==========================================
    function splitItem(btn) {
        let originalCard = $(btn).closest('.item-card');
        
        originalCard.find('.select2-init').select2('destroy');
        
        let clonedCard = originalCard.clone();
        splitIdx++;
        
        clonedCard.find('input, select, textarea').each(function() {
            let name = $(this).attr('name');
            if(name) {
                $(this).attr('name', name.replace(/po_items\[\d+\]/, 'po_items[' + splitIdx + ']'));
            }
            $(this).removeAttr('id'); 
        });
        
        let accHeader = clonedCard.find('.vendor-pr-header');
        let accBody = clonedCard.find('.vendor-pr-body');
        if(accHeader.length > 0) {
            let newTarget = 'vendorDataSplit' + splitIdx;
            accHeader.attr('data-bs-target', '#' + newTarget);
            accBody.attr('id', newTarget);
        }

        clonedCard.find('.btn-pecah').replaceWith(`
            <button type="button" class="btn btn-sm btn-outline-danger rounded-pill py-0 px-2 ms-2 fw-bold" style="font-size: 0.7rem;" onclick="removeSplitItem(this)">
                <i class="bi bi-trash-fill"></i> Hapus Pecahan
            </button>
        `);

        let origQtyInput = originalCard.find('.qty-input');
        let origQty = parseFloat(origQtyInput.val());
        if(origQty > 1) {
            let half = parseFloat((origQty / 2).toFixed(2));
            let rest = parseFloat((origQty - half).toFixed(2));
            origQtyInput.val(rest); 
            clonedCard.find('.qty-input').val(half); 
        }

        originalCard.after(clonedCard);
        
        initSelect2();
        
        calculateRow(origQtyInput[0]);
        calculateRow(clonedCard.find('.qty-input')[0]);
    }

    function removeSplitItem(btn) {
        let card = $(btn).closest('.item-card');
        card.remove();
        calculateGrandTotal();
    }

    function toggleRow(checkbox) {
        let row = checkbox.closest('.item-card');
        let inputs = row.querySelectorAll('input:not([type="checkbox"]), select, textarea');
        if(checkbox.checked) {
            row.classList.remove('disabled-item');
            row.classList.add('active-item');
            inputs.forEach(i => i.disabled = false);
        } else {
            row.classList.add('disabled-item');
            row.classList.remove('active-item');
            inputs.forEach(i => i.disabled = true);
        }
        calculateGrandTotal();
    }

    // ==========================================
    // LOGIKA UPLOAD MULTI-FOLDER (MODERN)
    // ==========================================
    function triggerFilePicker(index) {
        // Buat input file bayangan
        let fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.name = `po_items[${index}][attachments][]`;
        fileInput.multiple = true; // Masih bisa select banyak sekaligus
        fileInput.accept = '.pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx';

        // Saat user selesai memilih file
        fileInput.onchange = function() {
            if(this.files.length > 0) {
                let hiddenContainer = document.getElementById('hiddenFileInputs_' + index);
                let listContainer = document.getElementById('fileListContainer_' + index);
                
                // Buat ID Unik untuk batch input ini
                let inputId = 'fileInput_' + Date.now() + Math.random().toString(36).substr(2, 5);
                this.id = inputId;
                hiddenContainer.appendChild(this); // Simpan input ke dalam form

                // Looping untuk membuat UI Kotak File (Pill) di layar
                Array.from(this.files).forEach((file, fileIndex) => {
                    let pillId = 'pill_' + inputId + '_' + fileIndex;
                    let sizeKB = (file.size / 1024).toFixed(1) + ' KB';
                    
                    let pillHTML = `
                        <div id="${pillId}" class="file-pill d-flex align-items-center justify-content-between bg-white border rounded-3 p-2 shadow-sm">
                            <div class="d-flex align-items-center overflow-hidden">
                                <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex justify-content-center align-items-center me-2" style="width: 32px; height: 32px;">
                                    <i class="bi bi-file-earmark-text-fill"></i>
                                </div>
                                <div class="text-truncate">
                                    <div class="small fw-bold text-dark text-truncate" style="max-width: 150px;" title="${file.name}">${file.name}</div>
                                    <div class="text-muted" style="font-size: 0.65rem;">${sizeKB}</div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-link text-danger p-0 ms-2" onclick="removeSpecificFile('${inputId}', ${fileIndex}, '${pillId}')" title="Hapus File">
                                <i class="bi bi-x-circle-fill fs-5"></i>
                            </button>
                        </div>
                    `;
                    listContainer.insertAdjacentHTML('beforeend', pillHTML);
                });
            }
        };

        // Otomatis klik input file bayangan tersebut
        fileInput.click();
    }

    function removeSpecificFile(inputId, fileIndexToRemove, pillId) {
        let inputEle = document.getElementById(inputId);
        if(inputEle) {
            // Gunakan DataTransfer untuk memanipulasi FileList bawaan browser
            let dt = new DataTransfer();
            let files = inputEle.files;
            
            for(let i = 0; i < files.length; i++) {
                if(i !== fileIndexToRemove) {
                    dt.items.add(files[i]); // Masukkan kembali file yang tidak dihapus
                }
            }
            
            inputEle.files = dt.files; // Timpa dengan data baru
            
            // Jika input kosong (semua file di batch ini dihapus), buang tag input-nya
            if(inputEle.files.length === 0) {
                inputEle.remove();
            }
        }
        
        // Hapus kotak UI-nya dari layar dengan efek animasi
        let pill = document.getElementById(pillId);
        pill.style.opacity = '0';
        setTimeout(() => pill.remove(), 200);
    }

    // Engine Kalkulasi Real-time
    function calculateRow(el) {
        let row = el.closest('.item-card');
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

        document.querySelectorAll('.item-card').forEach(row => {
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

        let gDiscType = document.getElementById('globalDiscType').value;
        let gDiscVal = parseFloat(document.getElementById('globalDiscVal').value) || 0;
        let globalDiscAmt = (gDiscType === 'PERCENT') ? (dpp * gDiscVal / 100) : gDiscVal;

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
        document.querySelectorAll('.item-card').forEach(row => {
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

    // Submit Handling dengan Smart Validation
    document.getElementById('poForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // 1. CEK JIKA ADA KOLOM YANG KOSONG ATAU SALAH FORMAT
        if(!this.checkValidity()) { 
            // Cari semua elemen yang error
            let invalidElements = this.querySelectorAll(':invalid');
            
            if(invalidElements.length > 0) {
                let firstInvalid = invalidElements[0];
                
                // Tambahkan kotak merah ke semua kolom yang salah
                invalidElements.forEach(el => {
                    el.classList.add('is-invalid');
                    
                    // Otomatis hilangkan merah saat user mulai mengetik/memperbaiki
                    el.addEventListener('input', function() {
                        this.classList.remove('is-invalid');
                    }, {once: true});
                    el.addEventListener('change', function() {
                        this.classList.remove('is-invalid');
                    }, {once: true});
                });

                // Tampilkan Peringatan Jelas
                Swal.fire({
                    title: 'Data Belum Lengkap!',
                    text: 'Ada kolom wajib yang belum diisi atau format angkanya salah. Silakan periksa kotak yang berwarna MERAH.',
                    icon: 'error',
                    confirmButtonColor: '#dc3545',
                    confirmButtonText: 'Oke, Saya Perbaiki'
                }).then(() => {
                    // Otomatis scroll perlahan tepat ke kolom yang salah
                    firstInvalid.scrollIntoView({behavior: 'smooth', block: 'center'});
                    firstInvalid.focus();
                });
            }
            return; 
        }
        
        // 2. JIKA SEMUA VALID, MUNCULKAN KONFIRMASI TERBITKAN PO
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
            }
        });
    });
</script>
@endpush