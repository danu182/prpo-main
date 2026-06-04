<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Jalan Mutasi - {{ $transfer->transfer_number }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #333; margin: 0; padding: 20px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .title { font-size: 18px; font-weight: bold; margin: 0; letter-spacing: 1px; }
        .subtitle { font-size: 12px; color: #555; margin-top: 5px; }

        .info-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
        .info-table td { padding: 4px 8px; vertical-align: top; }
        .info-label { font-weight: bold; width: 120px; }
        .info-colon { width: 10px; }

        .item-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .item-table th, .item-table td { border: 1px solid #000; padding: 8px; }
        .item-table th { background-color: #f2f2f2; font-weight: bold; text-align: center; text-transform: uppercase; font-size: 11px; }
        .item-table td.center { text-align: center; }

        .signature-table { width: 100%; margin-top: 50px; text-align: center; border-collapse: collapse; }
        .signature-table td { width: 33.33%; padding: 10px; }
        .signature-space { height: 80px; }
        .signature-name { font-weight: bold; text-decoration: underline; }
        .signature-title { font-size: 11px; color: #555; }
    </style>
</head>
<body>

    <div class="header">
        <h1 class="title">SURAT JALAN MUTASI ANTAR GUDANG</h1>
        <p class="subtitle">Dokumen Resmi Pemindahan Fisik Inventaris & Aset Perusahaan</p>
    </div>

    <table class="info-table">
        <tr>
            <td class="info-label">No. Dokumen</td>
            <td class="info-colon">:</td>
            <td style="font-weight: bold;">{{ $transfer->transfer_number }}</td>
            <td class="info-label">Gudang Asal</td>
            <td class="info-colon">:</td>
            <td style="font-weight: bold; color: #d9534f;">{{ optional($transfer->fromWarehouse)->name }}</td>
        </tr>
        <tr>
            <td class="info-label">Tanggal Mutasi</td>
            <td class="info-colon">:</td>
            <td>{{ \Carbon\Carbon::parse($transfer->transfer_date)->format('d F Y') }}</td>
            <td class="info-label">Gudang Tujuan</td>
            <td class="info-colon">:</td>
            <td style="font-weight: bold; color: #5cb85c;">{{ optional($transfer->toWarehouse)->name }}</td>
        </tr>
        <tr>
            <td class="info-label">Alasan / Catatan</td>
            <td class="info-colon">:</td>
            <td colspan="4">{{ $transfer->notes ?? '-' }}</td>
        </tr>
    </table>

    <table class="item-table">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="15%">Kode Barang</th>
                <th width="35%">Nama / Deskripsi Barang</th>
                <th width="15%">Qty Mutasi</th>
                <th width="30%">Catatan / Serial Number</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transfer->items as $index => $item)
            <tr>
                <td class="center">{{ $index + 1 }}</td>
                <td class="center">{{ optional($item->item)->code }}</td>
                <td>
                    <strong>{{ optional($item->item)->name }}</strong>
                    @if(optional($item->item)->is_asset)
                        <br><span style="font-size: 10px; color: #777;">(Aset Tetap / Major Asset)</span>
                    @endif
                </td>
                <td class="center">
                    <strong>{{ (float) $item->qty_transferred }}</strong> {{ optional($item->item)->unit }}
                </td>
                <td style="font-size: 11px;">
                    @if($item->notes)
                        {!! str_replace(' | ', '<br>• ', '• ' . $item->notes) !!}
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
                <span class="signature-title"></span>
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
