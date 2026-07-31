@extends('layouts.app')

@push('css')
<style>
    /* 🔥 KUSTOMISASI TABEL SAAS MODERN 🔥 */
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
        color: #475569; /* Warna lebih gelap agar tajam */
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 1.2rem 1rem;
        border-bottom: 2px solid #e2e8f0;
        font-weight: 800;
        border-top: none;
    }
    .table-modern tbody td {
        padding: 1.2rem 1rem;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
        font-size: 0.85rem; /* Ukuran standar mudah dibaca */
    }
    .table-modern tbody tr { transition: all 0.2s ease; background-color: #ffffff; }
    .table-modern tbody tr:hover { background-color: #f8fafc !important; }

    /* Info Badge Kategori */
    .badge-category {
        font-size: 0.65rem;
        letter-spacing: 0.5px;
        padding: 0.35rem 0.65rem;
        border-radius: 6px;
    }

    /* Nilai Mata Uang */
    .price-strike {
        font-size: 0.75rem;
        color: #94a3b8;
        text-decoration: line-through;
        font-weight: 500;
    }
    .price-current {
        font-size: 1.05rem;
        font-weight: 800;
        color: #15803d; /* Hijau solid yang tajam */
    }
</style>
@endpush

@section('content')
{{-- 🔥 FULL WIDTH CONTAINER 🔥 --}}
<div class="pb-5 container-fluid px-md-4">

    {{-- 1. HEADER --}}
    <div class="mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h3 class="mb-0 fw-bold text-dark"><i class="bi bi-hdd-network text-primary me-2"></i>Laporan Induk Aset</h3>
            <p class="mt-1 mb-0 text-muted small">Manajemen inventaris IT, riwayat pengguna, departemen, dan nilai perolehan.</p>
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
        <div class="col-md-3">
            <div class="border-0 border-4 shadow-sm card border-start border-primary h-100 rounded-4">
                <div class="card-body">
                    <div class="mb-1 text-muted small fw-bold text-uppercase">Total Seluruh Aset</div>
                    <div class="fs-3 fw-bold text-dark">{{ number_format($totalAssets, 0, ',', '.') }} <span class="fs-6 text-muted fw-normal">Unit</span></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="border-0 border-4 shadow-sm card border-start border-success h-100 rounded-4">
                <div class="card-body">
                    <div class="mb-1 text-muted small fw-bold text-uppercase">Digunakan (Assigned)</div>
                    <div class="fs-3 fw-bold text-success">{{ number_format($inUse, 0, ',', '.') }} <span class="fs-6 text-muted fw-normal">Unit</span></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="border-0 border-4 shadow-sm card border-start border-warning h-100 rounded-4">
                <div class="card-body">
                    <div class="mb-1 text-muted small fw-bold text-uppercase">Di Gudang (Available)</div>
                    <div class="fs-3 fw-bold text-warning">{{ number_format($inWarehouse, 0, ',', '.') }} <span class="fs-6 text-muted fw-normal">Unit</span></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="border-0 border-4 shadow-sm card border-start border-info h-100 rounded-4 bg-info bg-opacity-10">
                <div class="card-body">
                    <div class="mb-1 text-dark small fw-bold text-uppercase">Total Nilai Perolehan</div>
                    <div class="fs-4 fw-bold text-dark">Rp {{ number_format($totalValue, 0, ',', '.') }}</div>

                    {{-- Nilai Buku yang ditampilkan di sini dihitung dari aset di halaman ini saja demi performa --}}
                    <div class="pt-2 mt-2 border-opacity-25 border-top border-info">
                        <div class="text-dark small fw-medium">Nilai Buku (Halaman Ini):</div>
                        <div class="fs-6 fw-bold text-danger">Rp {{ number_format($totalCurrentValue, 0, ',', '.') }}</div>
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
                    <label class="form-label small fw-bold text-dark">Status Keberadaan Aset</label>
                    <select name="status" class="form-select border-secondary-subtle">
                        <option value="">Semua Status</option>
                        <option value="in_use" {{ request('status') == 'in_use' ? 'selected' : '' }}>Digunakan Staff</option>
                        <option value="in_warehouse" {{ request('status') == 'in_warehouse' ? 'selected' : '' }}>Tersedia (Gudang)</option>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label small fw-bold text-dark">Pencarian Universal</label>
                    <div class="input-group">
                        <span class="bg-white input-group-text border-secondary-subtle text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" value="{{ request('search') }}" class="form-control border-secondary-subtle" placeholder="Cari No Aset, Nama Barang, S/N, atau Nama Karyawan...">
                    </div>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-dark w-100 fw-bold">Cari</button>
                </div>
            </form>
        </div>
    </div>

    {{-- 4. TABEL DATA MODERN --}}
    <div class="mb-4 border-4 card-table-wrapper border-top border-primary">
        <div class="p-0 table-responsive">
            <table class="table align-middle table-modern text-nowrap">
                <thead>
                    <tr>
                        <th class="text-center ps-3" width="5%">No</th>
                        <th width="15%">Identitas Aset</th>
                        <th width="10%">Kategori Master</th>
                        <th width="20%">Spesifikasi Barang</th>
                        <th width="15%">Lokasi / Pemegang</th>
                        <th width="12%">Status Aset</th>
                        <th width="10%">Catatan</th>
                        <th width="13%" class="text-end pe-4">Nilai Buku (IDR)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($assets as $index => $asset)
                        @php
                            $subCategoryName = optional(optional($asset->item)->category)->name ?? '';
                            $subCategoryCode = optional(optional($asset->item)->category)->code ?? '';

                            if(empty($subCategoryName)) {
                                $namaBarangLower = strtolower($asset->name);
                                if(str_contains($namaBarangLower, 'laptop') || str_contains($namaBarangLower, 'macbook')) {
                                    $subCategoryName = 'Laptops'; $subCategoryCode = 'LAP';
                                } elseif(str_contains($namaBarangLower, 'pc') || str_contains($namaBarangLower, 'desktop') || str_contains($namaBarangLower, 'imac')) {
                                    $subCategoryName = 'Elektronik & IT'; $subCategoryCode = 'ELK';
                                } elseif(str_contains($namaBarangLower, 'iphone') || str_contains($namaBarangLower, 'phone') || str_contains($namaBarangLower, 'hp')) {
                                    $subCategoryName = 'Handphone'; $subCategoryCode = 'HPN';
                                } else {
                                    $subCategoryName = 'Fixed Asset'; $subCategoryCode = 'AST';
                                }
                            }
                        @endphp
                        <tr>
                            <td class="text-center ps-3 text-muted fw-bold">{{ $assets->firstItem() + $index }}</td>

                            {{-- 1: KODE ASET & S/N --}}
                            <td>
                                <div class="fw-bold text-primary fs-6">{{ $asset->asset_number ?? 'N/A' }}</div>
                                <div class="mt-1 text-secondary font-monospace" style="font-size: 0.72rem;"><span class="fw-bold text-dark">FA:</span> {{ $asset->accounting_asset_number ?? '-' }}</div>
                                <div class="text-secondary font-monospace" style="font-size: 0.72rem;"><span class="fw-bold text-dark">S/N:</span> {{ $asset->serial_number ?? '-' }}</div>
                            </td>

                            {{-- 2: KATEGORI / TYPE --}}
                            <td>
                                <span class="mb-1 border badge bg-primary-subtle text-primary border-primary-subtle badge-category d-inline-block">
                                    {{ $subCategoryName }}
                                </span>
                                <div class="text-muted font-monospace" style="font-size: 0.7rem;"><i class="bi bi-tag"></i> {{ $subCategoryCode }}</div>
                            </td>

                            {{-- 3: NAMA ASET & SPESIFIKASI --}}
                            <td>
                                <div class="fw-bold text-dark text-wrap" style="max-width: 250px;">
                                    {{ $asset->name ?? optional($asset->item)->name ?? 'Nama Tidak Diketahui' }}
                                </div>
                                <div class="mt-1 text-secondary text-wrap" style="max-width: 250px; font-size: 0.75rem; line-height: 1.3;">
                                    {{ Str::limit(strip_tags($asset->spesifikasi_detail ?? '-'), 50) }}
                                </div>
                            </td>

                            {{-- 4: USER / GUDANG & DEPT --}}
                            <td>
                                @if(empty($asset->assigned_to))
                                    <div class="fw-bold text-warning text-wrap" style="max-width: 200px;">
                                        <i class="bi bi-shop me-1"></i> {{ optional($asset->warehouse)->name ?? 'Gudang Utama' }}
                                    </div>
                                    <div class="mt-1 text-muted" style="font-size: 0.75rem;">
                                        (Tersedia di Gudang)
                                    </div>
                                @else
                                    <div class="fw-bold text-primary text-wrap" style="max-width: 200px;">
                                        <i class="bi bi-person-badge me-1"></i> {{ optional($asset->assignee)->name ?? 'Unknown User' }}
                                    </div>
                                    <div class="mt-1 text-dark" style="font-size: 0.75rem;">
                                        <i class="bi bi-building-gear text-secondary me-1"></i> {{ optional(optional($asset->assignee)->department)->name ?? 'Tidak Ada Dept' }}
                                    </div>
                                @endif
                            </td>

                            {{-- 5: PT, TGL BELI, & STATUS --}}
                            <td>
                                @php
                                    $statusName = optional($asset->status)->name ?? 'Normal';
                                    $statusSlug = optional($asset->status)->slug;

                                    // 🔥 Logika Murni Menggunakan SLUG 🔥
                                    $isDisposedOrVoid = in_array($statusSlug, ['disposed', 'void', 'batal', 'canceled', 'cancelled']);

                                    if($statusSlug === 'in_use') {
                                        $badgeClass = 'bg-success-subtle text-success border border-success-subtle';
                                    } elseif($isDisposedOrVoid) {
                                        $badgeClass = 'bg-danger text-white shadow-sm';
                                    } elseif($statusSlug === 'available' || $statusSlug === 'returned') {
                                        $badgeClass = 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
                                    } else {
                                        $badgeClass = 'bg-secondary-subtle text-secondary'; // Untuk Maintenance, dll
                                    }
                                @endphp
                                <span class="badge {{ $badgeClass }} px-2 py-1 mb-2" style="font-size: 0.7rem;">
                                    {{ $statusName }}
                                </span>
                                <div class="text-secondary" style="font-size: 0.75rem;">
                                    <i class="bi bi-calendar3 me-1"></i> {{ $asset->acquisition_date ? \Carbon\Carbon::parse($asset->acquisition_date)->format('d M Y') : '-' }}
                                </div>
                            </td>

                            {{-- 6: CATATAN (NOTES) --}}
                            <td>
                                <div class="text-muted text-wrap fst-italic" style="max-width: 150px; font-size: 0.75rem; line-height: 1.3;">
                                    @if(!empty($asset->notes))
                                        <i class="bi bi-chat-left-text text-secondary me-1"></i> {{ Str::limit($asset->notes, 35) }}
                                    @else
                                        -
                                    @endif
                                </div>
                            </td>

                            {{-- 7: HARGA BELI --}}
                            <td class="text-end pe-4">
                                <div class="mb-1 price-strike">
                                    {{ number_format($asset->purchase_price ?? 0, 0, ',', '.') }}
                                </div>
                                <div class="price-current">
                                    {{ number_format($asset->net_book_value ?? $asset->purchase_price ?? 0, 0, ',', '.') }}
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-5 text-center text-muted">
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
