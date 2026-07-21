<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Lembar Stock Opname - {{ $opname->document_number }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 11px; color: #000; margin: 0; padding: 0; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .header h2 { margin: 0 0 5px 0; font-size: 18px; text-transform: uppercase; }
        .info-table { width: 100%; margin-bottom: 20px; }
        .info-table td { padding: 3px 0; font-size: 11px; }

        /* Tabel Rincian */
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 6px; text-align: left; vertical-align: middle; }
        .data-table th { background-color: #e9ecef; text-align: center; font-weight: bold; }

        /* Menghindari baris tabel terpotong di halaman baru */
        .data-table tr { page-break-inside: avoid; }

        .sign-table { width: 100%; margin-top: 50px; text-align: center; page-break-inside: avoid; }
        .sign-table td { width: 33%; padding-bottom: 60px; vertical-align: bottom; }
        .sign-line { border-top: 1px solid #000; width: 80%; margin: 0 auto; padding-top: 5px; font-weight: bold; }

        .fill-area { height: 25px; }
    </style>
</head>
<body>

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
                <td colspan="6" style="text-align: center; padding: 20px; font-style: italic;">Tidak ada data stok di gudang ini untuk dihitung.</td>
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
