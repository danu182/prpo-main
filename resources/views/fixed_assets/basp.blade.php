<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Berita Acara Serah Terima Perbaikan Aset</title>
    <style>
        @page { margin: 28px 40px 45px 40px; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 9.5pt; line-height: 1.4; color: #111; font-weight: normal; }
        .header { text-align: center; border-bottom: 1.5px solid #000; padding-bottom: 6px; margin-bottom: 12px; }
        .header h2 { margin: 0; font-size: 13pt; text-transform: uppercase; text-decoration: underline; letter-spacing: 0.5px; font-weight: bold; }
        .header p { margin: 3px 0 0 0; font-size: 9.5pt; font-weight: normal; }
        .content { margin-top: 8px; }
        .content p { margin: 0 0 8px 0; }
        .table-info { width: 100%; border-collapse: collapse; margin-top: 4px; margin-bottom: 10px; }
        .table-info td { vertical-align: top; padding: 2px 0; font-size: 9.5pt; font-weight: normal; }
        .table-info td:first-child { width: 28%; }
        .table-info td:nth-child(2) { width: 2%; }
        .party-label { font-style: italic; font-size: 9pt; padding-top: 3px !important; color: #333; }
        .table-asset { width: 100%; border-collapse: collapse; margin-top: 8px; margin-bottom: 12px; page-break-inside: auto; }
        .table-asset th, .table-asset td { border: 1px solid #333; padding: 5px 7px; text-align: left; vertical-align: top; font-size: 8.8pt; font-weight: normal; }
        /* .table-asset th { background-color: #fef3c7; } */
        .table-asset tr { page-break-inside: avoid; page-break-after: auto; }
        .item-spec { font-size: 7.6pt; color: #444; margin-top: 2px; line-height: 1.25; }
        .notes-box { border: 1px dashed #333; padding: 7px 10px; background-color: #fafafa; font-size: 9pt; margin-bottom: 10px; }
        .signature-box { width: 100%; margin-top: 16px; table-layout: fixed; page-break-inside: avoid; }
        .signature-box td { text-align: center; vertical-align: top; padding: 4px; font-size: 9pt; font-weight: normal; }
        .signature-space { height: 54px; position: relative; text-align: center; }
        .signature-img { max-height: 48px; max-width: 130px; object-fit: contain; margin-top: 2px; }
        .signature-name { text-decoration: underline; }
        footer { position: fixed; bottom: -25px; left: 0px; right: 0px; height: 20px; border-top: 1px solid #aaa; font-size: 7.5pt; color: #666; padding-top: 4px; font-style: italic; }
    </style>
</head>
<body>

    @php
        $tanggalServis = isset($transaction) ? $transaction->transaction_date : now();
        $nomorDokumen  = $documentNumber ?? ('BASP/' . \Carbon\Carbon::parse($tanggalServis)->format('Y/m/d') . '/' . substr($asset->asset_number, -4));

        // Pihak Pertama: Staff IT / Admin Perusahaan yang menyerahkan unit ke Service Center
        $p1 = $signers[0] ?? auth()->user();
        $p1Name  = $p1->name ?? '-';
        $p1Title = $p1->job_title ?? 'IT / GA';
        $p1Dept  = isset($p1->department) ? optional($p1->department)->name : '';

        // Parse catatan terstruktur Service Center | No. RMA | Keluhan
        $rawNotes     = isset($transaction) ? ($transaction->notes ?? '') : ($asset->notes ?? '');
        $serviceCenter = '-';
        $rmaNumber     = '-';
        $keluhanText   = $rawNotes ?: '-';

        if (str_contains($rawNotes, 'Service Center:')) {
            $parts = explode('|', $rawNotes);
            foreach ($parts as $part) {
                $part = trim($part);
                if (str_starts_with($part, 'Service Center:')) {
                    $serviceCenter = trim(str_replace('Service Center:', '', $part));
                } elseif (str_starts_with($part, 'No. RMA/Tiket:')) {
                    $rmaNumber = trim(str_replace('No. RMA/Tiket:', '', $part));
                } elseif (str_starts_with($part, 'Keluhan/Keterangan:')) {
                    $keluhanText = trim(str_replace('Keluhan/Keterangan:', '', $part));
                }
            }
        }

        $assetItems = (isset($transaction) && $transaction->details->count() > 0)
            ? $transaction->details->map(fn($d) => $d->asset)->filter()
            : collect([$asset]);

        // Tanda tangan digital Pihak Pertama
        $rawSigPath = $p1->signature_path ?? $p1->signature ?? $p1->digital_signature ?? $p1->ttd ?? null;
        $p1SigBase64 = null;
        if (($signatureMode ?? 'digital') === 'digital' && !empty($rawSigPath)) {
            if (str_starts_with($rawSigPath, 'data:image')) {
                $p1SigBase64 = $rawSigPath;
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
                        $p1SigBase64 = 'data:image/' . $ext . ';base64,' . base64_encode(file_get_contents($path));
                        break;
                    }
                }
            }
        }
    @endphp

    <footer>
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="text-align: left; font-size: 7.5pt; color: #666; font-style: italic; padding: 0;">
                    Dicetak otomatis oleh sistem pada {{ \Carbon\Carbon::now()->translatedFormat('d F Y H:i:s') }} WIB
                </td>
                <td style="text-align: right; font-size: 7.5pt; color: #666; font-style: italic; padding: 0; padding-right: 95px;">
                    Dokumen BASP Aset: {{ $nomorDokumen }} &nbsp;|
                </td>
            </tr>
        </table>
    </footer>

    <div class="header">
        <h2>BERITA ACARA SERAH TERIMA PERBAIKAN ASET</h2>
        <p>Nomor: {{ $nomorDokumen }}</p>
    </div>

    <div class="content">
        <p>Pada hari ini, tanggal {{ \Carbon\Carbon::parse($tanggalServis)->translatedFormat('d F Y') }}, telah dilakukan serah terima unit aset perusahaan untuk keperluan perbaikan / servis dengan rincian pihak sebagai berikut:</p>

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
                <td>Perusahaan Pemilik</td>
                <td>:</td>
                <td>{{ optional($asset->company)->name ?? 'Kantor Pusat' }}</td>
            </tr>
            <tr>
                <td colspan="3" class="party-label">Selanjutnya disebut sebagai PIHAK PERTAMA (Yang Menyerahkan untuk Perbaikan).</td>
            </tr>
        </table>

        <table class="table-info">
            <tr>
                <td>Tujuan Service Center / Vendor</td>
                <td>:</td>
                <td>{{ $serviceCenter }}</td>
            </tr>
            <tr>
                <td>No. RMA / Tanda Terima Servis</td>
                <td>:</td>
                <td>{{ $rmaNumber }}</td>
            </tr>
            <tr>
                <td colspan="3" class="party-label">Selanjutnya disebut sebagai PIHAK KEDUA (Penerima / Pelaksana Perbaikan).</td>
            </tr>
        </table>

        <p>PIHAK PERTAMA menyerahkan unit aset tersebut kepada PIHAK KEDUA untuk dilakukan pemeriksaan dan perbaikan dengan rincian sebagai berikut:</p>

        <table class="table-asset">
            <thead>
                <tr>
                    <th width="5%" style="text-align: center;">No</th>
                    <th width="22%">No. Aset / Label</th>
                    <th width="42%">Nama Barang & Spesifikasi</th>
                    <th width="16%">Serial Number</th>
                    <th width="15%">Pemegang Asal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($assetItems as $idx => $itemAsset)
                    @php
                        $rawSpek = $itemAsset->spesifikasi_detail ?: optional($itemAsset->item)->specification;
                        $cleanSpek = trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode((string) $rawSpek))));
                        $masterItemCode = optional($itemAsset->item)->code;
                        $masterItemName = optional($itemAsset->item)->name;

                        $detailRow = isset($transaction) ? $transaction->details->where('fixed_asset_id', $itemAsset->id)->first() : null;
                        $prevUserName = ($detailRow && $detailRow->previous_user_id)
                            ? (optional(\App\Models\User::find($detailRow->previous_user_id))->name ?? '-')
                            : 'Stok Gudang';

                        // Cari nomor dokumen transaksi asal (BAST jika dari pemegang, atau BAPA terakhir jika dari gudang)
                        $originTrx = null;
                        if (isset($transaction)) {
                            $targetTypes = ($detailRow && $detailRow->previous_user_id)
                                ? ['HANDOVER']
                                : ['RETURN', 'HANDOVER'];

                            $originTrx = \App\Models\FixedAssetTransaction::whereIn('transaction_type', $targetTypes)
                                ->where('id', '<', $transaction->id)
                                ->where(function ($q) use ($itemAsset) {
                                    $q->where('fixed_asset_id', $itemAsset->id)
                                      ->orWhereHas('details', fn($qd) => $qd->where('fixed_asset_id', $itemAsset->id));
                                })
                                ->orderBy('id', 'desc')
                                ->first();
                        }
                    @endphp
                    <tr>
                        <td style="text-align: center;">{{ $idx + 1 }}</td>
                        <td>
                            {{ $itemAsset->asset_number }}
                            @if($itemAsset->accounting_asset_number)
                                <br><small style="color: #555;">Tag: {{ $itemAsset->accounting_asset_number }}</small>
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
                            {{ $prevUserName }}
                            @if($originTrx)
                                <div style="font-size: 7.5pt; color: #333; margin-top: 2px;">
                                    Ref: {{ $originTrx->transaction_number }}
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p style="margin-bottom: 3px;">Keluhan Kerusakan & Keterangan Perbaikan:</p>
        <div class="notes-box">
            {{ $keluhanText }}
        </div>

        <p>Demikian Berita Acara Serah Terima Perbaikan ini dibuat sebagai bukti keluarnya aset perusahaan untuk proses servis hingga unit dikembalikan kembali ke Perusahaan.</p>

        @php
            // Susun daftar slot tanda tangan BASP:
            // Index 0 = Pihak Pertama (Staf Perusahaan)
            // Index 1 = Pihak Kedua (Service Center / Kurir -> null agar kolom kosong TTD basah & stempel)
            // Index 2+ = Saksi / Mengetahui (dari sisa pilihan user di modal)
            $baspSlots = collect();
            $p1Signer = isset($signers) && $signers->count() > 0 ? $signers[0] : null;
            $baspSlots->push($p1Signer);
            $baspSlots->push(null);

            if (isset($signers) && $signers->count() > 1) {
                foreach ($signers->slice(1) as $witness) {
                    $baspSlots->push($witness);
                }
            }
        @endphp

        {{-- <table class="signature-box">
            @foreach($baspSlots->chunk(3) as $rowIndex => $slotRow)
            <tr>
                @foreach($slotRow as $colIndex => $signer)
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
                    }
                @endphp

                <td style="width: 33.33%; padding-bottom: 14px;">
                    @if($globalIndex === 0)
                        <div style="min-height: 26px;">
                            YANG MENYERAHKAN,<br>
                            <span style="font-size: 8pt;">PIHAK PERTAMA</span>
                        </div>
                        <div class="signature-space">
                            @if($sigBase64)
                                <img src="{{ $sigBase64 }}" alt="TTD {{ optional($signer)->name }}" class="signature-img">
                            @endif
                        </div>
                        <div class="signature-name">{{ optional($signer)->name ?? '-' }}</div>
                        <div style="font-size: 8pt;">
                            {{ optional($signer)->job_title ?? 'IT / GA' }}
                            @if($signer && isset($signer->department) && optional($signer->department)->name)
                                - {{ $signer->department->name }}
                            @endif
                        </div>
                    @elseif($globalIndex === 1)
                        <div style="min-height: 26px;">
                            YANG MENERIMA,<br>
                            <span style="font-size: 8pt;">PIHAK KEDUA (SERVICE CENTER)</span>
                        </div>
                        <div class="signature-space"></div>
                        <div>( .................................... )</div>
                        <div style="font-size: 8pt; color: #333;">{{ $serviceCenterName ?? 'Vendor / Kurir Servis' }}</div>
                    @else
                        <div style="min-height: 26px;">
                            MENGETAHUI,<br>
                            <span style="font-size: 8pt;">SAKSI</span>
                        </div>
                        <div class="signature-space">
                            @if($sigBase64)
                                <img src="{{ $sigBase64 }}" alt="TTD {{ optional($signer)->name }}" class="signature-img">
                            @endif
                        </div>
                        <div class="signature-name">{{ optional($signer)->name ?? '-' }}</div>
                        <div style="font-size: 8pt;">
                            {{ optional($signer)->job_title ?? 'Karyawan' }}
                            @if($signer && isset($signer->department) && optional($signer->department)->name)
                                - {{ $signer->department->name }}
                            @endif
                        </div>
                    @endif
                </td>
                @endforeach


                @for($empty = $slotRow->count(); $empty < 3; $empty++)
                    <td style="width: 33.33%;"></td>
                @endfor
            </tr>
            @endforeach
        </table> --}}


        <div style="margin-top: 14px; page-break-inside: avoid;">
            @foreach($baspSlots->chunk(3) as $rowIndex => $slotRow)
            <table class="signature-box" style="margin-top: {{ $rowIndex > 0 ? '10px' : '0' }}; width: 100%; table-layout: fixed;">
                <tr>
                    @foreach($slotRow as $colIndex => $signer)
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
                        }
                    @endphp

                    <td style="width: {{ 100 / $slotRow->count() }}%; text-align: center; vertical-align: top; padding: 4px;">
                        @if($globalIndex === 0)
                            <div style="min-height: 26px;">
                                YANG MENYERAHKAN,<br>
                                <span style="font-size: 8pt;">PIHAK PERTAMA</span>
                            </div>
                            <div class="signature-space">
                                @if($sigBase64)
                                    <img src="{{ $sigBase64 }}" alt="TTD {{ optional($signer)->name }}" class="signature-img">
                                @endif
                            </div>
                            <div class="signature-name">{{ optional($signer)->name ?? '-' }}</div>
                            <div style="font-size: 8pt;">
                                {{ optional($signer)->job_title ?? 'IT / GA' }}
                                @if($signer && isset($signer->department) && optional($signer->department)->name)
                                    - {{ $signer->department->name }}
                                @endif
                            </div>
                        @elseif($globalIndex === 1)
                            <div style="min-height: 26px;">
                                YANG MENERIMA,<br>
                                <span style="font-size: 8pt;">PIHAK KEDUA (SERVICE CENTER)</span>
                            </div>
                            <div class="signature-space"></div>
                            <div>( .................................... )</div>
                            <div style="font-size: 8pt; color: #333;">{{ $serviceCenterName ?? 'Vendor / Kurir Servis' }}</div>
                        @else
                            <div style="min-height: 26px;">
                                MENGETAHUI,<br>
                                <span style="font-size: 8pt;">SAKSI</span>
                            </div>
                            <div class="signature-space">
                                @if($sigBase64)
                                    <img src="{{ $sigBase64 }}" alt="TTD {{ optional($signer)->name }}" class="signature-img">
                                @endif
                            </div>
                            <div class="signature-name">{{ optional($signer)->name ?? '-' }}</div>
                            <div style="font-size: 8pt;">
                                {{ optional($signer)->job_title ?? 'Karyawan' }}
                                @if($signer && isset($signer->department) && optional($signer->department)->name)
                                    - {{ $signer->department->name }}
                                @endif
                            </div>
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
