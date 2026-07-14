<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kwitansi Pelunasan - {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 12px; color: #333; line-height: 1.5; }
        .receipt-box { border: 2px dashed #15803d; padding: 30px; position: relative; background: #fff; }
        .header { text-align: center; border-bottom: 3px double #15803d; padding-bottom: 10px; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 22px; color: #15803d; letter-spacing: 2px; }
        .header p { margin: 5px 0 0 0; font-size: 10px; text-transform: uppercase; color: #555; }

        .meta-section { width: 100%; margin-bottom: 25px; }
        .meta-section td { vertical-align: top; }

        .details-table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
        .details-table th { background-color: #f0fdf4; color: #15803d; text-align: left; padding: 8px; font-weight: bold; border-bottom: 2px solid #15803d; font-size: 11px; }
        .details-table td { padding: 8px; border-bottom: 1px solid #e5e7eb; vertical-align: middle; }

        .grand-total-box { border: 2px solid #15803d; background-color: #f0fdf4; padding: 15px; text-align: center; font-size: 22px; font-weight: bold; color: #15803d; margin-bottom: 30px; }

        .signatures { width: 100%; margin-top: 40px; }
        .signatures td { text-align: center; width: 50%; font-size: 12px; }
        .sign-line { border-bottom: 1px solid #333; margin-top: 60px; margin-bottom: 5px; width: 60%; margin-left: auto; margin-right: auto; }

        .stamp-paid { position: absolute; top: 15%; right: 5%; border: 4px double #15803d; color: #15803d; font-size: 24px; font-weight: bold; padding: 8px 20px; transform: rotate(15deg); border-radius: 4px; text-transform: uppercase; background: rgba(240, 253, 244, 0.8); }
    </style>
</head>
<body>

    <div class="receipt-box">
        <!-- CAP LUNAS RESMI -->
        <div class="stamp-paid">LUNAS / PAID</div>

        <div class="header">
            <h1>KWITANSI PELUNASAN TOTAL</h1>
            <p>(OFFICIAL RECEIPT OF FULL PAYMENT)</p>
        </div>

        <table class="meta-section">
            <tr>
                <td style="width: 50%;">
                    <strong>Telah Diterima Dari / Dibayarkan Kepada:</strong><br>
                    <span style="font-size: 14px; font-weight: bold; color: #111827;">{{ optional($invoice->vendor)->name ?? 'Vendor Internal' }}</span><br>
                    {{ optional($invoice->vendor)->address }}
                </td>
                <td style="width: 50%; text-align: right; line-height: 1.6;">
                    <strong>No. Kwitansi:</strong> RECEPT/{{ str_replace('/', '-', $invoice->invoice_number) }}<br>
                    <strong>Tanggal Pelunasan:</strong> {{ \Carbon\Carbon::parse($invoice->payments->last()->payment_date ?? now())->format('d F Y') }}<br>
                    <strong>No. Invoice Induk:</strong> {{ $invoice->invoice_number }}
                </td>
            </tr>
        </table>

        <p style="margin-bottom: 10px;">Rincian tahapan pembayaran/cicilan yang telah diselesaikan untuk nomor faktur vendor <strong>{{ $invoice->vendor_invoice_number ?? '-' }}</strong> adalah sebagai berikut:</p>

        <!-- TABEL RIWAYAT CICILAN -->
        <table class="details-table">
            <thead>
                <tr>
                    <th>No. Transaksi</th>
                    <th>Tanggal Bayar</th>
                    <th>Metode</th>
                    <th>Bank / Ref</th>
                    <th style="text-align: right;">Nominal Dicairkan</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->payments as $pay)
                <tr>
                    <td style="font-family: monospace; font-weight: bold;">{{ $pay->payment_number }}</td>
                    <td>{{ \Carbon\Carbon::parse($pay->payment_date)->format('d/m/Y') }}</td>
                    <td>{{ strtoupper($pay->payment_method) }}</td>
                    <td>{{ $pay->bank_name ?? '-' }} <span style="font-size: 10px; color: #666;">({{ $pay->reference_number ?? '-' }})</span></td>
                    <td style="text-align: right; font-weight: bold;">IDR {{ number_format($pay->amount, 0, ',', '.') }}</td>
                </tr>
                @endforeach
                <tr style="background-color: #f9fafb;">
                    <td colspan="4" style="text-align: right; font-weight: bold; border-top: 2px solid #15803d;">TOTAL PELUNASAN Bersih:</td>
                    <td style="text-align: right; font-weight: bold; color: #15803d; border-top: 2px solid #15803d;">IDR {{ number_format($invoice->grand_total, 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>

        <div class="grand-total-box">
            ## TERBILANG LUNAS: IDR {{ number_format($invoice->grand_total, 0, ',', '.') }} ##
        </div>

        <table class="signatures">
            <tr>
                <td>
                    Akuntan / Kasir Perusahaan,
                    <div class="sign-line"></div>
                    Finance Department
                </td>
                <td>
                    Mengetahui / Menyetujui,
                    <div class="sign-line"></div>
                    Finance & Admin Manager
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
