<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Tagihan Vendor - {{ $invoice->invoice_number }}</title>
    <style>
        /* CSS KHUSUS MESIN PDF (MENGHINDARI BOOTSTRAP MODERN) */
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 12px; color: #333; line-height: 1.4; }
        table { width: 100%; border-collapse: collapse; }

        .header-title { font-size: 22px; font-weight: bold; color: #0d6efd; text-align: right; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .text-center { text-align: center; }
        .font-weight-bold { font-weight: bold; }

        .mt-10 { margin-top: 10px; }
        .mt-20 { margin-top: 20px; }
        .mt-30 { margin-top: 30px; }
        .mb-10 { margin-bottom: 10px; }

        /* Tabel Informasi */
        .info-table td { padding: 4px 0; vertical-align: top; }

        /* Tabel Rincian & Tabel Border */
        .border-table th, .border-table td { border: 1px solid #ddd; padding: 8px; vertical-align: top; }
        .border-table th { background-color: #f4f6f9; color: #333; text-transform: uppercase; font-size: 11px;}

        /* Tabel Total */
        .total-table td { padding: 6px; }
        .grand-total { font-size: 14px; font-weight: bold; background-color: #f4f6f9; }

        /* Tanda Tangan */
        .signature-table td { text-align: center; vertical-align: bottom; height: 90px; padding-bottom: 5px; }
        .signature-line { border-bottom: 1px solid #333; margin: 0 30px; margin-bottom: 5px; }

        /* Watermark Lunas */
        .watermark { position: absolute; top: 35%; left: 15%; font-size: 80px; color: rgba(40,167,69,0.1); transform: rotate(-30deg); z-index: -1; text-transform: uppercase; letter-spacing: 15px; font-weight: bold;}

        .status-badge { display: inline-block; padding: 5px 12px; background-color: #e9ecef; border-radius: 4px; font-size: 10px; text-transform: uppercase; border: 1px solid #ced4da;}
    </style>
</head>
<body>
    @php
        $totalPaid = $invoice->payments->sum('amount');
        $sisaTagihan = $invoice->grand_total - $totalPaid;
        $statusSlug = strtolower(optional($invoice->status)->slug ?? 'draft');
        $isPaid = $sisaTagihan <= 0 || $statusSlug === 'paid';
    @endphp

    {{-- WATERMARK MUNCUL JIKA LUNAS --}}
    @if($isPaid)
        <div class="watermark">PAID / LUNAS</div>
    @endif

    <table>
        <tr>
            <td width="60%">
                <h2 style="margin:0; text-transform: uppercase;">{{ $invoice->company->name ?? 'PT NAMA PERUSAHAAN KITA' }}</h2>
                <p style="margin:5px 0 0 0; color: #666;">{{ $invoice->company->address ?? 'Alamat Perusahaan Internal / Kantor Pusat' }}</p>
            </td>
            <td width="40%" class="text-right">
                <div class="header-title">ACCOUNT PAYABLE</div>
                <div class="status-badge" style="margin-top: 5px;">Dokumen Internal Finance</div>
            </td>
        </tr>
    </table>

    <hr style="border: 0.5px solid #ddd; margin: 20px 0;">

    <table>
        <tr>
            <td width="50%" style="vertical-align: top; padding-right: 10px;">
                <table class="info-table">
                    <tr><td width="40%">No. Internal (A/P)</td><td width="5%">:</td><td><strong>{{ $invoice->invoice_number }}</strong></td></tr>
                    <tr><td>No. Faktur Vendor</td><td>:</td><td>{{ $invoice->vendor_invoice_number ?? '-' }}</td></tr>
                    <tr><td>Tgl. Jatuh Tempo</td><td>:</td><td style="color: #dc3545; font-weight: bold;">{{ \Carbon\Carbon::parse($invoice->due_date)->format('d F Y') }}</td></tr>
                </table>
            </td>
            <td width="50%" style="vertical-align: top; padding-left: 10px;">
                <table class="info-table">
                    <tr><td width="35%">Referensi PO</td><td width="5%">:</td><td><strong>{{ optional($invoice->purchaseOrder)->po_number ?? '-' }}</strong></td></tr>
                    <tr><td>Referensi GR</td><td>:</td><td>{{ optional($invoice->goodsReceipt)->gr_number ?? 'BULK / GABUNGAN' }}</td></tr>
                    <tr><td>Mata Uang</td><td>:</td><td>IDR</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="mt-20">
        <strong style="text-transform: uppercase; font-size: 11px; color: #666;">Informasi Vendor (Penagih):</strong>
        <div style="border: 1px solid #ddd; padding: 12px; margin-top: 5px; background: #fafafa; border-radius: 4px;">
            <strong style="font-size: 14px;">{{ optional($invoice->vendor)->name ?? 'Vendor Tidak Terdaftar' }}</strong><br>
            <span style="color: #666;">{{ optional($invoice->vendor)->address ?? '-' }}</span>
        </div>
    </div>

    <div class="mt-20">
        <strong style="text-transform: uppercase; font-size: 11px; color: #666;">Rincian Barang Ditagih:</strong>
        <table class="mt-10 border-table">
            <thead>
                <tr>
                    <th width="5%" class="text-center">No</th>
                    <th width="45%" class="text-left">Nama Barang / Jasa</th>
                    <th width="10%" class="text-center">Qty</th>
                    <th width="20%" class="text-right">Harga Satuan</th>
                    <th width="20%" class="text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoice->items ?? [] as $index => $item)
                @php
                    // 🔥 RADAR PENCARI UOM (FIXED UNTUK DATA JSON / RELASI) 🔥
                    $uomName = 'UNIT'; // Default

                    // Coba cari dari relasi Master Item -> Tabel UOM -> Code/Name
                    if ($item->item && $item->item->uom) {
                        // Jika relasi $item->uom menghasilkan objek/model, kita ambil field 'code' atau 'name'
                        $uomName = is_object($item->item->uom)
                                    ? ($item->item->uom->code ?? $item->item->uom->name ?? 'UNIT')
                                    : $item->item->uom; // Fallback jika ternyata string biasa
                    }
                    // Jika di atas gagal, coba cari dari PO Item
                    elseif (isset($item->goodsReceiptItem->purchaseOrderItem->uom)) {
                        $poUom = $item->goodsReceiptItem->purchaseOrderItem->uom;
                        $uomName = is_object($poUom) ? ($poUom->code ?? $poUom->name ?? 'UNIT') : $poUom;
                    }

                    // Pastikan yang tercetak hanyalah string, bukan array/json
                    if(is_array($uomName) || is_object($uomName)) {
                         $uomName = 'UNIT';
                    }
                @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>
                        <strong>{{ optional($item->item)->name ?? '-' }}</strong><br>
                        <span style="font-size: 10px; color: #666;">{{ optional($item->item)->code ?? '-' }}</span>
                    </td>
                    <td class="text-center font-weight-bold" style="color: #0d6efd; font-size: 13px;">
                        {{ (float) $item->qty_invoiced }}
                        <span style="font-size: 10px; color: #333; font-weight: normal; margin-left: 3px;">{{ strtoupper($uomName) }}</span>
                    </td>
                    <td class="text-right">
                        {{ number_format($item->price, 0, ',', '.') }}
                        @if($item->discount_amount > 0)
                            <br><span style="font-size: 10px; color: #dc3545;">Disc: -{{ number_format($item->discount_amount, 0, ',', '.') }}</span>
                        @endif
                    </td>
                    <td class="text-right font-weight-bold">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center" style="color: #666;">Data item tidak ditemukan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <table class="mt-10" style="page-break-inside: avoid;">
        <tr>
            <td width="50%"></td>
            <td width="50%">
                <table class="total-table">
                    <tr>
                        <td width="60%" class="text-right" style="color:#666;">Subtotal Dasar :</td>
                        <td width="40%" class="text-right">Rp {{ number_format($invoice->subtotal, 0, ',', '.') }}</td>
                    </tr>
                    @if($invoice->item_discount_total > 0)
                    <tr><td class="text-right" style="color:#666;">Total Diskon Barang (-) :</td><td class="text-right" style="color:#dc3545;">- Rp {{ number_format($invoice->item_discount_total, 0, ',', '.') }}</td></tr>
                    @endif
                    @if($invoice->global_discount_total > 0)
                    <tr><td class="text-right" style="color:#666;">Diskon Global PO (-) :</td><td class="text-right" style="color:#dc3545;">- Rp {{ number_format($invoice->global_discount_total, 0, ',', '.') }}</td></tr>
                    @endif
                    @if($invoice->extra_discount_total > 0)
                    <tr><td class="text-right" style="color:#666;">Potongan Tambahan (-) :</td><td class="text-right" style="color:#dc3545;">- Rp {{ number_format($invoice->extra_discount_total, 0, ',', '.') }}</td></tr>
                    @endif
                    @if($invoice->tax_amount > 0)
                    <tr><td class="text-right" style="color:#666;">Pajak (PPN) (+) :</td><td class="text-right">Rp {{ number_format($invoice->tax_amount, 0, ',', '.') }}</td></tr>
                    @endif
                    @if($invoice->charge_total > 0)
                    <tr><td class="text-right" style="color:#666;">Biaya Tambahan PO (+) :</td><td class="text-right">Rp {{ number_format($invoice->charge_total, 0, ',', '.') }}</td></tr>
                    @endif
                    <tr>
                        <td class="text-right grand-total">GRAND TOTAL TAGIHAN :</td>
                        <td class="text-right grand-total" style="color:#0d6efd;">Rp {{ number_format($invoice->grand_total, 0, ',', '.') }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @if($totalPaid > 0)
    <div class="mt-20" style="page-break-inside: avoid;">
        <strong style="text-transform: uppercase; font-size: 11px; color: #198754;">Riwayat Pembayaran Keluar (Termin/Lunas):</strong>
        <table class="mt-10 border-table" style="border: 1px solid #198754;">
            <thead>
                <tr>
                    <th width="20%" class="text-center" style="background-color: #e8f5e9; color: #198754; border-color:#198754;">Tgl Bayar</th>
                    <th width="30%" class="text-center" style="background-color: #e8f5e9; color: #198754; border-color:#198754;">No. Referensi (Bank)</th>
                    <th width="20%" class="text-center" style="background-color: #e8f5e9; color: #198754; border-color:#198754;">Metode</th>
                    <th width="30%" class="text-right" style="background-color: #e8f5e9; color: #198754; border-color:#198754;">Nominal Bayar</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->payments as $pay)
                <tr>
                    <td class="text-center" style="border-color:#198754;">{{ \Carbon\Carbon::parse($pay->payment_date)->format('d M Y') }}</td>
                    <td class="text-center" style="border-color:#198754;">{{ $pay->bank_name ?? '-' }} <br> <span style="font-size:9px;color:#666;">Ref: {{ $pay->reference_number ?? '-' }}</span></td>
                    <td class="text-center" style="text-transform: uppercase; border-color:#198754;">{{ $pay->payment_method }}</td>
                    <td class="text-right font-weight-bold" style="color:#198754; border-color:#198754;">Rp {{ number_format($pay->amount, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="text-right font-weight-bold" style="border-color:#198754; padding:8px;">TOTAL DIBAYARKAN :</td>
                    <td class="text-right font-weight-bold" style="color:#198754; border-color:#198754; padding:8px;">Rp {{ number_format($totalPaid, 0, ',', '.') }}</td>
                </tr>
                @if(!$isPaid)
                <tr>
                    <td colspan="3" class="text-right font-weight-bold" style="color:#dc3545; border-color:#198754; padding:8px;">SISA KEKURANGAN :</td>
                    <td class="text-right font-weight-bold" style="color:#dc3545; border-color:#198754; padding:8px;">Rp {{ number_format($sisaTagihan, 0, ',', '.') }}</td>
                </tr>
                @endif
            </tfoot>
        </table>
    </div>
    @endif

    <table class="signature-table mt-30" style="page-break-inside: avoid;">
        <tr>
            <td width="33%">
                <div class="signature-line"></div>
                <div style="font-size:11px; color:#666;">Dibuat Oleh (A/P Admin)</div>
                <div class="mt-10 font-weight-bold">{{ optional($invoice->creator)->name ?? 'Admin A/P' }}</div>
            </td>
            <td width="33%">
                <div class="signature-line"></div>
                <div style="font-size:11px; color:#666;">Disetujui Oleh (Finance)</div>
                <div class="mt-10 font-weight-bold">Manager Keuangan</div>
            </td>
            <td width="33%">
                <div class="signature-line"></div>
                <div style="font-size:11px; color:#666;">Diterima Oleh (Kasir)</div>
                <div class="mt-10 font-weight-bold">Kasir / Teller</div>
            </td>
        </tr>
    </table>

</body>
</html>
