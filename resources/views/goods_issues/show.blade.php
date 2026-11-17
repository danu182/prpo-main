@extends('layouts.app')

@push('css')
<style>
    /* 🔥 DESIGN STEMPEL VOID SAKTI 🔥 */
    .void-stamp-container {
        position: absolute;
        top: 20%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-15deg);
        z-index: 9999;
        pointer-events: none;
        opacity: 0.15; /* Transparan agar tidak menutupi teks */
        display: none;
    }
    .is-void .void-stamp-container {
        display: block;
    }
    .void-stamp {
        border: 12px double #dc3545;
        color: #dc3545;
        font-family: 'Courier New', Courier, monospace;
        font-size: 100px;
        font-weight: 900;
        padding: 10px 40px;
        border-radius: 20px;
        text-transform: uppercase;
        letter-spacing: 15px;
        background-color: rgba(255, 255, 255, 0.5);
    }
</style>
@endpush

@section('content')
<div class="pb-5 container-fluid text-dark position-relative {{ optional($gi->status)->slug === 'void' ? 'is-void' : '' }}">

    {{-- Elemen Stempel --}}
    <div class="void-stamp-container">
        <div class="void-stamp">VOID</div>
    </div>

    @php
        // =========================================================================
        // 🔥 KUMPULKAN SEMUA ASET TETAP UNTUK TOMBOL CETAK MASSAL DI HEADER 🔥
        // =========================================================================
        $allGiAssetIds = [];
        foreach($gi->items as $giItem) {
            if ($giItem->notes) {
                preg_match_all('/AST\/[0-9]{4}\/[0-9]{2}\/[0-9]{4}/', $giItem->notes, $m);
                if (!empty($m[0])) {
                    $ids = \App\Models\FixedAsset::whereIn('asset_number', $m[0])->pluck('id')->toArray();
                    $allGiAssetIds = array_merge($allGiAssetIds, $ids);
                }
            }
        }
        $allGiAssetIds = array_unique($allGiAssetIds);
    @endphp

    {{-- HEADER HALAMAN & TOMBOL AKSI --}}
    <div class="mb-4 d-flex justify-content-between align-items-end">
        <div>
            <h4 class="mb-1 fw-bold text-dark">
                <i class="bi bi-box-arrow-up me-2 text-danger"></i>Detail Pengeluaran Barang
            </h4>
            <div class="text-muted small">
                Dokumen GI: <strong class="text-danger">{{ $gi->gi_number }}</strong>
            </div>
        </div>
        <div class="gap-2 d-flex">
            <a href="{{ route('goods-issues.index') }}" class="border shadow-sm btn btn-light rounded-pill fw-bold">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>

            @if(optional($gi->status)->slug !== 'void')
                <form action="{{ route('goods-issues.void', $gi->gi_number) }}" method="POST" class="d-inline form-void-gi">
                    @csrf
                    <button type="button" class="shadow-sm btn btn-outline-danger fw-bold rounded-pill btn-void">
                        <i class="bi bi-x-octagon-fill me-1"></i> Void Transaksi
                    </button>
                </form>

                {{-- 🔥 TOMBOL CETAK MASSAL SEMUA ASET DI DOKUMEN INI (JIKA ADA ASET TETAP) 🔥 --}}
                @if(count($allGiAssetIds) > 0)
                    <a href="{{ route('inventory.print_label', ['ids' => implode(',', $allGiAssetIds)]) }}" target="_blank" class="shadow-sm btn btn-primary rounded-pill fw-bold" title="Cetak Semua Label Aset Tetap (Tom & Jerry 127)">
                        <i class="bi bi-printer-fill me-1"></i> Cetak Semua Aset ({{ count($allGiAssetIds) }})
                    </a>
                @endif

                <a href="{{ route('goods-issues.print', $gi->gi_number) }}" target="_blank" class="px-3 shadow-sm btn btn-warning rounded-pill fw-bold text-dark">
                    <i class="bi bi-printer-fill me-1"></i> Cetak Bukti Keluar
                </a>

                <a href="{{ route('goods-issues.bast', $gi->gi_number) }}" target="_blank" class="px-3 shadow-sm btn btn-danger rounded-pill fw-bold">
                    <i class="bi bi-file-earmark-check-fill me-1"></i> Cetak BAST
                </a>
            @else
                <span class="px-4 py-2 border border-2 shadow-sm badge bg-danger rounded-pill fs-6 border-danger d-flex align-items-center">
                    <i class="bi bi-ban me-2"></i> DOKUMEN VOID (BATAL)
                </span>
            @endif
        </div>
    </div>

    {{-- KARTU INFORMASI DOKUMEN --}}
    <div class="mb-4 border-0 shadow-sm card rounded-4 bg-light">
        <div class="p-4 card-body">
            <div class="row g-4">
                <div class="col-md-5 border-end">
                    <h6 class="mb-3 fw-bold text-secondary text-uppercase" style="font-size: 0.8rem;">Informasi Penyerahan</h6>
                    <table class="table mb-0 table-sm table-borderless small">
                        <tr>
                            <td class="align-middle text-muted" width="35%">Waktu Keluar</td>
                            <td class="align-middle fw-bold">:
                                {{ \Carbon\Carbon::parse($gi->issue_date)->format('d M Y') }}
                                <span class="ms-1 text-muted fw-normal" style="font-size: 0.75rem;">
                                    <i class="mx-1 bi bi-clock"></i>{{ \Carbon\Carbon::parse($gi->created_at)->format('H:i') }} WIB
                                </span>
                            </td>
                        </tr>
                        <tr><td class="align-middle text-muted">Penerima</td><td class="align-middle fw-bold text-primary">: {{ $gi->requester_name }}</td></tr>
                        <tr><td class="align-middle text-muted">Departemen</td><td class="align-middle fw-bold">: {{ $gi->department ?? '-' }}</td></tr>
                        <tr>
                            <td class="align-middle text-muted">Gudang Asal</td>
                            <td class="align-middle fw-bold">:
                                <span class="px-2 py-1 bg-white border shadow-sm badge text-secondary border-secondary-subtle ms-1">
                                    <i class="bi bi-shop me-1"></i> {{ optional($gi->warehouse)->name ?? 'Gudang Utama' }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="align-middle text-muted">Status Dokumen</td>
                            <td class="align-middle fw-bold">:
                                @if($gi->status)
                                    <span class="badge bg-{{ $gi->status->color }}-subtle text-{{ $gi->status->color }} border border-{{ $gi->status->color }}-subtle rounded-pill px-3 shadow-sm ms-1 mt-1">
                                        {{ $gi->status->name }}
                                    </span>
                                @else
                                    <span class="px-3 mt-1 badge bg-secondary-subtle text-secondary rounded-pill ms-1">-</span>
                                @endif
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-7">
                    <h6 class="mb-3 fw-bold text-secondary text-uppercase" style="font-size: 0.8rem;">Catatan / Keperluan</h6>
                    <div class="p-3 bg-white border rounded-3 text-dark small" style="min-height: 90px;">
                        {{ $gi->notes ?: 'Tidak ada catatan khusus.' }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- TABEL BARANG DIKELUARKAN --}}
    <div class="overflow-hidden border-0 shadow-sm card rounded-4">
        <div class="py-3 bg-white card-header border-bottom">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-list-check me-2 text-danger"></i>Rincian Barang Dikeluarkan</h6>
        </div>
        <div class="p-0 card-body table-responsive">
            <table class="table mb-0 align-middle table-hover">
                <thead class="bg-light text-muted small text-uppercase">
                    <tr>
                        <th class="py-3 ps-4" width="5%">No</th>
                        <th class="py-3" width="32%">Nama Barang & Kode</th>
                        <th class="py-3 text-center" width="10%">Qty Keluar</th>
                        <th class="py-3 text-center" width="10%">Qty Retur</th>
                        <th class="py-3 pe-4" width="43%">Catatan / SN Aset & Cetak Label</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($gi->items as $index => $item)
                        @php
                            $isRealAsset = false;
                            $liveAssets = collect();
                            $snList = [];
                            $cleanTextNotes = '';

                            if ($item->notes) {
                                // 1. Deteksi Aset Tetap (Format AST/xxxx/xx/xxxx)
                                preg_match_all('/AST\/[0-9]{4}\/[0-9]{2}\/[0-9]{4}/', $item->notes, $matches);
                                $astNumbers = $matches[0];

                                if (!empty($astNumbers)) {
                                    $isRealAsset = true;
                                    $liveAssets = \App\Models\FixedAsset::whereIn('asset_number', $astNumbers)->get();
                                } else {
                                    // 2. Deteksi Serial Number Stok Minor
                                    // Cek dari tabel item_serials jika ada relasi goods_issue_id
                                    if (\Schema::hasColumn('item_serials', 'goods_issue_id')) {
                                        $snList = \DB::table('item_serials')
                                            ->where('goods_issue_id', $gi->id)
                                            ->where('item_id', $item->item_id)
                                            ->pluck('serial_number')
                                            ->toArray();
                                    }

                                    // Jika belum ketemu di item_serials, ekstrak dari teks catatan (misal: "SN: AAA, BBB | ...")
                                    if (empty($snList) && preg_match('/SN:\s*([^|\n\]]+)/i', $item->notes, $snMatch)) {
                                        $extracted = array_map('trim', explode(',', $snMatch[1]));
                                        $snList = array_filter($extracted, function($v) {
                                            return !empty($v) && stripos($v, 'Auto-Trace') === false;
                                        });
                                    }

                                    // Bersihkan teks catatan biasa (buang bagian SN: ... atau Auto-Trace agar rapi)
                                    $cleanTextNotes = preg_replace('/\|?\s*SN:\s*[^|\n]+/i', '', $item->notes);
                                    $cleanTextNotes = preg_replace('/\|?\s*Auto-Trace:[^|\n]+/i', '', $cleanTextNotes);
                                    $cleanTextNotes = trim($cleanTextNotes, " |\t\n\r\0\x0B");
                                }
                            }
                        @endphp

                        <tr class="border-bottom">
                            <td class="py-3 ps-4 text-muted">{{ $index + 1 }}</td>
                            <td>
                                <h6 class="mb-1 fw-bold text-dark">{{ $item->item_name ?? optional($item->item)->name }}</h6>
                                <span class="border badge bg-secondary-subtle text-secondary border-secondary-subtle">{{ optional($item->item)->code }}</span>

                                {{-- LABEL STATUS TRANSAKSI --}}
                                @if($isRealAsset)
                                    <span class="border badge bg-primary-subtle text-primary border-primary-subtle ms-1">Aset Tetap</span>
                                @elseif(count($snList) > 0 || str_contains($item->notes ?? '', 'SN:'))
                                    <span class="border badge bg-warning-subtle text-warning-emphasis border-warning-subtle ms-1">Stok Serial (SN)</span>
                                @else
                                    <span class="border badge bg-secondary-subtle text-secondary border-secondary-subtle ms-1">Stok Biasa</span>
                                @endif
                            </td>

                            <td class="py-3 text-center fw-bold text-danger fs-5">
                                <i class="bi bi-box-arrow-up-right me-1"></i> {{ (float) $item->qty_issued }} <br>
                                <span class="text-nowrap fw-bold text-muted text-uppercase" style="font-size: 0.65rem;">
                                    {{ $item->getRawOriginal('uom') ?: (optional(optional($item->item)->uom)->name ?? 'PCS') }}
                                </span>
                            </td>

                            <td class="text-center fw-bold fs-6">
                                @if((float)$item->qty_returned > 0)
                                    <span class="text-warning"><i class="bi bi-arrow-return-left me-1"></i>{{ (float)$item->qty_returned }}</span>
                                @else
                                    <span class="opacity-50 text-muted">0</span>
                                @endif
                            </td>

                            <td class="py-3 pe-4 small">
                                {{-- SKENARIO A: JIKA BARANG ADALAH ASET TETAP (MAJOR) --}}
                                @if($isRealAsset && $liveAssets->count() > 0)
                                    @if($liveAssets->count() > 1)
                                        <div class="mb-2 text-end">
                                            <a href="{{ route('inventory.print_label', ['ids' => implode(',', $liveAssets->pluck('id')->toArray())]) }}"
                                               target="_blank"
                                               class="px-2 py-1 shadow-sm btn btn-sm btn-primary rounded-pill fw-bold"
                                               style="font-size: 0.7rem;">
                                                <i class="bi bi-printer-fill me-1"></i> Cetak Massal Aset ({{ $liveAssets->count() }})
                                            </a>
                                        </div>
                                    @endif

                                    <div class="gap-2 d-flex flex-column">
                                        @foreach($liveAssets as $liveAst)
                                            <div class="p-2 bg-white border rounded shadow-sm d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong class="text-dark">{{ $liveAst->asset_number }}</strong>
                                                    @if($liveAst->serial_number)
                                                        <span class="text-primary ms-1">(SN: {{ $liveAst->serial_number }})</span>
                                                    @endif
                                                    @if($liveAst->accounting_asset_number)
                                                        <span class="badge bg-light text-dark border ms-1">FA: {{ $liveAst->accounting_asset_number }}</span>
                                                    @endif
                                                    @if($liveAst->spesifikasi_detail)
                                                        <div class="mt-1 text-muted" style="font-size: 0.72rem;">Spek: {!! strip_tags($liveAst->spesifikasi_detail) !!}</div>
                                                    @endif
                                                </div>
                                                {{-- TOMBOL CETAK SATUAN ASET --}}
                                                <a href="{{ route('inventory.print_label', $liveAst->id) }}"
                                                   target="_blank"
                                                   class="px-2 py-1 shadow-sm btn btn-sm btn-outline-primary rounded-pill fw-bold ms-2 text-nowrap"
                                                   style="font-size: 0.7rem;"
                                                   title="Cetak Label Tom & Jerry 127">
                                                    <i class="bi bi-printer-fill me-1"></i> Cetak
                                                </a>
                                            </div>
                                        @endforeach
                                    </div>

                                {{-- SKENARIO B: JIKA BARANG ADALAH STOK MINOR DENGAN SERIAL NUMBER --}}
                                @elseif(count($snList) > 0)
                                    @if(!empty($cleanTextNotes))
                                        <div class="mb-2 text-dark">• {{ $cleanTextNotes }}</div>
                                    @endif

                                    <div class="p-2 border rounded bg-light border-secondary-subtle">
                                        <div class="mb-2 d-flex justify-content-between align-items-center">
                                            <span class="fw-bold text-dark" style="font-size: 0.75rem;">
                                                <i class="bi bi-upc-scan me-1 text-primary"></i> SERIAL NUMBER ({{ count($snList) }} Unit):
                                            </span>
                                            {{-- TOMBOL CETAK MASSAL SN --}}
                                            @if(count($snList) > 1)
                                                <a href="{{ route('inventory.print_label', ['item_id' => $item->item_id, 'raw_sn' => implode(',', $snList)]) }}"
                                                   target="_blank"
                                                   class="px-2 py-1 shadow-sm btn btn-sm btn-primary rounded-pill fw-bold"
                                                   style="font-size: 0.68rem;">
                                                    <i class="bi bi-printer-fill me-1"></i> Cetak Massal ({{ count($snList) }})
                                                </a>
                                            @endif
                                        </div>

                                        <div class="flex-wrap gap-2 d-flex">
                                            @foreach($snList as $sn)
                                                <div class="px-2 py-1 bg-white border shadow-sm d-flex align-items-center rounded-pill border-secondary-subtle">
                                                    <span class="fw-semibold text-dark me-2" style="font-size: 0.75rem;">
                                                        <i class="bi bi-hash text-muted"></i>{{ $sn }}
                                                    </span>
                                                    {{-- TOMBOL CETAK SATUAN SN --}}
                                                    <a href="{{ route('inventory.print_label', ['item_id' => $item->item_id, 'raw_sn' => $sn]) }}"
                                                       target="_blank"
                                                       class="p-1 bg-light btn btn-sm rounded-circle d-flex align-items-center justify-content-center"
                                                       style="width: 22px; height: 22px; border: 1px solid #dee2e6;"
                                                       title="Cetak Label Ini Saja">
                                                        <i class="bi bi-printer-fill text-primary" style="font-size: 0.65rem;"></i>
                                                    </a>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>

                                {{-- SKENARIO C: STOK BIASA TANPA SN --}}
                                @else
                                    <div class="text-muted">
                                        {!! $item->notes ? nl2br(e(str_replace(' | ', "\n• ", '• ' . $item->notes))) : '-' !!}
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- KARTU RIWAYAT PENGEMBALIAN / RETUR (MUNCUL JIKA ADA) --}}
    @if($gi->returns && $gi->returns->count() > 0)
    <div class="mt-4 overflow-hidden border-0 border-4 shadow-sm card rounded-4 border-top border-warning">
        <div class="py-3 bg-white card-header border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-arrow-return-left me-2 text-warning"></i>Riwayat Pengembalian (Document Flow)</h6>
            <span class="border shadow-sm badge bg-light text-dark"><i class="bi bi-receipt me-1"></i> {{ $gi->returns->count() }} Dokumen Retur</span>
        </div>
        <div class="p-0 card-body table-responsive">
            <table class="table mb-0 align-middle table-hover">
                <thead class="bg-light text-muted small text-uppercase">
                    <tr>
                        <th class="py-3 ps-4" width="20%">No. Dokumen Retur</th>
                        <th class="py-3" width="20%">Waktu Kembali</th>
                        <th class="py-3" width="25%">Dikembalikan Oleh</th>
                        <th class="py-3 pe-4" width="35%">Diterima di Gudang (Tujuan Retur)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($gi->returns as $ret)
                        <tr class="border-bottom">
                            <td class="py-3 ps-4">
                                <a href="{{ route('goods-issue-returns.show', $ret->id) }}" target="_blank" class="fw-bold text-warning-emphasis text-decoration-none">
                                    <i class="bi bi-link-45deg me-1"></i>{{ $ret->return_number }}
                                </a>
                            </td>
                            <td class="py-3">
                                <div class="text-dark fw-bold">{{ \Carbon\Carbon::parse($ret->return_date)->format('d M Y') }}</div>
                                <div class="mt-1 small text-muted">
                                    <i class="bi bi-clock me-1"></i>{{ \Carbon\Carbon::parse($ret->created_at)->format('H:i') }} WIB
                                </div>
                            </td>
                            <td class="py-3 text-dark"><i class="bi bi-person-badge text-info me-1"></i> {{ $ret->returned_by_name }}</td>
                            <td class="py-3 pe-4">
                                <span class="px-3 py-2 border shadow-sm badge bg-warning-subtle text-warning-emphasis border-warning-subtle rounded-pill">
                                    <i class="bi bi-shop me-1"></i> {{ optional($ret->warehouse)->name ?? '-' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.querySelectorAll('.btn-void').forEach(btn => {
        btn.addEventListener('click', function(e) {
            let form = this.closest('.form-void-gi');
            Swal.fire({
                title: 'PERINGATAN KERAS! 🚨',
                html: "Anda akan membatalkan (VOID) dokumen ini. <br><br><b>Perhatian:</b><br>1. Stok barang akan otomatis dikembalikan ke gudang.<br>2. Transaksi beda bulan tidak bisa di-Void.<br>3. Tindakan ini tidak bisa dibatalkan!",
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, VOID Dokumen Ini!',
                cancelButtonText: 'Batal',
                customClass: { confirmButton: 'rounded-pill fw-bold px-4' },
                borderRadius: '15px'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
</script>
@endpush
