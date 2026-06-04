@extends('layouts.app')

@push('css')
<style>
    /* Transisi halus saat pindah tab */
    .transition-all { transition: all 0.3s ease; }

    /* Efek tombol naik sedikit saat di-hover */
    .btn-hover-lift { transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.2s; }
    .btn-hover-lift:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(0,0,0,.12)!important;
    }

    /* Statistik Card Highlight */
    .stat-card {
        border-radius: 16px;
        border: none;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        background: linear-gradient(145deg, #ffffff 0%, #f8f9fa 100%);
    }
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.08) !important;
    }

    /* Modifikasi tabel agar terlihat modern seperti SaaS */
    .po-custom-table {
        border-collapse: separate;
        border-spacing: 0 8px;
        margin-top: -8px;
    }
    .po-custom-table thead th {
        border-bottom: none;
        background-color: transparent !important;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 1px;
        color: #adb5bd;
        padding-bottom: 15px;
    }
    .po-custom-table tbody tr {
        background-color: #ffffff;
        box-shadow: 0 2px 6px rgba(0,0,0,0.02);
        border-radius: 12px;
        transition: all 0.2s ease;
    }
    .po-custom-table tbody tr:hover {
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        transform: scale(1.002);
        z-index: 10;
        position: relative;
    }
    .po-custom-table td {
        vertical-align: middle;
        border-top: 1px solid #f1f3f5;
        border-bottom: 1px solid #f1f3f5;
    }
    .po-custom-table td:first-child {
        border-left: 1px solid #f1f3f5;
        border-top-left-radius: 12px;
        border-bottom-left-radius: 12px;
    }
    .po-custom-table td:last-child {
        border-right: 1px solid #f1f3f5;
        border-top-right-radius: 12px;
        border-bottom-right-radius: 12px;
    }

    /* 🔥 ISOLASI TOTAL: CUSTOM TAB BUTTONS 🔥 */
    .po-tab-container {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-bottom: 1.5rem;
    }
    .po-tab-btn {
        border-radius: 50px;
        padding: 0.6rem 1.5rem;
        font-weight: 700;
        color: #6c757d;
        background-color: #f8f9fa;
        border: 1px solid #e9ecef;
        transition: all 0.3s ease;
        cursor: pointer;
        font-size: 1rem;
    }
    .po-tab-btn:hover {
        background-color: #e9ecef;
    }
    .po-tab-btn.active {
        background-color: #0d6efd;
        color: white;
        border-color: #0d6efd;
        box-shadow: 0 4px 10px rgba(13, 110, 253, 0.3);
    }
    
    /* Search Bar Modern */
    .po-search-box {
        background: #fff;
        border: 2px solid #e9ecef;
        border-radius: 50px;
        padding: 4px;
        transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }
    .po-search-box:focus-within {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
    }
</style>
@endpush

@section('content')
<div class="container pb-5 text-dark">

    {{-- LOGIKA SMART UX: Tentukan tab mana yang harus aktif --}}
    @php
        $activeTab = 'pr'; // Default aktif
        if(request('search') && $readyPrs->isEmpty() && $purchaseOrders->isNotEmpty()) {
            $activeTab = 'po';
        }
    @endphp

    {{-- HEADER HALAMAN & PENCARIAN --}}
    <div class="mb-4 gap-3 d-flex flex-column flex-lg-row justify-content-between align-items-lg-center">
        <div>
            <h3 class="mb-1 fw-bolder text-dark" style="letter-spacing: -0.5px;">
                <span class="text-primary me-2"><i class="bi bi-cart-check-fill"></i></span>Kelola Purchase Order
            </h3>
            <div class="text-muted small fw-medium">Pusat kendali konversi PR menjadi dokumen pesanan (PO) resmi ke vendor.</div>
        </div>

        {{-- FITUR PENCARIAN --}}
        <form action="{{ route('po.index') }}" method="GET" class="m-0">
            <div class="po-search-box d-flex align-items-center shadow-sm" style="min-width: 350px;">
                <span class="ps-3 pe-2 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" name="search" class="form-control border-0 shadow-none bg-transparent fw-medium" placeholder="Cari PR, PO, Vendor, PT..." value="{{ request('search') }}">
                @if(request('search'))
                    <a href="{{ route('po.index') }}" class="btn btn-link text-danger p-1 me-1 text-decoration-none" title="Reset Pencarian">
                        <i class="bi bi-x-circle-fill fs-5"></i>
                    </a>
                @endif
                <button class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" type="submit">Cari</button>
            </div>
        </form>
    </div>

    {{-- QUICK STATS CARD --}}
    <div class="row g-4 mb-4">
        <div class="col-md-6 col-lg-4">
            <div class="card stat-card shadow-sm h-100">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 56px; height: 56px;">
                        <i class="bi bi-inbox-fill fs-3"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-bold text-uppercase mb-1">Antrean PR Siap PO</div>
                        <h3 class="mb-0 fw-bolder text-dark">{{ $readyPrs->total() ?? $readyPrs->count() }} <span class="fs-6 text-muted fw-normal">Dokumen</span></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="card stat-card shadow-sm h-100">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="bg-success bg-opacity-10 text-success rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 56px; height: 56px;">
                        <i class="bi bi-check-circle-fill fs-3"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-bold text-uppercase mb-1">Total PO Terbit (All)</div>
                        <h3 class="mb-0 fw-bolder text-dark">{{ \App\Models\PurchaseOrder::count() }} <span class="fs-6 text-muted fw-normal">Dokumen</span></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ALERT NOTIFIKASI ERROR --}}
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm border-0 d-flex align-items-center mb-4">
            <i class="bi bi-exclamation-triangle-fill fs-5 me-3 text-danger"></i>
            <div class="fw-medium">{{ session('error') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm border-0 d-flex align-items-center mb-4">
            <i class="bi bi-check-circle-fill fs-5 me-3 text-success"></i>
            <div class="fw-medium">{{ session('success') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- 🔥 TABS NAVIGATION (TERISOLASI 100% DARI NAVBAR UTAMA) 🔥 --}}
    <div class="po-tab-container" id="poTabs" role="tablist">
        <button class="po-tab-btn {{ $activeTab == 'pr' ? 'active' : '' }}" id="pr-ready-tab" data-bs-toggle="pill" data-bs-target="#pr-ready" type="button" role="tab" aria-controls="pr-ready" aria-selected="{{ $activeTab == 'pr' ? 'true' : 'false' }}">
            <i class="bi bi-inbox me-1"></i> Antrean PR Siap Proses
            @if($readyPrs->count() > 0)
                <span class="badge {{ $activeTab == 'pr' ? 'bg-white text-primary' : 'bg-secondary text-white' }} rounded-pill ms-2 shadow-sm">{{ $readyPrs->total() ?? $readyPrs->count() }}</span>
            @endif
        </button>
        
        <button class="po-tab-btn {{ $activeTab == 'po' ? 'active' : '' }}" id="po-history-tab" data-bs-toggle="pill" data-bs-target="#po-history" type="button" role="tab" aria-controls="po-history" aria-selected="{{ $activeTab == 'po' ? 'true' : 'false' }}">
            <i class="bi bi-clock-history me-1"></i> Riwayat & Draft PO
            @if($purchaseOrders->count() > 0 && request('search'))
                <span class="badge {{ $activeTab == 'po' ? 'bg-white text-primary' : 'bg-secondary text-white' }} rounded-pill ms-2 shadow-sm">{{ $purchaseOrders->total() ?? $purchaseOrders->count() }}</span>
            @endif
        </button>
    </div>

    {{-- TAB CONTENT --}}
    <div class="tab-content" id="poTabsContent">

        {{-- ======================================================== --}}
        {{-- TAB 1: DAFTAR PR SIAP DIPROSES                           --}}
        {{-- ======================================================== --}}
        <div class="tab-pane fade {{ $activeTab == 'pr' ? 'show active' : '' }}" id="pr-ready" role="tabpanel" aria-labelledby="pr-ready-tab">
            @if($readyPrs->count() > 0)
                <div class="table-responsive px-1 pb-3">
                    <table class="table align-middle po-custom-table w-100">
                        <thead>
                            <tr>
                                <th class="ps-4" width="22%">No. Dokumen PR</th>
                                <th width="23%">PT Penanggung / Dept</th>
                                <th width="15%">Disetujui Pada</th>
                                <th width="20%">Target Selesai</th>
                                <th class="text-center pe-4" width="20%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($readyPrs as $index => $pr)
                                @php
                                    $needDate = \Carbon\Carbon::parse($pr->need_date);
                                    $isUrgent = $needDate->isPast() || $needDate->diffInDays(now()) <= 3;
                                @endphp
                                <tr class="{{ $isUrgent ? 'border-start border-4 border-danger' : 'border-start border-4 border-primary' }}">
                                    <td class="py-3 ps-4">
                                        <div class="fw-bolder text-dark fs-6">{{ $pr->pr_number }}</div>
                                        <div class="small text-muted mt-1"><i class="bi bi-person-circle me-1"></i>{{ $pr->user->name ?? 'System' }}</div>
                                    </td>
                                    <td class="py-3">
                                        <div class="fw-bold text-primary small">
                                            <i class="bi bi-building me-1"></i>{{ optional($pr->company)->name ?? 'Head Office' }}
                                        </div>
                                        <div class="text-muted mt-1 fw-medium" style="font-size: 0.75rem;">
                                            <i class="bi bi-diagram-2 me-1"></i>{{ optional($pr->department)->name ?? 'Umum' }}
                                        </div>
                                    </td>
                                    <td class="py-3 small text-muted fw-medium">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-success rounded-circle me-2" style="width: 8px; height: 8px;"></div>
                                            {{ \Carbon\Carbon::parse($pr->updated_at)->format('d M Y') }}
                                        </div>
                                    </td>
                                    <td class="py-3 small">
                                        <span class="{{ $isUrgent ? 'text-danger fw-bolder' : 'text-dark fw-bold' }}">
                                            <i class="bi bi-calendar-event me-1"></i>{{ $needDate->format('d M Y') }}
                                        </span>
                                        @if($isUrgent)
                                            <div class="mt-1"><span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2" style="font-size: 0.65rem;">Mendesak</span></div>
                                        @endif
                                    </td>
                                    <td class="py-3 pe-4 text-center">
                                        <a href="{{ route('po.process_pr', $pr->pr_number) }}" class="btn btn-primary rounded-pill fw-bold shadow-sm px-4 btn-hover-lift">
                                            <i class="bi bi-cart-plus-fill me-1"></i> Buat PO
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                @if($readyPrs->hasPages())
                    <div class="d-flex justify-content-center mt-4">
                        {{ $readyPrs->appends(request()->query())->links() }}
                    </div>
                @endif
            @else
                {{-- EMPTY STATE PR --}}
                <div class="card border-0 shadow-sm rounded-4 text-center py-5">
                    <div class="card-body py-5">
                        <img src="https://cdn-icons-png.flaticon.com/512/7486/7486744.png" alt="Empty" width="120" class="mb-4 opacity-50" style="filter: grayscale(100%);">
                        @if(request('search'))
                            <h4 class="fw-bolder text-dark">Pencarian Tidak Ditemukan</h4>
                            <p class="text-muted mb-4">Tidak ada PR yang cocok dengan pencarian "<b>{{ request('search') }}</b>".<br>Silakan coba kata kunci lain atau cek tab Riwayat PO.</p>
                            <a href="{{ route('po.index') }}" class="btn btn-outline-primary rounded-pill px-4 fw-bold btn-hover-lift">Reset Pencarian</a>
                        @else
                            <h4 class="fw-bolder text-dark">Antrean Bersih!</h4>
                            <p class="text-muted mb-0">Hore! Belum ada Purchase Request baru yang menunggu untuk diproses menjadi PO saat ini.</p>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        {{-- ======================================================== --}}
        {{-- TAB 2: DAFTAR RIWAYAT PO YANG SUDAH TERBIT               --}}
        {{-- ======================================================== --}}
        <div class="tab-pane fade {{ $activeTab == 'po' ? 'show active' : '' }}" id="po-history" role="tabpanel" aria-labelledby="po-history-tab">
            @if($purchaseOrders->count() > 0)
                <div class="table-responsive px-1 pb-3">
                    <table class="table align-middle po-custom-table w-100">
                        <thead>
                            <tr>
                                <th class="ps-4" width="22%">No. PO & Ref PR</th>
                                <th width="20%">PT Penanggung</th>
                                <th width="20%">Vendor Tujuan</th>
                                <th class="text-center" width="15%">Status PO</th>
                                <th class="text-end" width="15%">Total Nominal</th>
                                <th class="text-center pe-4" width="8%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($purchaseOrders as $po)
                                @php
                                    $borderColor = 'border-secondary';
                                    $statusSlug = strtolower(optional($po->status)->slug);
                                    if(in_array($statusSlug, ['issued', 'approved'])) $borderColor = 'border-success';
                                    if(in_array($statusSlug, ['draft', 'pending_approval'])) $borderColor = 'border-warning';
                                    if(in_array($statusSlug, ['rejected', 'canceled'])) $borderColor = 'border-danger';
                                @endphp
                                <tr class="border-start border-4 {{ $borderColor }}">
                                    <td class="py-3 ps-4">
                                        <div class="fw-bolder text-dark fs-6">{{ $po->po_number }}</div>
                                        <div class="text-muted mt-1 fw-medium d-flex align-items-center" style="font-size: 0.75rem;">
                                            <i class="bi bi-link-45deg me-1"></i> {{ $po->purchaseRequest->pr_number ?? 'PR Ref Tdk Ditemukan' }}
                                        </div>
                                    </td>
                                    <td class="py-3">
                                        <div class="fw-bold text-primary small">
                                            <i class="bi bi-building me-1"></i>
                                            {{ optional($po->company)->name ?? optional(optional($po->purchaseRequest)->company)->name ?? 'Head Office' }}
                                        </div>
                                    </td>
                                    <td class="py-3 small fw-bolder text-secondary">
                                        <i class="bi bi-shop me-1 text-muted"></i> {{ $po->vendor->name ?? 'Vendor Dihapus' }}
                                    </td>
                                    <td class="py-3 text-center">
                                        @if($po->status)
                                            <span class="badge bg-{{ $po->status->color }}-subtle text-{{ $po->status->color }} border border-{{ $po->status->color }}-subtle rounded-pill px-3 py-2 fw-bolder shadow-sm">
                                                <i class="bi bi-circle-fill me-1" style="font-size: 0.4rem; vertical-align: middle;"></i> {{ mb_strtoupper($po->status->name) }}
                                            </span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-3 py-2 fw-bolder shadow-sm">DRAFT</span>
                                        @endif
                                    </td>
                                    <td class="py-3 text-end fw-bolder text-dark fs-6">
                                        <span class="text-muted small me-1 fw-normal">{{ $po->currency ?? 'IDR' }}</span>
                                        {{ number_format($po->grand_total, 0, ',', '.') }}
                                    </td>
                                    <td class="py-3 pe-4 text-center">
                                        <a href="{{ route('po.show', $po->po_number) }}" class="btn btn-light bg-white border shadow-sm rounded-circle btn-hover-lift d-flex align-items-center justify-content-center mx-auto" style="width: 38px; height: 38px;" title="Lihat Detail">
                                            <i class="bi bi-chevron-right text-primary fw-bolder"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                @if($purchaseOrders->hasPages())
                    <div class="d-flex justify-content-center mt-4">
                        {{ $purchaseOrders->appends(request()->query())->links() }}
                    </div>
                @endif
            @else
                {{-- EMPTY STATE PO --}}
                <div class="card border-0 shadow-sm rounded-4 text-center py-5">
                    <div class="card-body py-5">
                        <img src="https://cdn-icons-png.flaticon.com/512/2855/2855562.png" alt="Empty" width="120" class="mb-4 opacity-50" style="filter: grayscale(100%);">
                        @if(request('search'))
                            <h4 class="fw-bolder text-dark">Pencarian Tidak Ditemukan</h4>
                            <p class="text-muted mb-4">Tidak ada dokumen PO yang cocok dengan pencarian "<b>{{ request('search') }}</b>".</p>
                            <a href="{{ route('po.index') }}" class="btn btn-outline-primary rounded-pill px-4 fw-bold btn-hover-lift">Reset Pencarian</a>
                        @else
                            <h4 class="fw-bolder text-dark">Riwayat PO Kosong</h4>
                            <p class="text-muted mb-0">Belum ada Purchase Order yang diterbitkan sejauh ini. Silakan proses PR di tab sebelah untuk membuat PO baru.</p>
                        @endif
                    </div>
                </div>
            @endif
        </div>

    </div>
</div>
@endsection