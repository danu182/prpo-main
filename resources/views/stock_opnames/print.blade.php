<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lembar Stock Opname - {{ $opname->document_number }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #000; margin: 0; padding: 20px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .header h2 { margin: 0 0 5px 0; font-size: 18px; text-transform: uppercase; }
        .info-table { width: 100%; margin-bottom: 20px; }
        .info-table td { padding: 3px 0; }
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 8px; text-align: left; }
        .data-table th { background-color: #f0f0f0; text-align: center; }
        .sign-table { width: 100%; margin-top: 50px; text-align: center; }
        .sign-table td { width: 33%; padding-bottom: 70px; vertical-align: bottom; }
        .sign-line { border-top: 1px solid #000; width: 70%; margin: 0 auto; padding-top: 5px; }

        /* Area untuk diisi manual (Kosong) */
        .fill-area { height: 25px; }

        @media print {
            body { padding: 0; }
            @page { margin: 1cm; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="header">
        <h2>Lembar Kerja Stock Opname (Blind Count)</h2>
        <strong>No. Dokumen: {{ $opname->document_number }}</strong>
    </div>

    <table class="info-table">
        <tr>
            <td width="15%"><strong>Lokasi / Gudang</strong></td>
            <td width="35%">: {{ optional($opname->warehouse)->name }}</td>
            <td width="15%"><strong>Tanggal Cetak</strong></td>
            <td width="35%">: {{ date('d-m-Y H:i') }}</td>
        </tr>
        <tr>
            <td><strong>Tgl Mulai Opname</strong></td>
            <td>: {{ \Carbon\Carbon::parse($opname->start_date)->format('d-m-Y') }}</td>
            <td><strong>Dibuat Oleh</strong></td>
            <td>: {{ optional($opname->creator)->name }}</td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="15%">Kode Barang</th>
                <th width="35%">Nama Barang</th>
                <th width="10%">Satuan</th>
                <th width="15%">Hasil Hitung Fisik<br>(Diisi Manual)</th>
                <th width="20%">Keterangan / Kondisi<br>(Diisi Manual)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($opname->items as $index => $item)
            <tr>
                <td style="text-align: center;">{{ $index + 1 }}</td>
                <td>{{ optional($item->item)->code }}</td>
                <td><strong>{{ optional($item->item)->name }}</strong></td>
                <td style="text-align: center;">{{ $item->base_uom }}</td>
                <td class="fill-area"></td>
                <td class="fill-area"></td>
            </tr>
            @empty
            <tr>
                <td colspan="6" style="text-align: center; padding: 20px;">Tidak ada data stok di gudang ini untuk dihitung.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <table class="sign-table">
        <tr>
            <td>
                <div class="sign-line">Dihitung Oleh (Checker)</div>
            </td>
            <td>
                <div class="sign-line">Diverifikasi Oleh (Supervisor)</div>
            </td>
            <td>
                <div class="sign-line">Diinput Ke Sistem Oleh</div>
            </td>
        </tr>
    </table>

</body>
</html>
