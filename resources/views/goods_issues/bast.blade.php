<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Berita Acara Serah Terima Aset</title>
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

        .table-asset { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .table-asset th, .table-asset td { border: 1px solid #000; padding: 6px 8px; text-align: left; }
        .table-asset th { background-color: #f2f2f2; font-size: 10pt; }
        .table-asset td { font-size: 10pt; }

        .signature-box { width: 100%; margin-top: 40px; table-layout: fixed; page-break-inside: avoid; }
        .signature-box td { text-align: center; vertical-align: bottom; width: 50%; }
        .signature-name { font-weight: bold; text-decoration: underline; margin-top: 70px; }

        /* CSS Penjinak HTML Spesifikasi */
        .spesifikasi-html { font-size: 8.5pt; margin-top: 5px; color: #222; text-align: justify; }
        .spesifikasi-html p { margin: 0 0 4px 0; padding: 0; }
        .spesifikasi-html ul, .spesifikasi-html ol { margin: 0 0 4px 0; padding-left: 15px; }
        .spesifikasi-html li { margin-bottom: 2px; text-align: left; }

        .asset-wrapper { margin-bottom: 15px; page-break-inside: avoid; }


        /* Pengaturan Margin Kertas & Footer */
        @page { margin: 40px 50px 70px 50px; }

        footer {
            position: fixed;
            bottom: -40px;
            left: 0px;
            right: 0px;
            height: 30px;
            border-top: 1px solid #888;
            text-align: right;
            font-size: 8.5pt;
            color: #555;
            padding-top: 5px;
            font-style: italic;
        }

        .pagenum:before { content: "Halaman " counter(page); }
    </style>
</head>
<body>

    {{-- 🔥 FOOTER OTOMATIS (Akan berulang di setiap halaman) 🔥 --}}
    <footer>
        Dokumen BAST: {{ $gi->gi_number }} &nbsp; | &nbsp; <span class="pagenum"></span>
    </footer>


    <div class="header">
        <h2>BERITA ACARA SERAH TERIMA ASET</h2>
        <p>Nomor: {{ $asset->bast_number ?? $gi->bast_number }}</p>
    </div>

    <div class="content">
        <p>Pada hari ini, tanggal <strong>{{ \Carbon\Carbon::parse($gi->issue_date)->translatedFormat('d F Y') }}</strong>, telah dilakukan serah terima barang/aset perusahaan dengan rincian pihak sebagai berikut:</p>

        <table class="table-info">
            <tr>
                <td>Nama Penyerah (Admin)</td>
                <td>:</td>
                <td><strong>{{ optional($gi->issuer)->name ?? auth()->user()->name }}</strong></td>
            </tr>
            <tr>
                <td>Jabatan / Departemen</td>
                <td>:</td>
                <td>{{ optional($gi->issuer)->job_title ?? 'Gudang / Logistik' }}</td>
            </tr>
            <tr>
                <td colspan="3"><br><i>Selanjutnya disebut sebagai <strong>PIHAK PERTAMA (Yang Menyerahkan)</strong>.</i></td>
            </tr>
        </table>

        <table class="table-info">
            <tr>
                <td>Nama Penerima (User)</td>
                <td>:</td>
                {{-- Di GI, nama penerima disimpan di requester_name --}}
                <td><strong>{{ $gi->requester_name }}</strong></td>
            </tr>
            <tr>
                <td>Departemen / Proyek</td>
                <td>:</td>
                <td>{{ $gi->department ?? '-' }}</td>
            </tr>
            <tr>
                <td colspan="3"><br><i>Selanjutnya disebut sebagai <strong>PIHAK KEDUA (Yang Menerima)</strong>.</i></td>
            </tr>
        </table>

        <p><strong>PIHAK PERTAMA</strong> menyerahkan barang/aset perusahaan kepada <strong>PIHAK KEDUA</strong>, dan <strong>PIHAK KEDUA</strong> menyatakan telah menerima aset tersebut dalam kondisi <strong>BAIK</strong> dan berfungsi normal dengan rincian:</p>

        {{-- 🔥 PERULANGAN (LOOPING) UNTUK SEMUA ASET YANG DIKELUARKAN 🔥 --}}
        @foreach($assets as $index => $ast)
        <div class="asset-wrapper">
            <table class="table-asset" style="border-bottom: none;">
                <thead>
                    <tr>
                        <th width="5%" class="text-center">No</th>
                        <th width="30%">No. Aset / Label</th>
                        <th width="35%">Nama Barang</th>
                        <th width="30%">Serial Number</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-center" style="vertical-align: top;">{{ $index + 1 }}</td>
                        <td style="vertical-align: top;">
                            {{ $ast->asset_number }}<br>
                            <span style="color: #555; font-size: 8pt;">{{ $ast->accounting_asset_number ? 'FA: '.$ast->accounting_asset_number : '' }}</span>
                        </td>
                        <td style="vertical-align: top;">
                            <strong>{{ $ast->name ?? optional($ast->item)->name }}</strong>
                        </td>
                        <td style="vertical-align: top;">
                            {{ $ast->serial_number ?? 'N/A' }}
                        </td>
                    </tr>
                </tbody>
            </table>

            {{-- SPESIFIKASI MENEMPEL DI BAWAH TABEL --}}
            <div style="padding: 6px 8px; border: 1px solid #000; border-top: none;">
                <strong style="font-size: 9pt;">Spesifikasi / Rincian Detail:</strong>
                @php
                    $spek = $ast->spesifikasi_detail ?? optional($ast->item)->specification;
                @endphp

                @if(!empty(trim(strip_tags($spek))))
                    <div class="spesifikasi-html">
                        {!! str_replace(['&nbsp;', '&amp;'], [' ', '&'], html_entity_decode($spek)) !!}
                    </div>
                @else
                    <div style="font-size: 8.5pt; color: #555; margin-top: 2px;">- Tidak ada spesifikasi khusus -</div>
                @endif
            </div>
        </div>
        @endforeach

        <p style="margin-top: 20px;"><strong>Syarat dan Ketentuan:</strong></p>
        <ol style="margin-top: 0; padding-left: 20px; font-size: 10pt; text-align: justify;">
            <li>PIHAK KEDUA bertanggung jawab penuh atas keutuhan, perawatan, dan keamanan aset tersebut.</li>
            <li>Aset ini adalah milik Perusahaan dan semata-mata digunakan untuk kepentingan pekerjaan.</li>
            <li>PIHAK KEDUA wajib mengembalikan aset ini kepada Perusahaan apabila terjadi pemutusan hubungan kerja (resign) atau jika diminta kembali oleh Perusahaan sewaktu-waktu.</li>
            <li>Segala bentuk kehilangan atau kerusakan akibat kelalaian PIHAK KEDUA akan menjadi tanggung jawab PIHAK KEDUA sesuai peraturan Perusahaan.</li>
        </ol>

        <p>Demikian Berita Acara Serah Terima ini dibuat dengan sebenarnya untuk dapat dipergunakan sebagaimana mestinya.</p>

        {{-- KOTAK TANDA TANGAN (Otomatis tidak akan terbelah halaman) --}}
        <table class="signature-box">
            <tr>
                <td>
                    <strong>YANG MENERIMA,</strong><br>
                    PIHAK KEDUA
                    <br><br><br><br>
                    <div class="signature-name">{{ $gi->requester_name }}</div>
                    <div style="font-size: 9pt;">Karyawan / Penerima</div>
                </td>
                <td>
                    <strong>YANG MENYERAHKAN,</strong><br>
                    PIHAK PERTAMA
                    <br><br><br><br>
                    <div class="signature-name">{{ optional($gi->issuer)->name ?? auth()->user()->name }}</div>
                    <div style="font-size: 9pt;">Bagian Gudang / IT</div>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
