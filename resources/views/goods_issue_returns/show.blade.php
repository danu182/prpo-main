<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BAST Retur - {{ $return->return_number }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Arial', sans-serif; background-color: #f4f6f9; color: #000; font-size: 14px; }
        .print-area { padding: 50px; max-width: 900px; margin: 20px auto; background-color: #fff; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .signature-line { border-bottom: 1px solid #000; width: 70%; margin: 70px auto 5px; }

        /* Modifikasi untuk menampung teks panjang No Aset */
        .asset-info { font-size: 0.85rem; color: #444; margin-top: 4px; line-height: 1.4; display: block; }

        @media print {
            body { background-color: #fff; margin: 0; padding: 0; }
            .print-area { box-shadow: none; margin: 0; padding: 15px; max-width: 100%; border: none !important; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<div class="text-center mt-4 mb-3 no-print">
    <a href="{{ route('goods-issue-returns.index') }}" class="btn btn-secondary fw-bold px-4 rounded-pill me-2">⬅ Kembali</a>
    <button onclick="window.print()" class="btn btn-warning text-dark fw-bold px-4 rounded-pill">
        <i class="bi bi-printer me-2"></i> Cetak Bukti Retur
    </button>
</div>

@php
    // Cek apakah ada barang berjenis aset di dalam retur ini
    $hasAsset = $return->items->contains(fn($i) => $i->item->is_asset ?? false);
@endphp

<div class="print-area border rounded">
    <div class="row border-bottom pb-3 mb-4 align-items-center">
        <div class="col-6">
            <h3 class="fw-bold mb-0 text-dark">PROCURE<span class="fw-normal">APP</span></h3>
            <small class="text-muted">Gudang Utama & Logistik</small>
        </div>
        <div class="col-6 text-end">
            <h4 class="fw-bold text-warning mb-1">BUKTI RETUR {{ $hasAsset ? 'ASET/BARANG' : 'BARANG' }}</h4>
            <h6 class="fw-bold mb-0">No: {{ $return->return_number }}</h6>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-7">
            <table class="table table-sm table-borderless mb-0">
                <tr><td width="35%" class="text-muted fw-semibold">Dikembalikan Oleh</td><td width="5%">:</td><td class="fw-bold">{{ $return->returned_by_name }}</td></tr>
                <tr><td class="text-muted fw-semibold">Ref. Pengeluaran (GI)</td><td>:</td><td class="fw-bold text-danger">{{ optional($return->goodsIssue)->gi_number }}</td></tr>
                <tr><td class="text-muted fw-semibold">Catatan Umum</td><td>:</td><td>{{ $return->notes ?? '-' }}</td></tr>
            </table>
        </div>
        <div class="col-5">
            <table class="table table-sm table-borderless mb-0">
                <tr><td width="40%" class="text-muted fw-semibold">Tgl Kembali</td><td width="5%">:</td><td class="fw-bold">{{ \Carbon\Carbon::parse($return->return_date)->format('d F Y') }}</td></tr>
                <tr><td class="text-muted fw-semibold">Penerima (Gudang)</td><td>:</td><td class="fw-bold">{{ optional($return->receiver)->name }}</td></tr>
            </table>
        </div>
    </div>

    <table class="table table-bordered border-dark text-center align-middle mb-5">
        <thead class="bg-light fw-bold">
            <tr>
                <th width="5%" class="py-2">No</th>
                <th width="45%" class="py-2 text-start ps-3">Nama Barang / Spesifikasi</th>
                <th width="10%" class="py-2">Qty Retur</th>
                <th width="40%" class="py-2">Identitas Aset (SN / No. Label)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($return->items as $index => $item)
            <tr>
                <td class="py-2">{{ $index + 1 }}</td>
                <td class="text-start py-2 ps-3">
                    <div class="fw-bold">{{ optional($item->item)->name }}</div>
                    <small class="text-muted">{{ optional($item->item)->code }}</small>
                </td>
                <td class="fw-bold fs-6 py-2">{{ (float)$item->qty_returned }}</td>
                <td class="text-start py-2 px-3">
                    @if(optional($item->item)->is_asset)
                        @php
                            // 🪄 SIHIR DETEKTIF: Mencari jejak Aset di dalam catatan retur (Jika disuntikkan dari Controller)
                            // Atau mencari jejak Aset dari histori FixedAssetHistory yang dibuat di waktu yang SAMA EXACTLY dengan dokumen retur ini.
                            // Karena kita punya relasi ke return->id (jika ditambahkan), tapi untuk amannya kita ambil dari History.

                            $assetHistories = \App\Models\FixedAssetHistory::with('fixedAsset')
                                ->where('created_by', $return->received_by)
                                ->where('notes', 'like', '%'.$return->return_number.'%') // Mencari ref nomor retur di notes history
                                ->get();
                        @endphp

                        @if($assetHistories->count() > 0)
                            @foreach($assetHistories as $history)
                                @if(optional($history->fixedAsset)->item_id == $item->item_id)
                                    <div class="asset-info border-bottom border-light pb-1 mb-1">
                                        <strong>{{ $history->fixedAsset->asset_number }}</strong>
                                        @if($history->fixedAsset->serial_number) (SN: {{ $history->fixedAsset->serial_number }}) @endif
                                        @if($history->fixedAsset->accounting_asset_number) [FA: {{ $history->fixedAsset->accounting_asset_number }}] @endif
                                    </div>
                                @endif
                            @endforeach
                        @else
                            {{-- Jika metode history gagal, fallback ke pembacaan dari notes item (jika ada) --}}
                            {!! str_replace('|', '<br>', $item->notes) !!}
                            <span class="small text-muted fst-italic">(Aset Tetap Dikembalikan)</span>
                        @endif
                    @else
                        <span class="text-muted small">- Persediaan (Stock) -</span>
                        @if($item->notes)
                            <br><small>Catatan: {{ $item->notes }}</small>
                        @endif
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="row text-center mt-5">
        <div class="col-6">
            <p class="small text-muted mb-0">Yang Mengembalikan,</p>
            <div class="signature-line"></div>
            <p class="fw-bold mb-0 text-uppercase">{{ $return->returned_by_name }}</p>
            <p class="small text-muted mb-0">Pihak Peminjam</p>
        </div>
        <div class="col-6">
            <p class="small text-muted mb-0">Penerima Gudang (IN),</p>
            <div class="signature-line"></div>
            <p class="fw-bold mb-0 text-uppercase">{{ optional($return->receiver)->name }}</p>
            <p class="small text-muted mb-0">Staff Logistik / IT</p>
        </div>
    </div>
</div>

</body>
</html>
