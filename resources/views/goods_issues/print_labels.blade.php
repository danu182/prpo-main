<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Label Keluar - {{ $gi->gi_number }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background-color: #f0f0f0; font-family: 'Arial', sans-serif; color: #000; }
        .preview-container { max-width: 800px; margin: 20px auto; display: flex; flex-wrap: wrap; gap: 15px; justify-content: center; }

        /* 🔥 UKURAN STIKER STANDAR 8x4 cm 🔥 */
        .label-sticker {
            width: 8cm;
            height: 4cm;
            background-color: white;
            border: 1px dashed #adb5bd;
            padding: 8px;
            box-sizing: border-box;
            display: flex;
            flex-direction: row;
            align-items: center;
            page-break-inside: avoid;
            overflow: hidden;
        }

        .label-qr { width: 32%; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .label-qr img { max-width: 100%; height: auto; image-rendering: pixelated; }
        .label-details { width: 68%; padding-left: 10px; display: flex; flex-direction: column; justify-content: center; }

        .item-title { font-size: 11px; font-weight: bold; line-height: 1.2; margin-bottom: 4px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

        @media print {
            body { background-color: white; margin: 0; padding: 0; }
            .d-print-none { display: none !important; }
            .preview-container { margin: 0; max-width: 100%; display: block; }
            .label-sticker { border: none; margin: 0; page-break-after: always; }
        }
    </style>
</head>
<body>

<div class="mt-4 mb-4 text-center d-print-none">
    <button onclick="window.print()" class="px-5 py-2 shadow-lg btn btn-dark rounded-pill fw-bold">
        <i class="bi bi-printer-fill me-2"></i> Mulai Print Label
    </button>
    <button onclick="window.close()" class="px-4 py-2 shadow-sm btn btn-outline-secondary rounded-pill fw-bold ms-2">Tutup</button>
</div>

<div class="preview-container">
    @foreach($labelItems as $giItem)
        @php
            // 1. Ekstrak data dasar dari item yang dikeluarkan
            $masterItem = $giItem->item;
            $qty = (int) $giItem->qty_issued;
            $isFixedAsset = $masterItem->is_asset;
            $labelType = $isFixedAsset ? "FIXED ASSET" : "MINOR ASSET / INVENTORY";

            // 2. Siapkan catatan yang berisi SN/AST
            $catatanSn = $giItem->notes ? $giItem->notes : '-';
            $arrayCatatan = array_map('trim', explode('|', $catatanSn));
        @endphp

        @for ($i = 0; $i < $qty; $i++)
            @php
                // Default value untuk barang minor/umum
                $qrData = $masterItem->code;
                $teksSpesifik = isset($arrayCatatan[$i]) ? $arrayCatatan[$i] : $catatanSn;
                $namaBarangStiker = $masterItem->name;

                // Variabel Data Detail
                $snFisik = $teksSpesifik != '-' ? $teksSpesifik : '-';
                $noAkuntansi = "-";
                $tglTerima = "-";
                $namaPT = "Milik Perusahaan";

                // ========================================================
                // 1. JIKA INI ASET TETAP (Cari Nomor AST yang Tersembunyi)
                // ========================================================
                if($isFixedAsset) {
                    // Cari pola AST/Tahun/Bulan/Nomor dari $teksSpesifik baris ini
                    if(preg_match('/AST\/[0-9]{4}\/[0-9]{2}\/[0-9]{4}/', $teksSpesifik, $matches)) {
                        $nomorAstDitemukan = $matches[0];
                        $qrData = $nomorAstDitemukan; // Ubah QR Code menjadi Nomor AST

                        // Tarik langsung dari Database agar data 100% akurat
                        $aset = \App\Models\FixedAsset::with('company')->where('asset_number', $nomorAstDitemukan)->first();

                        if ($aset) {
                            $namaBarangStiker = $aset->name;
                            $snFisik = $aset->serial_number ?? '-';
                            $noAkuntansi = $aset->accounting_asset_number ?? '-';
                            $tglTerima = $aset->acquisition_date ? \Carbon\Carbon::parse($aset->acquisition_date)->format('d/m/Y') : '-';
                            $namaPT = $aset->company->name ?? 'Milik Perusahaan';
                        }
                    }
                }
                // ========================================================
                // 2. JIKA INI MINOR ASSET / STOK (Lacak via PO Terakhir)
                // ========================================================
                else {
                    $latestPo = \App\Models\PurchaseOrder::with('company')
                                ->whereHas('items', function($q) use ($masterItem) {
                                    $q->where('item_id', $masterItem->id);
                                })
                                ->latest('id')
                                ->first();

                    if ($latestPo && $latestPo->company) {
                        $namaPT = $latestPo->company->name;
                        $tglTerima = $latestPo->po_date ? \Carbon\Carbon::parse($latestPo->po_date)->format('d/m/Y') : '-';
                    }
                }
            @endphp

            {{-- 🔥 HTML STIKER FISIK 🔥 --}}
            <div class="label-sticker">
                <div class="label-qr">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data={{ urlencode($qrData) }}" alt="QR Code">
                    <div style="font-size: 7.5px; margin-top: 4px; font-weight: bold; white-space: nowrap; letter-spacing: 0.5px;">
                        {{ $qrData }}
                    </div>
                </div>
                <div class="label-details">
                    <div style="font-size: 8px; font-weight: 900; border-bottom: 2px solid #000; margin-bottom: 3px;">
                        {{ $labelType }}
                    </div>

                    <div class="item-title">{{ $namaBarangStiker }}</div>

                    <table style="width: 100%; font-size: 8.5px; line-height: 1.1; border: none;">
                        <tr>
                            <td style="width: 28%; font-weight: bold; padding: 0; color: #555;">Kde</td>
                            <td style="padding: 0; font-weight: bold;">: {{ $masterItem->code }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 0; font-weight: bold; color: #555;">Milik</td>
                            <td style="padding: 0; font-weight: bold; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 120px;">
                                : {{ $namaPT }}
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 0; font-weight: bold; color: #555;">SN</td>
                            <td style="padding: 0; font-weight: bold;">: {{ $snFisik }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 0; font-weight: bold; color: #555;">FA No</td>
                            <td style="padding: 0; font-weight: bold;">: {{ $noAkuntansi }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 0; font-weight: bold; color: #555;">Tgl In</td>
                            <td style="padding: 0; font-weight: bold;">: {{ $tglTerima }}</td>
                        </tr>
                    </table>

                    <div style="margin-top: 3px; padding-top: 2px; border-top: 1px dotted #ccc; font-size: 7px; color: #666;">
                        Ref: {{ $gi->gi_number }}
                    </div>
                </div>
            </div>
        @endfor
    @endforeach
</div>

</body>
</html>
