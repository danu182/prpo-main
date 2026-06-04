<table>
    <tr>
        <td colspan="8" style="text-align: center; font-weight: bold; font-size: 14px;">LAPORAN PENGELUARAN KAS KASIR (PAYMENT)</td>
    </tr>
    <tr>
        <td colspan="8" style="text-align: center;">Periode: {{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} s/d {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}</td>
    </tr>
    <tr>
        <td colspan="8"></td>
    </tr>
    <tr>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #0d6efd; color: #ffffff;">No</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #0d6efd; color: #ffffff;">Tgl Bayar</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #0d6efd; color: #ffffff;">No. Bukti Kasir</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #0d6efd; color: #ffffff;">No. Tagihan (Invoice)</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #0d6efd; color: #ffffff;">Penerima Uang (Vendor)</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #0d6efd; color: #ffffff;">Metode</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #0d6efd; color: #ffffff;">Bank / Referensi</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #0d6efd; color: #ffffff;">Nominal Keluar (Rp)</th>
    </tr>

    @php
        $nomorUrut = 1;
        $totalKeluar = 0;
    @endphp

    @foreach($payments as $payment)
        <tr>
            <td style="border: 1px solid #000; text-align: center;">{{ $nomorUrut++ }}</td>
            <td style="border: 1px solid #000; text-align: center;">{{ \Carbon\Carbon::parse($payment->payment_date)->format('d/m/Y') }}</td>
            <td style="border: 1px solid #000;">{{ $payment->payment_number }}</td>

            {{-- UBAH $payment->vendorInvoice MENJADI $payment->invoice --}}
            <td style="border: 1px solid #000;">{{ optional($payment->invoice)->invoice_number ?? '-' }}</td>
            <td style="border: 1px solid #000;">{{ optional(optional($payment->invoice)->vendor)->name ?? '-' }}</td>

            <td style="border: 1px solid #000; text-align: center;">{{ strtoupper($payment->payment_method) }}</td>
            <td style="border: 1px solid #000;">{{ $payment->bank_name }} {{ $payment->reference_number ? '('.$payment->reference_number.')' : '' }}</td>
            <td style="border: 1px solid #000; text-align: right;">{{ $payment->amount }}</td>
        </tr>
        @php $totalKeluar += $payment->amount; @endphp
    @endforeach

    <tr>
        <td colspan="7" style="border: 1px solid #000; text-align: right; font-weight: bold; background-color: #f8f9fa;">TOTAL KAS KELUAR</td>
        <td style="border: 1px solid #000; text-align: right; font-weight: bold; background-color: #f8f9fa; color: #000;">{{ $totalKeluar }}</td>
    </tr>
</table>
