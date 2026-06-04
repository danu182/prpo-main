<table>
    <tr>
        <td colspan="8" style="text-align: center; font-weight: bold; font-size: 14px;">LAPORAN RETUR KE VENDOR (RTV)</td>
    </tr>
    <tr>
        <td colspan="8" style="text-align: center;">Periode: {{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} s/d {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}</td>
    </tr>
    <tr>
        <td colspan="8"></td>
    </tr>
    <tr>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #dc3545; color: #ffffff;">No</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #dc3545; color: #ffffff;">Tgl Retur</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #dc3545; color: #ffffff;">No. Dokumen RTV</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #dc3545; color: #ffffff;">No. Penerimaan (GR)</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #dc3545; color: #ffffff;">Vendor / Supplier</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #dc3545; color: #ffffff;">Nama Barang</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #dc3545; color: #ffffff;">Alasan Retur</th>
        <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #dc3545; color: #ffffff;">Qty Diretur</th>
    </tr>

    @php
        $nomorUrut = 1;
        $totalQtyRetur = 0;
    @endphp

    @foreach($rtvs as $rtv)
        @foreach($rtv->items as $itemRTV)
            <tr>
                <td style="border: 1px solid #000; text-align: center;">{{ $nomorUrut++ }}</td>
                <td style="border: 1px solid #000; text-align: center;">{{ \Carbon\Carbon::parse($rtv->return_date)->format('d/m/Y') }}</td>
                <td style="border: 1px solid #000;">{{ $rtv->rtv_number }}</td>
                <td style="border: 1px solid #000;">{{ optional($rtv->goodsReceipt)->gr_number }}</td>
                <td style="border: 1px solid #000;">{{ optional($rtv->vendor)->name }}</td>
                <td style="border: 1px solid #000;">{{ optional($itemRTV->item)->name ?? 'Item Tidak Ditemukan' }}</td>
                <td style="border: 1px solid #000; text-align: center;">{{ $itemRTV->return_reason ?? '-' }}</td>
                <td style="border: 1px solid #000; text-align: center; color: red;">{{ $itemRTV->qty_returned }}</td>
            </tr>
            @php $totalQtyRetur += $itemRTV->qty_returned; @endphp
        @endforeach
    @endforeach

    <tr>
        <td colspan="7" style="border: 1px solid #000; text-align: right; font-weight: bold; background-color: #f8f9fa;">TOTAL QTY SELURUH BARANG DIRETUR</td>
        <td style="border: 1px solid #000; text-align: center; font-weight: bold; background-color: #f8f9fa; color: red;">{{ $totalQtyRetur }}</td>
    </tr>
</table>
