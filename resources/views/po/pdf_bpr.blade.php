<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Bank Payment Request - {{ $po->po_number }}</title>
    <style>
        /* Pengaturan Dasar */
        body { font-family: Arial, Helvetica, sans-serif; font-size: 10pt; color: #000; margin: 0; padding: 15px; }

        /* Header Dokumen */
        .company-name { font-size: 16pt; font-weight: bold; margin: 0; text-transform: uppercase; }
        .doc-title { font-size: 12pt; margin: 5px 0 15px 0; }

        /* Tabel Utama dengan Border Hitam Pejat */
        table.bordered { width: 100%; border-collapse: collapse; margin-bottom: -1px; }
        table.bordered th, table.bordered td { border: 1px solid #000; padding: 6px 8px; vertical-align: top; }

        /* Tabel Informasi Header (Tanpa Border Dalam) */
        table.info-table { width: 100%; border-collapse: collapse; }
        table.info-table td { border: none; padding: 2px 0; vertical-align: top; }

        /* Tabel Item */
        table.main th { text-align: center; font-weight: bold; background-color: #fff; }
        table.main td { vertical-align: middle; }

        /* Penataan HTML Editor (Mencegah Jarak Berlebih) */
        .desc-cell p { margin: 0; padding: 0; line-height: 1.2; }
        .desc-cell ul, .desc-cell ol { margin: 2px 0; padding-left: 20px; }

        /* Trik Memisahkan Simbol Mata Uang dan Angka di Kolom Amount */
        table.currency { width: 100%; border-collapse: collapse; border: none; margin: 0; padding: 0; }
        table.currency td { border: none; padding: 0; margin: 0; background: transparent !important; }

        /* Tabel Tanda Tangan */
        table.signature { margin-top: -1px; }
        table.signature td { height: 110px; position: relative; }
        .sign-title { position: absolute; top: 8px; left: 8px; font-size: 9.5pt; }
        .sign-name { position: absolute; bottom: 10px; left: 0; right: 0; text-align: center; font-weight: bold; font-size: 10pt; }
        .sign-status { position: absolute; top: 40px; left: 0; right: 0; text-align: center; font-size: 8pt; font-weight: bold; }

        /* Watermark Background */
        .watermark { position: fixed; top: 30%; left: 5%; width: 90%; text-align: center; font-size: 80pt; font-weight: bold; text-transform: uppercase; color: rgba(255, 0, 0, 0.15); transform: rotate(-45deg); z-index: -1000; }
        .watermark-paid { color: rgba(0, 128, 0, 0.15); }
    </style>
</head>
<body>

    @php
        $statusSlug = strtolower(optional($po->status)->slug ?? $po->status);
    @endphp

    @if(in_array($statusSlug, ['rejected', 'canceled']))
        <div class="watermark">REJECTED</div>
    @elseif(in_array($statusSlug, ['paid', 'completed']))
        <div class="watermark watermark-paid">LUNAS / PAID</div>
    @endif

    {{-- KOP SURAT --}}
    <div class="company-name">{{ $po->company->name ?? 'DAMA' }}</div>
    <div class="doc-title">Bank Payment Request Form</div>

    {{-- KOTAK INFORMASI ATAS --}}
    <table class="bordered">
        <tr>
            <td width="50%">
                <table class="info-table">
                    <tr>
                        <td style="width: 100px;">Requester</td>
                        <td>: {{ $po->user->name ?? 'Super Administrator' }}</td>
                    </tr>
                    <tr>
                        <td>Department</td>
                        <td>: {{ optional($po->user->department)->name ?? 'Purchasing' }}</td>
                    </tr>
                    <tr>
                        <td>Request Date</td>
                        <td>: {{ date('d-M-y', strtotime($po->po_date ?? $po->created_at)) }}</td>
                    </tr>
                </table>
            </td>
            <td width="50%">
                <table class="info-table">
                    <tr>
                        <td style="width: 130px;">Title</td>
                        <td>: Pembayaran PO - {{ optional($po->vendor)->name ?? $po->vendor_name }}</td>
                    </tr>
                    <tr>
                        <td>Bill Ref.</td>
                        <td>: {{ $po->po_number }}</td>
                    </tr>
                    <tr>
                        <td>Payment Due Date</td>
                        <td>: {{ $po->delivery_date ? date('d-M-y', strtotime($po->delivery_date)) : '-' }}</td>
                    </tr>
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
                <th width="15%">Reference</th>
                <th width="20%">Total Amount ({{ $po->currency ?? 'Rp' }})</th>
                <th width="10%">Account No</th>
            </tr>
        </thead>
        <tbody>
            @foreach($po->items as $index => $item)
            <tr>
                <td style="text-align: center;">{{ $index + 1 }}</td>
                <td style="text-align: center;">{{ $po->vendor_invoice_number ?? '-' }}</td>

                {{-- 🔥 SOLUSI RENDER HTML: Menggunakan {!! !!} agar tag <p> ter-render dengan baik 🔥 --}}
                <td class="desc-cell">
                    {!! !empty($item->description) ? $item->description : (optional($item->item)->name ?? 'Item Belanja') !!}
                </td>

                <td></td>
                <td>
                    <table class="currency">
                        <tr>
                            <td style="text-align: left; width: 30%;">{{ $po->currency === 'IDR' ? 'Rp' : $po->currency }}</td>
                            <td style="text-align: right; width: 70%;">{{ number_format($item->amount ?? ($item->qty * $item->price), 0, ',', '.') }}</td>
                        </tr>
                    </table>
                </td>
                <td>{{ $index === 0 ? ($po->vendor_account ?? '') : '' }}</td>
            </tr>
            @endforeach

            {{-- BIAYA TAMBAHAN --}}
            @if(isset($po->total_charge) && $po->total_charge > 0)
            <tr>
                <td colspan="4" style="text-align: right;">Biaya Tambahan</td>
                <td>
                    <table class="currency">
                        <tr>
                            <td style="text-align: left; width: 30%;">{{ $po->currency === 'IDR' ? 'Rp' : $po->currency }}</td>
                            <td style="text-align: right; width: 70%;">{{ number_format($po->total_charge, 0, ',', '.') }}</td>
                        </tr>
                    </table>
                </td>
                <td></td>
            </tr>
            @endif

            {{-- DISKON --}}
            @if(isset($po->total_discount) && $po->total_discount > 0)
            <tr>
                <td colspan="4" style="text-align: right;">Diskon</td>
                <td>
                    <table class="currency">
                        <tr>
                            <td style="text-align: left; width: 30%;">{{ $po->currency === 'IDR' ? 'Rp' : $po->currency }}</td>
                            <td style="text-align: right; width: 70%;">({{ number_format($po->total_discount, 0, ',', '.') }})</td>
                        </tr>
                    </table>
                </td>
                <td></td>
            </tr>
            @endif

            {{-- PAJAK --}}
            @if(isset($po->total_tax) && $po->total_tax > 0)
            <tr>
                <td colspan="4" style="text-align: right;">Pajak (VAT/Ppn)</td>
                <td>
                    <table class="currency">
                        <tr>
                            <td style="text-align: left; width: 30%;">{{ $po->currency === 'IDR' ? 'Rp' : $po->currency }}</td>
                            <td style="text-align: right; width: 70%;">{{ number_format($po->total_tax, 0, ',', '.') }}</td>
                        </tr>
                    </table>
                </td>
                <td></td>
            </tr>
            @endif

            {{-- GRAND TOTAL --}}
            <tr>
                <td colspan="4" style="text-align: center; font-weight: bold;">Total Amount</td>
                <td style="font-weight: bold;">
                    <table class="currency">
                        <tr>
                            <td style="text-align: left; width: 30%;">{{ $po->currency === 'IDR' ? 'Rp' : $po->currency }}</td>
                            <td style="text-align: right; width: 70%;">{{ number_format($po->amount ?? $po->grand_total, 0, ',', '.') }}</td>
                        </tr>
                    </table>
                </td>
                <td></td>
            </tr>
        </tbody>
    </table>

    {{-- KOTAK TANDA TANGAN (BAWAH) --}}
    @php
        $approvals = \App\Models\DocumentApproval::with('role')
            ->where('document_id', $po->id)
            ->where('document_type', get_class($po))
            ->orderBy('step_order', 'asc')
            ->get();

        $totalCols = 1 + $approvals->count();
    @endphp

    <table class="bordered signature">
        <tr>
            <td style="width: {{ 100 / ($totalCols > 0 ? $totalCols : 1) }}%;">
                <div class="sign-title">Prepared by :</div>
                <div class="sign-name">{{ $po->user->name ?? 'Tim Purchasing' }}</div>
            </td>

            @foreach($approvals as $idx => $approval)
            <td style="width: {{ 100 / $totalCols }}%;">
                <div class="sign-title">{{ $loop->last ? 'Approved by :' : 'Checked by :' }}</div>

                @if($approval->status == 'APPROVED')
                    <div class="sign-status" style="color: #198754;">
                        Telah Disetujui<br>{{ date('d/m/Y', strtotime($approval->approved_at)) }}
                    </div>
                @elseif($approval->status == 'REJECTED')
                    <div class="sign-status" style="color: #dc3545;">
                        Ditolak
                    </div>
                @endif

                <div class="sign-name">
                    @if($approval->status == 'APPROVED')
                        {{ \App\Models\User::find($approval->approved_by)->name ?? optional($approval->role)->name }}
                    @else
                        @php
                            $roleName = optional($approval->role)->name ?? 'Atasan';
                            $deptName = '';
                            if (is_null($approval->target_department_id)) {
                                $deptName = optional($po->user->department)->name ?? '';
                            } elseif ($approval->target_department_id !== 'all' && $approval->target_department_id != 0) {
                                $deptObj = \App\Models\Department::find($approval->target_department_id);
                                $deptName = $deptObj ? $deptObj->name : '';
                            }
                        @endphp
                        ({{ $roleName }}{{ $deptName ? ' - ' . $deptName : '' }})
                    @endif
                </div>
            </td>
            @endforeach
        </tr>
    </table>

    {{-- 🔥 LAMPIRAN GAMBAR (MUNCUL DI HALAMAN BARU) 🔥 --}}
    @if(isset($po->attachments) && $po->attachments->count() > 0)
        @php
            $imageAttachments = $po->attachments->filter(function($att) {
                $ext = strtolower(pathinfo($att->file_name, PATHINFO_EXTENSION));
                return in_array($ext, ['jpg', 'jpeg', 'png']);
            });
        @endphp

        @if($imageAttachments->count() > 0)
            <div style="page-break-before: always;"></div>
            <h3 style="margin-bottom: 20px; font-family: Arial, sans-serif; color: #000;">Lampiran Dokumen Pendukung</h3>

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
