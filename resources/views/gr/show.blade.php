@extends('layouts.app')

@section('content')
<div class="container pb-5 text-dark">

    {{-- HEADER HALAMAN --}}
    <div class="gap-3 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <div class="gap-2 mb-1 d-flex align-items-center">
                <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-file-earmark-check me-2 text-success"></i>Detail Penerimaan Barang</h4>
                <span class="px-3 border badge bg-success-subtle text-success border-success-subtle rounded-pill">Tersimpan</span>
            </div>
            <div class="text-muted small">
                Dokumen GR: <strong class="text-primary">{{ $gr->gr_number }}</strong>
            </div>
        </div>
        <div class="gap-2 d-flex">
            <a href="{{ route('gr.index') }}" class="px-4 bg-white shadow-sm btn btn-outline-secondary rounded-pill fw-bold">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
            <button onclick="window.print()" class="px-4 shadow-sm btn btn-primary rounded-pill fw-bold">
                <i class="bi bi-printer me-1"></i> Cetak / PDF
            </button>
        </div>
    </div>

    {{-- 1. INFORMASI SURAT JALAN & PENERIMAAN --}}
    <div class="mb-4 border-0 shadow-sm card rounded-4">
        <div class="p-4 card-body bg-light rounded-4">
            <div class="row g-4">
                <div class="col-md-3">
                    <label class="mb-1 small fw-bold text-muted text-uppercase">No. Penerimaan (GR)</label>
                    <div class="fw-bolder fs-6 text-dark">{{ $gr->gr_number }}</div>
                </div>
                <div class="col-md-3">
                    <label class="mb-1 small fw-bold text-muted text-uppercase">Tgl. Terima Fisik</label>
                    <div class="fw-bold text-dark"><i class="bi bi-calendar-check text-success me-1"></i> {{ \Carbon\Carbon::parse($gr->received_date)->format('d F Y') }}</div>
                </div>
                <div class="col-md-3">
                    <label class="mb-1 small fw-bold text-muted text-uppercase">No. Surat Jalan Vendor</label>
                    <div class="fw-bold text-dark"><i class="bi bi-truck text-secondary me-1"></i> {{ $gr->delivery_note_number }}</div>
                </div>
                <div class="col-md-3">
                    <label class="mb-1 small fw-bold text-muted text-uppercase">Staf Penerima</label>
                    <div class="fw-bold text-dark"><i class="bi bi-person-badge text-primary me-1"></i> {{ optional($gr->receiver)->name ?? 'Sistem' }}</div>
                </div>

                {{-- BARIS BAWAH: PO, VENDOR, GUDANG, CATATAN --}}
                <div class="pt-3 col-md-3 border-top">
                    <label class="mb-1 small fw-bold text-muted text-uppercase">Referensi PO</label>
                    <div class="fw-bolder text-primary">
                        <a href="{{ route('po.show', optional($gr->purchaseOrder)->po_number) }}" class="text-decoration-none">
                            {{ optional($gr->purchaseOrder)->po_number ?? '-' }}
                        </a>
                    </div>
                </div>

                <div class="pt-3 col-md-3 border-top">
                    <label class="mb-1 small fw-bold text-muted text-uppercase">Vendor Pengirim</label>
                    <div class="fw-bold text-dark text-truncate" title="{{ optional(optional($gr->purchaseOrder)->vendor)->name ?? '-' }}">
                        <i class="bi bi-shop text-warning me-1"></i> {{ optional(optional($gr->purchaseOrder)->vendor)->name ?? '-' }}
                    </div>
                </div>

                {{-- 🔥 INI KOLOM GUDANG PENERIMA YANG BARU 🔥 --}}
                <div class="pt-3 col-md-3 border-top">
                    <label class="mb-1 small fw-bold text-muted text-uppercase">Gudang Penerima</label>
                    <div class="fw-bold text-dark">
                        <span class="px-2 py-1 border shadow-sm badge bg-light text-dark">
                            <i class="bi bi-box-seam text-info me-1"></i> {{ optional($gr->warehouse)->name ?? 'Gudang Utama / Default' }}
                        </span>
                    </div>
                </div>

                <div class="pt-3 col-md-3 border-top">
                    <label class="mb-1 small fw-bold text-muted text-uppercase">Catatan Penerimaan</label>
                    <div class="text-dark fst-italic" style="font-size: 0.85rem;">{{ $gr->notes ?: 'Tidak ada catatan khusus.' }}</div>
                </div>

            </div>
        </div>
    </div>

    {{-- LAMPIRAN GR --}}
    @if($gr->attachments && $gr->attachments->count() > 0)
    <div class="mb-4">
        <h6 class="mb-2 fw-bold text-dark"><i class="bi bi-paperclip me-1 text-primary"></i> Dokumen Surat Jalan / Foto Bukti</h6>
        <div class="flex-wrap gap-2 d-flex">
            @foreach($gr->attachments as $file)
                <a href="{{ asset('storage/' . $file->file_path) }}" target="_blank" class="gap-2 p-2 bg-white border shadow-sm badge text-dark border-secondary-subtle text-decoration-none d-flex align-items-center">
                    <i class="bi bi-file-earmark-text fs-5 text-danger"></i>
                    <span class="fw-medium">{{ $file->file_name }}</span>
                </a>
            @endforeach
        </div>
    </div>
    @endif

    {{-- 2. TABEL ITEM YANG DITERIMA --}}
    <div class="mb-4 overflow-hidden border-0 border-4 shadow-sm card rounded-4 border-top border-primary">
        <div class="px-4 py-3 bg-white card-header border-bottom">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-list-check me-2 text-primary"></i>Rincian Barang yang Diterima</h6>
        </div>
        <div class="p-0 card-body table-responsive">
            <table class="table mb-0 align-middle table-hover">
                <thead class="bg-light text-muted small text-uppercase fw-bold border-bottom">
                    <tr>
                        <th class="py-3 ps-4" width="25%">Barang & Spesifikasi</th>
                        <th class="py-3 text-center" width="12%">Qty Order (PO)</th>
                        <th class="py-3 text-center" width="15%">Qty Diterima</th>
                        <th class="py-3" width="13%">Kondisi</th>
                        <th class="py-3 pe-4" width="35%">Catatan Staf & Serial Number Terdaftar</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($gr->items as $item)
                    <tr>
                        {{-- NAMA BARANG --}}
                        <td class="py-3 ps-4">
                            <div class="fw-bolder text-dark" style="font-size: 0.95rem;">
                                {{ optional($item->item)->name ?? 'Nama Barang Tidak Ditemukan' }}
                            </div>
                            <div class="gap-1 mt-1 mb-2 d-flex">
                                <span class="border badge bg-secondary-subtle text-secondary">{{ optional($item->item)->code }}</span>
                                @if(optional($item->item)->is_trackable || optional($item->item)->is_asset)
                                    <span class="border badge bg-warning-subtle text-warning border-warning"><i class="bi bi-upc-scan me-1"></i>Tracked (SN)</span>
                                @endif
                            </div>
                        </td>

                        {{-- QTY PO --}}
                        <td class="py-3 text-center fw-bold text-secondary">
                            @php
                                $poItem = $item->purchaseOrderItem;
                                $poUomText = 'PCS'; // Default cadangan

                                if ($poItem) {
                                    // 1. Cek apakah PO menggunakan uom_id (Relasi ke master satuan Pack/Dus)
                                    if (!empty($poItem->uom_id) && optional($item->item)->itemUoms) {
                                        $uomMaster = collect($item->item->itemUoms)->where('id', $poItem->uom_id)->first();
                                        if ($uomMaster) {
                                            $poUomText = $uomMaster->uom_name;
                                            if ($uomMaster->conversion_qty > 1) {
                                                // Rangkai teks: Pack (Isi 20 Pcs)
                                                $baseName = optional(optional($item->item)->uom)->name ?? 'Pcs';
                                                $poUomText .= ' (Isi ' . (float)$uomMaster->conversion_qty . ' ' . $baseName . ')';
                                            }
                                        }
                                    }
                                    // 2. Fallback jika PO hanya menggunakan teks biasa
                                    elseif (!empty($poItem->uom)) {
                                        $poUomText = is_string($poItem->uom) ? $poItem->uom : (optional($poItem->uom)->name ?? 'PCS');
                                    }
                                }
                            @endphp

                            {{ (float)optional($poItem)->qty_ordered }} <br>
                            <span class="text-nowrap fw-normal text-uppercase" style="font-size: 0.65rem;">
                                {{ $poUomText }}
                            </span>
                        </td>

                        {{-- QTY GR (DITERIMA) --}}
                        <td class="py-3 text-center fw-bold text-success fs-5">
                            + {{ (float)$item->qty_received }} <br>
                            <span class="text-nowrap fw-bold text-muted" style="font-size: 0.65rem; text-transform: uppercase;">
                                {{-- Menggunakan getRawOriginal() untuk memastikan kita mengambil Teks (Varchar) dari DB, bukan relasi ID --}}
                                {{ $item->getRawOriginal('uom') ?: 'PCS' }}
                            </span>
                        </td>

                        {{-- KONDISI --}}
                        <td class="py-3">
                            <span class="px-3 py-2 border badge bg-light text-dark border-secondary-subtle">
                                {{ optional($item->condition)->name ?? 'Sesuai / Baik' }}
                            </span>
                        </td>

                        {{-- CATATAN & SERIAL NUMBER --}}
                        <td class="text-start">
                            {{-- 1. Tampilkan Catatan Asli Staf --}}
                            <div class="mb-2 text-dark">
                                {{ $item->notes ?? 'Tidak ada catatan khusus.' }}
                            </div>

                            {{-- 2. Tampilkan Nomor Register / SN dalam bentuk Kotak-Kotak (Badge) --}}
                            @if(!empty($item->registered_sns))
                                <div class="p-2 border rounded bg-light border-secondary-subtle">
                                    <span class="mb-2 d-block small text-muted fw-bold">
                                        <i class="bi bi-upc-scan me-1"></i> Daftar SN / Register:
                                    </span>

                                    <div class="flex-wrap gap-1 d-flex">
                                        {{-- Tampilkan maksimal 5 kotak pertama saja --}}
                                        @foreach(array_slice($item->registered_sns, 0, 5) as $sn)
                                            <span class="px-2 py-1 bg-white border shadow-sm badge text-dark border-secondary-subtle" style="font-size: 0.75rem;">
                                                {{ $sn }}
                                            </span>
                                        @endforeach

                                        {{-- Jika jumlahnya lebih dari 5, munculkan tombol Lihat Semua --}}
                                        @if(count($item->registered_sns) > 5)
                                            <span class="px-2 py-1 shadow-sm badge bg-primary" style="cursor: pointer;" data-bs-toggle="modal" data-bs-target="#snModal-{{ $item->id }}">
                                                + {{ count($item->registered_sns) - 5 }} Lainnya
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                {{-- 3. Modal Popup untuk menampilkan keseluruhan 100 SN --}}
                                @if(count($item->registered_sns) > 5)
                                <div class="modal fade" id="snModal-{{ $item->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                                        <div class="border-0 shadow modal-content rounded-4">
                                            <div class="modal-header bg-light border-bottom-0">
                                                <h6 class="modal-title fw-bold text-dark">
                                                    <i class="bi bi-upc-scan text-primary me-2"></i>Daftar Lengkap ({{ count($item->registered_sns) }} Unit)
                                                </h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="pt-0 modal-body bg-light">
                                                <div class="row g-2">
                                                    @foreach($item->registered_sns as $index => $sn)
                                                    <div class="col-md-6 col-12">
                                                        <div class="p-2 text-center bg-white border rounded shadow-sm text-dark fw-bold small">
                                                            <span class="text-muted me-1">{{ $index + 1 }}.</span> {{ $sn }}
                                                        </div>
                                                    </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endif
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>

<style>
    @media print {
        body { background-color: #fff !important; }
        .navbar, .sidebar, .btn, footer { display: none !important; }
        .card { border: none !important; box-shadow: none !important; }
        .container { max-width: 100% !important; padding: 0 !important; }
    }
</style>
@endsection
