@extends('layouts.app')

@section('content')
<div class="container pb-5 text-dark">

    {{-- HEADER HALAMAN --}}
    <div class="mb-4 d-flex justify-content-between align-items-end">
        <div>
            <a href="{{ route('stock-adjustments.index') }}" class="mb-2 text-decoration-none text-muted small fw-bold d-inline-block">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Riwayat
            </a>
            <h4 class="mb-0 fw-bold text-dark">
                <i class="bi bi-file-earmark-text text-primary me-2"></i> Rincian Berita Acara Opname
            </h4>
            <div class="mt-1 text-muted small">ID Dokumen: <strong class="text-primary">{{ $adjustment->adjustment_number }}</strong></div>
        </div>
        <div class="gap-2 d-flex">
            {{-- Tombol Cetak (Opsional untuk masa depan) --}}
            <button onclick="window.print()" class="px-4 shadow-sm btn btn-dark rounded-pill fw-bold">
                <i class="bi bi-printer me-1"></i> Cetak Dokumen
            </button>
        </div>
    </div>

    {{-- KARTU INFORMASI HEADER --}}
    <div class="mb-4 border-0 shadow-sm card rounded-4 bg-light">
        <div class="p-4 card-body">
            <div class="row g-4 text-start">
                <div class="col-md-3 border-end">
                    <label class="mb-1 text-muted small fw-bold text-uppercase d-block">Tanggal Opname</label>
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="bi bi-calendar3 me-2 text-primary"></i>{{ \Carbon\Carbon::parse($adjustment->adjustment_date)->format('d F Y') }}
                    </h6>
                </div>
                <div class="col-md-3 border-end text-start">
                    <label class="mb-1 text-muted small fw-bold text-uppercase d-block">Lokasi Gudang</label>
                    <h6 class="mb-0 fw-bold text-dark text-start">
                        <i class="bi bi-shop me-2 text-success"></i>{{ optional($adjustment->warehouse)->name }}
                    </h6>
                </div>
                <div class="col-md-3 border-end text-start">
                    <label class="mb-1 text-muted small fw-bold text-uppercase d-block">Eksekutor (PIC)</label>
                    <h6 class="mb-0 fw-bold text-dark text-start">
                        <i class="bi bi-person-check me-2 text-warning"></i>{{ optional($adjustment->adjuster)->name ?? 'Sistem' }}
                    </h6>
                </div>
                <div class="col-md-3 text-start">
                    <label class="mb-1 text-muted small fw-bold text-uppercase d-block">Alasan / Keterangan</label>
                    <p class="mb-0 small text-dark lh-sm text-start">{{ $adjustment->reason }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- TABEL RINCIAN BARANG --}}
    <div class="overflow-hidden border-0 border-4 shadow-sm card rounded-4 border-top border-primary">
        <div class="py-3 bg-white card-header border-bottom">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-list-check me-2 text-primary"></i>Daftar Penyesuaian Barang</h6>
        </div>
        <div class="p-0 card-body">
            <div class="table-responsive">
                <table class="table mb-0 align-middle table-hover">
                    <thead class="bg-light text-muted small text-uppercase fw-bold border-bottom">
                        <tr>
                            <th class="py-3 ps-4" width="5%">No</th>
                            <th class="py-3" width="35%">Nama Barang & Kode</th>
                            <th class="py-3 text-center" width="15%">Stok Sistem</th>
                            <th class="py-3 text-center" width="15%">Stok Fisik</th>
                            <th class="py-3 text-center pe-4" width="30%">Selisih / Mutasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($adjustment->items as $index => $item)
                        <tr>
                            <td class="py-3 ps-4 text-muted fw-bold">{{ $index + 1 }}</td>
                            <td class="py-3">
                                <div class="fw-bold text-dark">{{ optional($item->item)->name }}</div>
                                <div class="small text-muted">[{{ optional($item->item)->code }}]</div>
                            </td>
                            <td class="py-3 text-center fw-bold text-muted">
                                {{ (float)$item->previous_stock }} <small>{{ optional($item->item)->unit }}</small>
                            </td>
                            <td class="py-3 text-center fw-bold text-primary fs-6">
                                {{ (float)$item->new_stock }} <small>{{ optional($item->item)->unit }}</small>
                            </td>
                            <td class="py-3 text-center pe-4">
                                @if($item->difference > 0)
                                    <div class="p-2 px-4 border border-success rounded-pill bg-success-subtle text-success fw-bold small d-inline-block">
                                        <i class="bi bi-plus-circle-fill me-1"></i> +{{ (float)$item->difference }} (Koreksi Tambah)
                                    </div>
                                @elseif($item->difference < 0)
                                    <div class="p-2 px-4 border border-danger rounded-pill bg-danger-subtle text-danger fw-bold small d-inline-block">
                                        <i class="bi bi-dash-circle-fill me-1"></i> {{ (float)$item->difference }} (Koreksi Kurang)
                                    </div>
                                @else
                                    <div class="p-2 px-4 border border-secondary rounded-pill bg-light text-muted fw-bold small d-inline-block">
                                        = 0 (Sesuai)
                                    </div>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="p-4 bg-light card-footer border-top small text-muted">
            <i class="bi bi-info-circle me-1"></i> Penyesuaian ini telah memicu mutasi stok otomatis pada kartu stok barang terkait.
        </div>
    </div>
</div>

<style>
    @media print {
        .btn, a, .card-footer { display: none !important; }
        .container { width: 100% !important; max-width: 100% !important; }
        .card { border: 1px solid #ddd !important; box-shadow: none !important; }
        .bg-light { background-color: #fff !important; }
    }
</style>
@endsection
