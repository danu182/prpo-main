<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Goods Receipt Note - {{ $gr->gr_number }}</title>
    <style>
        /* Pengaturan Margin Kertas & Footer */
        @page { margin: 40px 50px 70px 50px; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 10pt; line-height: 1.4; color: #000; }

        .header-table { width: 100%; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
        .header-table td { vertical-align: top; }
        .company-name { font-size: 14pt; font-weight: bold; text-transform: uppercase; margin: 0 0 5px 0; }
        .company-address { font-size: 8.5pt; color: #555; }
        .doc-title { font-size: 14pt; font-weight: bold; text-align: right; text-transform: uppercase; color: #000; margin: 0 0 5px 0; letter-spacing: 1px; }
        .doc-number { font-size: 11pt; font-weight: bold; text-align: right; }

        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 9pt; }
        .info-table td { vertical-align: top; padding: 4px 0; }
        .info-table .label { width: 18%; color: #555; }
        .info-table .colon { width: 2%; }
        .info-table .val { width: 30%; font-weight: bold; }

        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 6px; font-size: 8.5pt; vertical-align: top; }
        .data-table th { background-color: #f0f0f0; text-align: center; text-transform: uppercase; }

        .signature-table { width: 100%; margin-top: 40px; text-align: center; page-break-inside: avoid; }
        .signature-table td { width: 33.33%; vertical-align: bottom; }
        .sign-space { height: 70px; }
        .sign-name { font-weight: bold; text-decoration: underline; text-transform: uppercase; }
        .sign-title { font-size: 8.5pt; color: #555; }

        /* Footer Abadi */
        footer {
            position: fixed; bottom: -40px; left: 0px; right: 0px; height: 30px;
            border-top: 1px solid #888; text-align: right; font-size: 8pt; color: #555;
            padding-top: 5px; font-style: italic;
        }
        .pagenum:before { content: "Halaman " counter(page); }

        .sn-list { margin: 4px 0 0 15px; padding: 0; font-family: monospace; font-size: 7.5pt; }
        .sn-list li { margin-bottom: 2px; }
    </style>
</head>
<body>

    <footer>
        Dokumen GR: {{ $gr->gr_number }} &nbsp; | &nbsp; Dicetak pada: {{ date('d-m-Y H:i') }} &nbsp; | &nbsp; <span class="pagenum"></span>
    </footer>

    <table class="header-table">
        <tr>
            <td width="60%">
                <h2 class="company-name">{{ $gr->po?->company?->name ?? 'PERUSAHAAN' }}</h2>
                <div class="company-address">{{ $gr->po?->company?->address ?? 'Alamat perusahaan belum diatur' }}</div>
            </td>
            <td width="40%" style="text-align: right;">
                <h2 class="doc-title">GOODS RECEIPT NOTE</h2>
                <div class="doc-number">No: {{ $gr->gr_number }}</div>
            </td>
        </tr>
    </table>

    <table class="info-table">
        <tr>
            <td class="label">No. Surat Jalan</td><td class="colon">:</td><td class="val">{{ $gr->delivery_note_number }}</td>
            <td class="label">Vendor Pengirim</td><td class="colon">:</td><td class="val">{{ $gr->po?->vendor?->name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Referensi PO</td><td class="colon">:</td><td class="val">{{ $gr->po?->po_number ?? '-' }}</td>
            <td class="label">Diterima Oleh</td><td class="colon">:</td><td class="val">{{ optional($gr->receiver)->name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Tanggal Terima</td><td class="colon">:</td><td class="val">{{ \Carbon\Carbon::parse($gr->received_date)->translatedFormat('d F Y') }}</td>
            <td class="label">Gudang Penerima</td><td class="colon">:</td><td class="val">{{ $warehouseName ?? 'Gudang Utama / Default' }}</td>
        </tr>
        <tr>
            <td class="label">Catatan</td><td class="colon">:</td>
            <td colspan="4" style="font-weight: normal; font-style: italic;">{{ $gr->notes ?? '-' }}</td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="30%">Nama Barang & Kode</th>
                <th width="8%">Pesan</th>
                <th width="10%">Datang</th>
                <th width="8%">Retur</th>
                <th width="12%">Kondisi</th>
                <th width="27%">Keterangan / S/N</th>
            </tr>
        </thead>
        <tbody>
            @foreach($gr->items as $idx => $item)
            @php
                $isStock = optional($item->item)->is_stockable;
                $isAsset = optional($item->item)->is_asset;
                $qtyReturned = (float) ($item->qty_returned ?? 0);

                // 🔥 LOGIKA CERDAS UOM 🔥
                $poItem = $item->purchaseOrderItem;
                $masterItem = $item->item;
                $baseUomName = optional($masterItem->uom)->name ?? 'PCS';

                // 1. Mencari Satuan yang dipesan di PO
                $poUomName = $baseUomName;
                $poUomId = $poItem?->uom_id ?? $poItem?->item_uom_id ?? null;

                // Jika PO pakai ID Konversi Kemasan (Box, Pack, dll)
                if (!empty($poUomId) && optional($masterItem)->itemUoms) {
                    $altUom = collect($masterItem->itemUoms)->where('id', $poUomId)->first();
                    if ($altUom) {
                        $poUomName = $altUom->uom_name ?? $altUom->name ?? $poUomName;
                    }
                } else {
                    // Jika tidak pakai ID, coba baca teks manualnya (atau JSON-nya)
                    $rawPoUom = $poItem?->uom;
                    if (is_string($rawPoUom) && str_starts_with(trim($rawPoUom), '{')) {
                        $parsed = json_decode($rawPoUom);
                        $poUomName = $parsed->name ?? $parsed->code ?? $baseUomName;
                    } elseif (is_string($rawPoUom) && !is_numeric($rawPoUom) && trim($rawPoUom) !== '') {
                        $poUomName = $rawPoUom;
                    }
                }

                // 2. Mencari Satuan Kedatangan (GR)
                $uomDatang = $item->uom ?? $poUomName;
                if (is_string($uomDatang) && str_starts_with(trim($uomDatang), '{')) {
                    $parsedGr = json_decode($uomDatang);
                    $uomDatang = $parsedGr->name ?? $parsedGr->code ?? $poUomName;
                }

                // Bersihkan Deskripsi
                $itemName = $masterItem?->name ?? '-';
                $rawDesc = $poItem?->description ?? '';
                $cleanDesc = strip_tags(str_replace(['</li>', '</p>', '<br>', '<br/>'], [', ', ' ', ' ', ' '], $rawDesc));
                $cleanDesc = str_replace('&nbsp;', ' ', $cleanDesc);
                $cleanDesc = rtrim(trim($cleanDesc), ',');
            @endphp
            <tr style="{{ $qtyReturned > 0 ? 'background-color: #fafafa;' : '' }}">
                <td style="text-align: center;">{{ $idx + 1 }}</td>
                <td>
                    <strong style="text-transform: uppercase;">{{ $itemName }}</strong><br>
                    <span style="font-size: 7.5pt; color: #444;">{{ $item->item?->code ?? '-' }}</span>
                    @if($isAsset) <span style="font-size: 7.5pt; font-weight: bold; color: #000;">[ASET]</span> @endif
                    @if($cleanDesc && $cleanDesc !== '-')
                        <div style="font-size: 7.5pt; color: #555; margin-top: 3px;">Spec: {{ \Illuminate\Support\Str::limit($cleanDesc, 100) }}</div>
                    @endif
                </td>
                <td style="text-align: center;">
                    <strong>{{ (float)($item->purchaseOrderItem?->qty_ordered ?? 0) }}</strong><br>
                    <span style="font-size: 7pt; color: #555;">{{ strtoupper($poUomName) }}</span>
                </td>
                <td style="text-align: center;">
                    <strong>{{ (float)$item->qty_received }}</strong><br>
                    <span style="font-size: 7pt; color: #555;">{{ strtoupper($uomDatang) }}</span>
                </td>
                <td style="text-align: center;">
                    @if($qtyReturned > 0)
                        <strong style="color: red;">{{ $qtyReturned }}</strong>
                    @else
                        -
                    @endif
                </td>
                <td style="text-align: center;">{{ $item->condition?->name ?? '-' }}</td>
                <td>
                    @if(trim(strip_tags($item->notes)))
                        <div style="font-style: italic; margin-bottom: 4px;">{{ trim(strip_tags($item->notes)) }}</div>
                    @endif

                    @if(!empty($item->registered_sns))
                        <div style="background-color: #f9f9f9; padding: 4px; border: 1px solid #eee;">
                            <strong>SN Terdaftar:</strong>
                            <ul class="sn-list">
                                @foreach($item->registered_sns as $sn)
                                    <li>{{ $sn }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="signature-table">
        <tr>
            <td>
                <div class="sign-title">Diserahkan Oleh (Vendor/Kurir),</div>
                <div class="sign-space"></div>
                <div class="sign-name">___________________________</div>
            </td>
            <td>
                <div class="sign-title">Diketahui Oleh (Purchasing/QC),</div>
                <div class="sign-space"></div>
                <div class="sign-name">___________________________</div>
            </td>
            <td>
                <div class="sign-title">Diterima Oleh (Gudang),</div>
                <div class="sign-space"></div>
                <div class="sign-name">{{ optional($gr->receiver)->name ?? '-' }}</div>
            </td>
        </tr>
    </table>

</body>
</html>
