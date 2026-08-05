@extends('layouts.app')

@push('css')
<style>
    .card-table-wrapper { border-radius: 16px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); background: #fff; }
    .table-modern { margin-bottom: 0; }
    .table-modern thead th { background-color: #f8fafc; color: #475569; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; padding: 1.2rem 1rem; border-bottom: 2px solid #e2e8f0; font-weight: 800; border-top: none; vertical-align: middle; }
    .table-modern tbody td { padding: 1rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; color: #334155; font-size: 0.85rem; }
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
                <i class="bi bi-clock-history text-primary me-2"></i> Riwayat Harga Beli (PO)
            </h3>
            <p class="mt-1 mb-0 text-muted small">Lacak histori harga beli barang dari Purchase Order yang sudah diterima di gudang.</p>
        </div>
        <div>
            <button class="shadow-sm btn btn-outline-dark fw-bold rounded-pill" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Cetak Riwayat
            </button>
        </div>
    </div>

    {{-- PENCARIAN & RANGE TANGGAL --}}
    <div class="mb-4 bg-white border-0 border-4 shadow-sm border-top card rounded-4 border-primary">
        <div class="p-4 card-body">
            <form action="{{ route('inventory.purchase-history') }}" method="GET">
                <div class="row g-3 align-items-end">

                    {{-- Filter Range Tanggal --}}
                    <div class="col-md-5">
                        <label class="form-label fw-bold small text-muted text-uppercase">Rentang Tanggal Diterima <span class="text-danger">*</span></label>
                        <div class="overflow-hidden shadow-sm input-group rounded-3">
                            <input type="date" name="start_date" class="form-control filter-input fw-bold text-primary" value="{{ $startDate }}" required title="Tanggal Mulai">
                            <span class="text-white input-group-text bg-primary border-primary">s/d</span>
                            <input type="date" name="end_date" class="form-control filter-input fw-bold text-primary" value="{{ $endDate }}" required title="Tanggal Akhir">
                        </div>
                    </div>

                    {{-- Filter Search Nama --}}
                    <div class="col-md-5">
                        <label class="form-label fw-bold small text-muted text-uppercase">Cari Barang / No. PO</label>
                        <div class="overflow-hidden shadow-sm input-group rounded-3">
                            <span class="bg-light input-group-text border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" name="search" class="form-control filter-input border-start-0 ps-0" placeholder="Ketik nama barang, kode, atau No. PO..." value="{{ $search }}">
                        </div>
                    </div>

                    {{-- Tombol Terapkan --}}
                    <div class="col-md-2">
                        <button type="submit" class="shadow-sm btn btn-dark w-100 fw-bold" style="height: 42px;">
                            <i class="bi bi-search me-1"></i> Cari Data
                        </button>
                    </div>

                </div>
            </form>
        </div>
    </div>

    {{-- TABEL RIWAYAT HARGA --}}
    <div class="mb-4 border-4 card-table-wrapper border-top border-dark">
        <div class="p-0 table-responsive">
            <table class="table align-middle table-modern text-nowrap">
                <thead>
                    <tr>
                        <th class="text-center ps-4" width="5%">No</th>
                        <th width="20%">Tgl Terima, No. PO, & Vendor</th>
                        <th width="25%">Identitas Barang</th>
                        <th class="text-end bg-success-subtle text-success border-start border-success" width="15%">Harga Beli<br><span class="fw-normal text-success" style="font-size: 0.65rem; text-transform:none;">(Unit Price)</span></th>
                        <th class="text-center bg-light" width="15%">Qty Diterima<br><span class="fw-normal text-muted" style="font-size: 0.65rem; text-transform:none;">(Fisik Masuk Gudang)</span></th>
                        <th class="text-end pe-4 bg-primary-subtle text-primary border-start border-primary" width="20%">Total Nilai<br><span class="fw-normal text-primary" style="font-size: 0.65rem; text-transform:none;">(Subtotal)</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($histories as $index => $history)
                        <tr>
                            <td class="text-center ps-4 text-muted fw-bold">{{ $histories->firstItem() + $index }}</td>

                            {{-- Tgl PO, No PO & NAMA SUPPLIER --}}
                            <td>
                                <div class="mb-1 fw-bold text-dark">{{ \Carbon\Carbon::parse($history->created_at)->format('d M Y') }}</div>
                                <div class="mb-1">
                                    <span class="border badge bg-dark-subtle text-dark border-dark-subtle font-monospace"><i class="bi bi-receipt me-1"></i> {{ $history->po_number }}</span>
                                </div>
                                {{-- 🔥 BARIS NAMA SUPPLIER 🔥 --}}
                                <div class="small text-muted fw-bold text-wrap" style="max-width: 200px;">
                                    <i class="bi bi-shop text-info me-1"></i> {{ $history->supplier_name ?? 'Vendor Tidak Diketahui' }}
                                </div>
                            </td>

                            {{-- Identitas Barang --}}
                            <td>
                                <div class="mb-1 fw-bold text-dark text-wrap" style="max-width: 250px;">{{ $history->item_name }}</div>
                                <span class="mb-1 border badge bg-secondary-subtle text-secondary border-secondary-subtle font-monospace">{{ $history->item_code }}</span>

                                {{-- 🔥 NAMA SPESIFIK DI PO 🔥 --}}
                                @if($history->po_item_name && $history->po_item_name != $history->item_name)
                                <div class="mt-1 small text-primary fw-medium" style="font-size: 0.75rem;">
                                    <i class="bi bi-tag-fill me-1"></i> PO: {{ $history->po_item_name }}
                                </div>
                                @endif
                            </td>

                            {{-- Harga Beli --}}
                            <td class="text-end bg-success-subtle border-start border-success">
                                <span class="text-success fs-6 fw-bold">Rp {{ number_format($history->unit_price, 0, ',', '.') }}</span>
                            </td>

                            {{-- Qty Diterima --}}
                            <td class="text-center bg-light">
                                <span class="fw-bold text-dark fs-6">{{ number_format($history->qty_received, 0, ',', '.') }}</span>
                                <span class="text-muted small ms-1">Unit</span>
                            </td>

                            {{-- Subtotal --}}
                            <td class="text-end pe-4 bg-primary-subtle border-start border-primary">
                                <span class="text-primary fs-6 fw-bold">Rp {{ number_format($history->subtotal, 0, ',', '.') }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-5 text-center text-muted">
                                <i class="mb-3 opacity-25 bi bi-clipboard-x display-4 d-block"></i>
                                <h6 class="fw-bold text-dark">Riwayat PO Tidak Ditemukan</h6>
                                <p class="mb-0 small text-muted">Tidak ada data penerimaan barang pada rentang tanggal ini.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- PAGINASI --}}
        @if($histories->hasPages())
        <div class="px-4 py-3 bg-white card-footer border-top d-flex flex-column flex-md-row justify-content-between align-items-center">
            <span class="mb-2 text-muted small mb-md-0 fw-medium">
                Menampilkan {{ $histories->firstItem() ?? 0 }} - {{ $histories->lastItem() ?? 0 }} dari total {{ $histories->total() }} riwayat
            </span>
            <nav>
                {{ $histories->links('pagination::bootstrap-5') }}
            </nav>
        </div>
        @endif
    </div>

</div>
@endsection
