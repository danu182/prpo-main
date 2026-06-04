<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak RTV - {{ $rtv->rtv_number }}</title>
    <style>
        /* Font dan Reset Dasar */
        body { 
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; 
            font-size: 12px; 
            color: #000; 
            background-color: #f4f6f9;
            margin: 0;
            padding: 0;
        }
        
        /* Kertas Cetak */
        .print-container { 
            width: 100%; 
            max-width: 850px; 
            margin: 20px auto; 
            padding: 40px; 
            background-color: #fff;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        /* Kop Surat (Header) */
        .header { 
            border-bottom: 2px solid #000; 
            padding-bottom: 15px; 
            margin-bottom: 25px; 
        }
        .header h1 { 
            margin: 0; 
            font-size: 24px; 
            color: #dc3545; /* Merah RTV */
            text-transform: uppercase; 
            text-align: right; 
            letter-spacing: 1px;
        }
        .header h4 { 
            margin: 5px 0 0 0; 
            font-size: 14px; 
            text-align: right; 
        }
        .company-name { 
            font-size: 22px; 
            font-weight: bold; 
            margin-bottom: 5px; 
        }
        .company-address {
            font-size: 12px;
            color: #444;
            max-width: 250px;
        }

        /* Tabel Informasi Meta */
        .info-table { 
            width: 100%; 
            margin-bottom: 25px; 
            font-size: 12px;
        }
        .info-table td { 
            padding: 4px 5px; 
            vertical-align: top; 
        }
        .info-table .label { 
            width: 130px; 
            font-weight: bold; 
            color: #333;
        }
        .info-table .colon { width: 10px; }

        /* Tabel Item Barang */
        .item-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 40px; 
        }
        .item-table th, .item-table td { 
            border: 1px solid #000; 
            padding: 10px; 
            text-align: left; 
        }
        .item-table th { 
            background-color: #f8f9fa; 
            font-weight: bold; 
            text-align: center; 
            text-transform: uppercase;
            font-size: 11px;
        }
        .text-center { text-align: center !important; }

        /* Tanda Tangan */
        .signature-table { 
            width: 100%; 
            margin-top: 50px; 
            text-align: center; 
        }
        .signature-table td { 
            width: 33%; 
            padding-bottom: 80px; 
            vertical-align: bottom;
        }
        .signature-line { 
            border-top: 1px solid #000; 
            width: 70%; 
            margin: 0 auto; 
            padding-top: 5px; 
            font-weight: bold; 
            text-transform: uppercase;
        }
        .signature-title {
            margin-bottom: 60px;
        }

        /* Mode Cetak Sebenarnya (Ctrl+P) */
        @media print {
            .no-print { display: none !important; }
            body { background-color: #fff; margin: 0; }
            .print-container { 
                box-shadow: none; 
                margin: 0; 
                padding: 10px; 
                width: 100%; 
                max-width: 100%;
            }
            .header h1 { color: #dc3545 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .item-table th { background-color: #f8f9fa !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }

        /* Panel Navigasi Mengambang (Hanya di Layar) */
        .action-panel {
            text-align: center; 
            padding: 15px; 
            background: #343a40; 
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .action-panel button {
            padding: 10px 20px; 
            border: none; 
            border-radius: 5px; 
            cursor: pointer; 
            font-weight: bold;
            font-size: 14px;
        }
        .btn-print { background: #0d6efd; color: #fff; margin-right: 10px; }
        .btn-close-window { background: #6c757d; color: #fff; }
        .btn-print:hover { background: #0b5ed7; }
        .btn-close-window:hover { background: #5c636a; }
    </style>
</head>
<body>

    {{-- Panel Tombol Cetak (Akan hilang otomatis saat diprint) --}}
    <div class="no-print action-panel">
        <button class="btn-print" onclick="window.print()">🖨️ Cetak Dokumen (Print)</button>
        <button class="btn-close-window" onclick="window.close()">Tutup Jendela</button>
    </div>
    
    <div class="print-container">
        {{-- BAGIAN KOP SURAT --}}
        <table class="header" width="100%">
            <tr>
                <td width="55%">
                    <div class="company-name">{{ optional($rtv->goodsReceipt->po->company)->name ?? 'PT PERUSAHAAN UMUM' }}</div>
                    <div class="company-address">{{ optional($rtv->goodsReceipt->po->company)->address ?? 'Alamat belum diatur pada sistem.' }}</div>
                </td>
                <td width="45%" style="vertical-align: bottom;">
                    <h1>RETURN TO VENDOR</h1>
                    <h4>No: {{ $rtv->rtv_number }}</h4>
                </td>
            </tr>
        </table>

        {{-- BAGIAN INFORMASI DOKUMEN --}}
        <table class="info-table">
            <tr>
                <td class="label">No. Surat Jalan</td>
                <td class="colon">:</td>
                <td width="35%">{{ $rtv->delivery_note_number ?: '-' }}</td>
                
                <td class="label">Vendor Tujuan</td>
                <td class="colon">:</td>
                <td><strong>{{ optional($rtv->vendor)->name }}</strong></td>
            </tr>
            <tr>
                <td class="label">Referensi GR</td>
                <td class="colon">:</td>
                <td>{{ optional($rtv->goodsReceipt)->gr_number }}</td>
                
                <td class="label">Pihak Pengirim</td>
                <td class="colon">:</td>
                <td>{{ optional($rtv->returner)->name ?? 'Administrator' }}</td>
            </tr>
            <tr>
                <td class="label">Referensi PO</td>
                <td class="colon">:</td>
                <td>{{ optional(optional($rtv->goodsReceipt)->po)->po_number }}</td>
                
                <td class="label">Catatan Retur</td>
                <td class="colon">:</td>
                <td rowspan="2">{{ $rtv->notes ?: '-' }}</td>
            </tr>
            <tr>
                <td class="label">Tanggal Retur</td>
                <td class="colon">:</td>
                <td>{{ \Carbon\Carbon::parse($rtv->return_date)->format('d F Y') }}</td>
                
                <td></td>
                <td></td>
            </tr>
        </table>

        {{-- BAGIAN TABEL BARANG --}}
        <table class="item-table">
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th width="35%">Nama Barang & Kode</th>
                    <th width="15%">Qty Retur</th>
                    <th width="45%">Keterangan Item / Alasan Retur</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rtv->items as $idx => $item)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>
                        <strong>{{ optional($item->item)->name }}</strong><br>
                        <span style="color: #666; font-size: 11px;">{{ optional($item->item)->code }}</span>
                    </td>
                    <td class="text-center">
                        <strong style="font-size: 14px;">{{ (float)$item->qty_returned }}</strong><br>
                        <span style="font-size: 11px;">{{ optional($item->purchaseOrderItem)->uom }}</span>
                    </td>
                    <td>
                        {{-- Field ini sudah berisi riwayat "Sihir UOM" kita: "Diretur: x Pack (= x Eceran). Alasan: ..." --}}
                        {{ $item->return_reason }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        {{-- BAGIAN TANDA TANGAN --}}
        <table class="signature-table">
            <tr>
                <td>
                    <div class="signature-title">Diserahkan Oleh (Vendor/Kurir),</div>
                    <div class="signature-line">Nama Terang & TTD</div>
                </td>
                <td>
                    <div class="signature-title">Diketahui Oleh (Purchasing/QC),</div>
                    <div class="signature-line">Nama Terang & TTD</div>
                </td>
                <td>
                    <div class="signature-title">Dikeluarkan Oleh (Gudang),</div>
                    <div class="signature-line">{{ optional($rtv->returner)->name ?? 'STAF GUDANG' }}</div>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>