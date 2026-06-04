<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Keuangan OPEX</title>
    <style>
        body { font-family: Helvetica, sans-serif; font-size: 11px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .header h2 { margin: 0; padding: 0; font-size: 18px; }
        .header p { margin: 5px 0 0 0; font-size: 12px; color: #666; }

        .summary-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
        .summary-table td { padding: 8px; border: 1px solid #ddd; background-color: #f8f9fa; text-align: center; font-weight: bold;}

        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 6px 8px; text-align: left; }
        .data-table th { background-color: #e9ecef; text-align: center; text-transform: uppercase; font-size: 10px;}
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .badge { padding: 3px 6px; font-size: 9px; border: 1px solid #333; text-transform: uppercase;}
    </style>
</head>
<body>

    <div class="header">
        <h2>LAPORAN KEUANGAN OPEX</h2>
        <p>Periode: {{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} s/d {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}</p>
    </div>

    <table class="summary-table">
        <tr>
            <td width="33%">Total Tagihan: <br><span style="font-size:14px; color:#0d6efd;">Rp {{ number_format($totalExpense, 0, ',', '.') }}</span></td>
            <td width="33%">Telah Dibayar: <br><span style="font-size:14px; color:#198754;">Rp {{ number_format($totalPaid, 0, ',', '.') }}</span></td>
            <td width="33%">Sisa Hutang: <br><span style="font-size:14px; color:#dc3545;">Rp {{ number_format($totalUnpaid, 0, ',', '.') }}</span></td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="20%">No. Tagihan & Tgl</th>
                <th width="25%">Vendor & PT</th>
                <th width="15%">Tagihan (Rp)</th>
                <th width="15%">Dibayar (Rp)</th>
                <th width="20%">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($bills as $index => $bill)
                @php
                    $paidAmount = $bill->payments->sum('amount_paid');
                @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>
                        <strong>{{ $bill->bill_number }}</strong><br>
                        {{ \Carbon\Carbon::parse($bill->invoice_date)->format('d/m/Y') }}
                    </td>
                    <td>
                        <strong>{{ $bill->vendor_name }}</strong><br>
                        <span style="font-size: 10px; color: #555;">{{ $bill->company->name ?? '-' }}</span>
                    </td>
                    <td class="text-right">{{ number_format($bill->amount, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($paidAmount, 0, ',', '.') }}</td>
                    <td class="text-center">
                        <span class="badge">{{ optional($bill->status)->name ?? 'UNKNOWN' }}</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center">Tidak ada transaksi pada periode ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

</body>
</html>
