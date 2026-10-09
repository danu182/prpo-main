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

        .wrapper { width: 100%; }

        /* 🔥 CSS BORDER SUPER CLEAN (Mencegah Garis Tebal/Dobel di DomPDF) 🔥 */
        table.info-table { width: 100%; border-collapse: collapse; border: 1px solid #000; border-bottom: none; }
        table.info-table .inner-table td { padding: 6px 8px; border: none; vertical-align: top; }

        table.grid { width: 100%; border-collapse: collapse; border: 1px solid #000; }
        table.grid th, table.grid td { border: 1px solid #000; padding: 6px 4px; vertical-align: top; }
        table.grid th { text-align: center; font-weight: bold; background-color: #fff; vertical-align: middle; }

        table.sign-table { width: 100%; border-collapse: collapse; border: 1px solid #000; border-top: none; page-break-inside: avoid; table-layout: fixed; }
        table.sign-table td { border: none; padding: 10px; vertical-align: top; text-align: center; }

        .stamp { display: inline-block; padding: 4px 10px; font-weight: bold; font-size: 10pt; letter-spacing: 1px; text-transform: uppercase; border: 2px solid; margin-top: 10px; }
        .stamp-issued { color: #198754; border-color: #198754; }

        .break-text { word-wrap: break-word; word-break: break-all; }

        table.amount-box { width: 100%; border: none !important; border-collapse: collapse; margin: 0; padding: 0; }
        table.amount-box td { border: none !important; padding: 0 !important; margin: 0 !important; vertical-align: top; white-space: nowrap; }
        .curr-txt { text-align: left; width: 1%; padding-right: 5px; color: #555; }
        .num-txt { text-align: right; letter-spacing: -0.3px; }

        footer { position: fixed; bottom: -30px; left: 0px; right: 0px; height: 30px; font-size: 8pt; color: #555; font-style: italic; }
    </style>
</head>
<body>

    @php
        $isDigital = (!isset($type) || $type === 'digital' || $type === 'hybrid');
        $printType = $type ?? 'digital';

        $currencyCode = $po->currency ?? 'IDR';
        $currencyData = \App\Models\Currency::where('code', $currencyCode)->first();
        $currency = $currencyData ? $currencyData->symbol : $currencyCode;

        $companyName = optional($po->company)->name ?? optional(optional($po->purchaseRequest)->company)->name ?? 'PT. KANTOR PUSAT';
    @endphp

    <footer>Dokumen {{ $isDigital ? 'elektronik' : 'fisik' }} ini diterbitkan oleh sistem ProcureApp pada {{ \Carbon\Carbon::now()->translatedFormat('d M Y H:i:s') }} WIB</footer>

    <div class="company-name">{{ $companyName }}</div>
    <div class="doc-title">Bank Payment Request Form @if($isDigital) <span style="font-size: 9pt; color:#666;">(Digital Signature)</span> @else <span style="font-size: 9pt; color:#666;">(Manual Signature)</span> @endif</div>

    <div class="wrapper">
        {{-- KOTAK INFORMASI --}}
        <table class="info-table">
            <tr>
                <td width="50%" style="padding: 0; vertical-align: top;">
                    <table class="inner-table" style="width: 100%; border-collapse: collapse;">
                        <tr><td style="width: 100px;">Requester</td><td>: {{ $po->user->name ?? 'Sistem' }}</td></tr>
                        <tr><td>Department</td><td>: {{ optional($po->user->department)->name ?? 'Purchasing' }}</td></tr>
                        <tr><td>Request Date</td><td>: {{ date('d-M-y', strtotime($po->po_date ?? $po->created_at)) }}</td></tr>
                    </table>
                </td>
                <td width="50%" style="padding: 0; vertical-align: top;">
                    <table class="inner-table" style="width: 100%; border-collapse: collapse;">
                        <tr><td style="width: 120px;">Title</td><td>: Pembayaran PO</td></tr>
                        <tr><td>Bill Ref.</td><td style="font-weight: bold;">: {{ $po->po_number }}</td></tr>
                        <tr>
                            <td>Supplier</td>
                            <td>
                                : {{ optional($po->vendor)->name ?? $po->vendor_name }}
                                @if(!empty($po->vendor_sub_name))
                                    - {{ $po->vendor_sub_name }}
                                @endif
                            </td>
                        </tr>
                        <tr><td>Due Date</td><td>: {{ $po->due_date ? date('d-M-y', strtotime($po->due_date)) : ($po->delivery_date ? date('d-M-y', strtotime($po->delivery_date)) : '-') }}</td></tr>
                    </table>
                </td>
            </tr>
        </table>

        @php
            $rowspanCount = $po->items->count() + 1;
            foreach($po->items as $item) {
                if((float)($item->discount_amount ?? 0) > 0) $rowspanCount++;
                if((float)($item->tax_amount ?? 0) > 0) $rowspanCount++;
            }
            if(isset($charges)) $rowspanCount += count($charges);

            $sumItemDisc = $po->items->sum('discount_amount');
            $actualGlobalDisc = (float)($po->discount_total ?? 0) - $sumItemDisc;
            if($actualGlobalDisc > 0) $rowspanCount++;

            $sumItemTax = $po->items->sum('tax_amount');
            $actualGlobalTax = (float)($po->tax_total ?? 0) - $sumItemTax;
            if($actualGlobalTax > 0) $rowspanCount++;

            if(isset($extraDiscounts)) $rowspanCount += count($extraDiscounts);
        @endphp

        {{-- TABEL GRID ITEM DETAIL --}}
        <table class="grid">
            <thead>
                <tr>
                    <th style="width: 4%;">No</th>
                    <th style="width: 13%;">Invoices No.</th>
                    <th style="width: 29%;">Description</th>
                    <th style="width: 10%;">Qty & Satuan</th>
                    <th style="width: 16%;">Unit Price</th>
                    <th style="width: 16%;">Total Amount</th>
                    <th style="width: 12%;">Account No</th>
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
                        <td style="text-align: center;">{{ $index + 1 }}</td>
                        <td style="text-align: center; color: #0d6efd;" class="break-text">@if($index === 0) {{ !empty($po->invoice_number) ? wordwrap($po->invoice_number, 14, " ", true) : '-' }} @endif</td>
                        <td>
                            @if(!empty($po->vendor_sub_name))
                                <u>{{ optional($po->vendor)->name }} - {{ $po->vendor_sub_name }}</u><br>
                            @endif

                            <strong style="font-size: 13px;">{{ $item->item_name ?? optional($item->item)->name }}</strong>
                            @if(!empty($item->description) && $item->description !== '-')
                                <br><span style="font-size: 10px; color: #555;">{!! strip_tags($item->description) !!}</span>
                            @endif
                        </td>
                        <td style="text-align: center;">
                            <strong>{{ $qty }}</strong><br>
                            <span style="font-size: 7.5pt; color: #0d6efd; font-weight: bold;">{{ $cleanUomDisplay }}</span>
                        </td>

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
                                    <td class="num-txt">{{ number_format($gross, 0, ',', '.') }}</td>
                                </tr>
                            </table>
                        </td>

                        @if($index === 0)
                        <td rowspan="{{ $rowspanCount }}" style="text-align: center; font-weight: bold; color: #198754;" class="break-text">
                            {{ !empty($po->account_number) ? wordwrap($po->account_number, 12, " ", true) : '-' }}
                        </td>
                        @endif
                    </tr>

                    {{-- GABUNGAN COLSPAN UNTUK MENGHILANGKAN BORDER BERANTAKAN DI BARIS TAMBAHAN --}}
                    @if($discAmt > 0)
                    <tr>
                        <td colspan="2" style="border-right: none;"></td>
                        <td style="color: red; border-left: none;">Diskon Item: {{ $item->item_name ?? optional($item->item)->name }}</td>
                        <td style="text-align: center;">1</td><td style="text-align: center;">-</td>
                        <td>
                            <table class="amount-box">
                                <tr><td class="curr-txt" style="color: red;">{{ $currency }}</td><td class="num-txt" style="color: red;">-{{ number_format($discAmt, 0, ',', '.') }}</td></tr>
                            </table>
                        </td>
                    </tr>
                    @endif
                    @if($taxAmt > 0)
                    <tr>
                        <td colspan="2" style="border-right: none;"></td>
                        <td style="border-left: none;">Pajak Item (VAT/PPN)</td>
                        <td style="text-align: center;">1</td><td style="text-align: center;">-</td>
                        <td>
                            <table class="amount-box">
                                <tr><td class="curr-txt">{{ $currency }}</td><td class="num-txt">{{ number_format($taxAmt, 0, ',', '.') }}</td></tr>
                            </table>
                        </td>
                    </tr>
                    @endif
                @endforeach

                @if($actualGlobalDisc > 0)
                <tr>
                    <td colspan="2" style="border-right: none;"></td>
                    <td style="color: red; border-left: none;">Diskon Header (Global)</td>
                    <td style="text-align: center;">1</td><td style="text-align: center;">-</td>
                    <td>
                        <table class="amount-box">
                            <tr><td class="curr-txt" style="color: red;">{{ $currency }}</td><td class="num-txt" style="color: red;">-{{ number_format($actualGlobalDisc, 0, ',', '.') }}</td></tr>
                        </table>
                    </td>
                </tr>
                @endif

                @if(isset($extraDiscounts)) @foreach($extraDiscounts as $disc)
                <tr>
                    <td colspan="2" style="border-right: none;"></td>
                    <td style="color: red; border-left: none;">{{ $disc->name ?? 'Potongan Tambahan' }}</td>
                    <td style="text-align: center;">1</td><td style="text-align: center;">-</td>
                    <td>
                        <table class="amount-box">
                            <tr><td class="curr-txt" style="color: red;">{{ $currency }}</td><td class="num-txt" style="color: red;">-{{ number_format($disc->amount, 0, ',', '.') }}</td></tr>
                        </table>
                    </td>
                </tr>
                @endforeach @endif

                @if(isset($charges)) @foreach($charges as $charge)
                <tr>
                    <td colspan="2" style="border-right: none;"></td>
                    <td style="border-left: none;">{{ $charge->name ?? 'Biaya Tambahan' }}</td>
                    <td style="text-align: center;">1</td><td style="text-align: center;">-</td>
                    <td>
                        <table class="amount-box">
                            <tr><td class="curr-txt">{{ $currency }}</td><td class="num-txt">{{ number_format($charge->amount, 0, ',', '.') }}</td></tr>
                        </table>
                    </td>
                </tr>
                @endforeach @endif

                @if($actualGlobalTax > 0)
                <tr>
                    <td colspan="2" style="border-right: none;"></td>
                    <td style="border-left: none;">Pajak Header (VAT/PPN)</td>
                    <td style="text-align: center;">1</td><td style="text-align: center;">-</td>
                    <td>
                        <table class="amount-box">
                            <tr><td class="curr-txt">{{ $currency }}</td><td class="num-txt">{{ number_format($actualGlobalTax, 0, ',', '.') }}</td></tr>
                        </table>
                    </td>
                </tr>
                @endif

                <tr>
                    <td colspan="5" style="padding: 10px; text-align: right; font-weight: bold; vertical-align: middle;">GRAND TOTAL</td>
                    <td style="padding: 10px 4px; vertical-align: middle;">
                        <table class="amount-box">
                            <tr>
                                <td class="curr-txt" style="font-size: 10pt; vertical-align: middle;">{{ $currency }}</td>
                                <td class="num-txt" style="font-weight: bold; font-size: 11pt; vertical-align: middle;">{{ number_format($po->grand_total, 0, ',', '.') }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </tbody>
        </table>

        {{-- KOTAK TANDA TANGAN --}}
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

        <table class="sign-table">
            <tr>
                <td style="text-align: left; padding: 10px 10px 0 10px; width: {{ 100 / ($totalCols > 0 ? $totalCols : 1) }}%;">Prepared by :</td>
                @foreach($approvals as $approval)
                    <td style="text-align: left; padding: 10px 10px 0 10px; width: {{ 100 / ($totalCols > 0 ? $totalCols : 1) }}%;">
                        @if($loop->last)
                            Approved by :
                        @else
                            @if($approvals->count() > 2)
                                Checked by {{ $loop->iteration }} :
                            @else
                                Checked by :
                            @endif
                        @endif
                    </td>
                @endforeach
            </tr>

            <tr>
                <td style="height: 70px; text-align: center;">
                    @if($prepSigBase64)
                        <img src="{{ $prepSigBase64 }}" style="max-height: 60px; max-width: 140px; object-fit: contain;">
                    @elseif($isDigital)
                        <div class="stamp stamp-issued">DIAJUKAN</div>
                    @endif
                </td>
                @foreach($approvals as $approval)
                    <td style="height: 70px; text-align: center;">
                        @if($isDigital)
                            @if($approval->status == 'APPROVED')
                                <div style="color: green; font-size: 10px; margin-top: 15px;">[ APPROVED ]</div>
                            @elseif($approval->status == 'REJECTED')
                                <div style="color: red; font-size: 10px; margin-top: 15px;">[ REJECTED ]</div>
                            @else
                                <div style="color: gray; font-size: 10px; margin-top: 15px;">[ PENDING ]</div>
                            @endif
                        @endif
                    </td>
                @endforeach
            </tr>

            <tr>
                <td style="padding: 0 10px 15px 10px; text-align: center;">
                    <strong><u>{{ $po->user->name ?? 'Requester' }}</u></strong><br>
                    <span style="font-size: 7pt; color: #666;">{{ $po->created_at ? $po->created_at->format('d/m/y H:i') : '' }}</span>
                </td>
                @foreach($approvals as $approval)
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
                            if ($firstUser) {
                                $namaTtd = $firstUser->name;
                            }
                        }

                        if (empty($namaTtd)) {
                            $namaTtd = $jabatanLengkap;
                            $jabatanLengkap = '';
                        }
                    @endphp
                    <td style="padding: 0 10px 15px 10px; text-align: center;">
                        <strong><u>{{ $namaTtd }}</u></strong>
                        @if(!empty($jabatanLengkap))
                            <br>
                            <span style="font-size: 8pt; color: #555;">{{ $jabatanLengkap }}</span>
                        @else
                            <br>
                            <span style="font-size: 8pt; color: transparent;">-</span>
                        @endif
                    </td>
                @endforeach
            </tr>
        </table>
    </div>

</body>
</html>
