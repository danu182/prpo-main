<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>BPR Digital - {{ $bill->bill_number }}</title>
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
    <footer>* Dokumen digital ini dicetak otomatis oleh sistem pada {{ \Carbon\Carbon::now()->translatedFormat('d F Y H:i:s') }} WIB</footer>

    @php
        $companyName = optional($bill->company)->name ?? 'HITAWASANA';
        $currency = $bill->currency ?? 'IDR';
    @endphp
    <div class="company-name">{{ $companyName }}</div>
    <div class="doc-title">Bank Payment Request Form <span style="font-size: 9pt; color:#666;">(Digital Signature)</span></div>

    {{-- KOTAK INFORMASI --}}
    <table class="info-table">
        <tr>
            <td width="13%" style="padding-top: 8px;">Requester</td><td width="2%" style="padding-top: 8px;">:</td>
            <td width="35%" class="td-divider" style="padding-top: 8px;">{{ $bill->user->name ?? 'System' }}</td>
            <td width="13%" style="padding-left: 12px; padding-top: 8px;">Title</td><td width="2%" style="padding-top: 8px;">:</td>
            <td width="35%" style="padding-top: 8px;">{{ $bill->title ?? 'Tagihan Opex' }}</td>
        </tr>
        <tr>
            <td>Department</td><td>:</td><td class="td-divider">{{ optional($bill->user->department)->name ?? 'Umum' }}</td>
            <td style="padding-left: 12px;">Bill Ref.</td><td>:</td><td class="fw-bold">{{ $bill->bill_number }}</td>
        </tr>
        <tr>
            <td style="padding-bottom: 8px;">Request Date</td><td style="padding-bottom: 8px;">:</td>
            <td class="td-divider" style="padding-bottom: 8px;">{{ date('d-M-y', strtotime($bill->created_at)) }}</td>
            <td style="padding-left: 12px; padding-bottom: 8px;">Due Date</td><td style="padding-bottom: 8px;">:</td>
            <td style="padding-bottom: 8px;">{{ $bill->due_date ? date('d-M-y', strtotime($bill->due_date)) : '-' }}</td>
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
            @foreach($bill->items as $index => $item)
                @php $qty = (float) ($item->qty ?? 1); @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="text-center break-text">@if($index === 0) {{ !empty($bill->vendor_invoice_number) ? wordwrap($bill->vendor_invoice_number, 14, " ", true) : '-' }} @endif</td>
                    <td><strong>{{ $item->name }}</strong> @if(!empty($item->description)) <br><span style="font-size: 9pt;">{!! strip_tags($item->description) !!}</span> @endif</td>
                    <td class="text-center">{{ $qty }} LS</td>
                    <td style="padding: 0 4px;">
                        <table class="amount-box"><tr><td class="curr-txt">{{ $currency }}</td><td class="num-txt">{{ number_format($item->amount, 0, ',', '.') }}</td></tr></table>
                    </td>
                    @if($index === 0)
                    <td rowspan="{{ $bill->items->count() + 1 }}" class="text-center break-text fw-bold" style="vertical-align: top; padding-top: 15px;">
                        {{ !empty($bill->account_number) ? wordwrap($bill->account_number, 14, " ", true) : '-' }}
                    </td>
                    @endif
                </tr>
            @endforeach
            <tr>
                <td colspan="4" class="text-center fw-bold">Total Amount</td>
                <td style="padding: 0 4px;"><table class="amount-box fw-bold"><tr><td class="curr-txt">{{ $currency }}</td><td class="num-txt">{{ number_format($bill->amount, 0, ',', '.') }}</td></tr></table></td>
            </tr>
        </tbody>
    </table>

    {{-- KOTAK TANDA TANGAN (DIGITAL / OTOMATIS) --}}
    @php
        $approvals = \App\Models\DocumentApproval::with('role')->where('document_id', $bill->id)->whereIn('document_type', ['App\Models\BillRequest', 'OPEX', 'BillRequest', get_class($bill)])->orderBy('step_order', 'asc')->get();
        $totalCols = 1 + $approvals->count();
        $prepSigBase64 = null;
        if ($bill->user && $bill->user->signature) {
            $path = public_path('storage/' . $bill->user->signature);
            if (file_exists($path)) { $prepSigBase64 = 'data:image/' . pathinfo($path, PATHINFO_EXTENSION) . ';base64,' . base64_encode(file_get_contents($path)); }
        }
    @endphp
    <table class="signature-table">
        <tr>
            <td style="width: {{ 100 / ($totalCols > 0 ? $totalCols : 1) }}%;">
                <div style="margin-bottom: 5px;">Prepared by :</div>
                <div style="height: 60px; text-align: center;">
                    @if($prepSigBase64)
                        <img src="{{ $prepSigBase64 }}" style="max-height: 60px; max-width: 140px; object-fit: contain;">
                    @else
                        <div style="display: inline-block; padding: 5px 10px; font-weight: bold; font-size: 10pt; border: 2px solid #198754; color: #198754; margin-top: 15px;">DIAJUKAN</div>
                    @endif
                </div>
                <div style="text-align: center; position: absolute; bottom: 10px; width: 100%; left: 0;">
                    <span class="fw-bold" style="text-decoration: underline;">{{ $bill->user->name ?? 'Requester' }}</span><br>
                    <span style="font-size: 7pt; color: #666;">{{ $bill->created_at ? $bill->created_at->format('d/m/y H:i') : '' }}</span>
                </div>
            </td>
            @foreach($approvals as $idx => $approval)
                @php
                    $approverUser = \App\Models\User::find($approval->approved_by);
                    $approverSigBase64 = null;
                    if ($approverUser && $approverUser->signature) {
                        $path = public_path('storage/' . $approverUser->signature);
                        if (file_exists($path)) { $approverSigBase64 = 'data:image/' . pathinfo($path, PATHINFO_EXTENSION) . ';base64,' . base64_encode(file_get_contents($path)); }
                    }
                    $roleName = optional($approval->role)->name ?? 'Manager';
                    $approverName = $roleName;
                    if ($approval->approved_by) {
                        if ($approverUser) $approverName = $approverUser->name;
                    } else {
                        $potentialUsers = \App\Models\User::role($roleName);
                        if (!empty($approval->target_department_id) && $approval->target_department_id !== 'all') { $potentialUsers->where('department_id', $approval->target_department_id); }
                        $firstUser = $potentialUsers->first();
                        if ($firstUser) $approverName = $firstUser->name;
                    }
                @endphp
                <td style="width: {{ 100 / $totalCols }}%;">
                    <div>{{ $loop->last ? 'Approved by :' : 'Checked by :' }}</div>

                    <div style="height: 60px; text-align: center;">
                        @if($approval->status == 'APPROVED')
                            @if($approverSigBase64)
                                <img src="{{ $approverSigBase64 }}" style="max-height: 60px; max-width: 140px; object-fit: contain;">
                            @else
                                <div style="display: inline-block; padding: 5px 10px; font-weight: bold; font-size: 10pt; border: 2px solid #0d6efd; color: #0d6efd; margin-top: 15px;">APPROVED</div>
                            @endif
                        @elseif($approval->status == 'REJECTED')
                            <div style="display: inline-block; padding: 5px 10px; font-weight: bold; font-size: 10pt; border: 2px solid #dc3545; color: #dc3545; margin-top: 15px;">REJECTED</div>
                        @else
                             <div style="display: inline-block; padding: 5px 10px; font-weight: bold; font-size: 10pt; border: 2px dashed #aaa; color: #aaa; margin-top: 15px;">PENDING</div>
                        @endif
                    </div>

                    <div style="text-align: center; position: absolute; bottom: 10px; width: 100%; left: 0;">
                        <span class="fw-bold" style="text-decoration: underline;">{{ $approverName }}</span><br>
                        <span style="font-size: 7pt; color: #666;">{{ $approval->approved_at ? date('d/m/y H:i', strtotime($approval->approved_at)) : '' }}</span>
                    </div>
                </td>
            @endforeach
        </tr>
    </table>
</body>
</html>
