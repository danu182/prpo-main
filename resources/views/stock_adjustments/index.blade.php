@extends('layouts.app')

@section('content')
<div class="container pb-5 text-dark">

    {{-- HEADER HALAMAN --}}
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 fw-bold text-dark">
                <i class="bi bi-journal-text text-warning me-2"></i> Riwayat Penyesuaian Stock
            </h4>
            <div class="mt-1 text-muted small">Daftar seluruh penyesuaian stok dan koreksi inventaris.</div>
        </div>
        <div>
            <a href="{{ route('stock-adjustments.create') }}" class="px-4 shadow-sm btn btn-warning text-dark fw-bold rounded-pill">
                <i class="bi bi-plus-lg me-1"></i> Buat Penyesuaian Stok Baru
            </a>
        </div>
    </div>

    {{-- FLASH MESSAGES --}}
    @if(session('success'))
        <div class="border-0 border-4 shadow-sm alert alert-success alert-dismissible fade show rounded-3 border-start border-success">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- TABEL UTAMA --}}
    <div class="border-0 border-4 shadow-sm card rounded-4 border-top border-warning">
        <div class="p-0 card-body">
            <div class="table-responsive">
                <table class="table mb-0 align-middle table-hover">
                    <thead class="bg-light text-muted small text-uppercase fw-bold border-bottom">
                        <tr>
                            <th class="py-3 ps-4" width="15%">No. Dokumen</th>
                            <th class="py-3" width="15%">Tanggal BA</th>
                            <th class="py-3" width="15%">Lokasi Gudang</th>
                            <th class="py-3 text-center" width="10%">Total Item</th>
                            <th class="py-3" width="20%">Di-Opname Oleh</th> {{-- 👤 KOLOM AUDIT SAKTI --}}
                            <th class="py-3" width="20%">Keterangan / Alasan</th>
                            <th class="py-3 pe-4 text-end" width="5%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($adjustments as $adj)
                        <tr>
                            <td class="py-3 ps-4">
                                <span class="fw-bold text-primary">{{ $adj->adjustment_number }}</span>
                            </td>
                            <td class="py-3 fw-semibold">
                                {{ \Carbon\Carbon::parse($adj->adjustment_date)->format('d M Y') }}
                            </td>
                            <td class="py-3">
                                <span class="px-3 border badge bg-info-subtle text-info-emphasis border-info-subtle rounded-pill">
                                    <i class="bi bi-shop me-1"></i> {{ optional($adj->warehouse)->name }}
                                </span>
                            </td>
                            <td class="py-3 text-center">
                                <span class="badge bg-secondary rounded-pill">
                                    {{ $adj->items_count ?? $adj->items->count() }} Item
                                </span>
                            </td>
                            <td class="py-3">
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-warning-subtle d-flex align-items-center justify-content-center me-2" style="width: 30px; height: 30px;">
                                        <i class="bi bi-person-fill text-warning"></i>
                                    </div>
                                    <div class="fw-bold text-dark small">
                                        {{ optional($adj->uploader)->name ?? 'Sistem' }}
                                    </div>
                                </div>
                            </td>
                            <td class="py-3">
                                <div class="text-muted small text-truncate" style="max-width: 200px;" title="{{ $adj->reason }}">
                                    {{ $adj->reason }}
                                </div>
                            </td>
                            <td class="py-3 pe-4 text-end">
                                {{-- Tombol Detail akan mengarah ke halaman Rincian (Show) --}}
                                <a href="{{ route('stock-adjustments.show', $adj->id) }}" class="px-3 btn btn-sm btn-outline-primary rounded-pill fw-bold">
                                    Rincian
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="py-5 text-center text-muted">
                                <i class="mb-3 opacity-25 bi bi-clipboard-x display-4 d-block"></i>
                                <p class="mb-0 fw-bold">Belum ada data penyesuaian stok.</p>
                                <small>Lakukan Stock Opname untuk menyeimbangkan fisik gudang dengan sistem.</small>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- PAGINATION --}}
        @if($adjustments->hasPages())
        <div class="p-3 bg-light card-footer border-top text-end">
            {{ $adjustments->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
