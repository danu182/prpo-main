<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>BAST - {{ $gi->gi_number }}</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11pt; line-height: 1.5; color: #000; }
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
        .header h2 { margin: 0; font-size: 16pt; text-transform: uppercase; text-decoration: underline; }
        .header p { margin: 5px 0 0 0; font-size: 10pt; }
        .content { margin-top: 20px; }
        .table-info { width: 100%; border-collapse: collapse; margin-top: 10px; margin-bottom: 20px; }
        .table-info td { vertical-align: top; padding: 5px 0; }
        .table-info td:first-child { width: 30%; font-weight: bold; }
        .table-info td:nth-child(2) { width: 3%; }
        .table-asset { width: 100%; border-collapse: collapse; margin-top: 15px; margin-bottom: 20px; }
        .table-asset th, .table-asset td { border: 1px solid #000; padding: 8px; text-align: left; }
        .table-asset th { background-color: #f2f2f2; }
        .signature-box { width: 100%; margin-top: 40px; table-layout: fixed; }
        .signature-box td { text-align: center; vertical-align: bottom; width: 50%; }
        .signature-name { font-weight: bold; text-decoration: underline; margin-top: 70px; }
        
        /* Agar saat di-print (Ctrl+P) formatnya rapi */
        @media print {
            body { margin: 0; padding: 0; }
            @page { margin: 2cm; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="header">
        <h2>BERITA ACARA SERAH TERIMA ASET</h2>
        <p>Nomor: BAST/{{ date('Y/m', strtotime($gi->issue_date)) }}/{{ substr($gi->gi_number, -4) }}</p>
    </div>

    <div class="content">
        <p>Pada hari ini, tanggal <strong>{{ \Carbon\Carbon::parse($gi->issue_date)->translatedFormat('d F Y') }}</strong>, telah dilakukan serah terima barang/aset perusahaan dengan rincian pihak sebagai berikut:</p>

        <table class="table-info">
            <tr>
                <td>Nama Penyerah (Admin)</td>
                <td>:</td>
                <td><strong>{{ $gi->created_by_name ?? auth()->user()->name }}</strong></td>
            </tr>
            <tr>
                <td>Jabatan / Departemen</td>
                <td>:</td>
                <td>{{ auth()->user()->job_title ?? 'General Affair / IT' }}</td>
            </tr>
            <tr>
                <td colspan="3"><br><i>Selanjutnya disebut sebagai <strong>PIHAK PERTAMA (Yang Menyerahkan)</strong>.</i></td>
            </tr>
        </table>

        <table class="table-info">
            <tr>
                <td>Nama Penerima (User)</td>
                <td>:</td>
                <td><strong>{{ $gi->requester_name }}</strong></td>
            </tr>
            <tr>
                <td>Jabatan / Departemen</td>
                <td>:</td>
                <td>{{ $gi->department ?? '-' }}</td>
            </tr>
            <tr>
                <td>Lokasi / Perusahaan</td>
                <td>:</td>
                <td>Kantor Pusat / Cabang Terkait</td>
            </tr>
            <tr>
                <td colspan="3"><br><i>Selanjutnya disebut sebagai <strong>PIHAK KEDUA (Yang Menerima)</strong>.</i></td>
            </tr>
        </table>

        <p><strong>PIHAK PERTAMA</strong> menyerahkan barang/aset perusahaan kepada <strong>PIHAK KEDUA</strong>, dan <strong>PIHAK KEDUA</strong> menyatakan telah menerima barang/aset tersebut dalam kondisi <strong>BAIK</strong> dan berfungsi normal dengan rincian:</p>

        <table class="table-asset">
            <thead>
                <tr>
                    <th width="5%" class="text-center">No</th>
                    <th width="25%">No. Aset / Label</th>
                    <th width="40%">Nama & Spesifikasi Barang</th>
                    <th width="30%">Serial Number</th>
                </tr>
            </thead>
            <tbody>
                {{-- 🔥 LOOPING SEMUA ASET YANG DIKELUARKAN DI DOKUMEN INI 🔥 --}}
                @forelse($assets as $index => $asset)
                <tr>
                    <td style="text-align: center;">{{ $index + 1 }}</td>
                    <td>
                        <strong>{{ $asset->asset_number }}</strong><br>
                        <small style="color: #555;">{{ $asset->accounting_asset_number ? 'Acc: '.$asset->accounting_asset_number : '' }}</small>
                    </td>
                    <td>
                        <strong>{{ $asset->name }}</strong><br>
                        <small>{{ $asset->spesifikasi_detail ?? '-' }}</small>
                    </td>
                    <td>{{ $asset->serial_number ?? 'N/A' }}</td>
                </tr>
                @empty
                {{-- Fallback jika yang dikeluarkan adalah stok biasa (bukan aset) --}}
                @foreach($gi->items as $index => $item)
                <tr>
                    <td style="text-align: center;">{{ $index + 1 }}</td>
                    <td>N/A (Stok Biasa)</td>
                    <td><strong>{{ $item->item->name }}</strong><br><small>{{ $item->qty_issued }} {{ $item->uom_name }}</small></td>
                    <td>-</td>
                </tr>
                @endforeach
                @endforelse
            </tbody>
        </table>

        <p><strong>Syarat dan Ketentuan:</strong></p>
        <ol style="margin-top: 0; padding-left: 20px; font-size: 10pt; text-align: justify;">
            <li>PIHAK KEDUA bertanggung jawab penuh atas keutuhan, perawatan, dan keamanan aset tersebut.</li>
            <li>Aset ini adalah milik Perusahaan dan semata-mata digunakan untuk kepentingan pekerjaan.</li>
            <li>PIHAK KEDUA wajib mengembalikan aset ini kepada Perusahaan apabila terjadi pemutusan hubungan kerja (resign) atau jika diminta kembali oleh Perusahaan sewaktu-waktu.</li>
            <li>Segala bentuk kehilangan atau kerusakan akibat kelalaian PIHAK KEDUA akan menjadi tanggung jawab PIHAK KEDUA sesuai peraturan Perusahaan.</li>
        </ol>

        <p>Demikian Berita Acara Serah Terima ini dibuat dengan sebenarnya untuk dapat dipergunakan sebagaimana mestinya.</p>

        <table class="signature-box">
            <tr>
                <td>
                    <strong>YANG MENERIMA,</strong><br>
                    PIHAK KEDUA
                    <br><br><br><br><br>
                    <div class="signature-name">{{ $gi->requester_name }}</div>
                    <div style="font-size: 9pt;">{{ $gi->department ?? 'Karyawan' }}</div>
                </td>
                <td>
                    <strong>YANG MENYERAHKAN,</strong><br>
                    PIHAK PERTAMA
                    <br><br><br><br><br>
                    <div class="signature-name">{{ $gi->created_by_name ?? auth()->user()->name }}</div>
                    <div style="font-size: 9pt;">{{ auth()->user()->job_title ?? 'GA / IT Dept' }}</div>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>