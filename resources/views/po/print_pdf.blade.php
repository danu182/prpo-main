<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Purchase Order - {{ $po->po_number }}</title>
    <style>
        body { font-family: 'Helvetica', Arial, sans-serif; font-size: 10pt; color: #333; margin: 0; padding: 0; }
        .header-table { width: 100%; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .company-name { font-size: 16pt; font-weight: bold; color: #0d6efd; text-transform: uppercase; margin: 0; }
        .company-address { font-size: 9pt; color: #555; margin-top: 5px; }
        .doc-title { font-size: 18pt; font-weight: bold; text-align: right; color: #333; margin: 0; }

        .info-table { width: 100%; margin-bottom: 20px; }
        .info-table td { vertical-align: top; width: 33.33%; padding-right: 15px; }
        .box-title { font-weight: bold; background-color: #f8f9fa; padding: 5px; border: 1px solid #ddd; font-size: 9pt; text-transform: uppercase;}
        .box-content { border: 1px solid #ddd; border-top: none; padding: 8px; font-size: 9pt; min-height: 80px;}

        .item-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .item-table th { background-color: #0d6efd; color: white; padding: 8px; font-size: 9pt; border: 1px solid #0d6efd; }
        .item-table td { padding: 6px 8px; border: 1px solid #ddd; font-size: 9pt; vertical-align: top; }
        .item-table .text-right { text-align: right; }
        .item-table .text-center { text-align: center; }

        .summary-table { width: 45%; float: right; border-collapse: collapse; }
        .summary-table td { padding: 5px 8px; border: 1px solid #ddd; font-size: 9pt; }
        .summary-table .bold { font-weight: bold; }
        .summary-table .bg-light { background-color: #f8f9fa; }

        .notes-section { width: 50%; float: left; font-size: 9pt; margin-top: 5px; }
        .notes-box { border: 1px dashed #aaa; padding: 10px; background-color: #fdfdfd; min-height: 50px; }

        .clear { clear: both; }

        .signature-table { width: 100%; margin-top: 50px; text-align: center; font-size: 9pt; page-break-inside: avoid; }
        .signature-table td { width: 33.33%; }
        .sign-area { height: 80px; }

        .signature-img { max-height: 55px; max-width: 110px; object-fit: contain; margin-top: 5px; }


        /* Pengaturan Margin Halaman PDF */
        @page { margin: 15mm 15mm 20mm 15mm; }

        /* Pengaturan Catatan Kaki (Fixed Footer) */
        footer {
            position: fixed;
            bottom: -15mm;
            left: 0;
            right: 0;
            height: 15mm;
            text-align: center;
            font-size: 8pt;
            color: #777;
        }

    </style>
</head>
<body>

    @php
        // Deteksi Tipe Cetakan (Default ke digital jika variabel tidak ada)
        $isDigital = (!isset($type) || $type === 'digital');
    @endphp

    {{-- 🔥 FOOTER DINAMIS 🔥 --}}
    <footer>
        @if($isDigital)
            <em>Dokumen digital ini diterbitkan secara elektronik oleh sistem ProcureApp pada {{ \Carbon\Carbon::now()->translatedFormat('d F Y H:i') }} WIB dan sah sebagai bukti pemesanan barang.</em>
        @else
            <em>Dokumen ini dicetak dari sistem ProcureApp pada {{ \Carbon\Carbon::now()->translatedFormat('d F Y H:i') }} WIB untuk ditandatangani secara manual.</em>
        @endif
    </footer>

    <table class="header-table">
        <tr>
            <td width="60%">
                <h1 class="company-name">{{ optional($po->company)->name ?? 'PT PERUSAHAAN KOMANDAN JAYA' }}</h1>
                <div class="company-address">{{ optional($po->company)->address ?? 'Gedung Pusat, Lt. 5, Jl. Sudirman No. 1, Jakarta' }}</div>
            </td>
            <td width="40%" style="text-align: right; vertical-align: bottom;">
                <h2 class="doc-title">PURCHASE ORDER</h2>
                <div style="font-size: 10pt; margin-top: 5px;">
                    <strong>No. PO:</strong> {{ $po->po_number }}<br>
                    <strong>Tanggal:</strong> {{ \Carbon\Carbon::parse($po->po_date)->format('d F Y') }}<br>
                    <strong>Ref PR:</strong> {{ optional($po->purchaseRequest)->pr_number ?? '-' }}
                </div>
            </td>
        </tr>
    </table>

    <table class="info-table">
        <tr>
            <td>
                <div class="box-title">KEPADA (VENDOR):</div>
                <div class="box-content">
                    <strong>{{ optional($po->vendor)->name ?? 'Vendor Tidak Diketahui' }}</strong><br>
                    {{ optional($po->vendor)->address ?? '-' }}<br>
                    PIC: {{ optional($po->vendor)->pic_name ?? '-' }}<br>
                    Telp: {{ optional($po->vendor)->phone ?? '-' }}<br>
                    Email: {{ optional($po->vendor)->email ?? '-' }}
                </div>
            </td>
            <td>
                <div class="box-title">KIRIM KE (SHIP TO):</div>
                <div class="box-content">
                    <strong>{{ optional($po->company)->name ?? 'Gudang Pusat' }}</strong><br>
                    {{ $po->shipping_address ?? optional($po->company)->address ?? 'Alamat tidak tersedia' }}
                </div>
            </td>
            <td>
                <div class="box-title">DETAIL PEMBAYARAN:</div>
                <div class="box-content">
                    Mata Uang: <strong>{{ $po->currency ?? 'IDR' }}</strong><br>
                    Termin Pembayaran: <strong>{{ $po->payment_terms ?? 'Cash On Delivery' }}</strong><br>
                    Estimasi Tiba: <strong>{{ $po->delivery_date ? \Carbon\Carbon::parse($po->delivery_date)->format('d M Y') : '-' }}</strong>
                </div>
            </td>
        </tr>
    </table>

    <table class="item-table">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="35%">Deskripsi Barang</th>
                <th width="10%">Qty</th>
                <th width="20%">Harga Satuan</th>
                <th width="10%">Disc/Tax</th>
                <th width="20%">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($po->items as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>
                        <strong>{{ optional($item->item)->code ?? '' }} - {{ optional($item->item)->name ?? 'Item Tidak Diketahui' }}</strong><br>
                        <span style="font-size: 8pt; color: #555;">{!! strip_tags($item->description) !!}</span>
                    </td>
                    <td class="text-center">{{ (float)$item->qty_ordered }} {{ $item->uom }}</td>
                    <td class="text-right">{{ number_format($item->unit_price, 0, ',', '.') }}</td>
                    <td class="text-right" style="font-size: 8pt; white-space: nowrap;">
                        @if($item->discount_amount > 0)
                            <span style="color: red;">D: {{ number_format($item->discount_amount, 0, ',', '.') }}</span><br>
                        @endif
                        @if($item->tax_amount > 0)
                            <span style="color: blue;">T: {{ number_format($item->tax_amount, 0, ',', '.') }}</span>
                        @endif
                        @if($item->discount_amount == 0 && $item->tax_amount == 0)
                            <div class="text-center">-</div>
                        @endif
                    </td>
                    <td class="text-right">{{ number_format($item->subtotal + $item->tax_amount, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="notes-section">
        <div style="font-weight: bold; margin-bottom: 5px;">Instruksi / Catatan:</div>
        <div class="notes-box">
            {!! $po->notes ? nl2br(e($po->notes)) : '<em>Tidak ada instruksi khusus.</em>' !!}
        </div>
    </div>

    <table class="summary-table">
        <tr>
            <td>Subtotal DPP</td>
            <td class="text-right">{{ $po->currency }} {{ number_format($po->subtotal, 0, ',', '.') }}</td>
        </tr>

        @if($po->discount_total > 0)
        <tr>
            <td>Total Diskon</td>
            <td class="text-right" style="color: red;">- {{ $po->currency }} {{ number_format($po->discount_total, 0, ',', '.') }}</td>
        </tr>
        @endif

        @if($po->tax_total > 0)
        <tr>
            <td>Total Pajak</td>
            <td class="text-right">{{ $po->currency }} {{ number_format($po->tax_total, 0, ',', '.') }}</td>
        </tr>
        @endif

        @if($charges->count() > 0)
            @foreach($charges as $charge)
                <tr>
                    <td>Biaya Tambahan: {{ $charge->name }}</td>
                    <td class="text-right">{{ $po->currency }} {{ number_format($charge->amount, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        @endif

        @if($extraDiscounts->count() > 0)
            @foreach($extraDiscounts as $ed)
                <tr>
                    <td>Potongan Ekstra: {{ $ed->name }}</td>
                    <td class="text-right" style="color: red;">- {{ $po->currency }} {{ number_format($ed->amount, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        @endif

        <tr class="bg-light">
            <td class="bold">GRAND TOTAL</td>
            <td class="text-right bold" style="font-size: 11pt;">{{ $po->currency }} {{ number_format($po->grand_total, 0, ',', '.') }}</td>
        </tr>
    </table>

    <div class="clear"></div>

    @php
        // Ambil data approval dan urutkan sesuai hierarki
        $approvals = $po->approvals->sortBy('step_order');

        // Hitung total kolom: 1 (Pembuat) + Jumlah Atasan + 1 (Vendor)
        $totalColumns = 2 + $approvals->count();

        // Bagi lebar kertas secara merata
        $tdWidth = (100 / $totalColumns) . '%';
    @endphp

    <table class="signature-table" style="width: 100%; margin-top: 50px; text-align: center; font-size: 9pt; page-break-inside: avoid;">
        <tr>
            {{-- 1. KOLOM PEMBUAT PO --}}
            <td style="width: {{ $tdWidth }}; vertical-align: bottom;">
                <div style="margin-bottom: 5px;"><strong>Dibuat Oleh,</strong></div>

                {{-- KOTAK TTD PEMBUAT --}}
                <div style="height: 65px; padding-top: 5px;">
                    @if($isDigital)
                        @php
                            $prepSigBase64 = null;
                            if (optional($po->user)->signature) {
                                $path = public_path('storage/' . $po->user->signature);
                                if (file_exists($path)) {
                                    $ext = pathinfo($path, PATHINFO_EXTENSION);
                                    $prepSigBase64 = 'data:image/' . $ext . ';base64,' . base64_encode(file_get_contents($path));
                                }
                            }
                        @endphp

                        @if($prepSigBase64)
                            <img src="{{ $prepSigBase64 }}" class="signature-img">
                        @else
                            <span style="color: #198754; border: 2px solid #198754; padding: 3px 8px; font-weight: bold; display: inline-block; margin-top: 15px; letter-spacing: 1px;">ISSUED</span>
                        @endif
                    @endif
                </div>

                <div style="margin-bottom: 2px;"><u><strong>{{ optional($po->user)->name ?? 'Purchasing Staff' }}</strong></u></div>
                <div style="font-size: 8pt; color: #555;">Purchasing Dept.</div>
                <div style="font-size: 7pt; color: #555;">
                    {{ $isDigital ? \Carbon\Carbon::parse($po->created_at)->format('d/m/Y') : 'Tgl: .....................' }}
                </div>
            </td>

            {{-- 2. KOLOM ATASAN (LOOPING DINAMIS) --}}
            @foreach($approvals as $app)
                <td style="width: {{ $tdWidth }}; vertical-align: bottom;">
                    <div style="margin-bottom: 5px;"><strong>Disetujui Oleh,</strong></div>

                    {{-- KOTAK TTD APPROVER --}}
                    <div style="height: 65px; padding-top: 5px;">
                        @if($isDigital)
                            @if(in_array(strtolower(optional($po->status)->slug), ['canceled', 'cancelled']))
                                <span style="color: #dc3545; border: 2px solid #dc3545; padding: 3px 8px; font-weight: bold; display: inline-block; margin-top: 15px; letter-spacing: 1px;">VOID</span>
                            @elseif($app->status === 'APPROVED')
                                @php
                                    $apprSigBase64 = null;
                                    if (optional($app->approver)->signature) {
                                        $path = public_path('storage/' . $app->approver->signature);
                                        if (file_exists($path)) {
                                            $ext = pathinfo($path, PATHINFO_EXTENSION);
                                            $apprSigBase64 = 'data:image/' . $ext . ';base64,' . base64_encode(file_get_contents($path));
                                        }
                                    }
                                @endphp

                                @if($apprSigBase64)
                                    <img src="{{ $apprSigBase64 }}" class="signature-img">
                                @else
                                    <span style="color: #0d6efd; border: 2px solid #0d6efd; padding: 3px 8px; font-weight: bold; display: inline-block; margin-top: 15px; letter-spacing: 1px;">APPROVED</span>
                                @endif
                            @elseif($app->status === 'REJECTED')
                                <span style="color: #dc3545; border: 2px solid #dc3545; padding: 3px 8px; font-weight: bold; display: inline-block; margin-top: 15px; letter-spacing: 1px;">REJECTED</span>
                            @else
                                <span style="color: #aaa; border: 2px dashed #aaa; padding: 3px 8px; font-weight: bold; display: inline-block; margin-top: 15px; letter-spacing: 1px;">PENDING</span>
                            @endif
                        @endif
                    </div>

                    @php
                        $roleName = optional($app->role)->name ?? 'Atasan';
                        $approverName = '.................................'; // Default untuk versi Manual / Belum Approve

                        // Coba nebak nama manager dari role & departemen terkait (Pre-fill name)
                        $potentialUsers = \App\Models\User::role($roleName);
                        if (!empty($app->target_department_id) && $app->target_department_id !== 'all') {
                            $potentialUsers->where('department_id', $app->target_department_id);
                        }
                        $firstUser = $potentialUsers->first();
                        
                        if ($app->status === 'APPROVED' || $app->status === 'REJECTED') {
                            $approverName = optional($app->approver)->name ?? 'Nama Tidak Terdata';
                        } elseif ($firstUser) {
                            $approverName = $firstUser->name;
                        }
                    @endphp

                    <div style="margin-bottom: 2px;"><u><strong>{{ $approverName }}</strong></u></div>
                    <div style="font-size: 8pt; color: #555;">{{ $roleName }}</div>
                    
                    @if($isDigital && in_array($app->status, ['APPROVED', 'REJECTED']))
                        <div style="font-size: 7pt; color: #555;">{{ \Carbon\Carbon::parse($app->approved_at)->format('d/m/Y') }}</div>
                    @else
                        <div style="font-size: 7pt; color: #555;">Tgl: .....................</div>
                    @endif
                </td>
            @endforeach

            {{-- 3. KOLOM VENDOR --}}
            <td style="width: {{ $tdWidth }}; vertical-align: bottom;">
                <div style="margin-bottom: 5px;"><strong>Dikonfirmasi Oleh,</strong></div>
                <div style="height: 65px; padding-top: 5px;"></div>
                <div style="margin-bottom: 2px;"><u><strong>.................................</strong></u></div>
                <div style="font-size: 8pt; color: #555;">Vendor Representative</div>
                <div style="font-size: 7pt; color: #555;">Tgl: .....................</div>
            </td>
        </tr>
    </table>

</body>
</html>