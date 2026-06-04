<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Berita Acara Penghapusan Aset (BAPP)</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11pt; line-height: 1.5; color: #000; }
        .header { text-align: center; border-bottom: 3px double #000; padding-bottom: 10px; margin-bottom: 20px; }
        .header h2 { margin: 0; font-size: 16pt; text-transform: uppercase; text-decoration: underline; color: #b30000; }
        .header p { margin: 5px 0 0 0; font-size: 10pt; font-weight: bold; }
        .content { margin-top: 20px; text-align: justify; }
        .table-info { width: 100%; border-collapse: collapse; margin-top: 10px; margin-bottom: 20px; }
        .table-info td { vertical-align: top; padding: 5px 0; }
        .table-info td:first-child { width: 30%; font-weight: bold; }
        .table-info td:nth-child(2) { width: 3%; }
        .table-asset { width: 100%; border-collapse: collapse; margin-top: 15px; margin-bottom: 20px; }
        .table-asset th, .table-asset td { border: 1px solid #000; padding: 8px; text-align: left; }
        .table-asset th { background-color: #e6e6e6; }
        .signature-box { width: 100%; margin-top: 50px; table-layout: fixed; }
        .signature-box td { text-align: center; vertical-align: bottom; width: 33.33%; }
        .signature-name { font-weight: bold; text-decoration: underline; margin-top: 80px; }
    </style>
</head>
<body>

    <div class="header">
        <h2>BERITA ACARA PENGHAPUSAN / PEMUSNAHAN ASET</h2>
        <p>Nomor: BAPP/{{ date('Y/m/d') }}/{{ substr($asset->asset_number, -4) }}</p>
    </div>

    <div class="content">
        <p>Pada hari ini, tanggal <strong>{{ \Carbon\Carbon::now()->translatedFormat('d F Y') }}</strong>, menerangkan bahwa Perusahaan telah melakukan PENGHAPUSAN / PEMUSNAHAN / PENJUALAN RONGSOKAN terhadap Barang Inventaris / Aset Tetap Perusahaan dengan rincian sebagai berikut:</p>

        {{-- 🔥 TAMBAHAN: INFO ENTITAS PEMILIK ASET 🔥 --}}
        <table class="table-info">
            <tr>
                <td>Entitas Pemilik Aset (PT)</td>
                <td>:</td>
                <td style="font-size: 12pt;"><strong>{{ optional($asset->company)->name ?? 'Kantor Pusat / Umum' }}</strong></td>
            </tr>
        </table>

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

        <p>Penghapusan / Pemusnahan aset tersebut di atas dilakukan berdasarkan hasil evaluasi dan pemeriksaan dengan alasan / keterangan sebagai berikut:</p>

        <div style="border: 2px dashed #b30000; background-color: #fff0f0; padding: 15px; font-weight: bold; color: #b30000; text-align: center; margin: 15px 0;">
            "{{ $asset->notes ?? 'Aset rusak berat / sudah tidak memiliki nilai ekonomis / hilang.' }}"
        </div>

        <p>Dengan ditandatanganinya Berita Acara ini, maka aset tersebut secara resmi <strong>DIHAPUSBUKUKAN</strong> dari daftar inventaris kekayaan Perusahaan, dan segala nilai buku yang terkait dengan aset ini akan disesuaikan oleh Departemen Keuangan / Akuntansi.</p>

        <p>Demikian Berita Acara ini dibuat dengan sebenar-benarnya untuk dapat dipergunakan sebagai dokumen audit dan penyesuaian laporan keuangan Perusahaan.</p>

        <table class="signature-box">
            <tr>
                <td>
                    <strong>Dibuat & Dieksekusi Oleh,</strong><br>
                    Dept. IT / General Affair
                    <br><br><br><br>
                    <div class="signature-name">{{ auth()->user()->name }}</div>
                    <div style="font-size: 9pt;">{{ auth()->user()->job_title ?? 'Admin Aset' }}</div>
                </td>
                <td>
                    <strong>Mengetahui / Saksi,</strong><br>
                    Dept. Finance / Accounting
                    <br><br><br><br>
                    <div class="signature-name">______________________</div>
                    <div style="font-size: 9pt;">Manager Keuangan</div>
                </td>
                <td>
                    <strong>Disetujui Oleh,</strong><br>
                    Manajemen / Direksi
                    <br><br><br><br>
                    <div class="signature-name">______________________</div>
                    <div style="font-size: 9pt;">Direktur / General Manager</div>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
