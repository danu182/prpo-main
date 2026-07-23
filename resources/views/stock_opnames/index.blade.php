@extends('layouts.app')

@section('content')
<div class="container-fluid pb-5">
    {{-- HEADER & TOMBOL AKSI UTAMA --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold text-dark mb-1"><i class="bi bi-ui-checks-grid me-2 text-warning"></i> Audit & Stock Opname</h3>
            <p class="text-muted mb-0">Kelola dan pantau perhitungan fisik persediaan gudang.</p>
        </div>

        <div class="d-flex flex-column flex-md-row gap-2 align-items-md-center">
            {{-- SEARCH BAR --}}
            <form action="{{ route('stock-opnames.index') }}" method="GET" class="m-0">
                <div class="input-group shadow-sm rounded-pill overflow-hidden">
                    <input type="text" name="search" class="form-control border-0 bg-white ps-4" placeholder="Cari No. Dokumen..." value="{{ request('search') }}">
                    <button class="btn btn-white bg-white border-0 pe-4 text-primary" type="submit">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </form>

            <a href="{{ route('stock-opnames.create') }}" class="btn btn-warning fw-bold text-dark rounded-pill px-4 shadow-sm text-nowrap">
                <i class="bi bi-plus-lg me-1"></i> Buka Sesi Opname
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm rounded-4 fw-bold p-3 mb-4 d-flex align-items-center">
            <i class="bi bi-check-circle-fill fs-4 me-3"></i>
            <div>{{ session('success') }}</div>
        </div>
    @endif

    {{-- TABEL DATA --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted small text-uppercase fw-bold border-bottom">
                        <tr>
                            <th class="ps-4 py-3">Dokumen Opname</th>
                            <th>Tanggal Mulai</th>
                            <th>Lokasi Gudang</th>
                            <th class="text-end">Valuasi Sistem</th>
                            <th class="text-end">Total Selisih</th>
                            <th class="text-center">Status</th>
                            <th class="text-end pe-4">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($opnames as $so)
                           @php
                                $hasApprovals = isset($so->approvals) && $so->approvals->count() > 0;
                                $statusSlug = optional($so->status)->slug ?? 'draft';
                                $statusName = optional($so->status)->name ?? 'Draft / Menghitung';

                                // 🔥 LOGIKA SINKRONISASI OTOMATIS (Sama seperti halaman Detail) 🔥
                                if ($hasApprovals) {
                                    $pendingApproval = $so->approvals->reject(function($app) {
                                        $st = strtoupper($app->status ?? '');
                                        return $st === 'APPROVED' || $st === 'REJECTED';
                                    })->first();

                                    $isRejected = $so->approvals->contains(function($app) {
                                        return strtoupper($app->status ?? '') === 'REJECTED';
                                    });

                                    if ($isRejected) {
                                        $statusSlug = 'rejected';
                                        $statusName = 'Ditolak';
                                    } elseif ($pendingApproval) {
                                        $statusSlug = 'pending_approval';
                                        $statusName = 'Menunggu Persetujuan';
                                    } else {
                                        $statusSlug = 'approved';
                                        $statusName = 'Disetujui / Selesai';
                                    }
                                }

                                $badgeClass = match($statusSlug) {
                                    'draft' => 'bg-secondary-subtle text-secondary border-secondary-subtle',
                                    'pending_approval', 'pending' => 'bg-warning-subtle text-warning border-warning-subtle',
                                    'approved' => 'bg-success-subtle text-success border-success-subtle',
                                    'rejected' => 'bg-danger-subtle text-danger border-danger-subtle',
                                    default => 'bg-light text-dark border'
                                };
                            @endphp
                        <tr>
                            <td class="ps-4 py-3">
                                <a href="{{ route('stock-opnames.show', $so->id) }}" class="fw-bold text-primary text-decoration-none">
                                    {{ $so->document_number }}
                                </a>
                                <div class="small text-muted mt-1"><i class="bi bi-person me-1"></i>{{ optional($so->creator)->name ?? 'Sistem' }}</div>
                            </td>
                            <td class="fw-semibold text-dark">{{ \Carbon\Carbon::parse($so->start_date)->format('d M Y') }}</td>
                            <td>
                                <span class="fw-bold text-dark"><i class="bi bi-shop me-1 text-muted"></i>{{ optional($so->warehouse)->name }}</span>
                            </td>
                            <td class="text-end fw-semibold text-secondary">Rp {{ number_format($so->total_system_value, 0, ',', '.') }}</td>
                            <td class="text-end fw-bold {{ $so->total_variance_value > 0 ? 'text-danger' : 'text-success' }}">
                                Rp {{ number_format($so->total_variance_value, 0, ',', '.') }}
                            </td>
                            <td class="text-center">
                                <span class="badge border px-3 py-2 rounded-pill {{ $badgeClass }}">
                                    {{ strtoupper($statusName) }}
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                @if($statusSlug === 'draft' || empty($so->status_id))
                                    <a href="{{ route('stock-opnames.edit', $so->id) }}" class="btn btn-sm btn-warning fw-bold text-dark px-3 rounded-pill shadow-sm">
                                        <i class="bi bi-pencil-square me-1"></i> Input Fisik
                                    </a>
                                @else
                                    <a href="{{ route('stock-opnames.show', $so->id) }}" class="btn btn-sm btn-outline-primary fw-bold px-3 rounded-pill">
                                        <i class="bi bi-eye me-1"></i> Lihat Detail
                                    </a>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-ui-checks-grid fs-1 d-block mb-3 text-secondary opacity-50"></i>
                                @if(request('search'))
                                    Tidak ada dokumen yang cocok dengan pencarian "<strong>{{ request('search') }}</strong>".
                                @else
                                    Belum ada riwayat Stock Opname. Mulai buat sesi baru!
                                @endif
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- PAGINATION Bawah Tabel --}}
        @if($opnames->hasPages())
        <div class="card-footer bg-white border-top py-3 pe-4">
            {{ $opnames->appends(request()->query())->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
