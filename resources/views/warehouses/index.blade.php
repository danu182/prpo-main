@extends('layouts.app')

@push('css')
<style>
    .skeleton { background: #e2e5e7; background-image: linear-gradient(90deg, rgba(255,255,255,0), rgba(255,255,255,0.6), rgba(255,255,255,0)); background-size: 200px 100%; background-repeat: no-repeat; border-radius: 4px; display: inline-block; animation: shimmer 1.5s infinite linear; }
    @keyframes shimmer { 0% { background-position: -200px 0; } 100% { background-position: calc(200px + 100%) 0; } }
    .sk-text { height: 16px; width: 100%; margin-bottom: 6px; }
    .sk-btn { height: 32px; width: 32px; border-radius: 50%; }
</style>
@endpush

@section('content')
<div class="container-fluid pb-5 text-dark">

    <div class="mb-4 row align-items-center">
        <div class="mb-3 col-lg-5 mb-lg-0">
            <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-box-seam text-primary me-2"></i> Master Gudang (Warehouse)</h4>
            <div class="mt-1 text-muted small">Kelola data lokasi penyimpanan fisik barang.</div>
        </div>
        <div class="col-lg-7">
            <div class="flex-wrap gap-2 d-flex justify-content-lg-end">
                <form action="{{ route('warehouses.index') }}" method="GET" class="d-flex flex-grow-1 flex-md-grow-0">
                    <div class="overflow-hidden border shadow-sm input-group rounded-pill">
                        <input type="text" name="search" class="px-4 bg-white border-0 form-control" placeholder="Cari Nama / Kode Gudang..." value="{{ request('search') }}">
                        <button class="px-4 text-white border-0 btn btn-primary fw-bold" type="submit"><i class="bi bi-search"></i></button>
                        @if(request('search'))
                            <a href="{{ route('warehouses.index') }}" class="px-3 btn btn-light border-start text-danger" title="Reset Pencarian"><i class="bi bi-x-lg"></i></a>
                        @endif
                    </div>
                </form>
                <a href="{{ route('warehouses.create') }}" class="px-4 shadow-sm btn btn-dark rounded-pill fw-bold"><i class="bi bi-plus-lg me-1"></i> Tambah Gudang</a>
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
                        <th class="py-3 ps-4" width="15%">Kode</th>
                        <th class="py-3" width="25%">Nama Gudang</th>
                        <th class="py-3" width="30%">Deskripsi Lokasi</th>
                        <th class="py-3 text-center" width="15%">Status</th>
                        <th class="py-3 pe-4 text-end" width="15%">Aksi</th>
                    </tr>
                </thead>

                {{-- SKELETON LOADING --}}
                <tbody id="skeleton-table">
                    @for($i = 0; $i < 4; $i++)
                    <tr>
                        <td class="py-3 ps-4"><div class="skeleton sk-text" style="width: 70%;"></div></td>
                        <td class="py-3"><div class="skeleton sk-text" style="width: 80%;"></div></td>
                        <td class="py-3"><div class="skeleton sk-text" style="width: 100%;"></div></td>
                        <td class="py-3 text-center"><div class="mx-auto skeleton sk-text" style="width: 50%;"></div></td>
                        <td class="py-3 pe-4 text-end"><div class="skeleton sk-btn"></div></td>
                    </tr>
                    @endfor
                </tbody>

                {{-- ACTUAL DATA --}}
                <tbody id="actual-table" class="d-none">
                    @forelse($warehouses as $warehouse)
                    <tr>
                        <td class="py-3 ps-4 fw-bold text-primary font-monospace">{{ $warehouse->code }}</td>
                        <td class="py-3 fw-semibold text-dark">{{ $warehouse->name }}</td>
                        <td class="py-3 text-secondary small">{{ Str::limit($warehouse->description ?? 'Tidak ada deskripsi', 50) }}</td>
                        <td class="py-3 text-center">
                            @if($warehouse->is_active)
                                <span class="px-3 py-2 border badge bg-success-subtle text-success border-success-subtle rounded-pill"><i class="bi bi-check-circle-fill me-1"></i> Aktif</span>
                            @else
                                <span class="px-3 py-2 border badge bg-danger-subtle text-danger border-danger-subtle rounded-pill"><i class="bi bi-x-circle-fill me-1"></i> Nonaktif</span>
                            @endif
                        </td>
                        <td class="py-3 pe-4 text-end text-nowrap">
                            <a href="{{ route('warehouses.show', $warehouse->id) }}" class="px-2 shadow-sm btn btn-sm btn-outline-info rounded-pill fw-bold" title="Detail">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="{{ route('warehouses.edit', $warehouse->id) }}" class="px-2 mx-1 shadow-sm btn btn-sm btn-outline-warning rounded-pill fw-bold" title="Edit">
                                <i class="bi bi-pencil-square"></i>
                            </a>

                            {{-- Form Toggle Status --}}
                            <form action="{{ route('warehouses.toggle_status', $warehouse->id) }}" method="POST" class="d-inline form-toggle">
                                @csrf
                                @method('PATCH')
                                <button type="button" class="px-2 shadow-sm btn btn-sm btn-outline-{{ $warehouse->is_active ? 'danger' : 'success' }} rounded-pill fw-bold btn-toggle" title="{{ $warehouse->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
                                    <i class="bi bi-power"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="py-5 text-center text-muted">
                            <i class="mb-3 opacity-50 bi bi-box-seam text-secondary display-6 d-block"></i>
                            <p class="mb-0 small">Belum ada data Gudang.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($warehouses->hasPages())
        <div class="pt-3 pb-2 bg-white border-0 card-footer rounded-bottom-4">{{ $warehouses->links() }}</div>
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
        }, 500);

        // SweetAlert untuk Toggle Status
        const toggleButtons = document.querySelectorAll('.btn-toggle');
        toggleButtons.forEach(button => {
            button.addEventListener('click', function() {
                const form = this.closest('.form-toggle');
                Swal.fire({
                    title: 'Ubah Status Gudang?',
                    text: "Apakah Anda yakin ingin mengubah status aktif/nonaktif gudang ini?",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#0d6efd',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Ya, Ubah!',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                })
            });
        });
    });
</script>
@endpush
