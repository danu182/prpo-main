<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>BPR - {{ $bill->bill_number }}</title>
    <style>
        @page { margin: 40px 40px 60px 40px; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 10pt; color: #000; margin: 0; }
        .company-name { font-size: 16pt; font-weight: bold; margin: 0; text-transform: uppercase; }
        .doc-title { font-size: 12pt; margin: 2px 0 15px 0; }

        /* 🔥 PERBAIKAN BORDER: Memastikan tabel tidak jebol ke samping 🔥 */
        table { width: 100%; border-collapse: collapse; margin: 0; padding: 0; table-layout: fixed; }

        /* 🔥 PERBAIKAN BORDER KOTAK INFORMASI 🔥 */
        table.info-table { border: 1px solid #000; border-bottom: none; width: 100%; }
        table.info-table td { padding: 4px 8px; vertical-align: top; border: none; word-wrap: break-word; }

        table.main-table { border: 1px solid #000; width: 100%; }
        table.main-table th, table.main-table td { border: 1px solid #000; padding: 6px 8px; vertical-align: middle; word-wrap: break-word; }
        table.main-table th { text-align: center; font-weight: bold; background-color: #fff; }

        table.signature-table { border: 1px solid #000; border-top: none; page-break-inside: avoid; width: 100%; }
        table.signature-table td { border: none; padding: 10px; vertical-align: top; text-align: left; height: 160px; position: relative; }

        .text-center { text-align: center; } .fw-bold { font-weight: bold; } .break-text { word-wrap: break-word; word-break: break-all; }

        /* Layout Harga */
        table.amount-box { width: 100%; border: none !important; margin: 0; padding: 0; table-layout: fixed; }
        table.amount-box td { border: none !important; padding: 0 !important; margin: 0 !important; vertical-align: middle; }
        .curr-txt { text-align: left; width: 25px !important; padding-right: 2px !important; }
        .num-txt { text-align: right; }

        footer { position: fixed; bottom: -30px; left: 0px; right: 0px; height: 30px; font-size: 8pt; color: #555; font-style: italic; }
    </style>
</head>
<body>
    <footer>Dokumen ini dicetak otomatis oleh sistem pada {{ \Carbon\Carbon::now()->translatedFormat('d F Y H:i:s') }} WIB - (Mode: {{ request('format', 'summary') }} | TTD: {{ request('signature', 'hybrid') }})</footer>

    @php
        $companyName = optional($bill->company)->name ?? 'HITAWASANA';
        $currencyCode = $bill->currency ?? 'IDR';
        $currencyData = \App\Models\Currency::where('code', $currencyCode)->first();
        $currency = $currencyData ? $currencyData->symbol : $currencyCode;

        // Parameter Mode dari URL
        $printFormat = request('format', 'summary');
        $sigMode = request('signature', 'hybrid');
    @endphp

    <div class="company-name">{{ $companyName }}</div>
    <div class="doc-title">Bank Payment Request Form </div>

    {{-- KOTAK INFORMASI --}}
    <table class="info-table">
        <tr>
            <td width="13%" style="padding-top: 8px;">Requester</td><td width="2%" style="padding-top: 8px;">:</td>
            <td width="35%" style="padding-top: 8px;">{{ $bill->user->name ?? 'System' }}</td>
            <td width="13%" style="padding-left: 12px; padding-top: 8px;">Title</td><td width="2%" style="padding-top: 8px;">:</td>
            <td width="35%" style="padding-top: 8px;">
                {{ !empty($bill->description) ? $bill->description . ' - ' . $bill->vendor_name : str_ireplace('Tagihan Opex - ', '', $bill->title ?? '-') }}
            </td>
        </tr>
        <tr>
            <td>Department</td><td>:</td><td>{{ optional($bill->user->department)->name ?? 'Umum' }}</td>
            <td style="padding-left: 12px;">Bill Ref.</td><td>:</td><td class="fw-bold">{{ $bill->bill_number }}</td>
        </tr>
        <tr>
            <td style="padding-bottom: 8px;">Request Date</td><td style="padding-bottom: 8px;">:</td>
            <td style="padding-bottom: 8px;">{{ $bill->invoice_date ? date('d F Y', strtotime($bill->invoice_date)) : '-' }}</td>
            <td style="padding-left: 12px; padding-bottom: 8px;">Due Date</td><td style="padding-bottom: 8px;">:</td>
            <td style="padding-bottom: 8px;">{{ $bill->due_date ? date('d F Y', strtotime($bill->due_date)) : '-' }}</td>
        </tr>
    </table>

    {{-- TABEL ITEM DETAIL --}}
    <table class="main-table">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="15%">Invoices No.</th>
                <th width="35%">Description</th>
                <th width="12%">Reference</th>
                <th width="20%">Total Amount</th>
                <th width="13%">Account No</th>
            </tr>
        </thead>
        <tbody>
            @foreach($bill->items as $index => $item)
                @php $qty = (float) ($item->qty ?? 1); @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="text-center break-text">@if($index === 0) {{ !empty($bill->vendor_invoice_number) ? wordwrap($bill->vendor_invoice_number, 14, " ", true) : '-' }} @endif</td>
                    <td>
                        {{ $item->name }}<br>
                        <span style="font-size: 9pt; color: #444;"><em>(Periode: {{ \Carbon\Carbon::parse($bill->invoice_date)->addMonths($bill->period_offset ?? 0)->translatedFormat('F Y') }})</em></span>
                        @if(!empty($item->description))
                            <br><span style="font-size: 9pt;">{!! nl2br(e($item->description)) !!}</span>
                        @endif
                    </td>
                    <td align="center">
                        @php
                            $satuan = 'LS';
                            if ($item->item) {
                                $namaSatuan = optional($item->item->uom)->name ?? optional($item->item->unit)->name ?? $item->item->uom_code ?? 'LS';
                                $satuan = trim(end(explode(' - ', $namaSatuan)));
                            }
                        @endphp
                        {{ $item->qty + 0 }} {{ $satuan }}
                    </td>
                    <td style="padding: 0 4px;">
                        <table class="amount-box"><tr><td class="curr-txt">{{ $currency }}</td><td class="num-txt">{{ number_format($item->amount, 0, ',', '.') }}</td></tr></table>
                    </td>
                    @if($index === 0)
                    <td rowspan="{{ $bill->items->count() }}" class="text-center break-text fw-bold" style="vertical-align: middle;">
                        {{ !empty($bill->account_number) ? wordwrap($bill->account_number, 14, " ", true) : '-' }}
                    </td>
                    @endif
                </tr>
            @endforeach

            {{-- 🔥 LOGIKA SMART SUMMARY 🔥 --}}
            @php
                // Hitung murni subtotal barang
                $subtotalGross = 0;
                $totalItemDiscount = 0;
                $totalItemTax = 0;

                foreach($bill->items as $itm) {
                    $gross = (float)$itm->qty * (float)$itm->price;
                    $itemDisc = ($itm->discount_type == 'fixed') ? (float)$itm->discount_value : ($gross * (float)$itm->discount_value / 100);
                    $itemTax = ($itm->tax_type == 'fixed') ? (float)$itm->tax_value : (($gross - $itemDisc) * (float)$itm->tax_value / 100);

                    $subtotalGross += $gross;
                    $totalItemDiscount += $itemDisc;
                    $totalItemTax += $itemTax;
                }
                $totalCharges = $bill->charges->sum('amount');
                $totalGlobalDiscount = $bill->discounts->sum('amount');

                // Cek apakah ada biaya siluman (selisih antara murni harga barang dan grand total)
                $hasExtraCosts = ($totalItemDiscount > 0 || $totalItemTax > 0 || $totalCharges > 0 || $totalGlobalDiscount > 0);

                // Paksa cetak rincian jika user minta 'detail', ATAU jika mode 'summary' tapi ternyata ada biaya ekstra
                $forcePrintDetails = ($printFormat == 'detail' || ($printFormat == 'summary' && $hasExtraCosts));
            @endphp

            @if($forcePrintDetails)
                <tr>
                    <td colspan="4" style="text-align: right; padding-right: 15px;">Subtotal Item</td>
                    <td style="padding: 0 4px;"><table class="amount-box"><tr><td class="curr-txt">{{ $currency }}</td><td class="num-txt">{{ number_format($subtotalGross, 0, ',', '.') }}</td></tr></table></td>
                    <td style="border-top: none; border-bottom: none;"></td>
                </tr>
                @if($totalItemDiscount > 0)
                <tr>
                    <td colspan="4" style="text-align: right; padding-right: 15px; color: red;">Diskon Item</td>
                    <td style="padding: 0 4px; color: red;"><table class="amount-box"><tr><td class="curr-txt">{{ $currency }}</td><td class="num-txt">- {{ number_format($totalItemDiscount, 0, ',', '.') }}</td></tr></table></td>
                    <td style="border-top: none; border-bottom: none;"></td>
                </tr>
                @endif
                @if($totalItemTax > 0)
                <tr>
                    <td colspan="4" style="text-align: right; padding-right: 15px;">Pajak</td>
                    <td style="padding: 0 4px;"><table class="amount-box"><tr><td class="curr-txt">{{ $currency }}</td><td class="num-txt">{{ number_format($totalItemTax, 0, ',', '.') }}</td></tr></table></td>
                    <td style="border-top: none; border-bottom: none;"></td>
                </tr>
                @endif
                @if($totalCharges > 0)
                <tr>
                    <td colspan="4" style="text-align: right; padding-right: 15px;">Biaya Ekstra (Lain-lain)</td>
                    <td style="padding: 0 4px;"><table class="amount-box"><tr><td class="curr-txt">{{ $currency }}</td><td class="num-txt">{{ number_format($totalCharges, 0, ',', '.') }}</td></tr></table></td>
                    <td style="border-top: none; border-bottom: none;"></td>
                </tr>
                @endif
                @if($totalGlobalDiscount > 0)
                <tr>
                    <td colspan="4" style="text-align: right; padding-right: 15px; color: red;">Potongan Harga Global</td>
                    <td style="padding: 0 4px; color: red;"><table class="amount-box"><tr><td class="curr-txt">{{ $currency }}</td><td class="num-txt">- {{ number_format($totalGlobalDiscount, 0, ',', '.') }}</td></tr></table></td>
                    <td style="border-top: none; border-bottom: none;"></td>
                </tr>
                @endif
            @endif

            {{-- BARIS GRAND TOTAL SELALU TAMPIL --}}
            <tr>
                <td colspan="4" class="fw-bold" style="text-align: right; padding-right: 15px;">GRAND TOTAL</td>
                <td style="padding: 0 4px; border-top: 1px solid #000;"><table class="amount-box fw-bold"><tr><td class="curr-txt">{{ $currency }}</td><td class="num-txt">{{ number_format($bill->amount, 0, ',', '.') }}</td></tr></table></td>
                @if($forcePrintDetails)
                    <td style="border-top: 1px solid #000;"></td>
                @else
                    {{-- Jika tidak ada rincian sama sekali, biarkan rowspan account numver mengisi celah ini otomatis --}}
                @endif
            </tr>
        </tbody>
    </table>

    {{-- KOTAK TANDA TANGAN DINAMIS --}}
    @php
        $approvals = \App\Models\DocumentApproval::with(['role'])
            ->where('document_id', $bill->id)
            ->whereIn('document_type', ['App\Models\BillRequest', 'OPEX', 'BillRequest', get_class($bill)])
            ->orderBy('step_order', 'asc')->get();

        $totalCols = 1 + $approvals->count();

        $getSignatureBase64 = function($signatureDbPath) {
            if (!$signatureDbPath) return null;
            $cleanPath = ltrim(str_replace('storage/', '', $signatureDbPath), '/');
            $paths = [storage_path('app/public/' . $cleanPath), public_path('storage/' . $cleanPath)];
            foreach($paths as $path) {
                if (file_exists($path)) return 'data:image/' . pathinfo($path, PATHINFO_EXTENSION) . ';base64,' . base64_encode(file_get_contents($path));
            }
            return null;
        };
    @endphp

    <table class="signature-table">
        <tr>
            {{-- KOLOM PEMBUAT --}}
            <td style="width: {{ 100 / ($totalCols > 0 ? $totalCols : 1) }}%;">
                <div style="margin-bottom: 10px;">Prepared by :</div>
                <div style="text-align: center;">
                    @if($sigMode != 'manual' && $bill->user && $bill->user->signature)
                        <img src="{{ $getSignatureBase64($bill->user->signature) }}" style="max-height: 70px; max-width: 140px; object-fit: contain;">
                    @endif
                </div>
                <div style="position: absolute; bottom: 25px; left: 0; width: 100%; text-align: center;">
                    <span class="fw-bold" style="text-decoration: underline;">{{ $bill->user->name ?? 'Requester' }}</span>
                </div>
                <div style="position: absolute; bottom: 10px; left: 0; width: 100%; text-align: center; font-size: 7pt; color: #666;">
                    {{ $sigMode != 'manual' && $bill->created_at ? $bill->created_at->format('d/m/y H:i') : '&nbsp;' }}
                </div>
            </td>

            {{-- KOLOM APPROVER --}}
            @foreach($approvals as $approval)
                <td style="width: {{ 100 / ($totalCols > 0 ? $totalCols : 1) }}%;">
                    <div style="margin-bottom: 10px; text-align: left;">
                        {{ $loop->last ? 'Approved by :' : ($approvals->count() > 2 ? 'Checked by ' . $loop->iteration . ' :' : 'Checked by :') }}
                    </div>
                    <div style="text-align: center;">
                        @php
                            $showSig = false;
                            $userTtd = $approval->approved_by ? \App\Models\User::find($approval->approved_by) : \App\Models\User::find($approval->user_id ?? $approval->approver_id ?? $approval->specific_user_id);

                            if ($sigMode == 'digital' && $userTtd) { $showSig = true; }
                            if ($sigMode == 'hybrid' && $approval->status == 'APPROVED' && $userTtd) { $showSig = true; }
                        @endphp

                        @if($showSig && $userTtd->signature)
                            <img src="{{ $getSignatureBase64($userTtd->signature) }}" style="max-height: 70px; max-width: 140px; object-fit: contain;">
                        @elseif($sigMode == 'hybrid' && $approval->status == 'APPROVED')
                            <div style="display: inline-block; padding: 5px 10px; font-weight: bold; font-size: 10pt; border: 2px solid #198754; color: #198754; margin-top: 15px;">APPROVED</div>
                        @elseif($sigMode == 'hybrid' && $approval->status != 'APPROVED')
                            <div style="color: gray; font-size: 10px; margin-top: 15px;">[ PENDING ]</div>
                        @endif
                    </div>

                    @php
                        $namaTtd = $userTtd ? $userTtd->name : trim((optional($approval->role)->name ?? 'Manager') . ' ' . (optional(\DB::table('departments')->where('id', $approval->target_department_id)->first())->name ?? ''));
                    @endphp
                    <div style="position: absolute; bottom: 25px; left: 0; width: 100%; text-align: center;">
                        <strong><u>{{ $namaTtd }}</u></strong>
                    </div>
                    <div style="position: absolute; bottom: 10px; left: 0; width: 100%; text-align: center; font-size: 7pt; color: #666;">
                        {{ ($sigMode != 'manual' && $approval->status == 'APPROVED' && $approval->approved_at) ? \Carbon\Carbon::parse($approval->approved_at)->format('d/m/y H:i') : '&nbsp;' }}
                    </div>
                </td>
            @endforeach
        </tr>
    </table>
</body>
</html>
