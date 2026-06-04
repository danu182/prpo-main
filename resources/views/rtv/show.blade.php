@extends('layouts.app')

@section('content')
<div class="container pb-5 text-dark">

    <div class="mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3">
        <div>
            <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-file-earmark-text me-2 text-danger"></i>Detail Return to Vendor (RTV)</h4>
            <div class="text-muted small">
                Dokumen RTV: <strong class="text-danger">{{ $rtv->rtv_number }}</strong>
            </div>
        </div>
        <div class="gap-2 d-flex">
            <a href="{{ route('rtv.index') }}" class="bg-white shadow-sm btn btn-outline-secondary rounded-pill fw-bold">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
            <button class="shadow-sm btn btn-primary rounded-pill fw-bold" onclick="window.print()">
                <i class="bi bi-printer-fill me-1"></i> Cetak Surat Jalan Retur
            </button>
        </div>
    </div>

    <div class="mb-4 border-0 border-4 shadow-sm card rounded-4 border-start border-danger bg-light">
        <div class="p-4 card-body">
            <div class="row g-4">
                <div class="col-md-4 border-end">
                    <h6 class="mb-3 fw-bold text-secondary text-uppercase" style="font-size: 0.8rem;">Informasi Retur</h6>
                    <table class="table mb-0 table-sm table-borderless small">
                        <tr><td class="text-muted" width="45%">Tanggal Retur</td><td class="fw-bold">: {{ \Carbon\Carbon::parse($rtv->return_date)->format('d M Y') }}</td></tr>
                        <tr><td class="text-muted">Surat Jalan Keluar</td><td class="fw-bold text-dark">: {{ $rtv->delivery_note_number ?? 'Tidak ada' }}</td></tr>
                        <tr><td class="text-muted">Diproses Oleh</td><td class="fw-bold">: {{ optional($rtv->returner)->name }}</td></tr>
                    </table>
                </div>

                <div class="col-md-4 border-end">
                    <h6 class="mb-3 fw-bold text-secondary text-uppercase" style="font-size: 0.8rem;">Referensi Dokumen Awal</h6>
                    <table class="table mb-0 table-sm table-borderless small">
                        {{-- 🔥 FIX BUG SLUG GR 🔥 --}}
                        <tr><td class="text-muted" width="40%">No. Penerimaan</td><td class="fw-bold text-success">: <a href="{{ route('gr.show', optional($rtv->goodsReceipt)->gr_number ?? '') }}" class="text-success text-decoration-none fw-bold">{{ optional($rtv->goodsReceipt)->gr_number }}</a></td></tr>
                        <tr><td class="text-muted">Nomor PO</td><td class="fw-bold text-primary">: <a href="{{ route('po.show', optional(optional($rtv->goodsReceipt)->po)->po_number ?? '') }}" class="text-primary text-decoration-none fw-bold">{{ optional(optional($rtv->goodsReceipt)->po)->po_number }}</a></td></tr>
                        <tr><td class="text-muted">PT Pemilik</td><td class="fw-bold">: {{ optional(optional(optional($rtv->goodsReceipt)->po)->company)->name ?? '-' }}</td></tr>
                    </table>
                </div>

                <div class="col-md-4">
                    <h6 class="mb-3 fw-bold text-secondary text-uppercase" style="font-size: 0.8rem;">Tujuan Vendor (Supplier)</h6>
                    <div class="fw-bold text-dark fs-6">{{ optional($rtv->vendor)->name }}</div>
                    <div class="mt-1 text-muted small">
                        <i class="bi bi-geo-alt-fill me-1"></i> {{ optional($rtv->vendor)->address ?? 'Alamat tidak tersedia' }}
                    </div>
                </div>
            </div>

            @if($rtv->notes)
            <div class="p-3 mt-4 bg-white border rounded-3 border-danger-subtle small text-danger-emphasis">
                <strong><i class="bi bi-journal-text me-1"></i> Catatan Retur Umum:</strong> {{ $rtv->notes }}
            </div>
            @endif
        </div>
    </div>

    <div class="border-0 shadow-sm card rounded-4">
        <div class="py-3 bg-white card-header border-bottom">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-box-seam me-2 text-danger"></i>Rincian Barang Dikembalikan</h6>
        </div>
        <div class="p-0 card-body table-responsive">
            <table class="table mb-0 align-middle table-hover">
                <thead class="bg-light text-muted small text-uppercase">
                    <tr>
                        <th class="py-3 ps-4" width="5%">No</th>
                        <th class="py-3" width="30%">Nama Barang & Kode</th>
                        <th class="py-3 text-center" width="15%">Qty Diretur</th>
                        <th class="py-3 pe-4" width="50%">Alasan & Keterangan Pengembalian</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rtv->items as $index => $item)
                        @php
                            $masterItem = $item->item;
                            $isStockable = optional($masterItem)->is_stockable ?? true;
                            $isAsset = optional($masterItem)->is_asset ?? false;
                            
                            // Ekstrak satuan dari return_reason (karena qty_returned hanya angka)
                            // Format: "Diretur: 10 Pcs (= 10 Eceran). Alasan: Rusak"
                            $keteranganLengkap = $item->return_reason;
                            $satuanTampil = '';
                            
                            // Pola RegEx untuk mencari kata setelah angka retur. Cth: mencari "Pcs" dari "Diretur: 10 Pcs"
                            if (preg_match('/Diretur:\s*[\d\.]+\s+([A-Za-z0-9_\-\.]+)/i', $keteranganLengkap, $matches)) {
                                $satuanTampil = $matches[1];
                            }
                        @endphp
                        <tr class="border-bottom">
                            <td class="py-3 ps-4 text-muted">{{ $index + 1 }}</td>
                            <td>
                                <h6 class="mb-1 fw-bold text-dark">{{ optional($masterItem)->name }}</h6>
                                <div class="gap-2 mt-1 d-flex align-items-center">
                                    <span class="small text-muted">{{ optional($masterItem)->code }}</span>
                                    @if($isAsset)
                                        <span class="border badge bg-primary-subtle text-primary border-primary-subtle" style="font-size: 0.65rem;">🏢 Aset Tetap</span>
                                    @elseif($isStockable)
                                        <span class="border badge bg-success-subtle text-success border-success-subtle" style="font-size: 0.65rem;">📦 Stok</span>
                                    @else
                                        <span class="border badge bg-warning-subtle text-warning border-warning-subtle" style="font-size: 0.65rem;">🛠️ Non-Stok</span>
                                    @endif
                                </div>
                            </td>
                            <td class="text-center fw-bold text-danger fs-5">
                                {{ (float) $item->qty_returned }}
                                @if($satuanTampil)
                                    <br><span class="text-muted fw-normal" style="font-size: 0.75rem;">{{ $satuanTampil }}</span>
                                @endif
                            </td>
                            <td class="pe-4 text-dark small">
                                <div class="p-2 border rounded bg-light border-warning-subtle text-danger-emphasis">
                                    <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i> 
                                    {{ $item->return_reason }}
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection