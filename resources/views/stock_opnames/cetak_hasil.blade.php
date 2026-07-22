<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Stock Opname - {{ $opname->document_number }}</title>
    <style>
        /* CSS Khusus untuk DomPDF agar rapi saat dicetak */
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 12px;
            color: #333;
            line-height: 1.4;
        }
        .header-table { width: 100%; border-bottom: 2px solid #2c3e50; padding-bottom: 15px; margin-bottom: 20px; }
        .header-table td { vertical-align: middle; }
        .company-name { font-size: 20px; font-weight: 800; color: #2c3e50; letter-spacing: 1px; }
        .title { font-size: 16px; font-weight: bold; text-align: center; text-transform: uppercase; }
        .doc-no { font-size: 14px; font-weight: bold; color: #e74c3c; text-align: right; }

        .info-table { width: 100%; margin-bottom: 20px; font-size: 11px; }
        .info-table td { padding: 4px; vertical-align: top; }
        .info-table .label { width: 15%; font-weight: bold; color: #555; }
        .info-table .colon { width: 2%; text-align: center; }
        .info-table .value { width: 33%; font-weight: bold; }

        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .data-table th, .data-table td { border: 1px solid #bdc3c7; padding: 8px 6px; }
        .data-table th {
            background-color: #ecf0f1;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            font-size: 10px;
            color: #2c3e50;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .fw-bold { font-weight: bold; }

        /* Highlight kolom hitung fisik */
        .col-actual { background-color: #fffde7; }

        .sign-table { width: 100%; margin-top: 30px; text-align: center; page-break-inside: avoid; }
        .sign-table td { width: 33%; padding: 10px; vertical-align: bottom; }
        .sign-space { height: 70px; margin: 10px 0; }
        .sign-name { text-decoration: underline; font-weight: bold; }
        .sign-role { font-size: 10px; color: #7f8c8d; margin-top: 3px; }
        .digital-stamp { color: #27ae60; border: 2px solid #27ae60; display: inline-block; padding: 5px; border-radius: 5px; transform: rotate(-5deg); font-weight: bold; font-size: 10px; }
    </style>
</head>
<body>

    {{-- KOP SURAT / HEADER --}}
    <table class="header-table">
        <tr>
            <td style="width: 25%;">
                <div class="company-name">{{ optional($opname->company)->code ?? 'PT NUSA' }}</div>
            </td>
            <td style="width: 50%;">
                <div class="title">LAPORAN HASIL AUDIT STOK<br>(STOCK OPNAME)</div>
            </td>
            <td style="width: 25%; text-align: right;">
                <div class="doc-no">{{ $opname->document_number }}</div>
                <div style="font-size: 10px; margin-top: 5px;">
                    Status: <strong>{{ optional($opname->status)->name ?? 'Selesai' }}</strong>
                </div>
            </td>
        </tr>
    </table>

    {{-- INFORMASI SESI --}}
    <table class="info-table">
        <tr>
            <td class="label">Entitas / PT</td><td class="colon">:</td><td class="value">{{ optional($opname->company)->name ?? 'Head Office' }}</td>
            <td class="label">Tanggal Audit</td><td class="colon">:</td><td class="value">{{ \Carbon\Carbon::parse($opname->start_date)->format('d M Y') }}</td>
        </tr>
        <tr>
            <td class="label">Gudang</td><td class="colon">:</td><td class="value">{{ optional($opname->warehouse)->name }}</td>
            <td class="label">Auditor</td><td class="colon">:</td><td class="value">{{ optional($opname->creator)->name ?? '-' }}</td>
        </tr>
    </table>

    {{-- TABEL DATA STOK (SISTEM VS FISIK) --}}
    <table class="data-table">
        <thead>
            <tr>
                <th width="4%">No</th>
                <th width="15%">Kode Barang</th>
                <th width="28%">Nama Barang & Spesifikasi</th>
                <th width="8%">Satuan</th>
                <th width="11%">Stok Sistem</th>
                <th width="11%" style="background-color: #f1c40f; color: #fff;">Hitung Fisik</th>
                <th width="10%">Selisih</th>
                <th width="13%">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @foreach($opname->items as $idx => $item)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td style="font-family: monospace; font-size: 11px;">{{ optional($item->item)->code ?? '-' }}</td>
                    <td>{{ optional($item->item)->name ?? 'Item Terhapus' }}</td>
                    <td class="text-center" style="font-size: 10px;">{{ $item->base_uom }}</td>
                    <td class="text-center fw-bold">{{ (float) $item->system_qty }}</td>
                    <td class="text-center fw-bold col-actual">{{ (float) $item->actual_qty }}</td>
                    <td class="text-center fw-bold" style="color: {{ $item->variance_qty < 0 ? '#c0392b' : ($item->variance_qty > 0 ? '#27ae60' : '#333') }};">
                        {{ $item->variance_qty > 0 ? '+' : '' }}{{ (float) $item->variance_qty }}
                    </td>
                    <td style="font-size: 10px; font-style: italic;">{{ $item->notes ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="text-right fw-bold">TOTAL QTY:</td>
                <td class="text-center fw-bold">{{ (float) $opname->items->sum('system_qty') }}</td>
                <td class="text-center fw-bold col-actual">{{ (float) $opname->items->sum('actual_qty') }}</td>
                <td class="text-center fw-bold">{{ $opname->items->sum('variance_qty') > 0 ? '+' : '' }}{{ (float) $opname->items->sum('variance_qty') }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    {{-- KOTAK TANDA TANGAN (Otomatis berdasarkan matriks persetujuan) --}}
    <table class="sign-table">
        <tr>
            {{-- Tanda Tangan Auditor --}}
            <td>
                <div>Disusun Oleh,</div>
                <div class="sign-space"></div>
                <div class="sign-name">{{ optional($opname->creator)->name ?? '_______________________' }}</div>
                <div class="sign-role">Auditor Stok Fisik</div>
            </td>

            {{-- Looping Tanda Tangan Approver --}}
            @if(isset($opname->approvals) && $opname->approvals->count() > 0)
                @foreach($opname->approvals as $app)
                    <td>
                        <div>Diperiksa/Disetujui Oleh,</div>
                        <div class="sign-space">
                            @if(strtolower($app->status) === 'approved')
                                <div class="digital-stamp">
                                    APPROVED<br>
                                    <span style="font-size: 8px; font-weight: normal;">{{ \Carbon\Carbon::parse($app->approved_at)->format('d/m/Y H:i') }}</span>
                                </div>
                            @endif
                        </div>
                        <div class="sign-name">{{ optional($app->approver)->name ?? '_______________________' }}</div>
                        <div class="sign-role">{{ optional($app->role)->name ?? 'Manajemen' }}</div>
                    </td>
                @endforeach
            @else
                {{-- Jika tidak ada matriks approval, tampilkan kolom kosong standar --}}
                <td>
                    <div>Mengetahui,</div>
                    <div class="sign-space"></div>
                    <div class="sign-name">_______________________</div>
                    <div class="sign-role">Manajer Operasional</div>
                </td>
            @endif
        </tr>
    </table>

</body>
</html>
