@extends('layouts.app')

@section('content')
<div class="pb-5 container-fluid text-dark">
    <div class="gap-3 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div class="d-flex align-items-center">
            <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-file-earmark-check me-2 text-success"></i>Detail Penerimaan Barang</h4>
            <span class="border ms-3 badge bg-success-subtle text-success border-success-subtle rounded-pill">Tersimpan</span>
        </div>
        <div class="gap-2 d-flex">
            <a href="{{ route('gr.index') }}" class="bg-white shadow-sm btn btn-outline-secondary rounded-pill fw-bold">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
            <div class="dropdown">
                <button class="shadow-sm btn btn-primary rounded-pill fw-bold dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-printer me-1"></i> Cetak Bukti GR
                </button>
                <ul class="border-0 shadow dropdown-menu dropdown-menu-end rounded-3">
                    <li><a class="py-2 dropdown-item fw-semibold" href="{{ route('gr.print_vendor', $gr->gr_number) }}" target="_blank"><i class="bi bi-truck me-2 text-primary"></i>Cetak untuk Vendor (Eksternal)</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="py-2 dropdown-item fw-semibold" href="{{ route('gr.print_internal', $gr->gr_number) }}" target="_blank"><i class="bi bi-diagram-3 me-2 text-info"></i>Cetak Distribusi (Internal Gudang)</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="py-2 dropdown-item fw-semibold" href="{{ route('gr.print', $gr->gr_number) }}" target="_blank"><i class="bi bi-file-earmark-text me-2 text-secondary"></i>Cetak Laporan Lengkap (Full)</a></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="mb-2 text-muted small">Dokumen GR: <strong class="text-primary">{{ $gr->gr_number }}</strong></div>

    {{-- KOTAK INFORMASI HEADER --}}
    <div class="mb-4 border-0 shadow-sm card rounded-4 border-top border-success border-3">
        <div class="p-4 card-body">
            <div class="row g-4">
                <div class="col-md-3">
                    <div class="mb-1 text-muted small fw-bold text-uppercase">No. Penerimaan (GR)</div>
                    <div class="fw-bolder text-dark">{{ $gr->gr_number }}</div>
                </div>
                <div class="col-md-3">
                    <div class="mb-1 text-muted small fw-bold text-uppercase">Tgl. Terima Fisik</div>
                    <div class="fw-bold text-dark"><i class="bi bi-calendar-check text-success me-1"></i> {{ \Carbon\Carbon::parse($gr->received_date)->translatedFormat('d F Y') }}</div>
                </div>
                <div class="col-md-3">
                    <div class="mb-1 text-muted small fw-bold text-uppercase">No. Surat Jalan Vendor</div>
                    <div class="fw-bold text-dark"><i class="bi bi-truck text-secondary me-1"></i> {{ $gr->delivery_note_number }}</div>
                </div>
                <div class="col-md-3">
                    <div class="mb-1 text-muted small fw-bold text-uppercase">Staf Penerima</div>
                    <div class="fw-bold text-dark"><i class="bi bi-person-badge text-primary me-1"></i> {{ $gr->receiver_name_display }}</div>
                </div>

                <div class="col-12"><hr class="my-2 border-secondary-subtle"></div>

                <div class="col-md-3">
                    <div class="mb-1 text-muted small fw-bold text-uppercase">Referensi PO</div>
                    <a href="{{ route('po.show', optional($gr->purchaseOrder)->po_number ?? '') }}" class="fw-bold text-primary text-decoration-none">{{ optional($gr->purchaseOrder)->po_number ?? '-' }}</a>
                </div>
                <div class="col-md-3">
                    <div class="mb-1 text-muted small fw-bold text-uppercase">Vendor Pengirim</div>
                    <div class="fw-bold text-dark"><i class="bi bi-shop text-warning me-1"></i> {{ optional(optional($gr->purchaseOrder)->vendor)->name ?? '-' }}</div>
                </div>
                <div class="col-md-3">
                    <div class="mb-1 text-muted small fw-bold text-uppercase">Gudang Penerima</div>
                    <div class="border fw-bold text-dark badge bg-light text-primary border-primary-subtle"><i class="bi bi-box me-1"></i> {{ $globalWarehouse }}</div>
                </div>
                <div class="col-md-3">
                    <div class="mb-1 text-muted small fw-bold text-uppercase">Catatan Penerimaan</div>
                    @if($gr->notes)
                        <div class="text-dark" style="font-size: 0.9rem;"><i>"{{ $gr->notes }}"</i></div>
                    @else
                        <div class="text-muted" style="font-size: 0.85rem;"><i>Tidak ada catatan khusus.</i></div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- TABEL RINCIAN ITEM --}}
    <div class="mb-4 overflow-hidden border-0 shadow-sm card rounded-4 border-top border-primary border-3">
        <div class="px-4 py-3 bg-white card-header border-bottom">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-list-ul me-2 text-primary"></i>Rincian Barang yang Diterima</h6>
        </div>
        <div class="p-0 card-body table-responsive">
            <table class="table mb-0 align-middle table-hover">
                <thead class="bg-light text-muted small text-uppercase fw-bold border-bottom">
                    <tr>
                        <th class="py-3 ps-4" width="30%">Barang & Spesifikasi</th>
                        <th class="py-3 text-center" width="10%">Order (PO)</th>
                        <th class="py-3 text-center" width="15%">Diterima</th>
                        <th class="py-3" width="15%">Gudang Tujuan</th>
                        <th class="py-3" width="10%">Kondisi</th>
                        <th class="py-3 pe-4" width="20%">Catatan Staf & Serial Number</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($gr->items as $item)
                    <tr>
                        <td class="py-3 ps-4">
                            <div class="mb-1 fw-bold text-dark text-uppercase">{{ optional($item->item)->name ?? 'Item Terhapus' }}</div>
                            <span class="border badge bg-secondary-subtle text-secondary border-secondary-subtle">{{ optional($item->item)->code ?? '-' }}</span>
                            @if(optional($item->item)->is_trackable)
                                <span class="border badge bg-warning-subtle text-warning-emphasis border-warning"><i class="bi bi-upc-scan me-1"></i>Tracked (SN)</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @php
                                $poItemQty = optional($item->purchaseOrderItem)->qty_ordered ?? 0;
                                $poItemUom = optional($item->purchaseOrderItem)->uom ?? 'PCS';
                            @endphp
                            <div class="fw-bold text-secondary">{{ (float)$poItemQty }}</div>
                            <div class="text-muted" style="font-size: 0.65rem; font-weight: bold; text-transform: uppercase;">{{ $poItemUom }}</div>
                        </td>
                        <td class="text-center">
                            <div class="fw-bolder text-success fs-5">+ {{ (float)$item->qty_received }}</div>
                            <div class="text-dark" style="font-size: 0.65rem; font-weight: bold; text-transform: uppercase;">{{ $item->clean_uom_name }}</div>
                        </td>
                        <td>
                            <div class="fw-bold text-primary" style="font-size: 0.85rem;"><i class="bi bi-box-seam me-1"></i> {{ $item->warehouse_name_display }}</div>
                        </td>
                        <td>
                            @if(optional($item->condition)->name == 'Sesuai / Baik')
                                <span class="border badge bg-light text-dark border-secondary-subtle">Sesuai / <span class="text-primary">Baik</span></span>
                            @else
                                <span class="border badge bg-danger-subtle text-danger border-danger-subtle">{{ optional($item->condition)->name ?? '-' }}</span>
                            @endif
                        </td>
                        <td class="pe-4">
                            @if(!empty($item->notes))
                                <div class="mb-2 text-dark" style="font-size: 0.85rem;"><i>"{{ $item->notes }}"</i></div>
                            @else
                                <div class="mb-2 text-muted" style="font-size: 0.85rem;">Tidak ada catatan khusus.</div>
                            @endif

                            {{-- 🔥 TAMPILAN SERIAL NUMBER (SN) SANGAT JELAS 🔥 --}}
                            @if(!empty($item->sn_list) && count($item->sn_list) > 0)
                                <div class="pt-2 mt-2 border-top border-secondary-subtle">
                                    <div class="mb-1 fw-bold text-dark" style="font-size: 0.75rem;">
                                        <i class="bi bi-upc-scan me-1 text-primary"></i> SERIAL NUMBER ({{ count($item->sn_list) }} Unit):
                                    </div>
                                    <div class="flex-wrap gap-1 d-flex">
                                        @foreach($item->sn_list as $sn)
                                            <span class="border fw-normal badge bg-light text-dark border-secondary-subtle" style="font-size: 0.75rem;">
                                                <i class="bi bi-hash text-muted"></i>{{ $sn }}
                                            </span>
                                        @endforeach
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
@endsection
