<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Bank Payment Request Form</title>
    <style>
        /* Pengaturan Margin Kertas & Font */
        @page { margin: 40px 40px 60px 40px; } /* Margin bawah disiapkan untuk Footer */
        body { 
            font-family: 'Helvetica', 'Arial', sans-serif; 
            font-size: 10pt; 
            color: #000; 
            line-height: 1.3; 
        }

        /* HEADER PERUSAHAAN & JUDUL */
        .company-name { font-size: 16pt; font-weight: bold; margin: 0 0 5px 0; text-transform: uppercase; }
        .doc-title { font-size: 12pt; margin: 0 0 15px 0; color: #333; }

        /* PENGATURAN FOOTER (TANGGAL & HALAMAN) - Anti Pecah */
        footer { position: fixed; bottom: -30px; left: 0px; right: 0px; height: 30px; }
        .footer-table { 
            width: 100%; border-top: 1px dashed #888; 
            padding-top: 5px; font-size: 8pt; color: #555; font-style: italic; 
            border-collapse: collapse;
        }
        .pagenum:before { content: counter(page); }

        /* PENGATURAN TABEL UMUM */
        table { width: 100%; border-collapse: collapse; }
        td, th { border: 1px solid #000; padding: 6px 8px; vertical-align: top; }
        
        /* ALIGNMENT & FONT */
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .fw-bold { font-weight: bold; }

        /* TABEL 1: INFO DOKUMEN */
        .info-table { margin-bottom: 15px; page-break-inside: avoid; }
        .info-label { width: 15%; font-weight: bold; border-right: none; }
        .info-colon { width: 2%; border-left: none; border-right: none; text-align: center; }
        .info-value { width: 33%; border-left: none; }

        /* TABEL 2: ITEM RINCIAN */
        .item-table { margin-bottom: 15px; }
        .item-table th { background-color: #f0f0f0; text-align: center; font-weight: bold; }

        /* TRIK KHUSUS DOMPDF UNTUK FORMAT "Rp" AGAR RATA KIRI KANAN */
        .currency-table td { border: none; padding: 0; vertical-align: middle; }

        /* TABEL 3: TANDA TANGAN */
        .sig-table { page-break-inside: avoid; }
        .sig-table td { width: 50%; height: 100px; vertical-align: top; }
        .sig-name { text-align: center; font-weight: bold; text-decoration: underline; margin-top: 60px; }
    </style>
</head>
<body>

    {{-- 🔥 FOOTER (Selalu letakkan di bawah body tag pembuka) 🔥 --}}
    <footer>
        <table class="footer-table" style="border:none;">
            <tr>
                <td style="border:none; padding:0; text-align:left;">
                    Printed on: {{ date('d-M-Y H:i') }} WIB &nbsp;|&nbsp; Ref: {{ $bill->bill_number ?? 'BILL/OPX/DM/2026/08/0001' }}
                </td>
                <td style="border:none; padding:0; text-align:right;">
                    Page <span class="pagenum"></span>
                </td>
            </tr>
        </table>
    </footer>

    {{-- KOP DOKUMEN --}}
    <div class="company-name">DAMA</div>
    <div class="doc-title">Bank Payment Request Form</div>

    {{-- TABEL INFORMASI --}}
    <table class="info-table">
        <tr>
            <td class="info-label">Requester</td><td class="info-colon">:</td><td class="info-value">{{ $bill->requester_name ?? 'Super Administrator' }}</td>
            <td class="info-label">Title</td><td class="info-colon">:</td><td class="info-value">{{ $bill->title ?? 'Tagihan Opex - CV Pertiwi' }}</td>
        </tr>
        <tr>
            <td class="info-label">Department</td><td class="info-colon">:</td><td class="info-value">{{ $bill->department ?? 'IT' }}</td>
            <td class="info-label">Bill Ref.</td><td class="info-colon">:</td><td class="info-value">{{ $bill->bill_number ?? 'BILL/OPX/DM/2026/08/0001' }}</td>
        </tr>
        <tr>
            <td class="info-label">Request Date</td><td class="info-colon">:</td><td class="info-value">{{ \Carbon\Carbon::parse($bill->request_date ?? now())->format('d-M-y') }}</td>
            <td class="info-label">Payment Due Date</td><td class="info-colon">:</td><td class="info-value">{{ \Carbon\Carbon::parse($bill->due_date ?? now())->format('d-M-y') }}</td>
        </tr>
    </table>

    {{-- TABEL UTAMA RINCIAN --}}
    <table class="item-table">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="15%">Invoices No.</th>
                <th width="35%">Description</th>
                <th width="15%">Reference</th>
                <th width="15%">Total Amount (Rp)</th>
                <th width="15%">Account No</th>
            </tr>
        </thead>
        <tbody>
            {{-- LOOPING DATA ITEM DI SINI JIKA ADA BANYAK BARIS --}}
            {{-- @foreach($bill->items as $index => $item) --}}
            <tr>
                <td class="text-center">1</td>
                <td class="text-center">2312313</td>
                <td>Maintenance Server Tahunan Ke-17</td>
                <td class="text-center"></td>
                <td>
                    {{-- Trik Anti-Pecah DomPDF untuk "Rp" rata kiri, Angka rata kanan --}}
                    <table class="currency-table" width="100%">
                        <tr>
                            <td style="text-align:left; width: 20%;">Rp</td>
                            <td style="text-align:right;">1.850.000</td>
                        </tr>
                    </table>
                </td>
                <td class="text-center"></td>
            </tr>
            {{-- @endforeach --}}
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="text-center fw-bold">Total Amount</td>
                <td class="fw-bold">
                    <table class="currency-table" width="100%">
                        <tr>
                            <td style="text-align:left; width: 20%;">Rp</td>
                            <td style="text-align:right;">1.850.000</td>
                        </tr>
                    </table>
                </td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    {{-- TABEL TANDA TANGAN --}}
    <table class="sig-table">
        <tr>
            <td>
                Prepared by :
                <div class="sig-name">{{ $bill->requester_name ?? 'Super Administrator' }}</div>
            </td>
            <td>
                Approved by :
                <div class="sig-name">{{ $bill->approver_name ?? 'Super Administrator' }}</div>
            </td>
        </tr>
    </table>

</body>
</html>