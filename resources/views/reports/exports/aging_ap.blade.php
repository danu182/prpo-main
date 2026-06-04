<table>
    <tr>
        <td colspan="10" style="text-align: center; font-weight: bold; font-size: 14px;">LAPORAN AGING SCHEDULE (UMUR HUTANG VENDOR)</td>
    </tr>
    <tr>
        <td colspan="10" style="text-align: center;">Periode Invoice: {{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} s/d {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}</td>
    </tr>
    <tr>
        <td colspan="10"></td>
    </tr>
    <tr>
        <th rowspan="2" style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #ffc107; vertical-align: middle;">No</th>
        <th rowspan="2" style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #ffc107; vertical-align: middle;">Vendor / Supplier</th>
        <th rowspan="2" style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #ffc107; vertical-align: middle;">No. Tagihan (INV)</th>
        <th rowspan="2" style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #ffc107; vertical-align: middle;">Jatuh Tempo</th>
        <th rowspan="2" style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #ffc107; vertical-align: middle;">Sisa Hutang (Rp)</th>
        <th colspan="4" style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #fd7e14; color: #fff;">Umur Keterlambatan (Overdue)</th>
    </tr>
    <tr>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #198754; color: #fff;">Belum Jatuh Tempo</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #fd7e14; color: #fff;">1 - 30 Hari</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #fd7e14; color: #fff;">31 - 60 Hari</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #dc3545; color: #fff;">> 60 Hari</th>
    </tr>

    @php
        $nomorUrut = 1;
        $totSisa = 0; $totBelum = 0; $tot30 = 0; $tot60 = 0; $totLebih = 0;
    @endphp

    @foreach($invoices as $inv)
        @php
            $b = $inv->unpaid_balance;
            $belum = (!$inv->is_overdue) ? $b : 0;
            $h30 = ($inv->is_overdue && $inv->days_overdue <= 30) ? $b : 0;
            $h60 = ($inv->is_overdue && $inv->days_overdue > 30 && $inv->days_overdue <= 60) ? $b : 0;
            $hLebih = ($inv->is_overdue && $inv->days_overdue > 60) ? $b : 0;

            $totSisa += $b; $totBelum += $belum; $tot30 += $h30; $tot60 += $h60; $totLebih += $hLebih;
        @endphp
        <tr>
            <td style="border: 1px solid #000; text-align: center;">{{ $nomorUrut++ }}</td>
            <td style="border: 1px solid #000;">{{ optional($inv->vendor)->name }}</td>
            <td style="border: 1px solid #000;">{{ $inv->invoice_number }}</td>
            <td style="border: 1px solid #000; text-align: center; {{ $inv->is_overdue ? 'color: red; font-weight: bold;' : '' }}">{{ \Carbon\Carbon::parse($inv->due_date)->format('d/m/Y') }}</td>
            <td style="border: 1px solid #000; text-align: right; font-weight: bold;">{{ $b }}</td>

            <td style="border: 1px solid #000; text-align: right; color: green;">{{ $belum > 0 ? $belum : '-' }}</td>
            <td style="border: 1px solid #000; text-align: right;">{{ $h30 > 0 ? $h30 : '-' }}</td>
            <td style="border: 1px solid #000; text-align: right; color: darkorange;">{{ $h60 > 0 ? $h60 : '-' }}</td>
            <td style="border: 1px solid #000; text-align: right; color: red; font-weight: bold;">{{ $hLebih > 0 ? $hLebih : '-' }}</td>
        </tr>
    @endforeach

    <tr>
        <td colspan="4" style="border: 1px solid #000; text-align: right; font-weight: bold; background-color: #f8f9fa;">GRAND TOTAL</td>
        <td style="border: 1px solid #000; text-align: right; font-weight: bold; background-color: #f8f9fa;">{{ $totSisa }}</td>
        <td style="border: 1px solid #000; text-align: right; font-weight: bold; background-color: #f8f9fa; color: green;">{{ $totBelum }}</td>
        <td style="border: 1px solid #000; text-align: right; font-weight: bold; background-color: #f8f9fa;">{{ $tot30 }}</td>
        <td style="border: 1px solid #000; text-align: right; font-weight: bold; background-color: #f8f9fa; color: darkorange;">{{ $tot60 }}</td>
        <td style="border: 1px solid #000; text-align: right; font-weight: bold; background-color: #f8f9fa; color: red;">{{ $totLebih }}</td>
    </tr>
</table>
