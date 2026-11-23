@extends('layouts.app')

@push('css')
<style>
    .kpi-filter-card {
        transition: all 0.2s ease;
        cursor: pointer;
        text-decoration: none;
    }
    .kpi-filter-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 14px rgba(15, 23, 42, 0.08) !important;
    }
    .table-trx thead th {
        background-color: #f8fafc;
        color: #475569;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        padding: 0.9rem 0.75rem;
        border-bottom: 2px solid #e2e8f0;
        font-weight: 800;
        white-space: nowrap;
    }
    .table-trx tbody td {
        padding: 0.85rem 0.75rem;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.84rem;
    }
    .table-trx tbody tr:hover {
        background-color: #f8fafc;
    }
</style>
@endpush

@section('content')
<div class="pb-5 container-fluid px-md-4">
    @if(session('success'))
        <div class="border-0 border-4 shadow-sm alert alert-success alert-dismissible fade show rounded-4 border-start border-success">
            <i class="bi bi-check-circle-fill me-2 text-success"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- HEADER --}}
    <div class="mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h3 class="mb-0 fw-bold text-dark"><i class="bi bi-arrow-left-right text-primary me-2"></i>Daftar Transaksi & Berita Acara Aset</h3>
            <p class="mt-1 mb-0 text-muted small">Pusat kendali dokumen BAST (Penyerahan), BAPA (Pengembalian), BASP (Servis), dan BAPP (Disposal).</p>
        </div>

        <div class="mt-3 mt-md-0">
            <a href="{{ route('fixed-assets.transactions.create') }}" class="px-4 shadow-sm btn btn-primary fw-bold rounded-pill">
                <i class="bi bi-plus-circle me-1"></i> Buat Transaksi Baru
            </a>
        </div>
    </div>

    {{-- KPI QUICK FILTER CARDS --}}
    @if(isset($stats))
    <div class="mb-4 row g-3">
        <div class="col-6 col-md">
            <a href="{{ route('fixed-assets.transactions') }}" class="border-0 border-4 shadow-sm card border-start border-dark h-100 rounded-4 kpi-filter-card {{ !request('type') ? 'bg-dark text-white' : 'bg-white text-dark' }}">
                <div class="py-3 card-body">
                    <div class="small fw-bold text-uppercase {{ !request('type') ? 'text-white-50' : 'text-muted' }}">Semua Dokumen</div>
                    <div class="mt-1 fs-4 fw-bold">{{ number_format($stats['total'], 0, ',', '.') }}</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md">
            <a href="{{ route('fixed-assets.transactions', ['type' => 'HANDOVER']) }}" class="border-0 border-4 shadow-sm card border-start border-primary h-100 rounded-4 kpi-filter-card {{ request('type') === 'HANDOVER' ? 'bg-primary text-white' : 'bg-white text-dark' }}">
                <div class="py-3 card-body">
                    <div class="small fw-bold text-uppercase {{ request('type') === 'HANDOVER' ? 'text-white-50' : 'text-primary' }}">Serah Terima (BAST)</div>
                    <div class="mt-1 fs-4 fw-bold">{{ number_format($stats['handover'], 0, ',', '.') }}</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md">
            <a href="{{ route('fixed-assets.transactions', ['type' => 'RETURN']) }}" class="border-0 border-4 shadow-sm card border-start border-warning h-100 rounded-4 kpi-filter-card {{ request('type') === 'RETURN' ? 'bg-warning text-dark' : 'bg-white text-dark' }}">
                <div class="py-3 card-body">
                    <div class="small fw-bold text-uppercase {{ request('type') === 'RETURN' ? 'text-dark' : 'text-warning-emphasis' }}">Pengembalian (BAPA)</div>
                    <div class="mt-1 fs-4 fw-bold">{{ number_format($stats['return'], 0, ',', '.') }}</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md">
            <a href="{{ route('fixed-assets.transactions', ['type' => 'REPAIR']) }}" class="border-0 border-4 shadow-sm card border-start border-info h-100 rounded-4 kpi-filter-card {{ in_array(request('type'), ['REPAIR', 'REPAIR_SEND', 'REPAIR_FINISH']) ? 'bg-info text-dark' : 'bg-white text-dark' }}">
                <div class="py-3 card-body">
                    <div class="small fw-bold text-uppercase {{ in_array(request('type'), ['REPAIR', 'REPAIR_SEND', 'REPAIR_FINISH']) ? 'text-dark' : 'text-info-emphasis' }}">Perbaikan (BASP / SRV)</div>
                    <div class="mt-1 fs-4 fw-bold">{{ number_format($stats['repair'], 0, ',', '.') }}</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md">
            <a href="{{ route('fixed-assets.transactions', ['type' => 'DISPOSAL']) }}" class="border-0 border-4 shadow-sm card border-start border-danger h-100 rounded-4 kpi-filter-card {{ request('type') === 'DISPOSAL' ? 'bg-danger text-white' : 'bg-white text-dark' }}">
                <div class="py-3 card-body">
                    <div class="small fw-bold text-uppercase {{ request('type') === 'DISPOSAL' ? 'text-white-50' : 'text-danger' }}">Disposal (BAPP)</div>
                    <div class="mt-1 fs-4 fw-bold">{{ number_format($stats['disposal'], 0, ',', '.') }}</div>
                </div>
            </a>
        </div>
    </div>
    @endif

    {{-- FILTER & PENCARIAN --}}
    <div class="p-3 mb-4 bg-white border shadow-sm rounded-4">
        <form action="{{ route('fixed-assets.transactions') }}" method="GET" class="row g-2 align-items-center">
            <div class="col-md-3">
                <select name="type" class="form-select border-secondary-subtle" onchange="this.form.submit()">
                    <option value="">-- Semua Jenis Transaksi --</option>
                    <option value="HANDOVER" {{ request('type') === 'HANDOVER' ? 'selected' : '' }}>Serah Terima ke Staf (BAST)</option>
                    <option value="RETURN" {{ request('type') === 'RETURN' ? 'selected' : '' }}>Pengembalian ke Gudang (BAPA)</option>
                    <option value="REPAIR_SEND" {{ request('type') === 'REPAIR_SEND' ? 'selected' : '' }}>Kirim Service Center (BASP)</option>
                    <option value="REPAIR_FINISH" {{ request('type') === 'REPAIR_FINISH' ? 'selected' : '' }}>Selesai Perbaikan (BAPA-SRV)</option>
                    <option value="DISPOSAL" {{ request('type') === 'DISPOSAL' ? 'selected' : '' }}>Penghapusan Aset (BAPP / Disposal)</option>
                    <option value="STATUS_CHANGE" {{ request('type') === 'STATUS_CHANGE' ? 'selected' : '' }}>Semua Update Status</option>
                </select>
            </div>
            <div class="col-md-6">
                <div class="input-group">
                    <span class="bg-white input-group-text border-secondary-subtle text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control border-secondary-subtle" placeholder="Cari No. Dokumen, No. Aset, No. Akuntansi (FA), Kode/Nama Item, Kategori, S/N, atau Karyawan..." value="{{ request('search') }}">
                </div>
            </div>
            <div class="gap-2 col-md-3 d-flex">
                <button type="submit" class="btn btn-dark fw-bold w-100">Cari</button>
                @if(request('search') || request('type'))
                    <a href="{{ route('fixed-assets.transactions') }}" class="border btn btn-outline-secondary fw-bold" title="Reset Filter">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- TABEL DAFTAR DOKUMEN TRANSAKSI --}}
    <div class="overflow-hidden bg-white border border-4 shadow-sm rounded-4 border-top border-primary">
        <div class="table-responsive">
            <table class="table mb-0 align-middle table-trx">
                <thead>
                    <tr>
                        <th class="ps-4">No. Dokumen & Tgl</th>
                        <th>Jenis Transaksi</th>
                        <th>Rincian Unit Aset</th>
                        <th>Pihak Terkait / Tujuan</th>
                        <th>Status Akhir & Catatan</th>
                        <th>Admin</th>
                        <th class="text-end pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $trx)
                    @php
                        $isDisposedTrx = $trx->transaction_type === 'STATUS_CHANGE' && optional($trx->status)->slug === 'disposed';
                        $isPrintable   = in_array($trx->transaction_type, ['HANDOVER', 'RETURN', 'REPAIR_SEND', 'REPAIR_FINISH']) || $isDisposedTrx;
                        $firstAsset    = optional($trx->details->first())->asset;
                        $extraCount    = max(0, $trx->details->count() - 1);

                        $faNumber     = optional($firstAsset)->accounting_asset_number ?: '-';
                        $itemCode     = optional(optional($firstAsset)->item)->code ?: '-';
                        $itemName     = optional(optional($firstAsset)->item)->name ?: '-';
                        $categoryName = optional(optional($firstAsset)->assetCategory)->name
                                        ?? optional(optional(optional($firstAsset)->item)->category)->name
                                        ?? '-';
                        $serialNumber = optional($firstAsset)->serial_number ?: '-';

                        // Ekstrak nama Service Center jika transaksi REPAIR_SEND
                        $serviceCenterName = '-';
                        if ($trx->transaction_type === 'REPAIR_SEND' && str_contains((string) $trx->notes, 'Service Center:')) {
                            $parts = explode('|', $trx->notes);
                            $serviceCenterName = trim(str_replace('Service Center:', '', $parts[0]));
                        }
                    @endphp
                    <tr>
                        {{-- 1. NO DOKUMEN & TANGGAL --}}
                        <td class="ps-4 text-nowrap">
                            <a href="{{ route('fixed-assets.transactions.show', $trx->id) }}" class="fw-bold text-primary text-decoration-none font-monospace d-block">
                                {{ $trx->transaction_number }}
                            </a>
                            <small class="text-muted">
                                <i class="bi bi-calendar3 me-1"></i>{{ \Carbon\Carbon::parse($trx->transaction_date)->format('d M Y') }}
                            </small>
                        </td>

                        {{-- 2. JENIS TRANSAKSI --}}
                        <td class="text-nowrap">
                            @if($trx->transaction_type === 'HANDOVER')
                                <span class="px-2.5 py-1 border badge bg-primary-subtle text-primary border-primary-subtle">
                                    <i class="bi bi-box-arrow-up-right me-1"></i>Serah Terima (BAST)
                                </span>
                            @elseif($trx->transaction_type === 'RETURN')
                                <span class="px-2.5 py-1 border badge bg-warning-subtle text-warning-emphasis border-warning-subtle">
                                    <i class="bi bi-box-arrow-in-left me-1"></i>Pengembalian (BAPA)
                                </span>
                            @elseif($trx->transaction_type === 'REPAIR_SEND')
                                <span class="px-2.5 py-1 border badge bg-info-subtle text-info-emphasis border-info-subtle">
                                    <i class="bi bi-tools me-1"></i>Kirim Servis (BASP)
                                </span>
                            @elseif($trx->transaction_type === 'REPAIR_FINISH')
                                <span class="px-2.5 py-1 border badge bg-success-subtle text-success border-success-subtle">
                                    <i class="bi bi-check2-square me-1"></i>Selesai Servis (BAPA-SRV)
                                </span>
                            @elseif($isDisposedTrx)
                                <span class="px-2.5 py-1 text-white shadow-sm badge bg-danger">
                                    <i class="bi bi-trash3-fill me-1"></i>Disposal (BAPP)
                                </span>
                            @else
                                <span class="px-2.5 py-1 border badge bg-secondary-subtle text-secondary border-secondary-subtle">
                                    <i class="bi bi-arrow-repeat me-1"></i>Update Status
                                </span>
                            @endif
                        </td>

                        {{-- 3. RINCIAN UNIT ASET (LENGKAP: NO ASET, FA, KATEGORI, MASTER ITEM, S/N) --}}
                        <td>
                            <div class="flex-wrap gap-1 d-flex align-items-center">
                                <span class="badge bg-dark rounded-pill">{{ $trx->details->count() }} Unit</span>
                                @if($firstAsset)
                                    <span class="border badge bg-light text-primary border-primary-subtle font-monospace">{{ $firstAsset->asset_number }}</span>
                                    <span class="border badge bg-secondary-subtle text-dark font-monospace">FA: {{ $faNumber }}</span>
                                    <span class="border badge bg-light text-dark border-secondary-subtle">{{ $categoryName }}</span>
                                @endif
                            </div>

                            @if($firstAsset)
                                <div class="mt-1 fw-bold text-dark text-truncate" style="max-width: 320px;" title="{{ $firstAsset->name }}">
                                    {{ $firstAsset->name }}
                                    @if($extraCount > 0)
                                        <span class="text-primary fw-bold">(+{{ $extraCount }} lainnya)</span>
                                    @endif
                                </div>
                                <div class="mt-0.5 text-muted text-truncate" style="max-width: 320px; font-size: 0.75rem;" title="[{{ $itemCode }}] {{ $itemName }}">
                                    <span class="font-monospace text-info-emphasis fw-semibold">[{{ $itemCode }}]</span> {{ $itemName }}
                                </div>
                                <div class="mt-0.5 font-monospace text-muted" style="font-size: 0.74rem;">
                                    <i class="bi bi-upc-scan me-1"></i>S/N: <strong class="text-dark">{{ $serialNumber }}</strong>
                                </div>
                            @endif
                        </td>

                        {{-- 4. PIHAK TERKAIT / TUJUAN --}}
                        <td class="small">
                            @if($trx->transaction_type === 'HANDOVER')
                                <div><span class="text-muted">Penerima:</span> <strong class="text-primary">{{ optional($trx->toUser)->name ?? '-' }}</strong></div>
                                <div class="text-muted" style="font-size: 0.74rem;">Oleh: {{ optional($trx->fromUser)->name ?? '-' }}</div>
                            @elseif($trx->transaction_type === 'RETURN')
                                <div><span class="text-muted">Dari:</span> <strong class="text-danger">{{ optional($trx->fromUser)->name ?? '-' }}</strong></div>
                                <div><span class="text-muted">Ke Gudang:</span> <strong class="text-dark">{{ optional($trx->warehouse)->name ?? '-' }}</strong></div>
                            @elseif($trx->transaction_type === 'REPAIR_SEND')
                                <div><span class="text-muted">Vendor:</span> <strong class="text-info-emphasis">{{ $serviceCenterName }}</strong></div>
                                <div class="text-muted" style="font-size: 0.74rem;">Pengirim: {{ optional($trx->fromUser)->name ?? '-' }}</div>
                            @elseif($trx->transaction_type === 'REPAIR_FINISH')
                                <div><span class="text-muted">Masuk Gudang:</span> <strong class="text-success">{{ optional($trx->warehouse)->name ?? '-' }}</strong></div>
                                <div class="text-muted" style="font-size: 0.74rem;">Penerima: {{ optional($trx->fromUser)->name ?? '-' }}</div>
                            @else
                                <div><span class="text-muted">PIC:</span> <strong class="text-dark">{{ optional($trx->fromUser)->name ?? '-' }}</strong></div>
                            @endif
                        </td>

                        {{-- 5. STATUS AKHIR & CATATAN --}}
                        <td>
                            <span class="mb-1 border badge bg-light text-dark border-secondary-subtle">
                                {{ optional($trx->status)->name ?? '-' }}
                            </span>
                            @if(!empty($trx->notes))
                                <div class="text-muted fst-italic text-truncate" style="max-width: 210px; font-size: 0.75rem;" title="{{ $trx->notes }}">
                                    {{ $trx->notes }}
                                </div>
                            @endif
                        </td>

                        {{-- 6. ADMIN --}}
                        <td class="small text-muted text-nowrap">{{ optional($trx->creator)->name ?? 'System' }}</td>

                        {{-- 7. AKSI --}}
                        <td class="text-end pe-4 text-nowrap">
                            <div class="gap-1 d-inline-flex">
                                @if($isPrintable)
                                    <a href="{{ route('fixed-assets.transactions.print', $trx->id) }}" target="_blank" class="border btn btn-sm btn-light text-dark rounded-pill fw-semibold" title="Cetak Langsung PDF">
                                        <i class="bi bi-printer text-danger"></i>
                                    </a>
                                @endif
                                <a href="{{ route('fixed-assets.transactions.show', $trx->id) }}" class="px-3 btn btn-sm btn-outline-primary fw-bold rounded-pill">
                                    <i class="bi bi-eye me-1"></i> Detail
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-5 text-center text-muted">
                            <i class="mb-2 opacity-25 bi bi-folder-x display-5 d-block"></i>
                            Belum ada dokumen transaksi aset yang sesuai. Klik <strong>Buat Transaksi Baru</strong> untuk memulai.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($transactions->hasPages())
            <div class="px-4 py-3 bg-white border-top d-flex flex-column flex-md-row justify-content-between align-items-center">
                <span class="mb-2 text-muted small mb-md-0">
                    Menampilkan {{ $transactions->firstItem() ?? 0 }} - {{ $transactions->lastItem() ?? 0 }} dari {{ $transactions->total() }} dokumen
                </span>
                <div>
                    {{ $transactions->links('pagination::bootstrap-5') }}
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
