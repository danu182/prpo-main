<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Berita Acara Penghapusan Aset (BAPP)</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11pt; line-height: 1.5; color: #000; }
        @page { margin: 40px 50px 70px 50px; }

        /* Kop Dokumen */
        .header { text-align: center; border-bottom: 3px double #000; padding-bottom: 10px; margin-bottom: 20px; }
        .header h2 { margin: 0; font-size: 16pt; text-transform: uppercase; text-decoration: underline; color: #b30000; }
        .header p { margin: 5px 0 0 0; font-size: 10pt; font-weight: bold; }

        .content { margin-top: 20px; text-align: justify; }

        /* Tabel Informasi Pemilik */
        .table-info { width: 100%; border-collapse: collapse; margin-top: 10px; margin-bottom: 20px; }
        .table-info td { vertical-align: top; padding: 5px 0; }
        .table-info td:first-child { width: 30%; font-weight: bold; }
        .table-info td:nth-child(2) { width: 3%; }

        /* Tabel Detail Aset */
        .table-asset { width: 100%; border-collapse: collapse; margin-top: 15px; margin-bottom: 20px; }
        .table-asset th, .table-asset td { border: 1px solid #000; padding: 8px; text-align: left; }
        .table-asset th { background-color: #e6e6e6; }

        /* Tanda Tangan */
        .signature-box { width: 100%; margin-top: 50px; table-layout: fixed; }
        .signature-box td { text-align: center; vertical-align: bottom; width: 33.33%; }
        .signature-name { font-weight: bold; text-decoration: underline; margin-top: 80px; }

        /* Cap Disposed */
        .stamp-disposed {
            position: absolute; top: 150px; right: 20px; color: rgba(220, 53, 69, 0.2);
            font-size: 60pt; font-weight: bold; text-transform: uppercase;
            transform: rotate(-15deg); z-index: -1; border: 5px solid rgba(220, 53, 69, 0.2);
            padding: 10px 20px; border-radius: 10px;
        }

        /* Kotak Alasan Disposal */
        .reason-box {
            border: 2px dashed #b30000;
            background-color: #fff0f0;
            padding: 15px;
            font-weight: bold;
            color: #b30000;
            text-align: center;
            margin: 15px 0;
            border-radius: 5px;
        }

        /* Footer */
        footer {
            position: fixed; bottom: -40px; left: 0px; right: 0px; height: 30px;
            border-top: 1px solid #888; text-align: right; font-size: 8.5pt; color: #555;
            padding-top: 5px; font-style: italic;
        }
        .pagenum:before { content: "Halaman " counter(page); }
    </style>
</head>
<body>

    {{-- 🔥 LOGIKA AMAN & PENCARIAN VAR PEMEGANG ASET TERAKHIR 🔥 --}}
    @php
        // 1. Log Riwayat Disposal Terakhir
        $disposeLog = optional($asset->histories)->first(function($log) {
            $noteLower = strtolower($log->notes);
            return str_contains($noteLower, 'disposed') || str_contains($noteLower, 'penghapusan') || str_contains($noteLower, 'dihapus');
        });

        $tanggalPenghapusan = $disposeLog ? $disposeLog->created_at : $asset->updated_at;
        $adminPembuat = $disposeLog ? optional($disposeLog->creator)->name : auth()->user()->name;
        $jabatanAdmin = $disposeLog ? optional($disposeLog->creator)->job_title : (auth()->user()->job_title ?? 'Admin Aset');

        // 2. Definisi Variabel $lastAssignee agar tidak Undefined Error
        $lastAssignee = $asset->assignee;
        if (!$lastAssignee) {
            $historyUser = optional($asset->histories)->first(function($h) {
                return !empty($h->assigned_to);
            });
            if ($historyUser) {
                $lastAssignee = \App\Models\User::find($historyUser->assigned_to);
            }
        }

        // 3. Pembersihan Teks Alasan
        $alasanDisposal = '';
        if ($disposeLog) {
            $rawNotes = $disposeLog->notes;
            if (str_contains($rawNotes, '| Alasan Resmi: ')) {
                $parts = explode('| Alasan Resmi: ', $rawNotes);
                $alasanDisposal = trim(end($parts));
            } elseif (str_contains($rawNotes, '| Catatan: ')) {
                $parts = explode('| Catatan: ', $rawNotes);
                $alasanDisposal = trim(end($parts));
            } else {
                $alasanDisposal = trim($rawNotes);
            }
        }

        $alasanDisposal = $alasanDisposal ?: ($asset->notes ?? 'Aset rusak berat / tidak dapat diperbaiki / sudah tidak memiliki nilai ekonomis.');
    @endphp

    <div class="stamp-disposed">DISPOSED</div>

    <footer>
        Dokumen BAPP Aset: {{ $asset->asset_number }} &nbsp; | &nbsp; <span class="pagenum"></span>
    </footer>

    <div class="header">
        <h2>BERITA ACARA PENGHAPUSAN / PEMUSNAHAN ASET</h2>
        <p>Nomor: BAPP/{{ \Carbon\Carbon::parse($tanggalPenghapusan)->format('Y/m/d') }}/{{ substr($asset->asset_number, -4) }}</p>
    </div>

    <div class="content">
        <p>Pada hari ini, tanggal <strong>{{ \Carbon\Carbon::parse($tanggalPenghapusan)->translatedFormat('d F Y') }}</strong>, menerangkan bahwa Perusahaan telah melakukan PENGHAPUSAN / PEMUSNAHAN / PENJUALAN RONGSOKAN terhadap Barang Inventaris / Aset Tetap Perusahaan dengan rincian sebagai berikut:</p>

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
                        <strong>{{ $asset->name ?? optional($asset->item)->name }}</strong><br>
                        <small>{!! strip_tags($asset->spesifikasi_detail ?? optional($asset->item)->specification) !!}</small>
                    </td>
                    <td>{{ $asset->serial_number ?? 'N/A' }}</td>
                </tr>
            </tbody>
        </table>

        <p>Penghapusan / Pemusnahan aset tersebut di atas dilakukan berdasarkan hasil evaluasi dan pemeriksaan dengan alasan / keterangan sebagai berikut:</p>

        <div class="reason-box">
            "{{ $alasanDisposal }}"
        </div>

        <p>Dengan ditandatanganinya Berita Acara ini, maka aset tersebut secara resmi <strong>DIHAPUSBUKUKAN</strong> dari daftar inventaris kekayaan Perusahaan, dan segala nilai buku yang terkait dengan aset ini akan disesuaikan oleh Departemen Keuangan / Akuntansi.</p>

        <p>Demikian Berita Acara ini dibuat dengan sebenar-benarnya untuk dapat dipergunakan sebagai dokumen audit dan penyesuaian laporan keuangan Perusahaan.</p>

        <table class="signature-box">
            <tr>
                <td>
                    <strong>Dibuat & Dieksekusi Oleh,</strong><br>
                    Dept. IT / General Affair
                    <br><br><br><br>
                    <div class="signature-name">{{ $adminPembuat }}</div>
                    <div style="font-size: 9pt;">{{ $jabatanAdmin }}</div>
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

        {{-- TIMESTAMP WAKTU CETAK DOKUMEN --}}
        <div style="margin-top: 40px; font-size: 8pt; color: #555; text-align: left; font-style: italic;">
            * Dokumen ini dicetak otomatis oleh sistem pada {{ \Carbon\Carbon::now()->translatedFormat('d F Y H:i:s') }} WIB
        </div>
    </div>

</body>
</html>
