<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bank Payment Request</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #000;
        }
        .header-title {
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .sub-title {
            font-size: 14px;
            margin-bottom: 5px;
        }

        /* 1. KOTAK HEADER (ATAS) */
        table.header-section {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
            border-bottom: none;
        }
        table.header-section > tbody > tr > td {
            padding: 5px 10px;
            vertical-align: top;
        }
        table.header-section td.border-right {
            border-right: 1px solid #000;
        }

        .inner-table {
            width: 100%;
            border-collapse: collapse;
        }
        .inner-table td {
            padding: 2px 0;
            border: none !important;
        }

        /* 2. KOTAK ITEM (TENGAH) */
        table.bpr-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
        }
        table.bpr-table th, table.bpr-table td {
            border: 1px solid #000;
            padding: 6px;
        }

        /* 3. KOTAK TANDA TANGAN (BAWAH) - 🔥 SUDAH DIPERBAIKI 🔥 */
        table.sig-table {
            width: 100%;
            border-collapse: collapse;
        }
        table.sig-table td {
            border: 1px solid #000; /* Kembalikan garis penuh */
            border-top: none; /* Kecuali garis atas agar menyatu dengan Total Amount */
            padding: 8px 10px;
            vertical-align: top;
        }
        .sig-title {
            text-align: left;
            font-size: 11px;
        }
        .sig-name {
            text-align: center; /* Posisi nama persis di tengah */
            font-size: 11px;
            margin-top: 70px; /* Memaksa jarak tanda tangan seragam semua */
        }

        /* Trik Mata Uang */
        .currency-table {
            width: 100%;
            border-collapse: collapse;
        }
        .currency-table td {
            padding: 0 !important;
            border: none !important;
        }
    </style>
</head>
<body>

    <!-- JUDUL PERUSAHAAN & DOKUMEN -->
    <div class="header-title">{{ $bill->company->name ?? 'PT MAHAPALA MAHARDHIKA' }}</div>
    <div class="sub-title">Bank Payment Request Form</div>

    <!-- 1. KOTAK HEADER (REQUESTER & INFO) -->
    <table class="header-section">
        <tr>
            <td style="width: 50%;" class="border-right">
                <table class="inner-table">
                    <tr>
                        <td style="width: 25%;">Requester</td>
                        <td style="width: 75%;">: {{ $bill->user->name ?? 'Super Administrator' }}</td>
                    </tr>
                    <tr>
                        <td>Department</td>
                        <td>: {{ optional($bill->user->department)->name ?? 'IT' }}</td>
                    </tr>
                    <tr>
                        <td>Request Date</td>
                        <td>: {{ \Carbon\Carbon::parse($bill->invoice_date)->format('d-M-y') }}</td>
                    </tr>
                </table>
            </td>
            <td style="width: 50%;">
                <table class="inner-table">
                    <tr>
                        <td style="width: 30%;">Title</td>
                        <td style="width: 70%;">: {{ $bill->title ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td>&nbsp;</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td>Payment Due Date</td>
                        <td>: {{ \Carbon\Carbon::parse($bill->due_date)->format('d-M-y') }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- 2. KOTAK ITEM INVOICE -->
    <table class="bpr-table">
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">No</th>
                <th style="width: 20%; text-align: center;">Invoices No.</th>
                <th style="width: 30%; text-align: center;">Description</th>
                <th style="width: 10%; text-align: center;">Reference</th>
                <th style="width: 20%; text-align: center;">Total Amount (Rp)</th>
                <th style="width: 15%; text-align: center;">Account No</th>
            </tr>
        </thead>
        <tbody>
            @foreach($bill->items as $index => $item)
            <tr>
                <td style="text-align: center;">{{ $index + 1 }}</td>
                <td style="text-align: center;">{{ $bill->vendor_invoice_number ?? $bill->bill_number }}</td>
                <td>{!! nl2br(e($item->description ?? $item->name)) !!}</td>
                <td></td>
                <td>
                    <table class="currency-table">
                        <tr>
                            <td style="text-align: left;">Rp</td>
                            <td style="text-align: right;">{{ number_format($item->amount, 0, ',', '.') }}</td>
                        </tr>
                    </table>
                </td>
                <td style="text-align: center;">
                    {{ $bill->account_number ?? $bill->vendor_account ?? $bill->vendor_bank_account ?? '' }}
                </td>
            </tr>
            @endforeach

            <!-- BARIS TOTAL AMOUNT -->
            <tr>
                <td colspan="4" style="text-align: center; font-weight: bold;">Total Amount</td>
                <td style="font-weight: bold;">
                    <table class="currency-table">
                        <tr>
                            <td style="text-align: left;">Rp</td>
                            <td style="text-align: right;">{{ number_format($bill->amount, 0, ',', '.') }}</td>
                        </tr>
                    </table>
                </td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <!-- 3. KOTAK TANDA TANGAN -->
    @php
        $approvals = \App\Models\DocumentApproval::with(['role'])
            ->where('document_id', $bill->id)
            ->whereIn('document_type', ['App\Models\BillRequest', 'OPEX', 'BillRequest', get_class($bill)])
            ->orderBy('step_order', 'asc')
            ->get();

        $totalCols = $approvals->count() + 1;
        $colWidth = 100 / ($totalCols > 0 ? $totalCols : 1);
    @endphp

    <table class="sig-table">
        <tr>
            <!-- KOTAK PEMBUAT -->
            <td style="width: {{ $colWidth }}%;">
                <div class="sig-title">Prepared by :</div>
                <div class="sig-name">{{ $bill->user->name ?? '-' }}</div>
            </td>

            <!-- KOTAK APPROVER -->
            @foreach($approvals as $index => $appr)
            <td style="width: {{ $colWidth }}%;">
                <div class="sig-title">
                    {{ $index == ($approvals->count() - 1) ? 'Approved by :' : 'Checked by :' }}
                </div>

                <div class="sig-name">
                    @if($appr->status == 'APPROVED')
                        <span style="font-weight: bold;">{{ optional($appr->approver)->name ?? optional($appr->role)->name ?? 'Approved' }}</span>
                    @else
                        @php
                            $roleName = optional($appr->role)->name ?? 'Pending';
                            $deptName = '';
                            if (is_null($appr->target_department_id)) {
                                $deptName = is_object($bill->user->department) ? $bill->user->department->name : ($bill->user->department ?? '');
                            } elseif ($appr->target_department_id !== 'all' && $appr->target_department_id != 0) {
                                $deptObj = \App\Models\Department::find($appr->target_department_id);
                                $deptName = $deptObj ? $deptObj->name : '';
                            } elseif ($appr->target_department_id === 'all' || $appr->target_department_id === 0) {
                                $deptName = 'All Dept';
                            }
                        @endphp
                        <span style="color: #6c757d;">({{ $roleName }}{{ $deptName ? ' - ' . $deptName : '' }})</span>
                    @endif
                </div>
            </td>
            @endforeach
        </tr>
    </table>

</body>
</html>
