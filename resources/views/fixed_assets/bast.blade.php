<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Berita Acara Serah Terima Aset</title>
    <style>
        @page { margin: 28px 40px 45px 40px; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 9.5pt; line-height: 1.4; color: #111; font-weight: normal; }
        .header { text-align: center; border-bottom: 1.5px solid #000; padding-bottom: 6px; margin-bottom: 12px; }
        .header h2 { margin: 0; font-size: 13.5pt; text-transform: uppercase; text-decoration: underline; letter-spacing: 0.5px; font-weight: bold; }
        .header p { margin: 3px 0 0 0; font-size: 9.5pt; font-weight: normal; }
        .content { margin-top: 8px; }
        .content p { margin: 0 0 8px 0; }
        .table-info { width: 100%; border-collapse: collapse; margin-top: 4px; margin-bottom: 10px; }
        .table-info td { vertical-align: top; padding: 2px 0; font-size: 9.5pt; font-weight: normal; }
        .table-info td:first-child { width: 26%; }
        .table-info td:nth-child(2) { width: 2%; }
        .party-label { font-style: italic; font-size: 9pt; padding-top: 3px !important; color: #333; }
        .table-asset { width: 100%; border-collapse: collapse; margin-top: 8px; margin-bottom: 12px; page-break-inside: auto; }
        .table-asset th, .table-asset td { border: 1px solid #333; padding: 5px 7px; text-align: left; vertical-align: top; font-size: 8.8pt; font-weight: normal; }
        .table-asset th { background-color: #f2f2f2; }
        .table-asset tr { page-break-inside: avoid; page-break-after: auto; }
        .item-spec { font-size: 7.6pt; color: #444; margin-top: 2px; line-height: 1.25; }
        .bapa-ref { font-size: 7.6pt; color: #991b1b; }
        .terms-list { margin: 4px 0 12px 0; padding-left: 18px; font-size: 8.5pt; line-height: 1.35; text-align: justify; }
        .terms-list li { margin-bottom: 2px; }
        .signature-box { width: 100%; margin-top: 14px; table-layout: fixed; page-break-inside: avoid; }
        .signature-box td { text-align: center; vertical-align: top; padding: 4px; font-size: 9pt; font-weight: normal; }
        .signature-space { height: 52px; position: relative; text-align: center; }
        .signature-img { max-height: 48px; max-width: 130px; object-fit: contain; margin-top: 2px; }
        .signature-name { text-decoration: underline; }
        footer { position: fixed; bottom: -28px; left: 0px; right: 0px; height: 24px; border-top: 1px solid #aaa; font-size: 7.5pt; color: #666; padding-top: 6px; font-style: italic; }
    </style>
</head>
<body>

    @php
        $handoverLog = $asset->histories->where('status', 'HANDOVER')->sortByDesc('created_at')->first();
        $tanggalPenyerahan = isset($transaction) ? $transaction->transaction_date : ($handoverLog ? $handoverLog->created_at : $asset->updated_at);
        $nomorDokumen = $documentNumber ?? ('BAST/' . \Carbon\Carbon::parse($tanggalPenyerahan)->format('Y/m/d') . '/' . substr($asset->asset_number, -4));

        // Tangkap Pihak 1 & Pihak 2
        $p1 = $signers[0] ?? null;
        $p2 = $signers[1] ?? null;

        $p1Name  = $p1->name ?? '-';
        $p1Title = $p1->job_title ?? 'Karyawan';
        $p1Dept  = isset($p1->department) ? optional($p1->department)->name : '';

        $p2Name  = $p2->name ?? '-';
        $p2Title = $p2->job_title ?? 'Karyawan';
        $p2Dept  = isset($p2->department) ? optional($p2->department)->name : '';

        $assetItems = (isset($transaction) && $transaction->details->count() > 0)
            ? $transaction->details->map(fn($d) => $d->asset)->filter()
            : collect([$asset]);
    @endphp

    <footer>
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="text-align: left; font-size: 7.5pt; color: #666; font-style: italic; padding: 0;">
                    Dicetak otomatis oleh sistem pada {{ \Carbon\Carbon::now()->translatedFormat('d F Y H:i:s') }} WIB
                </td>
                <td style="text-align: right; font-size: 7.5pt; color: #666; font-style: italic; padding: 0; padding-right: 95px;">
                    Dokumen BAST Aset: {{ $nomorDokumen }} &nbsp;|
                </td>
            </tr>
        </table>
    </footer>

    <div class="header">
        <h2>BERITA ACARA SERAH TERIMA ASET</h2>
        <p>Nomor: {{ $nomorDokumen }}</p>
    </div>

    <div class="content">
        <p>Pada hari ini, tanggal {{ \Carbon\Carbon::parse($tanggalPenyerahan)->translatedFormat('d F Y') }}, telah dilakukan serah terima barang/aset perusahaan dengan rincian pihak sebagai berikut:</p>

        <table class="table-info">
            <tr>
                <td>Nama Penyerah</td>
                <td>:</td>
                <td>{{ $p1Name }}</td>
            </tr>
            <tr>
                <td>Jabatan / Departemen</td>
                <td>:</td>
                <td>{{ $p1Title }}{{ $p1Dept ? ' - ' . $p1Dept : '' }}</td>
            </tr>
            <tr>
                <td colspan="3" class="party-label">Selanjutnya disebut sebagai PIHAK PERTAMA (Yang Menyerahkan).</td>
            </tr>
        </table>

        <table class="table-info">
            <tr>
                <td>Nama Penerima</td>
                <td>:</td>
                <td>{{ $p2Name }}</td>
            </tr>
            <tr>
                <td>Jabatan / Departemen</td>
                <td>:</td>
                <td>{{ $p2Title }}{{ $p2Dept ? ' - ' . $p2Dept : '' }}</td>
            </tr>
            <tr>
                <td>Lokasi / Perusahaan</td>
                <td>:</td>
                <td>{{ optional(optional($p2)->company ?? optional($asset->assignee)->company)->name ?? 'Kantor Pusat' }}</td>
            </tr>
            <tr>
                <td colspan="3" class="party-label">Selanjutnya disebut sebagai PIHAK KEDUA (Yang Menerima).</td>
            </tr>
        </table>

        <p>PIHAK PERTAMA menyerahkan barang/aset perusahaan kepada PIHAK KEDUA, dan PIHAK KEDUA menyatakan telah menerima barang/aset tersebut dalam kondisi BAIK dan berfungsi normal dengan rincian:</p>

        <table class="table-asset">
            <thead>
                <tr>
                    <th width="5%" style="text-align: center;">No</th>
                    <th width="22%">No. Aset / Label</th>
                    <th width="42%">Nama Barang & Spesifikasi</th>
                    <th width="16%">Serial Number</th>
                    <th width="15%">Ref. BAPA</th>
                </tr>
            </thead>
            <tbody>
                @foreach($assetItems as $idx => $itemAsset)
                    @php
                        $rawSpek = $itemAsset->spesifikasi_detail ?: optional($itemAsset->item)->specification;
                        $cleanSpek = trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode((string) $rawSpek))));
                        $masterItemCode = optional($itemAsset->item)->code;
                        $masterItemName = optional($itemAsset->item)->name;

                        $nextBapaTrx = null;
                        if (isset($transaction)) {
                            $nextBapaTrx = \App\Models\FixedAssetTransaction::where('transaction_type', 'RETURN')
                                ->where('id', '>', $transaction->id)
                                ->when($transaction->to_user_id, function ($q) use ($transaction) {
                                    $q->where('from_user_id', $transaction->to_user_id);
                                })
                                ->where(function ($q) use ($itemAsset) {
                                    $q->where('fixed_asset_id', $itemAsset->id)
                                      ->orWhereHas('details', fn($qd) => $qd->where('fixed_asset_id', $itemAsset->id));
                                })
                                ->orderBy('id', 'asc')
                                ->first();
                        }
                    @endphp
                    <tr>
                        <td style="text-align: center;">{{ $idx + 1 }}</td>
                        <td>
                            {{ $itemAsset->asset_number }}
                            @if($itemAsset->accounting_asset_number)
                                <br><small style="color: #111;">Tag: {{ $itemAsset->accounting_asset_number }}</small>
                            @endif
                        </td>
                        <td>
                            {{ $itemAsset->name ?? $masterItemName }}
                            @if($masterItemName && $masterItemName !== $itemAsset->name)
                                <div style="font-size: 7.8pt; color: #111; margin-top: 1px;">
                                    Item: {{ $masterItemCode ? '[' . $masterItemCode . '] ' : '' }}{{ $masterItemName }}
                                </div>
                            @elseif($masterItemCode)
                                <div style="font-size: 7.8pt; color: #111; margin-top: 1px;">
                                    Kode Item: {{ $masterItemCode }}
                                </div>
                            @endif
                            @if(!empty($cleanSpek) && strtolower($cleanSpek) !== 'item dibuat otomatis dari import aset')
                                <div class="item-spec">Spek: {{ \Illuminate\Support\Str::limit($cleanSpek, 120) }}</div>
                            @endif
                        </td>
                        <td>{{ $itemAsset->serial_number ?: 'N/A' }}</td>
                        <td>
                            @if($nextBapaTrx)
                                <div class="bapa-ref">{{ $nextBapaTrx->transaction_number }}</div>
                                <small style="color: #111;">Tgl: {{ \Carbon\Carbon::parse($nextBapaTrx->transaction_date)->format('d/m/Y') }}</small>
                            @else
                                <small style="color: #111;">Belum Retur</small>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p style="margin-bottom: 2px;">Syarat dan Ketentuan:</p>
        <ol class="terms-list">
            <li>PIHAK KEDUA bertanggung jawab penuh atas keutuhan, perawatan, dan keamanan aset tersebut.</li>
            <li>Aset ini adalah milik Perusahaan ({{ optional($asset->company)->name ?? 'Kantor Pusat' }}) dan semata-mata digunakan untuk kepentingan pekerjaan.</li>
            <li>PIHAK KEDUA wajib mengembalikan aset ini kepada Perusahaan apabila terjadi pemutusan hubungan kerja (resign) atau jika diminta kembali oleh Perusahaan sewaktu-waktu.</li>
            <li>Segala bentuk kehilangan atau kerusakan akibat kelalaian PIHAK KEDUA akan menjadi tanggung jawab PIHAK KEDUA sesuai peraturan Perusahaan.</li>
        </ol>

        <p>Demikian Berita Acara Serah Terima ini dibuat dengan sebenarnya untuk dapat dipergunakan sebagaimana mestinya.</p>

        {{-- <table class="signature-box">
            @foreach($signers->chunk(3) as $rowIndex => $signerRow)
            <tr>
                @foreach($signerRow as $colIndex => $signer)
                @php
                    $index = ($rowIndex * 3) + $colIndex;
                    $rawSigPath = $signer->signature_path
                        ?? $signer->signature
                        ?? $signer->digital_signature
                        ?? $signer->ttd
                        ?? null;

                    $sigBase64 = null;

                    if (($signatureMode ?? 'digital') === 'digital' && !empty($rawSigPath)) {
                        if (str_starts_with($rawSigPath, 'data:image')) {
                            $sigBase64 = $rawSigPath;
                        } else {
                            $cleanPath = ltrim(str_replace(['storage/', 'public/'], '', $rawSigPath), '/');
                            foreach ([
                                storage_path('app/public/' . $cleanPath),
                                public_path('storage/' . $cleanPath),
                                public_path(ltrim($rawSigPath, '/')),
                                storage_path('app/' . ltrim($rawSigPath, '/')),
                            ] as $path) {
                                if (file_exists($path) && is_file($path)) {
                                    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION)) ?: 'png';
                                    if ($ext === 'jpg') $ext = 'jpeg';
                                    $sigBase64 = 'data:image/' . $ext . ';base64,' . base64_encode(file_get_contents($path));
                                    break;
                                }
                            }
                        }
                    }
                @endphp
                <td style="width: 33.33%; padding-bottom: 14px;">
                    <div style="min-height: 26px;">
                        {{ $index == 0 ? 'YANG MENYERAHKAN,' : ($index == 1 ? 'YANG MENERIMA,' : 'MENGETAHUI,') }}<br>
                        <span style="font-size: 8pt;">{{ $index == 0 ? 'PIHAK PERTAMA' : ($index == 1 ? 'PIHAK KEDUA' : 'SAKSI') }}</span>
                    </div>
                    <div class="signature-space">
                        @if($sigBase64)
                            <img src="{{ $sigBase64 }}" alt="TTD {{ $signer->name }}" class="signature-img">
                        @endif
                    </div>
                    <div class="signature-name">{{ $signer->name }}</div>
                    <div style="font-size: 8pt;">
                        {{ $signer->job_title ?? 'Karyawan' }}
                        @if(isset($signer->department) && optional($signer->department)->name)
                            - {{ $signer->department->name }}
                        @endif
                    </div>
                </td>
                @endforeach

                @for($empty = $signerRow->count(); $empty < 3; $empty++)
                    <td style="width: 33.33%;"></td>
                @endfor
            </tr>
            @endforeach
        </table> --}}

        <div style="margin-top: 14px; page-break-inside: avoid;">
            @foreach($signers->chunk(3) as $rowIndex => $signerRow)
            <table class="signature-box" style="margin-top: {{ $rowIndex > 0 ? '10px' : '0' }}; width: 100%; table-layout: fixed;">
                <tr>
                    @foreach($signerRow as $colIndex => $signer)
                    @php
                        $index = ($rowIndex * 3) + $colIndex;
                        $rawSigPath = $signer->signature_path
                            ?? $signer->signature
                            ?? $signer->digital_signature
                            ?? $signer->ttd
                            ?? null;

                        $sigBase64 = null;

                        if (($signatureMode ?? 'digital') === 'digital' && !empty($rawSigPath)) {
                            if (str_starts_with($rawSigPath, 'data:image')) {
                                $sigBase64 = $rawSigPath;
                            } else {
                                $cleanPath = ltrim(str_replace(['storage/', 'public/'], '', $rawSigPath), '/');
                                foreach ([
                                    storage_path('app/public/' . $cleanPath),
                                    public_path('storage/' . $cleanPath),
                                    public_path(ltrim($rawSigPath, '/')),
                                    storage_path('app/' . ltrim($rawSigPath, '/')),
                                ] as $path) {
                                    if (file_exists($path) && is_file($path)) {
                                        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION)) ?: 'png';
                                        if ($ext === 'jpg') $ext = 'jpeg';
                                        $sigBase64 = 'data:image/' . $ext . ';base64,' . base64_encode(file_get_contents($path));
                                        break;
                                    }
                                }
                            }
                        }
                    @endphp
                    <td style="width: {{ 100 / $signerRow->count() }}%; text-align: center; vertical-align: top; padding: 4px;">
                        <div style="min-height: 26px;">
                            {{ $index == 0 ? 'YANG MENYERAHKAN,' : ($index == 1 ? 'YANG MENERIMA,' : 'MENGETAHUI,') }}<br>
                            <span style="font-size: 8pt;">{{ $index == 0 ? 'PIHAK PERTAMA' : ($index == 1 ? 'PIHAK KEDUA' : 'SAKSI') }}</span>
                        </div>
                        <div class="signature-space">
                            @if($sigBase64)
                                <img src="{{ $sigBase64 }}" alt="TTD {{ $signer->name }}" class="signature-img">
                            @endif
                        </div>
                        <div class="signature-name">{{ $signer->name }}</div>
                        <div style="font-size: 8pt;">
                            {{ $signer->job_title ?? 'Karyawan' }}
                            @if(isset($signer->department) && optional($signer->department)->name)
                                - {{ $signer->department->name }}
                            @endif
                        </div>
                    </td>
                    @endforeach
                </tr>
            </table>
            @endforeach
        </div>


    </div>

    <script type="text/php">
        if (isset($pdf)) {
            $text  = "Halaman {PAGE_NUM} dari {PAGE_COUNT}";
            $font  = $fontMetrics->get_font("Helvetica", "italic");
            $size  = 7.5;
            $color = array(0.4, 0.4, 0.4);
            $width = $fontMetrics->get_text_width("Halaman 1 dari 1", $font, $size);

            $x = $pdf->get_width() - $width - 40;
            $y = $pdf->get_height() - 29;

            $pdf->page_text($x, $y, $text, $font, $size, $color);
        }
    </script>

</body>
</html>
