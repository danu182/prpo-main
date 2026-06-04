<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Berita Acara Pengembalian Aset</title>
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
        .table-asset th { background-color: #ffe6e6; } /* Warna merah muda pudar membedakan dengan BAST */
        .signature-box { width: 100%; margin-top: 40px; table-layout: fixed; }
        .signature-box td { text-align: center; vertical-align: bottom; width: 50%; }
        .signature-name { font-weight: bold; text-decoration: underline; margin-top: 70px; }
    </style>
</head>
<body>

    <div class="header">
        <h2>BERITA ACARA PENGEMBALIAN ASET</h2>
        <p>Nomor: BAPA/{{ date('Y/m/d') }}/{{ substr($asset->asset_number, -4) }}</p>
    </div>

    <div class="content">
        <p>Pada hari ini, tanggal <strong>{{ \Carbon\Carbon::now()->translatedFormat('d F Y') }}</strong>, telah dilakukan pengembalian barang/aset perusahaan dengan rincian pihak sebagai berikut:</p>

        <table class="table-info">
            <tr>
                <td>Nama Karyawan (User)</td>
                <td>:</td>
                <td><strong>{{ optional($lastAssignee)->name }}</strong></td>
            </tr>
            <tr>
                <td>Jabatan / Departemen</td>
                <td>:</td>
                <td>{{ optional($lastAssignee)->job_title ?? '-' }}</td>
            </tr>
            {{-- 🔥 TAMBAHAN: INFO PT ASAL KARYAWAN 🔥 --}}
            <tr>
                <td>Entitas / PT</td>
                <td>:</td>
                <td>{{ optional(optional($lastAssignee)->company)->name ?? 'Kantor Pusat' }}</td>
            </tr>
            <tr>
                <td colspan="3"><br><i>Selanjutnya disebut sebagai <strong>PIHAK PERTAMA (Yang Mengembalikan)</strong>.</i></td>
            </tr>
        </table>

        <table class="table-info">
            <tr>
                <td>Nama Admin / GA / IT</td>
                <td>:</td>
                <td><strong>{{ auth()->user()->name }}</strong></td>
            </tr>
            <tr>
                <td>Jabatan / Departemen</td>
                <td>:</td>
                <td>{{ auth()->user()->job_title ?? 'General Affair / IT' }}</td>
            </tr>
            <tr>
                <td colspan="3"><br><i>Selanjutnya disebut sebagai <strong>PIHAK KEDUA (Yang Menerima)</strong>.</i></td>
            </tr>
        </table>

        <p><strong>PIHAK PERTAMA</strong> telah menyerahkan kembali barang/aset milik Perusahaan kepada <strong>PIHAK KEDUA</strong> dalam kondisi yang telah diperiksa dan disetujui bersama, dengan rincian barang sebagai berikut:</p>

        <table class="table-asset">
            <thead>
                <tr>
                    <th width="25%">No. Aset / Label</th>
                    <th width="45%">Nama & Spesifikasi Barang</th>
                    <th width="30%">Serial Number</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        {{ $asset->asset_number }}<br>
                        <small style="color: #555;">{{ $asset->accounting_asset_number ? 'Tag: '.$asset->accounting_asset_number : '' }}</small>
                    </td>
                    <td>
                        {{-- 🔥 PERBAIKAN: Tampilkan nama spesifik aset jika ada 🔥 --}}
                        <strong>{{ $asset->name ?? optional($asset->item)->name }}</strong><br>
                        <small>{{ $asset->spesifikasi_detail ?? optional($asset->item)->specification }}</small>
                    </td>
                    <td>{{ $asset->serial_number ?? 'N/A' }}</td>
                </tr>
            </tbody>
        </table>

        <p><strong>Keterangan Pengembalian:</strong></p>
        <p style="border: 1px dashed #000; padding: 10px; font-style: italic; background-color: #f9f9f9;">
            {{ $asset->notes ?? 'Dikembalikan ke gudang/IT dalam kondisi baik dan lengkap.' }}
        </p>

        <p>Dengan ditandatanganinya Berita Acara Pengembalian Aset ini, maka tanggung jawab <strong>PIHAK PERTAMA</strong> terhadap pemeliharaan aset tersebut dinyatakan telah <strong>selesai/gugur</strong>.</p>

        <table class="signature-box">
            <tr>
                <td>
                    <strong>YANG MENGEMBALIKAN,</strong><br>
                    PIHAK PERTAMA
                    <br><br><br><br>
                    <div class="signature-name">{{ optional($lastAssignee)->name }}</div>
                    <div style="font-size: 9pt;">{{ optional($lastAssignee)->job_title ?? 'Karyawan' }}</div>
                </td>
                <td>
                    <strong>YANG MENERIMA,</strong><br>
                    PIHAK KEDUA
                    <br><br><br><br>
                    <div class="signature-name">{{ auth()->user()->name }}</div>
                    <div style="font-size: 9pt;">{{ auth()->user()->job_title ?? 'GA / IT Dept' }}</div>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
