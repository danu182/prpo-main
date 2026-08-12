<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Goods Receipt Note - Internal</title>
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
        .data-table th, .data-table td { border: 1px solid #000; padding: 6px; font-size: 8.5pt; vertical-align: top; }
        .data-table th { background-color: #f0f0f0; text-align: center; }
        .signature-table { width: 100%; margin-top: 40px; text-align: center; page-break-inside: avoid; }
        .signature-table td { width: 33.33%; vertical-align: bottom; }
        .sign-space { height: 70px; }
        footer { position: fixed; bottom: -40px; left: 0; right: 0; height: 30px; border-top: 1px solid #888; text-align: right; font-size: 8pt; color: #555; padding-top: 5px; }
        .pagenum:before { content: "Halaman " counter(page); }
        
        /* 🔥 PEMISAH HALAMAN (MEMECAH KERTAS PER GUDANG) 🔥 */
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
    <footer>Dokumen GR: {{ $gr->gr_number }} | Dicetak: {{ date('d-m-Y H:i') }} | <span class="pagenum"></span></footer>

    @foreach($groupedItems as $warehouseName => $items)
    <div class="page-container">
        <table class="header-table">
            <tr>
                <td width="60%">
                    <h2 class="company-name">{{ $gr->purchaseOrder?->company?->name ?? 'PERUSAHAAN' }}</h2>
                    <div style="font-size: 8.5pt; color:#555;">{{ $gr->purchaseOrder?->company?->address ?? '-' }}</div>
                </td>
                <td width="40%" style="text-align: right;">
                    <h2 class="doc-title">INTERNAL RECEIPT NOTE</h2>
                    <div style="font-size: 11pt; font-weight: bold;">No: {{ $gr->gr_number }}</div>
                    <div style="margin-top: 5px; font-size: 10pt; color: #d9534f; font-weight: bold;">[ DISTRIBUSI : {{ strtoupper($warehouseName) }} ]</div>
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
                <td class="label">Diterima Oleh</td><td class="colon">:</td><td class="val">{{ $gr->receiver_name_display }}</td>
            </tr>
            <tr>
                <td class="label">Tanggal Terima</td><td class="colon">:</td><td class="val">{{ \Carbon\Carbon::parse($gr->receipt_date)->translatedFormat('d F Y') }}</td>
                <td class="label">Gudang Tujuan</td><td class="colon">:</td><td class="val" style="color: #0056b3;">{{ $warehouseName }}</td>
            </tr>
        </table>

        <table class="data-table">
            <thead>
                <tr>
                    <th width="5%">No</th><th width="45%">Nama Barang & Kode</th>
                    <th width="15%">Qty Terima</th><th width="35%">Kondisi / Catatan</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $idx => $item)
                <tr>
                    <td style="text-align: center;">{{ $idx + 1 }}</td>
                    <td>
                        <strong>{{ $item->purchaseOrderItem?->item_name ?? $item->item?->name ?? '-' }}</strong><br>
                        <span style="font-size: 7.5pt; color: #444;">{{ $item->item?->code ?? '-' }}</span>
                    </td>
                    <td style="text-align: center; font-size: 10pt;"><strong>{{ (float)$item->qty_received }}</strong></td>
                    <td>{{ $item->condition?->name ?? '-' }} <br> <i>{{ $item->notes }}</i></td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <table class="signature-table">
            <tr>
                <td><div>Kepala Gudang,</div><div class="sign-space"></div><div>_______________________</div></td>
                <td><div>Penerima Area ({{ $warehouseName }}),</div><div class="sign-space"></div><div>_______________________</div></td>
            </tr>
        </table>
    </div>

    {{-- KODE PEMISAH HALAMAN (PAGE BREAK) --}}
    @if(!$loop->last)
        <div class="page-break"></div>
    @endif
    @endforeach

</body>
</html>