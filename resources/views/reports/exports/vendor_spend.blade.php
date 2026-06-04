<table>
    <tr>
        <td colspan="5" style="text-align: center; font-weight: bold; font-size: 14px;">LAPORAN ANALISIS TOP SPEND PER VENDOR</td>
    </tr>
    <tr>
        <td colspan="5" style="text-align: center;">Periode: {{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} s/d {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}</td>
    </tr>
    <tr>
        <td colspan="5"></td>
    </tr>
    <tr>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #198754; color: #ffffff;">Ranking</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #198754; color: #ffffff;">Nama Vendor / Supplier</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #198754; color: #ffffff;">Jumlah Transaksi (PO)</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #198754; color: #ffffff;">Mata Uang</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #198754; color: #ffffff;">Total Nilai Pengeluaran</th>
    </tr>

    @foreach($spends as $index => $row)
        <tr>
            <td style="border: 1px solid #000; text-align: center; {{ $index < 3 ? 'font-weight: bold; color: red;' : '' }}">
                {{ $index + 1 }} {{ $index == 0 ? '🏆' : '' }}
            </td>
            <td style="border: 1px solid #000; {{ $index < 3 ? 'font-weight: bold;' : '' }}">{{ $row['vendor_name'] }}</td>
            <td style="border: 1px solid #000; text-align: center;">{{ $row['total_po'] }} Dokumen</td>
            <td style="border: 1px solid #000; text-align: center;">{{ $row['currency'] }}</td>
            <td style="border: 1px solid #000; text-align: right; font-weight: bold;">{{ $row['total_spend'] }}</td>
        </tr>
    @endforeach
</table>
