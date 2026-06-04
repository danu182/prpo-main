<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Label Minor Asset</title>
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

        /* Gaya Elegan Baru */
        .company-name { font-size: 8px; font-weight: 900; border-bottom: 2px solid #000; margin-bottom: 3px; text-transform: uppercase; }
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
    @foreach($assets as $asset)
        @php
            $masterItem = $asset->item;
            $namaBarang = $masterItem ? $masterItem->name : 'Barang Inventaris';
            $kodeBarang = $masterItem ? $masterItem->code : '-';

            // 1. Ambil jumlah fisik barang yang dipegang
            $qty = (int) $asset->qty;

            // 2. Pecah catatan (SN) berdasarkan garis lurus (|)
            $catatanSn = $asset->specific_details ? $asset->specific_details : '-';
            $arrayCatatan = array_map('trim', explode('|', $catatanSn));

            // 3. Nama perusahaan diambil dari Data Pegawai
            $user = \App\Models\User::with('company')->where('name', $asset->employee_name)->first();
            $namaPT = $user && $user->company ? $user->company->name : 'Milik Perusahaan';
            $tglSerah = $asset->created_at ? $asset->created_at->format('d/m/Y') : '-';
        @endphp

        {{-- 🔥 LAKUKAN PERULANGAN SEBANYAK QTY BARANG 🔥 --}}
        @for ($i = 0; $i < $qty; $i++)
            @php
                // Ambil SN spesifik untuk stiker ke-i
                $snFisik = $arrayCatatan[$i] ?? '-';

                // Jika ada SN, jadikan QR Code. Jika tidak ada, gabungkan Kode Barang + Singkatan Nama + Urutan
                $singkatanNama = strtoupper(substr(str_replace(' ', '', $asset->employee_name), 0, 3));
                $qrData = ($snFisik !== '-') ? $snFisik : $kodeBarang . '-' . $singkatanNama . '-' . str_pad($i + 1, 2, '0', STR_PAD_LEFT);
            @endphp

            <div class="label-sticker">
                <div class="label-qr">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data={{ urlencode($qrData) }}" alt="QR Code">
                    <div style="font-size: 7px; margin-top: 4px; font-weight: bold; white-space: nowrap; letter-spacing: 0.5px; overflow: hidden; text-overflow: ellipsis; max-width: 100%;">
                        {{ $qrData }}
                    </div>
                </div>

                <div class="label-details">
                    <div class="company-name">MINOR ASSET / INVENTORY</div>

                    <div class="item-title">{{ $namaBarang }}</div>

                    <table style="width: 100%; font-size: 8.5px; line-height: 1.1; border: none;">
                        <tr>
                            <td style="width: 28%; font-weight: bold; padding: 0; color: #555;">Kde</td>
                            <td style="padding: 0; font-weight: bold;">: {{ $kodeBarang }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 0; font-weight: bold; color: #555;">Milik</td>
                            <td style="padding: 0; font-weight: bold; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 120px;">
                                : {{ $namaPT }}
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 0; font-weight: bold; color: #555;">Pem</td>
                            <td style="padding: 0; font-weight: bold; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 120px;">
                                : {{ $asset->employee_name }}
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 0; font-weight: bold; color: #555;">SN</td>
                            <td style="padding: 0; font-weight: bold; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 120px;">
                                : {{ $snFisik }}
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 0; font-weight: bold; color: #555;">Tgl In</td>
                            <td style="padding: 0; font-weight: bold;">: {{ $tglSerah }}</td>
                        </tr>
                    </table>

                    <div style="margin-top: 3px; padding-top: 2px; border-top: 1px dotted #ccc; font-size: 7px; color: #666;">
                        Status: Minor Asset Dipinjamkan
                    </div>
                </div>
            </div>
        @endfor
    @endforeach
</div>

</body>
</html>
