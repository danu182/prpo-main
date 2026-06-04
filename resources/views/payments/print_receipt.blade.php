<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kuitansi Pembayaran - {{ $payment->payment_number }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 14px;
            color: #333;
            line-height: 1.5;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 30px;
        }
        .title {
            font-size: 22px;
            font-weight: bold;
            letter-spacing: 1px;
        }
        .subtitle {
            color: #666;
            font-size: 14px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            vertical-align: top;
        }
        .label {
            font-weight: bold;
            width: 30%;
        }
        .separator {
            width: 5%;
            text-align: center;
            font-weight: bold;
        }
        .amount-box {
            background-color: #f8f9fa;
            border: 2px dashed #ccc;
            padding: 20px;
            text-align: center;
            font-size: 28px;
            font-weight: bold;
            color: #198754;
            border-radius: 10px;
            margin-top: 30px;
        }
        .footer {
            margin-top: 60px;
            width: 100%;
        }
        .signature-box {
            float: right;
            width: 30%;
            text-align: center;
        }
        .signature-line {
            margin-top: 80px;
            border-bottom: 1px solid #000;
        }
        .clear {
            clear: both;
        }
    </style>
</head>
<body>

    <div class="header">
        <div class="title">BUKTI PEMBAYARAN (KUITANSI)</div>
        <div class="subtitle">No. Referensi: {{ $payment->payment_number }}</div>
    </div>

    <table>
        <tr>
            <td class="label">Tanggal Pembayaran</td>
            <td class="separator">:</td>
            <td>{{ \Carbon\Carbon::parse($payment->payment_date)->format('d F Y') }}</td>
        </tr>
        <tr>
            <td class="label">Telah Terima Dari</td>
            <td class="separator">:</td>
            <td>{{ $payment->paidByCompany->name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Dibayarkan Kepada</td>
            <td class="separator">:</td>
            <td>{{ $payment->billRequest->vendor_name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Metode Pembayaran</td>
            <td class="separator">:</td>
            <td>{{ $payment->paymentMethod->name ?? '-' }}
                @if($payment->transaction_reference)
                    <br><small>(Ref: {{ $payment->transaction_reference }})</small>
                @endif
            </td>
        </tr>
        <tr>
            <td class="label">Untuk Pembayaran</td>
            <td class="separator">:</td>
            <td>Tagihan Opex No. {{ $payment->billRequest->bill_number ?? '-' }}</td>
        </tr>
        @if($payment->note)
        <tr>
            <td class="label">Catatan / Keterangan</td>
            <td class="separator">:</td>
            <td>{{ $payment->note }}</td>
        </tr>
        @endif
    </table>

    <div class="amount-box">
        {{ $payment->billRequest->currency ?? 'IDR' }} {{ number_format($payment->amount_paid, 0, ',', '.') }}
    </div>

    <div class="footer">
        <div class="signature-box">
            <p>Finance / Kasir,</p>
            <div class="signature-line"></div>
            <p style="margin-top: 5px;">( Nama Terang & Tanda Tangan )</p>
        </div>
        <div class="clear"></div>
    </div>

</body>
</html>
