@extends('layouts.app')

@section('content')
<div class="container pb-5 text-dark">

    {{-- HEADER HALAMAN --}}
    <div class="gap-3 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-file-earmark-check me-2 text-success"></i>Detail Penerimaan Barang</h4>
                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3">Tersimpan</span>
            </div>
            <div class="text-muted small">
                Dokumen GR: <strong class="text-primary">{{ $gr->gr_number }}</strong>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('gr.index') }}" class="bg-white shadow-sm btn btn-outline-secondary rounded-pill fw-bold px-4">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
            <button onclick="window.print()" class="shadow-sm btn btn-primary rounded-pill fw-bold px-4">
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

                <div class="col-md-3 border-top pt-3">
                    <label class="mb-1 small fw-bold text-muted text-uppercase">Referensi PO</label>
                    <div class="fw-bolder text-primary">
                        <a href="{{ route('po.show', optional($gr->purchaseOrder)->po_number) }}" class="text-decoration-none">
                            {{ optional($gr->purchaseOrder)->po_number ?? '-' }}
                        </a>
                    </div>
                </div>
                <div class="col-md-5 border-top pt-3">
                    <label class="mb-1 small fw-bold text-muted text-uppercase">Vendor Pengirim</label>
                    <div class="fw-bold text-dark"><i class="bi bi-shop text-warning me-1"></i> {{ optional(optional($gr->purchaseOrder)->vendor)->name ?? '-' }}</div>
                </div>
                <div class="col-md-4 border-top pt-3">
                    <label class="mb-1 small fw-bold text-muted text-uppercase">Catatan Penerimaan Umum</label>
                    <div class="text-dark fst-italic" style="font-size: 0.85rem;">{{ $gr->notes ?? 'Tidak ada catatan khusus.' }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- LAMPIRAN GR --}}
    @if($gr->attachments && $gr->attachments->count() > 0)
    <div class="mb-4">
        <h6 class="fw-bold text-dark mb-2"><i class="bi bi-paperclip me-1 text-primary"></i> Dokumen Surat Jalan / Foto Bukti</h6>
        <div class="d-flex flex-wrap gap-2">
            @foreach($gr->attachments as $file)
                <a href="{{ asset('storage/' . $file->file_path) }}" target="_blank" class="badge bg-white text-dark border border-secondary-subtle text-decoration-none p-2 shadow-sm d-flex align-items-center gap-2">
                    <i class="bi bi-file-earmark-text fs-5 text-danger"></i> 
                    <span class="fw-medium">{{ $file->file_name }}</span>
                </a>
            @endforeach
        </div>
    </div>
    @endif

    {{-- 2. TABEL ITEM YANG DITERIMA --}}
    <div class="mb-4 overflow-hidden border-0 shadow-sm card rounded-4 border-top border-4 border-primary">
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
                            <div class="mt-1 d-flex gap-1 mb-2">
                                <span class="badge bg-secondary-subtle text-secondary border">{{ optional($item->item)->code }}</span>
                                @if(optional($item->item)->is_trackable || optional($item->item)->is_asset)
                                    <span class="badge bg-warning-subtle text-warning border border-warning"><i class="bi bi-upc-scan me-1"></i>Tracked (SN)</span>
                                @endif
                            </div>
                        </td>
                        
                        {{-- QTY PO --}}
                        <td class="py-3 text-center fw-bold text-secondary">
                            {{ (float)optional($item->purchaseOrderItem)->qty_ordered }} <br>
                            <span class="text-nowrap fw-normal" style="font-size: 0.65rem;">
                                {{ is_string(optional($item->purchaseOrderItem)->uom) ? optional($item->purchaseOrderItem)->uom : (optional(optional($item->purchaseOrderItem)->uom)->name ?? 'PCS') }}
                            </span>
                        </td>

                        {{-- QTY GR (DITERIMA) --}}
                        <td class="py-3 text-center fw-bold text-success fs-5">
                            @php
                                $uomDatangShow = $item->uom ?? (is_string(optional($item->purchaseOrderItem)->uom) ? optional($item->purchaseOrderItem)->uom : (optional(optional($item->purchaseOrderItem)->uom)->name ?? 'PCS'));
                            @endphp
                            + {{ (float)$item->qty_received }} <br>
                            <span class="text-nowrap fw-bold text-muted" style="font-size: 0.65rem;">
                                {{ $uomDatangShow }}
                            </span>
                        </td>
                        
                        {{-- KONDISI --}}
                        <td class="py-3">
                            <span class="badge bg-light text-dark border border-secondary-subtle px-3 py-2">
                                {{ optional($item->condition)->name ?? 'Sesuai / Baik' }}
                            </span>
                        </td>

                        {{-- CATATAN & SERIAL NUMBER (SANGAT BERSIH & MODERN) --}}
                        <td class="py-3 pe-4">
                            {{-- Tampilkan catatan asli murni dari ketikan staf gudang --}}
                            @if($item->notes)
                                <div class="text-dark mb-2 fw-semibold" style="font-size: 0.85rem; line-height: 1.4;">
                                    <i class="bi bi-chat-left-text text-muted me-1"></i> "{{ $item->notes }}"
                                </div>
                            @else
                                <div class="text-muted small fst-italic mb-2">Tidak ada catatan item.</div>
                            @endif

                            {{-- 🔥 RENDERING DATABASING SERIAL NUMBER SECARA AUTOMATIS 🔥 --}}
                            @if(!empty($item->registered_sns))
                                <div class="border rounded shadow-sm overflow-hidden border-warning-subtle">
                                    <div class="bg-warning-subtle px-2 py-1 d-flex justify-content-between align-items-center" style="font-size: 0.65rem;">
                                        <span class="fw-bold text-warning-emphasis"><i class="bi bi-upc-scan me-1"></i> Serial Number Diukir ke Database</span>
                                        <span class="badge bg-warning text-dark rounded-pill">{{ count($item->registered_sns) }} Unit Terdaftar</span>
                                    </div>
                                    <div class="p-2 bg-white text-start" style="max-height: 120px; overflow-y: auto;">
                                        <div class="row row-cols-1 row-cols-sm-2 g-1">
                                            @foreach($item->registered_sns as $sn)
                                                <div class="col" style="font-size: 0.75rem; font-family: monospace;">
                                                    <span class="text-success fw-bold">✓</span> <strong class="text-primary">{{ $sn }}</strong>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
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