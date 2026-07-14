<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bukti Pembayaran - {{ $payment->payment_number }}</title>
    <style>
        body { font-family: 'Arial', sans-serif; font-size: 14px; color: #333; margin: 0; padding: 20px; }
        .voucher-container { max-width: 800px; margin: 0 auto; border: 2px solid #333; padding: 20px; position: relative; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        .header h2 { margin: 0; letter-spacing: 2px; text-transform: uppercase; }
        .header p { margin: 5px 0 0 0; font-size: 12px; }

        .row { display: flex; flex-wrap: wrap; margin-bottom: 15px; }
        .col-6 { width: 50%; }

        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .info-table td { padding: 8px; vertical-align: top; }
        .info-table td:first-child { width: 30%; font-weight: bold; }
        .info-table td:nth-child(2) { width: 2%; }

        .amount-box { background-color: #f8f9fa; border: 2px solid #333; padding: 15px; text-align: center; font-size: 24px; font-weight: bold; margin-bottom: 20px; }

        .signatures { display: flex; justify-content: space-between; margin-top: 50px; }
        .sign-box { text-align: center; width: 30%; }
        .sign-line { border-bottom: 1px solid #333; margin-top: 60px; margin-bottom: 5px; }

        .watermark { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-45deg); font-size: 100px; color: rgba(0,0,0,0.05); font-weight: bold; z-index: -1; text-transform: uppercase; }

        /* Hilangkan elemen yang tidak perlu saat di-print */
        @media print {
            body { padding: 0; }
            .voucher-container { border: none; padding: 0; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="voucher-container">
        <div class="watermark">PAID</div>

        <div class="header">
            <h2>BUKTI PENGELUARAN KAS</h2>
            <p><strong>(PAYMENT VOUCHER)</strong></p>
        </div>

        <div class="row">
            <div class="col-6">
                <strong>Dibayarkan Kepada:</strong><br>
                {{ optional($invoice->vendor)->name ?? 'Vendor Internal' }}<br>
                {{ optional($invoice->vendor)->address }}
            </div>
            <div class="col-6" style="text-align: right;">
                <strong>No. Voucher:</strong> {{ $payment->payment_number }}<br>
                <strong>Tanggal Bayar:</strong> {{ \Carbon\Carbon::parse($payment->payment_date)->format('d F Y') }}<br>
                <strong>No. Ref Tagihan:</strong> {{ $invoice->invoice_number }}
            </div>
        </div>

        <table class="info-table">
            <tr>
                <td>Metode Pembayaran</td>
                <td>:</td>
                <td>{{ optional($payment->paymentMethod)->name ?? 'Cek / Giro' }}</td>
            </tr>
            @if($payment->bank_name)
            <tr>
                <td>Bank / Ref</td>
                <td>:</td>
                <td>{{ $payment->bank_name }} (Ref: {{ $payment->reference_number ?? '-' }})</td>
            </tr>
            @endif
            <tr>
                <td>Keterangan Pembayaran</td>
                <td>:</td>
                <td>
                    Pembayaran tagihan nomor faktur <strong>{{ $invoice->vendor_invoice_number }}</strong>.
                    {{ $payment->notes ? ' Catatan: ' . $payment->notes : '' }}
                </td>
            </tr>
            <tr>
                <td>Sisa Tagihan (Setelah ini)</td>
                <td>:</td>
                <td>IDR {{ number_format($sisaTagihan, 0, ',', '.') }}</td>
            </tr>
        </table>

        <div class="amount-box">
            # IDR {{ number_format($payment->amount, 0, ',', '.') }} #
        </div>

        <div class="signatures">
            <div class="sign-box">
                Disiapkan Oleh,
                <div class="sign-line"></div>
                Finance / Treasury
            </div>
            <div class="sign-box">
                Disetujui Oleh,
                <div class="sign-line"></div>
                Finance Manager
            </div>
            <div class="sign-box">
                Diterima Oleh,
                <div class="sign-line"></div>
                Pihak Vendor
            </div>
        </div>
    </div>

</body>
</html>
