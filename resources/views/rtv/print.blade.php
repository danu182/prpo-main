<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Bukti Retur Vendor - {{ $rtv->rtv_number }}</title>
    <style>
        /* Pengaturan Margin Kertas & Footer */
        @page { margin: 40px 50px 70px 50px; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 10.5pt; color: #000; line-height: 1.4; }

        .header { text-align: center; margin-bottom: 20px; border-bottom: 3px double #000; padding-bottom: 10px; }
        .title { font-size: 16pt; font-weight: bold; margin: 0; letter-spacing: 1px; text-transform: uppercase; }
        .subtitle { font-size: 10pt; color: #333; margin-top: 5px; }

        .info-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; font-size: 10pt; }
        .info-table td { padding: 4px 0; vertical-align: top; }
        .info-label { font-weight: bold; width: 18%; }
        .info-colon { width: 2%; }

        .item-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .item-table th, .item-table td { border: 1px solid #000; padding: 6px 8px; font-size: 9.5pt; vertical-align: top; }
        .item-table th { background-color: #f0f0f0; font-weight: bold; text-align: center; text-transform: uppercase; }
        .item-table td.center { text-align: center; }

        .signature-table { width: 100%; margin-top: 40px; text-align: center; border-collapse: collapse; page-break-inside: avoid; }
        .signature-table td { width: 33.33%; padding: 10px; vertical-align: bottom; }
        .signature-space { height: 70px; }
        .signature-name { font-weight: bold; text-decoration: underline; text-transform: uppercase; }
        .signature-title { font-size: 9pt; color: #555; }

        footer {
            position: fixed; bottom: -40px; left: 0px; right: 0px; height: 30px;
            border-top: 1px solid #888; text-align: right; font-size: 8pt; color: #555;
            padding-top: 5px; font-style: italic;
        }
        .pagenum:before { content: "Halaman " counter(page); }
    </style>
</head>
<body>

    {{-- 🔥 FOOTER OTOMATIS 🔥 --}}
    <footer>
        Dokumen RTV: {{ $rtv->rtv_number }} &nbsp; | &nbsp; Dicetak: {{ date('d-m-Y H:i') }} &nbsp; | &nbsp; <span class="pagenum"></span>
    </footer>

    <div class="header">
        <h1 class="title">BUKTI PENGEMBALIAN BARANG KE VENDOR</h1>
        <p class="subtitle">RETURN TO VENDOR (RTV) SLIP</p>
    </div>

    <table class="info-table">
        <tr>
            <td class="info-label">No. Dokumen RTV</td>
            <td class="info-colon">:</td>
            <td style="font-weight: bold; width: 30%;">{{ $rtv->rtv_number }}</td>
            <td class="info-label">Vendor Tujuan</td>
            <td class="info-colon">:</td>
            <td style="font-weight: bold; color: #d9534f;">{{ optional($rtv->vendor)->name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="info-label">Ref. Penerimaan (GR)</td>
            <td class="info-colon">:</td>
            <td>{{ optional($rtv->goodsReceipt)->gr_number ?? '-' }}</td>
            <td class="info-label">Perusahaan</td>
            <td class="info-colon">:</td>
            <td style="font-weight: bold;">{{ optional(optional($rtv->goodsReceipt)->po)->company->name ?? 'Kantor Pusat' }}</td>
        </tr>
        <tr>
            <td class="info-label">Tanggal Retur</td>
            <td class="info-colon">:</td>
            <td>{{ \Carbon\Carbon::parse($rtv->return_date)->translatedFormat('d F Y') }}</td>
            <td class="info-label">Surat Jalan Retur</td>
            <td class="info-colon">:</td>
            <td>{{ $rtv->delivery_note_number ?? '-' }}</td>
        </tr>
    </table>

    <table class="item-table">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="15%">Kode Barang</th>
                <th width="35%">Nama Barang</th>
                <th width="15%">Qty Retur</th>
                <th width="30%">Alasan & Serial Number</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rtv->items as $index => $item)
            @php
                // 🔥 DEFINISI NAMA SPESIFIK & NAMA MASTER 🔥
                $masterItem = $item->item;
                $masterName = optional($masterItem)->name ?? '-';

                // Di RTV, rujukan nama aslinya kembali ke dokumen PO asalnya
                $specificName = optional($item->purchaseOrderItem)->item_name ?? $masterName;

                // Cek aman apakah item ini aset tetap (AST)
                $isAsset = optional($masterItem)->is_asset || optional($masterItem)->item_type_code === 'AST';
            @endphp
            <tr>
                <td class="center">{{ $index + 1 }}</td>
                <td class="center">{{ optional($masterItem)->code ?? '-' }}</td>
                <td>
                    {{-- 🔥 CETAK NAMA BERSUSUN 🔥 --}}
                    <strong style="text-transform: uppercase;">{{ $specificName }}</strong><br>

                    @if(strtolower(trim($specificName)) !== strtolower(trim($masterName)))
                        <span style="font-size: 7.5pt; color: #555;">(Master: {{ $masterName }})</span><br>
                    @endif

                    {{-- @if($isAsset)
                        <span style="font-size: 7.5pt; font-weight: bold; color: #000;">[ASET TETAP]</span>
                    @endif --}}
                </td>
                <td class="center" style="vertical-align: middle;">
                    <strong style="font-size: 11pt; color: #d9534f;">{{ (float) $item->qty_returned }}</strong> <br>
                    <span style="font-size: 8pt; font-weight: bold; color: #555; text-transform: uppercase;">
                        {{ $item->uom ?? 'PCS' }}
                    </span>
                </td>
                <td style="font-size: 8.5pt;">
                    @if($item->return_reason)
                        {{-- Memecah alasan dan SN yang dipisah dengan " | " menjadi Bullet List --}}
                        @php $notesArray = explode(' | ', $item->return_reason); @endphp
                        <ul style="margin: 0; padding-left: 15px;">
                            @foreach($notesArray as $noteLine)
                                <li style="margin-bottom: 3px;">{{ trim($noteLine) }}</li>
                            @endforeach
                        </ul>
                    @else
                        -
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @if($rtv->notes)
    <div style="font-size: 9.5pt; margin-bottom: 20px; font-style: italic;">
        <strong>Catatan Tambahan:</strong><br>
        {{ $rtv->notes }}
    </div>
    @endif

    <div style="font-size: 9.5pt; margin-top: 15px; border: 1px dashed #777; padding: 10px; background-color: #fdfdfd; text-align: justify;">
        <strong>Pernyataan:</strong><br>
        Barang-barang di atas dikembalikan kepada pihak Vendor dikarenakan cacat, rusak, atau tidak sesuai dengan spesifikasi Pesanan Pembelian (PO). Pihak Perusahaan berhak meminta penggantian barang baru atau pengembalian dana sesuai kesepakatan.
    </div>

    <table class="signature-table">
        <tr>
            <td>
                Dikeluarkan Oleh,<br>
                <span class="signature-title">(Gudang Perusahaan)</span>
                <div class="signature-space"></div>
                <div class="signature-name">{{ optional($rtv->returner)->name ?? 'Admin Gudang' }}</div>
            </td>
            <td>
                Dibawa / Dikirim Oleh,<br>
                <span class="signature-title">(Kurir / Ekspedisi)</span>
                <div class="signature-space"></div>
                <div class="signature-name">( ....................................... )</div>
            </td>
            <td>
                Diterima Oleh,<br>
                <span class="signature-title">(Pihak Vendor)</span>
                <div class="signature-space"></div>
                <div class="signature-name">( ....................................... )</div>
            </td>
        </tr>
    </table>

</body>
</html>
