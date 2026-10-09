<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Bank Payment Request (Detail) - {{ $po->po_number }}</title>
    <style>
        @page { margin: 40px 40px 60px 40px; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 10pt; color: #000; margin: 0; padding: 0; }
        .company-name { font-size: 16pt; font-weight: bold; margin: 0; text-transform: uppercase; }
        .doc-title { font-size: 12pt; margin: 5px 0 15px 0; }

        /* CSS STANDAR (Sebagai Backup) */
        table { width: 100%; border-collapse: collapse; margin: 0; padding: 0; }
        .break-text { word-wrap: break-word; word-break: break-all; }

        table.amount-box { width: 100%; border: none !important; margin: 0; padding: 0; }
        table.amount-box td { border: none !important; padding: 0 !important; margin: 0 !important; vertical-align: middle; }
        .curr-txt { text-align: left; width: 1%; padding-right: 5px !important; color: #555; font-size: 9pt; }
        .curr-txt-red { text-align: left; width: 1%; padding-right: 5px !important; color: red; font-size: 9pt; }
        .num-txt { text-align: right; font-size: 10pt; }
        .num-txt-red { text-align: right; font-size: 10pt; color: red; }

        footer { position: fixed; bottom: -30px; left: 0px; right: 0px; height: 30px; font-size: 8pt; color: #555; font-style: italic; }
    </style>
</head>
<body>

    @php
        $isDigital = (!isset($type) || $type === 'digital' || $type === 'hybrid');
        $printType = $type ?? 'digital';

        // 🔥 LOGIKA PENCARIAN SIMBOL MATA UANG 🔥
        $currencyCode = $po->currency ?? 'IDR';
        $currencyData = \App\Models\Currency::where('code', $currencyCode)->first();
        $currency = $currencyData ? $currencyData->symbol : $currencyCode;

        $companyName = optional($po->company)->name ?? optional(optional($po->purchaseRequest)->company)->name ?? 'PT. KANTOR PUSAT';

        // Distribusi Grand Total
        $grandTotal = (float) ($po->grand_total ?? 0);
        $totalGrossAll = 0;
        foreach($po->items as $i) {
            $q = (float) ($i->qty ?? $i->qty_ordered ?? 1);
            $p = (float) ($i->unit_price ?? $i->price ?? 0);
            $totalGrossAll += ($q * $p);
        }
        if ($totalGrossAll <= 0) $totalGrossAll = 1;
        $runningTotal = 0;
    @endphp

    <footer>* Dokumen {{ $isDigital ? 'elektronik' : 'fisik' }} ini diterbitkan oleh sistem ProcureApp pada {{ \Carbon\Carbon::now()->translatedFormat('d M Y H:i:s') }} WIB - @if($isDigital) <span style="font-size: 9pt; color:#666;">(Digital Signature)</span> @else <span style="font-size: 9pt; color:#666;">(Manual Signature)</span> @endif</footer>

    <div class="company-name">{{ $companyName }}</div>
    <div class="doc-title">
        Bank Payment Request Form
        {{-- @if($isDigital) <span style="font-size: 9pt; color:#666;">(Digital Signature)</span> @else <span style="font-size: 9pt; color:#666;">(Manual Signature)</span> @endif --}}
    </div>

    {{-- KOTAK INFORMASI --}}
    <table style="width: 100%; border-collapse: collapse; border: 1px solid #000; border-bottom: none;">
        <tr>
            <td width="50%" style="padding: 0; vertical-align: top;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr><td style="padding: 6px 8px; width: 100px;">Requester</td><td style="padding: 6px 8px;">: {{ $po->user->name ?? 'Sistem' }}</td></tr>
                    <tr><td style="padding: 6px 8px;">Department</td><td style="padding: 6px 8px;">: {{ optional($po->user->department)->name ?? 'Purchasing' }}</td></tr>
                    <tr><td style="padding: 6px 8px;">Request Date</td><td style="padding: 6px 8px;">: {{ date('d-M-y', strtotime($po->po_date ?? $po->created_at)) }}</td></tr>
                </table>
            </td>
            <td width="50%" style="padding: 0; vertical-align: top;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr><td style="padding: 6px 8px; width: 120px;">Title</td><td style="padding: 6px 8px;">: Pembayaran PO</td></tr>
                    <tr><td style="padding: 6px 8px;">Bill Ref.</td><td style="padding: 6px 8px; font-weight: bold;">: {{ $po->po_number }}</td></tr>
                    <tr>
                        <td style="padding: 6px 8px;">Supplier</td>
                        <td style="padding: 6px 8px;">
                            : {{ optional($po->vendor)->name ?? $po->vendor_name }}
                            @if(!empty($po->vendor_sub_name))
                                - {{ $po->vendor_sub_name }}
                            @endif
                        </td>
                    </tr>
                    <tr><td style="padding: 6px 8px;">Due Date</td><td style="padding: 6px 8px;">: {{ $po->due_date ? date('d-M-y', strtotime($po->due_date)) : ($po->delivery_date ? date('d-M-y', strtotime($po->delivery_date)) : '-') }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- 🔥 TABEL ITEM DETAIL 🔥 --}}
    @php
        // PERBAIKAN: Tambahkan +1 agar Account No memanjang sampai menutupi baris Total Amount
        $rowspanCount = $po->items->count() + 1;

        foreach($po->items as $item) {
            if((float)($item->discount_amount ?? 0) > 0) $rowspanCount++;
            if((float)($item->tax_amount ?? 0) > 0) $rowspanCount++;
        }
        $sumItemDisc = $po->items->sum('discount_amount');
        $actualGlobalDisc = (float)($po->discount_total ?? 0) - $sumItemDisc;
        if($actualGlobalDisc > 0) $rowspanCount++;

        $sumItemTax = $po->items->sum('tax_amount');
        $actualGlobalTax = (float)($po->tax_total ?? 0) - $sumItemTax;
        if($actualGlobalTax > 0) $rowspanCount++;

        if(isset($extraDiscounts)) $rowspanCount += count($extraDiscounts);
        if(isset($charges)) $rowspanCount += count($charges);
    @endphp

    <table style="width: 100%; border-collapse: collapse; border: 1px solid #000;">
        <thead>
            <tr>
                <th style="border: 1px solid #000; padding: 6px 4px; text-align: center; width: 4%;">No</th>
                <th style="border: 1px solid #000; padding: 6px 4px; text-align: center; width: 13%;">Invoices No.</th>
                <th style="border: 1px solid #000; padding: 6px 4px; text-align: center; width: 29%;">Description</th>
                <th style="border: 1px solid #000; padding: 6px 4px; text-align: center; width: 10%;">Qty & Satuan</th>
                <th style="border: 1px solid #000; padding: 6px 4px; text-align: center; width: 16%;">Unit Price</th>
                <th style="border: 1px solid #000; padding: 6px 4px; text-align: center; width: 16%;">Total Amount</th>
                <th style="border: 1px solid #000; padding: 6px 4px; text-align: center; width: 12%;">Account No</th>
            </tr>
        </thead>
        <tbody>
            @foreach($po->items as $index => $item)
                @php
                    $qty = (float) ($item->qty ?? $item->qty_ordered ?? 1);
                    $price = (float) ($item->unit_price ?? $item->price ?? 0);
                    $gross = $qty * $price;
                    $discAmt = (float) ($item->discount_amount ?? 0);
                    $taxAmt = (float) ($item->tax_amount ?? 0);

                    if ($loop->last) {
                        $itemFinalNet = $grandTotal - $runningTotal;
                    } else {
                        $itemFinalNet = round(($gross / $totalGrossAll) * $grandTotal);
                        $runningTotal += $itemFinalNet;
                    }

                    $masterItem = $item->item;
                    $baseUomName = strtoupper(optional(optional($masterItem)->uom)->name ?? 'PCS');
                    $rawUom = $item->getRawOriginal('uom');

                    if (empty($rawUom) && !empty($item->uom_id) && $masterItem) {
                        $altDb = \Illuminate\Support\Facades\DB::table('item_uoms')->where('id', $item->uom_id)->first();
                        if ($altDb) {
                            $rawUom = strtoupper($altDb->uom_name) . " (Isi: " . (float)$altDb->conversion_qty . " " . $baseUomName . ")";
                        }
                    }
                    if (empty($rawUom)) $rawUom = $baseUomName;

                    if (is_string($rawUom) && str_starts_with(trim($rawUom), '{')) {
                        $uomObj = json_decode($rawUom, true);
                        if ($uomObj) {
                            $uomObjLower = array_change_key_case($uomObj, CASE_LOWER);
                            $rawUom = $uomObjLower['uom_name'] ?? $uomObjLower['name'] ?? $uomObjLower['code'] ?? $baseUomName;
                        }
                    }
                    $cleanUomDisplay = trim(preg_replace('/ \[PO\]| \[PR\]| \[GR\]/i', '', $rawUom));
                @endphp
                <tr>
                    <td style="border: 1px solid #000; padding: 6px 4px; text-align: center;">{{ $index + 1 }}</td>
                    <td style="border: 1px solid #000; padding: 6px 4px; text-align: center; color: #0d6efd;" class="break-text">@if($index === 0) {{ !empty($po->invoice_number) ? wordwrap($po->invoice_number, 14, " ", true) : '-' }} @endif</td>
                    <td style="border: 1px solid #000; padding: 6px 4px;">
                        @if(!empty($po->vendor_sub_name)) <u>{{ optional($po->vendor)->name }} - {{ $po->vendor_sub_name }}</u><br> @endif
                        <strong style="font-size: 13px;">{{ $item->item_name ?? optional($item->item)->name }}</strong>
                        @if(!empty($item->description) && $item->description !== '-') <br><span style="font-size: 10px; color: #555;">{!! strip_tags($item->description) !!}</span> @endif
                    </td>
                    <td style="border: 1px solid #000; padding: 6px 4px; text-align: center;">
                        <strong>{{ $qty }}</strong><br><span style="font-size: 7.5pt; color: #0d6efd; font-weight: bold;">{{ $cleanUomDisplay }}</span>
                    </td>
                    {{-- UNIT PRICE --}}
                    <td style="border: 1px solid #000; padding: 6px 4px; vertical-align: middle;">
                        <table style="width: 100%; border: none; border-collapse: collapse; margin: 0; padding: 0;">
                            <tr>
                                <td style="text-align: center;">-</td>
                                {{-- <td style="border: none; padding: 0; text-align: left; width: 1%; white-space: nowrap; color: #555;"></td> --}}
                                {{-- <td style="border: none; padding: 0; text-align: left; width: 1%; white-space: nowrap; color: #555;">{{ $currency }}</td>
                                <td style="border: none; padding: 0; text-align: right; white-space: nowrap;">{{ number_format($price, 0, ',', '.') }}</td> --}}
                            </tr>
                        </table>
                    </td>

                    {{-- TOTAL AMOUNT ITEM --}}
                    <td style="border: 1px solid #000; padding: 6px 4px; vertical-align: middle;">
                        <table style="width: 100%; border: none; border-collapse: collapse; margin: 0; padding: 0;">
                            <tr>
                                <td style="border: none; padding: 0; text-align: left; width: 1%; white-space: nowrap; color: #555;">{{ $currency }}</td>
                                <td style="border: none; padding: 0; text-align: right; white-space: nowrap;">{{ number_format($itemFinalNet, 0, ',', '.') }}</td>
                            </tr>
                        </table>
                    </td>

                    {{-- ACCOUNT NO DI-SET 'TOP' DENGAN PADDING AGAR SEJAJAR DENGAN BARIS PERTAMA --}}
                    @if($index === 0)
                    <td rowspan="{{ $rowspanCount }}" style="border: 1px solid #000; padding-top: 35px; padding-bottom: 6px; padding-left: 4px; padding-right: 4px; text-align: center; vertical-align: top; font-weight: bold; color: #198754;" class="break-text">
                        {{ !empty($po->account_number) ? wordwrap($po->account_number, 12, " ", true) : '-' }}
                    </td>
                    @endif
                </tr>

                @if($discAmt > 0)
                <tr>
                    <td style="border: 1px solid #000; border-right: none;"></td><td style="border: 1px solid #000; border-right: none; border-left: none;"></td>
                    <td style="border: 1px solid #000; color: red; border-left: none;">Diskon Item: {{ $item->item_name ?? optional($item->item)->name }}</td><td style="border: 1px solid #000; text-align: center;">1</td><td style="border: 1px solid #000; text-align: center;">-</td>
                    <td style="border: 1px solid #000; padding: 6px 4px;"><table class="amount-box"><tr><td class="curr-txt-red">{{ $currency }}</td><td class="num-txt-red">-{{ number_format($discAmt, 0, ',', '.') }}</td></tr></table></td>
                </tr>
                @endif

                @if($taxAmt > 0)
                <tr>
                    <td style="border: 1px solid #000; border-right: none;"></td><td style="border: 1px solid #000; border-right: none; border-left: none;"></td>
                    <td style="border: 1px solid #000; border-left: none;">Pajak Item (VAT/PPN)</td><td style="border: 1px solid #000; text-align: center;">1</td><td style="border: 1px solid #000; text-align: center;">-</td>
                    <td style="border: 1px solid #000; padding: 6px 4px;"><table class="amount-box"><tr><td class="curr-txt">{{ $currency }}</td><td class="num-txt">{{ number_format($taxAmt, 0, ',', '.') }}</td></tr></table></td>
                </tr>
                @endif
            @endforeach

            @if($actualGlobalDisc > 0)
            <tr>
                <td style="border: 1px solid #000; border-right: none;"></td><td style="border: 1px solid #000; border-right: none; border-left: none;"></td>
                <td style="border: 1px solid #000; color: red; border-left: none;">Diskon Header (Global)</td><td style="border: 1px solid #000; text-align: center;">1</td><td style="border: 1px solid #000; text-align: center;">-</td>
                <td style="border: 1px solid #000; padding: 6px 4px;"><table class="amount-box"><tr><td class="curr-txt-red">{{ $currency }}</td><td class="num-txt-red">-{{ number_format($actualGlobalDisc, 0, ',', '.') }}</td></tr></table></td>
            </tr>
            @endif

            @if(isset($extraDiscounts)) @foreach($extraDiscounts as $disc)
            <tr>
                <td style="border: 1px solid #000; border-right: none;"></td><td style="border: 1px solid #000; border-right: none; border-left: none;"></td>
                <td style="border: 1px solid #000; color: red; border-left: none;">{{ $disc->name ?? 'Potongan Tambahan' }}</td><td style="border: 1px solid #000; text-align: center;">1</td><td style="border: 1px solid #000; text-align: center;">-</td>
                <td style="border: 1px solid #000; padding: 6px 4px;"><table class="amount-box"><tr><td class="curr-txt-red">{{ $currency }}</td><td class="num-txt-red">-{{ number_format($disc->amount, 0, ',', '.') }}</td></tr></table></td>
            </tr>
            @endforeach @endif

            @if(isset($charges)) @foreach($charges as $charge)
            <tr>
                <td style="border: 1px solid #000; border-right: none;"></td><td style="border: 1px solid #000; border-right: none; border-left: none;"></td>
                <td style="border: 1px solid #000; border-left: none;">{{ $charge->name ?? 'Biaya Tambahan' }}</td><td style="border: 1px solid #000; text-align: center;">1</td><td style="border: 1px solid #000; text-align: center;">-</td>
                <td style="border: 1px solid #000; padding: 6px 4px;"><table class="amount-box"><tr><td class="curr-txt">{{ $currency }}</td><td class="num-txt">{{ number_format($charge->amount, 0, ',', '.') }}</td></tr></table></td>
            </tr>
            @endforeach @endif

            @if($actualGlobalTax > 0)
            <tr>
                <td style="border: 1px solid #000; border-right: none;"></td><td style="border: 1px solid #000; border-right: none; border-left: none;"></td>
                <td style="border: 1px solid #000; border-left: none;">Pajak Header (VAT/PPN)</td><td style="border: 1px solid #000; text-align: center;">1</td><td style="border: 1px solid #000; text-align: center;">-</td>
                <td style="border: 1px solid #000; padding: 6px 4px;"><table class="amount-box"><tr><td class="curr-txt">{{ $currency }}</td><td class="num-txt">{{ number_format($actualGlobalTax, 0, ',', '.') }}</td></tr></table></td>
            </tr>
            @endif

            {{-- 🔥 PERBAIKAN: PENYESUAIAN PADDING & FONT AGAR AMAN UNTUK NOMINAL PULUHAN/RATUSAN JUTA 🔥 --}}
            {{-- GRAND TOTAL AMOUNT --}}
            <tr>
                <td style="border: 1px solid #000; padding: 10px; text-align: right; font-weight: bold;" colspan="5">Total Amount</td>
                <td style="border: 1px solid #000; padding: 10px 4px; vertical-align: middle;">
                    <table style="width: 100%; border: none; border-collapse: collapse; margin: 0; padding: 0;">
                        <tr>
                            <td style="border: none; padding: 0; text-align: left; width: 1%; white-space: nowrap; font-weight: normal; font-size: 10pt;">{{ $currency }}</td>
                            <td style="border: none; padding: 0; text-align: right; white-space: nowrap; font-weight: bold; font-size: 11pt;">{{ number_format($po->grand_total, 0, ',', '.') }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </tbody>
    </table>

    {{-- KOTAK TANDA TANGAN (SMART LOOKUP) --}}
    @php
        $approvals = \App\Models\DocumentApproval::with(['role'])
            ->where('document_id', $po->id)
            ->whereIn('document_type', ['App\Models\PurchaseOrder', 'PO', 'PurchaseOrder', get_class($po)])
            ->orderBy('step_order', 'asc')
            ->get();

        $totalCols = 1 + $approvals->count();

        $prepSigBase64 = null;
        if ($po->user && $po->user->signature) {
            $path = public_path('storage/' . $po->user->signature);
            if (file_exists($path)) { $prepSigBase64 = 'data:image/' . pathinfo($path, PATHINFO_EXTENSION) . ';base64,' . base64_encode(file_get_contents($path)); }
        }
    @endphp

    <table style="width: 100%; border-collapse: collapse; border: 1px solid #000; border-top: none; table-layout: fixed; page-break-inside: avoid;">
        <tr>
            {{-- KOLOM PEMOHON --}}
            <td style="border: none; padding: 10px; vertical-align: top; text-align: center; height: 120px; position: relative;">
                <div style="text-align: left; margin-bottom: 5px;">Prepared by :</div>
                <div style="height: 60px; text-align: center;">
                    @if($prepSigBase64)
                        <img src="{{ $prepSigBase64 }}" style="max-height: 60px; max-width: 140px; object-fit: contain;">
                    @elseif($isDigital)
                        <div style="display: inline-block; padding: 5px 10px; font-weight: bold; font-size: 10pt; border: 2px solid #198754; color: #198754; margin-top: 15px;">DIAJUKAN</div>
                    @endif
                </div>
                <div style="text-align: center; position: absolute; bottom: 10px; width: 100%; left: 0;">
                    <span style="font-weight: bold; text-decoration: underline;">{{ $po->user->name ?? 'Requester' }}</span><br>
                    <span style="font-size: 7pt; color: #666;">{{ $po->created_at ? $po->created_at->format('d/m/y H:i') : '' }}</span>
                </div>
            </td>

            {{-- KOLOM PERSETUJUAN --}}
            @foreach($approvals as $approval)
                <td style="border: none; padding: 10px; vertical-align: top; text-align: center; height: 120px; position: relative;">
                    <div style="text-align: left; margin-bottom: 5px;">
                        @if($loop->last)
                            Approved by :
                        @else
                            @if($approvals->count() > 2)
                                Checked by {{ $loop->iteration }} :
                            @else
                                Checked by :
                            @endif
                        @endif
                    </div>
                    <div style="height: 60px; text-align: center;">
                        @if($isDigital)
                            @if($approval->status == 'APPROVED')
                                <div style="color: green; font-size: 10px; margin-top: 15px;">[ APPROVED ]</div>
                            @elseif($approval->status == 'REJECTED')
                                <div style="color: red; font-size: 10px; margin-top: 15px;">[ REJECTED ]</div>
                            @else
                                <div style="color: gray; font-size: 10px; margin-top: 15px;">[ PENDING ]</div>
                            @endif
                        @endif
                    </div>
                    <div style="text-align: center; position: absolute; bottom: 10px; width: 100%; left: 0;">
                        @php
                            $namaTtd = "";
                            $uid = $approval->user_id ?? $approval->approver_id ?? $approval->specific_user_id ?? null;

                            if (!empty($uid)) {
                                $userTtd = \App\Models\User::find($uid);
                                $namaTtd = $userTtd ? $userTtd->name : '';
                            }

                            $roleName = optional($approval->role)->name ?? 'Manager';
                            $deptName = '';
                            if (!empty($approval->target_department_id) && $approval->target_department_id !== 'all') {
                                $dept = \DB::table('departments')->where('id', $approval->target_department_id)->first();
                                $deptName = $dept ? $dept->name : '';
                            } else {
                                $deptName = optional(optional($po->user)->department)->name ?? '';
                            }

                            $jabatanLengkap = trim($roleName . ' ' . $deptName);

                            if (empty($namaTtd) && !empty($roleName)) {
                                $potentialUsers = \App\Models\User::role($roleName);
                                if (!empty($approval->target_department_id) && $approval->target_department_id !== 'all') {
                                    $potentialUsers->where('department_id', $approval->target_department_id);
                                } elseif (empty($approval->target_department_id)) {
                                    $potentialUsers->where('department_id', optional($po->user)->department_id);
                                }
                                $firstUser = $potentialUsers->first();
                                if ($firstUser) { $namaTtd = $firstUser->name; }
                            }

                            if (empty($namaTtd)) {
                                $namaTtd = $jabatanLengkap;
                                $jabatanLengkap = '';
                            }
                        @endphp
                        <span style="font-weight: bold; text-decoration: underline;">{{ $namaTtd }}</span>
                        @if(!empty($jabatanLengkap))
                            <br><span style="font-size: 8pt; color: #555;">{{ $jabatanLengkap }}</span>
                        @else
                            <br><span style="font-size: 8pt; color: transparent;">-</span>
                        @endif
                    </div>
                </td>
            @endforeach
        </tr>
    </table>

</body>
</html>
