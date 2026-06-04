<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>BAST Massal - {{ $batch->batch_id }}</title>
    <style>
        body { font-family: 'Helvetica', Arial, sans-serif; font-size: 12pt; line-height: 1.5; color: #333; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        .title { font-size: 14pt; font-weight: bold; text-decoration: underline; margin-bottom: 5px; }
        .subtitle { font-size: 11pt; font-weight: normal; margin-top: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; margin-bottom: 20px; }
        table, th, td { border: 1px solid #000; }
        th { padding: 8px; background-color: #f2f2f2; text-align: center; font-size: 10pt; }
        td { padding: 8px; font-size: 10pt; vertical-align: top; }
        .signature-box { width: 100%; margin-top: 40px; border: none; }
        .signature-box td { border: none; text-align: center; width: 50%; }
        .sign-space { height: 80px; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>

    @foreach($assets->groupBy('assigned_to') as $userId => $userAssets)
        @php $peminjam = $userAssets->first()->user; @endphp

        <div class="header">
            <h2 style="margin:0;">PT PERUSAHAAN KOMANDAN JAYA</h2>
            <p style="margin:5px 0 0 0; font-size:10pt;">Gedung Pusat, Lt. 5, Jl. Sudirman No. 1, Jakarta</p>
        </div>

        <div style="text-align: center;">
            <div class="title">BERITA ACARA SERAH TERIMA (BAST) ASET</div>
            <div class="subtitle">Nomor Batch Referensi: {{ $batch->batch_id }}</div>
        </div>

        <p style="margin-top: 30px;">Pada hari ini, diserahkan aset perusahaan dengan rincian sebagai berikut:</p>

        <p>
            <strong>Pihak Pertama (Yang Menyerahkan):</strong><br>
            Departemen IT / General Affair
        </p>

        <p>
            <strong>Pihak Kedua (Yang Menerima):</strong><br>
            Nama: {{ $peminjam->name ?? 'N/A' }}<br>
            Email: {{ $peminjam->email ?? '-' }}<br>
        </p>

        <p>Dengan rincian aset yang diserahterimakan:</p>

        <table>
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th width="20%">No. Aset</th>
                    <th width="35%">Nama Barang & Spesifikasi</th>
                    <th width="20%">S/N Fisik</th>
                    <th width="20%">Kondisi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($userAssets as $index => $asset)
                <tr>
                    <td style="text-align: center;">{{ $index + 1 }}</td>
                    <td>{{ $asset->asset_number }}</td>
                    <td>
                        <strong>{{ $asset->item->name ?? '-' }}</strong><br>
                        <span style="font-size: 9pt;">{{ $asset->spesifikasi_detail }}</span>
                    </td>
                    <td>{{ $asset->serial_number ?? '-' }}</td>
                    <td style="text-align: center;">Baik / Baru</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <p style="font-size: 10pt; text-align: justify;">
            Pihak Kedua menyatakan telah menerima aset tersebut di atas dalam kondisi baik dan berfungsi sebagaimana mestinya. Pihak Kedua wajib menjaga dan merawat aset tersebut sesuai dengan kebijakan perusahaan.
        </p>

        <table class="signature-box">
            <tr>
                <td>
                    <strong>Pihak Pertama,</strong><br>
                    Yang Menyerahkan
                    <div class="sign-space"></div>
                    ( .................................... )<br>
                    <strong>IT / GA Dept</strong>
                </td>
                <td>
                    <strong>Pihak Kedua,</strong><br>
                    Yang Menerima
                    <div class="sign-space"></div>
                    ( .................................... )<br>
                    <strong>{{ $peminjam->name ?? 'N/A' }}</strong>
                </td>
            </tr>
        </table>

        {{-- Jangan beri page-break di halaman terakhir --}}
        @if(!$loop->last)
            <div class="page-break"></div>
        @endif

    @endforeach

</body>
</html>
