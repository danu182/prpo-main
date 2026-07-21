@extends('layouts.app')

@section('content')
<div class="pb-5 container-fluid">
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h3 class="fw-bold text-dark"><i class="bi bi-ui-checks-grid me-2 text-warning"></i> Audit & Stock Opname</h3>
            <p class="mb-0 text-muted">Kelola dan pantau perhitungan fisik persediaan gudang.</p>
        </div>
        <a href="{{ route('stock-opnames.create') }}" class="px-4 shadow-sm btn btn-warning fw-bold text-dark rounded-pill">
            <i class="bi bi-plus-lg me-1"></i> Buka Sesi Opname
        </a>
    </div>

    @if(session('success'))
        <div class="border-0 shadow-sm alert alert-success rounded-3 fw-bold"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
    @endif

    <div class="border-0 shadow-sm card rounded-4">
        <div class="p-0 card-body">
            <div class="table-responsive">
                <table class="table mb-0 align-middle table-hover">
                    <thead class="table-light text-muted small text-uppercase">
                        <tr>
                            <th class="py-3 ps-4">Dokumen</th>
                            <th>Tanggal Mulai</th>
                            <th>Gudang</th>
                            <th>Valuasi Sistem</th>
                            <th>Total Selisih</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($opnames as $so)
                        <tr>
                            <td class="py-3 ps-4 fw-bold text-primary">{{ $so->document_number }}</td>
                            <td>{{ \Carbon\Carbon::parse($so->start_date)->format('d M Y') }}</td>
                            <td class="fw-bold">{{ optional($so->warehouse)->name }}</td>
                            <td>Rp {{ number_format($so->total_system_value, 0, ',', '.') }}</td>
                            <td class="text-danger fw-bold">Rp {{ number_format($so->total_variance_value, 0, ',', '.') }}</td>
                            <td>
                                <span class="border badge bg-secondary-subtle text-secondary border-secondary-subtle">
                                    {{ optional($so->status)->name ?? 'Draft / Menghitung' }}
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                @if(optional($so->status)->slug === 'draft' || empty($so->status_id))
                                    <a href="{{ route('stock-opnames.edit', $so->id) }}" class="px-3 btn btn-sm btn-primary fw-bold rounded-pill">Input Fisik</a>
                                @else
                                    <a href="{{ route('stock-opnames.show', $so->id) }}" class="px-3 btn btn-sm btn-outline-secondary rounded-pill">Lihat Detail</a>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="py-5 text-center text-muted">Belum ada riwayat Stock Opname.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
