@extends('layouts.app')

@push('css')
<style>
    .hover-shadow:hover {
        transform: translateY(-3px);
        box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important;
    }
    .transition-all {
        transition: all 0.2s ease-in-out;
    }
</style>
@endpush

@section('content')
<div class="container-fluid pb-5 text-dark">

    <div class="gap-3 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-end">
        <div>
            <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-file-earmark-text me-2 text-primary"></i>Detail Mutasi Gudang</h4>
            <div class="text-muted small">
                Dokumen: <strong class="text-primary">{{ $transfer->transfer_number }}</strong>
            </div>
        </div>
        <div class="gap-2 d-flex">
            <a href="{{ route('stock-transfers.index') }}" class="bg-white shadow-sm btn btn-outline-secondary rounded-pill fw-bold">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
            <a href="{{ route('stock-transfers.print', $transfer->id) }}" target="_blank" class="shadow-sm btn btn-dark rounded-pill fw-bold">
                <i class="bi bi-printer-fill me-1"></i> Cetak Surat Jalan
            </a>
        </div>
    </div>

    <div class="mb-4 border-0 border-4 shadow-sm card rounded-4 border-start border-primary bg-light">
        <div class="p-4 card-body">
            <div class="row g-4">
                <div class="col-md-5 border-end">
                    <h6 class="mb-3 fw-bold text-secondary text-uppercase" style="font-size: 0.8rem;"><i class="bi bi-info-square me-1"></i> Informasi Pemindahan</h6>
                    <table class="table mb-0 table-sm table-borderless small">
                        <tr><td class="text-muted" width="40%">Tanggal Mutasi</td><td class="fw-bold text-dark">: {{ \Carbon\Carbon::parse($transfer->transfer_date)->format('d M Y') }}</td></tr>
                        <tr><td class="text-muted">Diproses Oleh</td><td class="fw-bold text-dark">: {{ optional($transfer->creator)->name }}</td></tr>
                        <tr>
                            <td class="align-middle text-muted">Status Mutasi</td>
                            <td class="align-middle fw-bold">:
                                <span class="text-success"><i class="bi bi-check-circle-fill me-1"></i> Selesai / Terkirim</span>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="col-md-7">
                    <h6 class="mb-3 fw-bold text-secondary text-uppercase" style="font-size: 0.8rem;"><i class="bi bi-signpost-split me-1"></i> Rute Gudang</h6>
                    <div class="p-3 bg-white border rounded-3 d-flex align-items-center justify-content-around border-secondary-subtle">
                        <div class="text-center">
                            <div class="small fw-bold text-danger text-uppercase">DARI (ASAL)</div>
                            <div class="fw-bold text-dark fs-5">{{ optional($transfer->fromWarehouse)->name }}</div>
                        </div>
                        <div>
                            <i class="bi bi-arrow-right-circle-fill text-muted fs-3"></i>
                        </div>
                        <div class="text-center">
                            <div class="small fw-bold text-success text-uppercase">KE (TUJUAN)</div>
                            <div class="fw-bold text-dark fs-5">{{ optional($transfer->toWarehouse)->name }}</div>
                        </div>
                    </div>
                </div>
            </div>

            @if($transfer->notes)
            <div class="p-3 mt-4 bg-white border rounded-3 border-info-subtle small text-info-emphasis">
                <strong><i class="bi bi-journal-text me-1"></i> Catatan Mutasi:</strong> {{ $transfer->notes }}
            </div>
            @endif
        </div>
    </div>

    <div class="border-0 shadow-sm card rounded-4">
        <div class="py-3 bg-white card-header border-bottom">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-box-seam me-2 text-primary"></i>Rincian Barang yang Dimutasi</h6>
        </div>
        <div class="p-0 card-body table-responsive">
            <table class="table mb-0 align-middle table-hover">
                <thead class="bg-light text-muted small text-uppercase">
                    <tr>
                        <th class="py-3 ps-4" width="5%">No</th>
                        <th class="py-3" width="35%">Nama Barang & Kode</th>
                        <th class="py-3 text-center" width="20%">Qty Dipindah</th>
                        <th class="py-3 pe-4" width="40%">Catatan / Nomor Seri (SN) Aset</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transfer->items as $index => $item)
                    @php
                        $masterItem = $item->item;
                        $masterName = optional($masterItem)->name ?? '-';

                        // 🔥 Tarik nama spesifik (alias) yang disimpan saat mutasi 🔥
                        $specificName = $item->item_name ?? $masterName;

                        $isAsset = optional($masterItem)->is_asset ?? false;

                        // 🔥 Ambil UOM asli dari database, jangan di-hardcode 🔥
                        $uomAsli = $item->uom ?? optional($masterItem->uom)->name ?? 'PCS';
                    @endphp
                    <tr class="border-bottom">
                        <td class="py-3 ps-4 text-muted">{{ $index + 1 }}</td>
                        <td>
                            {{-- 🔥 MENAMPILKAN NAMA SPESIFIK & MASTER BERSUSUN 🔥 --}}
                            <h6 class="mb-0 fw-bold text-dark text-uppercase">{{ $specificName }}</h6>

                            @if(strtolower(trim($specificName)) !== strtolower(trim($masterName)))
                                <div class="mt-1 mb-1 text-muted" style="font-size: 0.75rem;">
                                    <i class="bi bi-box me-1"></i>Master: {{ $masterName }}
                                </div>
                            @endif

                            <div class="gap-2 mt-1 d-flex align-items-center">
                                <span class="border badge bg-secondary-subtle text-secondary border-secondary-subtle">{{ optional($masterItem)->code }}</span>
                                @if($isAsset)
                                    <span class="border badge bg-primary-subtle text-primary border-primary-subtle" style="font-size: 0.65rem;">🏢 Aset Tetap</span>
                                @endif
                            </div>
                        </td>
                        <td class="py-3 text-center align-middle">
                            <div class="fw-bold text-primary fs-5">{{ (float) $item->qty_transferred }}</div>
                            {{-- 🔥 SATUAN SEKARANG MEMANGGIL DATA ASLI DARI DATABASE 🔥 --}}
                            <div class="text-muted fw-bold text-uppercase" style="font-size: 0.7rem;">
                                {{ $uomAsli }}
                            </div>
                        </td>
                        <td class="py-3 pe-4 text-muted small">
                            @if($item->notes)
                                <div class="p-2 border rounded bg-light border-secondary-subtle">
                                    {{ $item->notes }}
                                </div>
                            @else
                                <span class="opacity-50 fst-italic">- Tidak ada catatan spesifik -</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
