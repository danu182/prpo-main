@extends('layouts.app')

@section('content')

<style>
    /* Desain Scrollbar Minimalis */
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #a8a8a8; }
</style>

<div class="container-fluid pb-5 text-dark">
    {{-- HEADER --}}
    <div class="gap-3 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-card-list me-2 text-primary"></i> Kartu Mutasi Stok</h4>
            <div class="mt-1 text-muted small">Riwayat keluar masuk untuk barang: <strong class="text-primary">{{ $item->code }} - {{ $item->name }}</strong></div>
        </div>
        <a href="{{ route('inventory.index') }}" class="px-4 border shadow-sm btn btn-light rounded-pill fw-bold">
            <i class="bi bi-arrow-left me-2"></i> Kembali
        </a>
    </div>

    @php
        // =====================================================================
        // 🔥 OTAK SINKRONISASI MUTLAK (SINGLE SOURCE OF TRUTH) 🔥
        // =====================================================================

        // 1. Hitung murni dari sejarah mutasi (Tabel adalah kebenaran mutlak)
        $inQuery = \App\Models\StockMutation::where('item_id', $item->id)->where('type', 'IN')->where('notes', 'not like', '%[DE-CAPITALIZE]%');
        if ($warehouseId) $inQuery->where('warehouse_id', $warehouseId);
        $totalIn = (float) $inQuery->sum('qty');

        $outQuery = \App\Models\StockMutation::where('item_id', $item->id)->where('type', 'OUT')->where('notes', 'not like', '%[CAPITALIZE]%');
        if ($warehouseId) $outQuery->where('warehouse_id', $warehouseId);
        $totalOut = (float) $outQuery->sum('qty');

        // Saldo Gabungan Terkini Pasti Akurat Sesuai Tabel Bawah
        $realCurrentStock = $totalIn - $totalOut;

        // 2. Hitung Aset Terdaftar (Available)
        $assetQuery = \App\Models\FixedAsset::where('item_id', $item->id)
                        ->whereHas('status', function($q) { $q->where('slug', 'available'); });
        if ($warehouseId) $assetQuery->where('warehouse_id', $warehouseId);
        $availableAssets = $assetQuery->get();
        $assetStock = (float) $availableAssets->count();

        // 3. Paksa Stok Fisik Biasa menyesuaikan agar mengabaikan Bug Double Entry di Database
        $bulkStock = max(0, $realCurrentStock - $assetStock);

        // 4. Kalkulator Maju untuk Tabel Saldo
        if (isset($mutations) && $mutations->count() > 0) {
            $oldestMut = $mutations->last();

            $pastInQuery = \App\Models\StockMutation::where('item_id', $item->id)
                            ->where('id', '<', $oldestMut->id)
                            ->where('type', 'IN')
                            ->where('notes', 'not like', '%[DE-CAPITALIZE]%');
            if ($warehouseId) $pastInQuery->where('warehouse_id', $warehouseId);
            $pastIn = (float) $pastInQuery->sum('qty');

            $pastOutQuery = \App\Models\StockMutation::where('item_id', $item->id)
                            ->where('id', '<', $oldestMut->id)
                            ->where('type', 'OUT')
                            ->where('notes', 'not like', '%[CAPITALIZE]%');
            if ($warehouseId) $pastOutQuery->where('warehouse_id', $warehouseId);
            $pastOut = (float) $pastOutQuery->sum('qty');

            $runningBalance = $pastIn - $pastOut;

            $reversed = collect($mutations->items())->reverse();

            foreach ($reversed as $mut) {
                $isCapitalize = str_contains($mut->notes, '[CAPITALIZE]');
                $isDeCapitalize = str_contains($mut->notes, '[DE-CAPITALIZE]');

                $mut->is_capitalize = $isCapitalize;
                $mut->is_decapitalize = $isDeCapitalize;

                if ($mut->type === 'IN' && !$isDeCapitalize) {
                    $runningBalance += (float) $mut->qty;
                } elseif ($mut->type === 'OUT' && !$isCapitalize) {
                    $runningBalance -= (float) $mut->qty;
                }

                $mut->dynamic_balance = $runningBalance;
            }
        }
    @endphp

    {{-- KOTAK SALDO & FILTER --}}
    <div class="mb-4 row g-4">
        <div class="col-md-4">
            <div class="p-4 text-white border-0 shadow-sm card rounded-4 bg-primary bg-gradient h-100 d-flex flex-column justify-content-center">
                <h6 class="mb-2 text-white-50 fw-bold text-uppercase">
                    Saldo Terkini {{ $warehouseId ? '(Gudang Dipilih)' : '(Total Semua)' }}
                </h6>
                <h1 class="mb-0 display-4 fw-bold">
                    {{ $realCurrentStock }}
                    <span class="fs-5 fw-normal">{{ optional($item->uom)->name }}</span>
                </h1>

                @if($realCurrentStock > 0 || $assetStock > 0 || $bulkStock > 0)
                <div class="pt-3 mt-3 border-opacity-25 border-top border-light">
                    <div class="mb-2 d-flex justify-content-between align-items-center small fw-semibold text-white-50">
                        <span><i class="bi bi-box me-1"></i> Stok Fisik Biasa</span>
                        <span class="text-white fw-bold">{{ $bulkStock }} {{ optional($item->uom)->name }}</span>
                    </div>

                    <a href="#detailAsetCollapse" data-bs-toggle="collapse" class="text-decoration-none d-flex justify-content-between align-items-center small fw-semibold text-white-50" style="transition: 0.3s; opacity: 0.9;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.9'">
                        <span>
                            <i class="bi bi-upc-scan me-1"></i> Aset Terdaftar (Siap GI)
                            <i class="bi bi-chevron-down ms-1" style="font-size: 0.65rem;"></i>
                        </span>
                        <span class="bg-white shadow-sm badge text-primary rounded-pill">{{ $assetStock }} {{ optional($item->uom)->name }}</span>
                    </a>

                    @if($assetStock > 0)
                    <div class="mt-2 collapse" id="detailAsetCollapse">
                        <div class="p-2 bg-white border border-white border-opacity-25 bg-opacity-10 rounded-3 custom-scrollbar" style="max-height: 140px; overflow-y: auto;">
                            @foreach($availableAssets as $ast)
                                <div class="pb-1 mb-1 border-opacity-25 border-bottom border-light" style="last-child { border: none !important; margin-bottom: 0 !important; padding-bottom: 0 !important; }">
                                    <div class="text-white fw-bold d-flex align-items-center" style="font-size: 0.75rem;">
                                        <i class="bi bi-check2-circle text-info me-1"></i> {{ $ast->serial_number ?: $ast->asset_number }}
                                    </div>
                                    <div class="text-white-50 ms-3" style="font-size: 0.65rem; line-height: 1.2;">
                                        No: {{ $ast->accounting_asset_number ?? $ast->asset_number }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
                @endif
            </div>
        </div>
        <div class="col-md-8">
            <div class="bg-white border-0 shadow-sm card rounded-4 h-100">
                <div class="p-4 card-body d-flex flex-column justify-content-center">
                    <label class="mb-2 fw-bold small text-muted"><i class="bi bi-funnel me-1"></i> Filter Riwayat Berdasarkan Gudang</label>
                    <form action="{{ route('inventory.show', $item->id) }}" method="GET" class="gap-2 d-flex">
                        <select name="warehouse_id" class="shadow-sm form-select border-primary" onchange="this.form.submit()">
                            <option value="">-- Semua Gudang --</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}" {{ $warehouseId == $wh->id ? 'selected' : '' }}>
                                    {{ $wh->name }}
                                </option>
                            @endforeach
                        </select>
                        @if($warehouseId)
                            <a href="{{ route('inventory.show', $item->id) }}" class="px-3 shadow-sm btn btn-danger" title="Reset Filter"><i class="bi bi-x-lg"></i></a>
                        @endif
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- TABEL MUTASI --}}
    <div class="border-0 border-4 shadow-sm card rounded-4 border-top border-primary">
        <div class="p-0 card-body table-responsive">
            <table class="table mb-0 align-middle table-hover">
                <thead class="bg-light fw-bold text-muted border-bottom text-uppercase small">
                    <tr>
                        <th class="py-3 ps-4" width="15%">Tanggal</th>
                        <th class="py-3" width="15%">Gudang</th>
                        <th class="py-3" width="10%">Tipe</th>
                        <th class="py-3 text-center" width="15%">No. Referensi</th>
                        <th class="py-3" width="20%">Keterangan</th>
                        <th class="py-3 text-center" width="8%">Masuk</th>
                        <th class="py-3 text-center" width="8%">Keluar</th>
                        <th class="py-3 pe-4 text-end" width="9%">Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($mutations as $mut)
                    <tr class="border-bottom">
                        <td class="py-3 ps-4 small text-muted">{{ $mut->created_at->format('d M Y, H:i') }}</td>
                        <td class="py-3 small fw-semibold text-secondary">{{ optional($mut->warehouse)->name ?? 'Umum' }}</td>

                        <td class="py-3">
                            @if($mut->is_capitalize)
                                <span class="px-2 border badge bg-info-subtle text-info border-info-subtle">ALOKASI ASET</span>
                            @elseif($mut->is_decapitalize)
                                <span class="px-2 border badge bg-warning-subtle text-warning border-warning-subtle">VOID ASET</span>
                            @elseif($mut->type == 'IN')
                                <span class="px-2 border badge bg-success-subtle text-success border-success-subtle">MASUK</span>
                            @else
                                <span class="px-2 border badge bg-danger-subtle text-danger border-danger-subtle">KELUAR</span>
                            @endif
                        </td>

                        <td class="py-3 text-center fw-bold text-dark">{{ $mut->reference_number ?? '-' }}</td>

                        <td class="py-3" style="max-width: 350px;">
                            @php
                                $text = str_replace(['</p>', '<br>', '<br/>'], ' ', $mut->notes);
                                $cleanNotes = strip_tags($text);

                                $cleanNotes = str_replace('[CAPITALIZE]', '', $cleanNotes);
                                $cleanNotes = str_replace('[DE-CAPITALIZE]', '', $cleanNotes);

                                $mainText = $cleanNotes;
                                $snText = '';
                                if (str_contains($cleanNotes, '[')) {
                                    $parts = explode('[', $cleanNotes);
                                    $mainText = trim($parts[0]);
                                    $snText = trim(str_replace(']', '', $parts[1] ?? ''));
                                }

                                $mainText = preg_replace('/\(Ref PO:\s*(.*?)\)/i', '<div class="mt-2 mb-1"><span class="px-2 py-1 border badge bg-primary-subtle text-primary border-primary-subtle"><i class="bi bi-file-earmark-text me-1"></i>Ref PO: $1</span></div>', $mainText);
                                $mainText = str_replace('Retur ke Vendor.', '<strong class="text-danger"><i class="bi bi-arrow-return-left me-1"></i>Retur ke Vendor.</strong><br>', $mainText);
                                $mainText = preg_replace('/^Masuk:/', '<strong class="text-success"><i class="bi bi-box-arrow-in-right me-1"></i>Masuk:</strong>', $mainText);

                                // =========================================================================
                                // 🔥 KECERDASAN BUATAN UNTUK MEMBEDAKAN ASET TETAP & SN BIASA 🔥
                                // =========================================================================
                                $realSns = [];

                                if ($mut->is_capitalize || $mut->is_decapitalize) {
                                    $assetsData = \DB::table('fixed_assets')
                                        ->where('item_id', $mut->item_id)
                                        ->where('warehouse_id', $mut->warehouse_id)
                                        ->whereBetween('created_at', [\Carbon\Carbon::parse($mut->created_at)->subMinutes(1), \Carbon\Carbon::parse($mut->created_at)->addMinutes(1)])
                                        ->get();

                                    foreach ($assetsData as $ad) {
                                        $badgeType = $mut->is_capitalize ? 'bg-primary' : 'bg-danger';
                                        $badgeText = $mut->is_capitalize ? 'ASET' : 'VOID';

                                        $accNo = $ad->accounting_asset_number ? '<div class="text-success fw-bold" style="font-size:0.7rem; margin-top:2px;">[FA: ' . $ad->accounting_asset_number . ']</div>' : '';
                                        $snInfo = $ad->serial_number ? '<div class="text-muted" style="font-size:0.7rem;">SN: ' . $ad->serial_number . '</div>' : '';

                                        // 🔥 FORMAT BARU: SUSUN KE BAWAH 🔥
                                        $htmlBlock = '
                                        <div class="p-2 mb-2 bg-white border rounded shadow-sm border-secondary-subtle ms-3 position-relative">
                                            <div class="top-0 position-absolute start-0 translate-middle">
                                                <span class="badge ' . $badgeType . ' px-2 py-1" style="font-size:0.55rem; letter-spacing:0.5px;">' . $badgeText . '</span>
                                            </div>
                                            <div class="mt-1 fw-bold text-primary">' . $ad->asset_number . '</div>
                                            ' . $accNo . '
                                            ' . $snInfo . '
                                        </div>';

                                        $realSns[] = $htmlBlock;
                                    }
                                } else {
                                    if (str_starts_with($mut->reference_number, 'GR-')) {
                                        $grDoc = \DB::table('goods_receipts')->where('gr_number', $mut->reference_number)->first();
                                        if ($grDoc) {
                                            $realSns = \DB::table('item_serials')->where('goods_receipt_id', $grDoc->id)->where('item_id', $mut->item_id)->where('warehouse_id', $mut->warehouse_id)->pluck('serial_number')->toArray();
                                        }
                                    } elseif (str_starts_with($mut->reference_number, 'RTV-')) {
                                        $rtvDoc = \DB::table('return_to_vendors')->where('rtv_number', $mut->reference_number)->first();
                                        if ($rtvDoc) {
                                            $realSns = \DB::table('item_serials')->where('return_to_vendor_id', $rtvDoc->id)->where('item_id', $mut->item_id)->where('warehouse_id', $mut->warehouse_id)->pluck('serial_number')->toArray();
                                        }
                                    } elseif (str_starts_with($mut->reference_number, 'GI-') || str_starts_with($mut->reference_number, 'RET-GI')) {
                                        // 🔥 TAMBAHAN LOGIKA UNTUK RET-GI & GI 🔥
                                        $baseRefNumber = str_replace('RET-', '', $mut->reference_number); // Jika Retur, kita cari dokumen aslinya
                                        $giDoc = \DB::table('goods_issues')->where('gi_number', $baseRefNumber)->orWhere('gi_number', $mut->reference_number)->first();

                                        if ($giDoc) {
                                            if (\Schema::hasColumn('item_serials', 'goods_issue_id')) {
                                                $snMinor = \DB::table('item_serials')->where('goods_issue_id', $giDoc->id)->where('item_id', $mut->item_id)->where('warehouse_id', $mut->warehouse_id)->pluck('serial_number')->toArray();
                                                $realSns = array_merge($realSns, $snMinor);
                                            } else {
                                                $snMinor = \DB::table('item_serials')
                                                    ->where('item_id', $mut->item_id)
                                                    ->where('warehouse_id', $mut->warehouse_id)
                                                    ->whereIn('status', ['ISSUED', 'USED', 'OUT', 'DISPATCHED'])
                                                    ->whereBetween('updated_at', [\Carbon\Carbon::parse($mut->created_at)->subMinutes(1), \Carbon\Carbon::parse($mut->created_at)->addMinutes(1)])
                                                    ->pluck('serial_number')->toArray();
                                                $realSns = array_merge($realSns, $snMinor);
                                            }

                                            $giAssets = \DB::table('fixed_assets')
                                                ->where('item_id', $mut->item_id)
                                                ->where('warehouse_id', $mut->warehouse_id)
                                                ->whereBetween('updated_at', [\Carbon\Carbon::parse($mut->created_at)->subMinutes(1), \Carbon\Carbon::parse($mut->created_at)->addMinutes(1)])
                                                ->get();

                                            foreach ($giAssets as $ad) {
                                                $accNo = $ad->accounting_asset_number ? '<div class="text-success fw-bold" style="font-size:0.7rem; margin-top:2px;">[FA: ' . $ad->accounting_asset_number . ']</div>' : '';
                                                $snInfo = $ad->serial_number ? '<div class="text-muted" style="font-size:0.7rem;">SN: ' . $ad->serial_number . '</div>' : '';

                                                $htmlBlock = '
                                                <div class="p-2 mb-2 bg-white border rounded shadow-sm border-secondary-subtle ms-3 position-relative">
                                                    <div class="top-0 position-absolute start-0 translate-middle">
                                                        <span class="px-2 py-1 badge bg-primary" style="font-size:0.55rem; letter-spacing:0.5px;">ASET</span>
                                                    </div>
                                                    <div class="mt-1 fw-bold text-primary">' . $ad->asset_number . '</div>
                                                    ' . $accNo . '
                                                    ' . $snInfo . '
                                                </div>';

                                                if ($ad->serial_number) {
                                                    $key = array_search($ad->serial_number, $realSns);
                                                    if ($key !== false) unset($realSns[$key]);
                                                }

                                                if (!in_array($htmlBlock, $realSns)) $realSns[] = $htmlBlock;
                                            }
                                            $realSns = array_values($realSns);
                                        }
                                    }
                                }

                                if (empty($realSns) && $snText && !$mut->is_capitalize && !$mut->is_decapitalize) {
                                    $cleanSn = str_replace(['SN Diretur: ', 'SN Terdaftar: ', 'SN: '], '', $snText);
                                    $snArray = array_map('trim', explode('|', $cleanSn));
                                } else {
                                    $snArray = $realSns;
                                }

                                $snArray = array_filter($snArray, function($val) {
                                    return stripos($val, 'Auto-FIFO') === false && trim($val) !== '';
                                });

                                $mainText = preg_replace('/\[SN:.*?\]/', '', $mainText);
                            @endphp

                            <div class="text-dark text-wrap" style="font-size: 0.85rem; line-height: 1.6;">
                                {!! trim($mainText) !!}
                            </div>

                            @if(count($snArray) > 0)
                                <div class="mt-2 overflow-hidden border rounded shadow-sm border-warning-subtle">
                                    <a href="#snCollapse_{{ $mut->id }}" data-bs-toggle="collapse" class="px-2 py-2 text-decoration-none d-block bg-warning-subtle text-warning-emphasis fw-bold d-flex justify-content-between align-items-center" style="font-size: 0.7rem; transition: background 0.2s;">
                                        <span><i class="bi bi-upc-scan me-1"></i> Detail Serial Number</span>
                                        <span>
                                            <span class="shadow-sm badge bg-warning text-dark rounded-pill me-1">{{ count($snArray) }} Unit</span>
                                            <i class="bi bi-chevron-down text-warning-emphasis"></i>
                                        </span>
                                    </a>
                                    <div class="collapse" id="snCollapse_{{ $mut->id }}">
                                        <div class="p-2 bg-light text-muted border-top border-warning-subtle" style="font-size: 0.75rem; font-family: monospace;">
                                            <div class="mt-2 row row-cols-1 g-2">
                                                @foreach($snArray as $sn)
                                                    <div class="col" style="word-break: break-word; line-height: 1.5;">
                                                        @if(str_contains($sn, '<div'))
                                                            {{-- Jika bentuknya Aset Block (Html buatan kita di atas) --}}
                                                            {!! $sn !!}
                                                        @else
                                                            {{-- Jika SN biasa --}}
                                                            <span class="text-success fw-bold me-1">✓</span> <strong class="text-dark">{{ $sn }}</strong>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </td>

                        {{-- MASUK --}}
                        <td class="py-3 text-center fw-bold text-success fs-6">
                            @if($mut->type == 'IN' && !$mut->is_decapitalize && !$mut->is_capitalize)
                                + {{ (float)$mut->qty }} <br><span class="text-muted" style="font-size: 0.65rem;">{{ optional($item->uom)->name }}</span>
                            @else
                                -
                            @endif
                        </td>

                        {{-- KELUAR --}}
                        <td class="py-3 text-center fw-bold text-danger fs-6">
                            @if($mut->type == 'OUT' && !$mut->is_capitalize && !$mut->is_decapitalize)
                                - {{ (float)$mut->qty }} <br><span class="text-muted" style="font-size: 0.65rem;">{{ optional($item->uom)->name }}</span>
                            @else
                                -
                            @endif
                        </td>

                        {{-- SALDO DINAMIS --}}
                        <td class="py-3 pe-4 text-end fw-bold bg-light text-primary fs-6 border-start">
                            {{ (float) ($mut->dynamic_balance ?? $mut->balance_after) }} <br><span class="text-muted" style="font-size: 0.65rem;">{{ optional($item->uom)->name }}</span>
                        </td>
                    </tr>
                    @empty
                        <tr><td colspan="8" class="py-5 text-center text-muted"><i class="mb-2 opacity-50 bi bi-inbox fs-1 d-block"></i> Belum ada riwayat pergerakan stok.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if(isset($mutations) && $mutations->hasPages())
        <div class="pt-3 pb-2 bg-white border-0 card-footer rounded-bottom-4">{{ $mutations->links() }}</div>
        @endif
    </div>
</div>
@endsection