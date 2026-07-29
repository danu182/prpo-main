<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Bank Payment Request - {{ $po->po_number }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 10pt; color: #000; margin: 0; padding: 15px; }
        .company-name { font-size: 16pt; font-weight: bold; margin: 0; text-transform: uppercase; }
        .doc-title { font-size: 12pt; margin: 5px 0 15px 0; }
        table.bordered { width: 100%; border-collapse: collapse; margin-bottom: -1px; }
        table.bordered th, table.bordered td { border: 1px solid #000; padding: 6px 8px; vertical-align: top; }
        table.info-table { width: 100%; border-collapse: collapse; }
        table.info-table td { border: none; padding: 2px 0; vertical-align: top; }
        table.main th { text-align: center; font-weight: bold; background-color: #fff; }
        table.main td { vertical-align: middle; }
        .desc-cell p { margin: 0; padding: 0; line-height: 1.2; }
        table.currency { width: 100%; border-collapse: collapse; border: none; margin: 0; padding: 0; }
        table.currency td { border: none; padding: 0; margin: 0; background: transparent !important; }
        table.signature { margin-top: -1px; }
        table.signature td { height: 110px; position: relative; }
        .sign-title { position: absolute; top: 8px; left: 8px; font-size: 9.5pt; }
        .sign-name { position: absolute; bottom: 10px; left: 0; right: 0; text-align: center; font-weight: bold; font-size: 10pt; }
        .sign-status { position: absolute; top: 40px; left: 0; right: 0; text-align: center; font-size: 8pt; font-weight: bold; }
        .watermark { position: fixed; top: 30%; left: 5%; width: 90%; text-align: center; font-size: 80pt; font-weight: bold; text-transform: uppercase; color: rgba(255, 0, 0, 0.15); transform: rotate(-45deg); z-index: -1000; }
        .watermark-paid { color: rgba(0, 128, 0, 0.15); }
    </style>
</head>
<body>

    @php
        $statusSlug = strtolower(optional($po->status)->slug ?? $po->status);
    @endphp

    @if(in_array($statusSlug, ['rejected', 'canceled', 'cancelled']))
        <div class="watermark">DIBATALKAN</div>
    @elseif(in_array($statusSlug, ['paid', 'completed']))
        <div class="watermark watermark-paid">LUNAS / PAID</div>
    @endif

    {{-- KOP SURAT --}}
    @php
        $companyName = optional($po->company)->name ?? optional(optional($po->purchaseRequest)->company)->name ?? 'PT. Kantor Pusat Internal';
    @endphp
    <div class="company-name">{{ $companyName }}</div>
    <div class="doc-title">Bank Payment Request Form</div>

    {{-- KOTAK INFORMASI ATAS --}}
    <table class="bordered">
        <tr>
            <td width="50%">
                <table class="info-table">
                    <tr><td style="width: 100px;">Requester</td><td>: {{ $po->user->name ?? 'Sistem' }}</td></tr>
                    <tr><td>Department</td><td>: {{ optional($po->user->department)->name ?? 'Purchasing' }}</td></tr>
                    <tr><td>Request Date</td><td>: {{ date('d-M-y', strtotime($po->po_date ?? $po->created_at)) }}</td></tr>
                </table>
            </td>
            <td width="50%">
                <table class="info-table">
                    <tr><td style="width: 130px;">Title</td><td>: Pembayaran PO - {{ optional($po->vendor)->name ?? $po->vendor_name }}</td></tr>
                    <tr><td>Bill Ref.</td><td>: {{ $po->po_number }}</td></tr>
                    <tr><td>Payment Due Date</td><td>: {{ $po->delivery_date ? date('d-M-y', strtotime($po->delivery_date)) : '-' }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Failsafe Accumulators untuk Grand Total PDF --}}
    @php
        $calcSubtotalGross = 0;
        $calcTotalDiscount = 0;
        $calcTotalTax = 0;
    @endphp

    {{-- TABEL ITEM (TENGAH) --}}
    <table class="bordered main">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="15%">Invoices No.</th>
                <th width="35%">Description</th>
                <th width="15%">Reference</th>
                <th width="20%">Total Amount ({{ $po->currency ?? 'IDR' }})</th>
                <th width="10%">Account No</th>
            </tr>
        </thead>
        <tbody>
                            @php
                                $calcSubtotalGross = 0;
                            @endphp

                            {{-- 1. LOOPING ITEM BARANG --}}
                            @foreach($po->items as $index => $item)
                                @php
                                    // 🔥 Mengamankan angka QTY dari semua kemungkinan nama kolom database 🔥
                                    $qty = (float) ($item->qty ?? $item->qty_ordered ?? $item->quantity ?? 0);
                                    if ($qty <= 0) $qty = 1; // Failsafe agar tidak nol

                                    $hargaSatuan = (float) ($item->unit_price ?? $item->price ?? 0);
                                    $subtotalDB = (float) ($item->subtotal ?? $item->total_price ?? ($qty * $hargaSatuan));

                                    if ($hargaSatuan == 0 && $subtotalDB > 0) {
                                        $hargaSatuan = $subtotalDB / $qty;
                                    }

                                    $calcSubtotalGross += $subtotalDB;

                                    // Mengamankan penamaan UOM (Satuan)
                                    $baseUomName = optional(optional($item->item)->uom)->name ?? 'Unit';
                                    $uomStr = is_string($item->uom) ? $item->uom : (isset($item->uom->name) ? $item->uom->name : $baseUomName);
                                    if (is_string($uomStr) && str_contains($uomStr, '{')) {
                                        $uomDec = json_decode($uomStr);
                                        $uomStr = $uomDec->name ?? $uomDec->code ?? $baseUomName;
                                    }
                                @endphp
                                <tr>
                                    <td style="text-align: center; vertical-align: top; padding-top: 10px;">{{ $index + 1 }}</td>
                                    <td style="text-align: center; vertical-align: top; padding-top: 10px;">-</td>
                                    <td style="padding-top: 10px; padding-bottom: 10px;">
                                        <div style="font-weight: bold; font-size: 13px; color: #000;">
                                            {{ $item->item_name ?? optional($item->item)->name }}
                                        </div>

                                        {{-- 🔥 QTY & UOM DIGABUNG DENGAN HARGA SEBAGAI RUMUS PERKALIAN 🔥 --}}
                                        <div style="font-size: 12px; color: #444; margin-top: 5px;">
                                            <strong style="color: #0d6efd;">{{ $qty }} {{ strtoupper($uomStr) }}</strong> &nbsp;x&nbsp; Rp {{ number_format($hargaSatuan, 0, ',', '.') }}
                                        </div>
                                    </td>
                                    <td></td>
                                    <td style="text-align: right; vertical-align: bottom; padding-bottom: 10px; font-weight: bold;">
                                        Rp {{ number_format($subtotalDB, 0, ',', '.') }}
                                    </td>
                                    <td></td>
                                </tr>
                            @endforeach

                            @php
                                // Kalkulasi Variabel Keuangan
                                $sumSubtotal = (float)($po->subtotal ?? 0) > 0 ? (float)$po->subtotal : $calcSubtotalGross;
                                $sumDiscount = (float)($po->discount_total ?? $po->discount_amount ?? 0);
                                $sumTax = (float)($po->tax_total ?? $po->tax_amount ?? 0);
                                $sumGrandTotal = (float)($po->grand_total ?? 0);
                            @endphp

                            {{-- 2. SUBTOTAL KOTOR (DPP) --}}
                            <tr>
                                <td colspan="4" style="text-align: right; font-weight: bold; padding-top: 8px;">Subtotal Gross (DPP)</td>
                                <td style="text-align: right; font-weight: bold; padding-top: 8px;">Rp {{ number_format($sumSubtotal, 0, ',', '.') }}</td>
                                <td></td>
                            </tr>

                            {{-- 3. DISKON KOMERSIAL --}}
                            @if($sumDiscount > 0)
                                <tr>
                                    <td colspan="4" style="text-align: right; color: red;">Diskon Komersial</td>
                                    <td style="text-align: right; color: red;">- Rp {{ number_format($sumDiscount, 0, ',', '.') }}</td>
                                    <td></td>
                                </tr>
                            @endif

                            {{-- 4. PAJAK (VAT / PPN) --}}
                            @if($sumTax > 0)
                                <tr>
                                    <td colspan="4" style="text-align: right;">Total Pajak (VAT / PPN)</td>
                                    <td style="text-align: right;">+ Rp {{ number_format($sumTax, 0, ',', '.') }}</td>
                                    <td></td>
                                </tr>
                            @endif

                            {{-- 5. RINCIAN BIAYA TAMBAHAN --}}
                            @if(isset($charges) && count($charges) > 0)
                                @foreach($charges as $charge)
                                    <tr>
                                        <td colspan="4" style="text-align: right; font-size: 11px; color: #555;">↳ Biaya: {{ $charge->name ?? 'Biaya Lainnya' }}</td>
                                        <td style="text-align: right; font-size: 11px;">+ Rp {{ number_format($charge->amount, 0, ',', '.') }}</td>
                                        <td></td>
                                    </tr>
                                @endforeach
                            @endif

                            {{-- 6. RINCIAN POTONGAN / DENDA --}}
                            @if(isset($extraDiscounts) && count($extraDiscounts) > 0)
                                @foreach($extraDiscounts as $disc)
                                    <tr>
                                        <td colspan="4" style="text-align: right; font-size: 11px; color: red;">↳ Potongan: {{ $disc->name ?? 'Diskon Tambahan' }}</td>
                                        <td style="text-align: right; font-size: 11px; color: red;">- Rp {{ number_format($disc->amount, 0, ',', '.') }}</td>
                                        <td></td>
                                    </tr>
                                @endforeach
                            @endif

                            {{-- 7. GRAND TOTAL --}}
                            <tr>
                                <td colspan="4" style="text-align: right; font-weight: bold; font-size: 14px; padding-top: 10px;">GRAND TOTAL</td>
                                <td style="text-align: right; font-weight: bold; font-size: 14px; padding-top: 10px;">Rp {{ number_format($sumGrandTotal, 0, ',', '.') }}</td>
                                <td></td>
                            </tr>
                        </tbody>
    </table>

    {{-- KOTAK TANDA TANGAN (BAWAH) --}}
    @php
        $approvals = \App\Models\DocumentApproval::with('role')
            ->where('document_id', $po->id)
            ->where('document_type', get_class($po))
            ->orderBy('step_order', 'asc')
            ->get();

        $totalCols = 1 + $approvals->count();
    @endphp

    <table class="bordered signature">
        <tr>
            <td style="width: {{ 100 / ($totalCols > 0 ? $totalCols : 1) }}%;">
                <div class="sign-title">Prepared by :</div>
                <div class="sign-name">{{ $po->user->name ?? 'Tim Purchasing' }}</div>
            </td>

            @foreach($approvals as $idx => $approval)
            <td style="width: {{ 100 / $totalCols }}%;">
                <div class="sign-title">{{ $loop->last ? 'Approved by :' : 'Checked by :' }}</div>

                @if($approval->status == 'APPROVED')
                    <div class="sign-status" style="color: #198754;">
                        Telah Disetujui<br>{{ date('d/m/Y', strtotime($approval->approved_at)) }}
                    </div>
                @elseif($approval->status == 'REJECTED')
                    <div class="sign-status" style="color: #dc3545;">
                        Ditolak
                    </div>
                @endif

                <div class="sign-name">
                    @if($approval->status == 'APPROVED')
                        {{ \App\Models\User::find($approval->approved_by)->name ?? optional($approval->role)->name }}
                    @else
                        @php
                            $roleName = optional($approval->role)->name ?? 'Atasan';
                            $deptName = '';
                            if (is_null($approval->target_department_id)) {
                                $deptName = optional($po->user->department)->name ?? '';
                            } elseif ($approval->target_department_id !== 'all' && $approval->target_department_id != 0) {
                                $deptObj = \App\Models\Department::find($approval->target_department_id);
                                $deptName = $deptObj ? $deptObj->name : '';
                            }
                        @endphp
                        ({{ $roleName }}{{ $deptName ? ' - ' . $deptName : '' }})
                    @endif
                </div>
            </td>
            @endforeach
        </tr>
    </table>

    {{-- 🔥 TIMESTAMP WAKTU CETAK DOKUMEN 🔥 --}}
    <div style="margin-top: 15px; font-size: 8pt; color: #555; text-align: left; font-style: italic;">
        * Dokumen ini dicetak otomatis oleh sistem pada {{ \Carbon\Carbon::now()->translatedFormat('d F Y H:i:s') }} WIB
    </div>

    {{-- 🔥 LAMPIRAN GAMBAR (MUNCUL DI HALAMAN BARU) 🔥 --}}
    @php
        $imageAttachments = collect();

        // Ambil dari Header
        if(isset($po->attachments)) {
            foreach($po->attachments as $att) {
                $ext = strtolower(pathinfo($att->file_name ?? $att->file_path, PATHINFO_EXTENSION));
                if(in_array($ext, ['jpg', 'jpeg', 'png'])) $imageAttachments->push($att);
            }
        }

        // Ambil dari Item
        if(isset($po->items)) {
            foreach($po->items as $item) {
                if(isset($item->raw_attachments)) {
                    foreach($item->raw_attachments as $att) {
                        $ext = strtolower(pathinfo($att->file_name ?? $att->file_path, PATHINFO_EXTENSION));
                        if(in_array($ext, ['jpg', 'jpeg', 'png'])) $imageAttachments->push($att);
                    }
                }
            }
        }
    @endphp

    @if($imageAttachments->count() > 0)
        <div style="page-break-before: always;"></div>
        <h3 style="margin-bottom: 20px; font-family: Arial, sans-serif; color: #000;">Lampiran Dokumen Pendukung</h3>

        @foreach($imageAttachments as $img)
            <div style="margin-bottom: 20px; text-align: center; font-family: Arial, sans-serif;">
                <p style="font-size: 10pt; text-align: left;"><strong>Nama File:</strong> {{ $img->file_name ?? 'File' }}</p>
                <img src="{{ public_path('storage/' . $img->file_path) }}" style="max-width: 100%; max-height: 800px; border: 1px solid #000; padding: 5px;">
            </div>
        @endforeach
    @endif

</body>
</html>
