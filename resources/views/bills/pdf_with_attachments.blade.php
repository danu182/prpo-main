<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Bank Payment Request Form</title>
    <style>
        /* Pengaturan Margin Kertas & Font */
        @page { margin: 40px 40px 60px 40px; } /* Margin bawah disiapkan untuk Footer */
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10pt;
            color: #000;
            line-height: 1.3;
        }

        /* HEADER PERUSAHAAN & JUDUL */
        .company-name { font-size: 16pt; font-weight: bold; margin: 0 0 5px 0; text-transform: uppercase; color: #0056b3; }
        .doc-title { font-size: 14pt; font-weight: bold; margin: 0 0 15px 0; color: #333; text-transform: uppercase; }

        /* PENGATURAN FOOTER (TANGGAL & HALAMAN) */
        footer { position: fixed; bottom: -30px; left: 0px; right: 0px; height: 30px; }
        .footer-table {
            width: 100%; border-top: 1px dashed #888;
            padding-top: 5px; font-size: 8pt; color: #555; font-style: italic;
            border-collapse: collapse; border: none;
        }
        .footer-table td { border: none !important; padding: 0; }
        .pagenum:before { content: counter(page); }

        /* WATERMARK */
        .watermark { position: fixed; top: 30%; left: 5%; width: 90%; text-align: center; font-size: 80pt; font-weight: bold; text-transform: uppercase; color: rgba(255, 0, 0, 0.15); transform: rotate(-45deg); z-index: -1000; }
        .watermark-paid { color: rgba(0, 128, 0, 0.15); }

        /* PENGATURAN TABEL UMUM */
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        td, th { border: 1px solid #000; padding: 6px 8px; vertical-align: top; }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .fw-bold { font-weight: bold; }

        /* TABEL 1: INFO DOKUMEN */
        .info-table { page-break-inside: avoid; }
        .info-box { border: 1px solid #000; padding: 8px; }
        .info-box p { margin: 2px 0; }

        /* TABEL 2: ITEM RINCIAN */
        .item-table th { background-color: #f0f0f0; text-align: center; font-weight: bold; text-transform: uppercase; font-size: 9pt;}

        /* 🔥 UTILITY MATA UANG ANTI BERANTAKAN (INNER TABLE) 🔥 */
        .break-text { word-wrap: break-word; word-break: break-all; }
        table.amount-box { width: 100%; border: none !important; margin: 0; padding: 0; }
        table.amount-box td { border: none !important; padding: 0 !important; margin: 0 !important; background: transparent !important; vertical-align: middle; line-height: 1.2; }
        .curr-txt { text-align: left; font-size: 8.5pt; color: #666; white-space: nowrap; width: 1%; padding-right: 5px !important; }
        .num-txt { text-align: right; font-size: 10pt; }

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
    </style>
</head>
<body>

    @php
        $statusSlug = strtolower(optional($bill->status)->slug ?? $bill->status);
        $currency = $bill->currency ?? 'IDR';
    @endphp

    {{-- FOOTER --}}
    <footer>
        <table class="footer-table">
            <tr>
                <td style="text-align:left;">
                    Printed on: {{ date('d-M-Y H:i') }} WIB &nbsp;|&nbsp; Ref: {{ $bill->bill_number }}
                </td>
                <td style="text-align:right;">
                    Page <span class="pagenum"></span>
                </td>
            </tr>
        </table>
    </footer>

    {{-- WATERMARK --}}
    @if(in_array($statusSlug, ['rejected', 'canceled', 'void']))
        <div class="watermark">DIBATALKAN</div>
    @elseif(in_array($statusSlug, ['paid', 'lunas']))
        <div class="watermark watermark-paid">LUNAS / PAID</div>
    @endif

    {{-- KOP SURAT --}}
    <div style="border-bottom: 2px solid #0056b3; padding-bottom: 10px; margin-bottom: 20px;">
        <table style="border: none; margin: 0;">
            <tr>
                <td style="border: none; padding: 0; width: 60%;">
                    <h1 class="company-name">{{ $bill->company->name ?? 'Perusahaan Internal' }}</h1>
                    <p style="margin: 0; color: #666; font-size: 9pt;">Dokumen Pengajuan Biaya Operasional (OPEX)</p>
                </td>
                <td style="border: none; padding: 0; width: 40%; text-align: right; vertical-align: bottom;">
                    <h2 class="doc-title">PAYMENT REQUEST</h2>
                </td>
            </tr>
        </table>
    </div>

    {{-- KOTAK INFORMASI ATAS --}}
    <table class="info-table">
        <tr>
            <td width="48%" class="info-box">
                <p style="color: #666; font-size: 8pt; text-transform: uppercase; font-weight: bold; border-bottom: 1px solid #ccc; padding-bottom: 3px; margin-bottom: 5px;">Dibayarkan Kepada (Vendor):</p>
                <p style="font-size: 11pt;"><strong>{{ $bill->vendor_name }}</strong></p>
                <p>Keterangan: {{ $bill->description ?? '-' }}</p>
            </td>
            <td width="4%" style="border: none;"></td>
            <td width="48%" class="info-box" style="text-align: right;">
                <p style="color: #666; font-size: 8pt; text-transform: uppercase; font-weight: bold; border-bottom: 1px solid #ccc; padding-bottom: 3px; margin-bottom: 5px;">Informasi Dokumen:</p>
                <table style="border: none; margin: 0; width: 100%;">
                    <tr><td style="border: none; padding: 2px 0;">No. Dokumen</td><td style="border: none; padding: 2px 0;">: <strong>{{ $bill->bill_number }}</strong></td></tr>
                    <tr><td style="border: none; padding: 2px 0;">Tgl. Tagihan</td><td style="border: none; padding: 2px 0;">: <strong>{{ date('d M Y', strtotime($bill->invoice_date)) }}</strong></td></tr>
                    <tr><td style="border: none; padding: 2px 0;">Jatuh Tempo</td><td style="border: none; padding: 2px 0;">: <strong style="color: red;">{{ date('d M Y', strtotime($bill->due_date)) }}</strong></td></tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- TABEL ITEM DETAIL (BPR RINGKAS) --}}
    <table class="item-table">
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
                        <strong style="font-size: 11pt;">{{ $item->name }}</strong>
                        @if(!empty($item->description))
                            <br><span style="font-size: 9pt; color: #555;">{{ strip_tags($item->description) }}</span>
                        @endif
                        @if($item->discount_amount > 0 || $item->tax_amount > 0)
                            <br><span style="font-size: 8pt; color: #666;">
                            (Hrg: {{ number_format($item->price,0,',','.') }}
                            @if($item->discount_amount>0) | Disc: -{{ number_format($item->discount_amount,0,',','.') }} @endif
                            @if($item->tax_amount>0) | Tax: +{{ number_format($item->tax_amount,0,',','.') }} @endif)
                            </span>
                        @endif
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

            {{-- Biaya Tambahan (Charges) --}}
            @if($bill->charges->count() > 0)
                <tr><td colspan="6" style="background-color: #f9f9f9; font-weight: bold; font-size: 8pt;">BIAYA TAMBAHAN (CHARGES)</td></tr>
                @foreach($bill->charges as $charge)
                <tr>
                    <td colspan="4" style="text-align: right;">{{ optional($charge->chargeType)->name ?? 'Biaya Lainnya' }} {{ $charge->note ? '('.$charge->note.')' : '' }}</td>
                    <td style="padding: 0; vertical-align: middle;">
                        <table class="amount-box">
                            <tr>
                                <td class="curr-txt">{{ $currency }}</td>
                                <td class="num-txt">{{ number_format($charge->amount, 0, ',', '.') }}</td>
                            </tr>
                        </table>
                    </td>
                    <td></td>
                </tr>
                @endforeach
            @endif

            {{-- Potongan Tambahan (Discounts) --}}
            @if($bill->discounts->count() > 0)
                <tr><td colspan="6" style="background-color: #f9f9f9; font-weight: bold; font-size: 8pt; color: red;">POTONGAN / DISKON EKSTRA</td></tr>
                @foreach($bill->discounts as $discount)
                <tr>
                    <td colspan="4" style="text-align: right; color: red;">{{ optional($discount->discountType)->name ?? 'Diskon Lainnya' }} {{ $discount->note ? '('.$discount->note.')' : '' }}</td>
                    <td style="padding: 0; vertical-align: middle;">
                        <table class="amount-box">
                            <tr>
                                <td class="curr-txt" style="color: red;">{{ $currency }}</td>
                                <td class="num-txt" style="color: red;">-{{ number_format($discount->amount, 0, ',', '.') }}</td>
                            </tr>
                        </table>
                    </td>
                    <td></td>
                </tr>
                @endforeach
            @endif

            {{-- GRAND TOTAL --}}
            <tr>
                <td colspan="4" style="text-align: right; font-weight: bold; padding: 12px; font-size: 12px;">GRAND TOTAL</td>
                <td colspan="2" style="padding: 12px; background-color: #f4f8ff; border-top: 2px solid #0056b3;">
                    <table class="amount-box">
                        <tr>
                            <td class="curr-txt" style="font-size: 10pt; font-weight: bold; color: #0056b3;">{{ $currency }}</td>
                            <td class="num-txt" style="font-weight: bold; font-size: 14px; color: #0056b3;">{{ number_format($bill->amount, 0, ',', '.') }}</td>
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


    {{-- ========================================================================= --}}
    {{-- 🔥 HALAMAN BARU KHUSUS UNTUK LAMPIRAN (HANYA MUNCUL JIKA ADA FILE) 🔥 --}}
    {{-- ========================================================================= --}}
    @if(isset($attachments) && $attachments->count() > 0)

        {{-- Memaksa pindah ke halaman baru --}}
        <div style="page-break-before: always;"></div>

        <div style="font-family: sans-serif;">
            <h3 style="text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px;">
                LAMPIRAN DOKUMEN PENDUKUNG
            </h3>

            @foreach($attachments as $attachment)
                @php
                    $ext = strtolower(pathinfo($attachment->file_path, PATHINFO_EXTENSION));
                @endphp

                {{-- Jika file berupa gambar, tampilkan preview-nya --}}
                @if(in_array($ext, ['jpg', 'jpeg', 'png']))

                    <div style="margin-bottom: 30px; text-align: center;">
                        <p style="font-weight: bold; font-size: 10pt; text-align: left;">
                            Nama File: {{ $attachment->file_name ?? 'Lampiran' }}
                        </p>

                        @php
                            // Konversi ke Base64 agar DomPDF bisa membacanya dengan aman
                            $imagePath = public_path('storage/' . $attachment->file_path);
                            $imageData = '';
                            if (file_exists($imagePath)) {
                                $data = file_get_contents($imagePath);
                                $imageData = 'data:image/' . $ext . ';base64,' . base64_encode($data);
                            }
                        @endphp

                        @if($imageData)
                            <img src="{{ $imageData }}" style="max-width: 100%; max-height: 800px; border: 1px solid #ccc; padding: 5px;">
                        @else
                            <p style="color: red;">[Gambar tidak ditemukan di direktori storage]</p>
                        @endif
                    </div>

                @else
                    {{-- Jika file berupa PDF --}}
                    <div style="margin-bottom: 15px; padding: 10px; background-color: #f8f9fa; border: 1px solid #ddd;">
                        <p><strong>File:</strong> {{ $attachment->file_name ?? $attachment->file_path }}</p>
                        <p style="font-size: 9pt; color: green; font-weight: bold;">
                            <em>*Dokumen PDF ini telah digabungkan secara otomatis di halaman akhir cetakan ini.*</em>
                        </p>
                    </div>
                @endif
            @endforeach
        </div>
    @endif

</body>
</html>
