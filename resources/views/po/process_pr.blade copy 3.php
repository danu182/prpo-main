@extends('layouts.app')

@push('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<style>
    .accordion-button:not(.collapsed) { background-color: #f8f9fa; color: #0d6efd; box-shadow: none; }
    .fixed-bottom { background: rgba(255, 255, 255, 0.95) !important; backdrop-filter: blur(10px); z-index: 1040;}

    /* Penyesuaian Select2 dengan Bootstrap 5 */
    .select2-container--bootstrap-5 .select2-selection {
        border-radius: 6px;
        border-color: #dee2e6;
        min-height: 35px;
        font-size: 0.85rem;
    }
    .select2-container--bootstrap-5.select2-container--focus .select2-selection {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
    }
    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
        color: #495057;
        padding-top: 2px;
        font-weight: 600;
    }

    /* Mencegah Harga Miliaran Terpotong */
    .table-custom th { white-space: nowrap; font-size: 0.8rem; letter-spacing: 0.5px; }
    .table-custom td { vertical-align: top; }
    .min-w-price { min-width: 140px !important; }
    .min-w-qty { min-width: 85px !important; }
    .min-w-vendor { min-width: 180px !important; }

    /* Mencegah spinner panah pada input number */
    input[type=number]::-webkit-inner-spin-button,
    input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
    input[type=number] { -moz-appearance: textfield; }

    /* Styling Checkbox Split PO */
    .split-checkbox { width: 1.3rem; height: 1.3rem; cursor: pointer; }

    /* Accordion Opsi Vendor */
    .vendor-accordion-btn {
        padding: 6px 10px; font-size: 0.75rem; background-color: #f0f7ff;
        border: 1px solid #cce5ff; border-radius: 6px; width: 100%; text-align: left;
        display: flex; justify-content: space-between; align-items: center; font-weight: bold; transition: 0.2s;
    }
    .vendor-accordion-btn:hover { background-color: #e6f2ff; }
    .vendor-quote-btn {
        border: 1px solid #dee2e6; background-color: #ffffff; border-radius: 6px;
        padding: 8px 10px; margin-bottom: 5px; width: 100%; text-align: left; transition: 0.2s;
        box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
    }
    .vendor-quote-btn:hover { border-color: #0d6efd; transform: translateY(-2px); cursor: pointer; }

    hr.dashed { border-top: 2px dashed #dee2e6; opacity: 1; }
</style>
@endpush

@section('content')
<div class="container pb-5 text-dark">

    {{-- HEADER HALAMAN --}}
    <div class="mb-4 d-flex justify-content-between align-items-center border-bottom pb-3">
        <div>
            <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-cart-plus-fill me-2 text-primary"></i>Terbitkan Purchase Order</h4>
            <div class="mt-1 text-muted small">
                Konversi PR <strong class="text-primary">{{ $pr->pr_number }}</strong> menjadi PO resmi.
            </div>
        </div>

        <div class="gap-2 d-flex align-items-center">
            @php
                $needDate = \Carbon\Carbon::parse($pr->need_date);
                $isUrgent = $needDate->isPast() || $needDate->diffInDays(now()) <= 3;
            @endphp
            <div class="px-3 py-2 border shadow-sm bg-white rounded-pill text-{{ $isUrgent ? 'danger' : 'dark' }} small d-none d-md-block">
                <i class="bi bi-calendar-exclamation me-1"></i> Target Selesai: <span class="fw-bold">{{ $needDate->format('d M Y') }}</span>
                @if($isUrgent)
                    <span class="ms-1 badge bg-danger rounded-pill" style="font-size: 0.6rem;">Mendesak</span>
                @endif
            </div>

            <a href="{{ route('po.index') }}" class="bg-white shadow-sm btn btn-outline-secondary rounded-pill fw-bold px-4">
                <i class="bi bi-arrow-left me-1"></i> Batal
            </a>
        </div>
    </div>

    <form action="{{ route('po.store_from_pr', $pr->id) }}" method="POST" id="poProcessForm" enctype="multipart/form-data">
        @csrf

        {{-- RIWAYAT PO SEBELUMNYA --}}
        @if(isset($existingPOs) && $existingPOs->count() > 0)
        <div class="mb-4 border-0 border-4 shadow-sm card rounded-4 border-start border-info bg-info bg-opacity-10">
            <div class="p-4 card-body">
                <h6 class="mb-3 fw-bold text-dark">
                    <i class="bi bi-clock-history me-2 text-info"></i>Riwayat PO dari PR Ini
                </h6>
                <div class="bg-white border table-responsive rounded-3">
                    <table class="table mb-0 align-middle table-sm table-hover">
                        <thead class="bg-light text-muted small text-uppercase">
                            <tr>
                                <th class="py-2 ps-3">No. PO</th>
                                <th class="py-2">Tanggal</th>
                                <th class="py-2">Vendor</th>
                                <th class="py-2 text-end">Total Nilai</th>
                                <th class="py-2 text-center">Status</th>
                                <th class="py-2 text-center pe-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            @foreach($existingPOs as $epo)
                            <tr>
                                <td class="py-2 ps-3 fw-bold text-primary">{{ $epo->po_number }}</td>
                                <td class="py-2 text-muted">{{ \Carbon\Carbon::parse($epo->po_date)->format('d M Y') }}</td>
                                <td class="py-2 fw-bold text-dark">{{ optional($epo->vendor)->name ?? 'Vendor Dihapus' }}</td>
                                <td class="py-2 text-end text-success fw-bold">{{ $epo->currency }} {{ number_format($epo->grand_total, 2, ',', '.') }}</td>
                                <td class="py-2 text-center">
                                    <span class="px-2 badge bg-secondary rounded-pill" style="font-size: 0.65rem;">
                                        {{ optional($epo->status)->name ?? 'DRAFT' }}
                                    </span>
                                </td>
                                <td class="py-2 text-center pe-3">
                                    <a href="{{ route('po.show', $epo->id) }}" target="_blank" class="px-3 py-0 btn btn-sm btn-outline-info rounded-pill fw-bold" style="font-size: 0.7rem;">
                                        Likat PO <i class="bi bi-box-arrow-up-right ms-1"></i>
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        <div class="row g-4">
            {{-- KOLOM KIRI (PROPORSI 9) --}}
            <div class="col-xl-9 col-lg-8">

                {{-- 1. ENTITAS PENANGGUNG & PENGIRIMAN --}}
                <div class="mb-4 border-0 shadow-sm card rounded-4">
                    <div class="py-3 bg-white card-header border-bottom">
                        <h6 class="mb-0 fw-bold text-dark text-uppercase" style="font-size: 0.85rem;"><i class="bi bi-buildings me-2 text-primary"></i>Informasi Pengiriman & Pembayaran</h6>
                    </div>
                    <div class="p-4 card-body bg-light bg-opacity-50">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="mb-1 small fw-bold text-muted">Ditanggung Oleh (Billing Entity) <span class="text-danger">*</span></label>
                                <select name="billing_company_id" id="billToSelect" class="shadow-sm form-select select2-init" required>
                                    <option value="">-- Pilih PT --</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}" data-address="{{ $company->address ?? '' }}" {{ $pr->company_id == $company->id ? 'selected' : '' }}>{{ $company->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-end mb-1">
                                    <label class="m-0 small fw-bold text-muted">Lokasi Pengiriman (Ship To)</label>
                                    <button type="button" class="p-0 btn btn-link btn-sm text-decoration-none small text-primary" onclick="updateShippingAddress(true)">
                                        <i class="bi bi-arrow-counterclockwise"></i> Reset
                                    </button>
                                </div>
                                <textarea name="shipping_address" id="shippingAddressInput" class="shadow-sm form-control" rows="1" style="transition: 0.3s;">{{ $pr->company->address ?? '' }}</textarea>
                            </div>

                            <div class="col-md-4">
                                <label class="mb-1 small fw-bold text-muted">Termin Pembayaran <span class="text-danger">*</span></label>
                                <select name="payment_term_id" class="shadow-sm form-select select2-init" required>
                                    <option value="">-- Pilih Termin --</option>
                                    @foreach($paymentTerms as $pt)
                                        <option value="{{ $pt->id }}">{{ $pt->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="mb-1 small fw-bold text-muted">Target Pengiriman <span class="text-danger">*</span></label>
                                @php
                                    $today = date('Y-m-d');
                                    $parsedNeedDate = $pr->need_date ? \Carbon\Carbon::parse($pr->need_date)->format('Y-m-d') : $today;
                                    $defaultDeliveryDate = ($parsedNeedDate < $today) ? $today : $parsedNeedDate;
                                @endphp
                                <input type="date" name="delivery_date" class="shadow-sm form-control" value="{{ $defaultDeliveryDate }}" min="{{ $today }}" required>
                            </div>

                            <div class="col-md-5">
                                <label class="mb-1 small fw-bold text-muted">Catatan PO (Internal/External Notes):</label>
                                <input type="text" name="notes" class="shadow-sm form-control" placeholder="Instruksi untuk vendor..." value="{{ $pr->description ?? '' }}">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 2. TABEL ITEM BARANG --}}
                <div class="mb-4 border-0 shadow-sm card rounded-4 overflow-hidden">
                    <div class="py-3 bg-white card-header border-bottom">
                        <h6 class="mb-0 fw-bold text-dark text-uppercase" style="font-size: 0.85rem;"><i class="bi bi-list-check me-2 text-primary"></i>Penentuan Vendor, Harga & Pajak Item</h6>
                    </div>
                    <div class="p-0 card-body bg-white">
                        <div class="table-responsive">
                            <table class="table mb-0 align-middle table-hover table-bordered border-light table-custom">
                                <thead class="bg-light text-dark fw-bold text-uppercase text-center">
                                    <tr>
                                        <th class="py-3 ps-3 border-end" width="3%" title="Centang untuk memproses">
                                            <i class="bi bi-check-square-fill fs-6 text-primary"></i>
                                        </th>
                                        <th class="py-3 text-start ps-3 border-end" width="25%">Item & Info Vendor PR</th>
                                        <th class="py-3 border-end" width="10%">Qty & Satuan</th>
                                        <th class="py-3 border-end" width="18%">Vendor & Lampiran</th>
                                        <th class="py-3 border-end" width="16%">Mata Uang & Harga</th>
                                        <th class="py-3 border-end" width="12%">Diskon</th>
                                        <th class="py-3 border-end" width="8%">Pajak</th>
                                        <th class="py-3 pe-4 text-end" width="12%">Netto</th>
                                    </tr>
                                </thead>
                                <tbody id="itemsContainer">
                                    @foreach($pr->items as $item)
                                    @php
                                        $orderedQty = $item->ordered_qty ?? 0;
                                        $sisaQty = $item->qty - $orderedQty;
                                        $itemStatus = strtoupper($item->status ?? '');
                                        $isRejected = in_array($itemStatus, ['REJECTED', 'DITOLAK']);

                                        $quote = ($selectedVendor) ? $item->vendorQuotes->where('vendor_id', $selectedVendor->id)->first() : $item->vendorQuotes->first();
                                        $defaultPrice = $quote ? ($quote->price ?? $quote->quoted_price ?? 0) : ($item->estimated_price ?? 0);
                                        $defaultCurrency = $quote ? ($quote->currency ?? 'IDR') : 'IDR';
                                        $defaultVendorId = $quote ? $quote->vendor_id : '';

                                        $itemSpec = $item->item->specification ?? $item->description ?? $item->item->name;
                                        $uomText = is_string($item->uom) ? $item->uom : (optional($item->uom)->name ?? optional($item->item->unit)->name ?? 'PCS');
                                    @endphp

                                    @if($sisaQty > 0)
                                    <tr class="align-top item-row border-bottom {{ $isRejected ? 'bg-danger bg-opacity-10 opacity-75' : '' }}" data-index="{{ $loop->index }}">

                                        {{-- 1. Checkbox --}}
                                        <td class="py-3 text-center ps-3 border-end">
                                            <input type="checkbox" name="po_items[{{ $loop->index }}][is_selected]" class="shadow-sm form-check-input split-checkbox" value="1" {{ $isRejected ? 'disabled' : 'checked' }} onchange="toggleRow(this)">
                                        </td>

                                        {{-- 2. Item & Deskripsi --}}
                                        <td class="py-3 ps-3 border-end">
                                            <div class="mb-1 small text-muted"><i class="bi bi-box me-1"></i>Master: <b class="text-dark">{{ $item->item->name }}</b></div>
                                            <textarea name="po_items[{{ $loop->index }}][description]" class="shadow-sm form-control form-control-sm fw-bold text-primary mb-1 desc-input" rows="2" placeholder="Ketik nama spesifik barang..." {{ $isRejected ? 'disabled' : '' }}>{{ $itemSpec }}</textarea>
                                            <div class="mb-2 small text-muted">Kode: {{ $item->item->code }}</div>

                                            <input type="hidden" name="po_items[{{ $loop->index }}][pr_item_id]" class="pr-id-input" value="{{ $item->id }}" {{ $isRejected ? 'disabled' : '' }}>
                                            <input type="hidden" name="po_items[{{ $loop->index }}][item_id]" value="{{ $item->item_id }}" {{ $isRejected ? 'disabled' : '' }}>

                                            @if($isRejected)
                                                <div class="p-2 mt-2 bg-white border rounded shadow-sm small text-danger border-danger fw-bold">
                                                    <i class="bi bi-shield-lock-fill me-1"></i> Item Ditolak Management.
                                                    <div class="mt-1 fw-normal fst-italic" style="font-size: 0.75rem;">
                                                        Alasan: "{{ $item->rejection_reason ?? $item->reject_reason ?? $item->notes ?? 'Tidak mendapat persetujuan.' }}"
                                                    </div>
                                                </div>
                                            @endif

                                            <div class="mt-2 split-action d-flex gap-1">
                                                <button type="button" class="btn btn-sm btn-outline-primary btn-split shadow-sm" onclick="splitRow(this)" {{ $isRejected ? 'disabled' : '' }} title="Pecah Baris untuk Multi Vendor/Satuan">
                                                    <i class="bi bi-diagram-2 me-1"></i> Pecah
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger btn-remove-split shadow-sm d-none" onclick="removeSplitRow(this)" title="Hapus Baris Pecahan">
                                                    <i class="bi bi-trash"></i> Hapus
                                                </button>
                                            </div>

                                            {{-- OPSI VENDOR DARI PR --}}
                                            @if($item->vendorQuotes && $item->vendorQuotes->count() > 0)
                                                <div class="mt-2 overflow-hidden border shadow-sm accordion accordion-flush rounded-3 border-primary-subtle" id="accItem{{ $loop->index }}">
                                                    <div class="accordion-item">
                                                        <h2 class="accordion-header">
                                                            <button class="vendor-accordion-btn text-primary" type="button" data-bs-toggle="collapse" data-bs-target="#ref{{ $loop->index }}">
                                                                <span><i class="bi bi-search me-1"></i> Opsi Vendor PR</span>
                                                                <i class="bi bi-chevron-down"></i>
                                                            </button>
                                                        </h2>
                                                        <div id="ref{{ $loop->index }}" class="accordion-collapse collapse" data-bs-parent="#accItem{{ $loop->index }}">
                                                            <div class="p-2 bg-white accordion-body">
                                                                <div class="small text-muted mb-2 fst-italic"><i class="bi bi-info-circle me-1"></i>Klik untuk auto-fill:</div>
                                                                @foreach($item->vendorQuotes as $quoteItem)
                                                                    @php
                                                                        $qPrice = $quoteItem->price ?? $quoteItem->quoted_price ?? 0;
                                                                        $qCurr = $quoteItem->currency ?? 'IDR';
                                                                    @endphp
                                                                    <button type="button" class="vendor-quote-btn" onclick="applyQuote(this, '{{ $quoteItem->vendor_id }}', '{{ $qPrice }}', '{{ $qCurr }}')">
                                                                        <div class="mb-1 d-flex justify-content-between align-items-center">
                                                                            <span class="fw-bold small text-dark text-truncate" style="max-width: 120px;">{{ optional($quoteItem->vendor)->name }}</span>
                                                                            <span class="border badge bg-primary-subtle text-primary border-primary-subtle">{{ $qCurr }} {{ number_format($qPrice, 0, ',', '.') }}</span>
                                                                        </div>
                                                                    </button>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                        </td>

                                        {{-- 3. QTY & SATUAN (DENGAN DATALIST UOM) --}}
                                        <td class="py-3 text-center align-top border-end px-2">
                                            <input type="number" name="po_items[{{ $loop->index }}][qty]" class="text-center form-control form-control-sm fw-bold qty-input border-primary mb-1 min-w-qty" value="{{ (float)$sisaQty }}" data-max="{{ (float)$sisaQty }}" step="0.01" oninput="calculateAll()" {{ $isRejected ? 'disabled' : 'required' }}>

                                            {{-- 🔥 FITUR UOM DATALIST 🔥 --}}
                                            <input type="text" name="po_items[{{ $loop->index }}][uom]" list="uomList_{{ $loop->index }}" class="text-center form-control form-control-sm fw-bold text-uppercase text-muted border-secondary-subtle" value="{{ $uomText }}" placeholder="Satuan" required {{ $isRejected ? 'disabled' : '' }}>
                                            <datalist id="uomList_{{ $loop->index }}">
                                                @if(isset($item->item->unit))
                                                    <option value="{{ $item->item->unit }}">Satuan Dasar</option>
                                                @endif
                                                @if(isset($item->item->uoms) && $item->item->uoms->count() > 0)
                                                    @foreach($item->item->uoms as $altUom)
                                                        <option value="{{ $altUom->uom->name ?? $altUom->name }}">{{ $altUom->uom->name ?? $altUom->name }} (Isi: {{ $altUom->conversion_qty ?? '' }})</option>
                                                    @endforeach
                                                @endif
                                            </datalist>

                                            <div class="mt-2 small text-muted bg-light rounded py-1" style="font-size: 0.65rem;">
                                                <i class="bi bi-info-circle"></i> Sisa PR: <b>{{ (float)$sisaQty }}</b>
                                            </div>
                                        </td>

                                        {{-- 4. VENDOR AKTUAL & LAMPIRAN --}}
                                        <td class="py-3 align-top border-end px-2">
                                            <select name="po_items[{{ $loop->index }}][vendor_id]" class="mb-2 form-select form-select-sm vendor-select border-primary fw-bold select2-vendor min-w-vendor" onchange="updateRowData(this, {{ $loop->index }})" {{ $isRejected ? 'disabled' : 'required' }}>
                                                <option value="">-- Pilih Vendor --</option>
                                                @foreach($vendors as $vendor)
                                                    @php
                                                        $quoteCheck = $item->vendorQuotes->where('vendor_id', $vendor->id)->first();
                                                        $isDefault = ($quoteCheck && $quoteCheck->is_selected == 1) || $item->suggested_vendor_id == $vendor->id || $defaultVendorId == $vendor->id;
                                                    @endphp
                                                    <option value="{{ $vendor->id }}" {{ $isDefault ? 'selected' : '' }} data-price="{{ $quoteCheck ? (float)($quoteCheck->price ?? $quoteCheck->quoted_price) : 0 }}" data-currency="{{ $quoteCheck ? $quoteCheck->currency : 'IDR' }}">
                                                        {{ $vendor->name }}
                                                    </option>
                                                @endforeach
                                            </select>

                                            <div class="p-2 border rounded bg-light border-secondary-subtle">
                                                <div class="mb-2 d-flex justify-content-between align-items-center">
                                                    <label class="mb-0 text-muted fw-bold" style="font-size: 0.65rem;"><i class="bi bi-paperclip"></i> Lampiran:</label>
                                                    <button type="button" class="px-2 py-0 btn btn-sm btn-outline-secondary" style="font-size: 0.65rem;" onclick="addFileRow({{ $loop->index }})" {{ $isRejected ? 'disabled' : '' }}><i class="bi bi-plus"></i> Tambah</button>
                                                </div>
                                                <div id="fileContainer_{{ $loop->index }}">
                                                    <div class="mb-1 input-group input-group-sm file-row">
                                                        <input type="file" name="po_items[{{ $loop->index }}][attachments][]" class="bg-white form-control form-control-sm" style="font-size: 0.7rem;" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" {{ $isRejected ? 'disabled' : '' }}>
                                                        <button type="button" class="btn btn-outline-danger px-2" onclick="this.closest('.file-row').remove()" {{ $isRejected ? 'disabled' : '' }}><i class="bi bi-x"></i></button>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        {{-- 5. MATA UANG & HARGA --}}
                                        <td class="py-3 align-top border-end px-2">
                                            <div class="input-group input-group-sm shadow-sm rounded overflow-hidden">
                                                <select name="po_items[{{ $loop->index }}][currency]" id="curr_{{ $loop->index }}" class="form-select bg-soft-blue fw-bold text-primary curr-select" style="max-width: 75px; padding-left: 5px;" onchange="updateRowCurrency(this)" {{ $isRejected ? 'disabled' : '' }}>
                                                    @foreach($currencies as $curr)
                                                        <option value="{{ $curr->code }}" {{ $defaultCurrency == $curr->code ? 'selected' : '' }}>{{ $curr->code }}</option>
                                                    @endforeach
                                                </select>
                                                <input type="number" name="po_items[{{ $loop->index }}][unit_price]" class="form-control text-end fw-bold price-input min-w-price" id="price_{{ $loop->index }}" value="{{ $defaultPrice }}" step="0.01" oninput="calculateAll()" {{ $isRejected ? 'disabled' : '' }} required>
                                            </div>
                                        </td>

                                        {{-- 6. DISKON --}}
                                        <td class="py-3 align-top border-end px-2">
                                            <div class="input-group input-group-sm shadow-sm rounded overflow-hidden">
                                                <input type="number" name="po_items[{{ $loop->index }}][discount_value]" class="form-control text-end line-disc-val min-w-qty" value="0" step="0.01" oninput="calculateAll()" {{ $isRejected ? 'disabled' : '' }}>
                                                <select name="po_items[{{ $loop->index }}][discount_type]" class="form-select bg-light line-disc-type fw-bold text-muted" style="max-width: 60px; padding-left: 5px;" onchange="calculateAll()" {{ $isRejected ? 'disabled' : '' }}>
                                                    <option value="percent">%</option>
                                                    <option value="fixed" class="disc-symbol">{{ $defaultCurrency }}</option>
                                                </select>
                                            </div>
                                            <input type="hidden" name="po_items[{{ $loop->index }}][discount_amount]" class="disc-amt-hidden">
                                        </td>

                                        {{-- 7. PAJAK --}}
                                        <td class="py-3 align-top border-end text-center px-2">
                                            <select name="po_items[{{ $loop->index }}][tax_id]" class="text-center form-select form-select-sm line-tax-select border-secondary-subtle shadow-sm" onchange="calculateAll()" {{ $isRejected ? 'disabled' : '' }}>
                                                <option value="0" data-percent="0">0%</option>
                                                @foreach($taxes as $tax)
                                                    <option value="{{ $tax->id }}" data-percent="{{ $tax->percent }}">{{ $tax->name }}</option>
                                                @endforeach
                                            </select>
                                        </td>

                                        {{-- 8. NETTO --}}
                                        <td class="py-3 pe-3 text-end fw-bold row-total text-primary fs-6 align-top">0.00</td>
                                    </tr>
                                    @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- 3. BIAYA LAIN & POTONGAN --}}
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="border-0 shadow-sm card rounded-4 h-100">
                            <div class="py-3 bg-white card-header border-bottom d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-truck me-2 text-primary"></i>Biaya Tambahan</h6>
                                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill fw-bold" id="addChargeBtn"><i class="bi bi-plus-lg"></i></button>
                            </div>
                            <div class="bg-opacity-50 card-body bg-light">
                                <div id="chargeContainer">
                                    <div class="py-4 text-center text-muted small" id="emptyChargeMsg"><i class="bi bi-info-circle fs-4 d-block text-secondary mb-2"></i>Belum ada biaya (cth: Ongkir).</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border-0 border-4 shadow-sm card rounded-4 h-100 border-start border-danger">
                            <div class="py-3 bg-white card-header border-bottom d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-ticket-perforated me-2 text-danger"></i>Potongan Tambahan</h6>
                                <button type="button" class="btn btn-sm btn-outline-danger rounded-pill fw-bold" id="addDiscountBtn"><i class="bi bi-plus-lg"></i></button>
                            </div>
                            <div class="bg-opacity-50 card-body bg-light">
                                <div id="discountContainer">
                                    <div class="py-4 text-center text-muted small" id="emptyDiscountMsg"><i class="bi bi-tags fs-4 d-block text-secondary mb-2"></i>Belum ada (cth: Voucher).</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ================= KOLOM KANAN: SUMMARY (PROPORSI 3) ================= --}}
            <div class="col-xl-3 col-lg-4">
                <div class="overflow-hidden border-0 shadow-sm card rounded-4 h-100 sticky-top" style="top: 20px;">
                    <div class="p-4 bg-white card-body text-dark">
                        <h6 class="pb-2 mb-3 fw-bold border-bottom text-uppercase" style="font-size: 0.85rem;"><i class="bi bi-calculator me-2 text-primary"></i>Ringkasan PO</h6>

                        <div class="mb-3 row">
                            <label class="col-sm-5 col-form-label small fw-bold text-danger">Diskon Global</label>
                            <div class="col-sm-7">
                                <div class="input-group input-group-sm shadow-sm rounded overflow-hidden">
                                    <select name="global_discount_type" id="global_discount_type" class="form-select border-danger-subtle fw-bold text-danger" style="max-width: 50px;" onchange="calculateAll()">
                                        <option value="percent">%</option>
                                        <option value="fixed" class="global-curr-symbol">Rp</option>
                                    </select>
                                    <input type="number" name="global_discount_value" id="global_discount_value" class="form-control border-danger-subtle text-end text-danger fw-bold" value="0" step="0.01" oninput="calculateAll()">
                                </div>
                            </div>
                        </div>

                        <div class="pb-3 mb-3 row border-bottom">
                            <label class="col-sm-5 col-form-label small fw-bold">Pajak Tambahan</label>
                            <div class="col-sm-7">
                                <select name="global_tax_id" id="global_tax_select" class="form-select form-select-sm border-secondary-subtle shadow-sm" onchange="calculateAll()">
                                    <option value="0" data-percent="0">Tidak Ada</option>
                                    <option value="RESET" data-percent="0">Reset Semua Item</option>
                                    @foreach($taxes as $tax)
                                        <option value="{{ $tax->id }}" data-percent="{{ $tax->percent }}">{{ $tax->name }} ({{ (float)$tax->percent }}%)</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="mb-2 d-flex justify-content-between small">
                            <span class="text-muted">Total Gross</span>
                            <span class="fw-bold" id="label_subtotal">0.00</span>
                        </div>
                        <div class="mb-2 d-flex justify-content-between small text-danger">
                            <span>Diskon (Item & Global)</span>
                            <span class="fw-bold" id="label_discount">0.00</span>
                        </div>
                        <div class="mb-2 d-flex justify-content-between small text-primary">
                            <span>DPP (Dasar Pajak)</span>
                            <span class="fw-bold" id="label_dpp">0.00</span>
                        </div>
                        <div class="mb-2 d-flex justify-content-between small">
                            <span class="text-muted">Total Pajak PPN</span>
                            <span class="fw-bold" id="label_tax">0.00</span>
                        </div>
                        <div class="mb-2 d-flex justify-content-between small text-success">
                            <span>Biaya Tambahan (+)</span>
                            <span class="fw-bold" id="label_charges">0.00</span>
                        </div>
                        <div class="mb-3 d-flex justify-content-between small text-danger">
                            <span>Potongan Voucher (-)</span>
                            <span class="fw-bold" id="label_extra_discounts">0.00</span>
                        </div>

                        <div class="p-3 border border-2 border-primary bg-primary-subtle rounded-3 text-center shadow-sm">
                            <h6 class="mb-1 fw-bold text-dark small">GRAND TOTAL</h6>
                            <div class="d-flex justify-content-center align-items-center text-primary">
                                <span class="global-curr-symbol me-1 fw-bold fs-6">IDR</span>
                                <h4 class="mb-0 fw-bolder" id="label_grandtotal">0.00</h4>
                            </div>
                        </div>

                        <input type="hidden" name="subtotal" id="input_subtotal">
                        <input type="hidden" name="tax_total" id="input_tax">
                        <input type="hidden" name="discount_total" id="input_discount">
                        <input type="hidden" name="charge_total" id="input_charge_total">
                        <input type="hidden" name="grand_total" id="input_grandtotal">
                    </div>
                </div>
            </div>
        </div>

        {{-- ACTION BAR --}}
        <div class="p-3 bg-white shadow-lg fixed-bottom border-top">
            <div class="container gap-3 d-flex justify-content-end align-items-center">
                <button type="button" class="px-5 shadow btn btn-primary rounded-pill fw-bold" id="btnSubmitPO">
                    <i class="bi bi-rocket-takeoff-fill me-1"></i> Terbitkan PO
                </button>
            </div>
        </div>
    </form>
</div>

{{-- DATALIST BIAYA & POTONGAN --}}
<datalist id="chargeTypeList">
    @foreach($chargeTypes as $type) <option value="{{ $type->name }}">{{ $type->category }}</option> @endforeach
</datalist>

@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    let globalPoItemIndex = {{ count($pr->items) }} + 100;

    function initSelect2() {
        $('.select2-init').select2({ theme: 'bootstrap-5', width: '100%' });
        $('.select2-vendor').select2({ theme: 'bootstrap-5', width: '100%', placeholder: '-- Cari/Pilih Vendor --' });
        $('.select2-dynamic').select2({ theme: 'bootstrap-5', width: '100%' });
    }

    $(document).ready(function() {
        initSelect2();
        $('#billToSelect').on('select2:select', function (e) { updateShippingAddress(); });

        setTimeout(() => { if(document.getElementById('shippingAddressInput').value === '') updateShippingAddress(); }, 300);

        document.querySelectorAll('.vendor-select').forEach(select => {
            if(select.value !== "" && !select.disabled) {
                const itemId = select.closest('tr').getAttribute('data-index');
                updateRowData(select, itemId);
            }
        });

        document.querySelectorAll('.split-checkbox').forEach(cb => { if(!cb.checked) toggleRow(cb); });

        // Panggil penyesuaian awal
        document.querySelectorAll('.curr-select').forEach(sel => updateRowCurrency(sel, false));
        updateGlobalCurrency();
        calculateAll();
    });

    // --- APPLY QUOTE DARI AKORDION PR ---
    window.applyQuote = function(btn, vendorId, price, currency) {
        let row = btn.closest('tr');

        let vendorSelect = $(row).find('.vendor-select');
        vendorSelect.val(vendorId).trigger('change');

        let currSelect = row.querySelector('.curr-select');
        if(currSelect) {
            currSelect.value = currency;
            updateRowCurrency(currSelect, false);
        }

        let priceInput = row.querySelector('.price-input');
        priceInput.value = price;

        let accordionDiv = row.querySelector('.collapse');
        if(accordionDiv) { $(accordionDiv).collapse('hide'); }

        calculateAll();

        row.classList.add('bg-warning', 'bg-opacity-10');
        setTimeout(() => row.classList.remove('bg-warning', 'bg-opacity-10'), 600);
    }

    // --- SINKRONISASI MATA UANG ---
    window.updateRowCurrency = function(selectElement, recalc = true) {
        let row = selectElement.closest('tr');
        let currency = selectElement.value;
        let discSymbolOption = row.querySelector('.line-disc-type option[value="fixed"]');
        if(discSymbolOption) { discSymbolOption.text = currency; }

        updateGlobalCurrency();
        if(recalc) calculateAll();
    }

    function updateGlobalCurrency() {
        let firstActiveCurrency = 'IDR';
        let found = false;
        document.querySelectorAll('.item-row').forEach(row => {
            if(!found && row.querySelector('input[type="checkbox"]').checked) {
                let sel = row.querySelector('.curr-select');
                if(sel) { firstActiveCurrency = sel.value; found = true; }
            }
        });

        document.querySelectorAll('.global-curr-symbol').forEach(el => {
            if(el.tagName.toLowerCase() === 'option') { el.text = firstActiveCurrency; }
            else { el.innerText = firstActiveCurrency; }
        });
    }

    // --- UPDATE ALAMAT ---
    function updateShippingAddress() {
        var select = document.getElementById('billToSelect');
        var textarea = document.getElementById('shippingAddressInput');
        var selectedOption = select.options[select.selectedIndex];
        if (selectedOption && selectedOption.getAttribute('data-address')) {
            textarea.value = selectedOption.getAttribute('data-address');
            textarea.classList.add('bg-warning', 'bg-opacity-25');
            setTimeout(() => textarea.classList.remove('bg-warning', 'bg-opacity-25'), 500);
        }
    }

    window.addFileRow = function(indexId) {
        const container = document.getElementById('fileContainer_' + indexId);
        const div = document.createElement('div');
        div.className = 'input-group input-group-sm mb-1 file-row shadow-sm rounded overflow-hidden border border-secondary-subtle';
        div.innerHTML = `
            <input type="file" name="po_items[${indexId}][attachments][]" class="bg-white form-control form-control-sm" style="font-size: 0.7rem;" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" required>
            <button type="button" class="btn btn-danger px-2" onclick="this.closest('.file-row').remove()"><i class="bi bi-x"></i></button>
        `;
        container.appendChild(div);
    }

    window.updateRowData = function(select, indexId) {
        const option = select.options[select.selectedIndex];
        if (!option) return;
        const newPrice = parseFloat(option.dataset.price) || 0;
        const priceInput = document.getElementById('price_' + indexId);
        if(priceInput) {
            const currentPrice = parseFloat(priceInput.value) || 0;
            if (newPrice > 0 || currentPrice === 0) priceInput.value = newPrice;
        }
        const currSelect = document.getElementById('curr_' + indexId);
        if (currSelect) {
            currSelect.value = option.dataset.currency || 'IDR';
            updateRowCurrency(currSelect, false);
        }
        calculateAll();
    }

    // =========================================================================
    // FITUR: PECAH BARIS (SPLIT PO)
    // =========================================================================
    window.splitRow = function(btn) {
        let tr = btn.closest('tr');
        let oldIndex = tr.getAttribute('data-index');
        let newIndex = globalPoItemIndex++;

        let currentDesc = tr.querySelector('.desc-input').value;
        let currentVendor = tr.querySelector('.vendor-select').value;
        let currentPrice = tr.querySelector('.price-input').value;
        let currentCurr = tr.querySelector('.curr-select').value;
        let currentDiscVal = tr.querySelector('.line-disc-val').value;
        let currentDiscType = tr.querySelector('.line-disc-type').value;
        let currentTax = tr.querySelector('.line-tax-select').value;

        let originalQtyInput = tr.querySelector('.qty-input');
        let maxQty = parseFloat(originalQtyInput.getAttribute('data-max')) || 0;

        let prItemId = tr.querySelector('.pr-id-input').value;
        let currentTotalQty = 0;
        document.querySelectorAll('.item-row').forEach(row => {
            let rowPrIdInput = row.querySelector('.pr-id-input');
            if(rowPrIdInput && rowPrIdInput.value === prItemId) {
                let qInput = row.querySelector('.qty-input');
                currentTotalQty += parseFloat(qInput.value) || 0;
            }
        });

        let sisaPecahan = maxQty - currentTotalQty;
        if(sisaPecahan < 0) sisaPecahan = 0;

        let clone = tr.cloneNode(true);
        let select2Spans = clone.querySelectorAll('.select2-container');
        select2Spans.forEach(span => span.remove());
        let selects = clone.querySelectorAll('select');
        selects.forEach(s => {
            s.classList.remove('select2-hidden-accessible');
            s.removeAttribute('data-select2-id'); s.removeAttribute('aria-hidden'); s.removeAttribute('tabindex');
            s.querySelectorAll('option').forEach(o => o.removeAttribute('data-select2-id'));
        });

        let html = clone.innerHTML;
        html = html.replace(new RegExp(`po_items\\[${oldIndex}\\]`, 'g'), `po_items[${newIndex}]`);
        html = html.replace(new RegExp(`_${oldIndex}\\b`, 'g'), `_${newIndex}`);
        html = html.replace(new RegExp(`Item${oldIndex}\\b`, 'g'), `Item${newIndex}`);
        html = html.replace(new RegExp(`ref${oldIndex}\\b`, 'g'), `ref${newIndex}`);
        html = html.replace(new RegExp(`uomList_${oldIndex}`, 'g'), `uomList_${newIndex}`);
        html = html.replace(new RegExp(`updateRowData\\(this, ${oldIndex}\\)`, 'g'), `updateRowData(this, ${newIndex})`);
        html = html.replace(new RegExp(`addFileRow\\(${oldIndex}\\)`, 'g'), `addFileRow(${newIndex})`);

        clone.innerHTML = html;
        clone.setAttribute('data-index', newIndex);
        clone.classList.add('border-start', 'border-4', 'border-info', 'bg-info', 'bg-opacity-10');

        let removeBtn = clone.querySelector('.btn-remove-split');
        if(removeBtn) removeBtn.classList.remove('d-none');

        let fileContainer = clone.querySelector('#fileContainer_' + newIndex);
        if(fileContainer) fileContainer.innerHTML = '';

        let cQty = clone.querySelector('.qty-input'); if(cQty) cQty.value = sisaPecahan > 0 ? sisaPecahan : '';
        let cDesc = clone.querySelector('.desc-input'); if(cDesc) cDesc.value = currentDesc;
        let cVendor = clone.querySelector('.vendor-select'); if(cVendor) cVendor.value = currentVendor;
        let cPrice = clone.querySelector('.price-input'); if(cPrice) cPrice.value = currentPrice;
        let cCurr = clone.querySelector('.curr-select'); if(cCurr) cCurr.value = currentCurr;
        let cDiscVal = clone.querySelector('.line-disc-val'); if(cDiscVal) cDiscVal.value = currentDiscVal;
        let cDiscType = clone.querySelector('.line-disc-type'); if(cDiscType) cDiscType.value = currentDiscType;
        let cTax = clone.querySelector('.line-tax-select'); if(cTax) cTax.value = currentTax;

        tr.parentNode.insertBefore(clone, tr.nextSibling);

        $(clone).find('.select2-vendor').select2({ theme: 'bootstrap-5', width: '100%', placeholder: '-- Cari/Pilih Vendor --' });
        $(clone).find('.select2-vendor').on('select2:select', function (e) { $(this).trigger('change'); });

        calculateAll();
    }

    window.removeSplitRow = function(btn) {
        let tr = btn.closest('tr');
        $(tr).find('.select2-vendor').select2('destroy');
        tr.remove();
        calculateAll();
    }

    // --- BIAYA & DISKON ---
    let chargeIndex = 0;
    const chargeContainer = document.getElementById('chargeContainer');
    const emptyChargeMsg = document.getElementById('emptyChargeMsg');

    document.getElementById('addChargeBtn').addEventListener('click', function() {
        emptyChargeMsg.style.display = 'none';
        const $div = $(`
            <div class="mb-2 charge-row input-group input-group-sm border rounded shadow-sm border-success-subtle">
                <select name="charges[${chargeIndex}][charge_type_id]" class="bg-white form-select fw-bold select2-dynamic" style="width: 45%;" required>
                    <option value="">-- Pilih Ongkir/Biaya --</option>
                    @foreach($chargeTypes as $type) <option value="{{ $type->id }}">{{ $type->name }}</option> @endforeach
                </select>
                <input type="number" name="charges[${chargeIndex}][amount]" class="form-control text-end fw-bold charge-amount text-success" placeholder="Nominal" min="0" step="0.01" required oninput="calculateAll()">
                <button type="button" class="px-2 btn btn-danger" onclick="removeCharge(this)"><i class="bi bi-trash"></i></button>
            </div>
        `);
        $(chargeContainer).append($div);
        chargeIndex++;
        $('.select2-dynamic').select2({ theme: 'bootstrap-5', width: '100%' });
    });

    window.removeCharge = function(btn) {
        $(btn).closest('.charge-row').find('.select2-dynamic').select2('destroy');
        btn.closest('.charge-row').remove();
        if (chargeContainer.querySelectorAll('.charge-row').length === 0) emptyChargeMsg.style.display = 'block';
        calculateAll();
    }

    let extraDiscIndex = 0;
    const discountContainer = document.getElementById('discountContainer');
    const emptyDiscMsg = document.getElementById('emptyDiscountMsg');

    document.getElementById('addDiscountBtn').addEventListener('click', function() {
        emptyDiscMsg.style.display = 'none';
        const $div = $(`
            <div class="mb-2 extra-disc-row input-group input-group-sm border rounded shadow-sm border-danger-subtle">
                <select name="extra_discounts[${extraDiscIndex}][discount_type_id]" class="bg-white form-select fw-bold text-danger select2-dynamic" style="width: 45%;" required>
                    <option value="">-- Pilih Voucher --</option>
                    @foreach($discountTypes as $type) <option value="{{ $type->id }}">{{ $type->name }}</option> @endforeach
                </select>
                <input type="number" name="extra_discounts[${extraDiscIndex}][amount]" class="form-control text-end fw-bold extra-disc-amount text-danger" placeholder="Nominal" min="0" step="0.01" required oninput="calculateAll()">
                <button type="button" class="px-2 btn btn-danger" onclick="removeExtraDiscount(this)"><i class="bi bi-trash"></i></button>
            </div>
        `);
        $(discountContainer).append($div);
        extraDiscIndex++;
        $('.select2-dynamic').select2({ theme: 'bootstrap-5', width: '100%' });
    });

    window.removeExtraDiscount = function(btn) {
        $(btn).closest('.extra-disc-row').find('.select2-dynamic').select2('destroy');
        btn.closest('.extra-disc-row').remove();
        if (discountContainer.querySelectorAll('.extra-disc-row').length === 0) emptyDiscMsg.style.display = 'block';
        calculateAll();
    }

    window.toggleRow = function(checkbox) {
        let row = checkbox.closest('tr');
        let inputs = row.querySelectorAll('input:not([type="checkbox"]), select, textarea, button');
        if(checkbox.checked) {
            row.classList.remove('opacity-50', 'bg-light');
            inputs.forEach(i => i.disabled = false);
        } else {
            row.classList.add('opacity-50', 'bg-light');
            inputs.forEach(i => i.disabled = true);
        }
        updateGlobalCurrency();
        calculateAll();
    }

    window.calculateAll = function() {
        let subtotalGross = 0; let totalLineDiscount = 0; let totalLineTax = 0;
        let totalCharges = 0; let totalExtraDiscounts = 0;

        document.querySelectorAll('.item-row').forEach(row => {
            const checkbox = row.querySelector('.split-checkbox');
            if (checkbox && !checkbox.checked) {
                row.querySelector('.row-total').innerText = "0,00";
                return;
            }

            const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
            const price = parseFloat(row.querySelector('.price-input').value) || 0;
            const lineDiscVal = parseFloat(row.querySelector('.line-disc-val').value) || 0;
            const lineDiscType = row.querySelector('.line-disc-type').value;
            const lineTaxSelect = row.querySelector('.line-tax-select');
            const lineTaxPercent = parseFloat(lineTaxSelect.selectedOptions[0].dataset.percent) || 0;

            const lineTotalGross = qty * price;
            let lineDiscount = (lineDiscType === 'percent') ? (lineTotalGross * (lineDiscVal / 100)) : lineDiscVal;
            let lineDpp = lineTotalGross - lineDiscount;
            let lineTax = lineDpp * (lineTaxPercent / 100);

            subtotalGross += lineTotalGross; totalLineDiscount += lineDiscount; totalLineTax += lineTax;
            row.querySelector('.row-total').innerText = lineDpp.toLocaleString('id-ID', { minimumFractionDigits: 2 });
        });

        const globalDiscType = document.getElementById('global_discount_type').value;
        const globalDiscVal = parseFloat(document.getElementById('global_discount_value').value) || 0;
        let totalAfterLineDiscount = subtotalGross - totalLineDiscount;
        let globalDiscount = (globalDiscType === 'percent') ? (totalAfterLineDiscount * (globalDiscVal / 100)) : globalDiscVal;

        let totalCommercialDiscount = totalLineDiscount + globalDiscount;
        let finalDpp = subtotalGross - totalCommercialDiscount;

        const globalTaxSelect = document.getElementById('global_tax_select');
        let globalTaxPercent = 0;
        if(globalTaxSelect.value !== '' && globalTaxSelect.value !== 'RESET') {
            globalTaxPercent = parseFloat(globalTaxSelect.selectedOptions[0].dataset.percent) || 0;
        }
        let globalTax = finalDpp * (globalTaxPercent / 100);
        let combinedTotalTax = totalLineTax + globalTax;

        document.querySelectorAll('.charge-amount').forEach(input => { totalCharges += parseFloat(input.value) || 0; });
        document.querySelectorAll('.extra-disc-amount').forEach(input => { totalExtraDiscounts += parseFloat(input.value) || 0; });

        let grandTotal = finalDpp + combinedTotalTax + totalCharges - totalExtraDiscounts;

        document.getElementById('lblSubtotal').innerText = subtotalGross.toLocaleString('id-ID', { minimumFractionDigits: 2 });
        document.getElementById('lblDiscount').innerText = '-' + totalCommercialDiscount.toLocaleString('id-ID', { minimumFractionDigits: 2 });
        document.getElementById('lblDpp').innerText = finalDpp.toLocaleString('id-ID', { minimumFractionDigits: 2 });
        document.getElementById('lblTax').innerText = combinedTotalTax.toLocaleString('id-ID', { minimumFractionDigits: 2 });
        document.getElementById('lblCharges').innerText = '+' + totalCharges.toLocaleString('id-ID', { minimumFractionDigits: 2 });
        document.getElementById('lblExtraDiscounts').innerText = '-' + totalExtraDiscounts.toLocaleString('id-ID', { minimumFractionDigits: 2 });

        let activeCurrency = 'IDR';
        const currDropdowns = document.querySelectorAll('select[name$="[currency]"]');
        if(currDropdowns.length > 0) activeCurrency = currDropdowns[0].value;

        document.getElementById('lblGrandTotal').innerText = grandTotal.toLocaleString('id-ID', { minimumFractionDigits: 2 });

        document.getElementById('input_subtotal').value = subtotalGross;
        document.getElementById('input_discount').value = totalCommercialDiscount;
        document.getElementById('input_tax').value = combinedTotalTax;
        document.getElementById('input_charge_total').value = totalCharges;
        document.getElementById('input_grandtotal').value = grandTotal;
    }

    window.applyGlobalTax = function(selectElement) {
        let selectedTaxId = selectElement.value;
        document.querySelectorAll('.item-row').forEach(row => {
            let itemTaxSelect = row.querySelector('.line-tax-select');
            if(selectedTaxId === 'RESET') { itemTaxSelect.value = "0"; }
            else if (selectedTaxId !== "" && selectedTaxId !== "0") { itemTaxSelect.value = selectedTaxId; }
        });
        calculateAll();
    }

    document.getElementById('btnSubmitPO').addEventListener('click', function() {
        const form = document.getElementById('poProcessForm');
        const checkedItems = document.querySelectorAll('.split-checkbox:checked');

        if(checkedItems.length === 0) {
            Swal.fire('Peringatan!', 'Anda harus memilih minimal 1 item yang VALID untuk diterbitkan menjadi PO.', 'warning');
            return;
        }

        let prQtys = {}; let isValidQty = true;
        document.querySelectorAll('.item-row').forEach(row => {
            const checkbox = row.querySelector('.split-checkbox');
            if(checkbox && checkbox.checked) {
                let prInput = row.querySelector('.pr-id-input');
                if(!prInput) return;
                let prId = prInput.value;
                let qtyInput = row.querySelector('.qty-input');
                let qty = parseFloat(qtyInput.value) || 0;
                let max = parseFloat(qtyInput.getAttribute('data-max')) || 0;

                if(!prQtys[prId]) {
                    prQtys[prId] = { sum: 0, max: max, name: row.querySelector('.desc-input').value };
                }
                prQtys[prId].sum += qty;
            }
        });

        for(let id in prQtys) {
            if(prQtys[id].sum > prQtys[id].max) {
                Swal.fire({
                    title: 'Kuantitas Berlebih!',
                    html: `Total pemesanan untuk item <br><b>"${prQtys[id].name}"</b> berjumlah <b class='text-danger'>${prQtys[id].sum}</b>.<br><br>Ini melebihi batas sisa PR (Max: <b>${prQtys[id].max}</b>).`,
                    icon: 'error'
                });
                isValidQty = false; break;
            }
        }

        if(!isValidQty) return;
        if (!form.checkValidity()) { form.reportValidity(); return; }

        Swal.fire({
            title: 'Konfirmasi Terbitkan PO',
            text: "Data keuangan akan disimpan dan PO resmi akan dibuat.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#0d6efd',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Terbitkan PO!',
            cancelButtonText: 'Batal',
            borderRadius: '15px'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({ title: 'Menyimpan...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                form.submit();
            }
        });
    });
</script>
@endpush
