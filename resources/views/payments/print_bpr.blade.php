<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>BPR Standar - {{ $bill->bill_number }}</title>
    <style>
        @page { margin: 40px 40px 60px 40px; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 10pt; color: #000; margin: 0; padding: 0; }
        .company-name { font-size: 16pt; font-weight: bold; margin: 0; text-transform: uppercase; }
        .doc-title { font-size: 12pt; margin: 5px 0 15px 0; }
        .wrapper { border: 1px solid #000; width: 100%; }
        table.grid { width: 100%; border-collapse: collapse; border-top: 1px solid #000; }
        table.grid th, table.grid td { border: 1px solid #000; padding: 6px 4px; vertical-align: middle; }
        table.grid th { text-align: center; font-weight: bold; background-color: #fff; }
        table.grid th:first-child, table.grid td:first-child { border-left: none; }
        table.grid th:last-child, table.grid td:last-child { border-right: none; }
        table.grid tr:last-child td { border-bottom: none; }
        table.sign-table { width: 100%; border-collapse: collapse; border-top: 1px solid #000; page-break-inside: avoid; }
        table.sign-table td { border: none; padding: 5px; vertical-align: bottom; text-align: center; }
        .stamp { display: inline-block; padding: 4px 10px; font-weight: bold; font-size: 10pt; border: 2px solid; }
        .stamp-issued { color: #198754; border-color: #198754; } .stamp-approved { color: #0d6efd; border-color: #0d6efd; }
        .stamp-rejected { color: #dc3545; border-color: #dc3545; } .stamp-pending { color: #aaa; border-color: #aaa; border-style: dashed; }
        table.amount-box { width: 100%; border: none !important; margin: 0; padding: 0; }
        table.amount-box td { border: none !important; padding: 0 !important; margin: 0 !important; vertical-align: middle; }
        .curr-txt { text-align: left; width: 1%; padding-right: 5px !important; color: #555; }
        .num-txt { text-align: right; }
    </style>
</head>
<body>
    @php
        $printType = $type ?? 'digital';
        $currency = $bill->currency ?? 'IDR';
        $companyName = optional($bill->company)->name ?? 'PT. PERUSAHAAN';

        // Distribusi Grand Total Proporsional
        $grandTotal = (float) ($bill->amount ?? 0);
        $totalGrossAll = 0;
        foreach($bill->items as $i) {
            $q = (float) ($i->qty ?? 1);
            $p = (float) ($i->price ?? $i->amount ?? 0);
            $totalGrossAll += ($q * $p);
        }
        if ($totalGrossAll <= 0) $totalGrossAll = 1; 
        $runningTotal = 0;
    @endphp

    <div class="company-name">{{ $companyName }}</div>
    <div class="doc-title">Bank Payment Request Form @if($printType == 'digital') <span style="font-size: 9pt; color:#666;">(Digital Signature)</span> @endif</div>

    <div class="wrapper">
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td width="50%" style="padding: 0;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr><td style="padding: 6px; width: 100px;">Requester</td><td style="padding: 6px;">: {{ $bill->user->name ?? 'Sistem' }}</td></tr>
                        <tr><td style="padding: 6px;">Department</td><td style="padding: 6px;">: {{ optional($bill->user->department)->name ?? 'Umum' }}</td></tr>
                        <tr><td style="padding: 6px;">Request Date</td><td style="padding: 6px;">: {{ date('d-M-y', strtotime($bill->created_at)) }}</td></tr>
                    </table>
                </td>
                <td width="50%" style="padding: 0; border-left: 1px solid #000;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr><td style="padding: 6px; width: 100px;">Title</td><td style="padding: 6px;">: {{ $bill->title }}</td></tr>
                        <tr><td style="padding: 6px;">Bill Ref.</td><td style="padding: 6px; font-weight: bold;">: {{ $bill->bill_number }}</td></tr>
                        <tr><td style="padding: 6px;">Due Date</td><td style="padding: 6px;">: {{ $bill->due_date ? date('d-M-y', strtotime($bill->due_date)) : '-' }}</td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <table class="grid">
            <thead>
                <tr>
                    <th style="width: 5%;">No</th><th style="width: 15%;">Invoices No.</th><th style="width: 35%;">Description</th>
                    <th style="width: 10%;">Reference</th><th style="width: 20%;">Total Amount</th><th style="width: 15%;">Account No</th>
                </tr>
            </thead>
            <tbody>
                @foreach($bill->items as $index => $item)
                    @php 
                        $qty = (float) ($item->qty ?? 1); 
                        $price = (float) ($item->price ?? $item->amount ?? 0); 
                        $gross = $qty * $price;
                        
                        if ($loop->last) { $itemFinalNet = $grandTotal - $runningTotal; } 
                        else { $itemFinalNet = round(($gross / $totalGrossAll) * $grandTotal); $runningTotal += $itemFinalNet; }
                    @endphp
                    <tr>
                        <td style="text-align: center;">{{ $index + 1 }}</td>
                        <td style="text-align: center; color: #0d6efd;">@if($index === 0) {{ $bill->vendor_invoice_number ?? '-' }} @endif</td>
                        <td><strong>{{ $item->name }}</strong> @if(!empty($item->description)) <br><span style="font-size: 10px; color: #555;">{!! strip_tags($item->description) !!}</span> @endif</td>
                        <td style="text-align: center;">{{ $qty }} LS</td>
                        <td><table class="amount-box"><tr><td class="curr-txt">{{ $currency }}</td><td class="num-txt">{{ number_format($itemFinalNet, 0, ',', '.') }}</td></tr></table></td>
                        @if($index === 0)
                        <td rowspan="{{ $bill->items->count() + 1 }}" style="text-align: center; font-weight: bold; color: #198754; vertical-align: top; padding-top: 15px;">{{ $bill->account_number ?? '-' }}</td>
                        @endif
                    </tr>
                @endforeach
                <tr>
                    <td colspan="4" style="text-align: center; font-weight: bold; padding: 10px;">Total Amount</td>
                    <td style="padding: 10px;"><table class="amount-box fw-bold"><tr><td class="curr-txt">{{ $currency }}</td><td class="num-txt" style="font-size: 12pt;">{{ number_format($bill->amount, 0, ',', '.') }}</td></tr></table></td>
                </tr>
            </tbody>
        </table>

        {{-- 🔥 KOTAK TTD HYBRID 🔥 --}}
        @php
            $approvals = \App\Models\DocumentApproval::with('role')->where('document_id', $bill->id)->where('document_type', get_class($bill))->orderBy('step_order', 'asc')->get();
            $totalCols = 1 + $approvals->count();
            
            $prepUser = $bill->user; $prepSigBase64 = null;
            if ($prepUser && $prepUser->signature) {
                $path = public_path('storage/' . $prepUser->signature);
                if (file_exists($path)) { $prepSigBase64 = 'data:image/' . pathinfo($path, PATHINFO_EXTENSION) . ';base64,' . base64_encode(file_get_contents($path)); }
            }

            $matrixData = [];
            foreach($approvals as $app) {
                $roleName = optional($app->role)->name ?? 'Approver';
                
                // Cari kandidat User asli yang menjabat role ini
                $idealUserQuery = \App\Models\User::whereHas('roles', function($q) use ($app) { $q->where('id', $app->role_id); });
                if (!empty($app->target_department_id) && $app->target_department_id !== 'all') { 
                    $idealUserQuery->where('department_id', $app->target_department_id); 
                }
                
                // Singkirkan Super Admin agar dapat nama Pejabat Asli
                $idealUser = (clone $idealUserQuery)->whereDoesntHave('roles', function($q) { 
                    $q->whereIn('name', ['Super Admin', 'super-admin', 'Super Administrator']); 
                })->first();
                
                // Fallback jika memang tidak ada user selain super admin yang pegang role ini
                if (!$idealUser) { $idealUser = $idealUserQuery->first(); }
                
                // Set Default Name: Jika User ada pakai namanya, jika tidak pakai nama Role (Msl: "Manager")
                $approverName = $idealUser ? $idealUser->name : $roleName;
                $apprSigBase64 = null;

                if ($app->status == 'APPROVED' || $app->status == 'REJECTED') {
                    $apprUser = \App\Models\User::find($app->approved_by);
                    
                    // Jika disetujui BUKAN oleh Super Admin, tampilkan nama asli si peng-approve
                    if ($apprUser && !$apprUser->hasRole(['Super Admin', 'super-admin', 'Super Administrator'])) {
                        $approverName = $apprUser->name;
                        $sig = $apprUser->signature;
                    } else {
                        // Jika di BYPASS Super Admin, tetap pertahankan nama Pejabat Asli ($approverName) & pakai TTD-nya
                        $sig = optional($idealUser)->signature;
                    }

                    if ($sig && $app->status == 'APPROVED') {
                        $p = public_path('storage/' . $sig);
                        if (file_exists($p)) { $apprSigBase64 = 'data:image/' . pathinfo($p, PATHINFO_EXTENSION) . ';base64,' . base64_encode(file_get_contents($p)); }
                    }
                }

                $matrixData[] = [
                    'status' => $app->status,
                    'approverName' => $approverName,
                    'signature' => $apprSigBase64
                ];
            }
        @endphp

        <table class="sign-table">
            <tr>
                <td style="text-align: left; padding: 10px 10px 0 10px; width: {{ 100 / $totalCols }}%; vertical-align: top;">Prepared by :</td>
                @foreach($matrixData as $data) 
                    <td style="text-align: left; padding: 10px 10px 0 10px; width: {{ 100 / $totalCols }}%; vertical-align: top;">{{ $loop->last ? 'Approved by :' : 'Checked by :' }}</td> 
                @endforeach
            </tr>
            <tr>
                <td style="height: 70px;">
                    @if($printType == 'digital' || $printType == 'hybrid')
                        @if($prepSigBase64) <img src="{{ $prepSigBase64 }}" style="max-height: 60px; max-width: 130px; object-fit: contain;"> @elseif($printType == 'digital') <div class="stamp stamp-issued">ISSUED</div> @endif
                    @endif
                </td>
                @foreach($matrixData as $data)
                    <td style="height: 70px;">
                        @if($printType == 'digital')
                            @if($data['status'] == 'APPROVED')
                                @if($data['signature']) <img src="{{ $data['signature'] }}" style="max-height: 60px; max-width: 130px; object-fit: contain;"> @else <div class="stamp stamp-approved">APPROVED</div> @endif
                            @elseif($data['status'] == 'REJECTED') <div class="stamp stamp-rejected">REJECTED</div> @else <div class="stamp stamp-pending">PENDING</div> @endif
                        @elseif($printType == 'hybrid')
                            @if($data['status'] == 'APPROVED' && $data['signature']) <img src="{{ $data['signature'] }}" style="max-height: 60px; max-width: 130px; object-fit: contain;"> @endif
                        @endif
                    </td>
                @endforeach
            </tr>
            <tr>
                <td style="padding: 0 10px 15px 10px;">
                    <u><strong>{{ $bill->user->name ?? 'System' }}</strong></u>
                </td>
                @foreach($matrixData as $data)
                    <td style="padding: 0 10px 15px 10px;">
                        <u><strong>{!! $data['approverName'] !!}</strong></u>
                    </td>
                @endforeach
            </tr>
        </table>
    </div>
</body>
</html>