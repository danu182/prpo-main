@extends('layouts.app')

@push('css')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <style>
        body { background-color: #f4f6f9; }

        /* Modern Select2 */
        .select2-container--bootstrap-5 .select2-selection { border-radius: 0.5rem; border: 1px solid #ced4da; min-height: 38px; }
        .select2-container--bootstrap-5.select2-container--focus .select2-selection { border-color: #f6c23e; box-shadow: 0 0 0 0.25rem rgba(246, 194, 62, 0.25); }

        /* Cards & Containers */
        .modern-card { background: #ffffff; border: none; border-radius: 1rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); margin-bottom: 1.5rem; }
        .modern-card-header { background: #ffffff; border-bottom: 1px solid rgba(0,0,0,0.05); padding: 1.25rem 1.5rem; border-radius: 1rem 1rem 0 0 !important; }
        .section-title { font-weight: 700; color: #2c3e50; font-size: 1.1rem; display: flex; align-items: center; margin-bottom: 0; }
        .section-title i { margin-right: 10px; color: #ffc107; background: rgba(255, 193, 7, 0.1); padding: 8px; border-radius: 8px; font-size: 1.1rem; }

        /* Form Typography */
        .form-label-custom { font-size: 0.75rem; font-weight: 700; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.5rem; }
        .form-control, .form-select { border-radius: 0.5rem; }
        .text-xs { font-size: 0.75rem; }

        /* Table Layout */
        .table-items th { background: #f8f9fa; color: #495057; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e9ecef; }
        .table-items td { vertical-align: top; padding: 1.25rem 0.75rem; border-bottom: 1px solid #e9ecef; }
        .item-box { background: #fdfdfe; border: 1px solid #f1f3f5; border-radius: 0.75rem; padding: 1rem; }

        /* Sticky Receipt */
        .receipt-panel { background: #ffffff; border-radius: 1rem; box-shadow: 0 10px 30px rgba(0,0,0,0.08); position: sticky; top: 20px; border-top: 5px solid #ffc107; }
        .receipt-row { display: flex; justify-content: space-between; margin-bottom: 0.75rem; font-size: 0.9rem; }
        .total-box { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 0.75rem; padding: 1.25rem; text-align: center; margin-top: 1rem; }
    </style>
@endpush

@section('content')

@php
    $taxes = $taxes ?? \App\Models\Tax::where('is_active', 1)->orderBy('percent')->get();
@endphp

<div class="py-4 container-fluid">

    {{-- HEADER KEKINIAN --}}
    <div class="mb-4 d-flex align-items-center">
        <a href="{{ route('bills.show', $bill->bill_number) }}" class="btn btn-white border shadow-sm rounded-circle me-3 d-flex align-items-center justify-content-center hover-light" style="width: 48px; height: 48px;">
            <i class="bi bi-arrow-left fs-5 text-warning"></i>
        </a>
        <div>
            <h3 class="mb-0 fw-bold text-dark" style="letter-spacing: -0.5px;">Edit Tagihan Opex</h3>
            <div class="text-muted small">No Ref: <span class="fw-bold text-primary">{{ $bill->bill_number }}</span></div>
        </div>
    </div>

    @if ($errors->any())
        <div class="mb-4 border-0 shadow-sm alert alert-danger rounded-4">
            <div class="mb-1 fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i>Terdapat Kesalahan:</div>
            <ul class="mb-0 small">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('bills.update', $bill->bill_number) }}" method="POST" enctype="multipart/form-data" id="billForm">
        @csrf
        @method('PUT')
        <div class="row g-4">
            {{-- KOLOM KIRI: FORM UTAMA --}}
            <div class="col-xl-8 col-lg-7">

                {{-- CARD 1: INFORMASI DASAR --}}
                <div class="modern-card">
                    <div class="modern-card-header">
                        <h5 class="section-title"><i class="bi bi-building"></i> 1. Informasi Dasar</h5>
                    </div>
                    <div class="p-4 card-body">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label-custom">PT Penanggung Jawab <span class="text-danger">*</span></label>
                                <select name="paid_by_company_id" class="form-select select2-single" required>
                                    <option value="">-- Ketik untuk mencari PT --</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}" {{ $bill->company_id == $company->id ? 'selected' : '' }}>
                                            {{ $company->code ? '['.$company->code.'] ' : '' }}{{ $company->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-custom">Vendor / Supplier <span class="text-danger">*</span></label>
                                <select name="vendor_name" class="form-select select2-vendor" required>
                                    <option value="{{ $bill->vendor_name }}" selected>{{ $bill->vendor_name }}</option>
                                    @foreach($vendors as $vendor)
                                        @if($vendor->name !== $bill->vendor_name)
                                            <option value="{{ $vendor->name }}">{{ $vendor->name }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                <div class="mt-1 text-muted" style="font-size: 0.7rem;"><i class="bi bi-lightbulb me-1"></i>Ketik lalu tekan Enter jika vendor belum ada.</div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-custom">Tgl Tagihan <span class="text-danger">*</span></label>
                                <input type="date" name="bill_date" class="form-control fw-bold text-dark" value="{{ \Carbon\Carbon::parse($bill->invoice_date)->format('Y-m-d') }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-custom">Jatuh Tempo <span class="text-danger">*</span></label>
                                <input type="date" name="due_date" class="form-control fw-bold text-dark" value="{{ \Carbon\Carbon::parse($bill->due_date)->format('Y-m-d') }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-custom">Periode Pemakaian</label>
                                <select name="period_offset" class="form-select">
                                    <option value="-1" {{ $bill->period_offset == -1 ? 'selected' : '' }}>Mundur 1 Bulan (Post-paid)</option>
                                    <option value="0" {{ $bill->period_offset == 0 ? 'selected' : '' }}>Sesuai Bulan Tagihan</option>
                                    <option value="1" {{ $bill->period_offset == 1 ? 'selected' : '' }}>Maju 1 Bulan (Pre-paid)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- CARD 2: DETAIL PEMBAYARAN --}}
                <div class="modern-card">
                    <div class="modern-card-header">
                        <h5 class="section-title"><i class="bi bi-wallet2"></i> 2. Detail Pembayaran & Workflow</h5>
                    </div>
                    <div class="p-4 card-body">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label-custom">No. Invoice Vendor <span class="text-muted text-lowercase">(Opsional)</span></label>
                                <input type="text" name="vendor_invoice_number" class="form-control fw-bold text-primary" value="{{ old('vendor_invoice_number', $bill->vendor_invoice_number) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-custom">No. Rekening Pembayaran</label>
                                <input type="text" name="account_number" class="form-control fw-bold text-success" value="{{ old('account_number', $bill->account_number) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-custom">Mata Uang <span class="text-danger">*</span></label>
                                <select name="currency_id" id="currency_select" class="form-select select2-single" required>
                                    @foreach($currencies as $curr)
                                        <option value="{{ $curr->id }}" data-symbol="{{ $curr->symbol }}" {{ $bill->currency == $curr->code ? 'selected' : '' }}>
                                            {{ $curr->code }} - {{ $curr->name }} ({{ $curr->symbol }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-custom text-primary"><i class="bi bi-shuffle me-1"></i> Rute Persetujuan Khusus</label>
                                <select name="custom_workflow_id" class="form-select select2-single" style="background-color: #f8f9fc;">
                                    <option value="">-- Ikuti Standar Departemen (Default) --</option>
                                    @if(isset($customWorkflows))
                                        @foreach($customWorkflows as $cw)
                                            <option value="{{ $cw->id }}" {{ (isset($selectedWorkflowId) && $selectedWorkflowId == $cw->id) ? 'selected' : '' }}>
                                                {{ $cw->name }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label-custom">Catatan / Keterangan Tagihan</label>
                                <textarea name="note" class="form-control" rows="2" placeholder="Tuliskan keterangan internal jika ada...">{{ old('note', $bill->description) }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- CARD 3: SIKLUS BERULANG --}}
                <div class="modern-card border-start border-4 border-info">
                    <div class="p-4 card-body">
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <h6 class="mb-1 fw-bold text-dark"><i class="bi bi-arrow-repeat me-2 text-info"></i>Tagihan Rutin</h6>
                                <p class="mb-0 text-muted" style="font-size: 0.75rem;">Jadwalkan tagihan otomatis</p>
                            </div>
                            <div class="col-md-3">
                                <select name="is_recurring" id="is_recurring" class="form-select form-select-sm fw-bold">
                                    <option value="0" {{ !$bill->is_recurring ? 'selected' : '' }}>Sekali Saja (One-time)</option>
                                    <option value="1" {{ $bill->is_recurring ? 'selected' : '' }}>Berulang (Recurring)</option>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <div id="recurring_setup" class="{{ !$bill->is_recurring ? 'd-none' : '' }}">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light text-muted fw-bold">Ulangi Tiap:</span>
                                        <input type="number" name="recurring_interval" class="text-center form-control fw-bold" value="{{ $bill->recurring_interval ?? 1 }}" min="1">
                                        <select name="recurring_period" class="form-select fw-bold">
                                            <option value="days" {{ $bill->recurring_period == 'days' ? 'selected' : '' }}>Hari</option>
                                            <option value="weeks" {{ $bill->recurring_period == 'weeks' ? 'selected' : '' }}>Minggu</option>
                                            <option value="months" {{ ($bill->recurring_period == 'months' || !$bill->recurring_period) ? 'selected' : '' }}>Bulan</option>
                                            <option value="years" {{ $bill->recurring_period == 'years' ? 'selected' : '' }}>Tahun</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- CARD 4: RINCIAN ITEM --}}
                <div class="modern-card">
                    <div class="modern-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="mb-0 section-title"><i class="bi bi-list-check"></i> 3. Rincian Barang & Jasa</h5>

                        {{-- Tool Pajak Global --}}
                        <div class="input-group input-group-sm w-auto shadow-sm">
                            <span class="input-group-text bg-light fw-bold text-muted border-end-0">Pajak Massal:</span>
                            <select id="global_tax_type" class="form-select border-start-0" style="max-width: 60px;">
                                <option value="percent" selected>%</option><option value="fixed">Rp</option>
                            </select>
                            <select id="global_tax_master" class="form-select fw-bold text-dark">
                                <option value="" data-rate="0">- Tanpa Pajak -</option>
                                <option value="MANUAL_PERCENT" data-rate="0">Manual (%)</option>
                                @foreach($taxes as $tax)
                                    <option value="{{ $tax->id }}" data-rate="{{ (float)$tax->percent }}">{{ $tax->name }} ({{ (float)$tax->percent }}%)</option>
                                @endforeach
                            </select>
                            <input type="text" id="global_tax_val" class="form-control price-display text-end d-none" value="0" style="max-width: 80px;">
                            <button class="btn btn-primary" type="button" id="btnApplyGlobalTax" title="Terapkan ke semua item"><i class="bi bi-check2-all"></i></button>
                        </div>
                    </div>

                    <div class="p-4 card-body table-responsive">
                        <table class="table align-middle table-items" id="itemTable">
                            <thead>
                                <tr>
                                    <th width="40%">Item & Spesifikasi</th>
                                    <th width="25%">Kuantitas & Harga</th>
                                    <th width="30%">Pajak & Diskon Item</th>
                                    <th width="5%" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="itemContainer">
                                @foreach($bill->items as $index => $item)
                                <tr class="item-row">
                                    {{-- Kolom 1: Data Item --}}
                                    <td>
                                        <div class="item-box">
                                            <label class="form-label text-xs fw-bold text-muted">Master Item <span class="text-danger">*</span></label>
                                            <select name="items[{{ $index }}][item_id]" class="mb-3 form-select select2-item item-select" required onchange="onOpexItemSelect(this, {{ $index }})">
                                                <option value="">-- Pilih Item Opex --</option>
                                                @foreach($opexItems as $opx)
                                                    <option value="{{ $opx->id }}" {{ $item->item_id == $opx->id ? 'selected' : '' }}>{{ $opx->code }} - {{ $opx->name }}</option>
                                                @endforeach
                                            </select>

                                            <label class="form-label text-xs fw-bold text-muted">Nama di Tagihan <span class="text-danger">*</span></label>
                                            <input type="text" name="items[{{ $index }}][name_override]" id="name_override_{{ $index }}" class="mb-3 form-control form-control-sm fw-bold text-primary" value="{{ $item->name }}" required>

                                            <label class="form-label text-xs fw-bold text-muted">Spesifikasi Detail</label>
                                            <textarea name="items[{{ $index }}][description]" class="form-control form-control-sm" rows="2" placeholder="Bisa tekan enter untuk baris baru...">{{ $item->description }}</textarea>
                                        </div>
                                    </td>

                                    {{-- Kolom 2: Harga & Qty --}}
                                    <td>
                                        <div class="item-box">
                                            <label class="form-label text-xs fw-bold text-muted">Jumlah (Qty) <span class="text-danger">*</span></label>
                                            <input type="number" name="items[{{ $index }}][qty]" class="mb-3 text-center form-control form-control-sm qty fw-bold" value="{{ $item->qty }}" min="1">

                                            <label class="form-label text-xs fw-bold text-muted">Harga Satuan <span class="text-danger">*</span></label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text curr-symbol bg-light text-muted">Rp</span>
                                                <input type="text" class="form-control price-display text-end fw-bold" value="{{ $item->price }}">
                                                <input type="hidden" name="items[{{ $index }}][price]" class="price-real" value="{{ $item->price }}">
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Kolom 3: Pajak & Diskon --}}
                                    <td>
                                        <div class="item-box border-info-subtle">
                                            <label class="form-label text-xs fw-bold text-info"><i class="bi bi-plus-circle me-1"></i>Pajak Item</label>
                                            <div class="mb-3 input-group input-group-sm">
                                                <select name="items[{{ $index }}][tax_type]" class="form-select tax-type" style="max-width: 55px; padding-right:5px; padding-left:5px;">
                                                    <option value="percent" {{ $item->tax_type == 'percent' ? 'selected' : '' }}>%</option>
                                                    <option value="fixed" {{ $item->tax_type == 'fixed' ? 'selected' : '' }}>Rp</option>
                                                </select>

                                                @php
                                                    $isManualPct = ($item->tax_type == 'percent' && (empty($item->tax_id) || $item->tax_id === 'MANUAL_PERCENT') && $item->tax_value > 0);
                                                    $selectedMaster = $isManualPct ? 'MANUAL_PERCENT' : $item->tax_id;
                                                @endphp

                                                <select name="items[{{ $index }}][tax_id]" class="form-select tax-master-select fw-bold text-info {{ $item->tax_type == 'fixed' ? 'd-none' : '' }}">
                                                    <option value="" data-rate="0">- Tanpa Pajak -</option>
                                                    <option value="MANUAL_PERCENT" data-rate="0" {{ $selectedMaster === 'MANUAL_PERCENT' ? 'selected' : '' }}>Manual (%)</option>
                                                    @foreach($taxes as $tax)
                                                        <option value="{{ $tax->id }}" data-rate="{{ (float)$tax->percent }}" {{ $selectedMaster == $tax->id ? 'selected' : '' }}>{{ $tax->name }}</option>
                                                    @endforeach
                                                </select>

                                                <input type="text" class="form-control text-end tax-val-display {{ ($item->tax_type == 'percent' && $selectedMaster !== 'MANUAL_PERCENT') ? 'd-none' : '' }}" value="{{ (float)$item->tax_value }}">
                                                <input type="hidden" name="items[{{ $index }}][tax_value]" class="tax-val-real" value="{{ (float)$item->tax_value }}">
                                            </div>

                                            <label class="form-label text-xs fw-bold text-danger"><i class="bi bi-dash-circle me-1"></i>Diskon Item</label>
                                            <div class="input-group input-group-sm">
                                                <select name="items[{{ $index }}][discount_type]" class="form-select disc-type" style="max-width: 55px; padding-right:5px; padding-left:5px;">
                                                    <option value="fixed" {{ $item->discount_type == 'fixed' ? 'selected' : '' }}>Rp</option>
                                                    <option value="percent" {{ $item->discount_type == 'percent' ? 'selected' : '' }}>%</option>
                                                </select>
                                                <input type="text" class="form-control text-end disc-val-display text-danger fw-bold" value="{{ (float)$item->discount_value }}">
                                                <input type="hidden" name="items[{{ $index }}][discount_value]" class="disc-val-real" value="{{ (float)$item->discount_value }}">
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Kolom 4: Aksi --}}
                                    <td class="text-center align-middle">
                                        <button type="button" class="btn btn-white border shadow-sm text-danger remove-item rounded-circle" title="Hapus Baris">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <button type="button" class="px-4 py-2 mt-2 btn btn-primary bg-opacity-10 text-primary border-0 rounded-pill fw-bold" id="addItem" style="background-color: rgba(13, 110, 253, 0.1);">
                            <i class="bi bi-plus-lg me-1"></i> Tambah Baris Item
                        </button>
                    </div>
                </div>

                {{-- CARD 5 & 6: BIAYA EKSTRA & LAMPIRAN --}}
                <div class="row g-4">
                    <div class="col-md-7">
                        <div class="modern-card h-100 mb-0">
                            <div class="p-4 card-body">
                                <h6 class="mb-4 fw-bold text-dark"><i class="bi bi-calculator me-2 text-warning"></i>Biaya Ekstra & Potongan Global</h6>

                                <div class="p-3 mb-3 border rounded-3 bg-light border-warning-subtle">
                                    <div id="chargeContainer">
                                        @foreach($bill->charges as $cIndex => $charge)
                                        <div class="charge-row mb-3 pb-3 border-bottom">
                                            <select name="charges[{{ $cIndex }}][charge_type_id]" class="mb-2 form-select select2-charge" required>
                                                <option value="">-- Pilih Master Biaya --</option>
                                                @foreach($chargeTypes as $ct)
                                                    <option value="{{ $ct->id }}" {{ $charge->charge_type_id == $ct->id ? 'selected' : '' }}>{{ $ct->name }}</option>
                                                @endforeach
                                            </select>
                                            <div class="mb-2 input-group input-group-sm">
                                                <span class="input-group-text curr-symbol border-end-0 bg-white">Rp</span>
                                                <input type="text" class="form-control charge-display text-end fw-bold border-start-0 text-warning-emphasis" value="{{ (float)$charge->amount }}">
                                                <input type="hidden" name="charges[{{ $cIndex }}][amount]" class="charge-real" value="{{ (float)$charge->amount }}">
                                            </div>
                                            <div class="d-flex">
                                                <input type="text" name="charges[{{ $cIndex }}][note]" class="form-control form-control-sm me-2 bg-white" value="{{ $charge->note }}" placeholder="Keterangan opsional...">
                                                <button type="button" class="btn btn-light border btn-sm remove-extra text-danger"><i class="bi bi-trash"></i></button>
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>
                                    <button type="button" class="btn btn-outline-warning btn-sm rounded-pill fw-bold text-dark w-100" id="addCharge">
                                        <i class="bi bi-plus-lg me-1"></i> Tambah Biaya (Ongkir/Materai)
                                    </button>
                                </div>

                                <div class="p-3 border rounded-3 bg-light border-danger-subtle">
                                    <div id="discountContainer">
                                        @foreach($bill->discounts as $dIndex => $discount)
                                        <div class="discount-row mb-3 pb-3 border-bottom">
                                            <select name="discounts[{{ $dIndex }}][discount_type_id]" class="mb-2 form-select select2-discount" required>
                                                <option value="">-- Pilih Master Potongan --</option>
                                                @foreach($discountTypes as $disc)
                                                    <option value="{{ $disc->id }}" {{ $discount->discount_type_id == $disc->id ? 'selected' : '' }}>{{ $disc->name }}</option>
                                                @endforeach
                                            </select>
                                            <div class="mb-2 input-group input-group-sm">
                                                <span class="text-danger input-group-text bg-white curr-symbol border-end-0 border-danger border-opacity-50">Rp</span>
                                                <input type="text" class="form-control ext-disc-display text-end text-danger fw-bold border-start-0 border-danger border-opacity-50" value="{{ (float)$discount->amount }}">
                                                <input type="hidden" name="discounts[{{ $dIndex }}][amount]" class="ext-disc-real" value="{{ (float)$discount->amount }}">
                                            </div>
                                            <div class="d-flex">
                                                <input type="text" name="discounts[{{ $dIndex }}][note]" class="form-control form-control-sm me-2 bg-white" value="{{ $discount->note }}" placeholder="Keterangan potongan...">
                                                <button type="button" class="btn btn-light border btn-sm remove-extra text-danger"><i class="bi bi-trash"></i></button>
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>
                                    <button type="button" class="btn btn-outline-danger btn-sm rounded-pill fw-bold w-100" id="addDiscount">
                                        <i class="bi bi-plus-lg me-1"></i> Tambah Potongan Tagihan
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-5">
                        <div class="modern-card h-100 mb-0">
                            <div class="p-4 card-body">
                                <h6 class="mb-3 fw-bold text-dark"><i class="bi bi-paperclip me-2 text-primary"></i>Lampiran File</h6>

                                {{-- Menampilkan File Saat Ini Jika Ada --}}
                                @if(isset($attachments) && $attachments->count() > 0)
                                    <div class="mb-3">
                                        <label class="form-label-custom text-muted">File Tersimpan (Ceklis untuk menghapus)</label>
                                        <ul class="list-group list-group-flush rounded border">
                                            @foreach($attachments as $file)
                                                <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                                                    <a href="{{ asset('storage/' . $file->file_path) }}" target="_blank" class="text-decoration-none small text-truncate" style="max-width: 70%;">
                                                        <i class="bi bi-file-earmark-text me-2 text-primary"></i>{{ $file->file_name }}
                                                    </a>
                                                    <div class="form-check form-switch m-0">
                                                        <input class="form-check-input border-danger" type="checkbox" name="delete_media[]" value="{{ $file->id }}" id="del_media_{{ $file->id }}">
                                                    </div>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                <div id="attachmentContainer">
                                    <div class="mb-2 input-group">
                                        <input type="file" name="attachments[]" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                                        <button class="btn btn-light border remove-file text-danger" type="button"><i class="bi bi-x-lg"></i></button>
                                    </div>
                                </div>
                                <button type="button" class="mt-2 btn btn-light border text-primary btn-sm rounded-pill fw-bold w-100" id="addFile">
                                    <i class="bi bi-plus-lg me-1"></i> Tambah File Baru
                                </button>
                                <p class="mt-3 text-muted" style="font-size: 0.7rem;"><i class="bi bi-info-circle me-1"></i>Mendukung file PDF, JPG, PNG (Max 5MB/file).</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            {{-- KOLOM KANAN: PANEL STRUK (STICKY) --}}
            <div class="col-xl-4 col-lg-5">
                <div class="receipt-panel">
                    <div class="p-4 card-body">
                        <h5 class="mb-4 text-center fw-bold text-dark pb-3 border-bottom"><i class="bi bi-receipt me-2 text-primary"></i>Ringkasan Tagihan</h5>

                        <div class="receipt-row">
                            <span class="text-muted">Subtotal Item</span>
                            <span class="fw-bold text-dark"><span class="curr-symbol-display">Rp</span> <span id="display_subtotal">0</span></span>
                        </div>
                        <div class="receipt-row">
                            <span class="text-muted">Diskon Per-Item</span>
                            <span class="fw-bold text-danger">- <span class="curr-symbol-display">Rp</span> <span id="display_item_discount">0</span></span>
                        </div>
                        <div class="receipt-row pb-3 mb-3 border-bottom">
                            <span class="text-muted">Total Pajak Item</span>
                            <span class="fw-bold text-info">+ <span class="curr-symbol-display">Rp</span> <span id="display_tax">0</span></span>
                        </div>

                        <div class="receipt-row mt-3">
                            <span class="text-muted fw-bold">Biaya Tambahan</span>
                            <span class="fw-bold text-warning-emphasis">+ <span class="curr-symbol-display">Rp</span> <span id="display_charges">0</span></span>
                        </div>
                        <div class="receipt-row mb-2">
                            <span class="text-muted fw-bold">Potongan Global</span>
                            <span class="fw-bold text-danger">- <span class="curr-symbol-display">Rp</span> <span id="display_extra_discounts">0</span></span>
                        </div>

                        <div class="total-box">
                            <div class="mb-2 text-muted fw-bold text-uppercase" style="font-size: 0.75rem; letter-spacing: 1px;">TOTAL DIBAYARKAN</div>
                            <h2 class="mb-0 fw-bolder text-dark"><span class="curr-symbol-display fs-5">Rp</span> <span id="display_grand_total">0</span></h2>
                        </div>

                        <button type="button" id="btnSubmitForm" class="py-3 mt-4 shadow-sm btn btn-warning w-100 rounded-pill fw-bold d-flex align-items-center justify-content-center text-dark" style="font-size: 1.05rem;">
                            <i class="bi bi-save2 fs-5 me-2"></i> Simpan Revisi
                        </button>
                        <a href="{{ route('bills.show', $bill->bill_number) }}" class="py-2 mt-2 btn btn-light w-100 rounded-pill fw-bold text-muted border-0">Batal & Kembali</a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

{{-- TEMPLATE CLONING HIDDEN --}}
<div id="hiddenSelectTemplate" style="display: none;">
    <select class="mb-3 form-select select2-item-template item-select" required onchange="onOpexItemSelect(this, 'INDEX_PLACEHOLDER')">
        <option value="">-- Pilih Item Opex --</option>
        @foreach($opexItems as $opx)
            <option value="{{ $opx->id }}">{{ $opx->code }} - {{ $opx->name }}</option>
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
        $('.select2-charge').select2({ theme: 'bootstrap-5', width: '100%' });
        $('.select2-discount').select2({ theme: 'bootstrap-5', width: '100%' });
        $('.select2-vendor').select2({ theme: 'bootstrap-5', width: '100%', tags: true, placeholder: "Cari atau ketik Vendor..." });
    }
    initSelect2();

    function formatNumber(num) { return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, "."); }
    function unformatNumber(str) { return parseInt(str.toString().replace(/[^0-9]/g, '')) || 0; }

    // INITIALISASI NILAI HARGA DARI DATABASE SAAT LOAD
    $('.price-display, .disc-val-display, .tax-val-display, .charge-display, .ext-disc-display').each(function() {
        let isPercent = false;
        if($(this).hasClass('disc-val-display')) isPercent = $(this).closest('.input-group').find('.disc-type').val() === 'percent';
        if($(this).hasClass('tax-val-display')) isPercent = $(this).closest('.input-group').find('.tax-type').val() === 'percent';

        let val = parseFloat($(this).val()) || 0;
        $(this).val(isPercent ? val : formatNumber(val));
    });

    $(document).on('keyup', '.price-display, .disc-val-display, .tax-val-display, .charge-display, .ext-disc-display, #global_tax_val', function() {
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

        if ($(this).attr('id') !== 'global_tax_val') {
            $(this).siblings('input[type="hidden"]').val(val);
        }
        calculate();
    });

    $(document).on('change', '.disc-type', function() {
        let displayInput = $(this).closest('.input-group').find('input[type="text"]');
        let hiddenInput = $(this).closest('.input-group').find('input[type="hidden"]');
        displayInput.val(0); hiddenInput.val(0);
        calculate();
    });

    // ==========================================
    // 🔥 LOGIKA PAJAK HYBRID (PER ITEM) 🔥
    // ==========================================
    $(document).on('change', '.tax-type', function() {
        let container = $(this).closest('.input-group');
        let masterSelect = container.find('.tax-master-select');
        let valDisplay = container.find('.tax-val-display');
        let valReal = container.find('.tax-val-real');

        if ($(this).val() === 'percent') {
            masterSelect.removeClass('d-none');
            if (masterSelect.val() === 'MANUAL_PERCENT') {
                valDisplay.removeClass('d-none');
            } else {
                valDisplay.addClass('d-none');
                let rate = masterSelect.find(':selected').data('rate') || 0;
                valDisplay.val(rate);
                valReal.val(rate);
            }
        } else {
            masterSelect.addClass('d-none');
            valDisplay.removeClass('d-none');
            valDisplay.val(0);
            valReal.val(0);
        }
        calculate();
    });

    $(document).on('change', '.tax-master-select', function() {
        let container = $(this).closest('.input-group');
        let valDisplay = container.find('.tax-val-display');
        let valReal = container.find('.tax-val-real');

        if ($(this).val() === 'MANUAL_PERCENT') {
            valDisplay.removeClass('d-none').val(0);
            valReal.val(0);
            valDisplay.focus();
        } else {
            valDisplay.addClass('d-none');
            let rate = $(this).find(':selected').data('rate') || 0;
            valDisplay.val(rate);
            valReal.val(rate);
        }
        calculate();
    });

    // ==========================================
    // 🔥 LOGIKA PAJAK HYBRID (GLOBAL) 🔥
    // ==========================================
    $('#global_tax_type').change(function() {
        let masterSelect = $('#global_tax_master');
        let valDisplay = $('#global_tax_val');

        if ($(this).val() === 'percent') {
            masterSelect.removeClass('d-none');
            if(masterSelect.val() === 'MANUAL_PERCENT') {
                valDisplay.removeClass('d-none');
            } else {
                valDisplay.addClass('d-none');
            }
        } else {
            masterSelect.addClass('d-none');
            valDisplay.removeClass('d-none').val(0);
        }
    });

    $('#global_tax_master').change(function() {
        let valDisplay = $('#global_tax_val');
        if ($(this).val() === 'MANUAL_PERCENT') {
            valDisplay.removeClass('d-none').val(0).focus();
        } else {
            valDisplay.addClass('d-none');
            let rate = $(this).find(':selected').data('rate') || 0;
            valDisplay.val(rate);
        }
    });

    $('#btnApplyGlobalTax').click(function() {
        let type = $('#global_tax_type').val();
        let masterId = $('#global_tax_master').val();
        let rate = $('#global_tax_master').find(':selected').data('rate') || 0;
        let manualValDisplay = $('#global_tax_val').val();
        let manualValReal = unformatNumber(manualValDisplay);

        if($('.item-row').length === 0) return;

        $('.item-row').each(function() {
            let rowType = $(this).find('.tax-type');
            let rowMaster = $(this).find('.tax-master-select');
            let rowDisplay = $(this).find('.tax-val-display');
            let rowReal = $(this).find('.tax-val-real');

            rowType.val(type);

            if (type === 'percent') {
                rowMaster.val(masterId).removeClass('d-none');
                if (masterId === 'MANUAL_PERCENT') {
                    rowDisplay.val(manualValDisplay).removeClass('d-none');
                    rowReal.val(manualValReal);
                } else {
                    rowDisplay.val(rate).addClass('d-none');
                    rowReal.val(rate);
                }
            } else {
                rowMaster.addClass('d-none');
                rowDisplay.val(manualValDisplay).removeClass('d-none');
                rowReal.val(manualValReal);
            }
        });

        calculate();

        Swal.fire({
            toast: true, position: 'top-end', icon: 'success',
            title: 'Pajak massal berhasil diterapkan!',
            showConfirmButton: false, timer: 2000
        });
    });

    // ==========================================
    // TAMBAH ITEM DAN BIAYA EKSTRA
    // ==========================================
    $('#addItem').click(function() {
        const container = document.getElementById('itemContainer');
        const index = new Date().getTime();
        const templateSelect = $('#hiddenSelectTemplate').html().replace('INDEX_PLACEHOLDER', index);

        const tr = document.createElement('tr');
        tr.className = 'item-row';
        tr.innerHTML = `
            <td>
                <div class="item-box">
                    <label class="form-label text-xs fw-bold text-muted">Master Item <span class="text-danger">*</span></label>
                    ${templateSelect}

                    <label class="form-label text-xs fw-bold text-muted">Nama di Tagihan <span class="text-danger">*</span></label>
                    <input type="text" name="items[${index}][name_override]" id="name_override_${index}" class="mb-3 form-control form-control-sm fw-bold text-primary" placeholder="Bisa diedit..." required>

                    <label class="form-label text-xs fw-bold text-muted">Spesifikasi Detail</label>
                    <textarea name="items[${index}][description]" class="form-control form-control-sm" rows="2" placeholder="Bisa tekan enter..."></textarea>
                </div>
            </td>
            <td>
                <div class="item-box">
                    <label class="form-label text-xs fw-bold text-muted">Jumlah (Qty) <span class="text-danger">*</span></label>
                    <input type="number" name="items[${index}][qty]" class="mb-3 text-center form-control form-control-sm qty fw-bold" value="1" min="1">

                    <label class="form-label text-xs fw-bold text-muted">Harga Satuan <span class="text-danger">*</span></label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text curr-symbol bg-light text-muted border-end-0">Rp</span>
                        <input type="text" class="form-control price-display text-end fw-bold border-start-0" value="0">
                        <input type="hidden" name="items[${index}][price]" class="price-real" value="0">
                    </div>
                </div>
            </td>
            <td>
                <div class="item-box border-info-subtle">
                    <label class="form-label text-xs fw-bold text-info"><i class="bi bi-plus-circle me-1"></i>Pajak Item</label>
                    <div class="mb-3 input-group input-group-sm">
                        <select name="items[${index}][tax_type]" class="form-select tax-type" style="max-width: 55px; padding-right:5px; padding-left:5px;">
                            <option value="percent" selected>%</option><option value="fixed">Rp</option>
                        </select>
                        <select name="items[${index}][tax_id]" class="form-select tax-master-select fw-bold text-info">
                            <option value="" data-rate="0">- Tanpa Pajak -</option><option value="MANUAL_PERCENT" data-rate="0">Manual (%)</option>
                            @foreach($taxes as $tax)
                                <option value="{{ $tax->id }}" data-rate="{{ (float)$tax->percent }}">{{ $tax->name }}</option>
                            @endforeach
                        </select>
                        <input type="text" class="form-control text-end tax-val-display d-none" value="0">
                        <input type="hidden" name="items[${index}][tax_value]" class="tax-val-real" value="0">
                    </div>

                    <label class="form-label text-xs fw-bold text-danger"><i class="bi bi-dash-circle me-1"></i>Diskon Item</label>
                    <div class="input-group input-group-sm">
                        <select name="items[${index}][discount_type]" class="form-select disc-type" style="max-width: 55px; padding-right:5px; padding-left:5px;">
                            <option value="fixed">Rp</option><option value="percent" selected>%</option>
                        </select>
                        <input type="text" class="form-control text-end disc-val-display text-danger fw-bold" value="0">
                        <input type="hidden" name="items[${index}][discount_value]" class="disc-val-real" value="0">
                    </div>
                </div>
            </td>
            <td class="text-center align-middle">
                <button type="button" class="btn btn-white border shadow-sm text-danger remove-item rounded-circle" title="Hapus Baris"><i class="bi bi-trash"></i></button>
            </td>
        `;
        container.appendChild(tr);
        $(tr).find('.select2-item-template').removeClass('select2-item-template').addClass('select2-item').attr('name', `items[${index}][item_id]`);
        $(tr).find('.select2-item').select2({ theme: 'bootstrap-5', width: '100%' });
        updateSymbols();
    });

    $('#addCharge').click(function() {
        const container = document.getElementById('chargeContainer');
        const index = new Date().getTime(); // Mencegah bentrok dengan index dari foreach blade
        const templateSelect = $('#hiddenChargeTemplate').html();

        const div = document.createElement('div');
        div.className = 'charge-row mb-3 pb-3 border-bottom';
        div.innerHTML = `
            ${templateSelect}
            <div class="mb-2 input-group input-group-sm">
                <span class="input-group-text curr-symbol border-end-0 bg-white">Rp</span>
                <input type="text" class="form-control charge-display text-end fw-bold border-start-0 text-warning-emphasis" value="0">
                <input type="hidden" name="charges[${index}][amount]" class="charge-real" value="0">
            </div>
            <div class="d-flex">
                <input type="text" name="charges[${index}][note]" class="form-control form-control-sm me-2 bg-white" placeholder="Keterangan opsional...">
                <button type="button" class="btn btn-light border btn-sm remove-extra text-danger"><i class="bi bi-trash"></i></button>
            </div>
        `;
        container.appendChild(div);
        $(div).find('.select2-charge-template').removeClass('select2-charge-template').addClass('select2-charge').attr('name', `charges[${index}][charge_type_id]`);
        $(div).find('.select2-charge').select2({ theme: 'bootstrap-5', width: '100%' });
        updateSymbols();
    });

    $('#addDiscount').click(function() {
        const container = document.getElementById('discountContainer');
        const index = new Date().getTime(); // Mencegah bentrok dengan index dari foreach blade
        const templateSelect = $('#hiddenDiscountTemplate').html();

        const div = document.createElement('div');
        div.className = 'discount-row mb-3 pb-3 border-bottom';
        div.innerHTML = `
            ${templateSelect}
            <div class="mb-2 input-group input-group-sm">
                <span class="text-danger input-group-text bg-white curr-symbol border-end-0 border-danger border-opacity-50">Rp</span>
                <input type="text" class="form-control ext-disc-display text-end text-danger fw-bold border-start-0 border-danger border-opacity-50" value="0">
                <input type="hidden" name="discounts[${index}][amount]" class="ext-disc-real" value="0">
            </div>
            <div class="d-flex">
                <input type="text" name="discounts[${index}][note]" class="form-control form-control-sm me-2 bg-white" placeholder="Keterangan potongan...">
                <button type="button" class="btn btn-light border btn-sm remove-extra text-danger"><i class="bi bi-trash"></i></button>
            </div>
        `;
        container.appendChild(div);
        $(div).find('.select2-discount-template').removeClass('select2-discount-template').addClass('select2-discount').attr('name', `discounts[${index}][discount_type_id]`);
        $(div).find('.select2-discount').select2({ theme: 'bootstrap-5', width: '100%' });
        updateSymbols();
    });

    $(document).on('click', '.remove-item', function() {
        if ($('.item-row').length > 1) {$(this).closest('.item-row').remove(); calculate(); }
        else { Swal.fire('Oops!', 'Minimal harus ada 1 barang/jasa.', 'warning'); }
    });
    $(document).on('click', '.remove-extra', function() {$(this).closest('.charge-row, .discount-row').remove(); calculate(); });

    $('#addFile').click(function() {
        $('#attachmentContainer').append(`<div class="mb-2 input-group"><input type="file" name="attachments[]" class="form-control" accept=".pdf,.jpg,.jpeg,.png"><button class="btn btn-light border remove-file text-danger" type="button"><i class="bi bi-x-lg"></i></button></div>`);
    });
    $(document).on('click', '.remove-file', function() {$(this).closest('.input-group').remove(); });

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

    $('#btnSubmitForm').click(function() {
        const form = document.getElementById('billForm');
        if (!form.checkValidity()) { form.reportValidity(); return; }

        Swal.fire({
            title: 'Simpan Revisi Tagihan?', text: "Pastikan semua perubahan nominal dan spesifikasi sudah benar.", icon: 'warning',
            showCancelButton: true, confirmButtonColor: '#ffc107', cancelButtonColor: '#6c757d',
            confirmButtonText: '<span class="text-dark">Ya, Update!</span>', reverseButtons: true, borderRadius: '1rem'
        }).then((result) => {
            if (result.isConfirmed) {
                $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2 text-dark"></span> <span class="text-dark">Menyimpan...</span>');
                Swal.fire({ title: 'Memproses Data...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
                form.submit();
            }
        });
    });

    // ==========================================
    // 🔥 AUTO FILL NAMA CUSTOM DARI MASTER 🔥
    // ==========================================
    window.onOpexItemSelect = function(selectObj, index) {
        if(!selectObj.options || selectObj.selectedIndex < 0) return;
        let selectedOption = selectObj.options[selectObj.selectedIndex];
        let itemNameRaw = selectedOption.text;

        let itemNameSplit = itemNameRaw.split(' - ');
        let cleanName = itemNameSplit.length > 1 ? itemNameSplit.slice(1).join(' - ') : itemNameRaw;

        if(selectObj.value !== "") {
            document.getElementById('name_override_' + index).value = cleanName;
        } else {
            document.getElementById('name_override_' + index).value = "";
        }
    };

    calculate(); // Panggil fungsi hitung total saat pertama halaman dirender
});
</script>
@endpush
