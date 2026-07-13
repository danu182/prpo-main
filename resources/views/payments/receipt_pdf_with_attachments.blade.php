<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kuitansi Pembayaran - {{ $payment->payment_number }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 10pt; color: #333; margin: 0; padding: 20px; }

        /* 🔥 Watermark Lunas 🔥 */
        .watermark { position: fixed; top: 35%; left: 10%; width: 80%; text-align: center; font-size: 80pt; font-weight: bold; text-transform: uppercase; color: rgba(34, 197, 94, 0.08); transform: rotate(-30deg); z-index: -1000; }

        /* Header Modern */
        .header-table { width: 100%; border-bottom: 3px double #0d6efd; padding-bottom: 15px; margin-bottom: 30px; }
        .company-name { font-size: 18pt; font-weight: 900; color: #0d6efd; text-transform: uppercase; margin: 0; }
        .doc-title { font-size: 22pt; font-weight: 900; text-align: right; color: #1e293b; text-transform: uppercase; margin: 0; letter-spacing: 2px; }
        .receipt-no { text-align: right; font-size: 10pt; color: #64748b; font-weight: bold; margin-top: 5px; }

        /* Tabel Konten Rapi */
        .content-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .content-table td { padding: 14px 10px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
        .label { font-weight: bold; color: #475569; width: 25%; text-transform: uppercase; font-size: 9pt; letter-spacing: 0.5px; }
        .separator { width: 3%; text-align: center; font-weight: bold; color: #94a3b8; }
        .value { font-size: 11pt; color: #0f172a; font-weight: 500; }

        /* Kotak Nominal Mewah */
        .amount-container { margin-top: 20px; margin-bottom: 50px; text-align: right; }
        .amount-box { display: inline-block; background-color: #f0fdf4; border: 2px solid #22c55e; padding: 15px 40px; font-size: 24pt; font-weight: 900; color: #16a34a; border-radius: 8px; box-shadow: 4px 4px 0px rgba(34, 197, 94, 0.2); }
        .amount-label { display: block; font-size: 10pt; color: #15803d; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; text-align: right; margin-right: 5px; font-weight: bold;}

        /* Area Tanda Tangan Ganda */
        .signature-table { width: 100%; margin-top: 50px; text-align: center; table-layout: fixed; }
        .signature-table td { width: 50%; vertical-align: bottom; }
        .sign-line { border-top: 1px solid #333; width: 60%; margin: 0 auto; margin-top: 80px; padding-top: 5px; font-weight: bold; font-size: 10pt; }
        .sign-role { font-size: 9pt; color: #64748b; margin-top: 2px; }
    </style>
</head>
<body>

    <div class="watermark">PAID / LUNAS</div>

    <table class="header-table">
        <tr>
            <td width="50%">
                <h1 class="company-name">{{ $payment->paidByCompany->name ?? 'Perusahaan Internal' }}</h1>
                <p style="margin: 5px 0 0 0; color: #64748b; font-size: 9pt;">Bukti Pembayaran / Official Receipt</p>
            </td>
            <td width="50%">
                <h2 class="doc-title">KUITANSI</h2>
                <div class="receipt-no">NO. REF: {{ $payment->payment_number }}</div>
            </td>
        </tr>
    </table>

    <table class="content-table">
        <tr>
            <td class="label">Tanggal Bayar</td>
            <td class="separator">:</td>
            <td class="value">{{ \Carbon\Carbon::parse($payment->payment_date)->format('d F Y') }}</td>
        </tr>
        <tr>
            <td class="label">Dibayarkan Kepada</td>
            <td class="separator">:</td>
            <td class="value"><strong style="font-size: 13pt;">{{ $payment->billRequest->vendor_name ?? '-' }}</strong></td>
        </tr>
        <tr>
            <td class="label">Guna Pembayaran</td>
            <td class="separator">:</td>
            <td class="value">
                Pembayaran Tagihan OPEX No. <strong>{{ $payment->billRequest->bill_number ?? '-' }}</strong>
                <br><span style="font-size: 9pt; color: #64748b; font-weight: normal;">(Deskripsi: {{ $payment->billRequest->title ?? '-' }})</span>
            </td>
        </tr>
        <tr>
            <td class="label">Metode Bayar</td>
            <td class="separator">:</td>
            <td class="value">
                {{ $payment->paymentMethod->name ?? '-' }}
                @if($payment->transaction_reference)
                    <span style="background-color: #e2e8f0; padding: 3px 10px; border-radius: 4px; font-size: 8pt; margin-left: 10px; font-weight: bold; color: #475569;">Ref: {{ $payment->transaction_reference }}</span>
                @endif
            </td>
        </tr>
        @if($payment->note)
        <tr>
            <td class="label">Keterangan / Note</td>
            <td class="separator">:</td>
            <td class="value" style="font-style: italic; color: #475569;">"{{ $payment->note }}"</td>
        </tr>
        @endif
    </table>

    <div class="amount-container">
        <span class="amount-label">Sejumlah Uang</span>
        <div class="amount-box">
            {{ $payment->billRequest->currency ?? 'IDR' }} {{ number_format($payment->amount_paid, 0, ',', '.') }}
        </div>
    </div>

    <table class="signature-table">
        <tr>
            <td>
                <p style="margin-bottom: 10px; font-weight: bold; color: #475569;">Penerima (Vendor),</p>
                <div class="sign-line">{{ $payment->billRequest->vendor_name ?? '-' }}</div>
                <p class="sign-role">Tanda Tangan & Cap Perusahaan</p>
            </td>
            <td>
                <p style="margin-bottom: 10px; font-weight: bold; color: #475569;">Pembayar (Finance),</p>
                <div class="sign-line">{{ $payment->paidByCompany->name ?? 'Finance Dept' }}</div>
                <p class="sign-role">Authorized Signature</p>
            </td>
        </tr>
    </table>



    {{-- ========================================================================= --}}
    {{-- 🔥 HALAMAN BARU KHUSUS UNTUK LAMPIRAN BUKTI BAYAR 🔥 --}}
    {{-- ========================================================================= --}}
    @if(isset($payment->attachments) && $payment->attachments->count() > 0)

        <div style="page-break-before: always;"></div>

        <div style="font-family: sans-serif;">
            <h3 style="text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px;">
                LAMPIRAN BUKTI PEMBAYARAN
            </h3>

            @foreach($payment->attachments as $attachment)
                @php
                    $ext = strtolower(pathinfo($attachment->file_path, PATHINFO_EXTENSION));
                @endphp

                @if(in_array($ext, ['jpg', 'jpeg', 'png']))
                    <div style="margin-bottom: 30px; text-align: center;">
                        <p style="font-weight: bold; font-size: 10pt; text-align: left;">
                            Nama File: {{ $attachment->file_name ?? 'Bukti Transfer' }}
                        </p>
                        @php
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
                            <p style="color: red;">[Gambar tidak ditemukan]</p>
                        @endif
                    </div>
                @else
                    <div style="margin-bottom: 15px; padding: 10px; background-color: #f8f9fa; border: 1px solid #ddd;">
                        <p><strong>File:</strong> {{ $attachment->file_name ?? $attachment->file_path }}</p>
                        <p style="font-size: 9pt; color: green; font-weight: bold;">
                            <em>*Dokumen PDF ini telah digabungkan secara otomatis di halaman akhir kuitansi ini.*</em>
                        </p>
                    </div>
                @endif
            @endforeach
        </div>
    @endif

</body>
</html>
