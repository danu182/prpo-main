<table>
    <tr>
        <td colspan="7" style="text-align: center; font-weight: bold; font-size: 14px;">LAPORAN PENERIMAAN BARANG (GOODS RECEIPT)</td>
    </tr>
    <tr>
        <td colspan="7" style="text-align: center;">Periode: {{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} s/d {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}</td>
    </tr>
    <tr>
        <td colspan="7"></td>
    </tr>
    <tr>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #0dcaf0; color: #000000;">No</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #0dcaf0; color: #000000;">Tgl Terima</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #0dcaf0; color: #000000;">No. Penerimaan (GR)</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #0dcaf0; color: #000000;">Referensi PO</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #0dcaf0; color: #000000;">Vendor / Supplier</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #0dcaf0; color: #000000;">Nama Barang</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #0dcaf0; color: #000000;">Qty Diterima</th>
    </tr>

    @php
        $nomorUrut = 1;
        $totalQtyGlobal = 0;
    @endphp

    {{-- Kita lakukan Double-Looping agar rincian barang berjejer ke bawah --}}
    @foreach($grs as $gr)
        @foreach($gr->items as $itemGR)
            <tr>
                <td style="border: 1px solid #000; text-align: center;">{{ $nomorUrut++ }}</td>
                <td style="border: 1px solid #000; text-align: center;">{{ \Carbon\Carbon::parse($gr->created_at)->format('d/m/Y H:i') }}</td>
                <td style="border: 1px solid #000;">{{ $gr->gr_number }}</td>
                <td style="border: 1px solid #000;">{{ optional($gr->po)->po_number }}</td>
                <td style="border: 1px solid #000;">{{ optional(optional($gr->po)->vendor)->name }}</td>
                <td style="border: 1px solid #000;">{{ optional($itemGR->item)->name ?? 'Item Tidak Ditemukan' }}</td>
                <td style="border: 1px solid #000; text-align: center;">{{ $itemGR->qty_received }}</td>
            </tr>
            @php $totalQtyGlobal += $itemGR->qty_received; @endphp
        @endforeach
    @endforeach

    <tr>
        <td colspan="6" style="border: 1px solid #000; text-align: right; font-weight: bold; background-color: #f8f9fa;">TOTAL QTY SELURUH BARANG DITERIMA</td>
        <td style="border: 1px solid #000; text-align: center; font-weight: bold; background-color: #f8f9fa;">{{ $totalQtyGlobal }}</td>
    </tr>
</table>
