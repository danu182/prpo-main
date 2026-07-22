@extends('layouts.app')

@push('css')
<style>
    .card-kpi { transition: all 0.3s ease; border-left: 5px solid transparent; }
    .card-kpi:hover { transform: translateY(-5px); box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.08) !important; }
</style>
@endpush

@section('content')
<div class="pb-5 container-fluid text-dark">

    {{-- HEADER HALAMAN --}}
    <div class="row align-items-center pb-3 mb-4 border-bottom gy-3">
        <div class="col-xl-7 col-lg-6">
            <h3 class="mb-1 fw-bold text-dark d-flex align-items-center">
                <i class="bi bi-bar-chart-line text-primary me-2"></i> Laporan Valuasi Persediaan
            </h3>
            <div class="text-muted small fw-medium mt-1">
                Laporan nilai aset riil gudang berdasarkan metode <strong class="text-dark">Moving Average / Spesific Identification</strong>.
            </div>
        </div>
        <div class="col-xl-5 col-lg-6 text-lg-end">
            <button onclick="window.print()" class="btn btn-dark rounded-pill px-4 fw-bold shadow-sm">
                <i class="bi bi-printer me-2"></i> Cetak Laporan
            </button>
        </div>
    </div>

    {{-- KARTU KPI (KEY PERFORMANCE INDICATORS) --}}
    <div class="row g-4 mb-4">
        {{-- KPI 1: GRAND TOTAL VALUASI --}}
        <div class="col-xl-6 col-lg-12">
            <div class="p-4 border-0 shadow-sm card rounded-4 h-100 card-kpi bg-primary-subtle border-primary-subtle" style="border-left-color: #0d6efd;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-primary fw-bold text-uppercase mb-0"><i class="bi bi-safe me-2"></i> Grand Total Aset Gudang</h6>
                    <div class="p-2 bg-white text-primary rounded-circle shadow-sm"><i class="bi bi-cash-stack fs-5"></i></div>
                </div>
                <h1 class="fw-bolder text-primary display-5 mb-0">Rp {{ number_format($grandTotalValue, 0, ',', '.') }}</h1>
                <div class="mt-3 text-muted small">
                    <i class="bi bi-info-circle me-1"></i> Total nilai kekayaan seluruh barang yang saat ini fisik-nya tersedia di gudang.
                </div>
            </div>
        </div>

        {{-- KPI 2 & 3: STATISTIK BARANG --}}
        <div class="col-xl-3 col-md-6">
            <div class="p-4 border-0 shadow-sm card rounded-4 h-100 card-kpi bg-white" style="border-left-color: #198754;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-muted fw-bold text-uppercase mb-0">Total Jenis Barang</h6>
                    <div class="p-2 bg-success-subtle text-success rounded-circle"><i class="bi bi-box-seam fs-5"></i></div>
                </div>
                <h2 class="fw-bolder text-dark mb-0">{{ number_format($totalItems, 0, ',', '.') }} <span class="fs-6 text-muted fw-normal">SKU</span></h2>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="p-4 border-0 shadow-sm card rounded-4 h-100 card-kpi bg-white" style="border-left-color: #ffc107;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-muted fw-bold text-uppercase mb-0">Total Qty Fisik</h6>
                    <div class="p-2 bg-warning-subtle text-warning rounded-circle"><i class="bi bi-boxes fs-5"></i></div>
                </div>
                <h2 class="fw-bolder text-dark mb-0">{{ number_format($totalQtyFisik, 0, ',', '.') }} <span class="fs-6 text-muted fw-normal">Unit</span></h2>
            </div>
        </div>
    </div>

    {{-- TABEL LAPORAN VALUASI --}}
    <div class="overflow-hidden border-0 shadow-sm card rounded-4 border-top border-4 border-primary">
        <div class="py-3 bg-white card-header border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-table me-2 text-primary"></i>Rincian Nilai per Barang</h6>
            <span class="text-muted small">Update Terakhir: {{ now()->format('d M Y, H:i') }}</span>
        </div>
        <div class="p-0 card-body">
            <div class="table-responsive">
                <table class="table mb-0 align-middle table-hover table-striped">
                    <thead class="bg-light text-muted small text-uppercase fw-bold border-bottom">
                        <tr>
                            <th class="py-3 ps-4" width="5%">No</th>
                            <th class="py-3" width="15%">Kode Barang</th>
                            <th class="py-3" width="30%">Nama Barang</th>
                            <th class="py-3 text-center" width="15%">Total Qty</th>
                            <th class="py-3 text-end" width="15%">Harga Rata-Rata (Avg)</th>
                            <th class="py-3 text-end pe-4" width="20%">Total Valuasi (Aset)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($valuations as $index => $row)
                            @php
                                // Menghitung HPP Rata-Rata per barang (Moving Average)
                                $avgPrice = $row->total_qty > 0 ? ($row->total_value / $row->total_qty) : 0;
                            @endphp
                            <tr>
                                <td class="py-3 ps-4 text-muted fw-bold">{{ $index + 1 }}</td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary font-monospace border border-secondary-subtle px-2 py-1">
                                        {{ optional($row->item)->code ?? 'UNKNOWN' }}
                                    </span>
                                </td>
                                <td class="py-3">
                                    <div class="fw-bold text-dark">{{ optional($row->item)->name ?? 'Item Tidak Ditemukan' }}</div>
                                    <div class="small text-muted">{{ optional(optional($row->item)->category)->name ?? 'Tanpa Kategori' }}</div>
                                </td>
                                <td class="py-3 text-center fw-bolder text-primary fs-6">
                                    {{ (float)$row->total_qty }} <span class="small fw-normal text-muted">{{ optional(optional($row->item)->uom)->name ?? 'PCS' }}</span>
                                </td>
                                <td class="py-3 text-end fw-semibold text-secondary">
                                    Rp {{ number_format($avgPrice, 2, ',', '.') }}
                                </td>
                                <td class="py-3 text-end pe-4 fw-bolder text-dark fs-6">
                                    Rp {{ number_format($row->total_value, 0, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-5 text-center text-muted">
                                    <i class="bi bi-inbox fs-1 opacity-25 d-block mb-2"></i>
                                    Tidak ada stok barang di gudang saat ini. Valuasi Rp 0.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="table-light fw-bold border-top">
                        <tr>
                            <td colspan="3" class="py-3 text-end text-uppercase text-muted small">Grand Total :</td>
                            <td class="py-3 text-center text-primary fs-6">{{ number_format($totalQtyFisik, 0, ',', '.') }}</td>
                            <td class="py-3"></td>
                            <td class="py-3 text-end pe-4 text-primary fs-5">Rp {{ number_format($grandTotalValue, 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
    @media print {
        body { background-color: white !important; }
        .btn, nav, header, footer { display: none !important; }
        .container-fluid { width: 100% !important; padding: 0 !important; }
        .card { border: 1px solid #ddd !important; box-shadow: none !important; }
        .bg-primary-subtle { background-color: #f8f9fa !important; border: 1px solid #000 !important; }
        .text-primary { color: #000 !important; }
    }
</style>
@endsection
