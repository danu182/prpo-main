@extends('layouts.app')

@section('content')
<div class="container pb-5 text-dark">

    {{-- HEADER HALAMAN --}}
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-pencil-square me-2 text-warning"></i>Edit Purchase Order</h4>
            <div class="mt-1 text-muted small">Revisi PO <strong class="text-primary">{{ $po->po_number }}</strong>. Status: {{ optional($po->status)->name }}</div>
        </div>

        <div class="gap-2 d-flex align-items-center">
            {{-- TAMBAHAN: Informasi Need Date dari PR yang Terhubung --}}
            @if(isset($po->purchaseRequest) && $po->purchaseRequest->need_date)
                @php
                    $needDate = \Carbon\Carbon::parse($po->purchaseRequest->need_date);
                    $isUrgent = $needDate->isPast() || $needDate->diffInDays(now()) <= 3;
                @endphp
                <div class="px-3 py-2 border shadow-sm bg-white rounded-pill text-{{ $isUrgent ? 'danger' : 'dark' }} small d-none d-md-block">
                    <i class="bi bi-calendar-exclamation me-1"></i> Target Selesai: <span class="fw-bold">{{ $needDate->format('d M Y') }}</span>
                    @if($isUrgent)
                        <span class="ms-1 badge bg-danger rounded-pill" style="font-size: 0.6rem;">Mendesak</span>
                    @endif
                </div>
            @endif

            <a href="{{ route('po.show', $po->id) }}" class="bg-white shadow-sm btn btn-outline-secondary rounded-pill fw-bold">
                <i class="bi bi-arrow-left me-1"></i> Batal
            </a>
        </div>
    </div>

    {{-- FORM DENGAN ENCTYPE MULTIPART UNTUK UPLOAD FILE --}}
    <form action="{{ route('po.update', $po->id) }}" method="POST" id="poProcessForm" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        {{-- 1. ENTITAS PENANGGUNG & PENGIRIMAN --}}
        <div class="mb-4 border-0 border-4 shadow-sm card rounded-4 border-start border-warning">
            <div class="p-4 card-body">
                <h6 class="mb-3 fw-bold text-dark"><i class="bi bi-info-circle-fill me-2 text-warning"></i>Informasi Pengiriman & Pembayaran</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="mb-1 small fw-bold text-muted">Ditanggung Oleh (Billing Entity):</label>
                        <select name="billing_company_id" class="shadow-sm form-select" required>
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}" {{ $po->bill_to_company_id == $company->id ? 'selected' : '' }}>{{ $company->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="mb-1 small fw-bold text-muted">Lokasi Pengiriman (Shipping Address):</label>
                        <textarea name="shipping_address" class="shadow-sm form-control" rows="1">{{ $po->shipping_address }}</textarea>
                    </div>

                    {{-- BARIS KEDUA --}}
                    <div class="col-md-4">
                        <label class="mb-1 small fw-bold text-muted">Termin Pembayaran:</label>
                        <select name="payment_term_id" class="shadow-sm form-select" required>
                            <option value="">-- Pilih Termin --</option>
                            @foreach($paymentTerms as $pt)
                                <option value="{{ $pt->id }}" {{ $po->payment_terms == $pt->name ? 'selected' : '' }}>{{ $pt->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- TAMBAHAN: Target Pengiriman (Ambil dari data PO, bukan PR) --}}
                    <div class="col-md-3">
                        <label class="mb-1 small fw-bold text-muted">Target Pengiriman:</label>
                        <input type="date" name="delivery_date" class="shadow-sm form-control" value="{{ $po->delivery_date }}" required>
                    </div>

                    {{-- Kolom Notes (Disesuaikan jadi col-md-5 agar sejajar) --}}
                    <div class="col-md-5">
                        <label class="mb-1 small fw-bold text-muted">Catatan PO (Internal/External Notes):</label>
                        <input type="text" name="notes" class="shadow-sm form-control" value="{{ $po->notes }}" placeholder="Catatan yang akan tercetak di PO...">
                    </div>
                </div>
            </div>
        </div>

        {{-- 2. TABEL ITEM BARANG & LAMPIRAN --}}
        <div class="mb-4 border-0 shadow-sm card rounded-4">
            <div class="py-3 bg-white card-header border-bottom">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-list-check me-2 text-primary"></i>Revisi Harga, Pajak & Lampiran</h6>
            </div>
            <div class="p-0 card-body">
                <div class="table-responsive">
                    <table class="table mb-0 align-middle table-hover" id="poTable">
                        <thead class="bg-light text-muted small text-uppercase">
                            <tr>
                                <th class="py-3 ps-4" width="22%">Item Barang</th>
                                <th class="py-3 text-center" width="8%">Qty</th>
                                <th class="py-3" width="22%">Vendor & Lampiran</th>
                                <th class="py-3" width="18%">Mata Uang & Harga</th>
                                <th class="py-3" width="10%">Diskon</th>
                                <th class="py-3" width="10%">Pajak Item</th>
                                <th class="py-3 pe-4 text-end" width="10%">Netto</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($po->items as $item)
                            <tr class="align-top item-row">
                                <td class="py-3 ps-4">
                                    <h6 class="mb-0 fw-bold text-dark fs-6">{{ $item->item->name ?? 'Barang Dihapus' }}</h6>
                                    @if($item->item)
                                        <div class="mb-2 small text-muted">{{ $item->item->code }}</div>
                                    @endif

                                    <input type="hidden" name="po_items[{{ $item->id }}][pr_item_id]" value="{{ $item->purchase_request_item_id }}">
                                </td>
                                <td>
                                    <input type="number" name="po_items[{{ $item->id }}][qty]" class="text-center form-control form-control-sm fw-bold qty-input" value="{{ (float)$item->qty_ordered }}" step="0.01">
                                </td>

                                {{-- VENDOR & UPLOAD FILE --}}
                                <td>
                                    <div class="p-2 mb-2 border rounded bg-light">
                                        <div class="mb-1 small text-muted">Vendor PO saat ini:</div>
                                        <strong class="d-block text-truncate text-dark" title="{{ $po->vendor->name ?? '-' }}">{{ $po->vendor->name ?? '-' }}</strong>
                                    </div>

                                    {{-- Tampilkan File Lama (Hanya di baris pertama agar tidak dobel tampilannya) --}}
                                    @if($loop->first && isset($po->attachments) && $po->attachments->count() > 0)
                                    <div class="p-2 mb-2 border rounded border-info bg-info bg-opacity-10">
                                        <label class="mb-1 d-block fw-bold text-info-emphasis" style="font-size: 0.65rem;">
                                            <i class="bi bi-folder-check"></i> File Tersimpan:
                                        </label>
                                        <ul class="mb-0 list-unstyled" style="font-size: 0.7rem;">
                                            @foreach($po->attachments as $file)
                                                <li class="pb-1 mb-1 d-flex justify-content-between align-items-center border-bottom border-info-subtle">
                                                    <a href="{{ Storage::url($file->file_path) }}" target="_blank" class="text-decoration-none text-primary fw-bold text-truncate" style="max-width: 85%;">
                                                        <i class="bi bi-file-earmark-text"></i> {{ $file->file_name }}
                                                    </a>
                                                    {{-- TOMBOL HAPUS --}}
                                                    <button type="button" class="px-1 py-0 btn btn-sm text-danger" onclick="deleteAttachment({{ $file->id }})" title="Hapus File Ini">
                                                        <i class="bi bi-trash-fill"></i>
                                                    </button>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                    @endif

                                    {{-- Input Tambah File Baru --}}
                                    <div class="p-2 border rounded bg-light border-secondary-subtle">
                                        <div class="mb-2 d-flex justify-content-between align-items-center">
                                            <label class="mb-0 text-muted fw-bold" style="font-size: 0.65rem;">
                                                <i class="bi bi-paperclip"></i> Tambah Lampiran Baru:
                                            </label>
                                            <button type="button" class="px-2 py-0 btn btn-sm btn-outline-secondary" style="font-size: 0.65rem;" onclick="addFileRow({{ $item->id }})">
                                                <i class="bi bi-plus"></i> Tambah
                                            </button>
                                        </div>
                                        <div id="fileContainer_{{ $item->id }}">
                                            <div class="mb-1 input-group input-group-sm file-row">
                                                <input type="file" name="po_items[{{ $item->id }}][attachments][]" class="bg-white form-control form-control-sm" style="font-size: 0.7rem;" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                                                <button type="button" class="btn btn-outline-danger" onclick="this.closest('.file-row').remove()"><i class="bi bi-x"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <div class="input-group input-group-sm">
                                        <select name="po_items[{{ $item->id }}][currency]" id="curr_{{ $item->id }}" class="form-select bg-light fw-bold text-primary" style="max-width: 85px; padding-left: 5px;">
                                            @foreach($currencies as $curr)
                                                <option value="{{ $curr->code }}" {{ $po->currency == $curr->code ? 'selected' : '' }}>{{ $curr->code }}</option>
                                            @endforeach
                                        </select>
                                        <input type="number" name="po_items[{{ $item->id }}][unit_price]" class="form-control text-end fw-bold price-input" id="price_{{ $item->id }}" value="{{ (float)$item->unit_price }}" step="0.01">
                                    </div>
                                </td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <input type="number" name="po_items[{{ $item->id }}][discount_value]" class="form-control text-end line-disc-val" value="{{ (float)$item->discount_value }}" step="0.01">
                                        <select name="po_items[{{ $item->id }}][discount_type]" class="form-select bg-light line-disc-type" style="max-width: 55px; padding-left: 5px;">
                                            <option value="percent" {{ $item->discount_type == 'percent' ? 'selected' : '' }}>%</option>
                                            <option value="fixed" {{ $item->discount_type == 'fixed' ? 'selected' : '' }}>Rp</option>
                                        </select>
                                    </div>
                                </td>
                                <td>
                                    <select name="po_items[{{ $item->id }}][tax_id]" class="text-center form-select form-select-sm line-tax-select border-secondary-subtle">
                                        <option value="0" data-percent="0">0%</option>
                                        @foreach($taxes as $tax)
                                            <option value="{{ $tax->id }}" data-percent="{{ $tax->percent }}" {{ $item->tax_id == $tax->id ? 'selected' : '' }}>
                                                {{ $tax->name }} ({{ (float)$tax->percent }}%)
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="pe-4 text-end fw-bold row-total text-primary">0.00</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- 3. SUSUNAN GRID 3 KOLOM --}}
        <div class="mb-5 row g-3">

            {{-- KOTAK 1: BIAYA TAMBAHAN --}}
            <div class="col-lg-4 col-md-12">
                <div class="border-0 shadow-sm card rounded-4 h-100">
                    <div class="py-3 bg-white card-header border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-truck me-2 text-primary"></i>Biaya Tambahan</h6>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill fw-bold" id="addChargeBtn">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                    <div class="bg-opacity-50 card-body bg-light">
                        <div id="chargeContainer">
                            @if(isset($charges) && $charges->count() > 0)
                                @foreach($charges as $idx => $charge)
                                    <div class="mb-2 charge-row">
                                        <div class="border rounded shadow-sm input-group input-group-sm">
                                            <select name="charges[{{ $idx }}][charge_type_id]" class="bg-white form-select fw-bold" style="width: 45%;" required>
                                                <option value="">-- Pilih --</option>
                                                @foreach($chargeTypes as $type)
                                                    <option value="{{ $type->id }}" {{ $charge->name == $type->name ? 'selected' : '' }}>{{ $type->name }}</option>
                                                @endforeach
                                            </select>
                                            <input type="number" name="charges[{{ $idx }}][amount]" class="form-control text-end fw-bold charge-amount" value="{{ (float)$charge->amount }}" min="0" step="0.01" required>
                                            <button type="button" class="px-2 btn btn-danger" onclick="removeCharge(this)"><i class="bi bi-trash"></i></button>
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                            <div class="py-4 text-center text-muted small" id="emptyChargeMsg" style="display: {{ (isset($charges) && $charges->count() > 0) ? 'none' : 'block' }};">
                                <i class="mb-2 bi bi-info-circle fs-4 d-block text-secondary"></i>Belum ada biaya (cth: Ongkir).
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- KOTAK 2: POTONGAN TAMBAHAN (VOUCHER/MEMBER) --}}
            <div class="col-lg-4 col-md-12">
                <div class="border-0 border-4 shadow-sm card rounded-4 h-100 border-start border-danger">
                    <div class="py-3 bg-white card-header border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-ticket-perforated me-2 text-danger"></i>Potongan Tambahan</h6>
                        <button type="button" class="btn btn-sm btn-outline-danger rounded-pill fw-bold" id="addDiscountBtn">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                    <div class="bg-opacity-50 card-body bg-light">
                        <div id="discountContainer">
                            @if(isset($extraDiscounts) && $extraDiscounts->count() > 0)
                                @foreach($extraDiscounts as $idx => $disc)
                                    <div class="mb-2 extra-disc-row">
                                        <div class="border rounded shadow-sm input-group input-group-sm border-danger-subtle">
                                            <select name="extra_discounts[{{ $idx }}][discount_type_id]" class="bg-white form-select fw-bold text-danger" style="width: 45%;" required>
                                                <option value="">-- Pilih --</option>
                                                @foreach($discountTypes as $type)
                                                    <option value="{{ $type->id }}" {{ $disc->name == $type->name ? 'selected' : '' }}>{{ $type->name }}</option>
                                                @endforeach
                                            </select>
                                            <input type="number" name="extra_discounts[{{ $idx }}][amount]" class="form-control text-end fw-bold extra-disc-amount text-danger" value="{{ (float)$disc->amount }}" min="0" step="0.01" required>
                                            <button type="button" class="px-2 btn btn-danger" onclick="removeExtraDiscount(this)"><i class="bi bi-trash"></i></button>
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                            <div class="py-4 text-center text-muted small" id="emptyDiscountMsg" style="display: {{ (isset($extraDiscounts) && $extraDiscounts->count() > 0) ? 'none' : 'block' }};">
                                <i class="mb-2 bi bi-tags fs-4 d-block text-secondary"></i>Belum ada (cth: Voucher).
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- KOTAK 3: FINANCIAL SUMMARY --}}
            <div class="col-lg-4 col-md-12">
                <div class="overflow-hidden border-0 shadow-sm card rounded-4 h-100">
                    <div class="p-3 bg-white card-body text-dark">
                        <h6 class="pb-2 mb-3 fw-bold border-bottom"><i class="bi bi-calculator me-2 text-primary"></i>Ringkasan PO</h6>

                        <div class="mb-2 row">
                            <label class="col-sm-5 col-form-label small fw-bold text-danger">Diskon Global</label>
                            <div class="col-sm-7">
                                <div class="input-group input-group-sm">
                                    <select name="global_discount_type" id="global_discount_type" class="form-select border-danger-subtle fw-bold text-danger" style="max-width: 80px;">
                                        <option value="percent" {{ $po->global_discount_type == 'percent' ? 'selected' : '' }}>%</option>
                                        <option value="fixed" {{ $po->global_discount_type == 'fixed' ? 'selected' : '' }}>Rp</option>
                                    </select>
                                    <input type="number" name="global_discount_value" id="global_discount_value" class="form-control border-danger-subtle text-end text-danger fw-bold" value="{{ (float)$po->global_discount_value }}" step="0.01">
                                </div>
                            </div>
                        </div>

                        <div class="pb-3 mb-3 row border-bottom">
                            <label class="col-sm-5 col-form-label small fw-bold">Pajak Tambahan</label>
                            <div class="col-sm-7">
                                <select name="global_tax_id" id="global_tax_select" class="form-select form-select-sm border-secondary-subtle">
                                    <option value="0" data-percent="0">Tidak Ada</option>
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
                        <div class="mb-2 d-flex justify-content-between small text-info">
                            <span>Biaya Tambahan (+)</span>
                            <span class="fw-bold" id="label_charges">0.00</span>
                        </div>
                        <div class="mb-3 d-flex justify-content-between small text-danger">
                            <span>Potongan Voucher (-)</span>
                            <span class="fw-bold" id="label_extra_discounts">0.00</span>
                        </div>

                        <div class="p-2 border bg-light rounded-3 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold text-dark">GRAND TOTAL</h6>
                            <h6 class="mb-0 fw-bold text-success" id="label_grandtotal">0.00</h6>
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
        <div class="p-3 bg-white shadow-lg fixed-bottom border-top" style="z-index: 1050;">
            <div class="container gap-3 d-flex justify-content-end align-items-center">
                <button type="button" class="px-5 shadow btn btn-warning text-dark rounded-pill fw-bold" id="btnSubmitPO">
                    <i class="bi bi-save-fill me-1"></i> Simpan Perubahan PO
                </button>
            </div>
        </div>
        <div style="height: 100px;"></div>
    </form>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // --- TAMBAHAN BARU: LOGIKA UPLOAD FILE DINAMIS ---
    function addFileRow(itemId) {
        const container = document.getElementById('fileContainer_' + itemId);
        const div = document.createElement('div');
        div.className = 'mb-1 input-group input-group-sm file-row';
        div.innerHTML = `
            <input type="file" name="po_items[${itemId}][attachments][]" class="bg-white form-control form-control-sm" style="font-size: 0.7rem;" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" required>
            <button type="button" class="btn btn-outline-danger" onclick="this.closest('.file-row').remove()"><i class="bi bi-x"></i></button>
        `;
        container.appendChild(div);
    }

    // --- 1. LOGIKA BIAYA TAMBAHAN (CHARGES) ---
    let chargeIndex = {{ isset($charges) && $charges->count() > 0 ? $charges->count() : 0 }};
    const chargeContainer = document.getElementById('chargeContainer');
    const emptyChargeMsg = document.getElementById('emptyChargeMsg');

    document.getElementById('addChargeBtn').addEventListener('click', function() {
        emptyChargeMsg.style.display = 'none';
        const div = document.createElement('div');
        div.className = 'mb-2 charge-row';
        div.innerHTML = `
            <div class="border rounded shadow-sm input-group input-group-sm">
                <select name="charges[${chargeIndex}][charge_type_id]" class="bg-white form-select fw-bold" style="width: 45%;" required>
                    <option value="">-- Pilih --</option>
                    @foreach($chargeTypes as $type)
                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                    @endforeach
                </select>
                <input type="number" name="charges[${chargeIndex}][amount]" class="form-control text-end fw-bold charge-amount" placeholder="Nominal" min="0" step="0.01" required>
                <button type="button" class="px-2 btn btn-danger" onclick="removeCharge(this)"><i class="bi bi-trash"></i></button>
            </div>
        `;
        chargeContainer.appendChild(div);
        chargeIndex++;
        calculateAll();
    });

    function removeCharge(btn) {
        btn.closest('.charge-row').remove();
        if (chargeContainer.querySelectorAll('.charge-row').length === 0) emptyChargeMsg.style.display = 'block';
        calculateAll();
    }

    // --- 2. LOGIKA POTONGAN TAMBAHAN (VOUCHER/MEMBER) ---
    let extraDiscIndex = {{ (isset($extraDiscounts) && $extraDiscounts->count() > 0) ? $extraDiscounts->count() : 0 }};
    const discountContainer = document.getElementById('discountContainer');
    const emptyDiscMsg = document.getElementById('emptyDiscountMsg');

    document.getElementById('addDiscountBtn').addEventListener('click', function() {
        emptyDiscMsg.style.display = 'none';
        const div = document.createElement('div');
        div.className = 'mb-2 extra-disc-row';
        div.innerHTML = `
            <div class="border rounded shadow-sm input-group input-group-sm border-danger-subtle">
                <select name="extra_discounts[${extraDiscIndex}][discount_type_id]" class="bg-white form-select fw-bold text-danger" style="width: 45%;" required>
                    <option value="">-- Pilih --</option>
                    @foreach($discountTypes as $type)
                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                    @endforeach
                </select>
                <input type="number" name="extra_discounts[${extraDiscIndex}][amount]" class="form-control text-end fw-bold extra-disc-amount text-danger" placeholder="Nominal" min="0" step="0.01" required>
                <button type="button" class="px-2 btn btn-danger" onclick="removeExtraDiscount(this)"><i class="bi bi-trash"></i></button>
            </div>
        `;
        discountContainer.appendChild(div);
        extraDiscIndex++;
        calculateAll();
    });

    function removeExtraDiscount(btn) {
        btn.closest('.extra-disc-row').remove();
        if (discountContainer.querySelectorAll('.extra-disc-row').length === 0) emptyDiscMsg.style.display = 'block';
        calculateAll();
    }

    // --- 3. LOGIKA KALKULASI FINANSIAL ---
    function calculateAll() {
        let subtotalGross = 0; let totalLineDiscount = 0; let totalLineTax = 0;
        let totalCharges = 0; let totalExtraDiscounts = 0;

        document.querySelectorAll('.item-row').forEach(row => {
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
        const globalTaxPercent = parseFloat(globalTaxSelect.selectedOptions[0].dataset.percent) || 0;
        let globalTax = finalDpp * (globalTaxPercent / 100);
        let combinedTotalTax = totalLineTax + globalTax;

        document.querySelectorAll('.charge-amount').forEach(input => { totalCharges += parseFloat(input.value) || 0; });
        document.querySelectorAll('.extra-disc-amount').forEach(input => { totalExtraDiscounts += parseFloat(input.value) || 0; });

        let grandTotal = finalDpp + combinedTotalTax + totalCharges - totalExtraDiscounts;

        let activeCurrency = 'IDR';
        const currDropdowns = document.querySelectorAll('select[name$="[currency]"]');
        if(currDropdowns.length > 0) activeCurrency = currDropdowns[0].value;

        document.getElementById('label_subtotal').innerText = subtotalGross.toLocaleString('id-ID', { minimumFractionDigits: 2 });
        document.getElementById('label_discount').innerText = '-' + totalCommercialDiscount.toLocaleString('id-ID', { minimumFractionDigits: 2 });
        document.getElementById('label_dpp').innerText = finalDpp.toLocaleString('id-ID', { minimumFractionDigits: 2 });
        document.getElementById('label_tax').innerText = combinedTotalTax.toLocaleString('id-ID', { minimumFractionDigits: 2 });
        document.getElementById('label_charges').innerText = '+' + totalCharges.toLocaleString('id-ID', { minimumFractionDigits: 2 });
        document.getElementById('label_extra_discounts').innerText = '-' + totalExtraDiscounts.toLocaleString('id-ID', { minimumFractionDigits: 2 });

        document.getElementById('label_grandtotal').innerText = activeCurrency + ' ' + grandTotal.toLocaleString('id-ID', { minimumFractionDigits: 2 });

        document.getElementById('input_subtotal').value = subtotalGross;
        document.getElementById('input_discount').value = totalCommercialDiscount;
        document.getElementById('input_tax').value = combinedTotalTax;
        document.getElementById('input_charge_total').value = totalCharges;
        document.getElementById('input_grandtotal').value = grandTotal;
    }

    document.addEventListener('input', calculateAll);
    document.addEventListener('change', calculateAll);
    window.onload = calculateAll;

    document.getElementById('btnSubmitPO').addEventListener('click', function() {
        const form = document.getElementById('poProcessForm');
        if (!form.checkValidity()) { form.reportValidity(); return; }
        Swal.fire({
            title: 'Simpan Revisi PO?',
            text: "Data yang lama akan ditimpa dengan perhitungan baru ini, dan lampiran baru akan ditambahkan.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ffc107',
            confirmButtonText: 'Ya, Simpan!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) form.submit();
        });
    });


   /// --- FUNGSI HAPUS FILE TERSIMPAN (ANTI-404) ---
    function deleteAttachment(attachmentId) {
        Swal.fire({
            title: 'Hapus Lampiran Ini?',
            text: "File akan dihapus secara permanen dari server dan tidak dapat dikembalikan!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Hapus File!',
            cancelButtonText: 'Batal',
            borderRadius: '15px'
        }).then((result) => {
            if (result.isConfirmed) {
                let form = document.createElement('form');
                form.method = 'POST';

                // PERBAIKAN FINAL: Gunakan route() Laravel lalu ganti string ':id' dengan ID asli
                // Ini akan otomatis membaca rute 'po/po/delete-attachment/{id}'
                let url = "{{ route('po.attachment.destroy', ':id') }}";
                form.action = url.replace(':id', attachmentId);

                let csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = '_token';
                csrfInput.value = '{{ csrf_token() }}';
                form.appendChild(csrfInput);

                let methodInput = document.createElement('input');
                methodInput.type = 'hidden';
                methodInput.name = '_method';
                methodInput.value = 'DELETE';
                form.appendChild(methodInput);

                document.body.appendChild(form);
                form.submit();
            }
        });
    }

</script>
@endpush
