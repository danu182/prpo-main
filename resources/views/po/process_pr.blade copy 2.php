@extends('layouts.app')

@section('content')
<div class="container pb-5 text-dark">

    {{-- HEADER HALAMAN --}}
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-cart-plus-fill me-2 text-primary"></i>Terbitkan Purchase Order</h4>
            <div class="mt-1 text-muted small">
                Konversi PR <strong class="text-primary">{{ $pr->pr_number }}</strong> menjadi PO resmi.
            </div>
        </div>

        <div class="gap-2 d-flex align-items-center">
            {{-- TAMBAHAN: Informasi Need Date --}}
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

            <a href="{{ route('po.index') }}" class="bg-white shadow-sm btn btn-outline-secondary rounded-pill fw-bold">
                <i class="bi bi-arrow-left me-1"></i> Batal
            </a>
        </div>
    </div>

    <form action="{{ route('po.store_from_pr', $pr->id) }}" method="POST" id="poProcessForm" enctype="multipart/form-data">
        @csrf

        {{-- ========================================================== --}}
        {{-- INFO RIWAYAT PO SEBELUMNYA (Muncul jika ini Partial PO)    --}}
        {{-- ========================================================== --}}
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
                                <td class="py-2 text-end text-success fw-bold">{{ $epo->currency }} {{ number_format($epo->grand_total, 2) }}</td>
                                <td class="py-2 text-center">
                                    <span class="px-2 badge bg-secondary rounded-pill" style="font-size: 0.65rem;">
                                        {{ optional($epo->status)->name ?? 'DRAFT' }}
                                    </span>
                                </td>
                                <td class="py-2 text-center pe-3">
                                    <a href="{{ route('po.show', $epo->id) }}" target="_blank" class="px-3 py-0 btn btn-sm btn-outline-info rounded-pill fw-bold" style="font-size: 0.7rem;">
                                        Lihat PO <i class="bi bi-box-arrow-up-right ms-1"></i>
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

        {{-- 1. ENTITAS PENANGGUNG & PENGIRIMAN --}}
        <div class="mb-4 border-0 border-4 shadow-sm card rounded-4 border-start border-primary">
            <div class="p-4 card-body">
                <h6 class="mb-3 fw-bold text-dark"><i class="bi bi-info-circle-fill me-2 text-primary"></i>Informasi Pengiriman & Pembayaran</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="mb-1 small fw-bold text-muted">Ditanggung Oleh (Billing Entity):</label>
                        {{-- DITAMBAHKAN CLASS select2-init --}}
                        <select name="billing_company_id" id="billToSelect" class="shadow-sm form-select select2-init" required onchange="updateShippingAddress()">
                            <option value="">-- Pilih PT --</option>
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}" data-address="{{ $company->address ?? '' }}" {{ $pr->company_id == $company->id ? 'selected' : '' }}>{{ $company->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="mb-1 small fw-bold text-muted">Lokasi Pengiriman (Shipping Address):</label>
                        <textarea name="shipping_address" id="shippingAddressInput" class="shadow-sm form-control" rows="1">{{ $pr->company->address ?? '' }}</textarea>
                    </div>

                    {{-- BARIS KEDUA --}}
                    <div class="col-md-4">
                        <label class="mb-1 small fw-bold text-muted">Termin Pembayaran:</label>
                        {{-- DITAMBAHKAN CLASS select2-init --}}
                        <select name="payment_term_id" class="shadow-sm form-select select2-init" required>
                            <option value="">-- Pilih Termin --</option>
                            @foreach($paymentTerms as $pt)
                                <option value="{{ $pt->id }}">{{ $pt->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- TAMBAHAN BARU: Target Pengiriman (Delivery Date) --}}
                    <div class="col-md-3">
                        <label class="mb-1 small fw-bold text-muted">Target Pengiriman:</label>

                        @php
                            // 1. Dapatkan tanggal hari ini (Y-m-d)
                            $today = date('Y-m-d');

                            // 2. Format need_date menjadi Y-m-d dengan Carbon
                            $parsedNeedDate = $pr->need_date ? \Carbon\Carbon::parse($pr->need_date)->format('Y-m-d') : $today;

                            // 3. Jika need_date ternyata sudah kelewat dari hari ini,
                            // paksa default value-nya menjadi hari ini agar form tidak error.
                            $defaultDeliveryDate = ($parsedNeedDate < $today) ? $today : $parsedNeedDate;
                        @endphp

                        <input type="date"
                               name="delivery_date"
                               class="shadow-sm form-control"
                               value="{{ $defaultDeliveryDate }}"
                               min="{{ $today }}"
                               required>
                    </div>

                    {{-- Kolom Notes disesuaikan ukurannya dari col-md-8 menjadi col-md-5 --}}
                    <div class="col-md-5">
                        <label class="mb-1 small fw-bold text-muted">Catatan PO (Notes):</label>
                        <input type="text" name="notes" class="shadow-sm form-control" placeholder="Catatan yang akan tercetak di PO...">
                    </div>
                </div>
            </div>
        </div>

        {{-- 2. TABEL ITEM BARANG --}}
        <div class="mb-4 border-0 shadow-sm card rounded-4">
            <div class="py-3 bg-white card-header border-bottom">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-list-check me-2 text-primary"></i>Penentuan Vendor, Harga & Pajak Item</h6>
            </div>
            <div class="p-0 card-body">
                <div class="table-responsive">
                    <table class="table mb-0 align-middle table-hover" id="poTable">
                        <thead class="bg-light text-muted small text-uppercase">
                            <tr>
                                <th class="py-3 ps-4" width="22%">Item & Info Vendor PR</th>
                                <th class="py-3 text-center" width="8%">Qty</th>
                                <th class="py-3" width="22%">Vendor & Lampiran</th>
                                <th class="py-3" width="16%">Harga Satuan</th>
                                <th class="py-3" width="12%">Diskon</th>
                                <th class="py-3" width="10%">Pajak Item</th>
                                <th class="py-3 pe-4 text-end" width="10%">Netto</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pr->items as $item)
                            @php
                                $orderedQty = $item->ordered_qty ?? 0;
                                $sisaQty = $item->qty - $orderedQty;
                            @endphp

                            @if($sisaQty > 0)
                            <tr class="align-top item-row">
                                <td class="py-3 ps-4">
                                    <h6 class="mb-0 fw-bold text-dark fs-6">{{ $item->item->name }}</h6>
                                    <div class="mb-2 small text-muted">{{ $item->item->code }}</div>
                                    <input type="hidden" name="po_items[{{ $item->id }}][pr_item_id]" value="{{ $item->id }}">
                                    <input type="hidden" name="po_items[{{ $item->id }}][item_id]" value="{{ $item->item_id }}">

                                    @if($orderedQty > 0)
                                        <span class="mb-2 border badge bg-warning text-dark" style="font-size: 0.65rem;">
                                            <i class="bi bi-info-circle"></i> Sisa Order (Minta: {{ (float)$item->qty }}, Sudah PO: {{ (float)$orderedQty }})
                                        </span>
                                    @endif

                                    <div class="overflow-hidden border shadow-sm accordion accordion-flush rounded-3" id="accItem{{ $item->id }}">
                                        <div class="accordion-item">
                                            <h2 class="accordion-header">
                                                <button class="px-3 py-1 accordion-button collapsed small bg-light fw-bold text-primary" style="font-size: 0.75rem;" type="button" data-bs-toggle="collapse" data-bs-target="#ref{{ $item->id }}">
                                                    <i class="bi bi-search me-2"></i> Opsi Vendor PR
                                                </button>
                                            </h2>
                                            <div id="ref{{ $item->id }}" class="accordion-collapse collapse" data-bs-parent="#accItem{{ $item->id }}">
                                                <div class="p-2 bg-white accordion-body">

                                                    @forelse($item->vendorQuotes as $quote)
                                                        <div class="p-2 mb-1 border-bottom">
                                                            <div class="mb-1 d-flex justify-content-between align-items-center">
                                                                <span class="fw-bold small text-dark">{{ $quote->vendor->name }}</span>
                                                                <span class="border badge bg-primary-subtle text-primary border-primary-subtle">{{ $quote->currency }} {{ number_format($quote->quoted_price, 0) }}</span>
                                                            </div>

                                                            @if($quote->reference_link)
                                                                <a href="{{ $quote->reference_link }}" target="_blank" class="mb-1 d-block small text-truncate text-decoration-none text-info" style="font-size: 0.7rem;"><i class="bi bi-link-45deg"></i> Link Referensi</a>
                                                            @endif

                                                            @if($quote->notes)
                                                                <div class="p-2 mt-1 rounded small text-muted bg-light border-start border-3 border-secondary" style="font-size: 0.7rem;">
                                                                    <i class="bi bi-chat-right-text me-1"></i> {{ $quote->notes }}
                                                                </div>
                                                            @endif

                                                            @if($quote->attachment)
                                                                <div class="mt-1">
                                                                    <a href="{{ asset('storage/' . $quote->attachment) }}" target="_blank" class="px-2 py-0 text-decoration-none btn btn-xs btn-outline-primary fw-bold" style="font-size: 0.65rem;">
                                                                        <i class="bi bi-file-earmark-pdf"></i> Lihat File Vendor PR
                                                                    </a>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @empty
                                                    @endforelse

                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <input type="number" name="po_items[{{ $item->id }}][qty]" class="text-center form-control form-control-sm fw-bold qty-input" value="{{ (float)$sisaQty }}" max="{{ (float)$sisaQty }}" step="0.01">
                                </td>

                                {{-- UI UPLOAD FILE DINAMIS --}}
                                <td>
                                    {{-- DITAMBAHKAN CLASS select2-vendor --}}
                                    <select name="po_items[{{ $item->id }}][vendor_id]" class="mb-2 form-select form-select-sm vendor-select border-primary fw-bold select2-vendor" onchange="updateRowData(this, {{ $item->id }})" required>
                                        <option value="">-- Pilih Vendor --</option>
                                        @foreach($vendors as $vendor)
                                            @php
                                                $quote = $item->vendorQuotes->where('vendor_id', $vendor->id)->first();
                                                $isDefault = ($quote && $quote->is_selected == 1) || $item->suggested_vendor_id == $vendor->id;
                                            @endphp
                                            <option value="{{ $vendor->id }}" {{ $isDefault ? 'selected' : '' }} data-price="{{ $quote ? (float)$quote->quoted_price : 0 }}" data-currency="{{ $quote ? $quote->currency : 'IDR' }}">
                                                {{ $vendor->name }}
                                            </option>
                                        @endforeach
                                    </select>

                                    <div class="p-2 border rounded bg-light border-secondary-subtle">
                                        <div class="mb-2 d-flex justify-content-between align-items-center">
                                            <label class="mb-0 text-muted fw-bold" style="font-size: 0.65rem;">
                                                <i class="bi bi-paperclip"></i> Lampiran:
                                            </label>
                                            <button type="button" class="px-2 py-0 btn btn-sm btn-outline-secondary" style="font-size: 0.65rem;" onclick="addFileRow({{ $item->id }})">
                                                <i class="bi bi-plus"></i> Tambah
                                            </button>
                                        </div>
                                        {{-- Wadah Container File --}}
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
                                                <option value="{{ $curr->code }}" {{ $curr->code == 'IDR' ? 'selected' : '' }}>{{ $curr->code }}</option>
                                            @endforeach
                                        </select>
                                        <input type="number" name="po_items[{{ $item->id }}][unit_price]" class="form-control text-end fw-bold price-input" id="price_{{ $item->id }}" value="0" step="0.01">
                                    </div>
                                </td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <input type="number" name="po_items[{{ $item->id }}][discount_value]" class="form-control text-end line-disc-val" value="0" step="0.01">
                                        <select name="po_items[{{ $item->id }}][discount_type]" class="form-select bg-light line-disc-type" style="max-width: 55px; padding-left: 5px;">
                                            <option value="percent">%</option>
                                            <option value="fixed">Rp</option>
                                        </select>
                                    </div>
                                </td>
                                <td>
                                    <select name="po_items[{{ $item->id }}][tax_id]" class="text-center form-select form-select-sm line-tax-select border-secondary-subtle">
                                        <option value="0" data-percent="0">0%</option>
                                        @foreach($taxes as $tax)
                                            <option value="{{ $tax->id }}" data-percent="{{ $tax->percent }}" {{ $tax->percent == 11 ? 'selected' : '' }}>
                                                {{ $tax->name }} ({{ (float)$tax->percent }}%)
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="pe-4 text-end fw-bold row-total text-primary">0.00</td>
                            </tr>
                            @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- 3. SUSUNAN GRID BARU: 3 KOLOM AGAR RAPI (col-lg-4 x 3 = 12) --}}
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
                            <div class="py-4 text-center text-muted small" id="emptyChargeMsg">
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
                            <div class="py-4 text-center text-muted small" id="emptyDiscountMsg">
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
                                        <option value="percent">%</option>
                                        <option value="fixed">Rp</option>
                                    </select>
                                    <input type="number" name="global_discount_value" id="global_discount_value" class="form-control border-danger-subtle text-end text-danger fw-bold" value="0" step="0.01">
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
                <button type="button" class="px-5 shadow btn btn-primary rounded-pill fw-bold" id="btnSubmitPO">
                    <i class="bi bi-rocket-takeoff-fill me-1"></i> Terbitkan PO
                </button>
            </div>
        </div>
        <div style="height: 100px;"></div>
    </form>
</div>
@endsection

@push('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<style>
    .accordion-button:not(.collapsed) { background-color: #f8f9fa; color: #0d6efd; box-shadow: none; }
    .fixed-bottom { background: rgba(255, 255, 255, 0.95) !important; backdrop-filter: blur(10px); }

    /* Penyesuaian Select2 dengan Bootstrap 5 */
    .select2-container--bootstrap-5 .select2-selection {
        border-radius: 8px;
        border-color: #dee2e6;
        min-height: 38px;
        font-size: 0.875rem;
    }
    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
        color: #495057;
        padding-top: 2px;
        font-weight: 600;
    }
</style>
@endpush

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // --- INISIALISASI SELECT2 ---
    $(document).ready(function() {
        $('.select2-init').select2({
            theme: 'bootstrap-5',
            width: '100%'
        });
        $('.select2-vendor').select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: '-- Cari/Pilih Vendor --'
        });

        // Trigger fungsi JS Anda ketika Select2 berubah agar perhitungan dan alamat ikut update
        $('.select2-vendor').on('select2:select', function (e) {
            $(this).trigger('change');
        });
        $('.select2-init').on('select2:select', function (e) {
            $(this).trigger('change');
        });

        // Paksa hitung saat pertama load
        calculateAll();
        // Load harga default vendor
        document.querySelectorAll('.vendor-select').forEach(select => {
            if(select.value !== "") {
                const itemId = select.name.match(/\[(\d+)\]/)[1];
                updateRowData(select, itemId);
            }
        });
    });

    // --- LOGIKA UPLOAD FILE DINAMIS ---
    function addFileRow(itemId) {
        const container = document.getElementById('fileContainer_' + itemId);
        const div = document.createElement('div');
        div.className = 'input-group input-group-sm mb-1 file-row';
        div.innerHTML = `
            <input type="file" name="po_items[${itemId}][attachments][]" class="bg-white form-control form-control-sm" style="font-size: 0.7rem;" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" required>
            <button type="button" class="btn btn-outline-danger" onclick="this.closest('.file-row').remove()"><i class="bi bi-x"></i></button>
        `;
        container.appendChild(div);
    }

    // --- FUNGSI UPDATE ALAMAT PENGIRIMAN ---
    function updateShippingAddress() {
        var select = document.getElementById('billToSelect');
        var textarea = document.getElementById('shippingAddressInput');
        var selectedOption = select.options[select.selectedIndex];
        if(selectedOption && selectedOption.getAttribute('data-address')) {
            textarea.value = selectedOption.getAttribute('data-address');
            textarea.classList.add('bg-warning', 'bg-opacity-25');
            setTimeout(() => textarea.classList.remove('bg-warning', 'bg-opacity-25'), 500);
        }
    }

    // --- 1. LOGIKA BIAYA TAMBAHAN (CHARGES) ---
    let chargeIndex = 0;
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
                <input type="number" name="charges[${chargeIndex}][amount]" class="form-control text-end fw-bold charge-amount" placeholder="Nominal" min="0" step="0.01" required oninput="calculateAll()">
                <button type="button" class="px-2 btn btn-danger" onclick="removeCharge(this)"><i class="bi bi-trash"></i></button>
            </div>
        `;
        chargeContainer.appendChild(div);
        chargeIndex++;
    });

    function removeCharge(btn) {
        btn.closest('.charge-row').remove();
        if (chargeContainer.querySelectorAll('.charge-row').length === 0) emptyChargeMsg.style.display = 'block';
        calculateAll();
    }

    // --- 2. LOGIKA POTONGAN TAMBAHAN (VOUCHER/MEMBER) ---
    let extraDiscIndex = 0;
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
                    @if(isset($discountTypes))
                        @foreach($discountTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    @endif
                </select>
                <input type="number" name="extra_discounts[${extraDiscIndex}][amount]" class="form-control text-end fw-bold extra-disc-amount text-danger" placeholder="Nominal" min="0" step="0.01" required oninput="calculateAll()">
                <button type="button" class="px-2 btn btn-danger" onclick="removeExtraDiscount(this)"><i class="bi bi-trash"></i></button>
            </div>
        `;
        discountContainer.appendChild(div);
        extraDiscIndex++;
    });

    function removeExtraDiscount(btn) {
        btn.closest('.extra-disc-row').remove();
        if (discountContainer.querySelectorAll('.extra-disc-row').length === 0) emptyDiscMsg.style.display = 'block';
        calculateAll();
    }

    // --- 3. LOGIKA KALKULASI FINANSIAL UTAMA ---
    window.calculateAll = function() {
        let subtotalGross = 0;
        let totalLineDiscount = 0;
        let totalLineTax = 0;
        let totalCharges = 0;
        let totalExtraDiscounts = 0;

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

            subtotalGross += lineTotalGross;
            totalLineDiscount += lineDiscount;
            totalLineTax += lineTax;

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

        document.querySelectorAll('.charge-amount').forEach(input => {
            totalCharges += parseFloat(input.value) || 0;
        });

        document.querySelectorAll('.extra-disc-amount').forEach(input => {
            totalExtraDiscounts += parseFloat(input.value) || 0;
        });

        let grandTotal = finalDpp + combinedTotalTax + totalCharges - totalExtraDiscounts;

        let activeCurrency = 'IDR';
        const currencyDropdowns = document.querySelectorAll('select[name$="[currency]"]');
        if (currencyDropdowns.length > 0) activeCurrency = currencyDropdowns[0].value;

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

    // --- 4. AUTO FILL HARGA VENDOR ---
    window.updateRowData = function(select, itemId) {
        const option = select.options[select.selectedIndex];
        document.getElementById('price_' + itemId).value = option.dataset.price || 0;

        const currSelect = document.getElementById('curr_' + itemId);
        if (currSelect) currSelect.value = option.dataset.currency || 'IDR';

        calculateAll();
    }

    document.addEventListener('input', calculateAll);
    document.addEventListener('change', calculateAll);

    document.getElementById('btnSubmitPO').addEventListener('click', function() {
        const form = document.getElementById('poProcessForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        Swal.fire({
            title: 'Konfirmasi Terbitkan PO',
            text: "Data keuangan akan disimpan dan PO resmi akan dibuat.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#0d6efd',
            confirmButtonText: 'Ya, Proses!',
            cancelButtonText: 'Batal',
            borderRadius: '15px'
        }).then((result) => {
            if (result.isConfirmed) form.submit();
        });
    });
</script>
@endpush
