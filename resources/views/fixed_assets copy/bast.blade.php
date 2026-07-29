<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Berita Acara Serah Terima Aset</title>
    <style>
        /* Pengaturan Margin Kertas & Footer */
        @page { margin: 40px 50px 70px 50px; }

        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11pt; line-height: 1.5; color: #000; }
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
        .header h2 { margin: 0; font-size: 16pt; text-transform: uppercase; text-decoration: underline; }
        .header p { margin: 5px 0 0 0; font-size: 10pt; }
        .content { margin-top: 20px; }

        .table-info { width: 100%; border-collapse: collapse; margin-top: 10px; margin-bottom: 20px; }
        .table-info td { vertical-align: top; padding: 5px 0; }
        .table-info td:first-child { width: 30%; font-weight: bold; }
        .table-info td:nth-child(2) { width: 3%; }

        .table-asset { width: 100%; border-collapse: collapse; margin-top: 15px; margin-bottom: 10px; }
        .table-asset th, .table-asset td { border: 1px solid #000; padding: 8px; text-align: left; }
        .table-asset th { background-color: #f2f2f2; }

        .signature-box { width: 100%; margin-top: 40px; table-layout: fixed; }
        .signature-box td { text-align: center; vertical-align: bottom; width: 50%; }
        .signature-name { font-weight: bold; text-decoration: underline; margin-top: 70px; }

        /* Cegah Kertas Bolong */
        .table-asset { page-break-inside: auto; }
        .table-asset tr { page-break-inside: avoid; page-break-after: auto; }

        /* CSS Penjinak HTML Spesifikasi */
        .spesifikasi-html { font-size: 8.5pt; margin-top: 5px; color: #333; text-align: justify; }
        .spesifikasi-html p { margin: 0 0 4px 0; padding: 0; }
        .spesifikasi-html ul, .spesifikasi-html ol { margin: 0 0 4px 0; padding-left: 15px; }
        .spesifikasi-html li { margin-bottom: 2px; text-align: left; }

        /* CSS Footer Abadi */
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

    {{-- 🔥 LOGIKA MENGUNCI TANGGAL PERMANEN DARI RIWAYAT PENYERAHAN (HANDOVER) 🔥 --}}
    @php
        // Sistem melacak histori dengan status HANDOVER (saat diserahkan ke user)
        $handoverLog = $asset->histories->where('status', 'HANDOVER')->sortByDesc('created_at')->first();
        $tanggalPenyerahan = $handoverLog ? $handoverLog->created_at : $asset->updated_at;
    @endphp

    {{-- 🔥 FOOTER OTOMATIS 🔥 --}}
    <footer>
        Dokumen BAST Aset: {{ $asset->asset_number }} &nbsp; | &nbsp; <span class="pagenum"></span>
    </footer>

    <div class="header">
        <h2>BERITA ACARA SERAH TERIMA ASET</h2>
        {{-- Tanggal di nomor BAST akan dikunci --}}
        <p>Nomor: BAST/{{ \Carbon\Carbon::parse($tanggalPenyerahan)->format('Y/m/d') }}/{{ substr($asset->asset_number, -4) }}</p>
    </div>

    <div class="content">
        {{-- Tanggal narasi surat BAST akan dikunci --}}
        <p>Pada hari ini, tanggal <strong>{{ \Carbon\Carbon::parse($tanggalPenyerahan)->translatedFormat('d F Y') }}</strong>, telah dilakukan serah terima barang/aset perusahaan dengan rincian pihak sebagai berikut:</p>

        <table class="table-info">
            <tr>
                <td>Nama Penyerah (Admin)</td>
                <td>:</td>
                {{-- Jika ada log, ambil nama Admin yang dulu menekan tombol serahkan --}}
                <td><strong>{{ $handoverLog ? optional($handoverLog->creator)->name : auth()->user()->name }}</strong></td>
            </tr>
            <tr>
                <td>Jabatan / Departemen</td>
                <td>:</td>
                <td>{{ $handoverLog ? optional($handoverLog->creator)->job_title : (auth()->user()->job_title ?? 'General Affair / IT') }}</td>
            </tr>
            <tr>
                <td colspan="3"><br><i>Selanjutnya disebut sebagai <strong>PIHAK PERTAMA (Yang Menyerahkan)</strong>.</i></td>
            </tr>
        </table>

        <table class="table-info">
            <tr>
                <td>Nama Penerima (User)</td>
                <td>:</td>
                <td><strong>{{ optional($asset->assignee)->name }}</strong></td>
            </tr>
            <tr>
                <td>Jabatan / Departemen</td>
                <td>:</td>
                <td>{{ optional($asset->assignee)->job_title ?? '-' }}</td>
            </tr>
            <tr>
                <td>Lokasi / Perusahaan</td>
                <td>:</td>
                <td>{{ optional(optional($asset->assignee)->company)->name ?? 'Kantor Pusat' }}</td>
            </tr>
            <tr>
                <td colspan="3"><br><i>Selanjutnya disebut sebagai <strong>PIHAK KEDUA (Yang Menerima)</strong>.</i></td>
            </tr>
        </table>

        <p><strong>PIHAK PERTAMA</strong> menyerahkan barang/aset perusahaan kepada <strong>PIHAK KEDUA</strong>, dan <strong>PIHAK KEDUA</strong> menyatakan telah menerima barang/aset tersebut dalam kondisi <strong>BAIK</strong> dan berfungsi normal dengan rincian:</p>

        {{-- TABEL HANYA UNTUK IDENTITAS INTI --}}
        <table class="table-asset">
            <thead>
                <tr>
                    <th width="30%">No. Aset / Label</th>
                    <th width="40%">Nama Barang</th>
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
                        <strong>{{ $asset->name ?? optional($asset->item)->name }}</strong>
                    </td>
                    <td style="vertical-align: top;">
                        {{ $asset->serial_number ?? 'N/A' }}
                    </td>
                </tr>
            </tbody>
        </table>

        {{-- SPESIFIKASI DIKELUARKAN DARI TABEL AGAR BISA TERBELAH KE HALAMAN 2 --}}
        @php
            $spek = $asset->spesifikasi_detail ?? optional($asset->item)->specification;
        @endphp

        @if(!empty(trim(strip_tags($spek))))
        <div style="margin-bottom: 20px;">
            <strong style="font-size: 10pt;">Spesifikasi Detail:</strong>
            <div class="spesifikasi-html">
                {!! str_replace(['&nbsp;', '&amp;'], [' ', '&'], html_entity_decode($spek)) !!}
            </div>
        </div>
        @endif

        <p><strong>Syarat dan Ketentuan:</strong></p>
        <ol style="margin-top: 0; padding-left: 20px; font-size: 10pt; text-align: justify;">
            <li>PIHAK KEDUA bertanggung jawab penuh atas keutuhan, perawatan, dan keamanan aset tersebut.</li>
            <li>Aset ini adalah milik Perusahaan ({{ optional($asset->company)->name ?? 'Kantor Pusat' }}) dan semata-mata digunakan untuk kepentingan pekerjaan.</li>
            <li>PIHAK KEDUA wajib mengembalikan aset ini kepada Perusahaan apabila terjadi pemutusan hubungan kerja (resign) atau jika diminta kembali oleh Perusahaan sewaktu-waktu.</li>
            <li>Segala bentuk kehilangan atau kerusakan akibat kelalaian PIHAK KEDUA akan menjadi tanggung jawab PIHAK KEDUA sesuai peraturan Perusahaan.</li>
        </ol>

        <p>Demikian Berita Acara Serah Terima ini dibuat dengan sebenarnya untuk dapat dipergunakan sebagaimana mestinya.</p>

        <table class="signature-box">
            <tr>
                <td>
                    <strong>YANG MENERIMA,</strong><br>
                    PIHAK KEDUA
                    <br><br><br><br>
                    <div class="signature-name">{{ optional($asset->assignee)->name }}</div>
                    <div style="font-size: 9pt;">{{ optional($asset->assignee)->job_title ?? 'Karyawan' }}</div>
                </td>
                <td>
                    <strong>YANG MENYERAHKAN,</strong><br>
                    PIHAK PERTAMA
                    <br><br><br><br>
                    <div class="signature-name">{{ $handoverLog ? optional($handoverLog->creator)->name : auth()->user()->name }}</div>
                    <div style="font-size: 9pt;">{{ $handoverLog ? optional($handoverLog->creator)->job_title : (auth()->user()->job_title ?? 'GA / IT Dept') }}</div>
                </td>
            </tr>
        </table>

        {{-- 🔥 TIMESTAMP WAKTU CETAK DOKUMEN 🔥 --}}
        <div style="margin-top: 40px; font-size: 8pt; color: #555; text-align: left; font-style: italic;">
            * Dokumen ini dicetak otomatis oleh sistem pada {{ \Carbon\Carbon::now()->translatedFormat('d F Y H:i:s') }} WIB
        </div>
    </div>

</body>
</html>
