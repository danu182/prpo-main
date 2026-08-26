@extends('layouts.app')

@push('css')
<style>
    .btn-action-rounded { border-radius: 50rem; font-weight: 600; padding: 0.5rem 1.2rem; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); transition: all 0.2s; }
    .btn-action-rounded:hover { transform: translateY(-2px); box-shadow: 0 0.25rem 0.5rem rgba(0,0,0,0.15); }
    .summary-card { transition: all 0.3s ease; border-left: 5px solid transparent; }
    .summary-card:hover { transform: translateY(-4px); }
</style>
@endpush

@section('content')
<div class="pb-5 container-fluid text-dark">

    {{-- ========================================================================= --}}
    {{-- 1. PRE-CALCULATION LOGIC (DENGAN RADAR HARGA 3 LAPIS) --}}
    {{-- ========================================================================= --}}
    @php
        $totalValuasiMutasi = 0;
        $totalQtyMasuk = 0;
        $totalQtyKeluar = 0;

        foreach($adjustment->items as $item) {
            // LAPIS 1: Coba ambil dari tabel detail penyesuaian
            $unitPrice = (float) ($item->unit_price ?? 0);

            // LAPIS 2 (RADAR CERDAS): Jika 0, intip langsung ke tumpukan fisik gudang (InventoryStock) yang baru masuk
            if ($unitPrice <= 0 && $item->difference > 0) {
                $invStock = \App\Models\InventoryStock::where('reference_number', $adjustment->adjustment_number)
                                ->where('item_id', $item->item_id)
                                ->first();
                if ($invStock) {
                    $unitPrice = (float) ($invStock->unit_price ?? 0);
                }
            }

            // LAPIS 3: Jika masih 0 juga, baru ambil harga bawaan dari Master Barang
            if ($unitPrice <= 0) {
                $unitPrice = (float) (optional($item->item)->purchase_price ?? optional($item->item)->unit_price ?? 0);
            }

            $totalValuasiMutasi += (abs($item->difference) * $unitPrice);

            if($item->difference > 0) $totalQtyMasuk += $item->difference;
            if($item->difference < 0) $totalQtyKeluar += abs($item->difference);
        }
    @endphp

    {{-- ========================================================================= --}}
    {{-- 2. HEADER HALAMAN & TOMBOL AKSI --}}
    {{-- ========================================================================= --}}
    <div class="pb-3 mb-4 row align-items-center border-bottom gy-3">
        <div class="col-xl-7 col-lg-6">
            <h4 class="mb-1 fw-bold text-dark d-flex align-items-center">
                <i class="bi bi-sliders text-primary me-2 fs-3"></i> Detail Penyesuaian Stok
            </h4>
            <div class="flex-wrap gap-3 mt-2 d-flex align-items-center">
                <span class="px-3 py-2 border shadow-sm badge rounded-pill bg-success-subtle text-success border-success-subtle">
                    <i class="bi bi-check-circle-fill me-1" style="font-size: 0.6rem;"></i> DIEKSEKUSI
                </span>
                <span class="text-muted small fw-medium">
                    ID Dokumen: <strong class="text-primary fs-6">{{ $adjustment->adjustment_number }}</strong>
                </span>
            </div>
        </div>

        <div class="col-xl-5 col-lg-6 text-lg-end">
            <div class="flex-wrap gap-2 d-flex justify-content-lg-end align-items-center">
                <a href="{{ route('stock-adjustments.index') }}" class="border btn btn-light btn-action-rounded">
                    <i class="bi bi-arrow-left me-1"></i> Kembali ke Riwayat
                </a>
                <a href="{{ route('stock-adjustments.print', $adjustment->id) }}" target="_blank" class="btn btn-dark btn-action-rounded">
                    <i class="bi bi-printer me-1"></i> Cetak Dokumen
                </a>
            </div>
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- 3. KARTU INFORMASI & RINGKASAN VALUASI --}}
    {{-- ========================================================================= --}}
    <div class="mb-4 row g-4">
        {{-- INFORMASI DOKUMEN --}}
        <div class="col-lg-7">
            <div class="bg-white border-0 shadow-sm card rounded-4 h-100">
                <div class="p-4 card-body d-flex flex-column justify-content-center">
                    <h6 class="mb-3 fw-bold text-muted text-uppercase small"><i class="bi bi-info-circle me-1"></i> Informasi Penyesuaian</h6>
                    <div class="row g-4 text-start">
                        <div class="col-sm-4 border-end">
                            <label class="mb-1 text-muted small fw-semibold d-block">Tanggal Eksekusi</label>
                            <h6 class="mb-0 fw-bold text-dark">
                                <i class="bi bi-calendar3 me-2 text-primary"></i>{{ \Carbon\Carbon::parse($adjustment->adjustment_date)->format('d M Y') }}
                            </h6>
                        </div>
                        <div class="col-sm-4 border-end">
                            <label class="mb-1 text-muted small fw-semibold d-block">Lokasi Gudang</label>
                            <h6 class="mb-0 fw-bold text-dark">
                                <i class="bi bi-shop me-2 text-success"></i>{{ optional($adjustment->warehouse)->name }}
                            </h6>
                        </div>
                        <div class="col-sm-4">
                            <label class="mb-1 text-muted small fw-semibold d-block">Eksekutor (PIC)</label>
                            <h6 class="mb-0 fw-bold text-dark text-truncate">
                                <i class="bi bi-person-badge me-2 text-warning"></i>{{ optional($adjustment->adjuster)->name ?? 'Sistem' }}
                            </h6>
                        </div>
                    </div>
                    <div class="pt-3 mt-4 border-top">
                        <label class="mb-1 text-muted small fw-semibold d-block">Alasan / Keterangan</label>
                        <p class="p-2 mb-0 border small text-secondary bg-light rounded-3 fst-italic lh-sm">{{ $adjustment->reason }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- RINGKASAN VALUASI MUTASI --}}
        <div class="col-lg-5">
            <div class="border-0 shadow-sm card rounded-4 h-100 summary-card bg-primary-subtle border-primary-subtle" style="border-left-color: #0d6efd;">
                <div class="p-4 card-body d-flex flex-column justify-content-between">
                    <div>
                        <div class="mb-1 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 text-primary fw-bold text-uppercase small"><i class="bi bi-wallet2 me-1"></i> Total Valuasi Mutasi</h6>
                            <div class="p-2 bg-white shadow-sm text-primary rounded-circle"><i class="bi bi-cash-stack"></i></div>
                        </div>
                        <h2 class="mb-0 fw-bolder text-primary display-6">Rp {{ number_format($totalValuasiMutasi, 0, ',', '.') }}</h2>
                        <span class="small text-muted fw-medium">*Total nilai absolut seluruh perubahan stok</span>
                    </div>

                    <div class="pt-3 mt-3 border-top border-primary-subtle d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small d-block">Total Item</span>
                            <strong class="text-dark fs-5">{{ $adjustment->items->count() }} Jenis</strong>
                        </div>
                        <div class="text-end">
                            <span class="px-2 py-1 mb-1 border badge bg-success-subtle text-success border-success-subtle rounded-pill d-block"><i class="bi bi-arrow-down-left"></i> {{ $totalQtyMasuk }} Masuk</span>
                            <span class="px-2 py-1 border badge bg-danger-subtle text-danger border-danger-subtle rounded-pill d-block"><i class="bi bi-arrow-up-right"></i> {{ $totalQtyKeluar }} Keluar</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- 4. TABEL RINCIAN BARANG & SERIAL NUMBER --}}
    {{-- ========================================================================= --}}
    <div class="overflow-hidden border-0 border-4 shadow-sm card rounded-4 border-top border-primary">
        <div class="py-3 bg-white card-header border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-list-check me-2 text-primary"></i>Daftar Rincian Mutasi Barang</h6>
        </div>
        <div class="p-0 card-body">
            <div class="table-responsive">
                <table class="table mb-0 align-middle table-hover">
                    <thead class="bg-light text-muted small text-uppercase fw-bold border-bottom">
                        <tr>
                            <th class="py-3 ps-4" width="5%">No</th>
                            <th class="py-3" width="25%">Barang & Kode</th>
                            <th class="py-3 text-end" width="15%">HPP / Unit (Rp)</th>
                            <th class="py-3 text-center" width="12%">Stok Lama</th>
                            <th class="py-3 text-center text-dark" width="12%">Stok Baru</th>
                            <th class="py-3 text-center" width="15%">Selisih Qty</th>
                            <th class="py-3 text-end pe-4" width="16%">Nilai Mutasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($adjustment->items as $index => $item)
                        @php
                            // RADAR CERDAS UNTUK TIAP BARIS (HARGA)
                            $unitPrice = (float) ($item->unit_price ?? 0);

                            if ($unitPrice <= 0 && $item->difference > 0) {
                                $invStock = \App\Models\InventoryStock::where('reference_number', $adjustment->adjustment_number)
                                                ->where('item_id', $item->item_id)
                                                ->first();
                                if ($invStock) $unitPrice = (float) ($invStock->unit_price ?? 0);
                            }

                            if ($unitPrice <= 0) {
                                $unitPrice = (float) (optional($item->item)->purchase_price ?? optional($item->item)->unit_price ?? 0);
                            }

                            $totalVal = abs($item->difference) * $unitPrice;
                            $rowClass = $item->difference < 0 ? 'table-danger-subtle' : ($item->difference > 0 ? 'table-info-subtle' : '');

                            // =========================================================
                            // 🔥 TRIK INTELIJEN: MENGINTIP SN YANG TERLIBAT TRANSAKSI INI 🔥
                            // =========================================================
                            $snList = [];
                            if (optional($item->item)->is_trackable) {
                                // Kita cari SN yang statusnya berubah pada DETIK yang sama dengan dokumen ini dibuat
                                $timeMatch = \Carbon\Carbon::parse($adjustment->created_at);
                                $timeStart = $timeMatch->copy()->subSeconds(5);
                                $timeEnd = $timeMatch->copy()->addSeconds(5);

                                if ($item->difference > 0) {
                                    // SN Surplus (Baru Ditemukan) -> Cari yang created_at nya cocok
                                    $snList = \DB::table('item_serials')
                                        ->where('item_id', $item->item_id)
                                        ->where('warehouse_id', $adjustment->warehouse_id)
                                        ->whereBetween('created_at', [$timeStart, $timeEnd])
                                        ->pluck('serial_number')
                                        ->toArray();
                                } elseif ($item->difference < 0) {
                                    // SN Defisit (Hilang) -> Cari yang statusnya diubah jadi LOST pada waktu tersebut
                                    $snList = \DB::table('item_serials')
                                        ->where('item_id', $item->item_id)
                                        ->where('status', 'LOST')
                                        ->whereBetween('updated_at', [$timeStart, $timeEnd])
                                        ->pluck('serial_number')
                                        ->toArray();
                                }
                            }
                        @endphp
                        <tr class="{{ $rowClass }}">
                            <td class="py-3 align-top ps-4 text-muted fw-bold">{{ $index + 1 }}</td>
                            <td class="py-3">
                                <div class="fw-bold text-dark">{{ optional($item->item)->name ?? 'Item Tidak Ditemukan' }}</div>
                                <div class="small text-muted font-monospace"><i class="bi bi-upc-scan me-1"></i>{{ optional($item->item)->code ?? '-' }}</div>

                                {{-- 🔥 TAMPILAN SN JIKA DITEMUKAN 🔥 --}}
                                @if(count($snList) > 0)
                                    <div class="mt-2 p-2 bg-white rounded shadow-sm border {{ $item->difference > 0 ? 'border-success-subtle' : 'border-danger-subtle' }}">
                                        <span class="d-block small fw-bold mb-1 {{ $item->difference > 0 ? 'text-success' : 'text-danger' }}">
                                            <i class="bi {{ $item->difference > 0 ? 'bi-plus-circle-fill' : 'bi-dash-circle-fill' }} me-1"></i> 
                                            SN {{ $item->difference > 0 ? 'Ditemukan' : 'Hilang' }} ({{ count($snList) }} unit):
                                        </span>
                                        <div class="flex-wrap gap-1 d-flex">
                                            @foreach($snList as $sn)
                                                <span class="badge border fw-medium {{ $item->difference > 0 ? 'bg-success-subtle text-success border-success-subtle' : 'bg-danger-subtle text-danger border-danger-subtle' }}" style="font-size: 0.65rem;">
                                                    {{ $sn }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </td>
                            <td class="py-3 align-top text-end fw-semibold text-secondary">
                                Rp {{ number_format($unitPrice, 0, ',', '.') }}
                            </td>
                            <td class="py-3 text-center align-top fw-bold text-muted">
                                {{ (float)$item->previous_stock }} <span class="small fw-normal">{{ optional($item->item)->unit }}</span>
                            </td>
                            <td class="py-3 text-center align-top fw-bolder text-primary fs-6">
                                {{ (float)$item->new_stock }} <span class="small fw-normal">{{ optional($item->item)->unit }}</span>
                            </td>
                            <td class="py-3 text-center align-top">
                                @if($item->difference > 0)
                                    <span class="px-3 py-2 border shadow-sm badge bg-success-subtle text-success border-success-subtle rounded-pill">
                                        <i class="bi bi-arrow-down-left me-1"></i> +{{ (float)$item->difference }}
                                    </span>
                                @elseif($item->difference < 0)
                                    <span class="px-3 py-2 border shadow-sm badge bg-danger-subtle text-danger border-danger-subtle rounded-pill">
                                        <i class="bi bi-arrow-up-right me-1"></i> {{ (float)$item->difference }}
                                    </span>
                                @else
                                    <span class="px-3 py-2 border shadow-sm badge bg-light text-muted rounded-pill">
                                        = 0 (Tetap)
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 align-top text-end pe-4 fw-bold">
                                @if($item->difference > 0)
                                    <span class="text-success">+ Rp {{ number_format($totalVal, 0, ',', '.') }}</span>
                                @elseif($item->difference < 0)
                                    <span class="text-danger">- Rp {{ number_format($totalVal, 0, ',', '.') }}</span>
                                @else
                                    <span class="text-muted">Rp 0</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light fw-bold border-top">
                        <tr>
                            <td colspan="6" class="py-3 text-end text-uppercase text-muted small">Total Valuasi Mutasi Keseluruhan :</td>
                            <td class="py-3 text-end pe-4 text-primary fs-6">Rp {{ number_format($totalValuasiMutasi, 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <div class="p-4 bg-light card-footer border-top small text-muted">
            <i class="bi bi-info-circle me-1"></i> Dokumen penyesuaian ini bersifat mutlak dan telah memperbarui saldo kuantitas serta HPP di tabel riwayat Kartu Stok Gudang.
        </div>
    </div>
</div>

<style>
    @media print {
        .btn, a, .card-footer { display: none !important; }
        .container { width: 100% !important; max-width: 100% !important; }
        .card { border: 1px solid #ddd !important; box-shadow: none !important; }
        .bg-light { background-color: #fff !important; }
    }
</style>
@endsection