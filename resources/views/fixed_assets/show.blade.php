@extends('layouts.app')

@push('css')
<style>
    /* 🔥 KUSTOMISASI TABEL SAAS MODERN 🔥 */
    .card-table-wrapper {
        border-radius: 16px;
        overflow: hidden;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        background: #fff;
    }
    .table-modern { margin-bottom: 0; }
    .table-modern thead th {
        background-color: #f8fafc;
        color: #64748b;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 1.2rem 1.5rem;
        border-bottom: 1px solid #e2e8f0;
        font-weight: 700;
        border-top: none;
    }
    .table-modern tbody td {
        padding: 1.25rem 1.5rem;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
        font-size: 0.9rem;
    }
    .table-modern tbody tr { transition: all 0.2s ease; background-color: #ffffff; }
    .table-modern tbody tr:hover { background-color: #f8fafc !important; }
</style>
@endpush

@section('content')
{{-- 🔥 DIUBAH MENJADI FULL WIDTH (CONTAINER-FLUID) 🔥 --}}
<div class="pb-5 container-fluid text-dark px-md-4">

    {{-- HEADER & TOMBOL KEMBALI --}}
    <div class="mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <a href="{{ route('assets.index') }}" class="text-decoration-none text-muted small fw-bold mb-2 d-inline-block">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar Aset
            </a>
            <h4 class="mb-0 fw-bold text-dark d-flex align-items-center">
                <i class="bi bi-journal-text me-2 text-primary"></i> Kartu Stok (Ledger)
            </h4>
            <div class="mt-1 text-muted small">Riwayat mutasi keluar-masuk barang secara terperinci.</div>
        </div>
    </div>

    {{-- KARTU INFORMASI BARANG (SUMMARY) --}}
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden border-top border-primary border-4">
                <div class="card-body p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-4">

                    {{-- Info Barang Kiri --}}
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary-subtle rounded-4 d-flex align-items-center justify-content-center border border-primary-subtle text-primary" style="width: 70px; height: 70px;">
                            <i class="bi bi-box-seam fs-2"></i>
                        </div>
                        <div>
                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle mb-1">
                                {{ $item->code ?? 'KODE-N/A' }}
                            </span>
                            <h4 class="fw-bold mb-0 text-dark">{{ $item->name }}</h4>
                            <small class="text-muted font-monospace"><i class="bi bi-upc-scan me-1"></i> ID Barang: #{{ $item->id }}</small>
                        </div>
                    </div>

                    {{-- Saldo Saat Ini Kanan --}}
                    <div class="text-md-end text-start border-md-start ps-md-5">
                        <p class="text-muted small fw-bold text-uppercase mb-1 tracking-wide">Saldo Stok Saat Ini</p>
                        <div class="d-flex align-items-center justify-content-md-end gap-3">
                            <h1 class="fw-bold mb-0 {{ $item->current_stock <= 0 ? 'text-danger' : 'text-primary' }}" style="font-size: 2.5rem;">
                                {{ number_format($item->current_stock, 0, ',', '.') }}
                            </h1>
                            <div>
                                @if($item->current_stock <= 0)
                                    <span class="badge bg-danger-subtle text-danger rounded-pill shadow-sm px-3 py-2"><i class="bi bi-exclamation-octagon me-1"></i> Habis</span>
                                @elseif($item->current_stock <= 10)
                                    <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill shadow-sm px-3 py-2"><i class="bi bi-exclamation-triangle me-1"></i> Menipis</span>
                                @else
                                    <span class="badge bg-success-subtle text-success rounded-pill shadow-sm px-3 py-2"><i class="bi bi-check-circle-fill me-1"></i> Aman</span>
                                @endif
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    {{-- TABEL RIWAYAT MUTASI MODERN --}}
    <div class="mb-5 border-4 card-table-wrapper border-top border-primary">
        <div class="px-4 py-3 bg-white card-header border-bottom-0 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-list-columns-reverse text-primary me-2"></i>Daftar Mutasi Stok</h6>
        </div>
        <div class="p-0 card-body table-responsive">
            <table class="table align-middle table-modern" style="min-width: 1100px;">
                <thead>
                    <tr>
                        <th class="ps-4" width="12%">Tanggal</th>
                        <th width="10%">Tipe</th>
                        <th width="15%">Ref Dokumen</th>
                        <th class="text-end" width="10%">Saldo Awal</th>
                        <th class="text-center" width="10%">IN / OUT</th>
                        <th class="text-end" width="10%">Saldo Akhir</th>
                        <th width="18%">Keterangan</th>
                        <th class="pe-4 text-end" width="15%">Diproses Oleh</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($mutations as $mut)
                        <tr>
                            <td class="ps-4 text-muted small">
                                <div class="fw-bold text-dark">{{ \Carbon\Carbon::parse($mut->created_at)->format('d M Y') }}</div>
                                <div style="font-size: 0.7rem;"><i class="bi bi-clock me-1"></i>{{ \Carbon\Carbon::parse($mut->created_at)->format('H:i:s') }} WIB</div>
                            </td>

                            <td>
                                @if($mut->type == 'IN')
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 shadow-sm">
                                        <i class="bi bi-box-arrow-in-down me-1"></i> MASUK
                                    </span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3 shadow-sm">
                                        <i class="bi bi-box-arrow-up me-1"></i> KELUAR
                                    </span>
                                @endif
                            </td>

                            <td class="fw-bold small text-primary">
                                @if(str_starts_with($mut->reference_number ?? '', 'GI'))
                                    <a href="{{ route('goods-issues.show', \App\Models\GoodsIssue::where('gi_number', $mut->reference_number)->first()->id ?? 0) }}" class="text-decoration-none border-bottom border-primary pb-1" title="Lihat Surat Jalan">
                                        <i class="bi bi-box-arrow-up-right me-1"></i> {{ $mut->reference_number }}
                                    </a>
                                @else
                                    {{ $mut->reference_number ?? '-' }}
                                @endif
                            </td>

                            <td class="text-end text-muted fw-medium">
                                {{ number_format($mut->balance_before, 0, ',', '.') }}
                            </td>

                            <td class="text-center fw-bold fs-6 {{ $mut->type == 'IN' ? 'text-success' : 'text-danger' }}">
                                {{ $mut->type == 'IN' ? '+' : '-' }} {{ number_format($mut->qty, 0, ',', '.') }}
                            </td>

                            <td class="text-end fw-bold text-dark fs-6">
                                {{ number_format($mut->balance_after, 0, ',', '.') }}
                            </td>

                            <td class="small text-muted fst-italic" style="line-height: 1.4;">
                                {{ $mut->notes ?? '-' }}
                            </td>

                            <td class="pe-4 text-end">
                                <div class="d-flex align-items-center justify-content-end gap-2">
                                    <div class="d-flex flex-column text-end">
                                        <span class="fw-bold text-dark small">{{ optional($mut->creator)->name ?? 'Sistem' }}</span>
                                        <span class="text-muted" style="font-size: 0.7rem;">Administrator</span>
                                    </div>
                                    <div class="bg-primary shadow-sm text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                        {{ strtoupper(substr(optional($mut->creator)->name ?? 'S', 0, 2)) }}
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-5 text-center text-muted">
                                <i class="mb-3 opacity-50 bi bi-clock-history text-secondary display-4 d-block"></i>
                                <h6 class="fw-bold text-dark">Belum Ada Riwayat Mutasi</h6>
                                <p class="mb-0 small">Belum ada riwayat pergerakan stok keluar/masuk untuk barang ini.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- PAGINASI --}}
        @if($mutations->hasPages())
        <div class="p-3 bg-white card-footer border-top rounded-bottom-4 d-flex justify-content-center">
            {{ $mutations->links('pagination::bootstrap-5') }}
        </div>
        @endif
    </div>

</div>
@endsection
