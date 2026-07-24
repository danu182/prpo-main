<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Bukti Pengeluaran Barang</title>
    <style>
        @page { margin: 40px 50px 70px 50px; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 10.5pt; line-height: 1.4; color: #000; }

        .header { text-align: center; border-bottom: 3px double #000; padding-bottom: 10px; margin-bottom: 20px; }
        .header h2 { margin: 0; font-size: 16pt; text-transform: uppercase; letter-spacing: 1px; }
        .header p { margin: 5px 0 0 0; font-size: 10pt; color: #333; }

        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .info-table td { vertical-align: top; padding: 4px 0; font-size: 10pt; }
        .info-table td:nth-child(1), .info-table td:nth-child(4) { width: 18%; font-weight: bold; }
        .info-table td:nth-child(2), .info-table td:nth-child(5) { width: 2%; }
        .info-table td:nth-child(3) { width: 35%; }
        .info-table td:nth-child(6) { width: 25%; }

        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 6px 8px; font-size: 9.5pt; }
        .data-table th { background-color: #f0f0f0; text-align: center; text-transform: uppercase; }

        .signature-table { width: 100%; margin-top: 40px; text-align: center; page-break-inside: avoid; }
        .signature-table td { width: 33.33%; vertical-align: bottom; }
        .sign-space { height: 80px; }
        .sign-name { font-weight: bold; text-decoration: underline; }
        .sign-title { font-size: 9pt; color: #333; }

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
        Dokumen GI: {{ $gi->gi_number }} &nbsp; | &nbsp; Dicetak pada: {{ date('d-m-Y H:i') }} &nbsp; | &nbsp; <span class="pagenum"></span>
    </footer>

    <div class="header">
        <h2>BUKTI PENGELUARAN BARANG</h2>
        <p>MATERIAL ISSUE SLIP</p>
    </div>

    <table class="info-table">
        <tr>
            <td>No. Dokumen</td><td>:</td>
            <td><strong>{{ $gi->gi_number }}</strong></td>
            <td>Tanggal Keluar</td><td>:</td>
            <td>{{ \Carbon\Carbon::parse($gi->issue_date)->translatedFormat('d F Y') }}</td>
        </tr>
        <tr>
            <td>Penerima</td><td>:</td>
            <td><strong>{{ $gi->requester_name }}</strong></td>
            <td>Asal Gudang</td><td>:</td>
            <td>{{ optional($gi->warehouse)->name ?? '-' }}</td>
        </tr>
        <tr>
            <td>Departemen/Proyek</td><td>:</td>
            <td>{{ $gi->department ?? '-' }}</td>
            <td>Status</td><td>:</td>
            <td>{{ optional($gi->status)->name ?? 'Valid' }}</td>
        </tr>
    </table>

    <p style="font-size: 10pt; margin-bottom: 5px;">Rincian barang yang dikeluarkan dari gudang:</p>

    <table class="data-table">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="15%">Kode Barang</th>
                <th width="35%">Nama & Spesifikasi Barang</th>
                <th width="10%">Qty</th>
                <th width="12%">Satuan</th>
                <th width="23%">Keterangan / SN</th>
            </tr>
        </thead>
        <tbody>
            @foreach($gi->items as $index => $item)
            <tr>
                <td style="text-align: center;">{{ $index + 1 }}</td>
                <td style="text-align: center;">{{ optional($item->item)->code ?? '-' }}</td>
                <td>
                    <strong>{{ $item->item_name ?? optional($item->item)->name }}</strong>
                    @if(optional($item->item)->specification)
                        <br><span style="font-size: 8pt; color:#444;">{{ strip_tags(optional($item->item)->specification) }}</span>
                    @endif
                </td>
                <td style="text-align: center; font-weight: bold;">{{ (float) $item->qty_issued }}</td>
                <td style="text-align: center;">
                    {{ trim(preg_replace('/ \(Isi:?.*\)/i', '', $item->uom ?? optional(optional($item->item)->uom)->name ?? '-')) }}
                </td>
                <td style="font-size: 8pt; vertical-align: top;">
                    @php
                        $rawNotes = $item->notes ?? '-';
                        // Pecah teks berdasarkan pemisah " | " bawaan sistem
                        $parts = explode(' | ', $rawNotes);
                    @endphp

                    @foreach($parts as $part)
                        @if(\Illuminate\Support\Str::startsWith(trim($part), 'SN:'))
                            @php
                                // Jika itu adalah deretan SN, kita pecah lagi komanya menjadi list
                                $snList = explode(', ', str_replace('SN: ', '', trim($part)));
                            @endphp
                            <div style="margin-top: 4px; margin-bottom: 4px; background-color: #f9f9f9; padding: 4px; border-radius: 4px; border: 1px solid #eee;">
                                <strong>Daftar S/N:</strong>
                                <ul style="margin: 2px 0 0 15px; padding: 0;">
                                    @foreach($snList as $sn)
                                        <li style="margin-bottom: 2px;">{{ trim($sn) }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @else
                            {{-- Teks biasa (seperti 'ok' atau 'Dikeluarkan fisik: 3 Pieces') --}}
                            <div style="margin-bottom: 4px;">{!! nl2br(e(trim($part))) !!}</div>
                        @endif
                    @endforeach
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div style="font-size: 9.5pt; margin-top: 15px; border: 1px dashed #777; padding: 10px; background-color: #fdfdfd;">
        <strong>Pernyataan:</strong><br>
        Barang-barang / material di atas telah diserahkan oleh pihak Gudang dan diterima oleh pihak peminta dalam jumlah yang sesuai dan kondisi fisik yang baik untuk dipergunakan sebagaimana mestinya.
    </div>

    <table class="signature-table">
        <tr>
            <td>
                <strong>Peminta / Penerima</strong>
                <div class="sign-space"></div>
                <div class="sign-name">{{ $gi->requester_name }}</div>
                <div class="sign-title">Tanda Tangan & Nama Terang</div>
            </td>
            <td>
                <strong>Disetujui Oleh</strong>
                <div class="sign-space"></div>
                <div class="sign-name">______________________</div>
                <div class="sign-title">Manager / Supervisor</div>
            </td>
            <td>
                <strong>Diserahkan Oleh</strong>
                <div class="sign-space"></div>
                <div class="sign-name">{{ optional($gi->issuer)->name ?? auth()->user()->name }}</div>
                <div class="sign-title">Bagian Gudang / Logistik</div>
            </td>
        </tr>
    </table>

</body>
</html>
