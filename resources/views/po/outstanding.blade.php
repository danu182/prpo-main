@extends('layouts.app')

@push('css')
<style>
    .card-table-wrapper { border-radius: 16px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); background: #fff; }
    .table-modern { margin-bottom: 0; }
    .table-modern thead th { background-color: #f8fafc; color: #475569; font-size: 0.75rem; text-transform: uppercase; padding: 1.2rem 1rem; border-bottom: 2px solid #e2e8f0; font-weight: 800; border-top: none; }
    .table-modern tbody td { padding: 1rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; color: #334155; font-size: 0.85rem; }
    .table-modern tbody tr:hover { background-color: #f8fafc !important; }
    .filter-input { border-color: #cbd5e1; height: 42px; }
</style>
@endpush

@section('content')
<div class="pb-5 container-fluid px-md-4">

    <div class="mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3">
        <div>
            <h3 class="mb-0 fw-bold text-dark d-flex align-items-center">
                <i class="bi bi-hourglass-split text-warning me-2"></i> Laporan Outstanding PO
            </h3>
            <p class="mt-1 mb-0 text-muted small">Daftar Purchase Order yang masih menggantung (belum selesai di-GR).</p>
        </div>
        <div>
            <button class="shadow-sm btn btn-outline-dark fw-bold rounded-pill" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Cetak Laporan
            </button>
        </div>
    </div>

    {{-- FILTER SUPER LENGKAP --}}
    <div class="mb-4 bg-white border-0 border-4 shadow-sm border-top card rounded-4 border-warning">
        <div class="p-4 card-body">
            <form action="{{ route('po.outstanding') }}" method="GET">
                <div class="row g-3 align-items-end">

                    {{-- Filter Tgl --}}
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted text-uppercase">Rentang Tgl PO</label>
                        <div class="overflow-hidden shadow-sm input-group rounded-3">
                            <input type="date" name="start_date" class="form-control filter-input fw-bold text-primary" value="{{ $startDate }}" required>
                            <span class="text-white input-group-text bg-primary border-primary">s/d</span>
                            <input type="date" name="end_date" class="form-control filter-input fw-bold text-primary" value="{{ $endDate }}" required>
                        </div>
                    </div>

                    {{-- Filter PT --}}
                    <div class="col-md-2">
                        <label class="form-label fw-bold small text-muted text-uppercase">Perusahaan (PT)</label>
                        <select name="company_id" class="shadow-sm form-select filter-input">
                            <option value="">-- Semua PT --</option>
                            @foreach($companies as $pt)
                                <option value="{{ $pt->id }}" {{ $companyId == $pt->id ? 'selected' : '' }}>{{ $pt->code ?? $pt->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Filter Vendor --}}
                    <div class="col-md-2">
                        <label class="form-label fw-bold small text-muted text-uppercase">Vendor</label>
                        <select name="vendor_id" class="shadow-sm form-select filter-input">
                            <option value="">-- Semua Vendor --</option>
                            @foreach($vendors as $v)
                                <option value="{{ $v->id }}" {{ $vendorId == $v->id ? 'selected' : '' }}>{{ $v->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Search Barang / PO --}}
                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-muted text-uppercase">Cari PO / Barang</label>
                        <div class="overflow-hidden shadow-sm input-group rounded-3">
                            <span class="bg-light input-group-text border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" name="search" class="form-control filter-input border-start-0 ps-0" placeholder="Ketik nama barang / No PO..." value="{{ $search }}">
                        </div>
                    </div>

                    {{-- Tombol --}}
                    <div class="col-md-1">
                        <button type="submit" class="shadow-sm btn btn-dark w-100 fw-bold" style="height: 42px;" title="Cari Data">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- TABEL OUTSTANDING --}}
    <div class="mb-4 border-4 card-table-wrapper border-top border-dark">
        <div class="p-0 table-responsive">
            <table class="table align-middle table-modern text-nowrap">
                <thead>
                    <tr>
                        <th width="5%" class="text-center">No</th>
                        <th width="15%">Usia & Tgl PO</th>
                        <th width="20%">No. PO & Perusahaan</th>
                        <th width="20%">Vendor</th>
                        <th class="text-center bg-light" width="10%">Qty Dipesan</th>
                        <th class="text-center bg-success-subtle text-success" width="10%">Sudah GR</th>
                        <th class="text-center bg-danger-subtle text-danger" width="10%">Sisa Gantung</th>
                        <th class="text-center pe-4" width="10%">Aksi Detail</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($outstandingPos as $index => $po)
                        <tr>
                            <td class="text-center fw-bold text-muted">{{ $outstandingPos->firstItem() + $index }}</td>

                            {{-- Usia PO --}}
                            <td>
                                <div class="mb-1 text-danger fw-bold"><i class="bi bi-clock-history me-1"></i> {{ \Carbon\Carbon::parse($po->created_at)->diffInDays(now()) }} Hari Lalu</div>
                                <span class="small text-muted">{{ \Carbon\Carbon::parse($po->created_at)->format('d M Y') }}</span>
                            </td>

                            {{-- Identitas PO & PT --}}
                            <td>
                                <div class="mb-1"><a href="{{ route('po.show', $po->po_number) }}" class="fw-bold text-primary text-decoration-none">{{ $po->po_number }}</a></div>
                                <span class="badge border border-secondary text-secondary bg-light"><i class="bi bi-building me-1"></i> {{ optional($po->company)->code ?? optional($po->company)->name ?? 'PT -' }}</span>
                            </td>

                            {{-- Vendor --}}
                            <td>
                                <div class="fw-bold text-dark text-wrap" style="max-width: 200px;"><i class="bi bi-shop text-info me-1"></i> {{ optional($po->vendor)->name ?? 'Vendor Unknown' }}</div>
                            </td>

                            {{-- Angka-Angka --}}
                            <td class="text-center bg-light fw-bold fs-6">{{ number_format($po->total_qty_ordered, 0, ',', '.') }}</td>
                            <td class="text-center bg-success-subtle text-success fw-bold fs-6">{{ number_format($po->total_qty_received, 0, ',', '.') }}</td>
                            <td class="text-center bg-danger-subtle text-danger fw-bold fs-5 border-end border-danger">{{ number_format($po->qty_sisa, 0, ',', '.') }}</td>

                            {{-- Tombol Aksi (Hanya ke Detail) --}}
                            <td class="text-center pe-4">
                                <a href="{{ route('po.show', $po->po_number) }}" class="shadow-sm btn btn-dark fw-bold rounded-pill">
                                    Buka & Proses <i class="bi bi-arrow-right ms-1"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-5 text-center">
                                <i class="mb-3 bi bi-check-circle text-success display-4 d-block"></i>
                                <h6 class="fw-bold">Data Outstanding Bersih!</h6>
                                <p class="small text-muted">Tidak ada PO yang menggantung pada rentang tanggal dan filter tersebut.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($outstandingPos->hasPages())
        <div class="py-3 bg-white border-top card-footer d-flex justify-content-end">
            {{ $outstandingPos->links('pagination::bootstrap-5') }}
        </div>
        @endif
    </div>

</div>
@endsection
