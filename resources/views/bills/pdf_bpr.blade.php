<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Bank Payment Request - {{ $bill->bill_number }}</title>
    <style>
        @page { margin: 40px 40px 60px 40px; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 10pt; color: #000; margin: 0; }

        .company-name { font-size: 16pt; font-weight: bold; margin: 0; text-transform: uppercase; }
        .doc-title { font-size: 12pt; margin: 2px 0 15px 0; }

        /* RESET TABEL */
        table { width: 100%; border-collapse: collapse; margin: 0; padding: 0; }

        /* 1. TABEL INFO (ATAS) */
        table.info-table { border: 1px solid #000; border-bottom: none; }
        table.info-table td { padding: 4px 8px; vertical-align: top; border: none; }
        .td-divider { border-right: 1px solid #000 !important; } /* Garis vertikal tengah */

        /* 2. TABEL ITEM (TENGAH) */
        table.main-table { border: 1px solid #000; }
        table.main-table th, table.main-table td { border: 1px solid #000; padding: 6px 8px; vertical-align: middle; }
        table.main-table th { text-align: center; font-weight: bold; background-color: #fff; }

        /* 3. TABEL TANDA TANGAN (BAWAH) */
        table.signature-table { border: 1px solid #000; border-top: none; page-break-inside: avoid; }
        table.signature-table td { border: none; padding: 10px; vertical-align: top; text-align: left; height: 110px; position: relative; } /* 🔥 PERBAIKAN: border diset 'none' agar garis vertikal hilang 🔥 */

        /* UTILITY */
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .fw-bold { font-weight: bold; }
        .break-text { word-wrap: break-word; word-break: break-all; }

        /* INNER TABLE UNTUK MATA UANG LURUS */
        table.amount-box { width: 100%; border: none !important; margin: 0; padding: 0; }
        table.amount-box td { border: none !important; padding: 0 !important; margin: 0 !important; vertical-align: middle; }
        .curr-txt { text-align: left; width: 1%; padding-right: 5px !important; }
        .num-txt { text-align: right; }

        /* FOOTER */
        footer { position: fixed; bottom: -30px; left: 0px; right: 0px; height: 30px; font-size: 8pt; color: #555; font-style: italic; }
    </style>
</head>
<body>

    <footer>
        * Dokumen ini dicetak otomatis oleh sistem pada {{ \Carbon\Carbon::now()->translatedFormat('d F Y H:i:s') }} WIB
    </footer>

    {{-- KOP SURAT --}}
    @php
        $companyName = optional($bill->company)->name ?? 'HITAWASANA';
        $currency = $bill->currency ?? 'IDR';
    @endphp
    <div class="company-name">{{ $companyName }}</div>
    <div class="doc-title">Bank Payment Request Form</div>

    {{-- ================================================================= --}}
    {{-- 1. KOTAK INFORMASI ATAS --}}
    {{-- ================================================================= --}}
    <table class="info-table">
        <tr>
            <td width="13%" style="padding-top: 8px;">Requester</td>
            <td width="2%" style="padding-top: 8px;">:</td>
            <td width="35%" class="td-divider" style="padding-top: 8px;">{{ $bill->user->name ?? 'System' }}</td>

            <td width="13%" style="padding-left: 12px; padding-top: 8px;">Title</td>
            <td width="2%" style="padding-top: 8px;">:</td>
            <td width="35%" style="padding-top: 8px;">{{ $bill->title ?? 'Tagihan Opex' }}</td>
        </tr>
        <tr>
            <td>Department</td>
            <td>:</td>
            <td class="td-divider">{{ optional($bill->user->department)->name ?? 'Umum' }}</td>

            <td style="padding-left: 12px;">Bill Ref.</td>
            <td>:</td>
            <td class="fw-bold">{{ $bill->bill_number }}</td>
        </tr>
        <tr>
            <td style="padding-bottom: 8px;">Request Date</td>
            <td style="padding-bottom: 8px;">:</td>
            <td class="td-divider" style="padding-bottom: 8px;">{{ date('d-M-y', strtotime($bill->created_at)) }}</td>

            <td style="padding-left: 12px; padding-bottom: 8px;">Payment Due Date</td>
            <td style="padding-bottom: 8px;">:</td>
            <td style="padding-bottom: 8px;">{{ $bill->due_date ? date('d-M-y', strtotime($bill->due_date)) : '-' }}</td>
        </tr>
    </table>

    {{-- ================================================================= --}}
    {{-- 2. TABEL ITEM DETAIL --}}
    {{-- ================================================================= --}}
    <table class="main-table">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="15%">Invoices No.</th>
                <th width="35%">Description</th>
                <th width="10%">Reference</th>
                <th width="20%">Total Amount</th>
                <th width="15%">Account No</th>
            </tr>
        </thead>
        <tbody>
            @foreach($bill->items as $index => $item)
                @php $qty = (float) ($item->qty ?? 1); @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>

                    <td class="text-center break-text">
                        @if($index === 0)
                            {{ !empty($bill->vendor_invoice_number) ? wordwrap($bill->vendor_invoice_number, 14, " ", true) : '-' }}
                        @endif
                    </td>

                    <td>
                        <strong>{{ $item->name }}</strong>
                        @if(!empty($item->description))
                            <br><span style="font-size: 9pt;">{!! strip_tags($item->description) !!}</span>
                        @endif
                    </td>

                    <td class="text-center">{{ $qty }} LS</td>

                    <td style="padding: 0 4px;">
                        <table class="amount-box">
                            <tr>
                                <td class="curr-txt">{{ $currency }}</td>
                                <td class="num-txt">{{ number_format($item->amount, 0, ',', '.') }}</td>
                            </tr>
                        </table>
                    </td>

                    {{-- 🔥 MERGE COLUMN: Hanya dipanggil di baris pertama dan merentang ke bawah 🔥 --}}
                    @if($index === 0)
                    <td rowspan="{{ $bill->items->count() + 1 }}" class="text-center break-text fw-bold" style="vertical-align: top; padding-top: 15px;">
                        {{ !empty($bill->account_number) ? wordwrap($bill->account_number, 14, " ", true) : '-' }}
                    </td>
                    @endif
                </tr>
            @endforeach

            {{-- GRAND TOTAL --}}
            <tr>
                <td colspan="4" class="text-center fw-bold">Total Amount</td>
                <td style="padding: 0 4px;">
                    <table class="amount-box fw-bold">
                        <tr>
                            <td class="curr-txt">{{ $currency }}</td>
                            <td class="num-txt">{{ number_format($bill->amount, 0, ',', '.') }}</td>
                        </tr>
                    </table>
                </td>
                {{-- Sel kosong Account No DIHAPUS karena sudah ditutup oleh Rowspan dari atas --}}
            </tr>
        </tbody>
    </table>

    {{-- ================================================================= --}}
    {{-- 3. KOTAK TANDA TANGAN (MANUAL / BASAH) --}}
    {{-- ================================================================= --}}
    @php
        $approvals = \App\Models\DocumentApproval::with('role')
            ->where('document_id', $bill->id)
            ->whereIn('document_type', ['App\Models\BillRequest', 'OPEX', 'BillRequest', get_class($bill)])
            ->orderBy('step_order', 'asc')
            ->get();

        $totalCols = 1 + $approvals->count();

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
    @endphp

    <table class="signature-table">
        <tr>
            {{-- KOLOM PEMBUAT --}}
            <td style="width: {{ 100 / ($totalCols > 0 ? $totalCols : 1) }}%;">
                <div style="margin-bottom: 5px;">Prepared by :</div>

                <div style="height: 60px; text-align: center;">
                    @if($prepSigBase64)
                        <img src="{{ $prepSigBase64 }}" style="max-height: 60px; max-width: 140px; object-fit: contain;">
                    @endif
                </div>

                <div style="text-align: center; position: absolute; bottom: 10px; width: 100%; left: 0;">
                    <span class="fw-bold" style="text-decoration: underline;">{{ $bill->user->name ?? 'Requester' }}</span>
                </div>
            </td>

            {{-- KOLOM APPROVAL BERANTAI --}}
            @foreach($approvals as $idx => $approval)
                @php
                    $roleName = optional($approval->role)->name ?? 'Manager';
                    $approverName = $roleName;

                    if ($approval->approved_by) {
                        $userAcc = \App\Models\User::find($approval->approved_by);
                        if ($userAcc) $approverName = $userAcc->name;
                    } else {
                        $potentialUsers = \App\Models\User::role($roleName);
                        if (!empty($approval->target_department_id) && $approval->target_department_id !== 'all') {
                            $potentialUsers->where('department_id', $approval->target_department_id);
                        }
                        $firstUser = $potentialUsers->first();
                        if ($firstUser) $approverName = $firstUser->name;
                    }
                @endphp

                <td style="width: {{ 100 / $totalCols }}%;">
                    <div>{{ $loop->last ? 'Approved by :' : 'Checked by :' }}</div>

                    {{-- DIBIARKAN KOSONG UNTUK TTD BASAH --}}

                    <div style="text-align: center; position: absolute; bottom: 10px; width: 100%; left: 0;">
                        <span class="fw-bold" style="text-decoration: underline;">{{ $approverName }}</span>
                    </div>
                </td>
            @endforeach
        </tr>
    </table>

</body>
</html>
