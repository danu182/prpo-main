<!DOCTYPE html>
<html>
<head>
    <title>Rekap Pembayaran - {{ $bill->bill_number }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; }
        .info-table { width: 100%; margin-bottom: 20px; }
        .main-table { width: 100%; border-collapse: collapse; }
        .main-table th, .main-table td { border: 1px solid #000; padding: 8px; text-align: left; }
        .text-right { text-align: right; }
        .badge { padding: 4px 8px; border: 1px solid #333; font-weight: bold; }
    </style>
</head>
<body>

    <div class="header">
        <h2>LAPORAN REKAPITULASI PEMBAYARAN</h2>
        <h3>{{ $bill->company->name }}</h3>
    </div>

    <table class="info-table">
        <tr>
            <td width="15%">No. Tagihan</td><td width="35%">: {{ $bill->bill_number }}</td>
            <td width="15%">Vendor</td><td width="35%">: {{ $bill->vendor_name }}</td>
        </tr>
        <tr>
            <td>Total Tagihan</td><td>: {{ $bill->currency }} {{ number_format($bill->amount, 0, ',', '.') }}</td>
            {{-- PERBAIKAN DI BARIS BAWAH INI --}}
            <td>Status</td><td>: <span class="badge">{{ strtoupper(optional($bill->status)->name ?? 'UNKNOWN') }}</span></td>
        </tr>
    </table>

    <table class="main-table">
        <thead style="background: #f0f0f0;">
            <tr>
                <th>No</th>
                <th>Tgl & Jam Bayar</th>
                <th>No. Voucher</th>
                <th>Metode</th>
                <th class="text-right">Nominal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($bill->payments as $index => $pay)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $pay->created_at->format('d/m/Y H:i') }}</td>
                <td>{{ $pay->payment_number }}</td>
                <td>{{ $pay->paymentMethod->name ?? '-' }}</td>
                <td class="text-right">{{ $bill->currency }} {{ number_format($pay->amount_paid, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="text-right"><strong>TOTAL DIBAYAR</strong></td>
                <td class="text-right"><strong>{{ $bill->currency }} {{ number_format($bill->payments->sum('amount_paid'), 0, ',', '.') }}</strong></td>
            </tr>
            <tr>
                <td colspan="4" class="text-right"><strong>SISA HUTANG</strong></td>
                <td class="text-right" style="color: red;"><strong>{{ $bill->currency }} {{ number_format($bill->amount - $bill->payments->sum('amount_paid'), 0, ',', '.') }}</strong></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
