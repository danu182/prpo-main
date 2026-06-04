<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Label - {{ $gr->gr_number }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background-color: #f0f0f0; font-family: 'Arial', sans-serif; color: #000; }

        /* Kontainer untuk tampilan layar (preview) */
        .preview-container { max-width: 800px; margin: 20px auto; display: flex; flex-wrap: wrap; gap: 15px; justify-content: center; }

        /* 🔥 STYLING UKURAN STIKER STANDAR (8x4 cm) 🔥 */
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

        .label-qr {
            width: 32%;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .label-qr img {
            max-width: 100%;
            height: auto;
            image-rendering: pixelated; /* Agar barcode tajam saat diprint */
        }

        .label-details {
            width: 68%;
            padding-left: 10px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .item-title {
            font-size: 11px;
            font-weight: bold;
            line-height: 1.2;
            margin-bottom: 4px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Class khusus saat tombol PRINT ditekan */
        @media print {
            body { background-color: white; margin: 0; padding: 0; }
            .d-print-none { display: none !important; }
            .preview-container { margin: 0; max-width: 100%; display: block; }

            .label-sticker {
                border: none; /* Hilangkan garis putus-putus preview saat diprint */
                margin: 0;
                page-break-after: always; /* MANTRA SAKTI: 1 Stiker = 1 Halaman / Potongan Kertas Thermal */
            }
        }
    </style>
</head>
<body>

<div class="mt-4 mb-4 text-center d-print-none">
    <div class="shadow-sm alert alert-info d-inline-block text-start" style="max-width: 600px;">
        <strong><i class="bi bi-info-circle-fill me-1"></i> Mode Cetak Printer Stiker (Thermal)</strong><br>
        <span class="small">Sistem otomatis membuat jumlah stiker sesuai <strong>Qty Kedatangan</strong>. Jika print menggunakan printer thermal, pastikan pengaturan ukuran kertas pada driver printer sudah disesuaikan (Misal: 80mm x 40mm).</span>
    </div>
    <br>
    <button onclick="window.print()" class="px-5 py-2 shadow-lg btn btn-dark rounded-pill fw-bold">
        <i class="bi bi-printer-fill me-2"></i> Mulai Print Label
    </button>
    <button onclick="window.close()" class="px-4 py-2 shadow-sm btn btn-outline-secondary rounded-pill fw-bold ms-2">
        Tutup
    </button>
</div>

<div class="preview-container">
    @foreach($labelItems as $grItem)
        @php
            $masterItem = $grItem->item;
            $itemName = $grItem->purchaseOrderItem->description ?? $masterItem->name;
            $qty = (int) $grItem->qty_received;

            // 🪄 SIHIR DETEKTIF: Jika Aset, kita tarik nomor AST yang digenerate Controller tadi
            $assets = [];
            if ($masterItem->is_asset) {
                $assets = \App\Models\FixedAsset::where('goods_receipt_id', $gr->id)
                            ->where('item_id', $masterItem->id)
                            ->orderBy('id', 'asc')
                            ->get();
            }
        @endphp

        {{-- Looping cetak stiker sebanyak QTY yang diterima --}}
        @for ($i = 0; $i < $qty; $i++)
            @php
                // Default untuk Minor Asset
                $qrData = $masterItem->code;
                $assetNumText = "-";
                $labelType = "MINOR ASSET / INVENTORY";

                // Timpa data jika dia Fixed Asset
                if ($masterItem->is_asset && isset($assets[$i])) {
                    $qrData = $assets[$i]->asset_number; // Barcode isinya Nomor Aset
                    $assetNumText = $assets[$i]->asset_number;
                    $labelType = "FIXED ASSET";
                }
            @endphp

            <div class="label-sticker">
                <div class="label-qr">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data={{ urlencode($qrData) }}" alt="QR Code">
                    <div style="font-size: 7px; margin-top: 3px; font-weight: bold; letter-spacing: 0.5px;">{!! str_replace('/', '<br>', $qrData) !!}</div>
                </div>
                <div class="label-details">
                    <div style="font-size: 8px; font-weight: 900; border-bottom: 2px solid #000; margin-bottom: 3px; text-transform: uppercase;">
                        {{ $labelType }}
                    </div>
                    <div class="item-title">
                        {{ $itemName }}
                    </div>
                    <table style="width: 100%; font-size: 9px; line-height: 1.2; border: none;">
                        <tr>
                            <td style="width: 30%; font-weight: bold; padding: 0; color: #555;">Kode</td>
                            <td style="padding: 0; font-weight: bold;">: {{ $masterItem->code }}</td>
                        </tr>

                        @if($masterItem->is_asset)
                        <tr>
                            <td style="padding: 0; font-weight: bold; color: #555;">No. AST</td>
                            <td style="padding: 0; font-weight: bold;">: <span style="font-size: 8px;">{{ $assetNumText }}</span></td>
                        </tr>
                        @else
                        <tr>
                            <td style="padding: 0; font-weight: bold; color: #555;">Vol</td>
                            <td style="padding: 0; font-weight: bold;">: Unit {{ $i + 1 }} / {{ $qty }}</td>
                        </tr>
                        @endif

                        <tr>
                            <td style="padding: 0; font-weight: bold; color: #555;">Ref Masuk</td>
                            <td style="padding: 0; font-size: 8px;">: {{ $gr->gr_number }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 0; font-weight: bold; color: #555;">Tgl</td>
                            <td style="padding: 0;">: {{ \Carbon\Carbon::parse($gr->received_date)->format('d/m/Y') }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        @endfor
    @endforeach
</div>

</body>
</html>
