@extends('layouts.app')

@section('content')
<div class="py-4 container-fluid">

    {{-- 1. HEADER --}}
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h3 class="mb-0 fw-bold text-dark">Laporan Induk Aset (Master Data)</h3>
            <p class="mb-0 text-secondary">Manajemen inventaris IT, riwayat pengguna, departemen, dan nilai perolehan.</p>
        </div>
        <div class="gap-2 d-flex">
            <a href="#" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-excel"></i> Export Excel</a>
            <a href="{{ route('fixed-assets.index') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Register Aset Baru</a>
        </div>
    </div>

    {{-- 2. KPI / SUMMARY CARDS --}}
    <div class="mb-4 row">
        <div class="col-md-3">
            <div class="border-0 border-4 shadow-sm card border-start border-primary h-100">
                <div class="card-body">
                    <div class="text-muted small fw-bold text-uppercase">Total Seluruh Aset</div>
                    <div class="fs-3 fw-bold text-dark">{{ number_format($totalAssets, 0, ',', '.') }} <span class="fs-6 text-muted fw-normal">Unit</span></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="border-0 border-4 shadow-sm card border-start border-success h-100">
                <div class="card-body">
                    <div class="text-muted small fw-bold text-uppercase">Digunakan (Assigned)</div>
                    <div class="fs-3 fw-bold text-success">{{ number_format($inUse, 0, ',', '.') }} <span class="fs-6 text-muted fw-normal">Unit</span></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="border-0 border-4 shadow-sm card border-start border-warning h-100">
                <div class="card-body">
                    <div class="text-muted small fw-bold text-uppercase">Di Gudang (Available)</div>
                    <div class="fs-3 fw-bold text-warning">{{ number_format($inWarehouse, 0, ',', '.') }} <span class="fs-6 text-muted fw-normal">Unit</span></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="border-0 border-4 shadow-sm card border-start border-info h-100">
                <div class="card-body">
                    <div class="text-muted small fw-bold text-uppercase">Total Nilai Perolehan</div>
                    <div class="fs-4 fw-bold text-info">IDR {{ number_format($totalValue, 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- 3. FILTER PENCARIAN DINAMIS --}}
    <div class="mb-4 border-0 shadow-sm card">
        <div class="rounded card-body bg-light">
            <form action="{{ route('fixed-assets.master_list') }}" method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Status Keberadaan Aset</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Semua Status</option>
                        <option value="in_use" {{ request('status') == 'in_use' ? 'selected' : '' }}>Digunakan Staff</option>
                        <option value="in_warehouse" {{ request('status') == 'in_warehouse' ? 'selected' : '' }}>Tersedia (Gudang)</option>
                    </select>
                </div>
                <div class="col-md-7">
                    <label class="form-label small fw-bold">Cari Nama Aset / S/N / Nama Karyawan...</label>
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Ketik kata kunci pencarian...">
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-dark btn-sm w-100"><i class="bi bi-search"></i></button>
                </div>
            </form>
        </div>
    </div>

    {{-- 4. TABEL DATA SUPER LENGKAP --}}
    <div class="border-0 shadow-sm card">
        <div class="p-0 card-body table-responsive">
            <table class="table mb-0 align-middle table-hover table-striped text-nowrap" style="font-size: 0.85rem;">
                <thead class="text-white bg-dark">
                    <tr>
                        <th class="text-center ps-3" width="4%">No</th>
                        <th width="13%">Identitas & Kode Aset</th>
                        <th width="10%">Kategori / Type</th>
                        <th width="20%">Nama Barang & Spesifikasi</th>
                        <th width="15%">User Pengguna / Gudang</th>
                        <th width="13%">Perolehan & Status</th>
                        <th width="13%">Catatan / Notes</th> {{-- 🔥 KOLOM CATATAN BARU 🔥 --}}
                        <th width="12%" class="text-end pe-3">Harga Beli</th>
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
                            <td class="text-center ps-3">{{ $assets->firstItem() + $index }}</td>

                            {{-- 1: KODE ASET & S/N --}}
                            <td>
                                <div class="fw-bold text-primary">{{ $asset->asset_number ?? 'Belum Ada Kode' }}</div>
                                <div class="text-secondary" style="font-size: 0.72rem;"><span class="fw-bold text-dark">Acc Code:</span> {{ $asset->accounting_asset_number ?? '-' }}</div>
                                <div class="text-secondary" style="font-size: 0.72rem;"><span class="fw-bold text-dark">S/N:</span> {{ $asset->serial_number ?? '-' }}</div>
                                <div class="text-secondary" style="font-size: 0.72rem;"><span class="fw-bold text-dark">Ref:</span> {{ $asset->goods_receipt_id ?? $asset->batch_id }}</div>
                            </td>

                            {{-- 2: KATEGORI / TYPE --}}
                            <td>
                                <span class="px-2 py-1 mb-1 border badge bg-primary-subtle text-primary border-primary">
                                    {{ $subCategoryName }}
                                </span>
                                <div class="ms-1 text-muted" style="font-size: 0.72rem; fw-semibold">Code: {{ $subCategoryCode }}</div>
                            </td>

                            {{-- 3: NAMA ASET & SPESIFIKASI --}}
                            <td>
                                <div class="mb-1">
                                    <span class="badge bg-secondary" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                                        <i class="bi bi-upc-scan me-1"></i> {{ optional($asset->item)->code ?? 'TANPA-SKU' }}
                                    </span>
                                    <div class="ms-1 text-muted" style="font-size: 0.72rem; fw-semibold">Name: {{ optional($asset->item)->name ?? 'TANPA NAMA' }}</div>
                                </div>
                                <div class="fw-bold text-dark text-wrap" style="max-width: 220px;">
                                    {{ $asset->name ?? optional($asset->item)->name ?? 'Nama Tidak Diketahui' }}
                                </div>
                                <div class="mt-1 text-muted text-wrap" style="max-width: 220px; font-size: 0.75rem; line-height: 1.2;">
                                    {{ $asset->spesifikasi_detail ?? '-' }}
                                </div>
                            </td>

                            {{-- 4: USER / GUDANG & DEPT --}}
                            <td>
                                @if(empty($asset->assigned_to))
                                    <div class="fw-bold text-warning">
                                        <i class="bi bi-shop"></i> {{ optional($asset->warehouse)->name ?? 'Gudang Belum Di-set' }}
                                    </div>
                                    <div class="mt-1 text-muted fw-medium" style="font-size: 0.72rem;">
                                        <i class="bi bi-box-seam"></i> (Status: Tersedia)
                                    </div>
                                @else
                                    <div class="fw-bold text-primary">
                                        <i class="bi bi-person-badge"></i> {{ optional($asset->assignee)->name ?? 'Unknown User' }}
                                    </div>
                                    <div class="mt-1 text-dark fw-medium" style="font-size: 0.72rem;">
                                        <i class="bi bi-building-gear text-secondary"></i> Dept: {{ optional(optional($asset->assignee)->department)->name ?? optional($asset->department)->name ?? 'Tidak Ada Dept' }}
                                    </div>
                                @endif
                            </td>

                            {{-- 5: PT, TGL BELI, & STATUS --}}
                            <td>
                                <div class="fw-bold text-secondary">{{ optional($asset->company)->name ?? '-' }}</div>
                                <div class="mt-1 text-muted" style="font-size: 0.72rem;">
                                    <i class="bi bi-calendar3"></i> {{ $asset->acquisition_date ? \Carbon\Carbon::parse($asset->acquisition_date)->format('d M Y') : '-' }}
                                </div>
                                <div class="mt-1">
                                    @php
                                        // Ambil nama status dari database
                                        $statusName = optional($asset->status)->name ?? 'Normal';
                                        $statusLower = strtolower($statusName);

                                        // 🎨 LOGIKA WARNA OTOMATIS (DYNAMIC BADGE) 🎨
                                        if(str_contains($statusLower, 'use') || str_contains($statusLower, 'pakai') || str_contains($statusLower, 'aktif')) {
                                            $badgeClass = 'bg-success-subtle text-success border border-success';
                                        } elseif(str_contains($statusLower, 'rusak') || str_contains($statusLower, 'service') || str_contains($statusLower, 'hilang')) {
                                            $badgeClass = 'bg-danger text-white shadow-sm';
                                        } elseif(str_contains($statusLower, 'available') || str_contains($statusLower, 'gudang') || str_contains($statusLower, 'ready')) {
                                            $badgeClass = 'bg-warning text-dark shadow-sm';
                                        } else {
                                            $badgeClass = 'bg-primary-subtle text-primary border border-primary';
                                        }
                                    @endphp
                                    <span class="badge {{ $badgeClass }} w-100 py-1" style="font-size: 0.7rem; font-weight: 600; letter-spacing: 0.3px;">
                                        {{ $statusName }}
                                    </span>
                                </div>
                            </td>

                            {{-- 6: CATATAN (NOTES) 🔥 --}}
                            <td>
                                <div class="text-muted text-wrap" style="max-width: 180px; font-size: 0.75rem; line-height: 1.3;">
                                    @if(!empty($asset->notes))
                                        <i class="bi bi-chat-left-text text-secondary me-1"></i> {{ $asset->notes }}
                                    @else
                                        -
                                    @endif
                                </div>
                            </td>

                            {{-- 7: HARGA BELI --}}
                            <td class="text-end fw-bold text-success pe-3 fs-6">
                                {{ optional($asset->currency)->code ?? 'IDR' }} {{ number_format($asset->purchase_price, 0, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-5 text-center text-muted">
                                <i class="mb-2 bi bi-inbox fs-1 d-block"></i>
                                Tidak ada data aset yang ditemukan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- 5. PAGINASI BAWAH --}}
        <div class="py-3 bg-white card-footer">
            <div class="d-flex justify-content-between align-items-center">
                <span class="text-muted small">
                    Menampilkan {{ $assets->firstItem() ?? 0 }} hingga {{ $assets->lastItem() ?? 0 }} dari {{ $assets->total() }} aset
                </span>
                <nav>
                    {{ $assets->links('pagination::bootstrap-5') }}
                </nav>
            </div>
        </div>
    </div>
</div>
@endsection
