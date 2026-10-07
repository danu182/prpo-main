<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>BPR - {{ $po->po_number }}</title>
    <style>
        @page { margin: 40px 40px 60px 40px; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 10pt; color: #000; margin: 0; }
        .company-name { font-size: 16pt; font-weight: bold; margin: 0; text-transform: uppercase; }
        .doc-title { font-size: 12pt; margin: 2px 0 15px 0; }
        table { width: 100%; border-collapse: collapse; margin: 0; padding: 0; }

        table.info-table { border: 1px solid #000; border-bottom: none; }
        table.info-table td { padding: 4px 8px; vertical-align: top; border: none; }
        .td-divider { border-right: 1px solid #000 !important; }

        table.main-table { border: 1px solid #000; }
        table.main-table th, table.main-table td { border: 1px solid #000; padding: 6px 8px; vertical-align: middle; }
        table.main-table th { text-align: center; font-weight: bold; background-color: #fff; }

        table.signature-table { border: 1px solid #000; border-top: none; page-break-inside: avoid; }
        table.signature-table td { border: none; padding: 10px; vertical-align: top; text-align: left; height: 110px; position: relative; }

        .text-center { text-align: center; } .fw-bold { font-weight: bold; } .break-text { word-wrap: break-word; word-break: break-all; }
        table.amount-box { width: 100%; border: none !important; margin: 0; padding: 0; }
        table.amount-box td { border: none !important; padding: 0 !important; margin: 0 !important; vertical-align: middle; }
        .curr-txt { text-align: left; width: 1%; padding-right: 5px !important; } .num-txt { text-align: right; }
        footer { position: fixed; bottom: -30px; left: 0px; right: 0px; height: 30px; font-size: 8pt; color: #555; font-style: italic; }
    </style>
</head>
<body>
    @php
        $isDigital = (!isset($type) || $type === 'digital' || $type === 'hybrid');
        $printType = $type ?? 'digital';

        $companyName = optional($po->company)->name ?? optional(optional($po->purchaseRequest)->company)->name ?? 'HITAWASANA';

        // 🔥 LOGIKA PENCARIAN SIMBOL MATA UANG 🔥
        $currencyCode = $po->currency ?? 'IDR';
        $currencyData = \App\Models\Currency::where('code', $currencyCode)->first();
        $currency = $currencyData ? $currencyData->symbol : $currencyCode;

        // Distribusi Grand Total (Anti-Rancu)
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

    <footer>* Dokumen ini dicetak otomatis oleh sistem pada {{ \Carbon\Carbon::now()->translatedFormat('d F Y H:i:s') }} WIB</footer>

    <div class="company-name">{{ $companyName }}</div>
    <div class="doc-title">
        Bank Payment Request Form
        @if($isDigital) <span style="font-size: 9pt; color:#666;">(Digital Signature)</span> @else <span style="font-size: 9pt; color:#666;">(Manual Signature)</span> @endif
    </div>

    {{-- KOTAK INFORMASI --}}
    <table class="info-table">
        <tr>
            <td width="13%" style="padding-top: 8px;">Requester</td><td width="2%" style="padding-top: 8px;">:</td>
            <td width="35%" style="padding-top: 8px;">{{ $po->user->name ?? 'System' }}</td>
            <td width="13%" style="padding-left: 12px; padding-top: 8px;">Title</td><td width="2%" style="padding-top: 8px;">:</td>
            <td width="35%" style="padding-top: 8px;">Pembayaran PO</td>
        </tr>
        <tr>
            <td>Department</td><td>:</td><td>{{ optional($po->user->department)->name ?? 'Purchasing' }}</td>
            <td style="padding-left: 12px;">Bill Ref.</td><td>:</td><td class="fw-bold">{{ $po->po_number }}</td>
        </tr>

        {{-- 🔥 BARIS BARU UNTUK VENDOR / SUPPLIER 🔥 --}}
        <tr>
            <td>Supplier</td><td>:</td>
            <td>
                {{ optional($po->vendor)->name ?? 'Vendor' }}
                @if(!empty($po->vendor_sub_name))
                    - {{ $po->vendor_sub_name }}
                @endif
            </td>
            <td style="padding-left: 12px;">Payment Due</td><td>:</td><td>{{ $po->due_date ? date('d-M-y', strtotime($po->due_date)) : ($po->delivery_date ? date('d-M-y', strtotime($po->delivery_date)) : '-') }}</td>
        </tr>

        <tr>
            <td style="padding-bottom: 8px;">Request Date</td><td style="padding-bottom: 8px;">:</td>
            <td style="padding-bottom: 8px;">{{ date('d-M-y', strtotime($po->po_date ?? $po->created_at)) }}</td>
            <td style="padding-left: 12px; padding-bottom: 8px;"></td><td style="padding-bottom: 8px;"></td>
            <td style="padding-bottom: 8px;"></td>
        </tr>
    </table>

    {{-- TABEL ITEM DETAIL --}}
    <table class="main-table">
        <thead>
            <tr>
                <th width="5%">No</th><th width="15%">Invoices No.</th><th width="35%">Description</th>
                <th width="10%">Reference</th><th width="20%">Total Amount</th><th width="15%">Account No</th>
            </tr>
        </thead>
        <tbody>
            @foreach($po->items as $index => $item)
                @php
                    $qty = (float) ($item->qty ?? $item->qty_ordered ?? 1);
                    $price = (float) ($item->unit_price ?? $item->price ?? 0);
                    $gross = $qty * $price;

                    if ($loop->last) {
                        $itemFinalNet = $grandTotal - $runningTotal;
                    } else {
                        $itemFinalNet = round(($gross / $totalGrossAll) * $grandTotal);
                        $runningTotal += $itemFinalNet;
                    }

                    // Native UOM Extractor
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
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="text-center break-text">@if($index === 0) {{ !empty($po->invoice_number) ? wordwrap($po->invoice_number, 14, " ", true) : '-' }} @endif</td>
                    <td>
                        <strong>{{ $item->item_name ?? optional($item->item)->name }}</strong>
                        @if(!empty($item->description) && $item->description !== '-')
                            <br><span style="font-size: 9pt;">{!! strip_tags($item->description) !!}</span>
                        @endif
                    </td>
                    <td align="center" style="vertical-align: middle;">
                        {{ $qty + 0 }} {{ $cleanUomDisplay }}
                    </td>
                    <td style="padding: 0 4px;">
                        <table class="amount-box"><tr><td class="curr-txt">{{ $currency }}</td><td class="num-txt">{{ number_format($itemFinalNet, 0, ',', '.') }}</td></tr></table>
                    </td>
                    @if($index === 0)
                    <td rowspan="{{ $po->items->count() + 1 }}" class="text-center break-text fw-bold" style="vertical-align: top; padding-top: 15px; color: #198754;">
                        {{ !empty($po->account_number) ? wordwrap($po->account_number, 14, " ", true) : '-' }}
                    </td>
                    @endif
                </tr>
            @endforeach
            <tr>
                <td colspan="4" class="text-center fw-bold">Total Amount</td>
                <td style="padding: 0 4px;"><table class="amount-box fw-bold"><tr><td class="curr-txt">{{ $currency }}</td><td class="num-txt">{{ number_format($po->grand_total, 0, ',', '.') }}</td></tr></table></td>
            </tr>
        </tbody>
    </table>

    {{-- KOTAK TANDA TANGAN --}}
    @php
        // 🔥 PERBAIKAN: Hanya me-load 'role', karena pencarian user sudah kita tangani otomatis di bawah 🔥
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

    <table class="signature-table">
        <tr>
            {{-- KOLOM PEMOHON --}}
            <td style="width: {{ 100 / ($totalCols > 0 ? $totalCols : 1) }}%;">
                <div style="margin-bottom: 5px;">Prepared by :</div>
                <div style="height: 60px; text-align: center;">
                    @if($isDigital && $prepSigBase64)
                        <img src="{{ $prepSigBase64 }}" style="max-height: 60px; max-width: 140px; object-fit: contain;">
                    @elseif($isDigital && !$prepSigBase64)
                        <div style="display: inline-block; padding: 5px 10px; font-weight: bold; font-size: 10pt; border: 2px solid #198754; color: #198754; margin-top: 15px;">DIAJUKAN</div>
                    @endif
                </div>
                <div style="text-align: center; position: absolute; bottom: 10px; width: 100%; left: 0;">
                    <span class="fw-bold" style="text-decoration: underline;">{{ $po->user->name ?? 'Requester' }}</span><br>
                    <span style="font-size: 7pt; color: #666;">{{ $po->created_at ? $po->created_at->format('d/m/y H:i') : '' }}</span>
                </div>
            </td>

            {{-- KOLOM PERSETUJUAN --}}
            @foreach($approvals as $approval)
                <td style="width: {{ 100 / ($totalCols > 0 ? $totalCols : 1) }}%;">

                    {{-- LABEL KIRI ATAS --}}
                    <div style="margin-bottom: 5px; text-align: left;">
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

                    {{-- STATUS DIGITAL ATAU RUANG KOSONG MANUAL --}}
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

                    {{-- LOGIKA CERDAS PENENTUAN NAMA TANDA TANGAN --}}
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
                            if (!empty($approval->target_department_id)) {
                                $dept = \DB::table('departments')->where('id', $approval->target_department_id)->first();
                                $deptName = $dept ? $dept->name : '';
                            } else {
                                $deptName = optional(optional($po->user)->department)->name ?? '';
                            }

                            $jabatanLengkap = trim($roleName . ' ' . $deptName);

                            if (empty($namaTtd)) {
                                $namaTtd = $jabatanLengkap;
                                $jabatanLengkap = '';
                            }
                        @endphp

                        <strong><u>{{ $namaTtd }}</u></strong><br>

                        {{-- TRIK PENYEIMBANG: Memastikan tinggi sama dengan kolom pemohon --}}
                        @if(!empty($jabatanLengkap))
                            <span style="font-size: 7pt; color: #555;">{{ $jabatanLengkap }}</span>
                        @else
                            <span style="font-size: 7pt; color: transparent;">-</span>
                        @endif
                    </div>
                </td>
            @endforeach
        </tr>
    </table>
</body>
</html>
