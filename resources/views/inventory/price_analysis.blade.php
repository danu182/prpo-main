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
    .table-modern tbody tr:hover { background-color: #f8fafc !important; }
    .filter-input { border-color: #cbd5e1; height: 42px; }
    .col-angka { font-size: 0.95rem; font-weight: 700; text-align: right; }
</style>
@endpush

@section('content')
<div class="pb-5 container-fluid px-md-4">

    {{-- HEADER --}}
    <div class="gap-3 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-end">
        <div>
            <h3 class="mb-0 fw-bold text-dark d-flex align-items-center">
                <i class="bi bi-graph-up-arrow text-primary me-2"></i> Analisis Harga Pembelian
            </h3>
            <p class="mt-1 mb-0 text-muted small">Pantau fluktuasi harga (Termurah, Termahal, Rata-rata) dari riwayat Purchase Order.</p>
        </div>
        <div>
            <button class="shadow-sm btn btn-dark fw-bold rounded-pill" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Cetak Analisis
            </button>
        </div>
    </div>

    {{-- PENCARIAN --}}
    <div class="mb-4 bg-white border-0 border-4 shadow-sm border-top card rounded-4 border-primary">
        <div class="p-4 card-body">
            <form action="{{ route('inventory.price-analysis') }}" method="GET">
                <div class="row g-3 align-items-end">
                    <div class="col-md-10">
                        <label class="form-label fw-bold small text-muted text-uppercase">Cari Barang</label>
                        <div class="overflow-hidden shadow-sm input-group rounded-3">
                            <span class="bg-light input-group-text border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" name="search" class="form-control filter-input border-start-0 ps-0" placeholder="Ketik nama / kode barang..." value="{{ $search }}">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="shadow-sm btn btn-dark w-100 fw-bold" style="height: 42px;">
                            <i class="bi bi-search me-1"></i> Cari
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- TABEL ANALISIS HARGA --}}
    <div class="mb-4 border-4 card-table-wrapper border-top border-dark">
        <div class="p-0 table-responsive">
            <table class="table align-middle table-modern text-nowrap">
                <thead>
                    <tr>
                        <th class="text-center ps-4" width="5%">No</th>
                        <th width="35%">Identitas Barang & Total Dibeli</th>
                        <th class="text-end bg-success-subtle text-success" width="15%">Harga Termurah<br><span class="fw-normal text-muted" style="font-size: 0.65rem; text-transform:none;">(Min. Price)</span></th>
                        <th class="text-end bg-danger-subtle text-danger" width="15%">Harga Termahal<br><span class="fw-normal text-muted" style="font-size: 0.65rem; text-transform:none;">(Max. Price)</span></th>
                        <th class="text-end bg-primary-subtle text-primary" width="15%">Harga Rata-rata<br><span class="fw-normal text-muted" style="font-size: 0.65rem; text-transform:none;">(Average Price)</span></th>
                        <th class="text-end pe-4 bg-warning-subtle text-dark border-start border-warning" width="15%">Harga Terakhir<br><span class="fw-normal text-muted" style="font-size: 0.65rem; text-transform:none;">(Latest PO)</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $index => $item)
                        <tr>
                            <td class="text-center ps-4 text-muted fw-bold">{{ $items->firstItem() + $index }}</td>

                            <td>
                                <div class="mb-1 fw-bold text-dark text-wrap" style="max-width: 250px;">{{ $item->name }}</div>
                                <span class="border badge bg-secondary-subtle text-secondary border-secondary-subtle font-monospace">{{ $item->code }}</span>
                                <div class="mt-1 text-muted" style="font-size: 0.75rem;">
                                    Total Dibeli Sepanjang Waktu: <span class="fw-bold text-dark">{{ number_format($item->total_dibeli, 0, ',', '.') }} Unit</span>
                                </div>
                            </td>

                            {{-- Harga Termurah --}}
                            <td class="text-end bg-success-subtle">
                                <span class="text-success fs-6 fw-bold">Rp {{ number_format($item->harga_termurah, 0, ',', '.') }}</span>
                            </td>

                            {{-- Harga Termahal --}}
                            <td class="text-end bg-danger-subtle">
                                <span class="text-danger fs-6 fw-bold">Rp {{ number_format($item->harga_termahal, 0, ',', '.') }}</span>
                            </td>

                            {{-- Harga Rata-rata --}}
                            <td class="text-end bg-primary-subtle">
                                <span class="text-primary fs-6 fw-bold">Rp {{ number_format($item->harga_rata, 0, ',', '.') }}</span>
                            </td>

                            {{-- 🔥 Harga Terakhir 🔥 --}}
                            <td class="text-end pe-4 bg-warning-subtle border-start border-warning">
                                <span class="text-dark fs-5 fw-bold">Rp {{ number_format($item->harga_terakhir, 0, ',', '.') }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-5 text-center text-muted">
                                <i class="mb-3 opacity-25 bi bi-graph-down display-4 d-block"></i>
                                <h6 class="fw-bold text-dark">Data Analisis Tidak Ditemukan</h6>
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
                Menampilkan {{ $items->firstItem() ?? 0 }} - {{ $items->lastItem() ?? 0 }}
            </span>
            <nav>
                {{ $items->links('pagination::bootstrap-5') }}
            </nav>
        </div>
        @endif
    </div>

</div>
@endsection
