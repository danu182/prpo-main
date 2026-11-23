<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Berita Acara Penghapusan Aset</title>
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
        .table-asset { width: 100%; border-collapse: collapse; margin-top: 8px; margin-bottom: 12px; page-break-inside: auto; }
        .table-asset th, .table-asset td { border: 1px solid #333; padding: 5px 7px; text-align: left; vertical-align: top; font-size: 8.6pt; font-weight: normal; }
        .table-asset th { background-color: #f3f4f6; }
        .table-asset tr { page-break-inside: avoid; page-break-after: auto; }
        .item-spec { font-size: 7.5pt; color: #444; margin-top: 2px; line-height: 1.25; }
        .notes-box { border: 1px dashed #333; padding: 7px 10px; background-color: #fafafa; font-size: 9pt; margin-bottom: 10px; }
        .signature-box { width: 100%; margin-top: 16px; table-layout: fixed; page-break-inside: avoid; }
        .signature-box td { text-align: center; vertical-align: top; padding: 4px; font-size: 8.8pt; font-weight: normal; }
        .signature-space { height: 52px; position: relative; text-align: center; }
        .signature-img { max-height: 48px; max-width: 125px; object-fit: contain; margin-top: 2px; }
        .signature-name { text-decoration: underline; }
        footer { position: fixed; bottom: -28px; left: 0px; right: 0px; height: 24px; border-top: 1px solid #aaa; font-size: 7.5pt; color: #666; padding-top: 6px; font-style: italic; }
    </style>
</head>
<body>

    @php
        $tanggalDisposal = isset($transaction) ? $transaction->transaction_date : ($asset->updated_at ?? now());
        $nomorDokumen    = $documentNumber ?? ('BAPP/' . \Carbon\Carbon::parse($tanggalDisposal)->format('Y/m/d') . '/' . substr($asset->asset_number, -4));

        $p1 = isset($signers) && $signers->count() > 0 ? $signers[0] : auth()->user();
        $p1Name  = $p1->name ?? '-';
        $p1Title = $p1->job_title ?? 'IT / GA';
        $p1Dept  = isset($p1->department) ? optional($p1->department)->name : '';

        $assetItems = (isset($transaction) && $transaction->details->count() > 0)
            ? $transaction->details->map(fn($d) => $d->asset)->filter()
            : collect([$asset]);

        $alasanDisposal = isset($transaction) && !empty($transaction->notes)
            ? $transaction->notes
            : ($asset->notes ?? 'Aset rusak berat / sudah tidak layak digunakan.');

        // Pastikan minimal ada 3 slot tanda tangan (Pengaju, Pemeriksa Finance/GA, Menyetujui)
        $signerSlots = collect();
        $sourceSigners = isset($signers) ? $signers->values() : collect([$p1]);
        $totalSlots = max(3, $sourceSigners->count());

        for ($i = 0; $i < $totalSlots; $i++) {
            $signerSlots->push($sourceSigners->get($i));
        }
    @endphp

    <footer>
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="text-align: left; font-size: 7.5pt; color: #666; font-style: italic; padding: 0;">
                    Dicetak otomatis oleh sistem pada {{ \Carbon\Carbon::now()->translatedFormat('d F Y H:i:s') }} WIB
                </td>
                <td style="text-align: right; font-size: 7.5pt; color: #666; font-style: italic; padding: 0; padding-right: 95px;">
                    Dokumen BAPP Aset: {{ $nomorDokumen }} &nbsp;|
                </td>
            </tr>
        </table>
    </footer>

    <div class="header">
        <h2>BERITA ACARA PENGHAPUSAN ASET (DISPOSAL)</h2>
        <p>Nomor: {{ $nomorDokumen }}</p>
    </div>

    <div class="content">
        <p>Pada hari ini, tanggal {{ \Carbon\Carbon::parse($tanggalDisposal)->translatedFormat('d F Y') }}, telah dilakukan pemeriksaan dan penghapusan (disposal) terhadap aset tetap milik Perusahaan dengan rincian sebagai berikut:</p>

        <table class="table-info">
            <tr>
                <td>Diajukan Oleh</td>
                <td>:</td>
                <td>{{ $p1Name }} ({{ $p1Title }}{{ $p1Dept ? ' - ' . $p1Dept : '' }})</td>
            </tr>
            <tr>
                <td>Perusahaan Pemilik</td>
                <td>:</td>
                <td>{{ optional($asset->company)->name ?? 'Kantor Pusat' }}</td>
            </tr>
            <tr>
                <td>Status Akhir Aset</td>
                <td>:</td>
                <td>Disposed (Dihapuskan dari Inventaris Aktif)</td>
            </tr>
        </table>

        <table class="table-asset">
            <thead>
                <tr>
                    <th width="5%" style="text-align: center;">No</th>
                    <th width="20%">No. Aset / Label</th>
                    <th width="35%">Nama Barang & Spesifikasi</th>
                    <th width="14%">Tgl Perolehan</th>
                    <th width="13%" style="text-align: right;">Harga Perolehan</th>
                    <th width="13%" style="text-align: right;">Nilai Buku Akhir</th>
                </tr>
            </thead>
            <tbody>
                @foreach($assetItems as $idx => $itemAsset)
                    @php
                        $rawSpek = $itemAsset->spesifikasi_detail ?: optional($itemAsset->item)->specification;
                        $cleanSpek = trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode((string) $rawSpek))));
                        $masterItemCode = optional($itemAsset->item)->code;
                        $masterItemName = optional($itemAsset->item)->name;

                        $currCode = strtoupper(optional($itemAsset->currency)->code ?? 'IDR');
                        $currSym  = optional($itemAsset->currency)->symbol ?? ($currCode === 'IDR' ? 'Rp' : $currCode);
                        $currDec  = ($currCode === 'IDR') ? 0 : 2;
                    @endphp
                    <tr>
                        <td style="text-align: center;">{{ $idx + 1 }}</td>
                        <td>
                            {{ $itemAsset->asset_number }}
                            @if($itemAsset->accounting_asset_number)
                                <br><small style="color: #111;">Tag: {{ $itemAsset->accounting_asset_number }}</small>
                            @endif
                            @if($itemAsset->serial_number)
                                <br><small style="color: #444;">S/N: {{ $itemAsset->serial_number }}</small>
                            @endif
                        </td>
                        <td>
                            {{ $itemAsset->name ?? $masterItemName }}
                            @if($masterItemCode)
                                <div style="font-size: 7.6pt; color: #111; margin-top: 1px;">
                                    Item: [{{ $masterItemCode }}] {{ $masterItemName }}
                                </div>
                            @endif
                            @if(!empty($cleanSpek) && strtolower($cleanSpek) !== 'item dibuat otomatis dari import aset')
                                <div class="item-spec">Spek: {{ \Illuminate\Support\Str::limit($cleanSpek, 100) }}</div>
                            @endif
                        </td>
                        <td>{{ $itemAsset->acquisition_date ? \Carbon\Carbon::parse($itemAsset->acquisition_date)->format('d/m/Y') : '-' }}</td>
                        <td style="text-align: right;">{{ $currSym }} {{ number_format($itemAsset->purchase_price ?? 0, $currDec, ',', '.') }}</td>
                        <td style="text-align: right;">{{ $currSym }} {{ number_format($itemAsset->net_book_value ?? $itemAsset->purchase_price ?? 0, $currDec, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p style="margin-bottom: 3px;">Alasan & Keterangan Penghapusan Aset:</p>
        <div class="notes-box">
            {{ $alasanDisposal }}
        </div>

        <p>Demikian Berita Acara Penghapusan Aset ini dibuat dengan sebenarnya sebagai dasar penghapusan fisik barang maupun pencatatan pembukuan aset Perusahaan.</p>

        {{-- <table class="signature-box">
            @foreach($signerSlots->chunk(3) as $rowIndex => $signerRow)
            <tr>
                @foreach($signerRow as $colIndex => $signer)
                @php
                    $globalIndex = ($rowIndex * 3) + $colIndex;
                    $sigBase64 = null;

                    if ($signer && ($signatureMode ?? 'digital') === 'digital') {
                        $rawSigPath = $signer->signature_path
                            ?? $signer->signature
                            ?? $signer->digital_signature
                            ?? $signer->ttd
                            ?? null;

                        if (!empty($rawSigPath)) {
                            if (str_starts_with($rawSigPath, 'data:image')) {
                                $sigBase64 = $rawSigPath;
                            } else {
                                $cleanPath = ltrim(str_replace(['storage/', 'public/'], '', $rawSigPath), '/');
                                foreach ([
                                    storage_path('app/public/' . $cleanPath),
                                    public_path('storage/' . $cleanPath),
                                    public_path(ltrim($rawSigPath, '/')),
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
                    }

                    $roleTitle = match ($globalIndex) {
                        0 => 'YANG MENGAJUKAN,',
                        1 => 'DIPERIKSA (FINANCE / GA),',
                        2 => 'MENYETUJUI,',
                        default => 'MENGETAHUI,',
                    };

                    $defaultJabatan = match ($globalIndex) {
                        0 => 'IT / GA',
                        1 => 'Finance / Accounting',
                        2 => 'Manajemen / Pimpinan',
                        default => 'Saksi',
                    };
                @endphp
                <td style="width: 33.33%; padding-bottom: 14px;">
                    <div style="min-height: 24px;">{{ $roleTitle }}</div>
                    <div class="signature-space">
                        @if($sigBase64)
                            <img src="{{ $sigBase64 }}" alt="TTD {{ $signer->name }}" class="signature-img">
                        @endif
                    </div>
                    @if($signer)
                        <div class="signature-name">{{ $signer->name }}</div>
                        <div style="font-size: 7.8pt;">
                            {{ $signer->job_title ?? 'Karyawan' }}
                            @if(isset($signer->department) && optional($signer->department)->name)
                                - {{ $signer->department->name }}
                            @endif
                        </div>
                    @else
                        <div>( .................................... )</div>
                        <div style="font-size: 7.8pt; color: #444;">{{ $defaultJabatan }}</div>
                    @endif
                </td>
                @endforeach


                @for($empty = $signerRow->count(); $empty < 3; $empty++)
                    <td style="width: 33.33%;"></td>
                @endfor
            </tr>
            @endforeach
        </table> --}}


        <div style="margin-top: 16px; page-break-inside: avoid;">
            @foreach($signerSlots->chunk(3) as $rowIndex => $signerRow)
            <table class="signature-box" style="margin-top: {{ $rowIndex > 0 ? '10px' : '0' }}; width: 100%; table-layout: fixed;">
                <tr>
                    @foreach($signerRow as $colIndex => $signer)
                    @php
                        $globalIndex = ($rowIndex * 3) + $colIndex;
                        $sigBase64 = null;

                        if ($signer && ($signatureMode ?? 'digital') === 'digital') {
                            $rawSigPath = $signer->signature_path
                                ?? $signer->signature
                                ?? $signer->digital_signature
                                ?? $signer->ttd
                                ?? null;

                            if (!empty($rawSigPath)) {
                                if (str_starts_with($rawSigPath, 'data:image')) {
                                    $sigBase64 = $rawSigPath;
                                } else {
                                    $cleanPath = ltrim(str_replace(['storage/', 'public/'], '', $rawSigPath), '/');
                                    foreach ([
                                        storage_path('app/public/' . $cleanPath),
                                        public_path('storage/' . $cleanPath),
                                        public_path(ltrim($rawSigPath, '/')),
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
                        }

                        $roleTitle = match ($globalIndex) {
                            0 => 'YANG MENGAJUKAN,',
                            1 => 'DIPERIKSA (FINANCE / GA),',
                            2 => 'MENYETUJUI,',
                            default => 'MENGETAHUI,',
                        };

                        $defaultJabatan = match ($globalIndex) {
                            0 => 'IT / GA',
                            1 => 'Finance / Accounting',
                            2 => 'Manajemen / Pimpinan',
                            default => 'Saksi',
                        };
                    @endphp
                    <td style="width: {{ 100 / $signerRow->count() }}%; text-align: center; vertical-align: top; padding: 4px;">
                        <div style="min-height: 24px;">{{ $roleTitle }}</div>
                        <div class="signature-space">
                            @if($sigBase64)
                                <img src="{{ $sigBase64 }}" alt="TTD {{ $signer->name }}" class="signature-img">
                            @endif
                        </div>
                        @if($signer)
                            <div class="signature-name">{{ $signer->name }}</div>
                            <div style="font-size: 7.8pt;">
                                {{ $signer->job_title ?? 'Karyawan' }}
                                @if(isset($signer->department) && optional($signer->department)->name)
                                    - {{ $signer->department->name }}
                                @endif
                            </div>
                        @else
                            <div>( .................................... )</div>
                            <div style="font-size: 7.8pt; color: #444;">{{ $defaultJabatan }}</div>
                        @endif
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
