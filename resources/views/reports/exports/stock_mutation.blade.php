<table>
    <tr>
        <td colspan="10" style="text-align: center; font-weight: bold; font-size: 14px;">LAPORAN KARTU MUTASI STOK GUDANG</td>
    </tr>
    <tr>
        <td colspan="10" style="text-align: center;">Periode: {{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} s/d {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}</td>
    </tr>
    <tr>
        <td colspan="10"></td>
    </tr>
    <tr>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #6c757d; color: #ffffff;">No</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #6c757d; color: #ffffff;">Waktu Mutasi</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #6c757d; color: #ffffff;">Nama Barang</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #6c757d; color: #ffffff;">Tipe</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #6c757d; color: #ffffff;">Qty Mutasi</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #6c757d; color: #ffffff;">Saldo Awal</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #6c757d; color: #ffffff;">Saldo Akhir</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #6c757d; color: #ffffff;">No. Referensi</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #6c757d; color: #ffffff;">Keterangan</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #6c757d; color: #ffffff;">Oleh (User)</th>
    </tr>

    @php $nomorUrut = 1; @endphp

    @foreach($mutations as $mut)
        <tr>
            <td style="border: 1px solid #000; text-align: center;">{{ $nomorUrut++ }}</td>
            <td style="border: 1px solid #000; text-align: center;">{{ \Carbon\Carbon::parse($mut->created_at)->format('d/m/Y H:i') }}</td>
            <td style="border: 1px solid #000;">{{ optional($mut->item)->name ?? 'Item Dihapus' }}</td>

            {{-- Warna IN (Masuk) jadi hijau, OUT (Keluar) jadi merah --}}
            <td style="border: 1px solid #000; text-align: center; font-weight: bold; color: {{ $mut->type == 'IN' ? 'green' : 'red' }};">{{ $mut->type }}</td>

            <td style="border: 1px solid #000; text-align: center;">{{ $mut->qty }}</td>
            <td style="border: 1px solid #000; text-align: center;">{{ $mut->balance_before }}</td>
            <td style="border: 1px solid #000; text-align: center; font-weight: bold;">{{ $mut->balance_after }}</td>
            <td style="border: 1px solid #000;">{{ $mut->reference_number }}</td>
            <td style="border: 1px solid #000;">{{ $mut->notes }}</td>
            <td style="border: 1px solid #000;">{{ optional($mut->creator)->name }}</td>
        </tr>
    @endforeach
</table>
