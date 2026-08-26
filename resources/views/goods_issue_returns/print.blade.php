<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Bukti Retur Barang - {{ $return->return_number }}</title>
    <style>
        /* Pengaturan Margin Kertas & Footer */
        @page { margin: 40px 50px 70px 50px; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 10.5pt; color: #000; line-height: 1.4; }

        .header { text-align: center; margin-bottom: 20px; border-bottom: 3px double #000; padding-bottom: 10px; }
        .title { font-size: 16pt; font-weight: bold; margin: 0; letter-spacing: 1px; text-transform: uppercase; }
        .subtitle { font-size: 10pt; color: #333; margin-top: 5px; }

        .info-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; font-size: 10pt; }
        .info-table td { padding: 4px 0; vertical-align: top; }
        .info-label { font-weight: bold; width: 18%; }
        .info-colon { width: 2%; }

        .item-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .item-table th, .item-table td { border: 1px solid #000; padding: 6px 8px; font-size: 9.5pt; vertical-align: top; }
        .item-table th { background-color: #f0f0f0; font-weight: bold; text-align: center; text-transform: uppercase; }
        .item-table td.center { text-align: center; }

        .signature-table { width: 100%; margin-top: 40px; text-align: center; border-collapse: collapse; page-break-inside: avoid; }
        .signature-table td { width: 50%; padding: 10px; vertical-align: bottom; }
        .signature-space { height: 70px; }
        .signature-name { font-weight: bold; text-decoration: underline; text-transform: uppercase; }
        .signature-title { font-size: 9pt; color: #555; }

        footer {
            position: fixed; bottom: -40px; left: 0px; right: 0px; height: 30px;
            border-top: 1px solid #888; text-align: right; font-size: 8pt; color: #555;
            padding-top: 5px; font-style: italic;
        }
        .pagenum:before { content: "Halaman " counter(page); }
    </style>
</head>
<body>

    <footer>
        Dokumen Retur: {{ $return->return_number }} &nbsp; | &nbsp; Dicetak: {{ date('d-m-Y H:i') }} &nbsp; | &nbsp; <span class="pagenum"></span>
    </footer>

    <div class="header">
        <h1 class="title">BUKTI PENGEMBALIAN BARANG (RETUR)</h1>
        <p class="subtitle">Dokumen Resmi Pengembalian Inventaris / Aset ke Gudang Perusahaan</p>
    </div>

    <table class="info-table">
        <tr>
            <td class="info-label">No. Dokumen Retur</td>
            <td class="info-colon">:</td>
            <td style="font-weight: bold; width: 30%;">{{ $return->return_number }}</td>
            <td class="info-label">Gudang Penerima</td>
            <td class="info-colon">:</td>
            <td style="font-weight: bold; color: #198754;">{{ optional($return->warehouse)->name ?? 'Gudang Utama' }}</td>
        </tr>
        <tr>
            <td class="info-label">Ref. Dok. Keluar (GI)</td>
            <td class="info-colon">:</td>
            <td>{{ optional($return->goodsIssue)->gi_number }}</td>
            <td class="info-label">Dikembalikan Oleh</td>
            <td class="info-colon">:</td>
            <td style="font-weight: bold;">{{ $return->returned_by_name }}</td>
        </tr>
        <tr>
            <td class="info-label">Tanggal Retur</td>
            <td class="info-colon">:</td>
            <td>{{ \Carbon\Carbon::parse($return->return_date)->translatedFormat('d F Y') }}</td>
            <td class="info-label">Catatan</td>
            <td class="info-colon">:</td>
            <td style="font-style: italic;">{{ $return->notes ?? '- Tidak ada catatan -' }}</td>
        </tr>
    </table>

    <table class="item-table">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="15%">Kode Barang</th>
                <th width="35%">Nama Barang</th>
                <th width="15%">Qty Retur</th>
                <th width="30%">Catatan / Serial Number</th>
            </tr>
        </thead>
        <tbody>
            @foreach($return->items as $index => $item)
            @php
                // 🔥 DEFINISIKAN VARIABEL NAMA BARANG 🔥
                $masterName = optional($item->item)->name ?? '-';
                $specificName = $item->item_name ?? optional($item->goodsIssueItem)->item_name ?? $masterName;

                // 🔥 SIHIR PEMISAH SATUAN DARI CATATAN 🔥
                $uomName = 'PCS'; // Default
                $cleanNotes = [];

                if($item->notes) {
                    $notesArray = explode(' | ', $item->notes);
                    foreach($notesArray as $noteLine) {
                        if (\Illuminate\Support\Str::startsWith(trim($noteLine), 'Satuan:')) {
                            $uomName = trim(str_replace('Satuan:', '', $noteLine));
                        } elseif (!empty(trim($noteLine))) {
                            $cleanNotes[] = trim($noteLine);
                        }
                    }
                }

                // =========================================================================
                // 🔥 TRIK INTELIJEN: TARIK SN/ASET LANGSUNG DARI BUKU SEJARAH DATABASE 🔥
                // =========================================================================

                // 1. Tarik Data Aset Tetap
                $returnedAssets = \Illuminate\Support\Facades\DB::table('fixed_asset_histories')
                    ->join('fixed_assets', 'fixed_asset_histories.fixed_asset_id', '=', 'fixed_assets.id')
                    ->where('fixed_asset_histories.notes', 'like', "%{$return->return_number}%")
                    ->where('fixed_assets.item_id', $item->item_id)
                    ->pluck('fixed_assets.asset_number')
                    ->toArray();

                // 🔥 KUNCI PERBAIKAN: JIKA ADA NO ASET, BARU DIA SAH JADI ASET TETAP 🔥
                $isAsset = count($returnedAssets) > 0;

                if ($isAsset) {
                    $cleanNotes[] = "No. Aset: " . implode(', ', $returnedAssets);
                }

                // 2. Tarik Data Serial Number (Minor / Stok Biasa)
                $snHistory = \Illuminate\Support\Facades\DB::table('employee_inventory_histories')
                    ->where('reference_number', $return->return_number)
                    ->where('item_id', $item->item_id)
                    ->first();

                if ($snHistory && preg_match('/SN:\s*(.*)$/', $snHistory->notes, $matches)) {
                    $cleanNotes[] = "SN: " . trim($matches[1]);
                }
            @endphp
            <tr>
                <td class="center" style="text-align: center;">{{ $index + 1 }}</td>
                <td class="center" style="text-align: center;">{{ optional($item->item)->code ?? '-' }}</td>
                <td>
                    <strong style="text-transform: uppercase;">{{ $specificName }}</strong><br>

                    @if(strtolower(trim($specificName)) !== strtolower(trim($masterName)))
                        <span style="font-size: 7.5pt; color: #555;">(Master: {{ $masterName }})</span><br>
                    @endif

                    {{-- TAMPILKAN LABEL HANYA JIKA BENAR-BENAR ASET --}}
                    @if($isAsset)
                        <span style="font-size: 7.5pt; font-weight: bold; color: #000;">[ASET TETAP]</span>
                    @endif
                </td>
                <td class="center">
                    <strong style="font-size: 11pt;">{{ (float) $item->qty_returned }}</strong><br>
                    <span style="font-size: 8pt; color: #555; text-transform: uppercase;">{{ $uomName }}</span>
                </td>
                <td style="font-size: 8.5pt;">
                    @if(count($cleanNotes) > 0)
                        <ul style="margin: 0; padding-left: 15px;">
                            @foreach($cleanNotes as $noteLine)
                                <li style="margin-bottom: 3px;">{{ $noteLine }}</li>
                            @endforeach
                        </ul>
                    @else
                        -
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div style="font-size: 9.5pt; margin-top: 15px; border: 1px dashed #777; padding: 10px; background-color: #fdfdfd; text-align: justify;">
        <strong>Pernyataan:</strong><br>
        Barang/Aset di atas telah diserahkan kembali oleh Karyawan bersangkutan dan diterima oleh pihak Gudang dalam jumlah yang sesuai untuk dimasukkan kembali ke dalam sistem persediaan Perusahaan.
    </div>

    <table class="signature-table">
        <tr>
            <td>
                Yang Mengembalikan,<br>
                <span class="signature-title">(Karyawan / User)</span>
                <div class="signature-space"></div>
                <div class="signature-name">{{ $return->returned_by_name }}</div>
            </td>
            <td>
                Yang Menerima,<br>
                <span class="signature-title">(Admin Gudang)</span>
                <div class="signature-space"></div>
                <div class="signature-name">{{ optional($return->receiver)->name ?? 'Admin Gudang' }}</div>
            </td>
        </tr>
    </table>

</body>
</html>
