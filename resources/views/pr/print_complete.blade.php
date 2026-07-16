<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dokumen Lengkap PR - {{ $pr->pr_number }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 10pt; color: #000; margin: 0; padding: 15px; }

        .company-name { font-size: 16pt; font-weight: bold; margin: 0; text-transform: uppercase; }
        .doc-title { font-size: 12pt; margin: 5px 0 15px 0; }

        table.bordered { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.bordered th, table.bordered td { border: 1px solid #000; padding: 6px 8px; vertical-align: top; }

        table.info-table { width: 100%; border-collapse: collapse; }
        table.info-table td { border: none; padding: 2px 0; vertical-align: top; }

        table.main th { text-align: center; font-weight: bold; background-color: #f4f4f4; }

        .desc-cell p { margin: 0; padding: 0; line-height: 1.2; }
        .desc-cell ul, .desc-cell ol { margin: 2px 0; padding-left: 20px; }

        .vendor-list { margin: 0; padding-left: 15px; font-size: 8pt; }
        .vendor-list li { margin-bottom: 5px; }
        .link-text { color: #0056b3; text-decoration: underline; word-break: break-all; }

        table.signature { margin-top: 20px; width: 100%; border-collapse: collapse; }
        table.signature td { border: 1px solid #000; height: 110px; position: relative; width: 33.33%; vertical-align: top; padding: 8px;}
        .sign-title { font-size: 9pt; }
        .sign-name { position: absolute; bottom: 10px; left: 0; right: 0; text-align: center; font-weight: bold; font-size: 10pt; }
        .sign-status { position: absolute; top: 35px; left: 0; right: 0; text-align: center; font-size: 8pt; font-weight: bold; }

        .watermark { position: fixed; top: 30%; left: 5%; width: 90%; text-align: center; font-size: 80pt; font-weight: bold; text-transform: uppercase; color: rgba(255, 0, 0, 0.15); transform: rotate(-45deg); z-index: -1000; }
    </style>
</head>
<body>

    @php
        $statusSlug = strtolower(optional($pr->status)->slug ?? $pr->status);
    @endphp

    @if(in_array($statusSlug, ['rejected', 'canceled', 'cancelled']))
        <div class="watermark">DIBATALKAN</div>
    @endif

    {{-- KOP SURAT --}}
    <div class="company-name">{{ $pr->company->name ?? 'Internal Company' }}</div>
    <div class="doc-title">PURCHASE REQUEST FORM (Lengkap)</div>

    {{-- KOTAK INFORMASI --}}
    <table class="bordered">
        <tr>
            <td width="50%">
                <table class="info-table">
                    <tr><td style="width: 100px;">Requester</td><td>: {{ $pr->user->name ?? 'Sistem' }}</td></tr>
                    <tr><td>Department</td><td>: {{ optional($pr->user->department)->name ?? 'Umum' }}</td></tr>
                    <tr><td>Request Date</td><td>: {{ date('d-M-y', strtotime($pr->request_date)) }}</td></tr>
                </table>
            </td>
            <td width="50%">
                <table class="info-table">
                    <tr><td style="width: 100px;">PR Number</td><td>: <strong>{{ $pr->pr_number }}</strong></td></tr>
                    <tr><td>Need Date</td><td>: {{ date('d-M-y', strtotime($pr->need_date)) }}</td></tr>
                    <tr><td>Purpose</td><td>: {{ $pr->description ?? '-' }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- TABEL ITEM & REFERENSI --}}
    <table class="bordered main">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="25%">Item & Spesifikasi</th>
                <th width="10%">Qty / UOM</th>
                <th width="60%">Referensi Penawaran (Vendors & Links)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pr->items as $index => $item)
            <tr>
                <td style="text-align: center;">{{ $index + 1 }}</td>
                <td class="desc-cell">
                    <strong>{{ optional($item->item)->name ?? 'Item Tidak Diketahui' }}</strong>
                    <div style="font-size: 8pt; color: #555; margin-top: 5px;">
                        {!! $item->specification ?? '-' !!}
                    </div>
                </td>
                <td style="text-align: center;">
                    <strong>{{ (float) $item->qty }}</strong><br>
                    <span style="font-size: 8pt;">
                        @php
                            $uomStr = $item->uom ?? 'Unit';

                            // Jika data tidak sengaja tersimpan sebagai JSON Object
                            if (is_string($uomStr) && str_starts_with(trim($uomStr), '{')) {
                                $uomObj = json_decode($uomStr);
                                // Ambil 'code' (cth: PCS) atau 'name' (cth: Pieces)
                                $uomStr = $uomObj->code ?? $uomObj->name ?? 'Unit';
                            } elseif (is_object($uomStr) || is_array($uomStr)) {
                                $uomStr = $uomStr->code ?? $uomStr->name ?? (is_array($uomStr) ? ($uomStr['code'] ?? 'Unit') : 'Unit');
                            }
                        @endphp
                        {{ strtoupper($uomStr) }}
                    </span>
                </td>
                <td>
                    @if($item->vendorQuotes && $item->vendorQuotes->count() > 0)
                        <ul class="vendor-list">
                        @foreach($item->vendorQuotes as $quote)
                            <li>
                                <strong>{{ optional($quote->vendor)->name ?? 'Vendor Tanpa Nama' }}</strong>
                                - {{ optional($quote->currency)->code ?? 'IDR' }} {{ number_format($quote->quoted_price, 2, ',', '.') }}

                                @if(!empty($quote->reference_link))
                                    <br>🔗 <a href="{{ $quote->reference_link }}" class="link-text" target="_blank">{{ $quote->reference_link }}</a>
                                @endif

                                @if(!empty($quote->notes))
                                    <br><span style="color: #666;"><em>Catatan: {{ $quote->notes }}</em></span>
                                @endif
                            </li>
                        @endforeach
                        </ul>
                    @else
                        <span style="font-size: 8pt; color: #999; font-style: italic;">Tidak ada penawaran/referensi vendor.</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- KOTAK TANDA TANGAN (MATRIKS) --}}
    @php
        $totalCols = 1 + $approvals->count();
    @endphp

    <table class="signature">
        <tr>
            <td style="width: {{ 100 / ($totalCols > 0 ? $totalCols : 1) }}%;">
                <div class="sign-title">Dibuat Oleh:</div>
                <div class="sign-status" style="color: #198754;">
                    Diajukan<br>{{ date('d/m/Y', strtotime($pr->created_at)) }}
                </div>
                <div class="sign-name">{{ $pr->user->name ?? 'Pemohon' }}<br><span style="font-size: 7.5pt; font-weight: normal;">Requester</span></div>
            </td>

            @foreach($approvals as $idx => $approval)
            <td style="width: {{ 100 / $totalCols }}%;">
                <div class="sign-title">{{ $loop->last ? 'Disetujui Oleh:' : 'Diperiksa Oleh:' }}</div>

                @if($approval->status == 'APPROVED')
                    <div class="sign-status" style="color: #198754;">
                        Telah Disetujui<br>{{ date('d/m/Y', strtotime($approval->approved_at)) }}
                    </div>
                @elseif($approval->status == 'REJECTED')
                    <div class="sign-status" style="color: #dc3545;">Ditolak</div>
                @endif

                <div class="sign-name">
                    @if($approval->status == 'APPROVED' || $approval->status == 'REJECTED')
                        {{ \App\Models\User::find($approval->approved_by)->name ?? optional($approval->role)->name }}
                    @else
                        ({{ optional($approval->role)->name ?? 'Atasan' }})
                    @endif
                </div>
            </td>
            @endforeach
        </tr>
    </table>

    {{-- 🔥 LAMPIRAN GAMBAR (MUNCUL DI HALAMAN BARU) 🔥 --}}
    @php
        $imageAttachments = [];
        foreach($pr->items as $item) {
            if($item->vendorQuotes) {
                foreach($item->vendorQuotes as $quote) {
                    if($quote->attachments) {
                        foreach($quote->attachments as $att) {
                            $ext = strtolower(pathinfo($att->file_name, PATHINFO_EXTENSION));
                            if(in_array($ext, ['jpg', 'jpeg', 'png'])) {
                                $imageAttachments[] = [
                                    'file' => $att,
                                    'vendor_name' => optional($quote->vendor)->name ?? 'Vendor',
                                    'item_name' => optional($item->item)->name ?? 'Item'
                                ];
                            }
                        }
                    }
                }
            }
        }
    @endphp

    @if(count($imageAttachments) > 0)
        <div style="page-break-before: always;"></div>
        <h3 style="margin-bottom: 20px; font-family: Arial, sans-serif;">Lampiran Gambar Penawaran & Referensi</h3>

        @foreach($imageAttachments as $imgData)
            <div style="margin-bottom: 30px; text-align: center; border-bottom: 1px dashed #ccc; padding-bottom: 20px;">
                <p style="font-size: 9pt; text-align: left; margin: 0 0 5px 0;">
                    <strong>Item:</strong> {{ $imgData['item_name'] }} <br>
                    <strong>Vendor:</strong> {{ $imgData['vendor_name'] }} <br>
                    <strong>File:</strong> {{ $imgData['file']->file_name }}
                </p>
                <img src="{{ public_path('storage/' . $imgData['file']->file_path) }}" style="max-width: 100%; max-height: 700px; border: 1px solid #000; padding: 5px;">
            </div>
        @endforeach
    @endif

</body>
</html>
