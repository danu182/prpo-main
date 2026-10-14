<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Purchase Order - {{ $po->po_number }}</title>
    <style>
        @page { margin: 30px 40px; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 10pt; color: #000; margin: 0; padding: 0; }

        h1 { text-align: center; font-size: 28pt; font-weight: bold; margin: 0 0 20px 0; letter-spacing: 1px; }

        table.grid { width: 100%; border-collapse: collapse; border: 1px solid #000; }
        table.grid th, table.grid td { border: 1px solid #000; padding: 5px 6px; vertical-align: top; }

        table.grid th { text-align: center; font-weight: bold; background-color: #d9d9d9; vertical-align: middle; text-transform: uppercase; font-size: 9.5pt; }

        table.sign-table { width: 100%; border-collapse: collapse; border: 1px solid #000; border-top: none; page-break-inside: avoid; table-layout: fixed; }
        table.sign-table td { border: 1px solid #000; vertical-align: top; padding: 0; }

        table.amount-box { width: 100%; border: none !important; border-collapse: collapse; margin: 0; padding: 0; }
        table.amount-box td { border: none !important; padding: 0 !important; margin: 0 !important; vertical-align: top; white-space: nowrap; }
        .curr-txt { text-align: left; width: 1%; padding-right: 5px; color: #333; font-size: 9.5pt; }
        .num-txt { text-align: right; letter-spacing: -0.3px; white-space: nowrap; }

        .stamp { display: inline-block; padding: 4px 10px; font-weight: bold; font-size: 10pt; letter-spacing: 1px; text-transform: uppercase; border: 2px solid; margin: auto; }
        .stamp-issued { color: #198754; border-color: #198754; }
        footer { position: fixed; bottom: -30px; left: 0px; right: 0px; height: 30px; font-size: 8pt; color: #555; font-style: italic; }

    </style>
</head>
<body>

    @php
        $isDigital = (!isset($type) || $type === 'digital' || $type === 'hybrid');

        $currencyCode = $po->currency ?? 'IDR';
        $currencyData = \App\Models\Currency::where('code', $currencyCode)->first();
        $currency = $currencyData ? $currencyData->symbol : $currencyCode;

        $companyName = optional($po->company)->name ?? optional(optional($po->purchaseRequest)->company)->name ?? 'PT. PANDU DEWANATA';
        $companyAddress = optional($po->company)->address ?? 'Menara Batavia, 11th Floor, Jl. K.H. Mas Mansyur, Kav. 126, Jakarta 10220, INDONESIA, tel. 62-21 573 7070, fax. 574 7733';

        $companyNpwp = optional($po->company)->tax_id;
    @endphp

    <footer>Dokumen {{ $isDigital ? 'elektronik' : 'fisik' }} ini diterbitkan oleh sistem pada {{ \Carbon\Carbon::now()->translatedFormat('d M Y H:i:s') }} WIB</footer>


    <h1>Purchase <span style="font-weight: normal;">Order</span></h1>

    <table class="grid" style="border-bottom: none;">
        <tr>
            <td style="width: 5%; border-right: none; border-bottom: none; padding-bottom: 10px;">To :</td>
            <td style="width: 45%; border-left: none; border-bottom: none; line-height: 1.4; padding-bottom: 10px;">
                <strong style="font-size: 11pt;">
                    {{ optional($po->vendor)->name ?? $po->vendor_name }}
                    @if(!empty($po->vendor_sub_name)) - {{ $po->vendor_sub_name }} @endif
                </strong><br><br>
                {{ optional($po->vendor)->address ?? '-' }}<br>
                Telp. {{ optional($po->vendor)->phone ?? '-' }}
            </td>
            <td style="width: 50%; padding: 0; vertical-align: top; border-bottom: none;">
                <table style="width: 100%; border-collapse: collapse; border: none; margin: 0;">
                    <tr>
                        <td style="border: none; padding: 6px 0 6px 10px; white-space: nowrap;">Purchase Order No.</td>
                        <td style="border: none; padding: 6px 5px; width: 1%;">:</td>
                        <td style="border: none; padding: 6px 10px; text-align: right; font-weight: bold; width: 50%;">{{ $po->po_number }}</td>
                    </tr>
                    <tr>
                        <td style="border: none; padding: 6px 0 6px 10px; white-space: nowrap;">Purchase Order Date</td>
                        <td style="border: none; padding: 6px 5px; width: 1%;">:</td>
                        <td style="border: none; padding: 6px 10px; text-align: right;">{{ date('d-M-y', strtotime($po->po_date ?? $po->created_at)) }}</td>
                    </tr>

                    <tr>
                        <td colspan="3" style="border: none; padding: 6px 10px;">&nbsp;</td>
                    </tr>

                    <tr>
                        <td style="border: none; padding: 6px 0 6px 10px; white-space: nowrap;">Purchase Req. No.</td>
                        <td style="border: none; padding: 6px 5px; width: 1%;">:</td>
                        <td style="border: none; padding: 6px 10px; text-align: right;">{{ $po->purchase_request_id ? optional($po->purchaseRequest)->pr_number : '-' }}</td>
                    </tr>
                    <tr>
                        <td style="border: none; padding: 6px 0 6px 10px; white-space: nowrap;">Purchase Req. Date</td>
                        <td style="border: none; padding: 6px 5px; width: 1%;">:</td>
                        <td style="border: none; padding: 6px 10px; text-align: right;">{{ $po->purchase_request_id ? date('d-M-y', strtotime($po->purchaseRequest->pr_date ?? $po->purchaseRequest->created_at)) : '-' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th rowspan="2" style="width: 4%;">NO.</th>
                <th rowspan="2" style="width: 31%;">DESCRIPTION</th>
                <th rowspan="2" style="width: 5%;">QTY</th>
                <th rowspan="2" style="width: 7%;">SAT</th>
                <th colspan="2" style="width: 35%;">PRICE</th>
                <th rowspan="2" style="width: 18%;">R E M A R K</th>
            </tr>
            <tr>
                <th style="width: 15%;">UNIT</th>
                <th style="width: 20%;">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @foreach($po->items as $index => $item)
                @php
                    $qty = (float) ($item->qty ?? $item->qty_ordered ?? 1);
                    $price = (float) ($item->unit_price ?? $item->price ?? 0);
                    $gross = $qty * $price;

                    $masterItem = $item->item;
                    $baseUomName = strtoupper(optional(optional($masterItem)->uom)->name ?? 'PCS');
                    $rawUom = $item->getRawOriginal('uom');
                    $convQty = 1;

                    if (empty($rawUom) && !empty($item->uom_id) && $masterItem) {
                        $altDb = \Illuminate\Support\Facades\DB::table('item_uoms')->where('id', $item->uom_id)->first();
                        if ($altDb) {
                            $rawUom = strtoupper($altDb->uom_name);
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

                    // 🔥 MENGAMBIL NILAI KONVERSI (ISI) DARI DATABASE 🔥
                    if (!empty($item->uom_id)) {
                        $checkDb = \Illuminate\Support\Facades\DB::table('item_uoms')->where('id', $item->uom_id)->first();
                        if ($checkDb) $convQty = (float) $checkDb->conversion_qty;
                    }

                    $uomHtml = strtoupper($cleanUomDisplay);

                    // 🔥 JIKA ISI LEBIH DARI 1, TAMPILKAN SEBAGAI INFO TAMBAHAN 🔥
                    if ($convQty > 1 && stripos($uomHtml, 'ISI') === false) {
                        $uomHtml .= "<br><span style='font-size: 7pt; color: #555; white-space: nowrap;'>(Isi: " . $convQty . " " . $baseUomName . ")</span>";
                    }

                    $utamaName = !empty(trim($item->item_name ?? '')) ? $item->item_name : optional($item->item)->name;
                    $cleanDescription = trim(strip_tags($item->description ?? ''));
                @endphp
                <tr>
                    <td style="text-align: center;">{{ $index + 1 }}</td>
                    <td>
                        {{ $utamaName }}
                        @if(!empty($cleanDescription) && $cleanDescription !== '-')
                            <br>
                            <span style="font-size: 8.5pt; color: #444;">
                                <em>Spesifikasi / Rincian :</em><br>
                                {!! $item->description !!}
                            </span>
                        @endif
                    </td>
                    <td style="text-align: center;">{{ $qty }}</td>
                    <td style="text-align: center;">{!! $uomHtml !!}</td>
                    <td style="padding: 6px 4px;">
                        <table class="amount-box">
                            <tr><td class="curr-txt">{{ $currency }}</td><td class="num-txt">{{ number_format($price, 0, ',', '.') }}</td></tr>
                        </table>
                    </td>
                    <td style="padding: 6px 4px;">
                        <table class="amount-box">
                            <tr><td class="curr-txt">{{ $currency }}</td><td class="num-txt">{{ number_format($gross, 0, ',', '.') }}</td></tr>
                        </table>
                    </td>
                    <td style="padding: 6px 4px;">{{ $item->remark ?? '' }}</td>
                </tr>

                @if((float)$item->discount_amount > 0)
                <tr>
                    <td colspan="4" style="text-align: right; color: red;">Diskon Item (-)</td>
                    <td style="background-color: #f9f9f9;"></td>
                    <td style="padding: 6px 4px;">
                        <table class="amount-box"><tr><td class="curr-txt" style="color: red;">{{ $currency }}</td><td class="num-txt" style="color: red;">-{{ number_format($item->discount_amount, 0, ',', '.') }}</td></tr></table>
                    </td>
                    <td></td>
                </tr>
                @endif
                @if((float)$item->tax_amount > 0)
                <tr>
                    <td colspan="4" style="text-align: right;">Pajak Item (+)</td>
                    <td style="background-color: #f9f9f9;"></td>
                    <td style="padding: 6px 4px;">
                        <table class="amount-box"><tr><td class="curr-txt">{{ $currency }}</td><td class="num-txt">{{ number_format($item->tax_amount, 0, ',', '.') }}</td></tr></table>
                    </td>
                    <td></td>
                </tr>
                @endif
            @endforeach

            @if(isset($charges)) @foreach($charges as $charge)
            <tr>
                <td colspan="4" style="text-align: right;">{{ $charge->name ?? 'Biaya Tambahan' }} (+)</td>
                <td style="background-color: #f9f9f9;"></td>
                <td style="padding: 6px 4px;">
                    <table class="amount-box"><tr><td class="curr-txt">{{ $currency }}</td><td class="num-txt">{{ number_format($charge->amount, 0, ',', '.') }}</td></tr></table>
                </td>
                <td></td>
            </tr>
            @endforeach @endif

            @if(isset($extraDiscounts)) @foreach($extraDiscounts as $disc)
            <tr>
                <td colspan="4" style="text-align: right; color: red;">{{ $disc->name ?? 'Potongan Tambahan' }} (-)</td>
                <td style="background-color: #f9f9f9;"></td>
                <td style="padding: 6px 4px;">
                    <table class="amount-box"><tr><td class="curr-txt" style="color: red;">{{ $currency }}</td><td class="num-txt" style="color: red;">-{{ number_format($disc->amount, 0, ',', '.') }}</td></tr></table>
                </td>
                <td></td>
            </tr>
            @endforeach @endif

            @php
                $sumItemDisc = $po->items->sum('discount_amount');
                $actualGlobalDisc = (float)($po->discount_total ?? 0) - $sumItemDisc;
            @endphp
            @if($actualGlobalDisc > 0)
            <tr>
                <td colspan="4" style="text-align: right; color: red;">Diskon Global (-)</td>
                <td style="background-color: #f9f9f9;"></td>
                <td style="padding: 6px 4px;">
                    <table class="amount-box"><tr><td class="curr-txt" style="color: red;">{{ $currency }}</td><td class="num-txt" style="color: red;">-{{ number_format($actualGlobalDisc, 0, ',', '.') }}</td></tr></table>
                </td>
                <td></td>
            </tr>
            @endif

            @php
                $sumItemTax = $po->items->sum('tax_amount');
                $actualGlobalTax = (float)($po->tax_total ?? 0) - $sumItemTax;
            @endphp
            @if($actualGlobalTax > 0)
            <tr>
                <td colspan="4" style="text-align: right;">Pajak Global (+)</td>
                <td style="background-color: #f9f9f9;"></td>
                <td style="padding: 6px 4px;">
                    <table class="amount-box"><tr><td class="curr-txt">{{ $currency }}</td><td class="num-txt">{{ number_format($actualGlobalTax, 0, ',', '.') }}</td></tr></table>
                </td>
                <td></td>
            </tr>
            @endif

            <tr>
                <td colspan="4" style="border-right: none;"></td>
                <td style="text-align: right; font-weight: normal; border-left: none; padding-right: 10px;">Grand Total</td>
                <td style="padding: 6px 4px; vertical-align: middle;">
                    <table class="amount-box">
                        <tr>
                            <td class="curr-txt" style="vertical-align: middle;">{{ $currency }}</td>
                            <td class="num-txt" style="vertical-align: middle; font-weight: bold;">{{ number_format($po->grand_total, 0, ',', '.') }}</td>
                        </tr>
                    </table>
                </td>
                <td></td>
            </tr>
        </tbody>
    </table>

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
            <td style="border-bottom: none; text-align: left; padding: 10px 10px 0 10px; width: {{ 100 / ($totalCols > 0 ? $totalCols : 1) }}%;">Prepared by :</td>
            @foreach($approvals as $approval)
                <td style="border-bottom: none; text-align: left; padding: 10px 10px 0 10px; width: {{ 100 / ($totalCols > 0 ? $totalCols : 1) }}%;">
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
            <td style="border-top: none; border-bottom: none; height: 70px; text-align: center; vertical-align: bottom;">
                @if($prepSigBase64)
                    <img src="{{ $prepSigBase64 }}" style="max-height: 60px; max-width: 140px; object-fit: contain; margin-bottom: 5px;">
                @elseif(isset($isDigital) && $isDigital)
                    <div class="stamp stamp-issued" style="margin-bottom: 15px;">DIAJUKAN</div>
                @endif
            </td>
            @foreach($approvals as $approval)
                <td style="border-top: none; border-bottom: none; height: 70px; text-align: center; vertical-align: bottom;">
                    @php
                        // 🔥 LOGIKA PENCARIAN TANDA TANGAN APPROVER 🔥
                        $apprSigBase64 = null;
                        if ($approval->status == 'APPROVED' && $approval->approved_by) {
                            $apprUser = \App\Models\User::find($approval->approved_by);
                            if ($apprUser && $apprUser->signature) {
                                $path = public_path('storage/' . $apprUser->signature);
                                if (file_exists($path)) {
                                    $apprSigBase64 = 'data:image/' . pathinfo($path, PATHINFO_EXTENSION) . ';base64,' . base64_encode(file_get_contents($path));
                                }
                            }
                        }
                    @endphp

                    @if(!isset($isDigital) || $isDigital)
                        @if($apprSigBase64)
                            <img src="{{ $apprSigBase64 }}" style="max-height: 60px; max-width: 140px; object-fit: contain; margin-bottom: 5px;">
                        @elseif($approval->status == 'APPROVED')
                            <div style="color: green; font-size: 10px; margin-bottom: 15px;">[ APPROVED ]</div>
                        @elseif($approval->status == 'REJECTED')
                            <div style="color: red; font-size: 10px; margin-bottom: 15px;">[ REJECTED ]</div>
                        @else
                            <div style="color: gray; font-size: 10px; margin-bottom: 15px;">[ PENDING ]</div>
                        @endif
                    @endif
                </td>
            @endforeach
        </tr>

        <tr>
            <td style="border-top: none; padding: 0 10px 15px 10px; text-align: center;">
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
                <td style="border-top: none; padding: 0 10px 15px 10px; text-align: center;">
                    <strong><u>{{ $namaTtd }}</u></strong>
                    @if(!empty($jabatanLengkap))
                        <br>
                        {{-- <span style="font-size: 8pt; color: #555;">{{ $jabatanLengkap }}</span> --}}
                    @else
                        <br>
                        <span style="font-size: 8pt; color: transparent;">-</span>
                    @endif
                </td>
            @endforeach
        </tr>
    </table>

    <table style="width: 100%; border-collapse: collapse; border: none; margin-top: 20px;">
        <tr>
            <td style="padding: 15px 5px; border: none;">
                <div style="font-weight: bold; margin-bottom: 12px; font-size: 10.5pt;">{{ $companyName }}</div>
                <div style="font-size: 9.5pt; line-height: 1.4;">{{ $companyAddress }}</div>

                @if(!empty($companyNpwp))
                    <div style="font-size: 9.5pt; margin-top: 12px;">NPWP : {{ $companyNpwp }}</div>
                @endif
            </td>
        </tr>
    </table>

</body>
</html>
