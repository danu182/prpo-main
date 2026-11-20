@extends('layouts.app')

@section('content')
<div class="pb-5 container-fluid px-md-4">
    @if(session('success'))
        <div class="border-0 border-4 shadow-sm alert alert-success alert-dismissible fade show rounded-4 border-start border-success">
            <i class="bi bi-check-circle-fill me-2 text-success"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h3 class="mb-0 fw-bold text-dark"><i class="bi bi-arrow-left-right text-danger me-2"></i>Daftar Transaksi Aset</h3>
            <p class="mt-1 mb-0 text-muted small">Riwayat dokumen Serah Terima (BAST), Pengembalian (BAPA), dan Update Status Aset.</p>
        </div>

        <div class="mt-3 mt-md-0">
            <a href="{{ route('fixed-assets.transactions.create') }}" class="px-4 shadow-sm btn btn-primary fw-bold rounded-pill">
                <i class="bi bi-plus-circle me-1"></i> Buat Transaksi Baru
            </a>
        </div>
    </div>

    {{-- Filter & Pencarian --}}
    <div class="p-3 mb-4 bg-white border shadow-sm rounded-4">
        <form action="{{ route('fixed-assets.transactions') }}" method="GET" class="row g-2 align-items-center">
            <div class="col-md-3">
                <select name="type" class="form-select" onchange="this.form.submit()">
                    <option value="">-- Semua Jenis Transaksi --</option>
                    <option value="HANDOVER" {{ request('type') === 'HANDOVER' ? 'selected' : '' }}>Serah Terima (BAST)</option>
                    <option value="RETURN" {{ request('type') === 'RETURN' ? 'selected' : '' }}>Pengembalian (BAPA)</option>
                    <option value="STATUS_CHANGE" {{ request('type') === 'STATUS_CHANGE' ? 'selected' : '' }}>Update Status</option>
                </select>
            </div>
            <div class="col-md-6">
                <input type="text" name="search" class="form-control" placeholder="Cari No. Transaksi (BAST/BAPA), Kode Aset, Nama Aset, atau Karyawan..." value="{{ request('search') }}">
            </div>
            <div class="gap-2 col-md-3 d-flex">
                <button type="submit" class="btn btn-dark fw-bold w-100"><i class="bi bi-search me-1"></i> Cari</button>
                @if(request('search') || request('type'))
                    <a href="{{ route('fixed-assets.transactions') }}" class="border btn btn-light text-danger fw-bold">Reset</a>
                @endif
            </div>
        </form>
    </div>

    {{-- Tabel Daftar Dokumen Transaksi --}}
    <div class="overflow-hidden bg-white border shadow-sm rounded-4">
        <div class="table-responsive">
            <table class="table mb-0 align-middle table-hover">
                <thead class="bg-light">
                    <tr class="text-uppercase small fw-bold text-secondary">
                        <th class="ps-4">No. Transaksi</th>
                        <th>Tanggal</th>
                        <th>Jenis</th>
                        <th>Jumlah Unit</th>
                        <th>Pihak Terkait</th>
                        <th>Admin</th>
                        <th class="text-end pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $trx)
                    <tr>
                        <td class="ps-4">
                            <a href="{{ route('fixed-assets.transactions.show', $trx->id) }}" class="fw-bold text-primary text-decoration-none font-monospace">
                                {{ $trx->transaction_number }}
                            </a>
                        </td>
                        <td class="small text-muted">{{ \Carbon\Carbon::parse($trx->transaction_date)->format('d M Y') }}</td>
                        <td>
                            @if($trx->transaction_type === 'HANDOVER')
                                <span class="border badge bg-primary-subtle text-primary border-primary-subtle">Serah Terima (BAST)</span>
                            @elseif($trx->transaction_type === 'RETURN')
                                <span class="border badge bg-warning-subtle text-warning-emphasis border-warning-subtle">Pengembalian (BAPA)</span>
                            @else
                                <span class="border badge bg-secondary-subtle text-secondary border-secondary-subtle">Update Status</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-dark rounded-pill">{{ $trx->details->count() }} Unit</span>
                            @if($trx->details->first() && $trx->details->first()->asset)
                                <div class="mt-1 small text-muted text-truncate" style="max-width: 220px;">
                                    {{ $trx->details->first()->asset->name }}
                                </div>
                            @endif
                        </td>
                        <td class="small">
                            @if($trx->transaction_type === 'HANDOVER')
                                <div><span class="text-muted">Penerima:</span> <strong class="text-primary">{{ optional($trx->toUser)->name ?? '-' }}</strong></div>
                            @elseif($trx->transaction_type === 'RETURN')
                                <div><span class="text-muted">Dari:</span> <strong class="text-danger">{{ optional($trx->fromUser)->name ?? '-' }}</strong></div>
                                <div><span class="text-muted">Gudang:</span> <strong>{{ optional($trx->warehouse)->name ?? '-' }}</strong></div>
                            @else
                                <span class="text-muted">Status:</span> <strong>{{ optional($trx->status)->name ?? '-' }}</strong>
                            @endif
                        </td>
                        <td class="small text-muted">{{ optional($trx->creator)->name ?? 'System' }}</td>
                        <td class="text-end pe-4">
                            <a href="{{ route('fixed-assets.transactions.show', $trx->id) }}" class="px-3 btn btn-sm btn-outline-primary fw-bold rounded-pill">
                                <i class="bi bi-eye me-1"></i> Detail
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-5 text-center text-muted">
                            <i class="mb-2 opacity-25 bi bi-folder-x display-5 d-block"></i>
                            Belum ada dokumen transaksi aset. Klik <strong>Buat Transaksi Baru</strong> untuk memulai.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($transactions->hasPages())
            <div class="px-4 py-3 bg-white border-top">
                {{ $transactions->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
</div>
@endsection