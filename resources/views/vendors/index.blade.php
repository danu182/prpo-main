@extends('layouts.app')

@push('css')
<style>
    .skeleton { background: #e2e5e7; background-image: linear-gradient(90deg, rgba(255,255,255,0), rgba(255,255,255,0.6), rgba(255,255,255,0)); background-size: 200px 100%; background-repeat: no-repeat; border-radius: 4px; display: inline-block; animation: shimmer 1.5s infinite linear; }
    @keyframes shimmer { 0% { background-position: -200px 0; } 100% { background-position: calc(200px + 100%) 0; } }
    .sk-text { height: 16px; width: 100%; margin-bottom: 6px; }
    .sk-text-short { height: 12px; width: 60%; }
    .sk-badge { height: 24px; width: 50px; border-radius: 12px; }
    .sk-btn { height: 32px; width: 32px; border-radius: 50%; }
</style>
@endpush

@section('content')
<div class="container pb-5 text-dark">

    <div class="mb-4 row align-items-center">
        <div class="mb-3 col-lg-5 mb-lg-0">
            <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-buildings me-2 text-primary"></i> Master Vendor (Supplier)</h4>
            <div class="mt-1 text-muted small">Kelola data mitra perusahaan, kontak, dan informasi pembayaran.</div>
        </div>
        <div class="col-lg-7">
            <div class="flex-wrap gap-2 d-flex justify-content-lg-end">
                <form action="{{ route('vendors.index') }}" method="GET" class="d-flex flex-grow-1 flex-md-grow-0">
                    <div class="overflow-hidden border shadow-sm input-group rounded-pill">
                        <input type="text" name="search" class="px-4 bg-white border-0 form-control" placeholder="Cari Nama atau Kode Vendor..." value="{{ request('search') }}">
                        <button class="px-4 text-white border-0 btn btn-primary fw-bold" type="submit"><i class="bi bi-search"></i></button>
                        @if(request('search'))
                            <a href="{{ route('vendors.index') }}" class="px-3 btn btn-light border-start text-danger" title="Reset"><i class="bi bi-x-lg"></i></a>
                        @endif
                    </div>
                </form>
                <a href="{{ route('vendors.create') }}" class="px-4 shadow-sm btn btn-dark rounded-pill fw-bold"><i class="bi bi-plus-lg me-1"></i> Tambah Vendor</a>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="border-0 border-4 shadow-sm alert alert-success rounded-3 fw-bold border-start border-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="border-0 border-4 shadow-sm card border-top border-primary rounded-3">
        <div class="p-0 card-body table-responsive">
            <table class="table mb-0 align-middle table-hover">
                <thead class="bg-light fw-bold text-muted border-bottom">
                    <tr>
                        <th class="py-3 ps-4" width="12%">Kode</th>
                        <th class="py-3" width="28%">Informasi Perusahaan</th>
                        <th class="py-3" width="25%">Contact Person (PIC)</th>
                        <th class="py-3" width="20%">Info Pembayaran</th>
                        <th class="py-3 pe-4 text-end" width="15%">Aksi</th>
                    </tr>
                </thead>

                {{-- SKELETON LOADING --}}
                <tbody id="skeleton-table">
                    @for($i = 0; $i < 5; $i++)
                    <tr>
                        <td class="py-3 ps-4"><div class="skeleton sk-text" style="width: 70px;"></div></td>
                        <td class="py-3"><div class="skeleton sk-text" style="width: 80%;"></div><div class="skeleton sk-text-short"></div></td>
                        <td class="py-3"><div class="skeleton sk-text" style="width: 70%;"></div><div class="skeleton sk-text-short" style="width: 50%;"></div></td>
                        <td class="py-3"><div class="skeleton sk-text" style="width: 90%;"></div><div class="skeleton sk-badge"></div></td>
                        <td class="py-3 pe-4 text-end"><div class="skeleton sk-btn me-1"></div><div class="skeleton sk-btn"></div></td>
                    </tr>
                    @endfor
                </tbody>

                {{-- ACTUAL DATA --}}
                <tbody id="actual-table" class="d-none">
                    @forelse($vendors as $vendor)
                    <tr>
                        <td class="py-3 ps-4">
                            @if($vendor->code)
                                <span class="fw-bold text-primary">{{ $vendor->code }}</span>
                            @else
                                <span class="px-2 py-1 border badge bg-secondary-subtle text-secondary" style="font-size: 0.7rem;">
                                    <i class="bi bi-dash"></i> Kosong
                                </span>
                            @endif
                        </td>
                        <td class="py-3">
                            <div class="fw-bold {{ $vendor->is_active ? 'text-dark' : 'text-muted text-decoration-line-through' }}">
                                {{ $vendor->name }}
                                @if(!$vendor->is_active) <span class="border badge bg-danger-subtle text-danger border-danger-subtle ms-1" style="font-size: 0.65rem;">Nonaktif</span> @endif
                            </div>
                            <div class="mt-1 small text-muted"><i class="bi bi-envelope me-1"></i> {{ $vendor->email ?? '-' }}</div>
                            <div class="small text-muted"><i class="bi bi-telephone me-1"></i> {{ $vendor->phone ?? '-' }}</div>
                        </td>
                        <td class="py-3">
                            <div class="fw-bold text-secondary"><i class="bi bi-person-badge me-1"></i> {{ $vendor->pic_name ?? 'Tidak Ada Data' }}</div>
                            <div class="small text-muted"><i class="bi bi-whatsapp me-1 text-success"></i> {{ $vendor->pic_phone ?? '-' }}</div>
                        </td>
                        <td class="py-3">
                            <div class="small fw-bold text-dark"><i class="bi bi-bank me-1 text-primary"></i> {{ $vendor->bank_name ?? '-' }}</div>
                            <div class="mt-1 border badge bg-info-subtle text-info-emphasis border-info-subtle">
                                <i class="bi bi-calendar3"></i> TOP: {{ $vendor->payment_terms_days }} Hari
                            </div>
                        </td>
                        <td class="py-3 pe-4 text-end text-nowrap">
                            <a href="{{ route('vendors.edit', $vendor->id) }}" class="px-3 shadow-sm btn btn-sm btn-outline-warning rounded-pill fw-bold me-1" title="Edit Vendor">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                            <form action="{{ route('vendors.toggle_status', $vendor->id) }}" method="POST" class="d-inline form-toggle">
                                @csrf @method('PATCH')
                                <button type="button" class="px-3 shadow-sm btn btn-sm btn-outline-{{ $vendor->is_active ? 'danger' : 'success' }} rounded-pill fw-bold btn-toggle" data-action="{{ $vendor->is_active ? 'nonaktifkan' : 'aktifkan kembali' }}" data-name="{{ $vendor->name }}">
                                    <i class="bi bi-{{ $vendor->is_active ? 'power' : 'check-circle' }}"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="py-5 text-center text-muted">
                            <i class="mb-3 opacity-50 bi bi-buildings text-secondary display-6 d-block"></i>
                            <p class="mb-0 small">Buku Alamat Vendor masih kosong.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($vendors->hasPages())
        <div class="pt-3 pb-2 bg-white border-0 card-footer rounded-bottom-4">{{ $vendors->links() }}</div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        setTimeout(() => {
            document.getElementById('skeleton-table').classList.add('d-none');
            document.getElementById('actual-table').classList.remove('d-none');
        }, 600);

        document.querySelectorAll('.btn-toggle').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const form = this.closest('.form-toggle');
                const actionText = this.getAttribute('data-action');
                const itemName = this.getAttribute('data-name');
                const isDeactivating = actionText === 'nonaktifkan';

                Swal.fire({
                    title: 'Konfirmasi',
                    html: `Yakin ingin <strong>${actionText}</strong> vendor <br><span class="text-primary fw-bold">"${itemName}"</span>?`,
                    icon: isDeactivating ? 'warning' : 'question',
                    showCancelButton: true,
                    confirmButtonColor: isDeactivating ? '#dc3545' : '#198754',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Ya, Laksanakan!',
                    cancelButtonText: 'Batal',
                    reverseButtons: true,
                    customClass: { popup: 'rounded-4 shadow-lg border-0' }
                }).then((result) => { if (result.isConfirmed) form.submit(); });
            });
        });
    });
</script>
@endpush
