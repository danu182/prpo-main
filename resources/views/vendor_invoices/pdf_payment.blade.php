<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Bukti Pembayaran - {{ $payment->payment_number }}</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 12px; color: #333; line-height: 1.4; }
        .voucher-box { border: 1px solid #333; padding: 20px; position: relative; background: #fff; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 5px; margin-bottom: 15px; }
        .header h2 { margin: 0; font-size: 18px; letter-spacing: 1px; text-transform: uppercase; }
        .header p { margin: 3px 0 0 0; font-size: 10px; font-weight: bold; }

        .meta-table { width: 100%; margin-bottom: 15px; font-size: 11px; }
        .meta-table td { vertical-align: top; }

        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .info-table td { padding: 6px 4px; vertical-align: top; border-bottom: 1px dashed #ddd; }
        .info-table td.label { width: 28%; font-weight: bold; }
        .info-table td.colon { width: 2%; }

        .amount-box { border: 2px solid #333; background-color: #f9f9f9; padding: 12px; text-align: center; font-size: 20px; font-weight: bold; margin-bottom: 25px; }

        .signatures { width: 100%; margin-top: 30px; }
        .signatures td { text-align: center; width: 33.33%; font-size: 11px; }
        .sign-line { border-bottom: 1px solid #333; margin-top: 50px; margin-bottom: 4px; width: 80%; margin-left: auto; margin-right: auto; }

        .watermark { position: absolute; top: 35%; left: 35%; font-size: 80px; color: rgba(0,0,0,0.03); font-weight: bold; transform: rotate(-30deg); text-transform: uppercase; }

        /* Teknik Pemisah Halaman untuk Lampiran Berkas */
        .page-break { page-break-before: always; text-align: center; }
        .attachment-title { font-size: 14px; font-weight: bold; margin-bottom: 10px; border-bottom: 1px solid #ccc; padding-bottom: 5px; text-align: left; }
        .attachment-img { max-width: 100%; max-height: 700px; object-fit: contain; margin-top: 10px; border: 1px dashed #bbb; }
        .attachment-meta { font-size: 11px; color: #666; text-align: left; margin-bottom: 5px; }
    </style>
</head>
<body>

    <!-- HALAMAN 1: BUKTI PENGELUARAN KAS -->
    <div class="voucher-box">
        <div class="watermark">PAID</div>

        <div class="header">
            <h2>BUKTI PENGELUARAN KAS</h2>
            <p>(PAYMENT VOUCHER)</p>
        </div>

        <table class="meta-table">
            <tr>
                <td style="width: 50%;">
                    <strong>Dibayarkan Kepada:</strong><br>
                    <span style="font-size: 13px; font-weight: bold;">{{ optional($invoice->vendor)->name ?? 'Vendor Internal' }}</span><br>
                    {{ optional($invoice->vendor)->address ?? 'Alamat tidak tersedia' }}
                </td>
                <td style="width: 50%; text-align: right; line-height: 1.5;">
                    <strong>No. Voucher:</strong> {{ $payment->payment_number }}<br>
                    <strong>Tanggal Bayar:</strong> {{ \Carbon\Carbon::parse($payment->payment_date)->format('d F Y') }}<br>
                    <strong>No. Ref Tagihan:</strong> {{ $invoice->invoice_number }}
                </td>
            </tr>
        </table>

        <table class="info-table">
            <tr>
                <td class="label">Metode Pembayaran</td>
                <td class="colon">:</td>
                <td>{{ strtoupper($payment->payment_method) }}</td>
            </tr>
            @if($payment->bank_name)
            <tr>
                <td class="label">Bank Asal / Ref Transaksi</td>
                <td class="colon">:</td>
                <td>{{ $payment->bank_name }} (Ref: {{ $payment->reference_number ?? '-' }})</td>
            </tr>
            @endif
            <tr>
                <td class="label">Keterangan Tagihan</td>
                <td class="colon">:</td>
                <td>Pembayaran invoice fisik nomor <strong>{{ $invoice->vendor_invoice_number ?? '-' }}</strong>.</td>
            </tr>
            @if($payment->notes)
            <tr>
                <td class="label">Catatan Finance</td>
                <td class="colon">:</td>
                <td>{{ $payment->notes }}</td>
            </tr>
            @endif
            <tr>
                <td class="label">Sisa Hutang Tagihan</td>
                <td class="colon">:</td>
                <td style="color: {{ $sisaTagihan > 0 ? '#b91c1c' : '#15803d' }}; font-weight: bold;">
                    IDR {{ number_format($sisaTagihan, 0, ',', '.') }} {{ $sisaTagihan > 0 ? '(Belum Lunas)' : '(LUNAS)' }}
                </td>
            </tr>
        </table>

        <div class="amount-box">
            # IDR {{ number_format($payment->amount, 0, ',', '.') }} #
        </div>

        <table class="signatures">
            <tr>
                <td>
                    Disiapkan Oleh,
                    <div class="sign-line"></div>
                    Finance / Treasury
                </td>
                <td>
                    Disetujui Oleh,
                    <div class="sign-line"></div>
                    Finance Manager
                </td>
                <td>
                    Diterima Oleh,
                    <div class="sign-line"></div>
                    Pihak Vendor
                </td>
            </tr>
        </table>
    </div>

    <!-- HALAMAN BERIKUTNYA: LAMPIRAN (JIKA DIPILIH) -->
    @if($withAttachments && isset($payment->attachments) && $payment->attachments->count() > 0)
        @foreach($payment->attachments as $index => $file)
            @php
                $extension = strtolower(pathinfo($file->file_path, PATHINFO_EXTENSION));
                $isImage = in_array($extension, ['jpg', 'jpeg', 'png']);
                // Mengonversi path penyimpanan menjadi path absolut sistem agar bisa dibaca DomPDF secara internal
                $absolutePath = storage_path('app/public/' . $file->file_path);
            @endphp

            @if($isImage && file_exists($absolutePath))
                <div class="page-break">
                    <div class="attachment-title">Lampiran Berkas #{{ $index + 1 }}</div>
                    <div class="attachment-meta">Nama File: {{ $file->file_name }}</div>
                    <img src="{{ $absolutePath }}" class="attachment-img" alt="Lampiran">
                </div>
            @endif
        @endforeach
    @endif

</body>
</html>
