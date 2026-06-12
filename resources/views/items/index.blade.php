@extends('layouts.app')

@push('css')
<style>
    /* ======================================================== */
    /* 🔥 EFEK SKELETON LOADING (SHIMMER) ALA TOKOPEDIA 🔥      */
    /* ======================================================== */
    .skeleton {
        background: #e2e5e7;
        background-image: linear-gradient(90deg, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.6), rgba(255, 255, 255, 0));
        background-size: 200px 100%;
        background-repeat: no-repeat;
        border-radius: 4px;
        display: inline-block;
        animation: shimmer 1.5s infinite linear;
    }

    @keyframes shimmer {
        0% { background-position: -200px 0; }
        100% { background-position: calc(200px + 100%) 0; }
    }

    .sk-text { height: 16px; width: 100%; margin-bottom: 6px; }
    .sk-text-short { height: 12px; width: 60%; }
    .sk-badge { height: 24px; width: 50px; border-radius: 12px; }
    .sk-btn { height: 32px; width: 32px; border-radius: 50%; }
</style>
@endpush

@section('content')
<div class="container pb-5 text-dark">

    {{-- HEADER & PENCARIAN --}}
    <div class="mb-4 row align-items-center">
        <div class="mb-3 col-lg-5 mb-lg-0">
            <h4 class="mb-0 fw-bold text-dark">
                <i class="bi bi-boxes me-2 text-warning"></i> Katalog Master Item
            </h4>
            <div class="mt-1 text-muted small">Kelola seluruh data barang persediaan, aset, dan jasa operasional.</div>
        </div>

        <div class="col-lg-7">
            <div class="flex-wrap gap-2 d-flex justify-content-lg-end">
                <form action="{{ route('items.index') }}" method="GET" class="d-flex flex-grow-1 flex-md-grow-0">
                    <div class="overflow-hidden border shadow-sm input-group rounded-pill">
                        <input type="text" name="search" class="px-4 bg-white border-0 form-control" placeholder="Cari Kode atau Nama Barang..." value="{{ request('search') }}">
                        <button class="px-4 border-0 btn btn-warning text-dark fw-bold" type="submit">
                            <i class="bi bi-search"></i>
                        </button>
                        @if(request('search'))
                            <a href="{{ route('items.index') }}" class="px-3 btn btn-light border-start text-danger" title="Reset">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        @endif
                    </div>
                </form>

                {{-- SEKARANG MENGARAH KE HALAMAN RIWAYAT IMPORT --}}
                <a href="{{ route('items.import_index') }}" class="px-4 shadow-sm btn btn-success rounded-pill fw-bold">
                    <i class="bi bi-clock-history me-1"></i> Riwayat Import
                </a>
                <a href="{{ route('items.create') }}" class="px-4 shadow-sm btn btn-dark rounded-pill fw-bold">
                    <i class="bi bi-plus-lg me-1"></i> Tambah Item
                </a>
            </div>
        </div>
    </div>

    {{-- ALERT NOTIFIKASI --}}
    @if(session('success'))
        <div class="border-0 border-4 shadow-sm alert alert-success rounded-3 fw-bold border-start border-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="border-0 border-4 shadow-sm alert alert-danger rounded-3 border-start border-danger alert-dismissible fade show">
            <div class="fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i> Gagal memproses data!</div>
            <ul class="mt-2 mb-0 small">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- TABEL DATA --}}
    <div class="border-0 border-4 shadow-sm card border-top border-warning rounded-3">
        <div class="p-0 card-body table-responsive">
            <table class="table mb-0 align-middle table-hover">
                <thead class="bg-light fw-bold text-muted border-bottom">
                    <tr>
                        <th class="py-3 ps-4" width="15%">Kode Item</th>
                        <th class="py-3" width="25%">Nama Barang / Spesifikasi</th>
                        <th class="py-3 text-center" width="10%">Sat. Dasar</th>
                        <th class="py-3" width="20%">Karakteristik</th>
                        <th class="py-3 text-center" width="15%">Stok Gudang</th>
                        <th class="py-3 pe-4 text-end" width="15%">Aksi</th>
                    </tr>
                </thead>

                {{-- 1. BAGIAN SKELETON (BAYANGAN LOADING) --}}
                <tbody id="skeleton-table">
                    @for($i = 0; $i < 5; $i++)
                    <tr>
                        <td class="py-3 ps-4"><div class="skeleton sk-text" style="width: 80px;"></div></td>
                        <td class="py-3">
                            <div class="skeleton sk-text" style="width: 80%;"></div>
                            <div class="skeleton sk-text-short"></div>
                        </td>
                        <td class="py-3 text-center"><div class="skeleton sk-badge"></div></td>
                        <td class="py-3"><div class="skeleton sk-badge" style="width: 70px;"></div></td>
                        <td class="py-3 text-center"><div class="skeleton sk-text" style="width: 40px; margin: 0 auto;"></div></td>
                        <td class="py-3 pe-4 text-end">
                            <div class="skeleton sk-btn me-1"></div>
                            <div class="skeleton sk-btn"></div>
                        </td>
                    </tr>
                    @endfor
                </tbody>

                {{-- 2. BAGIAN DATA ASLI (Disembunyikan saat pertama load) --}}
                <tbody id="actual-table" class="d-none">
                    @forelse($items as $item)
                    <tr>
                        <td class="py-3 ps-4 fw-bold text-dark">{{ $item->code }}</td>

                        <td class="py-3">
                            <div class="fw-bold {{ $item->is_active ? 'text-primary' : 'text-muted text-decoration-line-through' }}">
                                {{ $item->name }}
                                @if(!$item->is_active)
                                    <span class="border badge bg-danger-subtle text-danger border-danger-subtle ms-1" style="font-size: 0.65rem;">Nonaktif</span>
                                @endif
                            </div>
                            <div class="mt-1 small text-muted text-truncate" style="max-width: 250px;" title="{{ $item->specification }}">
                                {{ $item->specification ?? 'Tidak ada spesifikasi khusus' }}
                            </div>
                        </td>

                        <td class="py-3 text-center fw-bold text-secondary">{{ optional($item->uom)->code ?? '-' }}</td>

                        {{-- 🔥 KOLOM KARAKTERISTIK DINAMIS 🔥 --}}
                        <td class="py-3">
                            <div class="flex-wrap gap-1 d-flex">

                                {{-- Menampilkan Tipe Barang dari Database --}}
                                @if($item->item_type_code == 'STK')
                                    <span class="border badge bg-success-subtle text-success border-success-subtle"><i class="bi bi-box-seam me-1"></i> {{ optional($item->itemType)->name ?? 'Stok' }}</span>
                                @elseif($item->item_type_code == 'NST')
                                    <span class="border badge bg-warning-subtle text-warning-emphasis border-warning-subtle"><i class="bi bi-cart me-1"></i> {{ optional($item->itemType)->name ?? 'Non-Stok' }}</span>
                                @elseif($item->item_type_code == 'JSA')
                                    <span class="border badge bg-secondary-subtle text-secondary border-secondary-subtle"><i class="bi bi-tools me-1"></i> {{ optional($item->itemType)->name ?? 'Jasa' }}</span>
                                @else
                                    <span class="border badge bg-light text-dark">{{ optional($item->itemType)->name ?? $item->item_type_code }}</span>
                                @endif

                                {{-- Menampilkan Status Aset --}}
                                {{-- @if($item->is_asset)
                                    <span class="border badge bg-info-subtle text-info-emphasis border-info-subtle"><i class="bi bi-pc-display me-1"></i> Aset</span>
                                @endif --}}

                                {{-- Menampilkan Status Lacak Fisik --}}
                                @if($item->is_trackable)
                                    <span class="border badge bg-primary-subtle text-primary border-primary-subtle"><i class="bi bi-upc-scan me-1"></i> Dilacak</span>
                                @endif
                            </div>
                        </td>

                        {{-- 🔥 KOLOM STOK GUDANG DINAMIS 🔥 --}}
                        <td class="py-3 text-center">
                            {{-- Stok hanya muncul jika tipenya adalah STK (Stok) --}}
                            @if($item->item_type_code == 'STK')
                                <span class="fs-5 fw-bold {{ $item->current_stock > 0 ? 'text-success' : 'text-danger' }}">
                                    {{ (float) $item->current_stock }}
                                </span>
                                @if($item->min_stock || $item->max_stock)
                                    <div class="mt-1" style="font-size: 0.65rem;">
                                        <span class="text-danger">Min: {{ (float)$item->min_stock ?? 0 }}</span> |
                                        <span class="text-primary">Max: {{ $item->max_stock ? (float)$item->max_stock : '~' }}</span>
                                    </div>
                                @endif
                            @else
                                <span class="text-muted small fst-italic">N/A</span>
                            @endif
                        </td>

                        {{-- KOLOM AKSI (Tidak ada yang diubah) --}}
                        <td class="py-3 pe-4 text-end text-nowrap">
                            <a href="{{ route('items.edit', ['item' => $item->slug ?: $item->id]) }}" class="px-3 shadow-sm btn btn-sm btn-outline-warning rounded-pill fw-bold me-1" title="Edit Data">
                                <i class="bi bi-pencil-square"></i>
                            </a>

                            <form action="{{ route('items.toggle_status', ['item' => $item->slug ?: $item->id]) }}" method="POST" class="d-inline form-toggle">
                                @csrf
                                @method('PATCH')
                                @if($item->is_active)
                                    <button type="button" class="px-3 shadow-sm btn btn-sm btn-outline-danger rounded-pill fw-bold btn-toggle" data-action="nonaktifkan" data-name="{{ $item->name }}" title="Nonaktifkan Barang">
                                        <i class="bi bi-power"></i>
                                    </button>
                                @else
                                    <button type="button" class="px-3 shadow-sm btn btn-sm btn-outline-success rounded-pill fw-bold btn-toggle" data-action="aktifkan kembali" data-name="{{ $item->name }}" title="Aktifkan Kembali Barang">
                                        <i class="bi bi-check-circle"></i>
                                    </button>
                                @endif
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="py-5 text-center text-muted">
                            <i class="mb-3 opacity-50 bi bi-boxes text-secondary display-6 d-block"></i>
                            <p class="mb-0 small">Katalog Barang masih kosong.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(isset($items) && $items->hasPages())
        <div class="pt-3 pb-2 bg-white border-0 card-footer rounded-bottom-4" id="pagination-container">
            {{ $items->links() }}
        </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
{{-- 🔥 IMPORT SWEETALERT2 🔥 --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    document.addEventListener("DOMContentLoaded", function() {

        // 1. Efek Skeleton Loading (Shimmer) - Cukup ditulis 1 kali saja di sini!
        setTimeout(function() {
            document.getElementById('skeleton-table').classList.add('d-none');
            document.getElementById('actual-table').classList.remove('d-none');
        }, 600);

        // 2. 🔥 SIHIR SWEETALERT2 UNTUK TOMBOL AKTIF/NONAKTIF 🔥
        const toggleButtons = document.querySelectorAll('.btn-toggle');

        toggleButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault(); // Hentikan aksi default

                const form = this.closest('.form-toggle');
                const actionText = this.getAttribute('data-action');
                const itemName = this.getAttribute('data-name');
                const isDeactivating = actionText === 'nonaktifkan';

                Swal.fire({
                    title: 'Konfirmasi Tindakan',
                    html: `Apakah Anda yakin ingin <strong>${actionText}</strong> item <br><span class="text-primary fw-bold">"${itemName}"</span>?`,
                    icon: isDeactivating ? 'warning' : 'question',
                    showCancelButton: true,
                    confirmButtonColor: isDeactivating ? '#dc3545' : '#198754',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Ya, Laksanakan!',
                    cancelButtonText: 'Batal',
                    reverseButtons: true,
                    customClass: {
                        popup: 'rounded-4 shadow-lg border-0'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Langsung Submit tanpa memunculkan spinner tambahan
                        form.submit();
                    }
                });
            });
        });
    });
</script>
@endpush
