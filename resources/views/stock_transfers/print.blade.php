<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Jalan Mutasi - {{ $transfer->transfer_number }}</title>
    <style>
        /* Pengaturan Margin Kertas & Footer */
        @page { margin: 40px 50px 70px 50px; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 10.5pt; color: #000; line-height: 1.4; }

        .header { text-align: center; margin-bottom: 20px; border-bottom: 3px double #000; padding-bottom: 10px; }
        .title { font-size: 16pt; font-weight: bold; margin: 0; letter-spacing: 1px; text-transform: uppercase; }
        .subtitle { font-size: 10pt; color: #333; margin-top: 5px; }

        .info-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; font-size: 10pt; }
        .info-table td { padding: 4px 0; vertical-align: top; }
        .info-label { font-weight: bold; width: 16%; }
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

        /* Footer Abadi */
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
        Dokumen Mutasi: {{ $transfer->transfer_number }} &nbsp; | &nbsp; Dicetak: {{ date('d-m-Y H:i') }} &nbsp; | &nbsp; <span class="pagenum"></span>
    </footer>

    <div class="header">
        <h1 class="title">SURAT JALAN MUTASI ANTAR GUDANG</h1>
        <p class="subtitle">Dokumen Resmi Pemindahan Fisik Inventaris & Aset Perusahaan</p>
    </div>

    <table class="info-table">
        <tr>
            <td class="info-label">No. Dokumen</td>
            <td class="info-colon">:</td>
            <td style="font-weight: bold; width: 32%;">{{ $transfer->transfer_number }}</td>
            <td class="info-label">Gudang Asal</td>
            <td class="info-colon">:</td>
            <td style="font-weight: bold; color: #d9534f;">{{ optional($transfer->fromWarehouse)->name }}</td>
        </tr>
        <tr>
            <td class="info-label">Tanggal Mutasi</td>
            <td class="info-colon">:</td>
            <td>{{ \Carbon\Carbon::parse($transfer->transfer_date)->translatedFormat('d F Y') }}</td>
            <td class="info-label">Gudang Tujuan</td>
            <td class="info-colon">:</td>
            <td style="font-weight: bold; color: #198754;">{{ optional($transfer->toWarehouse)->name }}</td>
        </tr>
        <tr>
            <td class="info-label">Catatan Mutasi</td>
            <td class="info-colon">:</td>
            <td colspan="4" style="font-style: italic;">{{ $transfer->notes ?? '- Tidak ada catatan -' }}</td>
        </tr>
    </table>

    <table class="item-table">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="15%">Kode Barang</th>
                <th width="30%">Nama / Deskripsi Barang</th>
                <th width="15%">Qty Mutasi</th>
                <th width="35%">Catatan / Serial Number</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transfer->items as $index => $item)
            @php
                // 🔥 KOREKSI PENGECEKAN ASET 🔥
                $isAsset = optional($item->item)->item_type_code === 'AST';
            @endphp
            <tr>
                <td class="center">{{ $index + 1 }}</td>
                <td class="center">{{ optional($item->item)->code ?? '-' }}</td>
                <td>
                    <strong>{{ optional($item->item)->name ?? '-' }}</strong>
                    @if($isAsset)
                        <br><span style="font-size: 8pt; font-weight: bold;">[ASET TETAP]</span>
                    @endif
                </td>
                <td class="center">
                    <strong style="font-size: 11pt;">{{ (float) $item->qty_transferred }}</strong><br>
                    {{-- 🔥 MENGGUNAKAN UOM DARI TABEL MUTASI 🔥 --}}
                    <span style="font-size: 8pt; color: #555;">{{ $item->uom ?? 'PCS' }}</span>
                </td>
                <td style="font-size: 8.5pt;">
                    @if($item->notes)
                        {{-- Memecah SN yang dipisah dengan " | " menjadi Bullet List --}}
                        @php $notesArray = explode(' | ', $item->notes); @endphp
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

    <table class="signature-table">
        <tr>
            <td>
                Dikeluarkan Oleh,<br>
                <span class="signature-title">(Gudang Asal)</span>
                <div class="signature-space"></div>
                <div class="signature-name">{{ optional($transfer->creator)->name ?? 'Admin Gudang' }}</div>
                <div class="signature-title">Tgl: .......................................</div>
            </td>
            <td>
                Dibawa / Dikirim Oleh,<br>
                <span class="signature-title">(Kurir / Ekspedisi)</span>
                <div class="signature-space"></div>
                <div class="signature-name">( ....................................... )</div>
                <div class="signature-title">Tgl: .......................................</div>
            </td>
            <td>
                Diterima Oleh,<br>
                <span class="signature-title">(Gudang Tujuan)</span>
                <div class="signature-space"></div>
                <div class="signature-name">( ....................................... )</div>
                <div class="signature-title">Tgl: .......................................</div>
            </td>
        </tr>
    </table>

</body>
</html>
