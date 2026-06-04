@extends('layouts.app')

@push('css')
<style>
    @media print {
        body { background-color: white; margin: 0; padding: 0; }
        .navbar, .btn, .d-print-none { display: none !important; }
        .card { border: none !important; box-shadow: none !important; }
        .card-header { background-color: transparent !important; border-bottom: 2px solid #000 !important; padding-left: 0 !important; padding-right: 0 !important; }
        .table { border-color: #000 !important; }
        .table th { background-color: #f8f9fa !important; color: #000 !important; -webkit-print-color-adjust: exact; }
    }
</style>
@endpush

@section('content')
<div class="container pb-5 text-dark">

    {{-- HEADER --}}
    <div class="gap-3 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center d-print-none">
        <div>
            <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-file-earmark-text text-primary me-2"></i> Detail Mutasi Gudang</h4>
            <div class="mt-1 text-muted small">Dokumen: <strong class="text-primary">{{ $transfer->transfer_number }}</strong></div>
        </div>
        <div class="gap-2 d-flex">
            <a href="{{ route('stock-transfers.index') }}" class="px-4 border shadow-sm btn btn-light rounded-pill fw-bold">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
            <a href="{{ route('stock-transfers.print', $transfer->id) }}" target="_blank" class="px-4 shadow-sm btn btn-dark rounded-pill fw-bold">
                <i class="bi bi-printer me-2"></i> Cetak Surat Jalan
            </a>
        </div>
    </div>

    {{-- KOP SURAT (MUNCUL SAAT PRINT) --}}
    <div class="pb-3 mb-4 text-center border-2 d-none d-print-block border-bottom border-dark">
        <h3 class="mb-0 fw-bolder text-uppercase">BUKTI MUTASI ANTAR GUDANG</h3>
        <p class="mb-0 fw-bold fs-5">No. {{ $transfer->transfer_number }}</p>
    </div>

    {{-- CARD INFORMASI TRANSFER --}}
    <div class="mb-4 border-0 border-4 shadow-sm card rounded-4 border-start border-primary">
        <div class="p-4 card-body">
            <div class="row g-4">
                {{-- Info Kiri --}}
                <div class="col-md-5 border-end border-light-subtle">
                    <h6 class="mb-3 text-muted fw-bold small text-uppercase"><i class="bi bi-info-square me-2"></i>Informasi Pemindahan</h6>
                    <table class="table mb-0 table-sm table-borderless small">
                        <tr>
                            <td class="text-muted" width="40%">Tanggal Mutasi</td>
                            <td class="fw-bold text-dark">: {{ \Carbon\Carbon::parse($transfer->transfer_date)->format('d M Y') }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Diproses Oleh</td>
                            <td class="fw-bold text-dark">: {{ optional($transfer->creator)->name ?? 'Sistem' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Status Mutasi</td>
                            <td class="fw-bold text-success">: <i class="bi bi-check-circle-fill me-1"></i>Selesai / Terkirim</td>
                        </tr>
                    </table>
                </div>

                {{-- Info Kanan (Rute) --}}
                <div class="col-md-7 ps-md-4">
                    <h6 class="mb-3 text-muted fw-bold small text-uppercase"><i class="bi bi-signpost-split me-2"></i>Rute Gudang</h6>

                    <div class="p-3 border bg-light rounded-3 border-light-subtle d-flex align-items-center justify-content-between">
                        <div class="text-center w-100">
                            <div class="mb-1 text-danger small fw-bold">DARI (ASAL)</div>
                            <h5 class="mb-0 fw-bolder text-dark">{{ optional($transfer->fromWarehouse)->name ?? '-' }}</h5>
                        </div>

                        <div class="px-3 text-muted fs-3">
                            <i class="bi bi-arrow-right-circle-fill"></i>
                        </div>

                        <div class="text-center w-100">
                            <div class="mb-1 text-success small fw-bold">KE (TUJUAN)</div>
                            <h5 class="mb-0 fw-bolder text-dark">{{ optional($transfer->toWarehouse)->name ?? '-' }}</h5>
                        </div>
                    </div>

                    @if($transfer->notes)
                    <div class="p-2 mt-3 bg-warning-subtle rounded-3 small">
                        <span class="fw-bold text-warning-emphasis"><i class="bi bi-chat-quote me-1"></i> Catatan:</span> {{ $transfer->notes }}
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- CARD DAFTAR BARANG --}}
    <div class="border-0 border-4 shadow-sm card rounded-4 border-top border-primary">
        <div class="p-4 bg-white card-header border-bottom">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-box-seam me-2 text-primary"></i>Rincian Barang yang Dimutasi</h6>
        </div>

        <div class="p-0 card-body table-responsive">
            <table class="table mb-0 align-middle table-hover">
                <thead class="bg-light text-muted small text-uppercase">
                    <tr>
                        <th class="py-3 ps-4" width="5%">No</th>
                        <th class="py-3" width="35%">Nama Barang & Kode</th>
                        <th class="py-3 text-center" width="15%">Qty Dipindah</th>
                        <th class="py-3 pe-4" width="45%">Catatan / Nomor Seri (SN) Aset</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transfer->items as $index => $itemRow)
                        <tr class="border-bottom">
                            <td class="py-3 ps-4 fw-bold text-muted">{{ $index + 1 }}</td>
                            <td class="py-3">
                                <h6 class="mb-1 fw-bold text-dark">{{ optional($itemRow->item)->name }}</h6>
                                <span class="px-2 border badge bg-secondary-subtle text-secondary border-secondary-subtle">
                                    {{ optional($itemRow->item)->code ?? 'Tanpa Kode' }}
                                </span>
                                @if(optional($itemRow->item)->is_asset)
                                    <span class="px-2 border badge bg-primary-subtle text-primary border-primary-subtle ms-1">Aset Tetap</span>
                                @endif
                                @if(optional($itemRow->item)->is_trackable)
                                    <span class="px-2 border badge bg-warning-subtle text-warning-emphasis border-warning-subtle ms-1">Minor Asset</span>
                                @endif
                            </td>
                            <td class="py-3 text-center">
                                <span class="px-3 py-2 border fw-bold text-primary bg-light border-primary-subtle rounded-pill fs-6">
                                    {{ (float) $itemRow->qty_transferred }} {{ optional($itemRow->item)->unit ?? 'Unit' }}
                                </span>
                            </td>
                            <td class="py-3 pe-4 small text-muted">
                                @if($itemRow->notes)
                                    @php
                                        // Pecah catatan berdasarkan pemisah " | " (pipa) untuk membuat list yang rapi
                                        $notesArray = array_filter(array_map('trim', explode('|', $itemRow->notes)));
                                    @endphp
                                    <div class="gap-1 d-flex flex-column">
                                        @foreach($notesArray as $noteLine)
                                            <div class="p-1 px-2 border rounded bg-light border-light-subtle">
                                                <i class="bi bi-upc-scan me-2 text-secondary"></i><span class="text-dark fw-medium">{!! $noteLine !!}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="opacity-50 fst-italic">- Tidak ada catatan spesifik -</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-5 text-center text-muted">
                                <i class="mb-2 opacity-50 bi bi-inbox fs-1 d-block"></i>
                                Tidak ada rincian barang.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- AREA TTD UNTUK PRINT --}}
    <div class="mt-5 row d-none d-print-flex">
        <div class="text-center col-4">
            <p class="mb-5 small text-muted">Dikeluarkan Oleh (Gudang Asal),</p>
            <h6 class="mt-5 fw-bold text-dark text-decoration-underline">_______________________</h6>
        </div>
        <div class="text-center col-4">
            <p class="mb-5 small text-muted">Dibawa Oleh (Kurir/Logistik),</p>
            <h6 class="mt-5 fw-bold text-dark text-decoration-underline">_______________________</h6>
        </div>
        <div class="text-center col-4">
            <p class="mb-5 small text-muted">Diterima Oleh (Gudang Tujuan),</p>
            <h6 class="mt-5 fw-bold text-dark text-decoration-underline">_______________________</h6>
        </div>
    </div>

</div>
@endsection
