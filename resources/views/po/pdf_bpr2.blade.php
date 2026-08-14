<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Bank Payment Request - {{ $po->po_number }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 10pt; color: #000; margin: 0; padding: 15px; }
        .company-name { font-size: 16pt; font-weight: bold; margin: 0; text-transform: uppercase; }
        .doc-title { font-size: 12pt; margin: 5px 0 15px 0; }
        table.bordered { width: 100%; border-collapse: collapse; margin-bottom: -1px; }
        table.bordered th, table.bordered td { border: 1px solid #000; padding: 6px 8px; vertical-align: top; }
        table.info-table { width: 100%; border-collapse: collapse; }
        table.info-table td { border: none; padding: 2px 0; vertical-align: top; }
        table.main th { text-align: center; font-weight: bold; background-color: #fff; }
        table.main td { vertical-align: middle; }
        table.currency { width: 100%; border-collapse: collapse; border: none; margin: 0; padding: 0; }
        table.currency td { border: none; padding: 0; margin: 0; background: transparent !important; }

        .watermark { position: fixed; top: 30%; left: 5%; width: 90%; text-align: center; font-size: 80pt; font-weight: bold; text-transform: uppercase; color: rgba(255, 0, 0, 0.15); transform: rotate(-45deg); z-index: -1000; }
        .watermark-paid { color: rgba(0, 128, 0, 0.15); }

        /* 🔥 PENGATURAN TANDA TANGAN & STEMPEL 🔥 */
        table.signature { width: 100%; border-collapse: collapse; margin-top: 30px; page-break-inside: avoid; }
        table.signature td { border: none; padding: 10px; text-align: center; vertical-align: top; }
        .sign-title { font-size: 10pt; font-weight: bold; margin-bottom: 20px; color: #333; }

        /* Area Kotak Tanda Tangan/Stempel */
        .sign-box { height: 65px; margin-bottom: 10px; }
        .sign-box img { max-height: 65px; max-width: 130px; }

        /* Desain Stempel Status */
        .stamp { display: inline-block; padding: 5px 12px; font-weight: bold; font-size: 11pt; letter-spacing: 1.5px; text-transform: uppercase; border: 2px solid; margin-top: 15px; }
        .stamp-issued { color: #198754; border-color: #198754; }
        .stamp-approved { color: #0d6efd; border-color: #0d6efd; }
        .stamp-rejected { color: #dc3545; border-color: #dc3545; }

        /* Teks Identitas */
        .sign-name { font-weight: bold; text-decoration: underline; font-size: 10pt; color: #000; }
        .sign-meta { font-size: 8.5pt; color: #555; margin-top: 3px; }
        .sign-dept { font-size: 8pt; color: #777; margin-top: 2px; }
    </style>
</head>
<body>

    @php
        $statusSlug = strtolower(optional($po->status)->slug ?? $po->status);
    @endphp

    @if(in_array($statusSlug, ['rejected', 'canceled', 'cancelled']))
        <div class="watermark">DIBATALKAN</div>
    @elseif(in_array($statusSlug, ['paid', 'completed']))
        <div class="watermark watermark-paid">LUNAS / PAID</div>
    @endif

    {{-- KOP SURAT --}}
    @php
        $companyName = optional($po->company)->name ?? optional(optional($po->purchaseRequest)->company)->name ?? 'PT. Kantor Pusat Internal';
    @endphp
    <div class="company-name">{{ $companyName }}</div>
    <div class="doc-title">Bank Payment Request Form</div>

    {{-- KOTAK INFORMASI ATAS --}}
    <table class="bordered">
        <tr>
            <td width="50%">
                <table class="info-table">
                    <tr><td style="width: 100px;">Requester</td><td>: {{ $po->user->name ?? 'Sistem' }}</td></tr>
                    <tr><td>Department</td><td>: {{ optional($po->user->department)->name ?? 'Purchasing' }}</td></tr>
                    <tr><td>Request Date</td><td>: {{ date('d-M-y', strtotime($po->po_date ?? $po->created_at)) }}</td></tr>
                </table>
            </td>
            <td width="50%">
                <table class="info-table">
                    <tr><td style="width: 130px;">Title</td><td>: Pembayaran PO - {{ optional($po->vendor)->name ?? $po->vendor_name }}</td></tr>
                    <tr><td>Bill Ref.</td><td>: {{ $po->po_number }}</td></tr>
                    <tr><td>Payment Due Date</td><td>: {{ $po->due_date ? date('d-M-y', strtotime($po->due_date)) : ($po->delivery_date ? date('d-M-y', strtotime($po->delivery_date)) : '-') }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- TABEL ITEM (TENGAH) --}}
    <table class="bordered main">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="15%">Invoices No.</th>
                <th width="35%">Description</th>
                {{-- 🔥 KOLOM REFERENCE DIUBAH MENJADI QTY 🔥 --}}
                <th width="10%">Qty</th>
                <th width="20%">Total Amount ({{ $po->currency ?? 'IDR' }})</th>
                <th width="15%">Account No</th>
            </tr>
        </thead>
        <tbody>
            @foreach($po->items as $index => $item)
                @php
                    $qty = (float) ($item->qty ?? $item->qty_ordered ?? $item->quantity ?? 0);
                    if ($qty <= 0) $qty = 1;

                    $hargaSatuan = (float) ($item->unit_price ?? $item->price ?? 0);
                    $subtotalDB = (float) ($item->subtotal ?? $item->total_price ?? ($qty * $hargaSatuan));

                    // Pembersihan string UOM agar rapi
                    $baseUomName = optional(optional($item->item)->uom)->name ?? 'PCS';
                    $uomStr = is_string($item->uom) ? $item->uom : (isset($item->uom->name) ? $item->uom->name : $baseUomName);
                    if (is_string($uomStr) && str_contains($uomStr, '{')) {
                        $uomDec = json_decode($uomStr);
                        $uomStr = $uomDec->name ?? $uomDec->code ?? $baseUomName;
                    }
                    $uomStr = preg_replace('/ \(Isi:.*\)/i', '', $uomStr);
                @endphp
                <tr>
                    <td style="text-align: center; vertical-align: top; padding-top: 10px;">{{ $index + 1 }}</td>
                    
                    {{-- Menampilkan Nomor Invoice --}}
                    <td style="text-align: center; vertical-align: top; padding-top: 10px; font-weight: bold; color: #0d6efd;">
                        {{ $index === 0 ? ($po->invoice_number ?: '-') : '' }}
                    </td>
                    
                    <td style="padding-top: 10px; padding-bottom: 10px;">
                        <div style="font-weight: bold; font-size: 13px; color: #000;">
                            {{ $item->item_name ?? optional($item->item)->name }}
                        </div>
                        @if(!empty($item->description) && $item->description !== '-')
                            <div style="font-size: 11px; color: #555; margin-top: 3px;">
                                {!! strip_tags($item->description) !!}
                            </div>
                        @endif
                    </td>
                    
                    {{-- 🔥 DATA QTY DITAMPILKAN DI SINI 🔥 --}}
                    <td style="text-align: center; vertical-align: top; padding-top: 10px; color: #333;">
                        <span style="font-weight: bold;">{{ $qty }}</span><br>
                        <span style="font-size: 9px;">{{ strtoupper($uomStr) }}</span>
                    </td>
                    
                    <td style="text-align: right; vertical-align: bottom; padding-bottom: 10px; font-weight: bold;">
                        Rp {{ number_format($subtotalDB, 0, ',', '.') }}
                    </td>
                    
                    {{-- Menampilkan Nomor Rekening --}}
                    <td style="text-align: center; vertical-align: top; padding-top: 10px; font-weight: bold; color: #198754;">
                        {{ $index === 0 ? ($po->account_number ?: '-') : '' }}
                    </td>
                </tr>
            @endforeach

            @php
                // Langsung ambil Final Grand Total dari Database
                $sumGrandTotal = (float)($po->grand_total ?? 0);
            @endphp

            {{-- 🔥 HANYA MENAMPILKAN GRAND TOTAL 🔥 --}}
            <tr>
                <td colspan="4" style="text-align: right; font-weight: bold; font-size: 14px; padding-top: 10px; padding-bottom: 10px;">GRAND TOTAL</td>
                <td style="text-align: right; font-weight: bold; font-size: 14px; padding-top: 10px; padding-bottom: 10px; background-color: #f8f9fa;">Rp {{ number_format($sumGrandTotal, 0, ',', '.') }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>

    {{-- 🔥 KOTAK TANDA TANGAN (PEMISAHAN BARIS JABATAN & DEPT) 🔥 --}}
    @php
        $approvals = \App\Models\DocumentApproval::with('role')
            ->where('document_id', $po->id)
            ->where('document_type', get_class($po))
            ->orderBy('step_order', 'asc')
            ->get();

        $totalCols = 1 + $approvals->count();

        // 1. Data Pembuat (Requester)
        $prepUser = $po->user;
        $prepSigPath = ($prepUser && $prepUser->signature && file_exists(public_path('storage/' . $prepUser->signature)))
                        ? public_path('storage/' . $prepUser->signature) : null;
        $prepDept = optional($prepUser->department)->name ?? 'Purchasing Dept.';
        $prepRole = optional(optional($prepUser)->roles->first())->name ?? 'Staff';
    @endphp

    <table class="signature">
        <tr>
            {{-- KOLOM PEMBUAT (ISSUED) --}}
            <td style="width: {{ 100 / ($totalCols > 0 ? $totalCols : 1) }}%;">
                <div class="sign-title">Dibuat Oleh,</div>
                <div class="sign-box">
                    @if($prepSigPath)
                        <img src="{{ $prepSigPath }}">
                    @else
                        <div class="stamp stamp-issued">ISSUED</div>
                    @endif
                </div>
                <div class="sign-name">{{ $po->user->name ?? 'Tim Purchasing' }}</div>
                <div class="sign-meta" style="font-weight: bold;">{{ $prepRole }}</div>
                <div class="sign-dept">{{ $prepDept }}</div>
                <div class="sign-meta" style="margin-top: 5px;">{{ $po->created_at ? $po->created_at->format('d/m/Y H:i') : '-' }}</div>
            </td>

            {{-- KOLOM APPROVAL BERANTAI --}}
            @foreach($approvals as $idx => $approval)
                @php
                    $approverUser = \App\Models\User::find($approval->approved_by);
                    $hasSignature = $approverUser && $approverUser->signature && file_exists(public_path('storage/' . $approverUser->signature));

                    // Tentukan Nama Jabatan (Role) dari Matriks
                    $roleName = optional($approval->role)->name ?? 'Manager';

                    // Tentukan Departemen
                    $deptName = '';
                    if (!empty($approval->target_department_id) && $approval->target_department_id !== 'all') {
                        $deptObj = \App\Models\Department::find($approval->target_department_id);
                        $deptName = $deptObj ? $deptObj->name : '';
                    } else {
                        if ($approverUser && $approverUser->department_id) {
                            $deptName = optional($approverUser->department)->name ?? '';
                        } else {
                            $deptName = optional($po->user->department)->name ?? ''; 
                        }
                    }

                    // Tentukan Nama Penyetuju
                    $approverName = '<span style="color:#aaa;">...................................................</span>';
                    if ($approval->status == 'APPROVED' || $approval->status == 'REJECTED') {
                        $approverName = $approverUser->name ?? $roleName;
                        if ($approverUser && $approverUser->job_title && !str_contains(strtolower($approverUser->name), 'super')) {
                            $roleName = $approverUser->job_title;
                        }
                    }
                @endphp
                <td style="width: {{ 100 / $totalCols }}%;">
                    <div class="sign-title">{{ $loop->last ? 'Disetujui Oleh,' : 'Diperiksa Oleh,' }}</div>

                    <div class="sign-box">
                        @if($approval->status == 'APPROVED')
                            @if($hasSignature)
                                <img src="{{ public_path('storage/' . $approverUser->signature) }}">
                            @else
                                <div class="stamp stamp-approved">APPROVED</div>
                            @endif
                        @elseif($approval->status == 'REJECTED')
                            <div class="stamp stamp-rejected">REJECTED</div>
                        @endif
                    </div>

                    <div class="sign-name">
                        {!! $approverName !!}
                    </div>

                    {{-- 🔥 ROLE & DEPT DIPISAH MENJADI 2 BARIS 🔥 --}}
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

    {{-- TIMESTAMP WAKTU CETAK DOKUMEN --}}
    <div style="margin-top: 25px; font-size: 8pt; color: #555; text-align: left; font-style: italic;">
        * Dokumen ini dicetak otomatis oleh sistem pada {{ \Carbon\Carbon::now()->translatedFormat('d F Y H:i:s') }} WIB
    </div>

    {{-- LAMPIRAN GAMBAR (MUNCUL DI HALAMAN BARU) --}}
    @php
        $imageAttachments = collect();

        if(isset($po->attachments)) {
            foreach($po->attachments as $att) {
                $ext = strtolower(pathinfo($att->file_name ?? $att->file_path, PATHINFO_EXTENSION));
                if(in_array($ext, ['jpg', 'jpeg', 'png'])) $imageAttachments->push($att);
            }
        }

        if(isset($po->items)) {
            foreach($po->items as $item) {
                if(isset($item->raw_attachments)) {
                    foreach($item->raw_attachments as $att) {
                        $ext = strtolower(pathinfo($att->file_name ?? $att->file_path, PATHINFO_EXTENSION));
                        if(in_array($ext, ['jpg', 'jpeg', 'png'])) $imageAttachments->push($att);
                    }
                }
            }
        }
    @endphp

    @if($imageAttachments->count() > 0)
        <div style="page-break-before: always;"></div>
        <h3 style="margin-bottom: 20px; font-family: Arial, sans-serif; color: #000;">Lampiran Dokumen Pendukung</h3>

        @foreach($imageAttachments as $img)
            <div style="margin-bottom: 20px; text-align: center; font-family: Arial, sans-serif;">
                <p style="font-size: 10pt; text-align: left;"><strong>Nama File:</strong> {{ $img->file_name ?? 'File' }}</p>
                <img src="{{ public_path('storage/' . $img->file_path) }}" style="max-width: 100%; max-height: 800px; border: 1px solid #000; padding: 5px;">
            </div>
        @endforeach
    @endif

</body>
</html>