<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Goods Receipt Note - Vendor</title>
    <style>
        @page { margin: 40px 50px 70px 50px; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 10pt; line-height: 1.4; color: #000; }

        .header-table { width: 100%; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
        .header-table td { vertical-align: top; }
        .company-name { font-size: 14pt; font-weight: bold; margin: 0 0 5px 0; }
        .doc-title { font-size: 14pt; font-weight: bold; text-align: right; margin: 0 0 5px 0; }

        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 9pt; }
        .info-table td { vertical-align: top; padding: 4px 0; }
        .info-table .label { width: 18%; color: #555; }
        .info-table .colon { width: 2%; }
        .info-table .val { width: 30%; font-weight: bold; }

        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 6px; font-size: 8.5pt; vertical-align: middle; }
        .data-table th { background-color: #f0f0f0; text-align: center; }

        /* 🔥 Penyesuaian Sign Table yang Rapi dan Rata Bawah 🔥 */
        .signature-table { width: 100%; margin-top: 50px; page-break-inside: avoid; border-collapse: collapse; text-align: center; }
        .signature-table td { width: 33.33%; padding: 0 10px; }
        .sign-title { font-size: 9pt; margin-bottom: 60px; }
        .sign-line { border-bottom: 1px solid #000; margin: 0 auto; width: 70%; height: 20px; }
        .sign-name { font-size: 8.5pt; font-weight: bold; padding-top: 5px; height: 15px; }

        footer { position: fixed; bottom: -40px; left: 0; right: 0; height: 30px; border-top: 1px solid #888; text-align: right; font-size: 8pt; color: #555; padding-top: 5px; }
        .pagenum:before { content: "Halaman " counter(page); }
    </style>
</head>
<body>
    <footer>Dokumen GR: {{ $gr->gr_number }} | Dicetak: {{ date('d-m-Y H:i') }} | <span class="pagenum"></span></footer>

    <table class="header-table">
        <tr>
            <td width="60%">
                <h2 class="company-name">{{ $gr->purchaseOrder?->company?->name ?? 'PERUSAHAAN' }}</h2>
                <div style="font-size: 8.5pt; color:#555;">{{ $gr->purchaseOrder?->company?->address ?? '-' }}</div>
            </td>
            <td width="40%" style="text-align: right;">
                <h2 class="doc-title">GOODS RECEIPT NOTE</h2>
                <div style="font-size: 11pt; font-weight: bold;">No: {{ $gr->gr_number }}</div>
            </td>
        </tr>
    </table>

    <table class="info-table">
        <tr>
            <td class="label">No. Surat Jalan</td><td class="colon">:</td><td class="val">{{ $gr->delivery_note_number }}</td>
            <td class="label">Vendor Pengirim</td><td class="colon">:</td><td class="val">{{ $gr->purchaseOrder?->vendor?->name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Referensi PO</td><td class="colon">:</td><td class="val">{{ $gr->purchaseOrder?->po_number ?? '-' }}</td>

            {{-- Perbaiki Null Display untuk Penerima --}}
            @php $namaPenerima = $gr->receiver_name_display ?? $gr->user->name ?? '-'; @endphp
            <td class="label">Diterima Oleh</td><td class="colon">:</td><td class="val">{{ $namaPenerima }}</td>
        </tr>
        <tr>
            <td class="label">Tanggal Terima</td><td class="colon">:</td><td class="val">{{ \Carbon\Carbon::parse($gr->receipt_date)->translatedFormat('d F Y') }}</td>
            <td class="label">Gudang Tujuan</td><td class="colon">:</td>
            <td class="val" style="color: #0056b3;">
                @php
                    $whCount = $gr->items->pluck('warehouse_name_display')->unique()->count();
                    echo $whCount > 1 ? 'Multi-Gudang (Lihat Tabel)' : ($gr->items->first()->warehouse_name_display ?? 'Gudang Utama');
                @endphp
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="35%">Nama Barang & Kode</th>
                <th width="15%">Pesanan (PO)</th>
                <th width="15%">Diterima (GR)</th>
                <th width="15%">Gudang Tujuan</th>
                <th width="15%">Kondisi / Catatan</th>
            </tr>
        </thead>
        <tbody>
            @foreach($gr->items as $idx => $item)
            @php
                // 🔥 LOGIKA UOM CERDAS AGAR PDF TIDAK SALAH PAHAM 🔥
                $poItem = $item->purchaseOrderItem;
                $masterItem = $item->item;
                $baseUomName = strtoupper(optional($masterItem->uom)->name ?? 'PCS');

                // 1. UOM PESANAN (PO)
                $poUomDisplay = $baseUomName;
                if ($poItem) {
                    $rawPoUom = $poItem->getRawOriginal('uom') ?: $poItem->uom ?: $baseUomName;
                    $poConvFactor = 1;

                    if (!empty($poItem->uom_id) && $poItem->uom_id != $masterItem->uom_id) {
                        $uomDb = collect(optional($masterItem)->itemUoms)->where('id', $poItem->uom_id)->first();
                        if ($uomDb) {
                            $poConvFactor = (float) $uomDb->conversion_qty;
                            $poUomDisplay = strtoupper($uomDb->uom_name) . " (Isi {$poConvFactor} {$baseUomName})";
                        }
                    } elseif (preg_match('/\(Isi:\s*([0-9.]+)/i', $rawPoUom, $matches)) {
                        $poConvFactor = (float) $matches[1];
                        $cleanName = trim(preg_replace('/ \(Isi:.*\)/i', '', $rawPoUom));
                        $poUomDisplay = strtoupper($cleanName) . " (Isi {$poConvFactor} {$baseUomName})";
                    } else {
                        $poUomDisplay = strtoupper($rawPoUom);
                    }
                }

                // 2. UOM TERIMA (GR)
                $grUomDisplay = $baseUomName;
                $rawGrUom = $item->getRawOriginal('uom') ?: $item->uom ?: $baseUomName;
                $grConvFactor = 1;

                if (!empty($item->uom_id) && $item->uom_id != $masterItem->uom_id) {
                    $uomDb = collect(optional($masterItem)->itemUoms)->where('id', $item->uom_id)->first();
                    if ($uomDb) {
                        $grConvFactor = (float) $uomDb->conversion_qty;
                        $grUomDisplay = strtoupper($uomDb->uom_name) . " (Isi {$grConvFactor} {$baseUomName})";
                    }
                } elseif (preg_match('/\(Isi:\s*([0-9.]+)/i', $rawGrUom, $matches)) {
                    $grConvFactor = (float) $matches[1];
                    $cleanName = trim(preg_replace('/ \(Isi:.*\)/i', '', $rawGrUom));
                    $grUomDisplay = strtoupper($cleanName) . " (Isi {$grConvFactor} {$baseUomName})";
                } else {
                    $grUomDisplay = strtoupper(trim(preg_replace('/ \[PO\]| \[GR\]/i', '', $rawGrUom)));
                }

            @endphp
            <tr>
                <td style="text-align: center;">{{ $idx + 1 }}</td>
                <td>
                    <strong>{{ $poItem->item_name ?? $masterItem->name ?? '-' }}</strong><br>
                    <span style="font-size: 7.5pt; color: #444;">{{ $masterItem->code ?? '-' }}</span>
                </td>
                <td style="text-align: center;">
                    <strong>{{ (float)($poItem->qty_ordered ?? 0) }}</strong><br>
                    <span style="font-size: 7pt; color: #555;">{{ $poUomDisplay }}</span>
                </td>
                <td style="text-align: center;">
                    <strong style="color: #198754;">{{ (float)$item->qty_received }}</strong><br>
                    <span style="font-size: 7pt; color: #198754;">{{ $grUomDisplay }}</span>
                </td>
                <td style="text-align: center; color: #0056b3;">
                    <strong>{{ $item->warehouse_name_display }}</strong>
                </td>
                <td>
                    {{ $item->condition?->name ?? '-' }}
                    @if($item->notes)
                        <br><i style="font-size: 7.5pt;">{{ $item->notes }}</i>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- 🔥 TABEL TANDA TANGAN DIBUAT RAPI DAN SEJAJAR 🔥 --}}
    <table class="signature-table">
        <tr>
            <td style="vertical-align: top;">
                <div class="sign-title">Diserahkan Oleh (Vendor),</div>
            </td>
            <td style="vertical-align: top;">
                <div class="sign-title">Diketahui Oleh (Purchasing),</div>
            </td>
            <td style="vertical-align: top;">
                <div class="sign-title">Diterima Oleh (Gudang),</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="sign-line"></div>
                <div class="sign-name"></div>
            </td>
            <td>
                <div class="sign-line"></div>
                <div class="sign-name"></div>
            </td>
            <td>
                <div class="sign-line"></div>
                <div class="sign-name">{{ $namaPenerima }}</div>
            </td>
        </tr>
    </table>
</body>
</html>
