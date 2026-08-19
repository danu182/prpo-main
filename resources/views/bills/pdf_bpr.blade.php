<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Bank Payment Request - {{ $bill->bill_number }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 10pt; color: #000; margin: 0; padding: 15px; }
        .company-name { font-size: 16pt; font-weight: bold; margin: 0; text-transform: uppercase; }
        .doc-title { font-size: 12pt; margin: 5px 0 15px 0; }

        table.bordered { width: 100%; border-collapse: collapse; margin-bottom: -1px; table-layout: fixed; }
        table.bordered th, table.bordered td { border: 1px solid #000; padding: 6px 4px; vertical-align: middle; }

        table.info-table { width: 100%; border-collapse: collapse; }
        table.info-table td { border: none; padding: 2px 0; vertical-align: top; }

        table.main th { text-align: center; font-weight: bold; background-color: #fff; }
        table.main td { vertical-align: middle; }

        .watermark { position: fixed; top: 30%; left: 5%; width: 90%; text-align: center; font-size: 80pt; font-weight: bold; text-transform: uppercase; color: rgba(255, 0, 0, 0.15); transform: rotate(-45deg); z-index: -1000; }
        .watermark-paid { color: rgba(0, 128, 0, 0.15); }

        /* 🔥 PENGATURAN TANDA TANGAN DINAMIS 🔥 */
        table.signature { width: 100%; border-collapse: collapse; margin-top: 30px; page-break-inside: avoid; }
        table.signature td { border: 1px solid #000; padding: 10px; text-align: center; vertical-align: top; }
        .sign-title { font-size: 10pt; font-weight: bold; margin-bottom: 20px; color: #333; text-align: left;}

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
        .num-txt { text-align: right; font-size: 10pt; }
    </style>
</head>
<body>

    @php
        $statusSlug = strtolower(optional($bill->status)->slug ?? $bill->status);
        $currency = $bill->currency ?? 'IDR';
    @endphp

    @if(in_array($statusSlug, ['rejected', 'canceled', 'cancelled', 'void']))
        <div class="watermark">DIBATALKAN</div>
    @elseif(in_array($statusSlug, ['paid', 'lunas']))
        <div class="watermark watermark-paid">LUNAS / PAID</div>
    @endif

    {{-- KOP SURAT --}}
    @php
        $companyName = optional($bill->company)->name ?? 'PT. Kantor Pusat Internal';
    @endphp
    <div class="company-name">{{ $companyName }}</div>
    <div class="doc-title">Bank Payment Request Form</div>

    {{-- KOTAK INFORMASI ATAS --}}
    <table class="bordered" style="margin-bottom: 15px; table-layout: auto;">
        <tr>
            <td width="50%">
                <table class="info-table">
                    <tr><td style="width: 100px; font-weight:bold;">Requester</td><td>: {{ $bill->user->name ?? 'Sistem' }}</td></tr>
                    <tr><td style="font-weight:bold;">Department</td><td>: {{ optional($bill->user->department)->name ?? 'Umum' }}</td></tr>
                    <tr><td style="font-weight:bold;">Request Date</td><td>: {{ date('d-M-y', strtotime($bill->created_at)) }}</td></tr>
                </table>
            </td>
            <td width="50%">
                <table class="info-table">
                    <tr><td style="width: 130px; font-weight:bold;">Title</td><td>: {{ $bill->title ?? 'Tagihan Opex' }}</td></tr>
                    <tr><td style="font-weight:bold;">Bill Ref.</td><td>: {{ $bill->bill_number }}</td></tr>
                    <tr><td style="font-weight:bold;">Payment Due Date</td><td>: {{ $bill->due_date ? date('d-M-y', strtotime($bill->due_date)) : '-' }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- TABEL ITEM DETAIL (BPR RINGKAS) --}}
    <table class="bordered main">
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
            @foreach($bill->items as $index => $item)
                @php
                    $qty = (float) ($item->qty ?? 1);
                @endphp
                <tr>
                    <td style="text-align: center;">{{ $index + 1 }}</td>

                    <td style="text-align: center; color: #0d6efd;" class="break-text">
                        @if($index === 0)
                            {{ !empty($bill->vendor_invoice_number) ? wordwrap($bill->vendor_invoice_number, 16, " ", true) : '-' }}
                        @endif
                    </td>

                    <td>
                        <strong style="font-size: 13px;">{{ $item->name }}</strong>
                        @if(!empty($item->description))
                        <strong style="font-size: 13px;">{{ $item->description }}</strong>
                        @endif
                        {{-- @if(!empty($item->description)) --}}
                        {{-- <br><span style="font-size: 10px; color: #555;">{!! strip_tags($item->description) !!}</span> --}}
                        {{-- @endif --}}
                    </td>

                    <td style="text-align: center;">{{ $qty }} LS</td>

                    {{-- 🔥 Format Inner Table agar Mata Uang Lurus Mutlak 🔥 --}}
                    <td style="padding: 0; vertical-align: middle;">
                        <table class="amount-box">
                            <tr>
                                <td class="curr-txt">{{ $currency }}</td>
                                <td class="num-txt">{{ number_format($item->amount, 0, ',', '.') }}</td>
                            </tr>
                        </table>
                    </td>

                    <td style="text-align: center; font-weight: bold; color: #198754;" class="break-text">
                        @if($index === 0)
                            {{ !empty($bill->account_number) ? wordwrap($bill->account_number, 14, " ", true) : '-' }}
                        @endif
                    </td>
                </tr>
            @endforeach

            {{-- GRAND TOTAL --}}
            <tr>
                <td colspan="4" style="text-align: center; font-weight: bold; padding: 12px; font-size: 13px;">Total Amount</td>
                <td colspan="2" style="padding: 12px; background-color: #f8f9fa;">
                    <table class="amount-box">
                        <tr>
                            <td class="curr-txt" style="font-size: 10pt; font-weight: normal;">{{ $currency }}</td>
                            <td class="num-txt" style="font-weight: bold; font-size: 13px;">{{ number_format($bill->amount, 0, ',', '.') }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </tbody>
    </table>

    {{-- 🔥 KOTAK TANDA TANGAN DINAMIS (ANTI ERROR IMAGE DENGAN BASE64) 🔥 --}}
    @php
        $approvals = \App\Models\DocumentApproval::with('role')
            ->where('document_id', $bill->id)
            ->whereIn('document_type', ['App\Models\BillRequest', 'OPEX', 'BillRequest', get_class($bill)])
            ->orderBy('step_order', 'asc')
            ->get();

        $totalCols = 1 + $approvals->count();

        // Data Pembuat (Requester)
        $prepUser = $bill->user;
        $prepSigBase64 = null;

        if ($prepUser && $prepUser->signature) {
            $path = public_path('storage/' . $prepUser->signature);
            if (file_exists($path)) {
                $type = pathinfo($path, PATHINFO_EXTENSION);
                $data = file_get_contents($path);
                $prepSigBase64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
            }
        }

        $prepDept = optional($prepUser->department)->name ?? 'Umum';
        $prepRole = optional(optional($prepUser)->roles->first())->name ?? 'Staff';
    @endphp

    <table class="signature">
        <tr>
            {{-- KOLOM PEMBUAT (ISSUED) --}}
            <td style="width: {{ 100 / ($totalCols > 0 ? $totalCols : 1) }}%;">
                <div class="sign-title">Prepared by :</div>
                <div class="sign-box">
                    @if($prepSigBase64)
                        <img src="{{ $prepSigBase64 }}">
                    @else
                        <div class="stamp stamp-issued">ISSUED</div>
                    @endif
                </div>
                <div class="sign-name">{{ $bill->user->name ?? 'Tim Operasional' }}</div>
                <div class="sign-meta" style="font-weight: bold;">{{ $prepRole }}</div>
                <div class="sign-dept">{{ $prepDept }}</div>
                <div class="sign-meta" style="margin-top: 5px;">{{ $bill->created_at ? $bill->created_at->format('d/m/Y H:i') : '-' }}</div>
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
                    <div class="sign-title">{{ $loop->last ? 'Approved by :' : 'Checked by :' }}</div>

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
