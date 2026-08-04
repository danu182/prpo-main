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
        color: #475569;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 1.2rem 1rem;
        border-bottom: 2px solid #e2e8f0;
        font-weight: 800;
        border-top: none;
        vertical-align: middle;
    }
    .table-modern tbody td {
        padding: 1rem;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
        font-size: 0.85rem;
    }
    .table-modern tbody tr { transition: all 0.2s ease; background-color: #ffffff; }
    .table-modern tbody tr:hover { background-color: #f8fafc !important; }

    /* Custom Input Search & Date */
    .filter-input { border-color: #cbd5e1; height: 42px; }

    /* Kolom Angka Mutasi */
    .col-angka { font-size: 1rem; font-weight: 700; width: 10%; text-align: right; }
</style>
@endpush

@section('content')
<div class="pb-5 container-fluid px-md-4">

    {{-- HEADER --}}
    <div class="gap-3 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-end">
        <div>
            <h3 class="mb-0 fw-bold text-dark d-flex align-items-center">
                <i class="bi bi-clock-history text-primary me-2"></i> Laporan Mutasi Inventory
            </h3>
            <p class="mt-1 mb-0 text-muted small">Lacak pergerakan Saldo Awal, Masuk, Keluar, dan Saldo Akhir pada periode tertentu.</p>
        </div>

        {{-- 🔥 TOMBOL EXCEL & CETAK LAPORAN (UPDATE) 🔥 --}}
        <div class="gap-2 d-flex">
            {{-- request()->all() berfungsi untuk membawa filter tanggal & gudang yang sedang aktif ke halaman cetak/excel --}}
            <a href="{{ route('inventory.stock-as-of.export', request()->all()) }}" class="shadow-sm btn btn-success fw-bold rounded-pill">
                <i class="bi bi-file-earmark-excel me-1"></i> Export Excel
            </a>
            <a href="{{ route('inventory.stock-as-of.print', request()->all()) }}" target="_blank" class="shadow-sm btn btn-dark fw-bold rounded-pill">
                <i class="bi bi-printer me-1"></i> Cetak Laporan
            </a>
        </div>
    </div>

    {{-- FILTER SUPER LENGKAP --}}
    <div class="mb-4 bg-white border-0 border-4 shadow-sm border-top card rounded-4 border-primary">
        <div class="p-4 card-body">
            <form action="{{ route('inventory.stock-as-of') }}" method="GET">
                <div class="row g-3 align-items-end">

                    {{-- Filter Range Tanggal --}}
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted text-uppercase">Periode Waktu <span class="text-danger">*</span></label>
                        <div class="overflow-hidden shadow-sm input-group rounded-3">
                            <input type="date" name="start_date" class="form-control filter-input fw-bold text-primary" value="{{ $startDate }}" required title="Tanggal Mulai">
                            <span class="text-white input-group-text bg-primary border-primary">s/d</span>
                            <input type="date" name="end_date" class="form-control filter-input fw-bold text-primary" value="{{ $endDate }}" required title="Tanggal Akhir">
                        </div>
                    </div>

                    {{-- Filter Gudang --}}
                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-muted text-uppercase">Lokasi Gudang</label>
                        <select name="warehouse_id" class="shadow-sm form-select filter-input">
                            <option value="">-- Semua Gudang --</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}" {{ $warehouseId == $wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Filter Search Nama --}}
                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-muted text-uppercase">Cari Barang</label>
                        <div class="overflow-hidden shadow-sm input-group rounded-3">
                            <span class="bg-light input-group-text border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" name="search" class="form-control filter-input border-start-0 ps-0" placeholder="Ketik nama / kode..." value="{{ $search }}">
                        </div>
                    </div>

                    {{-- Tombol Terapkan --}}
                    <div class="col-md-2">
                        <button type="submit" class="shadow-sm btn btn-dark w-100 fw-bold" style="height: 42px;">
                            <i class="bi bi-funnel-fill me-1"></i> Terapkan
                        </button>
                    </div>

                </div>
            </form>
        </div>
    </div>

    {{-- TABEL DATA MODERN (MUTASI 4 PILAR) --}}
    <div class="mb-4 border-4 card-table-wrapper border-top border-dark">
        <div class="px-4 py-3 bg-white card-header border-bottom-0 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
            <h6 class="mb-0 fw-bold text-dark">
                <i class="bi bi-box-seam me-2 text-dark"></i> Mutasi Stok:
                <span class="text-primary">{{ \Carbon\Carbon::parse($startDate)->translatedFormat('d M Y') }}</span>
                s/d
                <span class="text-primary">{{ \Carbon\Carbon::parse($endDate)->translatedFormat('d M Y') }}</span>
            </h6>
            @if($warehouseId)
                <span class="px-3 py-1 border shadow-sm badge bg-info text-dark rounded-pill border-info"><i class="bi bi-geo-alt-fill me-1"></i> Filter: Gudang Terpilih</span>
            @else
                <span class="px-3 py-1 text-white shadow-sm badge bg-secondary rounded-pill"><i class="bi bi-globe me-1"></i> Semua Gudang Tergabung</span>
            @endif
        </div>
        <div class="p-0 table-responsive">
            <table class="table align-middle table-modern text-nowrap">
                <thead>
                    <tr>
                        <th class="text-center ps-4" width="5%">No</th>
                        <th width="30%">Identitas Barang</th>
                        <th class="text-end bg-light" width="15%">Saldo Awal<br><span class="fw-normal text-muted" style="font-size: 0.65rem; text-transform:none;">(Sblm {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }})</span></th>
                        <th class="text-end bg-success-subtle text-success" width="15%">+ Masuk<br><span class="fw-normal text-success" style="font-size: 0.65rem; text-transform:none;">(Penerimaan/Retur)</span></th>
                        <th class="text-end bg-danger-subtle text-danger" width="15%">- Keluar<br><span class="fw-normal text-danger" style="font-size: 0.65rem; text-transform:none;">(Pemakaian/Hilang)</span></th>
                        <th class="text-end pe-4 bg-primary-subtle text-primary" width="20%">Saldo Akhir<br><span class="fw-normal text-primary" style="font-size: 0.65rem; text-transform:none;">(Tgl {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }})</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $index => $item)
                        <tr>
                            <td class="text-center ps-4 text-muted fw-bold">{{ $items->firstItem() + $index }}</td>

                            <td>
                                <div class="mb-1 fw-bold text-dark text-wrap" style="max-width: 250px;">{{ $item->name }}</div>
                                <span class="border badge bg-secondary-subtle text-secondary border-secondary-subtle font-monospace">{{ $item->code }}</span>
                            </td>

                            {{-- Saldo Awal --}}
                            <td class="bg-light col-angka text-muted">
                                {{ number_format($item->saldo_awal, 0, ',', '.') }}
                            </td>

                            {{-- Mutasi Masuk (IN) --}}
                            <td class="bg-success-subtle text-success col-angka">
                                {{ $item->mutasi_in > 0 ? '+'.number_format($item->mutasi_in, 0, ',', '.') : '-' }}
                            </td>

                            {{-- Mutasi Keluar (OUT) --}}
                            <td class="bg-danger-subtle text-danger col-angka">
                                {{ $item->mutasi_out > 0 ? '-'.number_format($item->mutasi_out, 0, ',', '.') : '-' }}
                            </td>

                            {{-- 🔥 Saldo Akhir 🔥 --}}
                            <td class="pe-4 bg-primary-subtle col-angka">
                                <span class="fs-5 {{ $item->saldo_akhir < 0 ? 'text-danger' : 'text-primary' }}">
                                    {{ number_format($item->saldo_akhir, 0, ',', '.') }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-5 text-center text-muted">
                                <i class="mb-3 opacity-25 bi bi-box display-4 d-block"></i>
                                <h6 class="fw-bold text-dark">Data Tidak Ditemukan</h6>
                                <p class="mb-0 small text-muted">Barang inventory tidak ditemukan atau filter tidak cocok.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- PAGINASI --}}
        @if($items->hasPages())
        <div class="px-4 py-3 bg-white card-footer border-top d-flex flex-column flex-md-row justify-content-between align-items-center">
            <span class="mb-2 text-muted small mb-md-0 fw-medium">
                Menampilkan {{ $items->firstItem() ?? 0 }} - {{ $items->lastItem() ?? 0 }} dari {{ $items->total() }} barang
            </span>
            <nav>
                {{ $items->links('pagination::bootstrap-5') }}
            </nav>
        </div>
        @endif
    </div>

</div>
@endsection
