<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Label Aset - Tom & Jerry 127</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <style>
        :root {
            --label-width: 85mm;      /* Lebar baku stiker T&J 127 */
            --label-height: 35mm;     /* Tinggi baku stiker T&J 127 */
            --cols: 2;                /* Jumlah kolom (T&J 127 biasanya 2 kolom) */
            --gap-x: 2mm;             /* Jarak celah horizontal antar kotak stiker */
            --gap-y: 2mm;             /* Jarak celah vertikal antar kotak stiker */
            --page-padding-top: 5mm;  /* 🔥 UBAH INI JIKA CETAKAN TERLALU KE ATAS/BAWAH */
            --page-padding-left: 5mm; /* 🔥 UBAH INI JIKA CETAKAN TERLALU KE KIRI/KANAN */
        }

        body { background-color: #e9ecef; font-family: 'Arial', sans-serif; }

        .control-panel {
            background: #fff; padding: 15px 25px; border-bottom: 2px solid #0d6efd;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin-bottom: 20px;
            position: sticky; top: 0; z-index: 1000;
        }

        .sheet {
            background: white; width: 210mm; min-height: 297mm;
            margin: 0 auto; padding: var(--page-padding-top) var(--page-padding-left);
            box-shadow: 0 0 10px rgba(0,0,0,0.15); display: grid;
            grid-template-columns: repeat(var(--cols), var(--label-width));
            column-gap: var(--gap-x); row-gap: var(--gap-y); align-content: start;
        }

        .label-empty {
            width: var(--label-width); height: var(--label-height);
            background: repeating-linear-gradient(45deg, #f8f9fa, #f8f9fa 10px, #ffffff 10px, #ffffff 20px);
            border: 1px solid #eee; display: flex; align-items: center; justify-content: center;
            color: #ccc; font-size: 0.8rem; font-weight: bold;
        }

        .label-sticker {
            width: var(--label-width);
            height: var(--label-height);
            background-color: white;
            border: 1px dashed #adb5bd;
            padding: 5px;
            box-sizing: border-box;
            display: flex;
            flex-direction: row;
            align-items: center;
            page-break-inside: avoid;
            overflow: hidden;
        }

        .label-qr {
            width: 30%; text-align: center; display: flex; flex-direction: column;
            align-items: center; justify-content: center; padding-right: 5px; border-right: 1px dashed #eee;
        }
        .label-qr img { max-width: 100%; height: auto; image-rendering: pixelated; margin-bottom: 2px; }
        .label-qr .qr-text { font-size: 7px; font-weight: bold; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }

        .label-details {
            width: 70%; padding-left: 8px; display: flex; flex-direction: column; justify-content: center;
        }

        /* Typography Rapat & Padat */
        .company-name { font-size: 8px; font-weight: 900; border-bottom: 2px solid #000; margin-bottom: 2px; text-transform: uppercase; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .item-title { font-size: 9px; font-weight: bold; line-height: 1.1; margin-bottom: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .master-title { font-size: 7.5px; color: #555; margin-bottom: 4px; font-weight: bold; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .info-table { width: 100%; font-size: 7.5px; line-height: 1.1; border: none; }
        .info-table td { padding: 0 0 1px 0; font-weight: bold; }
        .info-table td.label-col { width: 28%; color: #555; }
        .info-table td.val-col { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 90px; }

        @media print {
            @page { size: A4; margin: 0; }
            body { background: white; }
            .control-panel { display: none !important; }
            .sheet { box-shadow: none; margin: 0; padding-top: var(--page-padding-top) !important; padding-left: var(--page-padding-left) !important; }
            .label-sticker { border: none !important; }
            .label-empty { border: none !important; background: transparent !important; color: transparent !important; }
        }
    </style>
</head>
<body>

    <div class="control-panel d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0 fw-bold text-primary"><i class="bi bi-upc-scan me-2"></i> Print Preview Label</h5>
            <div class="small text-muted">Kertas: Tom & Jerry 127 | Total: {{ count($assets) }} item</div>
        </div>

        <form action="{{ url()->current() }}" method="GET" class="gap-3 d-flex align-items-center">
            @if(request('ids')) <input type="hidden" name="ids" value="{{ request('ids') }}"> @endif
            @if(request('raw_sn'))
                <input type="hidden" name="raw_sn" value="{{ request('raw_sn') }}">
                <input type="hidden" name="item_id" value="{{ request('item_id') }}">
            @endif

            <div class="input-group input-group-sm" style="width: 350px;">
                <span class="input-group-text bg-light fw-bold">Mulai Cetak di Kotak Ke:</span>
                <input type="number" name="start_pos" class="text-center form-control fw-bold text-primary" value="{{ $startPos }}" min="1" max="40">
                <button type="submit" class="btn btn-secondary"><i class="bi bi-arrow-clockwise"></i></button>
            </div>

            <button type="button" onclick="window.print()" class="px-4 shadow-sm btn btn-primary btn-sm fw-bold">
                <i class="bi bi-printer-fill me-1"></i> CETAK SEKARANG
            </button>
            <button type="button" onclick="window.close()" class="btn btn-outline-danger btn-sm">Tutup</button>
        </form>
    </div>

    <div class="sheet">
        @for ($i = 1; $i < $startPos; $i++)
            <div class="label-empty">label ke- {{ $i }} Terpakai</div>
        @endfor

        @foreach($assets as $asset)
            <div class="label-sticker">
                <!-- QR CODE -->
                <div class="label-qr">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data={{ urlencode($asset->qr_data) }}" alt="QR Code">
                    {{-- <div class="qr-text">{{ $asset->qr_data }}</div> --}}
                    <div class="qr-text">
                        @if($asset->serial_number !== '-')
                            {{ $asset->serial_number }}
                        @else
                            {{ $asset->item_code }}
                        @endif
                    </div>
                </div>

                <!-- DESKRIPSI -->
                <div class="label-details">
                    <div class="company-name" title="{{ $asset->company_name }}">{{ $asset->company_name }}</div>

                    <!-- Nama Master Item sebagai Judul Utama -->
                    <div class="item-title" title="{{ $asset->master_item_name }}">{{ $asset->master_item_name }}</div>

                    <!-- Deskripsi Spesifik PO sebagai Sub-judul -->
                    @if($asset->po_item_name !== $asset->master_item_name)
                        <div class="master-title" title="{{ $asset->po_item_name }}">{{ $asset->po_item_name }}</div>
                    @endif

                    <table class="info-table">
                        <tr>
                            <td class="label-col">Kode</td>
                            <td class="val-col">: {{ $asset->item_code }}</td>
                        </tr>
                        <tr>
                            <td class="label-col">SN</td>
                            <td class="val-col">: {{ $asset->serial_number }}</td>
                        </tr>
                        <tr>
                            <td class="label-col">FA No</td>
                            <td class="val-col">: {{ $asset->asset_number }}</td>
                        </tr>
                        <tr>
                            <td class="label-col">Tgl In</td>
                            <td class="val-col">: {{ $asset->tanggal_in }}</td>
                        </tr>
                        <tr>
                            <td class="label-col">Ref</td>
                            <td class="val-col">: {{ $asset->reference_number }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        @endforeach
    </div>

</body>
</html>
