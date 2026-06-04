<table>
    <tr>
        <td colspan="6" style="text-align: center; font-weight: bold; font-size: 14px;">LAPORAN REKAP PURCHASE ORDER (PO)</td>
    </tr>
    <tr>
        <td colspan="6" style="text-align: center;">Periode: {{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} s/d {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}</td>
    </tr>
    <tr>
        <td colspan="6"></td>
    </tr>
    <tr>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #198754; color: #ffffff;">No</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #198754; color: #ffffff;">Tanggal PO</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #198754; color: #ffffff;">Nomor PO</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #198754; color: #ffffff;">Vendor / Supplier</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #198754; color: #ffffff;">Status</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #198754; color: #ffffff;">Total Nilai (Rp)</th>
    </tr>

    @php $totalSemua = 0; @endphp

    @foreach($pos as $index => $po)
    <tr>
        <td style="border: 1px solid #000; text-align: center;">{{ $index + 1 }}</td>
        <td style="border: 1px solid #000; text-align: center;">{{ \Carbon\Carbon::parse($po->po_date)->format('d/m/Y') }}</td>
        <td style="border: 1px solid #000;">{{ $po->po_number }}</td>
        <td style="border: 1px solid #000;">{{ optional($po->vendor)->name }}</td>
        <td style="border: 1px solid #000; text-align: center;">{{ optional($po->status)->name }}</td>
        <td style="border: 1px solid #000; text-align: right;">{{ $po->grand_total }}</td>
    </tr>
    @php $totalSemua += $po->grand_total; @endphp
    @endforeach

    <tr>
        <td colspan="5" style="border: 1px solid #000; text-align: right; font-weight: bold; background-color: #f8f9fa;">GRAND TOTAL</td>
        <td style="border: 1px solid #000; text-align: right; font-weight: bold; background-color: #f8f9fa;">{{ $totalSemua }}</td>
    </tr>
</table>
