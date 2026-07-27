<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Tagihan Vendor - {{ $invoice->invoice_number }}</title>
    <style>
        /* CSS STRUKTURAL KHUSUS ENGINE PDF (ANTI-BLANK & ANTI-STRETCH) */
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 12px;
            color: #333;
            line-height: 1.4;
            padding: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-title {
            font-size: 22px;
            font-weight: bold;
            color: #0d6efd;
            text-align: right;
        }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .text-center { text-align: center; }
        .font-weight-bold { font-weight: bold; }

        .mt-10 { margin-top: 10px; }
        .mt-20 { margin-top: 20px; }
        .mt-30 { margin-top: 30px; }
        .mb-10 { margin-bottom: 10px; }

        /* Tabel Informasi Header */
        .info-table td {
            padding: 4px 0;
            vertical-align: top;
        }

        /* Tabel Rincian Utama & Dokumentasi */
        .border-table th, .border-table td {
            border: 1px solid #ced4da;
            padding: 8px;
            vertical-align: middle;
        }
        .border-table th {
            background-color: #f4f6f9;
            color: #333;
            text-transform: uppercase;
            font-size: 11px;
            font-weight: bold;
        }

        /* Tabel Finansial */
        .total-table td {
            padding: 5px 8px;
        }
        .grand-total {
            font-size: 13px;
            font-weight: bold;
            background-color: #f4f6f9;
            border-top: 1px solid #333;
            border-bottom: 1px solid #333;
        }

        /* Gaya Stempel Watermark Lunas */
        .watermark {
            position: absolute;
            top: 30%;
            left: 15%;
            font-size: 75px;
            color: rgba(40, 167, 69, 0.08);
            transform: rotate(-25deg);
            z-index: -1;
            text-transform: uppercase;
            letter-spacing: 12px;
            font-weight: bold;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            background-color: #e9ecef;
            border-radius: 4px;
            font-size: 10px;
            text-transform: uppercase;
            border: 1px solid #ced4da;
            font-weight: bold;
            color: #495057;
        }
    </style>
</head>
<body>

    @php
        // Kalkulasi Logika Finansial & Status Pembayaran
        $totalPaid = $invoice->payments->sum('amount');
        $sisaTagihan = $invoice->grand_total - $totalPaid;
        $statusSlug = strtolower(optional($invoice->status)->slug ?? 'draft');
        $isPaid = $sisaTagihan <= 0 || $statusSlug === 'paid';

        // Kolektor Data Dokumen GR Pendukung dari Item
        $compiledGoodsReceipts = $invoice->items->map(function($item) {
            return optional($item->goodsReceiptItem)->goodsReceipt
                ?? optional($item->goodsReceiptItem)->goods_receipt;
        })->filter()->unique('id');
    @endphp

    {{-- WATERMARK DI BELAKANG DOKUMEN JIKA SUDAH LUNAS --}}
    @if($isPaid)
        <div class="watermark">PAID / LUNAS</div>
    @endif

    <table>
        <tr>
            <td width="60%">
                <h2 style="margin:0; text-transform: uppercase; letter-spacing: 0.5px; color: #212529;">{{ $invoice->company->name ?? 'PERUM WIJAYANTI HASTUTI' }}</h2>
                <p style="margin:5px 0 0 0; color: #6c757d; font-size: 11px;">{{ $invoice->company->address ?? 'Kpg. Dahlia No. 822, Bima 54712, Banten' }}</p>
            </td>
            <td width="40%" class="text-right" style="vertical-align: top;">
                <div class="header-title">ACCOUNT PAYABLE</div>
                <div class="status-badge" style="margin-top: 5px;">DOKUMEN INTERNAL FINANCE</div>
            </td>
        </tr>
    </table>

    <hr style="border: 0.5px solid #dee2e6; margin: 15px 0;">

    <table>
        <tr>
            <td width="50%" style="vertical-align: top; padding-right: 15px;">
                <table class="info-table">
                    <tr><td width="40%" style="color:#6c757d;">No. Internal (A/P)</td><td width="5%">:</td><td><strong>{{ $invoice->invoice_number }}</strong></td></tr>
                    <tr><td style="color:#6c757d;">No. Faktur Vendor</td><td>:</td><td>{{ $invoice->vendor_invoice_number ?? '-' }}</td></tr>
                    <tr><td style="color:#6c757d;">Tgl. Jatuh Tempo</td><td>:</td><td style="color: #dc3545; font-weight: bold;">{{ \Carbon\Carbon::parse($invoice->due_date)->format('d F Y') }}</td></tr>
                </table>
            </td>
            <td width="50%" style="vertical-align: top; padding-left: 15px;">
                <table class="info-table">
                    <tr><td width="35%" style="color:#6c757d;">Referensi PO</td><td width="5%">:</td><td><strong>{{ optional($invoice->purchaseOrder)->po_number ?? '-' }}</strong></td></tr>
                    <tr><td style="color:#6c757d;">Referensi GR</td><td>:</td><td><strong>{{ $compiledGoodsReceipts->count() > 1 ? 'GABUNGAN (BULK)' : (optional($invoice->goodsReceipt)->gr_number ?? 'GABUNGAN (BULK)') }}</strong></td></tr>
                    <tr><td style="color:#6c757d;">Mata Uang</td><td>:</td><td>IDR</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="mt-20">
        <span style="text-transform: uppercase; font-size: 10px; font-weight: bold; color: #6c757d; letter-spacing: 0.5px;">INFORMASI VENDOR (PENAGIH):</span>
        <div style="border: 1px solid #dee2e6; padding: 10px; margin-top: 4px; background-color: #fafafa; border-radius: 4px;">
            <strong style="font-size: 13px; color: #212529;">{{ optional($invoice->vendor)->name ?? 'Vendor Tidak Terdaftar' }}</strong><br>
            <span style="color: #495057; font-size: 11px;">{{ optional($invoice->vendor)->address ?? '-' }}</span>
        </div>
    </div>

    <div class="mt-20">
        <span style="text-transform: uppercase; font-size: 10px; font-weight: bold; color: #6c757d; letter-spacing: 0.5px;">RINCIAN BARANG DITAGIH:</span>
        <table class="mt-10 border-table">
            <thead>
                <tr>
                    <th width="5%" class="text-center">No</th>
                    <th width="45%" class="text-left">Nama Barang / Jasa</th>
                    <th width="15%" class="text-center">Qty</th>
                    <th width="15%" class="text-right">Harga Satuan</th>
                    <th width="20%" class="text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
            @foreach($invoice->items as $index => $item)
            @php
                // 1. 🔥 BYPASS RELASI: Cari Langsung Pakai ID (Anti-Gagal) 🔥
                $grItem = \App\Models\GoodsReceiptItem::find($item->goods_receipt_item_id);
                $poItem = $grItem ? \App\Models\PurchaseOrderItem::find($grItem->purchase_order_item_id) : null;

                // 2. 🔥 LOGIKA NAMA SPESIFIK & MASTER 🔥
                $masterItem = $item->item;
                $masterName = optional($masterItem)->name ?? '-';

                // Ambil nama alias dari GR, jika tidak ada, ambil dari PO, jika tidak ada, ambil Master
                $specificName = optional($grItem)->item_name ?? optional($poItem)->item_name ?? $masterName;
            @endphp
            <tr>
                <td style="text-align: center;">{{ $index + 1 }}</td>
                <td>
                    {{-- 🔥 CETAK NAMA BERSUSUN DI PDF 🔥 --}}
                    <strong style="text-transform: uppercase;">{{ $specificName }}</strong><br>

                    @if(strtolower(trim($specificName)) !== strtolower(trim($masterName)))
                        <span style="font-size: 7.5pt; color: #555;">(Master: {{ $masterName }})</span><br>
                    @endif
                    <span style="font-size: 8pt; color: #888;">{{ optional($masterItem)->code ?? '-' }}</span>
                </td>
                <td style="text-align: center;">
                    <strong style="color: #0275d8; font-size: 10pt;">{{ (float) $item->qty_invoiced }}</strong>
                    <span style="font-size: 8pt; text-transform: uppercase;">{{ optional($poItem)->uom ?? 'PCS' }}</span>
                </td>
                <td style="text-align: right;">
                    {{ number_format($item->price, 0, ',', '.') }}
                </td>
                <td style="text-align: right;">
                    <strong>{{ number_format($item->subtotal, 0, ',', '.') }}</strong>
                </td>
            </tr>
            @endforeach
        </tbody>
        </table>
    </div>

    <table class="mt-10" style="page-break-inside: avoid;">
        <tr>
            <td width="45%"></td>
            <td width="55%">
                <table class="total-table">
                    <tr>
                        <td width="65%" class="text-right" style="color:#6c757d; font-size: 11px;">Subtotal Dasar :</td>
                        <td width="35%" class="text-right" style="font-weight: 500;">Rp {{ number_format($invoice->subtotal, 0, ',', '.') }}</td>
                    </tr>
                    @if($invoice->item_discount_total > 0)
                    <tr><td class="text-right" style="color:#6c757d; font-size: 11px;">Total Diskon Barang (-) :</td><td class="text-right" style="color:#dc3545;">- Rp {{ number_format($invoice->item_discount_total, 0, ',', '.') }}</td></tr>
                    @endif
                    @if($invoice->global_discount_total > 0)
                    <tr><td class="text-right" style="color:#6c757d; font-size: 11px;">Diskon Global PO (-) :</td><td class="text-right" style="color:#dc3545;">- Rp {{ number_format($invoice->global_discount_total, 0, ',', '.') }}</td></tr>
                    @endif
                    @if($invoice->extra_discount_total > 0)
                    <tr><td class="text-right" style="color:#6c757d; font-size: 11px;">Potongan Tambahan (-) :</td><td class="text-right" style="color:#dc3545;">- Rp {{ number_format($invoice->extra_discount_total, 0, ',', '.') }}</td></tr>
                    @endif
                    @if($invoice->tax_amount > 0)
                    <tr><td class="text-right" style="color:#6c757d; font-size: 11px;">Pajak (PPN) (+) :</td><td class="text-right" style="color: #0dcaf0;">+ Rp {{ number_format($invoice->tax_amount, 0, ',', '.') }}</td></tr>
                    @endif
                    @if($invoice->charge_total > 0)
                    <tr><td class="text-right" style="color:#6c757d; font-size: 11px;">Biaya Tambahan PO (+) :</td><td class="text-right" style="color: #ffc107;">+ Rp {{ number_format($invoice->charge_total, 0, ',', '.') }}</td></tr>
                    @endif
                    <tr>
                        <td class="text-right grand-total">GRAND TOTAL TAGIHAN :</td>
                        <td class="text-right grand-total" style="color:#0d6efd;">Rp {{ number_format($invoice->grand_total, 0, ',', '.') }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @if($compiledGoodsReceipts->count() > 0)
    <div class="mt-20" style="page-break-inside: avoid;">
        <span style="text-transform: uppercase; font-size: 10px; font-weight: bold; color: #0d6efd; letter-spacing: 0.5px;">DAFTAR REFERENSI SURAT JALAN (GOODS RECEIPT):</span>
        <table class="mt-10 border-table" style="border: 1px solid #ced4da;">
            <thead>
                <tr>
                    <th width="10%" class="text-center" style="background-color: #f8f9fa;">No</th>
                    <th width="45%" class="text-left" style="background-color: #f8f9fa;">No. Surat Jalan (GR)</th>
                    <th width="30%" class="text-center" style="background-color: #f8f9fa;">Tanggal Diterima Gudang</th>
                    <th width="15%" class="text-center" style="background-color: #f8f9fa;">Status GR</th>
                </tr>
            </thead>
            <tbody>
                @foreach($compiledGoodsReceipts as $index => $gr)
                <tr>
                    <td class="text-center" style="color: #6c757d;">{{ $index + 1 }}</td>
                    <td class="text-left font-weight-bold" style="color: #333;">
                        {{ $gr->gr_number }}
                    </td>
                    <td class="text-center" style="color: #495057;">
                        {{ $gr->received_date ? \Carbon\Carbon::parse($gr->received_date)->format('d M Y') : \Carbon\Carbon::parse($gr->created_at)->format('d M Y') }}
                    </td>
                    <td class="text-center">
                        <span style="color: #198754; font-weight: bold; font-size: 10px;">VERIFIED</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if($totalPaid > 0)
    <div class="mt-20" style="page-break-inside: avoid;">
        <span style="text-transform: uppercase; font-size: 10px; font-weight: bold; color: #198754; letter-spacing: 0.5px;">RIWAYAT PEMBAYARAN KELUAR (TERMIN/LUNAS):</span>
        <table class="mt-10 border-table" style="border: 1px solid #198754;">
            <thead>
                <tr>
                    <th width="20%" class="text-center" style="background-color: #e8f5e9; color: #198754; border-color:#198754;">Tgl Bayar</th>
                    <th width="35%" class="text-center" style="background-color: #e8f5e9; color: #198754; border-color:#198754;">Bank & No. Referensi</th>
                    <th width="20%" class="text-center" style="background-color: #e8f5e9; color: #198754; border-color:#198754;">Metode</th>
                    <th width="25%" class="text-right" style="background-color: #e8f5e9; color: #198754; border-color:#198754;">Nominal Bayar</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->payments as $pay)
                <tr>
                    <td class="text-center" style="border-color:#198754;">{{ \Carbon\Carbon::parse($pay->payment_date)->format('d M Y') }}</td>
                    <td class="text-center" style="border-color:#198754; color: #495057;">
                        {{ $pay->bank_name ?? '-' }} <br>
                        <span style="font-size:9px; color:#6c757d;">Ref: {{ $pay->reference_number ?? '-' }}</span>
                    </td>
                    <td class="text-center" style="text-transform: uppercase; border-color:#198754;">{{ strtoupper($pay->payment_method) }}</td>
                    <td class="text-right font-weight-bold" style="color:#198754; border-color:#198754;">Rp {{ number_format($pay->amount, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="text-right font-weight-bold" style="border-color:#198754; padding:6px; background-color: #f4f6f9;">TOTAL DIBAYARKAN :</td>
                    <td class="text-right font-weight-bold" style="color:#198754; border-color:#198754; padding:6px; background-color: #f4f6f9;">Rp {{ number_format($totalPaid, 0, ',', '.') }}</td>
                </tr>
                @if(!$isPaid)
                <tr>
                    <td colspan="3" class="text-right font-weight-bold" style="color:#dc3545; border-color:#198754; padding:6px;">SISA KEKURANGAN :</td>
                    <td class="text-right font-weight-bold" style="color:#dc3545; border-color:#198754; padding:6px;">Rp {{ number_format($sisaTagihan, 0, ',', '.') }}</td>
                </tr>
                @endif
            </tfoot>
        </table>
    </div>
    @endif

    <table style="width: 100%; margin-top: 50px; border-top: 1px dashed #ced4da; padding-top: 12px; page-break-inside: avoid;">
        <tr>
            <td style="color: #6c757d; font-size: 11px; text-align: center; line-height: 1.6;">
                <span style="color: #198754; font-weight: bold; letter-spacing: 0.5px;">✓ VERIFIKASI SISTEM OTOMATIS (DISETUJUI SECARA ELEKTRONIK)</span><br>
                Dokumen kewajiban Account Payable ini dinyatakan sah dan valid di dalam basis data ERP Keuangan.<br>
                Seluruh riwayat pembuatan dan otorisasi *Posting* tersimpan permanen tanpa memerlukan tanda tangan basah.<br>
                <span style="font-size: 10px; color: #adb5bd; margin-top: 5px; display: block;">Dicetak Oleh: {{ optional($invoice->creator)->name ?? 'System Administrator' }} | Waktu Cetak: {{ \Carbon\Carbon::now()->format('d M Y H:i:s') }} WIB</span>
            </td>
        </tr>
    </table>




</body>
</html>
