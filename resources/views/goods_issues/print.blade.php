@php
    $hasAsset = $gi->items->contains(fn($i) => $i->item->is_asset ?? false);
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak BAST - {{ $gi->gi_number }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        /* 🔥 STEMPEL KHUSUS KERTAS PRINT 🔥 */
        .print-void-watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-25deg);
            border: 15px double #dc3545;
            color: #dc3545;
            font-size: 100pt;
            font-weight: 900;
            padding: 20px 60px;
            opacity: 0.12;
            z-index: 9999;
            text-transform: uppercase;
            letter-spacing: 15px;
            pointer-events: none;
            border-radius: 30px;
            display: none;
            text-align: center;
            line-height: 1;
        }

        @media print {
            body { background-color: #fff !important; }
            .no-print { display: none !important; }
            .print-void-watermark {
                display: block !important;
                -webkit-print-color-adjust: exact;
            }
        }

        /* Styling Umum */
        .print-area { background-color: white; padding: 40px; margin: 20px auto; max-width: 900px; box-shadow: 0 0 15px rgba(0,0,0,0.3); position: relative; }
        .document-title { color: #dc3545; font-weight: 800; letter-spacing: 1px; }
        .signature-box { margin-top: 60px; text-align: center; }
        .signature-line { border-bottom: 1px solid #000; width: 75%; margin: 0 auto; margin-top: 70px; margin-bottom: 8px; }
        
        /* Layout BAST Aset */
        .bast-container { background-color: #fff; width: 210mm; min-height: 297mm; margin: 20px auto; padding: 1.5cm; box-shadow: 0 0 15px rgba(0,0,0,0.3); color: #000; font-family: 'Arial', sans-serif; font-size: 10.5pt; line-height: 1.4; position: relative;}
        .bast-header { text-align: center; border-bottom: 3px double #000; padding-bottom: 10px; margin-bottom: 15px; }
        .bast-header h2 { margin: 0; font-size: 14pt; font-weight: bold; text-transform: uppercase;}
    </style>
</head>
<body style="background-color: #525659;">

    {{-- Munculkan Watermark jika status VOID --}}
    @if(optional($gi->status)->slug === 'void')
        <div class="print-void-watermark">BATAL / VOID</div>
    @endif

    <div class="mt-3 mb-3 text-center no-print">
        <button onclick="window.print()" class="px-5 shadow btn btn-primary btn-lg fw-bold">🖨️ Cetak Dokumen</button>
    </div>

@if(!$hasAsset)
    {{-- ========================================================================= --}}
    {{-- 📦 TEMPLATE 1: BUKTI PENGELUARAN STOK (NON-ASET) --}}
    {{-- ========================================================================= --}}
    <div class="print-area">
        <div class="pb-3 mb-4 border-2 row border-bottom border-dark align-items-center">
            <div class="col-sm-6">
                <h3 class="mb-0 fw-bold text-dark">PROCURE<span class="fw-normal">APP</span></h3>
                <small class="text-muted">{{ optional($gi->warehouse)->name ?? 'Gudang Utama & Logistik' }}</small>
            </div>
            <div class="mt-3 col-sm-6 text-sm-end mt-sm-0">
                <h4 class="mb-1 document-title">BUKTI PENGELUARAN BARANG</h4>
                <h6 class="mb-0 fw-bold text-dark">No: {{ $gi->gi_number }}</h6>
            </div>
        </div>

        <div class="mb-4 row">
            <div class="col-sm-7">
                <table class="table mb-0 table-sm table-borderless">
                    <tr><td width="35%" class="text-muted">Diserahkan kepada</td><td width="5%">:</td><td class="fw-bold">{{ $gi->requester_name }}</td></tr>
                    <tr><td class="text-muted">Departemen</td><td>:</td><td class="fw-bold">{{ $gi->department ?? '-' }}</td></tr>
                    <tr><td class="text-muted">Keperluan</td><td>:</td><td>{{ $gi->notes ?? '-' }}</td></tr>
                </table>
            </div>
            <div class="col-sm-5">
                <table class="table mb-0 table-sm table-borderless">
                    <tr><td width="45%" class="text-muted">Tanggal Keluar</td><td width="5%">:</td><td class="fw-bold">{{ \Carbon\Carbon::parse($gi->issue_date)->format('d F Y') }}</td></tr>
                    <tr><td class="text-muted">Dikeluarkan Oleh</td><td>:</td><td>{{ optional($gi->issuer)->name }}</td></tr>
                </table>
            </div>
        </div>

        <table class="table mb-5 align-middle table-bordered border-dark">
            <thead class="text-center bg-light fw-bold">
                <tr>
                    <th width="5%" class="py-2">No</th>
                    <th width="40%" class="py-2 text-start ps-3">Nama Barang</th>
                    <th width="20%" class="py-2">Qty Keluar</th>
                    <th width="35%" class="py-2">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @foreach($gi->items as $index => $item)
                <tr>
                    <td class="py-2 text-center">{{ $index + 1 }}</td>
                    <td class="py-2 ps-3">
                        <div class="fw-bold">{{ optional($item->item)->name }}</div>
                        <small class="text-muted">{{ optional($item->item)->code }}</small>
                    </td>
                    {{-- 🔥 TAMBAHAN SATUAN (UOM) 🔥 --}}
                    <td class="py-2 text-center fw-bold">
                        {{ (float)$item->qty_issued }} {{ optional($item->item->uom)->name ?? 'Unit' }}
                    </td>
                    <td class="py-2 small text-muted">{{ $item->notes ?? '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="row signature-box">
            <div class="col-4">
                <p class="mb-0 small text-muted">Penerima Barang,</p>
                <div class="signature-line"></div>
                <p class="mb-0 fw-bold">{{ $gi->requester_name }}</p>
            </div>
            <div class="col-4"></div>
            <div class="col-4">
                <p class="mb-0 small text-muted">Diserahkan Oleh,</p>
                <div class="signature-line"></div>
                <p class="mb-0 fw-bold">{{ optional($gi->issuer)->name }}</p>
            </div>
        </div>
    </div>

@else
    {{-- ========================================================================= --}}
    {{-- 💻 TEMPLATE 2: BAST ASET TETAP (BAST FORMAL) --}}
    {{-- ========================================================================= --}}
    <div class="bast-container">
        <div class="bast-header">
            <h2>BERITA ACARA SERAH TERIMA ASET</h2>
            <p class="mb-0">Nomor: BAST/{{ \Carbon\Carbon::parse($gi->issue_date)->format('Y/m') }}/{{ substr($gi->gi_number, -4) }}</p>
        </div>

        <div class="bast-content">
            <p style="text-align: justify;">Pada hari ini, tanggal <strong>{{ \Carbon\Carbon::parse($gi->issue_date)->translatedFormat('d F Y') }}</strong>, telah dilakukan serah terima barang/aset perusahaan dengan rincian pihak sebagai berikut:</p>

            <table style="width: 100%; margin-bottom: 15px;">
                <tr><td width="28%"><strong>Nama Penyerah</strong></td><td width="3%">:</td><td><strong>{{ optional($gi->issuer)->name }}</strong></td></tr>
                <tr><td><strong>Gudang Pengirim</strong></td><td>:</td><td>{{ optional($gi->warehouse)->name ?? '-' }}</td></tr>
                <tr><td colspan="3"><i>Selanjutnya disebut sebagai <strong>PIHAK PERTAMA</strong>.</i></td></tr>
            </table>

            <table style="width: 100%; margin-bottom: 15px;">
                <tr><td width="28%"><strong>Nama Penerima</strong></td><td width="3%">:</td><td><strong>{{ $gi->requester_name }}</strong></td></tr>
                <tr><td><strong>Departemen / Proyek</strong></td><td>:</td><td>{{ $gi->department ?? '-' }}</td></tr>
                <tr><td colspan="3"><i>Selanjutnya disebut sebagai <strong>PIHAK KEDUA</strong>.</i></td></tr>
            </table>

            <p style="text-align: justify;"><strong>PIHAK PERTAMA</strong> menyerahkan barang/aset perusahaan kepada <strong>PIHAK KEDUA</strong> dalam kondisi <strong>BAIK</strong> dengan rincian:</p>

            <table class="table table-bordered border-dark align-middle">
                <thead class="text-center bg-light">
                    <tr>
                        <th width="5%">No</th>
                        <th width="35%">Nama Barang</th>
                        <th width="15%">Qty</th>
                        <th width="45%">Identitas Aset (SN / Spek)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($gi->items as $index => $row)
                    <tr>
                        <td class="text-center">{{ $index + 1 }}</td>
                        <td><strong>{{ optional($row->item)->name }}</strong></td>
                        {{-- 🔥 TAMBAHAN SATUAN (UOM) 🔥 --}}
                        <td class="text-center fw-bold">
                            {{ (float)$row->qty_issued }} {{ optional($row->item->uom)->name ?? 'Unit' }}
                        </td>
                        <td style="font-size: 9pt; line-height: 1.3;">
                            @if($row->item->is_asset || !empty($row->notes))
                                {!! str_replace(' | ', '<br>', $row->notes) !!}
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <p class="mt-3"><strong>Syarat dan Ketentuan:</strong></p>
            <ol style="font-size: 9.5pt; text-align: justify; padding-left: 20px;">
                <li>PIHAK KEDUA bertanggung jawab penuh atas perawatan dan keamanan aset tersebut.</li>
                <li>Aset ini adalah milik Perusahaan dan semata-mata digunakan untuk kepentingan operasional.</li>
                <li>PIHAK KEDUA wajib mengembalikan aset ini apabila terjadi pemutusan hubungan kerja atau tidak lagi dibutuhkan.</li>
            </ol>

            <table class="mt-5 text-center" style="width: 100%; table-layout: fixed;">
                <tr>
                    <td><strong>YANG MENERIMA,</strong></td>
                    <td><strong>MENGETAHUI,</strong></td>
                    <td><strong>YANG MENYERAHKAN,</strong></td>
                </tr>
                <tr><td colspan="3" style="height: 70px;"></td></tr>
                <tr>
                    <td><div style="text-decoration: underline; font-weight: bold;">{{ $gi->requester_name }}</div>Pihak Kedua</td>
                    <td><div style="border-bottom: 1px solid #000; width: 60%; margin: 0 auto; margin-bottom: 3px;"></div>Atasan</td>
                    <td><div style="text-decoration: underline; font-weight: bold;">{{ optional($gi->issuer)->name }}</div>Pihak Pertama</td>
                </tr>
            </table>
        </div>
    </div>
@endif

</body>
</html>