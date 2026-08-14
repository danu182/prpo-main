<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Bank Payment Request (Detail) - {{ $po->po_number }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 10pt; color: #000; margin: 0; padding: 15px; }
        .company-name { font-size: 16pt; font-weight: bold; margin: 0; text-transform: uppercase; }
        .doc-title { font-size: 12pt; margin: 5px 0 15px 0; }
        
        /* Pengaturan Tabel Utama */
        table.bordered { width: 100%; border-collapse: collapse; margin-bottom: -1px; table-layout: fixed; }
        table.bordered th, table.bordered td { border: 1px solid #000; padding: 6px 4px; vertical-align: middle; }
        
        table.info-table { width: 100%; border-collapse: collapse; }
        table.info-table td { border: none; padding: 2px 0; vertical-align: top; }
        
        table.main th { text-align: center; font-weight: bold; background-color: #fff; }
        table.main td { vertical-align: middle; }

        .watermark { position: fixed; top: 30%; left: 5%; width: 90%; text-align: center; font-size: 80pt; font-weight: bold; text-transform: uppercase; color: rgba(255, 0, 0, 0.15); transform: rotate(-45deg); z-index: -1000; }
        .watermark-paid { color: rgba(0, 128, 0, 0.15); }

        /* 🔥 PENGATURAN TANDA TANGAN & STEMPEL 🔥 */
        table.signature { width: 100%; border-collapse: collapse; margin-top: 40px; page-break-inside: avoid; }
        table.signature td { border: none; padding: 10px; text-align: center; vertical-align: top; }
        .sign-title { font-size: 10pt; font-weight: bold; margin-bottom: 20px; color: #333; }
        
        .sign-box { height: 75px; margin-bottom: 10px; display: flex; align-items: center; justify-content: center; }
        .sign-box img { max-height: 75px; max-width: 140px; object-fit: contain; }
        
        .stamp { display: inline-block; padding: 5px 12px; font-weight: bold; font-size: 11pt; letter-spacing: 1.5px; text-transform: uppercase; border: 2px solid; margin-top: 15px; }
        .stamp-issued { color: #198754; border-color: #198754; }
        .stamp-approved { color: #0d6efd; border-color: #0d6efd; }
        .stamp-rejected { color: #dc3545; border-color: #dc3545; }
        
        .sign-name { font-weight: bold; text-decoration: underline; font-size: 10pt; color: #000; }
        .sign-meta { font-size: 8.5pt; color: #333; margin-top: 3px; }
        .sign-dept { font-size: 8pt; color: #777; margin-top: 2px; }

        /* 🔥 UTILITY MATA UANG ANTI BERANTAKAN (INNER TABLE) 🔥 */
        .break-text { word-wrap: break-word; word-break: break-all; }
        table.amount-box { width: 100%; border: none !important; margin: 0; padding: 0; }
        table.amount-box td { border: none !important; padding: 0 !important; margin: 0 !important; background: transparent !important; vertical-align: middle; line-height: 1.2; }
        .curr-txt { text-align: left; font-size: 8.5pt; color: #666; white-space: nowrap; width: 1%; padding-right: 5px !important; }
        .curr-txt-red { text-align: left; font-size: 8.5pt; color: red; white-space: nowrap; width: 1%; padding-right: 5px !important; }
        .num-txt { text-align: right; font-size: 10pt; }
        .num-txt-red { text-align: right; font-size: 10pt; color: red; }
    </style>
</head>
<body>

    @php
        $statusSlug = strtolower(optional($po->status)->slug ?? $po->status);
        $currency = $po->currency ?? 'IDR';
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
    <table class="bordered" style="margin-bottom: 0; table-layout: auto;">
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
                    <tr><td>Payment Due Date</td><td>: {{ $po->due_date ? date('d-M-y', strtotime($po->due_date)) : ($po->delivery_date ? date('d-M-y', strtotime($po->delivery_date)) : '-') }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- TABEL ITEM DETAIL (FORMAT EXCEL AKUNTANSI) --}}
    <table class="bordered main">
        <thead>
            <tr>
                <th style="width: 4%;">No</th>
                <th style="width: 12%;">Invoices No.</th>
                <th style="width: 29%;">Description</th>
                <th style="width: 10%;">Reference</th>
                <th style="width: 15%;">Unit Price</th>
                <th style="width: 17%;">Total Amount</th>
                <th style="width: 13%;">Account No</th>
            </tr>
        </thead>
        <tbody>
            @foreach($po->items as $index => $item)
                @php
                    $qty = (float) ($item->qty ?? $item->qty_ordered ?? $item->quantity ?? 0);
                    if ($qty <= 0) $qty = 1;
                    $price = (float) ($item->unit_price ?? $item->price ?? 0);
                    $subtotalDB = (float) ($item->subtotal ?? $item->total_price ?? ($qty * $price));
                    $discAmt = (float) ($item->discount_amount ?? 0);
                    $taxAmt = (float) ($item->tax_amount ?? 0);

                    // Pembersihan string UOM agar rapi
                    $baseUomName = optional(optional($item->item)->uom)->name ?? 'PCS';
                    $uomStr = is_string($item->uom) ? $item->uom : (isset($item->uom->name) ? $item->uom->name : $baseUomName);
                    if (is_string($uomStr) && str_contains($uomStr, '{')) {
                        $uomDec = json_decode($uomStr);
                        $uomStr = $uomDec->name ?? $uomDec->code ?? $baseUomName;
                    }
                    $uomStr = preg_replace('/ \(Isi:.*\)/i', '', $uomStr);
                @endphp

                {{-- Baris Utama Item --}}
                <tr>
                    <td style="text-align: center;">{{ $index + 1 }}</td>
                    <td style="text-align: center; color: #0d6efd;" class="break-text">
                        @if($index === 0)
                            {{ !empty($po->invoice_number) ? wordwrap($po->invoice_number, 14, " ", true) : '-' }}
                        @endif
                    </td>
                    <td>
                        <strong style="font-size: 13px;">{{ $item->item_name ?? optional($item->item)->name }}</strong>
                        @if(!empty($item->description) && $item->description !== '-')
                            <br><span style="font-size: 10px; color: #555;">{!! strip_tags($item->description) !!}</span>
                        @endif
                    </td>
                    <td style="text-align: center;">{{ $qty }} {{ strtoupper($uomStr) }}</td>
                    
                    {{-- 🔥 Format Kotak Akuntansi 100% Lurus Kiri Kanan 🔥 --}}
                    <td>
                        <table class="amount-box">
                            <tr>
                                <td class="curr-txt">{{ $currency }}</td>
                                <td class="num-txt">{{ number_format($price, 0, ',', '.') }}</td>
                            </tr>
                        </table>
                    </td>
                    <td>
                        <table class="amount-box">
                            <tr>
                                <td class="curr-txt">{{ $currency }}</td>
                                <td class="num-txt">{{ number_format($qty * $price, 0, ',', '.') }}</td>
                            </tr>
                        </table>
                    </td>
                    
                    <td style="text-align: center; font-weight: bold; color: #198754;" class="break-text">
                        @if($index === 0)
                            {{ !empty($po->account_number) ? wordwrap($po->account_number, 12, " ", true) : '-' }}
                        @endif
                    </td>
                </tr>

                {{-- Baris Diskon per Item --}}
                @if($discAmt > 0)
                <tr>
                    <td style="border-top: none; border-bottom: none;"></td>
                    <td style="border-top: none; border-bottom: none;"></td>
                    <td style="color: red;">Diskon Item: {{ $item->item_name ?? optional($item->item)->name }}</td>
                    <td style="text-align: center;">1</td>
                    <td style="text-align: center;">-</td>
                    <td>
                        <table class="amount-box">
                            <tr>
                                <td class="curr-txt-red">{{ $currency }}</td>
                                <td class="num-txt-red">-{{ number_format($discAmt, 0, ',', '.') }}</td>
                            </tr>
                        </table>
                    </td>
                    <td style="border-top: none; border-bottom: none;"></td>
                </tr>
                @endif

                {{-- Baris Pajak per Item --}}
                @if($taxAmt > 0)
                <tr>
                    <td style="border-top: none; border-bottom: none;"></td>
                    <td style="border-top: none; border-bottom: none;"></td>
                    <td>Pajak Item (VAT/PPN)</td>
                    <td style="text-align: center;">1</td>
                    <td style="text-align: center;">-</td>
                    <td>
                        <table class="amount-box">
                            <tr>
                                <td class="curr-txt">{{ $currency }}</td>
                                <td class="num-txt">{{ number_format($taxAmt, 0, ',', '.') }}</td>
                            </tr>
                        </table>
                    </td>
                    <td style="border-top: none; border-bottom: none;"></td>
                </tr>
                @endif
            @endforeach

            {{-- Biaya Tambahan (Charges) --}}
            @if(isset($charges) && count($charges) > 0)
                @foreach($charges as $charge)
                <tr>
                    <td style="border-top: none; border-bottom: none;"></td>
                    <td style="border-top: none; border-bottom: none;"></td>
                    <td>{{ $charge->name ?? 'Biaya Tambahan' }}</td>
                    <td style="text-align: center;">1</td>
                    <td style="text-align: center;">-</td>
                    <td>
                        <table class="amount-box">
                            <tr>
                                <td class="curr-txt">{{ $currency }}</td>
                                <td class="num-txt">{{ number_format($charge->amount, 0, ',', '.') }}</td>
                            </tr>
                        </table>
                    </td>
                    <td style="border-top: none; border-bottom: none;"></td>
                </tr>
                @endforeach
            @endif

            {{-- Diskon Global Header --}}
            @php
                $sumItemDisc = $po->items->sum('discount_amount');
                $actualGlobalDisc = (float)($po->discount_total ?? 0) - $sumItemDisc;
            @endphp
            @if($actualGlobalDisc > 0)
            <tr>
                <td style="border-top: none; border-bottom: none;"></td>
                <td style="border-top: none; border-bottom: none;"></td>
                <td style="color: red;">Diskon Header (Global)</td>
                <td style="text-align: center;">1</td>
                <td style="text-align: center;">-</td>
                <td>
                    <table class="amount-box">
                        <tr>
                            <td class="curr-txt-red">{{ $currency }}</td>
                            <td class="num-txt-red">-{{ number_format($actualGlobalDisc, 0, ',', '.') }}</td>
                        </tr>
                    </table>
                </td>
                <td style="border-top: none; border-bottom: none;"></td>
            </tr>
            @endif

            {{-- Pajak Global Header --}}
            @php
                $sumItemTax = $po->items->sum('tax_amount');
                $actualGlobalTax = (float)($po->tax_total ?? 0) - $sumItemTax;
            @endphp
            @if($actualGlobalTax > 0)
            <tr>
                <td style="border-top: none; border-bottom: none;"></td>
                <td style="border-top: none; border-bottom: none;"></td>
                <td>Pajak Header (VAT/PPN)</td>
                <td style="text-align: center;">1</td>
                <td style="text-align: center;">-</td>
                <td>
                    <table class="amount-box">
                        <tr>
                            <td class="curr-txt">{{ $currency }}</td>
                            <td class="num-txt">{{ number_format($actualGlobalTax, 0, ',', '.') }}</td>
                        </tr>
                    </table>
                </td>
                <td style="border-top: none; border-bottom: none;"></td>
            </tr>
            @endif

            {{-- Potongan Tambahan (Vouchers) --}}
            @if(isset($extraDiscounts) && count($extraDiscounts) > 0)
                @foreach($extraDiscounts as $disc)
                <tr>
                    <td style="border-top: none; border-bottom: none;"></td>
                    <td style="border-top: none; border-bottom: none;"></td>
                    <td style="color: red;">{{ $disc->name ?? 'Potongan Tambahan' }}</td>
                    <td style="text-align: center;">1</td>
                    <td style="text-align: center;">-</td>
                    <td>
                        <table class="amount-box">
                            <tr>
                                <td class="curr-txt-red">{{ $currency }}</td>
                                <td class="num-txt-red">-{{ number_format($disc->amount, 0, ',', '.') }}</td>
                            </tr>
                        </table>
                    </td>
                    <td style="border-top: none; border-bottom: none;"></td>
                </tr>
                @endforeach
            @endif

            {{-- Penutup kosmetik agar garis bawah tabel menyambung cantik --}}
            <tr>
                <td style="border-top: none; height: 1px; padding: 0;"></td>
                <td style="border-top: none; height: 1px; padding: 0;"></td>
                <td colspan="4" style="border: none; height: 1px; padding: 0;"></td>
                <td style="border-top: none; height: 1px; padding: 0;"></td>
            </tr>

            {{-- GRAND TOTAL --}}
            <tr>
                <td colspan="4" style="text-align: center; font-weight: bold; padding: 12px; font-size: 13px;">GRAND TOTAL</td>
                <td colspan="2" style="padding: 12px;">
                    <table class="amount-box">
                        <tr>
                            <td class="curr-txt" style="font-size: 10pt; font-weight: normal;">{{ $currency }}</td>
                            <td class="num-txt" style="font-weight: bold; font-size: 13px;">{{ number_format($po->grand_total, 0, ',', '.') }}</td>
                        </tr>
                    </table>
                </td>
                <td style="border-top: none;"></td>
            </tr>
        </tbody>
    </table>

    {{-- 🔥 KOTAK TANDA TANGAN (ANTI ERROR IMAGE DENGAN BASE64) 🔥 --}}
    @php
        $approvals = \App\Models\DocumentApproval::with('role')
            ->where('document_id', $po->id)
            ->where('document_type', get_class($po))
            ->orderBy('step_order', 'asc')
            ->get();

        $totalCols = 1 + $approvals->count();

        // Data Pembuat (Requester)
        $prepUser = $po->user;
        $prepSigBase64 = null;
        
        if ($prepUser && $prepUser->signature) {
            $path = public_path('storage/' . $prepUser->signature);
            if (file_exists($path)) {
                $type = pathinfo($path, PATHINFO_EXTENSION);
                $data = file_get_contents($path);
                $prepSigBase64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
            }
        }
        
        $prepDept = optional($prepUser->department)->name ?? 'Purchasing Dept.';
        $prepRole = optional(optional($prepUser)->roles->first())->name ?? 'Staff';
    @endphp

    <table class="signature">
        <tr>
            {{-- KOLOM PEMBUAT (ISSUED) --}}
            <td style="width: {{ 100 / ($totalCols > 0 ? $totalCols : 1) }}%;">
                <div class="sign-title">Dibuat Oleh,</div>
                <div class="sign-box">
                    @if($prepSigBase64)
                        <img src="{{ $prepSigBase64 }}">
                    @else
                        <div class="stamp stamp-issued">ISSUED</div>
                    @endif
                </div>
                <div class="sign-name">{{ $po->user->name ?? 'Tim Purchasing' }}</div>
                <div class="sign-meta" style="font-weight: bold;">{{ $prepRole }}</div>
                <div class="sign-dept">{{ $prepDept }}</div>
                <div class="sign-meta" style="margin-top: 5px;">{{ $po->created_at ? $po->created_at->format('d/m/Y H:i') : '-' }}</div>
            </td>

            {{-- KOLOM APPROVAL BERANTAI --}}
            @foreach($approvals as $idx => $approval)
                @php
                    $approverUser = \App\Models\User::find($approval->approved_by);
                    
                    $approverSigBase64 = null;
                    if ($approverUser && $approverUser->signature) {
                        $path = public_path('storage/' . $approverUser->signature);
                        if (file_exists($path)) {
                            $type = pathinfo($path, PATHINFO_EXTENSION);
                            $data = file_get_contents($path);
                            $approverSigBase64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
                        }
                    }

                    $roleName = optional($approval->role)->name ?? 'Manager';
                    $deptName = '';
                    if (!empty($approval->target_department_id) && $approval->target_department_id !== 'all') {
                        $deptObj = \App\Models\Department::find($approval->target_department_id);
                        $deptName = $deptObj ? $deptObj->name : '';
                    } else {
                        if ($approverUser && $approverUser->department_id) {
                            $deptName = optional($approverUser->department)->name ?? '';
                        } else {
                            $deptName = optional($po->user->department)->name ?? ''; 
                        }
                    }

                    $approverName = '<span style="color:#aaa;">...................................................</span>';
                    if ($approval->status == 'APPROVED' || $approval->status == 'REJECTED') {
                        $approverName = $approverUser->name ?? $roleName;
                        if ($approverUser && $approverUser->job_title && !str_contains(strtolower($approverUser->name), 'super')) {
                            $roleName = $approverUser->job_title;
                        }
                    }
                @endphp
                <td style="width: {{ 100 / $totalCols }}%;">
                    <div class="sign-title">{{ $loop->last ? 'Disetujui Oleh,' : 'Diperiksa Oleh,' }}</div>

                    <div class="sign-box">
                        @if($approval->status == 'APPROVED')
                            @if($approverSigBase64)
                                <img src="{{ $approverSigBase64 }}">
                            @else
                                <div class="stamp stamp-approved">APPROVED</div>
                            @endif
                        @elseif($approval->status == 'REJECTED')
                            <div class="stamp stamp-rejected">REJECTED</div>
                        @endif
                    </div>

                    <div class="sign-name">{!! $approverName !!}</div>
                    <div class="sign-meta" style="font-weight: bold;">{{ $roleName }}</div>
                    <div class="sign-dept">{{ $deptName ?: '-' }}</div>
                    
                    <div class="sign-meta" style="margin-top: 5px;">
                        @if($approval->status == 'APPROVED' || $approval->status == 'REJECTED')
                            {{ $approval->approved_at ? date('d/m/Y H:i', strtotime($approval->approved_at)) : '-' }}
                        @else
                            Tgl: ..........................
                        @endif
                    </div>
                </td>
            @endforeach
        </tr>
    </table>

    <div style="margin-top: 25px; font-size: 8pt; color: #555; text-align: left; font-style: italic;">
        * Dokumen ini dicetak otomatis oleh sistem pada {{ \Carbon\Carbon::now()->translatedFormat('d F Y H:i:s') }} WIB
    </div>

</body>
</html>