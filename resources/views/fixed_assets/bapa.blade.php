<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Berita Acara Pengembalian Aset</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11pt; line-height: 1.5; color: #000; }
        @page { margin: 40px 50px 70px 50px; }
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
        .table-asset th { background-color: #ffe6e6; }
        .signature-box { width: 100%; margin-top: 40px; table-layout: fixed; }
        .signature-box td { text-align: center; vertical-align: bottom; }
        .signature-name { font-weight: bold; text-decoration: underline; margin-top: 70px; }
        footer { position: fixed; bottom: -40px; left: 0px; right: 0px; height: 30px; border-top: 1px solid #888; text-align: right; font-size: 8.5pt; color: #555; padding-top: 5px; font-style: italic; }
        .pagenum:before { content: "Halaman " counter(page); }
    </style>
</head>
<body>

    @php
        $returnLog = $asset->histories->where('status', 'Returned')->first();
        if(!$returnLog) $returnLog = $asset->histories->where('status', 'RETURNED')->first();
        $tanggalPengembalian = $returnLog ? $returnLog->created_at : $asset->updated_at;
    @endphp

    <footer>Dokumen BAPA Aset: {{ $asset->asset_number }} &nbsp; | &nbsp; <span class="pagenum"></span></footer>

    <div class="header">
        <h2>BERITA ACARA PENGEMBALIAN ASET</h2>
        <p>Nomor: BAPA/{{ \Carbon\Carbon::parse($tanggalPengembalian)->format('Y/m/d') }}/{{ substr($asset->asset_number, -4) }}</p>
    </div>

    <div class="content">
        <p>Pada hari ini, tanggal <strong>{{ \Carbon\Carbon::parse($tanggalPengembalian)->translatedFormat('d F Y') }}</strong>, telah dilakukan pengembalian barang/aset perusahaan dengan rincian pihak sebagai berikut:</p>

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
                    <td style="vertical-align: top;">
                        {{ $asset->asset_number }}<br>
                        <small style="color: #555;">{{ $asset->accounting_asset_number ? 'Tag: '.$asset->accounting_asset_number : '' }}</small>
                    </td>
                    <td style="vertical-align: top;">
                        <strong>{{ $asset->name ?? optional($asset->item)->name }}</strong><br>
                        <small>{!! strip_tags($asset->spesifikasi_detail ?? optional($asset->item)->specification) !!}</small>
                    </td>
                    <td style="vertical-align: top;">{{ $asset->serial_number ?? 'N/A' }}</td>
                </tr>
            </tbody>
        </table>

        <p><strong>Keterangan Pengembalian:</strong></p>
        <p style="border: 1px dashed #000; padding: 10px; font-style: italic; background-color: #f9f9f9;">
            {{ $asset->notes ?? 'Dikembalikan ke gudang dalam kondisi baik.' }}
        </p>

        <p>Dengan ditandatanganinya Berita Acara Pengembalian Aset ini, maka tanggung jawab <strong>PIHAK PERTAMA</strong> terhadap pemeliharaan aset tersebut dinyatakan telah <strong>selesai/gugur</strong>.</p>

        {{-- 🔥 KOTAK TANDA TANGAN DINAMIS & TERLENGKAP 🔥 --}}
        <table class="signature-box">
            <tr>
                @foreach($signers as $index => $signer)
                <td style="width: {{ 100 / count($signers) }}%; padding: 10px;">
                    <strong>{{ $index == 0 ? 'YANG MENGEMBALIKAN,' : ($index == 1 ? 'YANG MENERIMA,' : 'MENGETAHUI,') }}</strong><br>
                    <span style="font-size: 9pt;">{{ $index == 0 ? 'PIHAK PERTAMA' : ($index == 1 ? 'PIHAK KEDUA' : 'SAKSI') }}</span>
                    <br><br><br><br><br>
                    <div class="signature-name">{{ $signer->name }}</div>
                    <div style="font-size: 9pt;">
                        {{ $signer->job_title ?? 'Karyawan' }}
                        @if(optional($signer->department)->name)
                            - {{ $signer->department->name }}
                        @endif
                    </div>
                </td>
                @endforeach
            </tr>
        </table>

        <div style="margin-top: 40px; font-size: 8pt; color: #555; text-align: left; font-style: italic;">
            * Dokumen ini dicetak otomatis oleh sistem pada {{ \Carbon\Carbon::now()->translatedFormat('d F Y H:i:s') }} WIB
        </div>
    </div>
</body>
</html>
