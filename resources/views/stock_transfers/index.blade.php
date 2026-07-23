@extends('layouts.app')

@section('content')
<div class="pb-5 container-fluid text-dark">

    {{-- HEADER --}}
    <div class="gap-3 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-truck text-primary me-2"></i> Mutasi Antar Gudang</h4>
            <div class="mt-1 text-muted small">Daftar riwayat pemindahan fisik stok antar gudang.</div>
        </div>
        <div>
            <a href="{{ route('stock-transfers.create') }}" class="px-4 shadow-sm btn btn-primary rounded-pill fw-bold">
                <i class="bi bi-plus-lg me-1"></i> Buat Mutasi Baru
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="border-0 border-4 shadow-sm alert alert-success rounded-3 fw-bold border-start border-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- KOTAK TABEL --}}
    <div class="border-0 border-4 shadow-sm card rounded-4 border-top border-primary">

        {{-- PENCARIAN --}}
        <div class="p-4 bg-white card-header border-bottom d-flex justify-content-between align-items-center">
            <form action="{{ route('stock-transfers.index') }}" method="GET" class="d-flex w-100" style="max-width: 400px;">
                <div class="shadow-sm input-group">
                    <span class="bg-white border-end-0 input-group-text text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="border-start-0 form-control" placeholder="Cari No Transfer..." value="{{ request('search') }}">
                    @if(request('search'))
                        <a href="{{ route('stock-transfers.index') }}" class="btn btn-outline-secondary border-start-0"><i class="bi bi-x-lg"></i></a>
                    @endif
                    <button type="submit" class="px-3 btn btn-primary fw-bold">Cari</button>
                </div>
            </form>
        </div>

        {{-- TABEL --}}
        <div class="p-0 card-body table-responsive">
            <table class="table mb-0 align-middle table-hover">
                <thead class="bg-light text-muted small text-uppercase">
                    <tr>
                        <th class="py-3 ps-4">No. Transfer & Tanggal</th>
                        <th class="py-3">Rute Mutasi (Asal ➔ Tujuan)</th>
                        <th class="py-3 text-center">Item Dipindah</th>
                        <th class="py-3">Keterangan / Catatan</th>
                        <th class="py-3 text-center pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transfers as $tf)
                    <tr class="border-bottom">
                        <td class="py-3 ps-4">
                            <div class="fw-bold text-dark fs-6">{{ $tf->transfer_number }}</div>
                            <div class="text-muted small"><i class="bi bi-calendar-event me-1"></i>{{ \Carbon\Carbon::parse($tf->transfer_date)->format('d M Y') }}</div>
                        </td>
                        <td class="py-3">
                            <div class="gap-2 d-flex align-items-center">
                                <span class="px-2 py-1 border badge bg-danger-subtle text-danger border-danger-subtle rounded-pill">
                                    <i class="bi bi-box-arrow-up me-1"></i>{{ optional($tf->fromWarehouse)->name ?? 'Gudang Asal' }}
                                </span>
                                <i class="bi bi-arrow-right text-muted fw-bold"></i>
                                <span class="px-2 py-1 border badge bg-success-subtle text-success border-success-subtle rounded-pill">
                                    <i class="bi bi-box-arrow-in-down me-1"></i>{{ optional($tf->toWarehouse)->name ?? 'Gudang Tujuan' }}
                                </span>
                            </div>
                        </td>
                        <td class="py-3 text-center">
                            <span class="px-3 py-2 shadow-sm badge bg-primary rounded-pill">{{ $tf->items->count() }} Jenis</span>
                        </td>
                        <td class="py-3">
                            <span class="text-muted small d-inline-block text-truncate" style="max-width: 250px;" title="{{ $tf->notes }}">
                                {{ $tf->notes ?? '-' }}
                            </span>
                        </td>
                        <td class="py-3 text-center pe-4">
                            <a href="{{ route('stock-transfers.show', $tf->id) }}" class="px-3 shadow-sm btn btn-sm btn-outline-primary rounded-pill fw-bold">
                                Rincian
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="py-5 text-center text-muted">
                            <i class="mb-2 opacity-50 bi bi-inbox fs-1 d-block"></i>
                            <h6 class="fw-bold text-dark">Belum Ada Data Transfer</h6>
                            <p class="mb-0 small">Riwayat mutasi antar gudang akan muncul di sini.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($transfers->hasPages())
        <div class="p-3 bg-white card-footer border-top rounded-bottom-4">
            {{ $transfers->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
