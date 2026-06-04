<table>
    <tr>
        <td colspan="7" style="text-align: center; font-weight: bold; font-size: 14px;">LAPORAN REKAP PURCHASE ORDER (PO)</td>
    </tr>
    <tr>
        <td colspan="7" style="text-align: center;">Periode: {{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} s/d {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}</td>
    </tr>
    <tr>
        <td colspan="7"></td>
    </tr>
    <tr>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #198754; color: #ffffff;">No</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #198754; color: #ffffff;">Tanggal PO</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #198754; color: #ffffff;">Nomor PO</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #198754; color: #ffffff;">Vendor / Supplier</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #198754; color: #ffffff;">Status</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #198754; color: #ffffff;">Mata Uang</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #198754; color: #ffffff;">Total Nilai</th>
    </tr>

    @php
        // Array pintar untuk memisahkan perhitungan tiap mata uang
        $totals = [];
    @endphp

    @foreach($pos as $index => $po)
        @php
            // Ambil mata uang dari database (jika kosong, anggap IDR)
            $mataUang = $po->currency ?? 'IDR';

            // Tambahkan ke keranjang total sesuai mata uangnya
            if(!isset($totals[$mataUang])) {
                $totals[$mataUang] = 0;
            }
            $totals[$mataUang] += $po->grand_total;
        @endphp
    <tr>
        <td style="border: 1px solid #000; text-align: center;">{{ $index + 1 }}</td>
        <td style="border: 1px solid #000; text-align: center;">{{ \Carbon\Carbon::parse($po->po_date)->format('d/m/Y') }}</td>
        <td style="border: 1px solid #000;">{{ $po->po_number }}</td>
        <td style="border: 1px solid #000;">{{ optional($po->vendor)->name }}</td>
        <td style="border: 1px solid #000; text-align: center;">{{ optional($po->status)->name }}</td>
        <td style="border: 1px solid #000; text-align: center;">{{ $mataUang }}</td>
        <td style="border: 1px solid #000; text-align: right;">{{ $po->grand_total }}</td>
    </tr>
    @endforeach

    {{-- Cetak baris Grand Total berjejer ke bawah sesuai jumlah mata uang yang ada --}}
    @foreach($totals as $currency => $totalValue)
    <tr>
        <td colspan="6" style="border: 1px solid #000; text-align: right; font-weight: bold; background-color: #f8f9fa;">GRAND TOTAL ({{ $currency }})</td>
        <td style="border: 1px solid #000; text-align: right; font-weight: bold; background-color: #f8f9fa;">{{ $totalValue }}</td>
    </tr>
    @endforeach
</table>
