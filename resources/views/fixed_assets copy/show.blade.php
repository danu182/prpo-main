@extends('layouts.app')

@section('content')
<div class="container pb-5 text-dark">

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
            <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
                <div class="card-body p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-4">

                    {{-- Info Barang Kiri --}}
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-light rounded-3 d-flex align-items-center justify-content-center border" style="width: 60px; height: 60px;">
                            <i class="bi bi-box-seam text-secondary fs-3"></i>
                        </div>
                        <div>
                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle mb-1">
                                {{ $item->code ?? 'KODE-N/A' }}
                            </span>
                            <h5 class="fw-bold mb-0 text-dark">{{ $item->name }}</h5>
                            <small class="text-muted">ID Barang: #{{ $item->id }}</small>
                        </div>
                    </div>

                    {{-- Saldo Saat Ini Kanan --}}
                    <div class="text-md-end text-start border-md-start ps-md-4">
                        <p class="text-muted small fw-bold text-uppercase mb-1 tracking-wide">Saldo Stok Saat Ini</p>
                        <div class="d-flex align-items-center justify-content-md-end gap-2">
                            <h2 class="fw-bold mb-0 {{ $item->current_stock <= 0 ? 'text-danger' : 'text-primary' }}">
                                {{ number_format($item->current_stock, 0, ',', '.') }}
                            </h2>
                            @if($item->current_stock <= 0)
                                <span class="badge bg-danger-subtle text-danger rounded-pill shadow-sm"><i class="bi bi-exclamation-octagon"></i> Habis</span>
                            @elseif($item->current_stock <= 10)
                                <span class="badge bg-warning-subtle text-warning rounded-pill shadow-sm"><i class="bi bi-exclamation-triangle"></i> Menipis</span>
                            @else
                                <span class="badge bg-success-subtle text-success rounded-pill shadow-sm"><i class="bi bi-check-circle"></i> Aman</span>
                            @endif
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    {{-- TABEL RIWAYAT MUTASI --}}
    <div class="shadow-sm card border-0 border-top border-4 border-primary rounded-3">
        <div class="card-header bg-white border-0 pt-4 pb-2 px-4 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold text-dark mb-0"><i class="bi bi-list-columns-reverse text-primary me-2"></i>Daftar Mutasi Stok</h6>
        </div>
        <div class="p-0 card-body">
            <div class="table-responsive">
                <table class="table mb-0 align-middle table-hover">
                    <thead class="bg-light fw-bold text-dark border-bottom">
                        <tr>
                            <th class="py-3 ps-4" width="12%">Tanggal</th>
                            <th class="py-3" width="10%">Tipe</th>
                            <th class="py-3" width="15%">Ref Dokumen</th>
                            <th class="py-3 text-end" width="10%">Saldo Awal</th>
                            <th class="py-3 text-center" width="10%">IN / OUT</th>
                            <th class="py-3 text-end" width="10%">Saldo Akhir</th>
                            <th class="py-3" width="18%">Keterangan</th>
                            <th class="py-3 pe-4 text-end" width="15%">Diproses Oleh</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($mutations as $mut)
                            <tr>
                                <td class="py-3 ps-4 text-muted small">
                                    <div class="fw-bold text-dark">{{ \Carbon\Carbon::parse($mut->created_at)->format('d M Y') }}</div>
                                    <div style="font-size: 0.7rem;">{{ \Carbon\Carbon::parse($mut->created_at)->format('H:i:s') }} WIB</div>
                                </td>

                                <td class="py-3">
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

                                <td class="py-3 fw-bold small text-primary">
                                    @if(str_starts_with($mut->reference_number ?? '', 'GI'))
                                        <a href="{{ route('goods-issues.show', \App\Models\GoodsIssue::where('gi_number', $mut->reference_number)->first()->id ?? 0) }}" class="text-decoration-none border-bottom border-primary pb-1" title="Lihat Surat Jalan">
                                            <i class="bi bi-box-arrow-up-right me-1"></i> {{ $mut->reference_number }}
                                        </a>
                                    @else
                                        {{ $mut->reference_number ?? '-' }}
                                    @endif
                                </td>

                                <td class="py-3 text-end text-muted">
                                    {{ number_format($mut->balance_before, 0, ',', '.') }}
                                </td>

                                <td class="py-3 text-center fw-bold fs-6 {{ $mut->type == 'IN' ? 'text-success' : 'text-danger' }}">
                                    {{ $mut->type == 'IN' ? '+' : '-' }} {{ number_format($mut->qty, 0, ',', '.') }}
                                </td>

                                <td class="py-3 text-end fw-bold text-dark">
                                    {{ number_format($mut->balance_after, 0, ',', '.') }}
                                </td>

                                <td class="py-3 small text-muted">
                                    {{ $mut->notes ?? '-' }}
                                </td>

                                <td class="py-3 pe-4 text-end">
                                    <div class="d-flex align-items-center justify-content-end gap-2">
                                        <span class="fw-bold text-dark small">{{ optional($mut->creator)->name ?? 'Sistem' }}</span>
                                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; font-size: 0.7rem;">
                                            {{ substr(optional($mut->creator)->name ?? 'S', 0, 2) }}
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-5 text-center text-muted">
                                    <i class="mb-3 opacity-50 bi bi-clock-history text-secondary" style="font-size: 3rem;"></i>
                                    <p class="mb-0 small">Belum ada riwayat mutasi stok untuk barang ini.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- PAGINASI --}}
        @if($mutations->hasPages())
        <div class="bg-white card-footer border-0 pt-3 pb-2 rounded-bottom-4">
            {{ $mutations->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
