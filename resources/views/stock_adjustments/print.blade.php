<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Berita Acara Opname - {{ $adjustment->adjustment_number }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 12px; color: #333; margin: 0; padding: 0; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 15px; }
        .header h2 { margin: 0 0 5px 0; font-size: 20px; text-transform: uppercase; color: #000; }
        .header p { margin: 0; font-size: 14px; color: #555; }

        .info-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
        .info-table td { padding: 4px 0; vertical-align: top; }

        /* Tabel Barang */
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 8px; text-align: left; vertical-align: middle; }
        .data-table th { background-color: #f2f2f2; text-align: center; font-weight: bold; font-size: 11px; }
        .data-table tr { page-break-inside: avoid; }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-success { color: #198754; font-weight: bold; }
        .text-danger { color: #dc3545; font-weight: bold; }

        /* Kolom TTD */
        .sign-table { width: 100%; margin-top: 100px; text-align: center; page-break-inside: avoid; } /* margin-top diubah jadi 100px */
        .sign-table td { width: 50%; vertical-align: top; }
        .sign-space { height: 80px; }
        .sign-line { border-top: 1px solid #000; width: 60%; margin: 0 auto; padding-top: 5px; font-weight: bold; }
    </style>
</head>
<body>

    <div class="header">
        <h2>Berita Acara Penyesuaian Stok (Opname)</h2>
        <p><strong>No. Dokumen: {{ $adjustment->adjustment_number }}</strong></p>
    </div>

    <table class="info-table">
        <tr>
            <td width="20%"><strong>Tanggal Opname</strong></td>
            <td width="30%">: {{ \Carbon\Carbon::parse($adjustment->adjustment_date)->format('d F Y') }}</td>
            <td width="20%"><strong>Eksekutor (PIC)</strong></td>
            <td width="30%">: {{ optional($adjustment->adjuster)->name }}</td>
        </tr>
        <tr>
            <td><strong>Lokasi Gudang</strong></td>
            <td>: {{ optional($adjustment->warehouse)->name }}</td>
            <td><strong>Alasan / Keterangan</strong></td>
            <td>: {{ $adjustment->reason }}</td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="40%">Nama Barang & Kode</th>
                <th width="15%">Stok Sistem</th>
                <th width="15%">Stok Fisik</th>
                <th width="25%">Selisih / Mutasi</th>
            </tr>
        </thead>
        <tbody>
            @foreach($adjustment->items as $index => $item)
                @php
                    $diff = $item->difference;
                    $diffText = $diff > 0 ? '+'.$diff.' (Tambah)' : $diff.' (Kurang)';
                    $diffColor = $diff > 0 ? 'text-success' : 'text-danger';
                @endphp
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>
                    <strong>{{ optional($item->item)->name }}</strong><br>
                    <span style="font-size: 10px; color: #666;">SKU: {{ optional($item->item)->code }}</span>
                </td>
                <td class="text-center">{{ (float) $item->previous_stock }}</td>
                <td class="text-center" style="font-weight: bold; color: #0d6efd;">{{ (float) $item->new_stock }}</td>
                <td class="text-center {{ $diffColor }}">{{ $diffText }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <p style="font-size: 10px; font-style: italic; color: #666;">
        * Dokumen ini dicetak secara otomatis oleh sistem pada {{ date('d F Y H:i') }} dan penyesuaian di atas telah memicu mutasi pada kartu stok barang terkait.
    </p>

    <table class="sign-table">
        <tr>
            <td>
                <div class="sign-line">Kepala Gudang</div>
            </td>
            <td>
                <div class="sign-line">Eksekutor / Admin</div>
                <div style="font-size: 11px; margin-top: 2px;">{{ optional($adjustment->adjuster)->name }}</div>
            </td>
        </tr>
    </table>

</body>
</html>
