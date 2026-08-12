@extends('layouts.app')

@section('content')
<div class="px-0 container-fluid">

    {{-- HEADER HALAMAN --}}
    <div class="gap-3 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h4 class="mb-0 fw-bold text-dark">
                <i class="bi bi-file-earmark-text-fill me-2 text-primary"></i> Purchase Requests (PR)
            </h4>
            <div class="mt-1 text-muted small">Kelola daftar permintaan barang dari semua departemen.</div>
        </div>

        <a href="{{ route('pr.create') }}" class="px-4 shadow-sm btn btn-primary rounded-pill fw-bold text-nowrap">
            <i class="bi bi-plus-lg me-1"></i> Buat PR Baru
        </a>
    </div>

    {{-- FILTER & PENCARIAN (NATIVE LARAVEL) --}}
    <form action="{{ route('pr.index') }}" method="GET" class="mb-4">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="mb-1 small text-muted fw-bold">Filter Status</label>
                <select name="status" class="shadow-sm form-select rounded-3">
                    <option value="">Semua Status</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status->name }}" {{ request('status') == $status->name ? 'selected' : '' }}>
                            {{ $status->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="mb-1 small text-muted fw-bold">Departemen / PT</label>
                <select name="department" class="shadow-sm form-select rounded-3">
                    <option value="">Semua Departemen</option>
                    <option value="Head Office" {{ request('department') == 'Head Office' ? 'selected' : '' }}>Head Office</option>
                    @foreach($companies as $comp)
                        <option value="{{ $comp->name }}" {{ request('department') == $comp->name ? 'selected' : '' }}>
                            {{ $comp->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-5">
                <label class="mb-1 small text-muted fw-bold">Pencarian Cepat</label>
                <div class="overflow-hidden shadow-sm input-group rounded-3">
                    <input type="text" name="search" class="form-control" placeholder="Cari No PR atau Nama Requester..." value="{{ request('search') }}">
                    <button class="px-4 btn btn-primary fw-bold" type="submit">
                        <i class="bi bi-search me-1"></i> Cari
                    </button>
                </div>
            </div>

            {{-- Tombol Reset (Hanya muncul jika ada filter yang aktif) --}}
            @if(request('search') || request('status') || request('department'))
            <div class="col-md-1">
                <a href="{{ route('pr.index') }}" class="shadow-sm btn btn-outline-danger w-100 rounded-3" title="Reset Semua Filter">
                    <i class="bi bi-arrow-clockwise"></i>
                </a>
            </div>
            @endif
        </div>
    </form>

    {{-- ALERT NOTIFIKASI SUCCESS/ERROR --}}
    {{-- @if(session('success'))
        <div class="shadow-sm alert alert-success alert-dismissible fade show rounded-3">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif --}}
    @if(session('error'))
        <div class="shadow-sm alert alert-danger alert-dismissible fade show rounded-3">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- TABEL DATA PR --}}
    <div class="border-0 border-4 shadow-sm card border-top border-primary rounded-3">
        <div class="p-0 card-body">
            <div class="table-responsive">
                <table class="table mb-0 align-middle table-hover">
                    <thead class="bg-light fw-bold text-dark border-bottom">
                        <tr>
                            <th class="py-3 ps-4">No. Dokumen</th>
                            <th class="py-3">Tanggal</th>
                            <th class="py-3">Requester</th>
                            <th class="py-3">Departemen</th>
                            <th class="py-3 text-center">Status</th>
                            <th class="py-3 pe-4 text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $pr)
                            <tr>
                                <td class="py-3 ps-4">
                                    <a href="{{ route('pr.edit', $pr->pr_number) }}" class="fw-bold text-decoration-none">
                                        {{ $pr->pr_number }}
                                    </a>

                                    @if(isset($pr->purchaseOrders) && $pr->purchaseOrders->count() > 0)
                                        <div class="mt-1 small text-muted" style="font-size: 0.75rem;">
                                            <i class="bi bi-link-45deg"></i> Ref PO:
                                            @foreach($pr->purchaseOrders as $po)
                                                <a href="{{ route('po.show', $po->id) }}" class="pb-1 border-opacity-25 text-muted text-decoration-none fw-semibold border-bottom border-secondary">
                                                    {{ $po->po_number }}
                                                </a>{{ !$loop->last ? ', ' : '' }}
                                            @endforeach
                                        </div>
                                    @endif

                                    <!-- 🔥 LENCANA INDIKATOR SMART RESTOCK (WARNA BARU) 🔥 -->
                                    @php
                                        $isAutoRestock = false;
                                        if(!empty($pr->description) && str_contains($pr->description, 'Auto-Restock')) $isAutoRestock = true;
                                        if(!empty($pr->notes) && str_contains($pr->notes, 'Smart Restock')) $isAutoRestock = true;
                                    @endphp

                                    @if($isAutoRestock)
                                        <div class="mt-1">
                                            <span class="shadow-sm badge rounded-pill" style="background: linear-gradient(135deg, #6f42c1 0%, #0d6efd 100%); color: #ffffff; font-size: 0.65rem; letter-spacing: 0.5px; border: none;">
                                                <i class="bi bi-robot me-1"></i> SMART RESTOCK
                                            </span>
                                        </div>
                                    @endif
                                </td>

                                <td class="py-3 text-muted">
                                    {{ \Carbon\Carbon::parse($pr->request_date)->format('d M Y') }}
                                </td>

                                <td class="py-3">
                                    <div class="gap-2 d-flex align-items-center">
                                        @if($pr->user && $pr->user->avatar)
                                            <img src="{{ asset('storage/' . $pr->user->avatar) }}" class="border border-white shadow-sm rounded-circle" width="32" height="32" style="object-fit: cover;" alt="{{ $pr->user->name }}">
                                        @else
                                            <img src="https://ui-avatars.com/api/?name={{ urlencode(optional($pr->user)->name ?? 'User') }}&background=random&color=fff&size=32" class="border border-white shadow-sm rounded-circle" alt="user">
                                        @endif
                                        <span class="fw-bold text-dark small">{{ optional($pr->user)->name ?? 'User Tidak Diketahui' }}</span>
                                    </div>
                                </td>

                                <td class="py-3">
                                    <span class="text-muted small fw-bold">
                                        {{ $pr->company->name ?? 'Head Office' }}
                                    </span>
                                </td>

                                <td class="py-3 text-center">
                                    @if($pr->status)
                                        <span class="badge bg-{{ $pr->status->color }}-subtle text-{{ $pr->status->color }} border border-{{ $pr->status->color }}-subtle px-3 py-2 rounded-pill fw-bold shadow-sm">
                                            <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i> {{ $pr->status->name }}
                                        </span>
                                    @else
                                        <span class="px-3 py-2 border shadow-sm badge bg-secondary-subtle text-secondary border-secondary-subtle rounded-pill fw-bold">
                                            Unknown
                                        </span>
                                    @endif
                                </td>

                                <td class="py-3 pe-4 text-end">
                                    <div class="btn-group">
                                        {{-- MENJADI: --}}
                                        <a href="{{ route('pr.show', $pr->pr_number) }}" class="mx-1 border shadow-sm btn btn-sm btn-light rounded-circle" title="Lihat Detail">
                                            <i class="bi bi-eye text-primary"></i>
                                        </a>

                                        @if($pr->status && $pr->status->slug == 'pending_approval')
                                            {{-- <a href="{{ route('pr.edit', $pr->id) }}" class="mx-1 border shadow-sm btn btn-sm btn-light rounded-circle" title="Edit
                                                PR"> --}}
                                                <a href="{{ route('pr.edit', $pr->pr_number) }}" class="mx-1 border shadow-sm btn btn-sm btn-light rounded-circle" title="Edit PR">
                                                    <i class="bi bi-pencil-square text-warning"></i>
                                                </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-5 text-center text-muted">
                                    <i class="mb-3 opacity-50 bi bi-inbox text-secondary" style="font-size: 3rem;"></i>
                                    <p class="mb-0 small">
                                        @if(request('search') || request('status') || request('department'))
                                            Tidak ada hasil yang cocok dengan filter pencarian Anda.
                                        @else
                                            Belum ada data Purchase Request saat ini.
                                        @endif
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- PAGINASI BAWAAN LARAVEL --}}
        @if($requests->hasPages())
        <div class="pt-3 pb-2 bg-white border-0 card-footer rounded-bottom-4">
            {{ $requests->appends(request()->query())->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
