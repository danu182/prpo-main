<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tagihan OPEX - {{ $bill->bill_number }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 10pt; color: #333; margin: 0; padding: 20px; }

        /* 🔥 WATERMARK CSS (KHUSUS DOMPDF) 🔥 */
        .watermark {
            position: fixed;
            top: 30%;
            left: 5%;
            width: 90%;
            text-align: center;
            font-size: 80pt;
            font-weight: bold;
            text-transform: uppercase;
            color: rgba(255, 0, 0, 0.15); /* Merah Transparan */
            transform: rotate(-45deg);
            z-index: -1000;
        }
        .watermark-paid {
            color: rgba(0, 128, 0, 0.15); /* Hijau Transparan */
        }

        .header-table { width: 100%; border-bottom: 2px solid #0056b3; padding-bottom: 10px; margin-bottom: 20px; }
        .header-table td { vertical-align: middle; }
        .company-name { font-size: 16pt; font-weight: bold; color: #0056b3; text-transform: uppercase; margin: 0; }
        .doc-title { font-size: 18pt; font-weight: bold; text-align: right; color: #333; text-transform: uppercase; margin: 0; }

        .info-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
        .info-table td { vertical-align: top; padding: 5px; }
        .info-box { border: 1px solid #ddd; background-color: #f9f9f9; padding: 10px; border-radius: 5px; }
        .info-box p { margin: 2px 0; }

        .main-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .main-table th, .main-table td { border: 1px solid #ddd; padding: 8px; }
        .main-table th { background-color: #0056b3; color: #fff; text-align: left; font-size: 9pt; text-transform: uppercase; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        .summary-table { width: 40%; float: right; border-collapse: collapse; }
        .summary-table td { padding: 6px 8px; border-bottom: 1px solid #eee; }
        .summary-table .bold td { font-weight: bold; color: #000; }
        .summary-table .total td { font-weight: bold; font-size: 12pt; border-top: 2px solid #0056b3; border-bottom: 2px solid #0056b3; color: #0056b3; background-color: #f4f8ff; }

        .signature-table { width: 100%; margin-top: 80px; text-align: center; table-layout: fixed; }
        .signature-table td { vertical-align: bottom; padding: 0 10px; }
        .sign-line { border-top: 1px solid #000; width: 90%; margin: 0 auto; margin-top: 60px; padding-top: 5px; font-weight: bold; font-size: 9pt; }

        .clearfix { clear: both; }
        .status-stamp { display: inline-block; padding: 5px 15px; font-weight: bold; font-size: 14pt; transform: rotate(-10deg); margin-bottom: 10px; border: 3px solid; }
        .stamp-rejected { color: red; border-color: red; }
        .stamp-paid { color: green; border-color: green; }
        .stamp-approved { color: #0056b3; border-color: #0056b3; }
    </style>
</head>
<body>

    @php
        // Deteksi Status Slug (Anti Gagal)
        $statusSlug = strtolower(optional($bill->status)->slug ?? $bill->status);
    @endphp

    {{-- 🔥 WATERMARK BACKGROUND 🔥 --}}
    @if(in_array($statusSlug, ['rejected', 'canceled']))
        <div class="watermark">REJECTED</div>
    @elseif(in_array($statusSlug, ['paid']))
        <div class="watermark watermark-paid">LUNAS / PAID</div>
    @endif

    <table class="header-table">
        <tr>
            <td width="60%">
                <h1 class="company-name">{{ $bill->company->name ?? 'Perusahaan Internal' }}</h1>
                <p style="margin: 5px 0 0 0; color: #666;">Dokumen Pengajuan Biaya Operasional (OPEX)</p>
            </td>
            <td width="40%" class="text-right">
                <h2 class="doc-title">PAYMENT REQUEST</h2>
                <p style="margin: 5px 0 0 0;"><strong>No:</strong> {{ $bill->bill_number }}</p>
                <p style="margin: 2px 0 0 0;"><strong>Tanggal:</strong> {{ date('d M Y', strtotime($bill->invoice_date)) }}</p>
            </td>
        </tr>
    </table>

    <table class="info-table">
        <tr>
            <td width="48%" class="info-box">
                <p style="color: #666; font-size: 8pt; text-transform: uppercase; font-weight: bold; border-bottom: 1px solid #ccc; padding-bottom: 3px; margin-bottom: 5px;">Dibayarkan Kepada (Vendor):</p>
                <p><strong>{{ $bill->vendor_name }}</strong></p>
                <p>Keterangan: {{ $bill->description ?? '-' }}</p>
            </td>
            <td width="4%"></td>
            <td width="48%" class="info-box" style="text-align: right;">
                <p style="color: #666; font-size: 8pt; text-transform: uppercase; font-weight: bold; border-bottom: 1px solid #ccc; padding-bottom: 3px; margin-bottom: 5px;">Informasi Dokumen:</p>
                <p>Jatuh Tempo: <strong>{{ date('d M Y', strtotime($bill->due_date)) }}</strong></p>
                <p>Mata Uang: <strong>{{ $bill->currency }}</strong></p>
                <p>Siklus: <strong>{{ $bill->is_recurring ? 'Berulang ('. $bill->recurring_interval .' '. $bill->recurring_period .')' : 'Sekali Bayar' }}</strong></p>
            </td>
        </tr>
    </table>

    <div style="text-align: center; margin-bottom: 15px;">
        @if(in_array($statusSlug, ['paid']))
            <div class="status-stamp stamp-paid">LUNAS / PAID</div>
        @elseif(in_array($statusSlug, ['rejected', 'canceled']))
            <div class="status-stamp stamp-rejected">DITOLAK / REJECTED</div>
        @elseif(in_array($statusSlug, ['approved']))
            <div class="status-stamp stamp-approved">DISETUJUI / APPROVED</div>
        @endif
    </div>

    {{-- TABEL ITEM UTAMA --}}
    <table class="main-table">
        <thead>
            <tr>
                <th width="5%" class="text-center">No</th>
                <th width="45%">Deskripsi Item Jasa / Biaya</th>
                <th width="10%" class="text-center">Qty</th>
                <th width="20%" class="text-right">Harga Satuan</th>
                <th width="20%" class="text-right">Total Bersih</th>
            </tr>
        </thead>
        <tbody>
            @foreach($bill->items as $index => $item)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>
                    <strong>{{ $item->name }}</strong>
                    @if($item->description)<br><span style="font-size: 8pt; color: #666;">{{ $item->description }}</span>@endif
                    @if($item->discount_amount > 0 || $item->tax_amount > 0)
                        <br><span style="font-size: 8pt; color: #666;">
                        (Harga awal: {{ number_format($item->price,0,',','.') }}
                        @if($item->discount_amount>0) | Disc: -{{ number_format($item->discount_amount,0,',','.') }} @endif
                        @if($item->tax_amount>0) | Tax: +{{ number_format($item->tax_amount,0,',','.') }} @endif)
                        </span>
                    @endif
                </td>
                <td class="text-center">{{ $item->qty }}</td>
                <td class="text-right">{{ number_format($item->price, 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($item->amount, 0, ',', '.') }}</td>
            </tr>
            @endforeach

            @if($bill->charges->count() > 0)
                <tr><td colspan="5" style="background-color: #f9f9f9; font-weight: bold; font-size: 8pt;">BIAYA TAMBAHAN (CHARGES)</td></tr>
                @foreach($bill->charges as $charge)
                <tr>
                    <td></td>
                    <td colspan="3">{{ optional($charge->chargeType)->name ?? 'Biaya Lainnya' }} {{ $charge->note ? '('.$charge->note.')' : '' }}</td>
                    <td class="text-right">{{ number_format($charge->amount, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            @endif

            @if($bill->discounts->count() > 0)
                <tr><td colspan="5" style="background-color: #f9f9f9; font-weight: bold; font-size: 8pt; color: red;">POTONGAN / DISKON EKSTRA</td></tr>
                @foreach($bill->discounts as $discount)
                <tr>
                    <td></td>
                    <td colspan="3" style="color: red;">{{ optional($discount->discountType)->name ?? 'Diskon Lainnya' }} {{ $discount->note ? '('.$discount->note.')' : '' }}</td>
                    <td class="text-right" style="color: red;">({{ number_format($discount->amount, 0, ',', '.') }})</td>
                </tr>
                @endforeach
            @endif
        </tbody>
    </table>

    {{-- TABEL RINGKASAN BAWAH --}}
    <table class="summary-table">
        @php
            $grossItems = $bill->items->sum('subtotal');
            $totalItemDisc = $bill->items->sum('discount_amount');
            $totalExtDisc = $bill->discounts->sum('amount');
            $totalDiscounts = $totalItemDisc + $totalExtDisc;
        @endphp
        <tr>
            <td>Subtotal Item:</td>
            <td class="text-right">{{ number_format($grossItems, 0, ',', '.') }}</td>
        </tr>
        @if($totalDiscounts > 0)
        <tr>
            <td style="color: red;">Total Diskon:</td>
            <td class="text-right" style="color: red;">({{ number_format($totalDiscounts, 0, ',', '.') }})</td>
        </tr>
        @endif
        @if($bill->total_tax > 0)
        <tr>
            <td>Total Pajak:</td>
            <td class="text-right">{{ number_format($bill->total_tax, 0, ',', '.') }}</td>
        </tr>
        @endif
        @if($bill->total_charge > 0)
        <tr>
            <td>Biaya Tambahan:</td>
            <td class="text-right">{{ number_format($bill->total_charge, 0, ',', '.') }}</td>
        </tr>
        @endif
        <tr class="total">
            <td>GRAND TOTAL:</td>
            <td class="text-right">{{ $bill->currency }} {{ number_format($bill->amount, 0, ',', '.') }}</td>
        </tr>
    </table>

    <div class="clearfix"></div>

    {{-- 🔥 LOGIKA TANDA TANGAN DINAMIS 🔥 --}}
    @php
        $approvals = \App\Models\DocumentApproval::with('role')
            ->where('document_id', $bill->id)
            ->whereIn('document_type', [get_class($bill), 'OPEX', 'App\Models\BillRequest'])
            ->orderBy('step_order', 'asc')
            ->get();

        $totalColumns = 1 + $approvals->count();
        $colWidth = (100 / $totalColumns) . '%';
    @endphp

    <table class="signature-table">
        <tr>
            <td style="width: {{ $colWidth }};">
                <p style="margin-bottom: 50px;">Dibuat Oleh,</p>
                <div class="sign-line">{{ $bill->user->name ?? 'Admin System' }}</div>
                <p style="font-size: 8pt; color: #666; margin-top: 2px;">Pemohon / Requestor</p>
                <p style="font-size: 7pt; color: #999;">(Tersubmit di Sistem)</p>
            </td>

            @foreach($approvals as $approval)
            <td style="width: {{ $colWidth }};">
                <p style="margin-bottom: 50px;">
                    @if($loop->last) Disetujui Oleh, @else Diperiksa Oleh, @endif
                </p>

                <div class="sign-line">
                    @if($approval->status == 'APPROVED')
                        {{ \App\Models\User::find($approval->approved_by)->name ?? optional($approval->role)->name }}
                    @else
                        {{ optional($approval->role)->name ?? 'Atasan' }}
                    @endif
                </div>

                <p style="font-size: 8pt; color: #666; margin-top: 2px;">
                    @if($approval->status == 'APPROVED')
                        <span style="color: green;">Telah Disetujui</span>
                    @elseif($approval->status == 'REJECTED')
                        <span style="color: red;">Ditolak</span>
                    @else
                        Menunggu Persetujuan
                    @endif
                </p>

                @if($approval->approved_at)
                    <p style="font-size: 7pt; color: #999; margin: 0;">{{ date('d/m/Y H:i', strtotime($approval->approved_at)) }}</p>
                @endif
            </td>
            @endforeach
        </tr>
    </table>


    {{-- ========================================================================= --}}
    {{-- 🔥 HALAMAN BARU KHUSUS UNTUK LAMPIRAN (HANYA MUNCUL JIKA ADA FILE) 🔥 --}}
    {{-- ========================================================================= --}}
    @if(isset($bill->attachments) && $bill->attachments->count() > 0)

        {{-- Memaksa pindah ke halaman baru --}}
        <div style="page-break-before: always;"></div>

        <div style="font-family: sans-serif;">
            <h3 style="text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px;">
                LAMPIRAN DOKUMEN PENDUKUNG
            </h3>

            @foreach($bill->attachments as $attachment)
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
                    {{-- Jika file berupa PDF/Docx, hanya tampilkan teks informasinya --}}
                    <div style="margin-bottom: 15px; padding: 10px; background-color: #f8f9fa; border: 1px solid #ddd;">
                        <p><strong>File:</strong> {{ $attachment->file_name ?? $attachment->file_path }}</p>
                        <p style="font-size: 8pt; color: #666;">
                            <em>*File ini berformat non-gambar (PDF/Doc) sehingga tidak dapat disisipkan langsung ke lembar ini. Silakan unduh secara terpisah di sistem.*</em>
                        </p>
                    </div>
                @endif
            @endforeach
        </div>
    @endif

</body>
</html>
