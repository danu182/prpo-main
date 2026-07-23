@extends('layouts.app')

@section('content')
<div class="pb-5 container-fluid text-dark">

    <div class="mb-4 d-flex justify-content-between align-items-end">
        <div>
            <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-bar-chart-fill me-2 text-primary"></i>Pusat Laporan (Report Center)</h4>
            <div class="text-muted small">
                Pilih dan hasilkan laporan performa operasional serta finansial perusahaan berdasarkan periode.
            </div>
        </div>
    </div>

    {{-- KATEGORI 1: PEMBELIAN (PURCHASING) --}}
    <h6 class="pb-2 mb-3 fw-bold text-success text-uppercase border-bottom"><i class="bi bi-cart-check-fill me-2"></i>Modul Pembelian (Purchasing)</h6>
    <div class="mb-5 row g-3">
        <div class="col-md-4">
            <div class="border-0 shadow-sm card rounded-4 h-100 widget-card report-card" data-bs-toggle="modal" data-bs-target="#modalFilterReport" data-title="Rekap Purchase Order (PO)" data-type="po_recap">
                <div class="p-4 text-center cursor-pointer card-body">
                    <div class="mx-auto mb-3 icon-box bg-success bg-opacity-10 text-success fs-3" style="width: 60px; height: 60px;">
                        <i class="bi bi-file-earmark-text"></i>
                    </div>
                    <h6 class="mb-1 fw-bold">Rekap Purchase Order (PO)</h6>
                    <small class="text-muted">Laporan daftar PO yang diterbitkan dalam periode tertentu.</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="border-0 shadow-sm card rounded-4 h-100 widget-card report-card" data-bs-toggle="modal" data-bs-target="#modalFilterReport" data-title="Top Spend per Vendor" data-type="vendor_spend">
                <div class="p-4 text-center cursor-pointer card-body">
                    <div class="mx-auto mb-3 icon-box bg-success bg-opacity-10 text-success fs-3" style="width: 60px; height: 60px;">
                        <i class="bi bi-shop"></i>
                    </div>
                    <h6 class="mb-1 fw-bold">Top Spend per Vendor</h6>
                    <small class="text-muted">Analisis pengeluaran terbanyak berdasarkan supplier/vendor.</small>
                </div>
            </div>
        </div>
    </div>

    {{-- KATEGORI 2: GUDANG (WAREHOUSE) --}}
    <h6 class="pb-2 mb-3 fw-bold text-info text-uppercase border-bottom"><i class="bi bi-box-seam-fill me-2"></i>Modul Gudang & Logistik</h6>
    <div class="mb-5 row g-3">
        <div class="col-md-4">
            <div class="border-0 shadow-sm card rounded-4 h-100 widget-card report-card" data-bs-toggle="modal" data-bs-target="#modalFilterReport" data-title="Penerimaan Barang (GR)" data-type="gr_recap">
                <div class="p-4 text-center cursor-pointer card-body">
                    <div class="mx-auto mb-3 icon-box bg-info bg-opacity-10 text-info fs-3" style="width: 60px; height: 60px;">
                        <i class="bi bi-truck"></i>
                    </div>
                    <h6 class="mb-1 fw-bold">Penerimaan Barang (GR)</h6>
                    <small class="text-muted">Laporan barang yang masuk ke gudang beserta detail itemnya.</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="border-0 shadow-sm card rounded-4 h-100 widget-card report-card" data-bs-toggle="modal" data-bs-target="#modalFilterReport" data-title="Retur ke Vendor (RTV)" data-type="rtv_recap">
                <div class="p-4 text-center cursor-pointer card-body">
                    <div class="mx-auto mb-3 icon-box bg-danger bg-opacity-10 text-danger fs-3" style="width: 60px; height: 60px;">
                        <i class="bi bi-arrow-return-left"></i>
                    </div>
                    <h6 class="mb-1 fw-bold">Retur ke Vendor (RTV)</h6>
                    <small class="text-muted">Daftar pengembalian barang cacat/rusak ke pihak supplier.</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="border-0 shadow-sm card rounded-4 h-100 widget-card report-card" data-bs-toggle="modal" data-bs-target="#modalFilterReport" data-title="Mutasi Stok Barang" data-type="stock_mutation">
                <div class="p-4 text-center cursor-pointer card-body">
                    <div class="mx-auto mb-3 icon-box bg-secondary bg-opacity-10 text-secondary fs-3" style="width: 60px; height: 60px;">
                        <i class="bi bi-boxes"></i>
                    </div>
                    <h6 class="mb-1 fw-bold">Kartu Mutasi Stok</h6>
                    <small class="text-muted">Jejak pergerakan (masuk/keluar) barang di gudang per periode.</small>
                </div>
            </div>
        </div>
    </div>

    {{-- KATEGORI 3: KEUANGAN (FINANCE) --}}
    <h6 class="pb-2 mb-3 fw-bold text-warning text-uppercase border-bottom"><i class="bi bi-wallet-fill me-2"></i>Modul Keuangan (A/P & Opex)</h6>
    <div class="mb-4 row g-3">
        <div class="col-md-4">
            <div class="border-0 shadow-sm card rounded-4 h-100 widget-card report-card" data-bs-toggle="modal" data-bs-target="#modalFilterReport" data-title="Aging Schedule Hutang" data-type="aging_ap">
                <div class="p-4 text-center cursor-pointer card-body">
                    <div class="mx-auto mb-3 icon-box bg-warning bg-opacity-10 text-warning fs-3" style="width: 60px; height: 60px;">
                        <i class="bi bi-calendar-x"></i>
                    </div>
                    <h6 class="mb-1 fw-bold">Aging Schedule (Umur Hutang)</h6>
                    <small class="text-muted">Pantau hutang jatuh tempo dan overdue ke seluruh vendor.</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="border-0 shadow-sm card rounded-4 h-100 widget-card report-card" data-bs-toggle="modal" data-bs-target="#modalFilterReport" data-title="Pengeluaran Kas (Payment)" data-type="payment_recap">
                <div class="p-4 text-center cursor-pointer card-body">
                    <div class="mx-auto mb-3 icon-box bg-primary bg-opacity-10 text-primary fs-3" style="width: 60px; height: 60px;">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <h6 class="mb-1 fw-bold">Pengeluaran Kas Kasir</h6>
                    <small class="text-muted">Rekap total pembayaran (transfer/tunai) yang sudah sukses ditarik.</small>
                </div>
            </div>
        </div>
    </div>

</div>

{{-- MODAL FILTER GLOBAL UNTUK SEMUA LAPORAN --}}
<div class="modal fade" id="modalFilterReport" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="border-0 shadow modal-content rounded-4">
            <div class="text-white modal-header bg-dark rounded-top-4">
                <h6 class="mb-0 modal-title fw-bold" id="reportModalTitle"><i class="bi bi-funnel-fill me-2"></i>Filter Laporan</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            {{-- Form akan kita arahkan ke rute generate (nanti kita buat) --}}
            <form action="{{ route('reports.generate') }}" method="GET" id="reportForm">
                <input type="hidden" name="report_type" id="reportTypeInput">

                <div class="p-4 modal-body">
                    <div class="mb-4 border alert alert-light small text-muted">
                        Silakan tentukan periode tanggal data yang ingin Anda tarik ke dalam laporan ini.
                    </div>

                    <div class="row g-3">
                        <div class="col-6">
                            <label class="mb-1 fw-bold text-dark small">Tanggal Awal <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" class="form-control" required value="{{ date('Y-m-01') }}">
                        </div>
                        <div class="col-6">
                            <label class="mb-1 fw-bold text-dark small">Tanggal Akhir <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" class="form-control" required value="{{ date('Y-m-t') }}">
                        </div>
                    </div>
                </div>

                <div class="py-3 bg-light modal-footer border-top-0 rounded-bottom-4 d-flex justify-content-between">
                    <button type="button" class="px-4 btn btn-outline-secondary fw-bold rounded-pill" data-bs-dismiss="modal">Batal</button>
                    <div class="gap-2 d-flex">
                        <button type="submit" class="px-3 shadow-sm btn btn-success fw-bold rounded-pill">
                            <i class="bi bi-file-earmark-excel-fill me-1"></i> Download Excel
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('css')
<style>
    .widget-card { transition: transform 0.2s ease, box-shadow 0.2s ease; cursor: pointer; }
    .widget-card:hover { transform: translateY(-5px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; border-bottom: 3px solid #0d6efd !important;}
    .icon-box { display: flex; align-items: center; justify-content: center; border-radius: 12px; }
</style>
@endpush

@push('scripts')
<script>
    // Script Dinamis untuk mengubah Judul Modal sesuai Kartu yang diklik
    document.addEventListener('DOMContentLoaded', function () {
        var reportCards = document.querySelectorAll('.report-card');
        reportCards.forEach(function(card) {
            card.addEventListener('click', function() {
                var title = this.getAttribute('data-title');
                var type = this.getAttribute('data-type');

                document.getElementById('reportModalTitle').innerHTML = '<i class="bi bi-funnel-fill me-2"></i>Filter: ' + title;
                document.getElementById('reportTypeInput').value = type;
            });
        });
    });
</script>
@endpush
