@extends('layouts.app')

@section('content')

<style>
    /* Desain Scrollbar Minimalis */
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #a8a8a8; }
</style>

<div class="container pb-5 text-dark">
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

    {{-- KOTAK SALDO & FILTER --}}
    <div class="mb-4 row g-4">
        <div class="col-md-4">
            <div class="p-4 text-white border-0 shadow-sm card rounded-4 bg-primary bg-gradient h-100 d-flex flex-column justify-content-center">
                <h6 class="mb-2 text-white-50 fw-bold text-uppercase">
                    Saldo Terkini {{ $warehouseId ? '(Gudang Dipilih)' : '(Total Semua)' }}
                </h6>
                <h1 class="mb-0 display-4 fw-bold">
                    {{ (float) $currentStock }} 
                    <span class="fs-5 fw-normal">{{ optional($item->uom)->name }}</span>
                </h1>

                @php
                    // 🔥 LOGIKA PEMISAH ALAM (Tarik Data Lengkapnya, Bukan Cuma Angkanya) 🔥
                    $assetQuery = \App\Models\FixedAsset::where('item_id', $item->id)
                                    ->whereHas('status', function($q) { $q->where('slug', 'available'); });
                    if ($warehouseId) {
                        $assetQuery->where('warehouse_id', $warehouseId);
                    }
                    
                    $availableAssets = $assetQuery->get(); // Ambil seluruh data asetnya
                    $assetStock = $availableAssets->count();
                    $bulkStock = max(0, (float)$currentStock - $assetStock);
                @endphp

                {{-- MUNCULKAN RINCIAN JIKA STOK ADA --}}
                @if($currentStock > 0)
                <div class="mt-3 pt-3 border-top border-light border-opacity-25">
                    
                    {{-- Baris Stok Biasa --}}
                    <div class="d-flex justify-content-between align-items-center small fw-semibold text-white-50 mb-2">
                        <span><i class="bi bi-box me-1"></i> Stok Fisik Biasa</span>
                        <span class="text-white fw-bold">{{ $bulkStock }} {{ optional($item->uom)->name }}</span>
                    </div>
                    
                    {{-- Baris Aset Terdaftar (Bisa Diklik Lipat-Buka) --}}
                    <a href="#detailAsetCollapse" data-bs-toggle="collapse" class="text-decoration-none d-flex justify-content-between align-items-center small fw-semibold text-white-50" style="transition: 0.3s; opacity: 0.9;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.9'">
                        <span>
                            <i class="bi bi-upc-scan me-1"></i> Aset Terdaftar (Siap GI) 
                            <i class="bi bi-chevron-down ms-1" style="font-size: 0.65rem;"></i>
                        </span>
                        <span class="badge bg-white text-primary rounded-pill shadow-sm">{{ $assetStock }} {{ optional($item->uom)->name }}</span>
                    </a>

                    {{-- 🔥 ISI DETAIL ASET YANG TERSEMBUNYI 🔥 --}}
                    @if($assetStock > 0)
                    <div class="collapse mt-2" id="detailAsetCollapse">
                        <div class="bg-white bg-opacity-10 border border-white border-opacity-25 rounded-3 p-2 custom-scrollbar" style="max-height: 140px; overflow-y: auto;">
                            @foreach($availableAssets as $ast)
                                <div class="pb-1 mb-1 border-bottom border-light border-opacity-25" style="last-child { border: none !important; margin-bottom: 0 !important; padding-bottom: 0 !important; }">
                                    <div class="text-white fw-bold d-flex align-items-center" style="font-size: 0.75rem;">
                                        <i class="bi bi-check2-circle text-info me-1"></i> {{ $ast->serial_number }}
                                    </div>
                                    <div class="text-white-50 ms-3" style="font-size: 0.65rem; line-height: 1.2;">
                                        No: {{ $ast->accounting_asset_number ?? $ast->asset_number }}
                                        @if($ast->spesifikasi_detail)
                                            <br>Spek: {{ Str::limit($ast->spesifikasi_detail, 35) }}
                                        @endif
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
                            @if($mut->type == 'IN')
                                <span class="px-2 border badge bg-success-subtle text-success border-success-subtle">MASUK</span>
                            @else
                                <span class="px-2 border badge bg-danger-subtle text-danger border-danger-subtle">KELUAR</span>
                            @endif
                        </td>
                        <td class="py-3 text-center fw-bold text-dark">{{ $mut->reference_number ?? '-' }}</td>

                        {{-- KOLOM KETERANGAN (DIPERCANTIK & DIBERSIHKAN DARI HTML) --}}
                        <td class="py-3" style="max-width: 350px;">
                            @php
                                $text = str_replace(['</p>', '<br>', '<br/>'], ' ', $mut->notes);
                                $cleanNotes = strip_tags($text);

                                $mainText = $cleanNotes;
                                $snText = '';
                                if (str_contains($cleanNotes, '[')) {
                                    $parts = explode('[', $cleanNotes);
                                    $mainText = trim($parts[0]);
                                    $snText = trim(str_replace(']', '', $parts[1] ?? ''));
                                }

                                $mainText = preg_replace('/\(Ref PO:\s*(.*?)\)/i', '<div class="mt-2 mb-1"><span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1"><i class="bi bi-file-earmark-text me-1"></i>Ref PO: $1</span></div>', $mainText);
                                $mainText = str_replace('Retur ke Vendor.', '<strong class="text-danger"><i class="bi bi-arrow-return-left me-1"></i>Retur ke Vendor.</strong><br>', $mainText);
                                $mainText = preg_replace('/^Masuk:/', '<strong class="text-success"><i class="bi bi-box-arrow-in-right me-1"></i>Masuk:</strong>', $mainText);

                                // =========================================================================
                                // 🔥 TRIK DEWA V2: MENGGELEDAH TABEL ITEM_SERIALS & FIXED_ASSETS 🔥
                                // =========================================================================
                                $realSns = [];
                                if (str_starts_with($mut->reference_number, 'GR-')) {
                                    $grDoc = \DB::table('goods_receipts')->where('gr_number', $mut->reference_number)->first();
                                    if ($grDoc) {
                                        $realSns = \DB::table('item_serials')->where('goods_receipt_id', $grDoc->id)->where('item_id', $mut->item_id)->pluck('serial_number')->toArray();
                                    }
                                } elseif (str_starts_with($mut->reference_number, 'RTV-')) {
                                    $rtvDoc = \DB::table('return_to_vendors')->where('rtv_number', $mut->reference_number)->first();
                                    if ($rtvDoc) {
                                        $realSns = \DB::table('item_serials')->where('return_to_vendor_id', $rtvDoc->id)->where('item_id', $mut->item_id)->pluck('serial_number')->toArray();
                                    }
                                } elseif (str_starts_with($mut->reference_number, 'GI-')) {
                                    $giDoc = \DB::table('goods_issues')->where('gi_number', $mut->reference_number)->first();
                                    if ($giDoc) {
                                        // 1. Cek Tabel item_serials (Barang Biasa)
                                        if (\Schema::hasColumn('item_serials', 'goods_issue_id')) {
                                            $snMinor = \DB::table('item_serials')->where('goods_issue_id', $giDoc->id)->where('item_id', $mut->item_id)->pluck('serial_number')->toArray();
                                            $realSns = array_merge($realSns, $snMinor);
                                        } else {
                                            $snMinor = \DB::table('item_serials')
                                                ->where('item_id', $mut->item_id)
                                                ->whereIn('status', ['ISSUED', 'USED', 'OUT', 'DISPATCHED'])
                                                ->whereBetween('updated_at', [\Carbon\Carbon::parse($mut->created_at)->subMinutes(1), \Carbon\Carbon::parse($mut->created_at)->addMinutes(1)])
                                                ->pluck('serial_number')->toArray();
                                            $realSns = array_merge($realSns, $snMinor);
                                        }

                                        // 2. Cek Tabel fixed_assets (Barang Aset Tetap seperti Genset)
                                        $snMayor = \DB::table('fixed_assets')
                                            ->where('item_id', $mut->item_id)
                                            ->whereBetween('updated_at', [\Carbon\Carbon::parse($mut->created_at)->subMinutes(1), \Carbon\Carbon::parse($mut->created_at)->addMinutes(1)])
                                            ->pluck('serial_number')->toArray();
                                            
                                        $realSns = array_merge($realSns, $snMayor);
                                        $realSns = array_filter(array_unique($realSns));
                                    }
                                }

                                // Set fallback
                                if (!empty($realSns)) {
                                    $snArray = $realSns;
                                } elseif ($snText) {
                                    $cleanSn = str_replace(['SN Diretur: ', 'SN Terdaftar: ', 'SN: '], '', $snText);
                                    $snArray = array_map('trim', explode('|', $cleanSn));
                                } else {
                                    $snArray = [];
                                }

                                // 🔥 BLOKIR KATA AUTO-FIFO AGAR TIDAK MUNCUL SEBAGAI SN 🔥
                                $snArray = array_filter($snArray, function($val) {
                                    return stripos($val, 'Auto-FIFO') === false && trim($val) !== '';
                                });

                                // Hapus sisa teks [SN: ...] dari catatan utama
                                $mainText = preg_replace('/\[SN:.*?\]/', '', $mainText);
                            @endphp

                            {{-- RENDER TEKS UTAMA --}}
                            <div class="text-dark text-wrap" style="font-size: 0.85rem; line-height: 1.6;">
                                {!! $mainText !!}
                            </div>

                            {{-- RENDER KOTAK SERIAL NUMBER FULL (ACCORDION / COLLAPSE) --}}
                            @if(count($snArray) > 0)
                                <div class="mt-2 border rounded shadow-sm border-warning-subtle overflow-hidden">
                                    <a href="#snCollapse_{{ $mut->id }}" data-bs-toggle="collapse" class="text-decoration-none d-block bg-warning-subtle text-warning-emphasis fw-bold px-2 py-2 d-flex justify-content-between align-items-center" style="font-size: 0.7rem; transition: background 0.2s;">
                                        <span><i class="bi bi-upc-scan me-1"></i> Detail Serial Number</span>
                                        <span>
                                            <span class="badge bg-warning text-dark rounded-pill shadow-sm me-1">{{ count($snArray) }} Unit</span>
                                            <i class="bi bi-chevron-down text-warning-emphasis"></i>
                                        </span>
                                    </a>
                                    <div class="collapse" id="snCollapse_{{ $mut->id }}">
                                        <div class="p-2 bg-light text-muted border-top border-warning-subtle" style="font-size: 0.75rem; font-family: monospace;">
                                            <div class="row row-cols-1 g-2">
                                                @foreach($snArray as $sn)
                                                    <div class="col" style="word-break: break-all;">
                                                        <span class="text-success fw-bold me-2">✓</span><strong class="text-dark">{{ $sn }}</strong>
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
                            @if($mut->type == 'IN')
                                + {{ (float)$mut->qty }} <br><span class="text-muted" style="font-size: 0.65rem;">{{ optional($item->uom)->name }}</span>
                            @else
                                -
                            @endif
                        </td>

                        {{-- KELUAR --}}
                        <td class="py-3 text-center fw-bold text-danger fs-6">
                            @if($mut->type == 'OUT')
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