<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Penerimaan Barang - {{ $gr->gr_number }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { font-family: 'Arial', sans-serif; background-color: #f8f9fa; color: #000; font-size: 13px; } /* Font sedikit dikecilkan agar muat banyak */
        .kertas { background: #fff; width: 210mm; min-height: 297mm; margin: 20px auto; padding: 40px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .table-items th { background-color: #f1f1f1 !important; -webkit-print-color-adjust: exact; color-adjust: exact; }

        /* Memastikan warna badge ikut tercetak di kertas */
        .badge { -webkit-print-color-adjust: exact; color-adjust: exact; border: 1px solid #ccc !important; }

        @media print {
            body { background: #fff; }
            .kertas { width: 100%; min-height: auto; margin: 0; padding: 0; box-shadow: none; border: none !important; }
            .d-print-none { display: none !important; }
        }
    </style>
</head>
<body>

<div class="mt-3 mb-3 text-center d-print-none">
    <button onclick="window.print()" class="px-4 shadow-sm btn btn-primary rounded-pill fw-bold">
        <i class="bi bi-printer-fill me-2"></i> Cetak Dokumen
    </button>
    <button onclick="window.close()" class="px-4 shadow-sm btn btn-outline-secondary rounded-pill fw-bold ms-2">
        Tutup
    </button>
</div>

<div class="border kertas">
    {{-- HEADER / KOP SURAT --}}
    <div class="pb-3 mb-4 row border-bottom border-dark border-2 align-items-center">
        <div class="col-6">
            <h3 class="mb-0 fw-bold">{{ $gr->po?->company?->name ?? 'PERUSAHAAN' }}</h3>
            <div class="small text-muted">
                {{ $gr->po?->company?->address ?? 'Alamat belum diatur' }}
            </div>
        </div>
        <div class="col-6 text-end">
            <h4 class="mb-1 fw-bold text-success">GOODS RECEIPT NOTE</h4>
            <div class="fw-bold fs-6">No: {{ $gr->gr_number }}</div>
        </div>
    </div>

    {{-- INFORMASI PENERIMAAN --}}
    <div class="mb-4 row small">
        <div class="col-6">
            <table class="table mb-0 table-sm table-borderless">
                <tr><td width="35%" class="fw-bold text-muted">No. Surat Jalan Vendor</td><td width="5%">:</td><td class="fw-bold">{{ $gr->delivery_note_number }}</td></tr>
                <tr><td class="fw-bold text-muted">Referensi PO</td><td>:</td><td class="fw-bold">{{ $gr->po?->po_number ?? '-' }}</td></tr>
                <tr><td class="fw-bold text-muted">Tanggal Terima</td><td>:</td><td>{{ \Carbon\Carbon::parse($gr->received_date)->format('d F Y') }}</td></tr>
            </table>
        </div>
        <div class="col-6">
            <table class="table mb-0 table-sm table-borderless">
                <tr><td width="30%" class="fw-bold text-muted">Vendor Pengirim</td><td width="5%">:</td><td class="fw-bold">{{ $gr->po?->vendor?->name ?? '-' }}</td></tr>
                <tr><td class="fw-bold text-muted">Penerima (Gudang)</td><td>:</td><td>{{ optional($gr->receiver)->name ?? '-' }}</td></tr>
                <tr><td class="fw-bold text-muted">Catatan</td><td>:</td><td>{{ $gr->notes ?? '-' }}</td></tr>
            </table>
        </div>
    </div>

    {{-- TABEL BARANG DITERIMA --}}
    <table class="table align-middle table-bordered border-dark table-items">
        <thead class="text-center align-middle">
            <tr>
                <th width="5%" class="py-2">No</th>
                <th width="33%" class="py-2">Nama Barang & Kode</th>
                <th width="10%" class="py-2">Qty<br>Pesan</th>
                <th width="10%" class="py-2">Qty<br>Datang</th>
                <th width="10%" class="py-2">Qty<br>Retur</th>
                <th width="12%" class="py-2">Kondisi</th>
                <th width="20%" class="py-2">Keterangan Item</th>
            </tr>
        </thead>
        <tbody>
            @foreach($gr->items as $idx => $item)
            @php
                $isStock = optional($item->item)->is_stockable;
                $isAsset = optional($item->item)->is_asset;
                $qtyReturned = (float) ($item->qty_returned ?? 0);
                
                // 1. AMBIL SATUAN
                $rawPoUom = $item->purchaseOrderItem?->uom;
                $poUomName = is_string($rawPoUom) ? $rawPoUom : (optional($rawPoUom)->name ?? optional($item->item->uom)->name ?? 'PCS');
                $uomDatang = $item->uom ?? $poUomName; 

                // 2. PISAHKAN NAMA BARANG DAN SPESIFIKASI 🔥
                $itemName = $item->item?->name ?? 'Nama Barang Tidak Ditemukan';
                $rawDesc = $item->purchaseOrderItem?->description ?? '';
                $cleanDesc = strip_tags(str_replace(['</li>', '</p>', '<br>', '<br/>'], [', ', ' ', ' ', ' '], $rawDesc));
                $cleanDesc = str_replace('&nbsp;', ' ', $cleanDesc);
                $cleanDesc = rtrim(trim($cleanDesc), ',');
            @endphp
            <tr class="{{ $qtyReturned > 0 ? 'bg-light' : '' }}">
                <td class="text-center align-top pt-3">{{ $idx + 1 }}</td>
                <td class="align-top pt-3">
                    {{-- NAMA BARANG SELALU TAMPIL PERTAMA --}}
                    <div class="fw-bold text-uppercase {{ $qtyReturned > 0 ? 'text-danger' : 'text-dark' }}">
                        {{ $itemName }}
                    </div>
                    
                    {{-- SPESIFIKASI TAMPIL DI BAWAHNYA JIKA ADA --}}
                    @if($cleanDesc && $cleanDesc !== '-')
                        <div class="text-muted mt-1" style="font-size: 0.75rem;">
                            Spec: {{ $cleanDesc }}
                        </div>
                    @endif
                    
                    {{-- SKU DAN PENANDA ASET --}}
                    <div class="small text-dark fw-bold mt-2">
                        {{ $item->item?->code ?? '-' }}
                        @if($isAsset)
                            <span class="ms-1 text-primary" style="font-size: 10px;">[ASET]</span>
                        @elseif(!$isStock)
                            <span class="ms-1 text-info" style="font-size: 10px;">[JASA]</span>
                        @endif
                    </div>
                </td>
                
                <td class="text-center align-top pt-3">
                    {{ (float)($item->purchaseOrderItem?->qty_ordered ?? 0) }}
                    <div class="text-muted fw-bold" style="font-size: 0.7rem;">{{ $poUomName }}</div>
                </td>
                
                <td class="text-center fw-bold fs-6 align-top pt-3">
                    {{ (float)$item->qty_received }}
                    <div class="text-muted fw-normal" style="font-size: 0.7rem;">{{ $uomDatang }}</div>
                </td>

                <td class="text-center align-top pt-3">
                    @if($qtyReturned > 0)
                        <span class="fw-bold text-danger">{{ $qtyReturned }}</span>
                        <div class="text-danger" style="font-size: 0.7rem;">{{ $uomDatang }}</div>
                    @else
                        <span class="text-muted">-</span>
                    @endif
                </td>

                <td class="text-center align-top pt-3">
                    <span class="badge bg-light text-dark border border-secondary-subtle">{{ $item->condition?->name ?? '-' }}</span>
                </td>
                
                {{-- KOLOM KETERANGAN (SN BERBARIS RAPI) 🔥 --}}
                <td class="small text-muted align-top pt-3">
                    @php $catatan = trim(strip_tags($item->notes ?? '')); @endphp
                    @if($catatan && $catatan !== '-')
                        <div class="fst-italic mb-2">{{ $catatan }}</div>
                    @endif
                    
                    @if(!empty($item->registered_sns))
                        <div class="text-dark" style="font-size: 0.7rem; font-family: monospace;">
                            <strong class="text-success">SN Terdaftar:</strong>
                            <div class="mt-1">
                                @foreach($item->registered_sns as $sn)
                                    {{-- Menggunakan white-space: nowrap agar tidak terpotong di tengah jalan --}}
                                    <div style="white-space: nowrap; margin-bottom: 2px;">• {{ $sn }}</div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- TANDA TANGAN (BAST) --}}
    <div class="pt-3 mt-5 text-center row" style="page-break-inside: avoid;">
        <div class="col-4">
            <p class="mb-0 small">Diserahkan Oleh (Vendor/Kurir),</p>
            <div style="height: 80px;"></div>
            <p class="mb-0 fw-bold">.............................................</p>
            <p class="small text-muted">Nama Terang & TTD</p>
        </div>
        <div class="col-4">
            <p class="mb-0 small">Diketahui Oleh (Purchasing/QC),</p>
            <div style="height: 80px;"></div>
            <p class="mb-0 fw-bold">.............................................</p>
            <p class="small text-muted">Nama Terang & TTD</p>
        </div>
        <div class="col-4">
            <p class="mb-0 small">Diterima Oleh (Gudang),</p>
            <div style="height: 80px;"></div>
            <p class="mb-0 fw-bold text-decoration-underline text-uppercase">{{ optional($gr->receiver)->name ?? '-' }}</p>
            <p class="small text-muted">Staf Gudang</p>
        </div>
    </div>

</div>

</body>
</html>
