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

        /* 1. KOTAK HEADER */
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

        /* 2. KOTAK TABEL INVOICE */
        table.bpr-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
        }
        table.bpr-table th, table.bpr-table td {
            border: 1px solid #000;
            padding: 6px;
        }

        /* 3. KOTAK TANDA TANGAN */
        table.sig-table {
            width: 100%;
            border-collapse: collapse;
        }
        table.sig-table td {
            border: 1px solid #000;
            border-top: none;
            padding: 8px 10px;
            vertical-align: top;
        }
        .sig-title {
            text-align: left;
            font-size: 11px;
        }
        .sig-name {
            text-align: center;
            font-size: 11px;
            margin-top: 70px;
        }

        /* Trik Mata Uang Rata Kiri Kanan */
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
                    <!-- 🔥 PERBAIKAN: Nomor Internal Sistem Dipindah Ke Sini 🔥 -->
                    <tr>
                        <td>Bill Ref.</td>
                        <td>: {{ $bill->bill_number }}</td>
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
            <!-- 🔥 PERBAIKAN: Looping Berdasarkan Jumlah Item 🔥 -->
            @foreach($bill->items as $index => $item)
            <tr>
                <td style="text-align: center;">{{ $index + 1 }}</td>

                <!-- Nomor Invoice dicetak di setiap baris atau bisa juga di baris pertama saja -->
                <td style="text-align: center;">{{ $bill->vendor_invoice_number ?? '-' }}</td>

                <!-- Ambil deskripsi, jika kosong ambil nama -->
                <td>{{ !empty($item->description) ? $item->description : $item->name }}</td>

                <td></td>

                <td>
                    <!-- Harga masing-masing item -->
                    <table class="currency-table">
                        <tr>
                            <td style="text-align: left;">Rp</td>
                            <td style="text-align: right;">{{ number_format($item->amount, 0, ',', '.') }}</td>
                        </tr>
                    </table>
                </td>

                <!-- Nomor rekening dimunculkan di baris pertama saja agar tabel tetap rapi -->
                <td style="text-align: center;">
                    {{ $index === 0 ? ($bill->account_number ?? $bill->vendor_account ?? $bill->vendor_bank_account ?? '') : '' }}
                </td>
            </tr>
            @endforeach

            <!-- BARIS TAMBAHAN: Muncul otomatis JIKA ada biaya ekstra,
                 agar Total Amount di bawahnya tetap masuk akal perhitungannya -->
            @if($bill->total_charge > 0)
            <tr>
                <td colspan="4" style="text-align: right; font-size: 10px; color: #555;">Biaya Tambahan</td>
                <td style="font-size: 10px; color: #555;">
                    <table class="currency-table">
                        <tr>
                            <td style="text-align: left;">Rp</td>
                            <td style="text-align: right;">{{ number_format($bill->total_charge, 0, ',', '.') }}</td>
                        </tr>
                    </table>
                </td>
                <td></td>
            </tr>
            @endif

            <!-- BARIS TOTAL AMOUNT (GRAND TOTAL) -->
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


    {{-- 🔥 LAMPIRAN GAMBAR (MUNCUL DI HALAMAN BARU) 🔥 --}}
    @if(isset($bill->attachments) && $bill->attachments->count() > 0)
        @php
            $imageAttachments = $bill->attachments->filter(function($att) {
                $ext = strtolower(pathinfo($att->file_name, PATHINFO_EXTENSION));
                return in_array($ext, ['jpg', 'jpeg', 'png']);
            });
        @endphp

        @if($imageAttachments->count() > 0)
            <div style="page-break-before: always;"></div>
            <h3 style="margin-bottom: 20px; font-family: Arial, sans-serif;">Lampiran Dokumen Pendukung</h3>

            @foreach($imageAttachments as $img)
                <div style="margin-bottom: 20px; text-align: center; font-family: Arial, sans-serif;">
                    <p style="font-size: 10pt; text-align: left;"><strong>Nama File:</strong> {{ $img->file_name }}</p>
                    <img src="{{ public_path('storage/' . $img->file_path) }}" style="max-width: 100%; max-height: 800px; border: 1px solid #000; padding: 5px;">
                </div>
            @endforeach
        @endif
    @endif

</body>
</html>
