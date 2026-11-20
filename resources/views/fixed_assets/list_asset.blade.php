@extends('layouts.app')

@push('css')
<style>
    .card-table-wrapper {
        border-radius: 16px;
        overflow: hidden;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        background: #fff;
    }
    .table-modern { margin-bottom: 0; }
    .table-modern thead th {
        background-color: #f8fafc;
        color: #475569;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        padding: 0.95rem 0.75rem;
        border-bottom: 2px solid #e2e8f0;
        font-weight: 800;
        border-top: none;
        white-space: nowrap;
    }
    .table-modern tbody td {
        padding: 0.9rem 0.75rem;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
        font-size: 0.82rem;
    }
    .table-modern tbody tr { transition: all 0.2s ease; background-color: #ffffff; }
    .table-modern tbody tr:hover { background-color: #f8fafc !important; }

    .price-current {
        font-size: 0.92rem;
        font-weight: 800;
        color: #15803d;
    }
</style>
@endpush

@section('content')
<div class="pb-5 container-fluid px-md-4">

    {{-- 1. HEADER --}}
    <div class="mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h3 class="mb-0 fw-bold text-dark"><i class="bi bi-hdd-network text-primary me-2"></i>Laporan Induk Aset</h3>
            <p class="mt-1 mb-0 text-muted small">
                Manajemen inventaris IT, riwayat pengguna, departemen, dan nilai perolehan
                @if(request('company_id'))
                    — Filter Entitas: <strong class="text-primary">{{ optional($companies->firstWhere('id', request('company_id')))->name }}</strong>
                @endif
            </p>
        </div>
        <div class="gap-2 mt-3 mt-md-0 d-flex">
            <a href="{{ route('fixed-assets.master_list_export', request()->all()) }}" class="border shadow-sm btn btn-light fw-bold text-success">
                <i class="bi bi-file-earmark-excel-fill me-1"></i> Export Excel
            </a>
            <a href="{{ route('fixed-assets.index') }}" class="shadow-sm btn btn-primary fw-bold">
                <i class="bi bi-plus-circle me-1"></i> Register Aset
            </a>
        </div>
    </div>

    {{-- 2. KPI / SUMMARY CARDS --}}
    <div class="mb-4 row g-3">
        <div class="col-md-2">
            <div class="border-0 border-4 shadow-sm card border-start border-primary h-100 rounded-4">
                <div class="card-body">
                    <div class="mb-1 text-muted small fw-bold text-uppercase">Total Aset</div>
                    <div class="fs-3 fw-bold text-dark">{{ number_format($totalAssets, 0, ',', '.') }} <span class="fs-6 text-muted fw-normal">Unit</span></div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="border-0 border-4 shadow-sm card border-start border-success h-100 rounded-4">
                <div class="card-body">
                    <div class="mb-1 text-muted small fw-bold text-uppercase">Digunakan</div>
                    <div class="fs-3 fw-bold text-success">{{ number_format($inUse, 0, ',', '.') }} <span class="fs-6 text-muted fw-normal">Unit</span></div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="border-0 border-4 shadow-sm card border-start border-warning h-100 rounded-4">
                <div class="card-body">
                    <div class="mb-1 text-muted small fw-bold text-uppercase">Di Gudang</div>
                    <div class="fs-3 fw-bold text-warning">{{ number_format($inWarehouse, 0, ',', '.') }} <span class="fs-6 text-muted fw-normal">Unit</span></div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="border-0 border-4 shadow-sm card border-start border-info h-100 rounded-4 bg-info bg-opacity-10">
                <div class="card-body">
                    <div class="mb-2 text-dark small fw-bold text-uppercase">
                        <i class="bi bi-cash-stack me-1 text-info"></i> Ringkasan Nilai Perolehan & Nilai Buku Saat Ini (Per Mata Uang)
                    </div>
                    <div class="row g-2">
                        @forelse($currencyTotals as $currCode => $totals)
                            @php
                                $symbol   = $totals['symbol'] ?? ($currCode === 'IDR' ? 'Rp' : $currCode);
                                $decimals = ($currCode === 'IDR') ? 0 : 2;
                            @endphp
                            <div class="col-sm-6">
                                <div class="p-2 bg-white border rounded-3 border-info-subtle h-100">
                                    <div class="mb-1 d-flex justify-content-between align-items-center">
                                        <span class="badge bg-dark">{{ $currCode }}</span>
                                        <span class="text-muted fw-semibold" style="font-size: 0.7rem;">Total Keseluruhan</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-baseline" style="font-size: 0.76rem;">
                                        <span class="text-muted">Nilai Perolehan Awal:</span>
                                        <span class="fw-bold text-dark">{{ $symbol }} {{ number_format($totals['purchase'], $decimals, ',', '.') }}</span>
                                    </div>
                                    <div class="pt-1 mt-1 border-top d-flex justify-content-between align-items-baseline" style="font-size: 0.78rem;">
                                        <span class="text-muted">Nilai Buku Saat Ini:</span>
                                        <span class="fw-bold text-success">{{ $symbol }} {{ number_format($totals['book'], $decimals, ',', '.') }}</span>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-12 text-muted small">Belum ada nilai perolehan.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 3. FILTER PENCARIAN DINAMIS --}}
    <div class="mb-4 border-0 shadow-sm card rounded-4">
        <div class="p-3 border card-body bg-light rounded-4 border-secondary-subtle">
            <form action="{{ route('fixed-assets.master_list') }}" method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-dark">Perusahaan (Entitas PT)</label>
                    <select name="company_id" class="form-select border-secondary-subtle">
                        <option value="">Semua Perusahaan</option>
                        @foreach($companies as $comp)
                            <option value="{{ $comp->id }}" {{ request('company_id') == $comp->id ? 'selected' : '' }}>
                                {{ $comp->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-dark">Status Keberadaan</label>
                    <select name="status" class="form-select border-secondary-subtle">
                        <option value="">Semua Status</option>
                        <option value="in_use" {{ request('status') == 'in_use' ? 'selected' : '' }}>Digunakan Staff</option>
                        <option value="in_warehouse" {{ request('status') == 'in_warehouse' ? 'selected' : '' }}>Tersedia (Gudang)</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label small fw-bold text-dark">Pencarian Universal</label>
                    <div class="input-group">
                        <span class="bg-white input-group-text border-secondary-subtle text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" value="{{ request('search') }}" class="form-control border-secondary-subtle" placeholder="Cari No Aset, No Akuntansi, S/N, Kode/Nama Item, Spesifikasi, Karyawan...">
                    </div>
                </div>
                <div class="gap-1 col-md-2 d-flex">
                    <button type="submit" class="btn btn-dark w-100 fw-bold">Cari</button>
                    @if(request()->hasAny(['company_id', 'status', 'search']))
                        <a href="{{ route('fixed-assets.master_list') }}" class="border btn btn-outline-secondary fw-bold" title="Reset Filter">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- 4. TABEL DATA MODERN (KOLOM TERPISAH) --}}
    <div class="mb-4 border-4 card-table-wrapper border-top border-primary">
        <div class="p-0 table-responsive">
            <table class="table align-middle table-modern">
                <thead>
                    <tr>
                        <th class="text-center ps-3">No</th>
                        <th>No. Aset Otomatis</th>
                        <th>No. Aset Akuntansi</th>
                        <th>Serial Number (S/N)</th>
                        <th>Kode Master Item</th>
                        <th>Nama Item</th>
                        <th>Spesifikasi Aset</th>
                        <th>Perusahaan (PT)</th>
                        <th>Lokasi / Pemegang</th>
                        <th>Status & Tgl Beli</th>
                        <th>Catatan</th>
                        <th class="text-end pe-3">Nilai Perolehan & Saat Ini</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($assets as $index => $asset)
                        @php
                            $masterItemCode = optional($asset->item)->code ?? '-';
                            $masterItemName = optional($asset->item)->name ?? '-';
                            $companyName    = optional($asset->company)->name ?? '-';

                            $currObj = is_object($asset->currency)
                                ? $asset->currency
                                : (is_string($asset->currency) && str_starts_with(trim($asset->currency), '{') ? json_decode($asset->currency) : null);

                            $rowCurr      = strtoupper($currObj->code ?? (is_string($asset->currency) && !str_starts_with(trim($asset->currency), '{') ? $asset->currency : 'IDR'));
                            $currSymbol   = $currObj->symbol ?? ($rowCurr === 'IDR' ? 'Rp' : $rowCurr);
                            $currDecimals = ($rowCurr === 'IDR') ? 0 : 2;
                        @endphp
                        <tr>
                            <td class="text-center ps-3 text-muted fw-bold">{{ $assets->firstItem() + $index }}</td>

                            {{-- 1. NO ASET OTOMATIS --}}
                            <td class="text-nowrap">
                                <span class="fw-bold text-primary font-monospace">{{ $asset->asset_number ?? '-' }}</span>
                            </td>

                            {{-- 2. NO ASET AKUNTANSI --}}
                            <td class="text-nowrap">
                                <span class="font-monospace text-dark fw-semibold">{{ $asset->accounting_asset_number ?: '-' }}</span>
                            </td>

                            {{-- 3. SERIAL NUMBER --}}
                            <td class="text-nowrap">
                                <span class="font-monospace text-secondary">{{ $asset->serial_number ?: '-' }}</span>
                            </td>

                            {{-- 4. KODE MASTER ITEM --}}
                            <td class="text-nowrap">
                                <span class="border badge bg-light text-primary border-primary-subtle font-monospace">
                                    {{ $masterItemCode }}
                                </span>
                            </td>

                            {{-- 5. NAMA ITEM MASTER --}}
                            <td>
                                <div class="fw-semibold text-dark" style="min-width: 140px; max-width: 190px;">
                                    {{ $masterItemName }}
                                </div>
                            </td>

                            {{-- 6. NAMA BARANG / SPESIFIKASI --}}
                            <td>
                                <div class="fw-bold text-dark" style="min-width: 210px; max-width: 260px;">
                                    {{ $asset->name ?? '-' }}
                                </div>
                                @if(!empty($asset->spesifikasi_detail) && $asset->spesifikasi_detail !== '-')
                                    <div class="mt-1 text-secondary" style="min-width: 210px; max-width: 260px; font-size: 0.74rem; line-height: 1.3;">
                                        {{ Str::limit(strip_tags($asset->spesifikasi_detail), 65) }}
                                    </div>
                                @endif
                            </td>

                            {{-- 7. NAMA PERUSAHAAN --}}
                            <td>
                                <span class="border badge bg-light text-dark border-secondary-subtle text-wrap text-start" style="min-width: 140px; font-size: 0.72rem;">
                                    <i class="bi bi-building text-primary me-1"></i>{{ $companyName }}
                                </span>
                            </td>

                            {{-- 8. LOKASI / PEMEGANG --}}
                            <td>
                                @if(empty($asset->assigned_to))
                                    <div class="fw-bold text-warning text-nowrap">
                                        <i class="bi bi-shop me-1"></i> {{ optional($asset->warehouse)->name ?? 'Gudang Utama' }}
                                    </div>
                                    <div class="text-muted" style="font-size: 0.72rem;">(Tersedia)</div>
                                @else
                                    <div class="fw-bold text-primary text-nowrap">
                                        <i class="bi bi-person-badge me-1"></i> {{ optional($asset->assignee)->name ?? 'Unknown' }}
                                    </div>
                                    <div class="text-dark" style="font-size: 0.72rem;">
                                        {{ optional(optional($asset->assignee)->department)->name ?? '-' }}
                                    </div>
                                @endif
                            </td>

                            {{-- 9. STATUS & TGL BELI --}}
                            <td class="text-nowrap">
                                @php
                                    $statusName = optional($asset->status)->name ?? 'Normal';
                                    $statusLower = strtolower($statusName);
                                    if(str_contains($statusLower, 'use') || str_contains($statusLower, 'pakai')) {
                                        $badgeClass = 'bg-success-subtle text-success border border-success-subtle';
                                    } elseif(str_contains($statusLower, 'rusak') || str_contains($statusLower, 'hilang')) {
                                        $badgeClass = 'bg-danger text-white shadow-sm';
                                    } elseif(str_contains($statusLower, 'available') || str_contains($statusLower, 'gudang')) {
                                        $badgeClass = 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
                                    } else {
                                        $badgeClass = 'bg-secondary-subtle text-secondary';
                                    }
                                @endphp
                                <span class="badge {{ $badgeClass }} px-2 py-1 mb-1" style="font-size: 0.7rem;">
                                    {{ $statusName }}
                                </span>
                                <div class="text-secondary" style="font-size: 0.73rem;">
                                    <i class="bi bi-calendar3 me-1"></i> {{ $asset->acquisition_date ? \Carbon\Carbon::parse($asset->acquisition_date)->format('d M Y') : '-' }}
                                </div>
                            </td>

                            {{-- 10. CATATAN --}}
                            <td>
                                <div class="text-muted fst-italic" style="min-width: 100px; max-width: 140px; font-size: 0.74rem; line-height: 1.3;">
                                    @if(!empty($asset->notes))
                                        {{ Str::limit($asset->notes, 35) }}
                                    @else
                                        -
                                    @endif
                                </div>
                            </td>

                            {{-- 11. NILAI PEROLEHAN AWAL & NILAI BUKU SAAT INI --}}
                            <td class="text-end pe-3 text-nowrap">
                                <div class="mb-1">
                                    <span class="badge {{ $rowCurr === 'IDR' ? 'bg-secondary-subtle text-dark' : 'bg-warning-subtle text-dark border border-warning' }}" style="font-size: 0.65rem;">
                                        {{ $rowCurr }}
                                    </span>
                                </div>
                                <div class="text-muted" style="font-size: 0.72rem;">
                                    Perolehan: <span class="text-decoration-line-through">{{ $currSymbol }} {{ number_format($asset->purchase_price ?? 0, $currDecimals, ',', '.') }}</span>
                                </div>
                                <div class="price-current">
                                    <span class="text-muted fw-normal" style="font-size: 0.72rem;">Saat Ini:</span>
                                    {{ $currSymbol }} {{ number_format($asset->net_book_value ?? $asset->purchase_price ?? 0, $currDecimals, ',', '.') }}
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="py-5 text-center text-muted">
                                <i class="mb-3 opacity-50 bi bi-inbox fs-1 d-block"></i>
                                <h6 class="fw-bold text-dark">Data Aset Tidak Ditemukan.</h6>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- 5. PAGINASI --}}
        @if($assets->hasPages())
        <div class="px-4 py-3 bg-white card-footer border-top d-flex flex-column flex-md-row justify-content-between align-items-center">
            <span class="mb-2 text-muted small mb-md-0 fw-medium">
                Menampilkan {{ $assets->firstItem() ?? 0 }} - {{ $assets->lastItem() ?? 0 }} dari {{ $assets->total() }} total aset
            </span>
            <nav>
                {{ $assets->links('pagination::bootstrap-5') }}
            </nav>
        </div>
        @endif
    </div>
</div>
@endsection