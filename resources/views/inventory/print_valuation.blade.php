<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Mutasi Inventory</title>
    @if(!isset($isExcel))
    <style>
        /* Pengaturan Margin Kertas A4 */
        @page { margin: 40px 40px 60px 40px; }

        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 10pt; color: #000; margin: 0; padding: 0; }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .fw-bold { font-weight: bold; }

        /* Kop Laporan */
        .header { border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
        .header h2 { margin: 0; font-size: 14pt; text-transform: uppercase; letter-spacing: 1px; color: #1e3a8a; }
        .header p { margin: 5px 0 0 0; font-size: 10pt; }

        /* Tabel Mutasi */
        table.data-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 6px 8px; font-size: 9pt; }
        .data-table th { background-color: #f1f5f9; text-align: center; font-weight: bold; }

        .row-data td { vertical-align: middle; }

        /* Tanda Tangan */
        .signature-table { width: 30%; margin-left: 70%; margin-top: 30px; border: none; }
        .signature-table td { border: none; text-align: center; font-size: 10pt; }

        /* 🔥 FOOTER & NOMOR HALAMAN ABADI 🔥 */
        footer {
            position: fixed;
            bottom: -40px; /* Posisi di dalam margin bawah kertas */
            left: 0px;
            right: 0px;
            height: 30px;
            font-size: 8.5pt;
            color: #555;
            border-top: 1px solid #888;
            padding-top: 5px;
            font-style: italic;
        }
        .footer-table { width: 100%; border: none; }
        .footer-table td { border: none; padding: 0; }
        .pagenum:before { content: "Halaman " counter(page); }
    </style>
    @endif
</head>
<body>

    {{-- 🔥 FOOTER INI WAJIB DITARUH DI ATAS AGAR TERBACA DI SEMUA HALAMAN OLEH DOMPDF 🔥 --}}
    @if(!isset($isExcel))
    <footer>
        <table class="footer-table">
            <tr>
                <td style="text-align: left;">
                    Dicetak otomatis oleh sistem pada: {{ \Carbon\Carbon::now()->translatedFormat('d F Y H:i:s') }} WIB
                </td>
                <td style="text-align: right;">
                    <span class="pagenum"></span>
                </td>
            </tr>
        </table>
    </footer>
    @endif

    {{-- KOP LAPORAN --}}
    <div class="text-center header">
        <h2>LAPORAN MUTASI INVENTORY</h2>
        <p>
            Periode: <strong>{{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }}</strong> s/d <strong>{{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}</strong><br>
            Lokasi Gudang: <strong>{{ $warehouse ? $warehouse->name : 'SEMUA GUDANG' }}</strong>
        </p>
    </div>

    {{-- ISI TABEL --}}
    <table class="data-table">
        <thead>
            <tr>
                <th width="4%">No</th>
                <th width="12%">Kode Barang</th>
                <th width="32%">Nama Inventory</th>
                <th width="14%">Kategori</th>
                <th width="9%">Saldo Awal</th>
                <th width="9%">Masuk (IN)</th>
                <th width="9%">Keluar (OUT)</th>
                <th width="11%">Saldo Akhir</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $index => $item)
                <tr class="row-data">
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td style="font-family: monospace;">{{ $item->code }}</td>
                    <td><strong>{{ $item->name }}</strong></td>
                    <td>{{ optional($item->category)->name ?? '-' }}</td>
                    <td class="text-end">{{ number_format($item->saldo_awal, 0, ',', '.') }}</td>
                    <td class="text-end" style="color: #15803d;">{{ $item->mutasi_in }}</td>
                    <td class="text-end" style="color: #b91c1c;">{{ $item->mutasi_out }}</td>
                    <td class="text-end fw-bold">{{ number_format($item->saldo_akhir, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center" style="padding: 20px;">Tidak ada pergerakan barang pada periode dan filter ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Kotak Tanda Tangan (Hanya muncul di PDF, bukan di Excel) --}}
    @if(!isset($isExcel))
    <table class="signature-table">
        <tr>
            <td>
                <p>Mengetahui,</p>
                <br><br><br><br>
                <p class="fw-bold" style="text-decoration: underline;">{{ auth()->user()->name ?? 'Administrator' }}</p>
                <p style="margin-top: -10px; font-size: 9pt;">Bag. Inventory / Gudang</p>
            </td>
        </tr>
    </table>
    @endif

</body>
</html>
