@extends('layouts.app')

@push('css')
<style>
    /* Modern SaaS Aesthetic */
    .opex-wrapper { background-color: #f8fafc; min-height: 100vh; }

    /* KPI Cards */
    .kpi-card-modern {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
    }
    .kpi-card-modern:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 24px -8px rgba(15, 23, 42, 0.08) !important;
        border-color: #cbd5e1;
    }
    .kpi-icon-box {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
        flex-shrink: 0;
    }

    /* Segmented Tabs */
    .nav-segmented {
        background-color: #e2e8f0;
        padding: 4px;
        border-radius: 12px;
        display: inline-flex;
        gap: 4px;
    }
    .nav-segmented .nav-link {
        color: #475569;
        font-weight: 600;
        font-size: 0.85rem;
        border-radius: 9px;
        padding: 0.45rem 1.15rem;
        transition: all 0.2s ease;
        border: none;
    }
    .nav-segmented .nav-link:hover:not(.active) {
        color: #0f172a;
        background-color: rgba(255, 255, 255, 0.5);
    }
    .nav-segmented .nav-link.active {
        background-color: #ffffff;
        color: #0d6efd;
        box-shadow: 0 2px 6px rgba(15, 23, 42, 0.08);
    }

    /* Filter Box */
    .filter-card-modern {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
    }
    .form-control-modern, .form-select-modern {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        font-size: 0.85rem;
        padding: 0.5rem 0.85rem;
        color: #334155;
        background-color: #f8fafc;
        transition: all 0.2s;
    }
    .form-control-modern:focus, .form-select-modern:focus {
        background-color: #ffffff;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
    }

    /* Table Styling */
    .table-card-modern {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        overflow: hidden;
    }
    .table-opex thead th {
        background-color: #f8fafc;
        color: #64748b;
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        padding: 1rem 1.25rem;
        border-bottom: 1px solid #e2e8f0;
        border-top: none;
    }
    .table-opex tbody td {
        padding: 1.05rem 1.25rem;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
        font-size: 0.875rem;
    }
    .table-opex tbody tr.bill-row {
        transition: background-color 0.15s ease;
    }
    .table-opex tbody tr.bill-row:hover {
        background-color: #f8fafc;
    }
    .overdue-row {
        background-color: #fff5f5 !important;
        border-left: 3px solid #ef4444;
    }
    .normal-row {
        border-left: 3px solid transparent;
    }

    /* Typography & Badges */
    .tabular-nums { font-variant-numeric: tabular-nums; }
    .badge-pill-soft {
        padding: 0.35em 0.75em;
        font-weight: 600;
        font-size: 0.72rem;
        border-radius: 50rem;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .status-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        display: inline-block;
    }
    .btn-action-dots {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        background: #ffffff;
        color: #64748b;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }
    .btn-action-dots:hover {
        background: #f1f5f9;
        color: #0f172a;
        border-color: #cbd5e1;
    }
</style>
@endpush

@section('content')
<div class="px-3 py-4 container-fluid px-md-4 opex-wrapper">

    {{-- HEADER --}}
    <div class="gap-3 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <div class="gap-2 mb-1 d-flex align-items-center">
                <div class="p-2 text-white shadow-sm rounded-3 bg-primary d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                    <i class="bi bi-wallet2 fs-5"></i>
                </div>
                <h4 class="mb-0 fw-bold text-dark" style="letter-spacing: -0.02em;">Daftar Tagihan Opex</h4>
            </div>
            <p class="mb-0 text-muted small">Kelola tagihan operasional, siklus langganan otomatis, dan jadwal jatuh tempo pembayaran.</p>
        </div>

        <div class="gap-2 d-flex">
            <a href="{{ route('bills.create') }}" class="px-4 py-2 shadow-sm btn btn-primary rounded-pill fw-semibold d-inline-flex align-items-center">
                <i class="bi bi-plus-lg me-2"></i> Buat Tagihan Baru
            </a>
        </div>
    </div>

    {{-- KPI SUMMARY CARDS --}}
    <div class="mb-4 row g-3">
        {{-- Card 1: Total --}}
        <div class="col-md-4">
            <div class="p-3 shadow-sm kpi-card-modern h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="mb-1 text-muted fw-bold text-uppercase d-block" style="font-size: 0.7rem; letter-spacing: 0.05em;">Total Tagihan (Sesuai Filter)</span>
                        <h4 class="mb-1 fw-bold text-dark tabular-nums">Rp {{ number_format($summary['total_nominal'] ?? 0, 0, ',', '.') }}</h4>
                        <span class="text-muted small"><i class="bi bi-file-earmark-text me-1"></i>{{ $summary['total_count'] ?? 0 }} dokumen tagihan</span>
                    </div>
                    <div class="kpi-icon-box bg-primary-subtle text-primary">
                        <i class="bi bi-receipt"></i>
                    </div>
                </div>
            </div>
        </div>

        {{-- Card 2: Unpaid --}}
        <div class="col-md-4">
            <div class="p-3 shadow-sm kpi-card-modern h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="mb-1 text-muted fw-bold text-uppercase d-block" style="font-size: 0.7rem; letter-spacing: 0.05em;">Menunggu Pembayaran</span>
                        <h4 class="mb-1 fw-bold text-warning-emphasis tabular-nums">Rp {{ number_format($summary['total_unpaid'] ?? 0, 0, ',', '.') }}</h4>
                        <span class="text-muted small"><i class="bi bi-clock-history me-1"></i>{{ $summary['count_unpaid'] ?? 0 }} tagihan belum lunas</span>
                    </div>
                    <div class="kpi-icon-box bg-warning-subtle text-warning-emphasis">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                </div>
            </div>
        </div>

        {{-- Card 3: Overdue (Clickable Filter) --}}
        <div class="col-md-4">
            <a href="{{ route('bills.index', array_merge(request()->query(), ['overdue' => request('overdue') == '1' ? null : '1'])) }}" class="text-decoration-none">
                <div class="p-3 shadow-sm kpi-card-modern h-100 {{ request('overdue') == '1' ? 'border-danger bg-danger-subtle' : '' }}">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="gap-2 mb-1 d-flex align-items-center">
                                <span class="text-danger fw-bold text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.05em;">Lewat Jatuh Tempo</span>
                                @if(request('overdue') == '1')
                                    <span class="badge bg-danger rounded-pill" style="font-size: 0.65rem;">Filter Aktif <i class="bi bi-x"></i></span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger rounded-pill" style="font-size: 0.65rem;">Klik Saring</span>
                                @endif
                            </div>
                            <h4 class="mb-1 fw-bold text-danger tabular-nums">Rp {{ number_format($summary['total_overdue'] ?? 0, 0, ',', '.') }}</h4>
                            <span class="text-danger-emphasis small"><i class="bi bi-exclamation-circle me-1"></i>{{ $summary['count_overdue'] ?? 0 }} tagihan terlambat bayar</span>
                        </div>
                        <div class="kpi-icon-box {{ ($summary['count_overdue'] ?? 0) > 0 ? 'bg-danger text-white' : 'bg-danger-subtle text-danger' }}">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    {{-- TOOLBAR: TABS & RESET --}}
    <div class="flex-wrap gap-3 mb-3 d-flex justify-content-between align-items-center">
        <div class="nav-segmented">
            <a class="nav-link {{ request('tab') != 'recurring' ? 'active' : '' }}" href="{{ route('bills.index', request()->except('tab', 'page')) }}">
                <i class="bi bi-grid-1x2 me-1"></i> Semua Tagihan
            </a>
            <a class="nav-link {{ request('tab') == 'recurring' ? 'active' : '' }}" href="{{ route('bills.index', array_merge(request()->except('page'), ['tab' => 'recurring'])) }}">
                <i class="bi bi-arrow-repeat me-1"></i> Langganan Aktif (Recurring)
            </a>
        </div>

        @php
            $hasActiveFilters = request()->anyFilled(['company_id', 'vendor', 'status', 'search', 'month', 'year', 'start_date', 'end_date', 'overdue', 'bill_type']);
            $hasDateFilters = request()->anyFilled(['month', 'year', 'start_date', 'end_date']);
        @endphp

        <div class="gap-2 d-flex align-items-center">
            @if($hasActiveFilters)
                <a href="{{ route('bills.index', ['tab' => request('tab')]) }}" class="px-3 btn btn-sm btn-outline-danger rounded-pill fw-semibold">
                    <i class="bi bi-x-circle me-1"></i> Reset Filter
                </a>
            @endif
        </div>
    </div>

    {{-- FILTER CARD --}}
    <div class="p-3 mb-4 shadow-sm filter-card-modern">
        <form action="{{ route('bills.index') }}" method="GET">
            @if(request()->has('tab')) <input type="hidden" name="tab" value="{{ request('tab') }}"> @endif
            @if(request()->has('overdue')) <input type="hidden" name="overdue" value="{{ request('overdue') }}"> @endif

            {{-- BARIS UTAMA (RINGKAS & BERSIH) --}}
            <div class="row g-2 align-items-center">
                <div class="col-md-3">
                    <div class="position-relative">
                        <i class="bi bi-search position-absolute text-muted" style="left: 12px; top: 50%; transform: translateY(-50%); font-size: 0.85rem;"></i>
                        <input type="text" name="search" class="form-control form-control-modern ps-5"
                               placeholder="Cari No. Bill, judul, vendor..." value="{{ request('search') }}">
                    </div>
                </div>

                <div class="col-md-2">
                    <select name="company_id" class="form-select form-select-modern">
                        <option value="">Semua PT</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}" {{ request('company_id') == $company->id ? 'selected' : '' }}>
                                {{ $company->code ? '['.$company->code.'] ' : '' }}{{ $company->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <select name="bill_type" class="form-select form-select-modern">
                        <option value="">Semua Jenis</option>
                        <option value="parent" {{ request('bill_type') == 'parent' ? 'selected' : '' }}>Induk Berulang</option>
                        <option value="auto" {{ request('bill_type') == 'auto' ? 'selected' : '' }}>Auto-Recurring</option>
                        <option value="manual" {{ request('bill_type') == 'manual' ? 'selected' : '' }}>Manual (Sekali)</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <select name="status" class="form-select form-select-modern">
                        <option value="">Semua Status</option>
                        <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="partial" {{ request('status') == 'partial' ? 'selected' : '' }}>Partial</option>
                        <option value="paid" {{ request('status') == 'paid' ? 'selected' : '' }}>Paid</option>
                        <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                    </select>
                </div>

                <div class="gap-2 col-md-3 d-flex">
                    <button type="button" class="border btn btn-light rounded-3 fw-semibold text-secondary flex-grow-1 d-flex align-items-center justify-content-center"
                            style="font-size: 0.85rem;" data-bs-toggle="collapse" data-bs-target="#advancedDateFilter" aria-expanded="{{ $hasDateFilters ? 'true' : 'false' }}">
                        <i class="bi bi-calendar-range me-1 text-primary"></i> Periode
                        @if($hasDateFilters)
                            <span class="p-1 ms-1 bg-primary rounded-circle"></span>
                        @endif
                    </button>

                    <button type="submit" class="px-4 btn btn-dark rounded-3 fw-semibold" style="font-size: 0.85rem;">
                        Filter
                    </button>
                </div>
            </div>

            {{-- BARIS PERIODE WAKTU (COLLAPSIBLE AGAR RAPI) --}}
            <div class="collapse {{ $hasDateFilters ? 'show' : '' }}" id="advancedDateFilter">
                <div class="pt-3 mt-3 border-top row g-2 align-items-end">
                    <div class="col-md-2">
                        <label class="mb-1 small fw-bold text-muted" style="font-size: 0.7rem;">ACUAN TANGGAL</label>
                        <select name="date_type" class="form-select form-select-modern fw-semibold">
                            <option value="invoice_date" {{ request('date_type', 'invoice_date') == 'invoice_date' ? 'selected' : '' }}>Tgl Tagihan</option>
                            <option value="due_date" {{ request('date_type') == 'due_date' ? 'selected' : '' }}>Tgl Jatuh Tempo</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="mb-1 small fw-bold text-muted" style="font-size: 0.7rem;">BULAN</label>
                        <select name="month" id="filter_month" class="form-select form-select-modern">
                            <option value="">-- Semua Bulan --</option>
                            @php
                                $months = [
                                    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                                    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                                    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
                                ];
                            @endphp
                            @foreach($months as $num => $name)
                                <option value="{{ $num }}" {{ request('month') == $num ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="mb-1 small fw-bold text-muted" style="font-size: 0.7rem;">TAHUN</label>
                        <select name="year" id="filter_year" class="form-select form-select-modern">
                            <option value="">-- Semua Tahun --</option>
                            @foreach($availableYears as $yr)
                                <option value="{{ $yr }}" {{ request('year') == $yr ? 'selected' : '' }}>{{ $yr }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="mb-1 small fw-bold text-muted" style="font-size: 0.7rem;">DARI TANGGAL</label>
                        <input type="date" name="start_date" id="filter_start_date" class="form-control form-control-modern" value="{{ request('start_date') }}">
                    </div>

                    <div class="col-md-2">
                        <label class="mb-1 small fw-bold text-muted" style="font-size: 0.7rem;">SAMPAI TANGGAL</label>
                        <input type="date" name="end_date" id="filter_end_date" class="form-control form-control-modern" value="{{ request('end_date') }}">
                    </div>

                    <div class="col-md-2">
                        <label class="mb-1 small fw-bold text-muted" style="font-size: 0.7rem;">NAMA VENDOR</label>
                        <input type="text" name="vendor" class="form-control form-control-modern" placeholder="Filter vendor..." value="{{ request('vendor') }}">
                    </div>
                </div>
            </div>
        </form>
    </div>

    {{-- TABEL DATA --}}
    <div class="shadow-sm table-card-modern">
        <div class="table-responsive" style="min-height: 320px;">
            <table class="table mb-0 table-opex">
                <thead>
                    <tr>
                        <th width="19%">No. Dokumen & Jenis</th>
                        <th width="25%">Rincian Tagihan</th>
                        <th width="19%">Vendor & Entitas PT</th>
                        <th class="text-end" width="13%">Nominal</th>
                        <th class="text-center" width="11%">Jatuh Tempo</th>
                        <th class="text-center" width="9%">Status</th>
                        <th class="text-end" width="4%"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bills as $bill)
                    @php
                        $statusSlug = optional($bill->status)->slug ?? 'unknown';
                        $statusName = optional($bill->status)->name ?? 'UNKNOWN';
                        $statusColor = optional($bill->status)->color ?? 'secondary';

                        $dueDate = $bill->due_date ? \Carbon\Carbon::parse($bill->due_date)->startOfDay() : null;
                        $today = now()->startOfDay();
                        $isUnpaid = !in_array($statusSlug, ['paid', 'rejected', 'cancelled']);
                        $isOverdue = $dueDate && $dueDate->lt($today) && $isUnpaid;
                        $daysDiff = $dueDate ? $today->diffInDays($dueDate, false) : null;

                        // Deteksi Jenis Tagihan
                        $billSig = strtolower(trim(($bill->title ?? '') . '|' . ($bill->vendor_name ?? '') . '|' . ($bill->company_id ?? '')));
                        $isAutoChild = !$bill->is_recurring && (
                            !empty($bill->parent_id)
                            || !empty($bill->parent_bill_id)
                            || str_contains(strtolower($bill->notes ?? ''), 'otomatis')
                            || str_contains(strtolower($bill->notes ?? ''), 'recurring')
                            || str_contains(strtolower($bill->description ?? ''), 'otomatis')
                            || (isset($recurringSignatures) && in_array($billSig, $recurringSignatures))
                        );

                        // Bersihkan judul agar tidak duplikat dengan nama vendor
                        $cleanTitle = trim(str_ireplace('Tagihan Opex - ', '', $bill->title ?? ''));
                        $isTitleSameAsVendor = strtolower($cleanTitle) === strtolower(trim($bill->vendor_name ?? ''));
                        $displayTitle = ($isTitleSameAsVendor && !empty($bill->description)) ? $bill->description : ($cleanTitle ?: '-');
                        $displaySub = ($isTitleSameAsVendor && !empty($bill->description)) ? 'Tagihan Operasional' : ($bill->description ?: 'Tidak ada catatan tambahan');
                    @endphp
                    <tr class="bill-row {{ $isOverdue ? 'overdue-row' : 'normal-row' }}" style="cursor: pointer;" data-href="{{ route('bills.show', $bill->bill_number) }}">

                        {{-- KOLOM 1: NOMOR & JENIS --}}
                        <td>
                            <a href="{{ route('bills.show', $bill->bill_number) }}" class="mb-1 fw-bold text-decoration-none text-primary d-block" style="font-size: 0.85rem;">
                                {{ $bill->bill_number }}
                            </a>
                            <div class="flex-wrap gap-2 d-flex align-items-center">
                                <span class="text-muted" style="font-size: 0.75rem;">
                                    {{ \Carbon\Carbon::parse($bill->invoice_date)->format('d M Y') }}
                                </span>

                                @if($bill->is_recurring)
                                    <span class="border badge bg-info-subtle text-info-emphasis border-info-subtle rounded-pill" style="font-size: 0.65rem;" title="Induk Langganan">
                                        <i class="bi bi-arrow-repeat"></i> Induk
                                    </span>
                                @elseif($isAutoChild)
                                    <span class="border badge rounded-pill" style="background-color: #f3e8ff; color: #6b21a8; border-color: #d8b4fe !important; font-size: 0.65rem;" title="Auto-Recurring">
                                        <i class="bi bi-robot"></i> Auto
                                    </span>
                                @else
                                    <span class="border badge bg-light text-secondary rounded-pill" style="font-size: 0.65rem;" title="Input Manual">
                                        Manual
                                    </span>
                                @endif
                            </div>
                        </td>

                        {{-- KOLOM 2: RINCIAN TAGIHAN (ANTI DUPLIKAT) --}}
                        <td>
                            <div class="fw-semibold text-dark text-truncate" style="max-width: 280px;" title="{{ $displayTitle }}">
                                {{ $displayTitle }}
                            </div>
                            <div class="text-muted text-truncate" style="max-width: 280px; font-size: 0.78rem;" title="{{ $displaySub }}">
                                {{ $displaySub }}
                            </div>
                        </td>

                        {{-- KOLOM 3: VENDOR & PT --}}
                        <td>
                            <div class="fw-medium text-dark text-truncate" style="max-width: 210px;" title="{{ $bill->vendor_name }}">
                                {{ $bill->vendor_name }}
                            </div>
                            <div class="gap-1 text-muted text-truncate d-flex align-items-center" style="max-width: 210px; font-size: 0.75rem;">
                                <i class="bi bi-building text-secondary"></i>
                                <span>{{ $bill->company->name ?? '-' }}</span>
                            </div>
                        </td>

                        {{-- KOLOM 4: NOMINAL --}}
                        <td class="text-end tabular-nums">
                            <span class="text-muted small me-1">{{ $bill->currency }}</span>
                            <span class="fw-bold text-dark" style="font-size: 0.92rem;">{{ number_format($bill->amount, 0, ',', '.') }}</span>
                        </td>

                        {{-- KOLOM 5: JATUH TEMPO --}}
                        <td class="text-center tabular-nums">
                            <div class="fw-medium {{ $isOverdue ? 'text-danger fw-bold' : 'text-dark' }}" style="font-size: 0.82rem;">
                                {{ $dueDate ? $dueDate->format('d M Y') : '-' }}
                            </div>
                            @if($dueDate && $isUnpaid)
                                @if($daysDiff < 0)
                                    <span class="mt-1 badge bg-danger rounded-pill" style="font-size: 0.62rem;">Telat {{ abs($daysDiff) }} hr</span>
                                @elseif($daysDiff == 0)
                                    <span class="mt-1 badge bg-warning text-dark rounded-pill" style="font-size: 0.62rem;">Hari Ini</span>
                                @elseif($daysDiff <= 7)
                                    <span class="mt-1 border badge bg-warning-subtle text-warning-emphasis border-warning rounded-pill" style="font-size: 0.62rem;">H-{{ $daysDiff }}</span>
                                @endif
                            @endif
                        </td>

                        {{-- KOLOM 6: STATUS --}}
                        <td class="text-center">
                            <span class="badge-pill-soft bg-{{ $statusColor }}-subtle text-{{ $statusColor }} border border-{{ $statusColor }}-subtle">
                                <span class="status-dot bg-{{ $statusColor }}"></span>
                                {{ $statusName }}
                            </span>
                        </td>

                        {{-- KOLOM 7: AKSI --}}
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn-action-dots" type="button" data-bs-toggle="dropdown" data-bs-boundary="window">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="py-2 border-0 shadow-lg dropdown-menu dropdown-menu-end rounded-3" style="font-size: 0.85rem;">
                                    <li>
                                        <a class="py-2 dropdown-item" href="{{ route('bills.show', $bill->bill_number) }}">
                                            <i class="bi bi-eye me-2 text-primary"></i> Lihat Detail
                                        </a>
                                    </li>
                                    <li>
                                        <a class="py-2 dropdown-item" href="{{ route('bills.print', $bill->bill_number) }}" target="_blank">
                                            <i class="bi bi-printer me-2 text-dark"></i> Cetak Dokumen
                                        </a>
                                    </li>

                                    @if(in_array($statusSlug, ['pending', 'draft', 'rejected']))
                                        <li><hr class="my-1 dropdown-divider"></li>
                                        <li>
                                            <a class="py-2 dropdown-item" href="{{ route('bills.edit', $bill->bill_number) }}">
                                                <i class="bi bi-pencil me-2 text-warning"></i> Edit Tagihan
                                            </a>
                                        </li>
                                        <li>
                                            <form action="{{ route('bills.destroy', $bill->bill_number) }}" method="POST" class="form-delete">
                                                @csrf
                                                @method('DELETE')
                                                <button type="button" class="py-2 dropdown-item text-danger btn-delete">
                                                    <i class="bi bi-trash me-2"></i> Hapus
                                                </button>
                                            </form>
                                        </li>
                                    @endif

                                    @if(in_array($statusSlug, ['approved', 'partial']))
                                        <li><hr class="my-1 dropdown-divider"></li>
                                        <li>
                                            <a class="py-2 dropdown-item fw-bold text-success" href="{{ route('payments.process', $bill->bill_number) }}">
                                                <i class="bi bi-cash-coin me-2"></i> Input Pembayaran
                                            </a>
                                        </li>
                                    @endif
                                </ul>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-5 text-center text-muted">
                            <div class="py-4">
                                <i class="mb-2 opacity-25 bi bi-inbox display-5 d-block"></i>
                                <h6 class="fw-bold text-dark">Tidak Ada Tagihan Ditemukan</h6>
                                <p class="mb-0 small">Belum ada data tagihan atau tidak ada yang cocok dengan filter yang dipilih.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($bills->hasPages())
            <div class="gap-2 px-4 py-3 bg-white border-top d-flex flex-column flex-md-row justify-content-between align-items-center">
                <div class="small text-muted">
                    Menampilkan <span class="fw-semibold text-dark">{{ $bills->firstItem() }}</span> - <span class="fw-semibold text-dark">{{ $bills->lastItem() }}</span> dari <span class="fw-semibold text-dark">{{ $bills->total() }}</span> tagihan
                </div>
                <div>
                    {{ $bills->links() }}
                </div>
            </div>
        @endif
    </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const monthEl = document.getElementById('filter_month');
        const yearEl = document.getElementById('filter_year');
        const startEl = document.getElementById('filter_start_date');
        const endEl = document.getElementById('filter_end_date');

        function clearMonthYear() {
            if (startEl.value || endEl.value) {
                monthEl.value = '';
                yearEl.value = '';
            }
        }

        function clearDateRange() {
            if (monthEl.value || yearEl.value) {
                startEl.value = '';
                endEl.value = '';
            }
        }

        if (startEl && endEl && monthEl && yearEl) {
            startEl.addEventListener('change', clearMonthYear);
            endEl.addEventListener('change', clearMonthYear);
            monthEl.addEventListener('change', clearDateRange);
            yearEl.addEventListener('change', clearDateRange);
        }

        // Navigasi klik baris tabel (bersih tanpa inline onclick)
        document.querySelectorAll('.bill-row').forEach(row => {
            row.addEventListener('click', function(e) {
                if (!e.target.closest('.dropdown') && !e.target.closest('a') && !e.target.closest('button')) {
                    window.location.href = this.dataset.href;
                }
            });
        });

        // Notifikasi Session Sukses & Error (Aman dari linter JS VS Code)
        const successMessage = @json(session('success'));
        const errorMessage = @json(session('error'));

        if (successMessage) {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
            Toast.fire({ icon: 'success', title: successMessage });
        }

        if (errorMessage) {
            Swal.fire({
                icon: 'error',
                title: 'Aksi Ditolak!',
                text: errorMessage,
                customClass: { popup: 'rounded-4' }
            });
        }
    });

    // Konfirmasi Hapus Data
    document.addEventListener('click', function (e) {
        if (e.target.closest('.btn-delete')) {
            const button = e.target.closest('.btn-delete');
            const form = button.closest('.form-delete');

            Swal.fire({
                title: 'Hapus Tagihan?',
                text: "Data tagihan, rincian item, dan lampiran dokumen akan dihapus permanen!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Hapus Permanen',
                cancelButtonText: 'Batal',
                reverseButtons: true,
                customClass: { popup: 'rounded-4' }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Menghapus Data...',
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading() }
                    });
                    form.submit();
                }
            });
        }
    });
</script>
@endpush
