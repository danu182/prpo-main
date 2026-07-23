@extends('layouts.app')

@section('content')
<div class="pb-5 container-fluid">
    {{-- HEADER --}}
    <div class="gap-3 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h3 class="mb-1 fw-bold text-dark"><i class="bi bi-diagram-3-fill me-2 text-primary"></i> Master Departemen</h3>
            <p class="mb-0 text-muted">Kelola daftar departemen atau divisi perusahaan.</p>
        </div>

        <div class="gap-2 d-flex flex-column flex-md-row align-items-md-center">
            <form action="{{ route('departments.index') }}" method="GET" class="m-0">
                <div class="overflow-hidden shadow-sm input-group rounded-pill">
                    <input type="text" name="search" class="bg-white border-0 form-control ps-4" placeholder="Cari Nama / Kode..." value="{{ request('search') }}">
                    <button class="bg-white border-0 btn btn-white pe-4 text-primary" type="submit">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </form>
            <button type="button" class="px-4 shadow-sm btn btn-primary fw-bold rounded-pill text-nowrap" data-bs-toggle="modal" data-bs-target="#modalTambah">
                <i class="bi bi-plus-lg me-1"></i> Tambah Divisi
            </button>
        </div>
    </div>

    {{-- ALERT VALIDASI ERROR --}}
    @if ($errors->any())
        <div class="mb-4 border-0 shadow-sm alert alert-danger rounded-4">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- TABEL DATA --}}
    <div class="overflow-hidden border-0 shadow-sm card rounded-4">
        <div class="p-0 card-body">
            <div class="table-responsive">
                <table class="table mb-0 align-middle table-hover">
                    <thead class="table-light text-muted small text-uppercase fw-bold border-bottom">
                        <tr>
                            <th class="py-3 ps-4" width="5%">No</th>
                            <th width="15%">Kode</th>
                            <th>Nama Departemen</th>
                            <th width="15%" class="text-center">Status</th>
                            <th class="text-end pe-4" width="15%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($departments as $idx => $dept)
                            <tr>
                                <td class="ps-4 text-muted fw-bold">{{ $departments->firstItem() + $idx }}</td>
                                <td><span class="px-2 py-1 border shadow-sm badge bg-light text-dark font-monospace">{{ $dept->code }}</span></td>
                                <td class="fw-bold text-dark">{{ $dept->name }}</td>
                                <td class="text-center">
                                    @if($dept->is_active)
                                        <span class="px-3 border badge bg-success-subtle text-success border-success-subtle rounded-pill">Aktif</span>
                                    @else
                                        <span class="px-3 border badge bg-danger-subtle text-danger border-danger-subtle rounded-pill">Non-Aktif</span>
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group">
                                        <a href="{{ route('departments.show', $dept->id) }}" class="border btn btn-sm btn-light text-primary" title="show">
                                            <i class="bi bi-eye"></i>
                                        </a>

                                        <a href="{{ route('departments.edit', $dept->id) }}" class="border btn btn-sm btn-light text-primary" title="edit">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>

                                        <form action="{{ route('departments.destroy', $dept->id) }}" method="POST" id="delete-form-{{ $dept->id }}" class="m-0">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="border btn btn-sm btn-light text-danger" onclick="confirmDelete('delete-form-{{ $dept->id }}')" title="Hapus">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>

                            {{-- MODAL EDIT DEPARTEMEN --}}
                            <div class="modal fade" id="modalEdit{{ $dept->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="border-0 shadow-lg modal-content rounded-4">
                                        <div class="modal-header border-bottom">
                                            <h5 class="modal-title fw-bold text-dark">Edit Departemen</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form action="{{ route('departments.update', $dept->id) }}" method="POST">
                                            @csrf
                                            @method('PUT')
                                            <div class="p-4 modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label small fw-bold text-muted">Nama Departemen <span class="text-danger">*</span></label>
                                                    <input type="text" name="name" class="form-control" value="{{ $dept->name }}" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label small fw-bold text-muted">Kode (Maks 10 Karakter) <span class="text-danger">*</span></label>
                                                    <input type="text" name="code" class="form-control font-monospace text-uppercase" value="{{ $dept->code }}" maxlength="10" required>
                                                </div>
                                                <div class="mt-4 form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="is_active" id="activeEdit{{ $dept->id }}" value="1" {{ $dept->is_active ? 'checked' : '' }}>
                                                    <label class="form-check-label ms-2 fw-medium" for="activeEdit{{ $dept->id }}">Departemen Aktif</label>
                                                </div>
                                            </div>
                                            <div class="p-3 modal-footer bg-light border-top">
                                                <button type="button" class="px-4 btn btn-light rounded-pill" data-bs-dismiss="modal">Batal</button>
                                                <button type="submit" class="px-4 btn btn-primary rounded-pill fw-bold">Simpan Perubahan</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <tr>
                                <td colspan="5" class="py-5 text-center text-muted">
                                    <i class="mb-2 opacity-25 bi bi-folder-x fs-1 d-block"></i>
                                    Belum ada data Departemen.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($departments->hasPages())
            <div class="py-3 bg-white card-footer border-top pe-4">
                {{ $departments->appends(request()->query())->links() }}
            </div>
        @endif
    </div>
</div>

{{-- MODAL TAMBAH DEPARTEMEN --}}
<div class="modal fade" id="modalTambah" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="border-0 shadow-lg modal-content rounded-4">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold text-dark">Tambah Departemen Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('departments.store') }}" method="POST">
                @csrf
                <div class="p-4 modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Nama Departemen <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="Contoh: Human Resources" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Kode (Maks 10 Karakter) <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control font-monospace text-uppercase" placeholder="Contoh: HRD" maxlength="10" required>
                    </div>
                    <div class="mt-4 form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="activeAdd" value="1" checked>
                        <label class="form-check-label ms-2 fw-medium" for="activeAdd">Departemen Aktif</label>
                    </div>
                </div>
                <div class="p-3 modal-footer bg-light border-top">
                    <button type="button" class="px-4 btn btn-light rounded-pill" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="px-4 btn btn-primary rounded-pill fw-bold">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function confirmDelete(formId) {
        Swal.fire({
            title: 'Hapus Departemen?',
            text: "Data ini tidak dapat dikembalikan jika dihapus!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="bi bi-trash me-1"></i> Ya, Hapus',
            cancelButtonText: 'Batal',
            reverseButtons: true,
            customClass: {
                confirmButton: 'rounded-pill px-4 shadow-sm m-1',
                cancelButton: 'rounded-pill px-4 m-1'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById(formId).submit();
            }
        });
    }
</script>
@endpush
