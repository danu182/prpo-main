<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Bank Payment Request (Standar) - {{ $po->po_number }}</title>
    <style>
        @page { margin: 40px 40px 60px 40px; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 10pt; color: #000; margin: 0; padding: 0; }
        .company-name { font-size: 16pt; font-weight: bold; margin: 0; text-transform: uppercase; }
        .doc-title { font-size: 12pt; margin: 5px 0 15px 0; }
        
        /* 🔥 SEAMLESS BOX UTAMA 🔥 */
        .wrapper { border: 1px solid #000; width: 100%; }
        
        table.grid { width: 100%; border-collapse: collapse; border-top: 1px solid #000; }
        table.grid th, table.grid td { border: 1px solid #000; padding: 6px 4px; vertical-align: middle; }
        table.grid th { text-align: center; font-weight: bold; background-color: #fff; }
        table.grid th:first-child, table.grid td:first-child { border-left: none; }
        table.grid th:last-child, table.grid td:last-child { border-right: none; }
        table.grid tr:last-child td { border-bottom: none; }
        
        table.sign-table { width: 100%; border-collapse: collapse; border-top: 1px solid #000; page-break-inside: avoid; }
        table.sign-table td { border: none; padding: 5px; vertical-align: middle; text-align: center; }
        
        .stamp { display: inline-block; padding: 4px 10px; font-weight: bold; font-size: 10pt; letter-spacing: 1px; text-transform: uppercase; border: 2px solid; }
        .stamp-issued { color: #198754; border-color: #198754; }
        .stamp-approved { color: #0d6efd; border-color: #0d6efd; }
        .stamp-rejected { color: #dc3545; border-color: #dc3545; }
        .stamp-pending { color: #aaa; border-color: #aaa; border-style: dashed; }
        
        .break-text { word-wrap: break-word; word-break: break-all; }
        table.amount-box { width: 100%; border: none !important; margin: 0; padding: 0; }
        table.amount-box td { border: none !important; padding: 0 !important; margin: 0 !important; vertical-align: middle; }
        .curr-txt { text-align: left; width: 1%; padding-right: 5px !important; color: #555; font-size: 9pt; }
        .num-txt { text-align: right; font-size: 10pt; }
        
        footer { position: fixed; bottom: -30px; left: 0px; right: 0px; height: 30px; font-size: 8pt; color: #555; font-style: italic; }
    </style>
</head>
<body>

    @php
        $isDigital = (!isset($type) || $type === 'digital');
        $currency = $po->currency ?? 'IDR';
        $companyName = optional($po->company)->name ?? optional(optional($po->purchaseRequest)->company)->name ?? 'PT. KANTOR PUSAT';

        // 🔥 LOGIKA MATEMATIKA ANTI-RANCU (Distribusikan Grand Total ke setiap baris item secara proporsional) 🔥
        $grandTotal = (float) ($po->grand_total ?? 0);
        $totalGrossAll = 0;
        foreach($po->items as $i) {
            $q = (float) ($i->qty ?? $i->qty_ordered ?? 1);
            $p = (float) ($i->unit_price ?? $i->price ?? 0);
            $totalGrossAll += ($q * $p);
        }
        if ($totalGrossAll <= 0) $totalGrossAll = 1; // Cegah error bagi nol
        $runningTotal = 0;
    @endphp

    <footer>
        * Dokumen {{ $isDigital ? 'elektronik' : 'fisik' }} ini diterbitkan oleh sistem ProcureApp pada {{ \Carbon\Carbon::now()->translatedFormat('d M Y H:i:s') }} WIB
    </footer>

    <div class="company-name">{{ $companyName }}</div>
    <div class="doc-title">
        Bank Payment Request Form 
        @if($isDigital) <span style="font-size: 9pt; color:#666;">(Digital Signature)</span> @endif
    </div>

    {{-- BUNGKUSAN KOTAK SEAMLESS --}}
    <div class="wrapper">
        
        {{-- KOTAK INFORMASI ATAS --}}
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td width="50%" style="padding: 0;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr><td style="padding: 6px; width: 100px;">Requester</td><td style="padding: 6px;">: {{ $po->user->name ?? 'Sistem' }}</td></tr>
                        <tr><td style="padding: 6px;">Department</td><td style="padding: 6px;">: {{ optional($po->user->department)->name ?? 'Purchasing' }}</td></tr>
                        <tr><td style="padding: 6px;">Request Date</td><td style="padding: 6px;">: {{ date('d-M-y', strtotime($po->po_date ?? $po->created_at)) }}</td></tr>
                    </table>
                </td>
                <td width="50%" style="padding: 0; border-left: 1px solid #000;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr><td style="padding: 6px; width: 120px;">Title</td><td style="padding: 6px;">: Pembayaran PO - {{ optional($po->vendor)->name ?? $po->vendor_name }}</td></tr>
                        <tr><td style="padding: 6px;">Bill Ref.</td><td style="padding: 6px; font-weight: bold;">: {{ $po->po_number }}</td></tr>
                        <tr><td style="padding: 6px;">Due Date</td><td style="padding: 6px;">: {{ $po->due_date ? date('d-M-y', strtotime($po->due_date)) : ($po->delivery_date ? date('d-M-y', strtotime($po->delivery_date)) : '-') }}</td></tr>
                    </table>
                </td>
            </tr>
        </table>

        {{-- TABEL ITEM DETAIL --}}
        <table class="grid">
            <thead>
                <tr>
                    <th style="width: 5%;">No</th>
                    <th style="width: 18%;">Invoices No.</th>
                    <th style="width: 32%;">Description</th>
                    <th style="width: 10%;">Reference</th>
                    <th style="width: 20%;">Total Amount</th>
                    <th style="width: 15%;">Account No</th>
                </tr>
            </thead>
            <tbody>
                @foreach($po->items as $index => $item)
                    @php
                        $qty = (float) ($item->qty ?? $item->qty_ordered ?? 1);
                        $price = (float) ($item->unit_price ?? $item->price ?? 0);
                        $gross = $qty * $price;
                        
                        // Sistem mendistribusikan Grand Total ke baris item agar jumlahnya pasti klop!
                        if ($loop->last) {
                            $itemFinalNet = $grandTotal - $runningTotal;
                        } else {
                            $itemFinalNet = round(($gross / $totalGrossAll) * $grandTotal);
                            $runningTotal += $itemFinalNet;
                        }

                        $uomStr = preg_replace('/ \(Isi:.*\)/i', '', is_string($item->uom) ? $item->uom : (optional(optional($item->item)->uom)->name ?? 'PCS'));
                    @endphp
                    <tr>
                        <td style="text-align: center;">{{ $index + 1 }}</td>
                        <td style="text-align: center; color: #0d6efd;" class="break-text">@if($index === 0) {{ !empty($po->invoice_number) ? wordwrap($po->invoice_number, 14, " ", true) : '-' }} @endif</td>
                        <td>
                            <strong style="font-size: 13px;">{{ $item->item_name ?? optional($item->item)->name }}</strong>
                            @if(!empty($item->description) && $item->description !== '-') <br><span style="font-size: 10px; color: #555;">{!! strip_tags($item->description) !!}</span> @endif
                        </td>
                        <td style="text-align: center;">{{ $qty }} {{ strtoupper($uomStr) }}</td>
                        <td>
                            <table class="amount-box">
                                <tr>
                                    <td class="curr-txt">{{ $currency }}</td>
                                    <td class="num-txt">{{ number_format($itemFinalNet, 0, ',', '.') }}</td>
                                </tr>
                            </table>
                        </td>
                        @if($index === 0)
                        <td rowspan="{{ $po->items->count() + 1 }}" style="text-align: center; font-weight: bold; color: #198754; vertical-align: top; padding-top: 15px;" class="break-text">
                            {{ !empty($po->account_number) ? wordwrap($po->account_number, 12, " ", true) : '-' }}
                        </td>
                        @endif
                    </tr>
                @endforeach
                <tr>
                    <td colspan="4" style="text-align: center; font-weight: bold; padding: 10px;">Total Amount</td>
                    <td style="padding: 10px;">
                        <table class="amount-box fw-bold">
                            <tr>
                                <td class="curr-txt" style="font-size: 10pt; font-weight: normal;">{{ $currency }}</td>
                                <td class="num-txt" style="font-weight: bold; font-size: 12pt;">{{ number_format($grandTotal, 0, ',', '.') }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </tbody>
        </table>

        {{-- KOTAK TANDA TANGAN (DIGITAL / MANUAL / HYBRID) --}}
        @php
            $printType = $type ?? 'digital';
            $approvals = \App\Models\DocumentApproval::with('role')->where('document_id', $po->id)->where('document_type', get_class($po))->orderBy('step_order', 'asc')->get();
            $totalCols = 1 + $approvals->count();
            
            $prepUser = $po->user; $prepSigBase64 = null;
            if ($prepUser && $prepUser->signature) {
                $path = public_path('storage/' . $prepUser->signature);
                if (file_exists($path)) { $prepSigBase64 = 'data:image/' . pathinfo($path, PATHINFO_EXTENSION) . ';base64,' . base64_encode(file_get_contents($path)); }
            }
        @endphp

        <table class="sign-table">
            <tr>
                <td style="text-align: left; padding: 10px 10px 0 10px; width: {{ 100 / $totalCols }}%;">Prepared by :</td>
                @foreach($approvals as $app)
                    <td style="text-align: left; padding: 10px 10px 0 10px; width: {{ 100 / $totalCols }}%;">{{ $loop->last ? 'Approved by :' : 'Checked by :' }}</td>
                @endforeach
            </tr>
            <tr>
                <td style="height: 70px;">
                    @if($printType == 'digital')
                        @if($prepSigBase64) <img src="{{ $prepSigBase64 }}" style="max-height: 60px; max-width: 130px; object-fit: contain;"> @else <div class="stamp stamp-issued">ISSUED</div> @endif
                    @elseif($printType == 'hybrid')
                        @if($prepSigBase64) <img src="{{ $prepSigBase64 }}" style="max-height: 60px; max-width: 130px; object-fit: contain;"> @endif
                    @endif
                </td>
                @foreach($approvals as $app)
                    @php
                        $apprSigBase64 = null;
                        if ($app->status == 'APPROVED' && optional($app->approver)->signature) {
                            $path = public_path('storage/' . $app->approver->signature);
                            if (file_exists($path)) { $apprSigBase64 = 'data:image/' . pathinfo($path, PATHINFO_EXTENSION) . ';base64,' . base64_encode(file_get_contents($path)); }
                        }
                    @endphp
                    <td style="height: 70px;">
                        @if($printType == 'digital')
                            @if($app->status == 'APPROVED')
                                @if($apprSigBase64) <img src="{{ $apprSigBase64 }}" style="max-height: 60px; max-width: 130px; object-fit: contain;"> @else <div class="stamp stamp-approved">APPROVED</div> @endif
                            @elseif($app->status == 'REJECTED')
                                <div class="stamp stamp-rejected">REJECTED</div>
                            @else
                                <div class="stamp stamp-pending">PENDING</div>
                            @endif
                        @elseif($printType == 'hybrid')
                            @if($app->status == 'APPROVED' && $apprSigBase64)
                                <img src="{{ $apprSigBase64 }}" style="max-height: 60px; max-width: 130px; object-fit: contain;">
                            @endif
                        @endif
                    </td>
                @endforeach
            </tr>
            <tr>
                <td style="padding: 0 10px 15px 10px;"><u><strong>{{ $po->user->name ?? 'Tim Purchasing' }}</strong></u></td>
                @foreach($approvals as $app)
                    @php
                        $approverName = '<span style="color:#fff;">_</span>';
                        if ($app->status == 'APPROVED' || $app->status == 'REJECTED') {
                            $approverName = optional($app->approver)->name ?? optional($app->role)->name;
                        } else {
                            $roleName = optional($app->role)->name;
                            $potentialUsers = \App\Models\User::role($roleName);
                            if (!empty($app->target_department_id) && $app->target_department_id !== 'all') { $potentialUsers->where('department_id', $app->target_department_id); }
                            $firstUser = $potentialUsers->first();
                            if ($firstUser) $approverName = $firstUser->name;
                        }
                    @endphp
                    <td style="padding: 0 10px 15px 10px;"><u><strong>{!! $approverName !!}</strong></u></td>
                @endforeach
            </tr>
        </table>

    </div>

</body>
</html>