@extends('layouts.app')

@section('content')
<div class="container pb-5 text-dark">

    <div class="gap-3 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h4 class="mb-0 fw-bold text-dark">
                <i class="bi bi-shield-lock me-2 text-danger"></i> Manajemen Hak Akses (Role)
            </h4>
            <div class="mt-1 text-muted small">Buat grup jabatan dan atur menu apa saja yang boleh mereka akses.</div>
        </div>

        <button type="button" class="px-4 shadow-sm btn btn-dark fw-bold rounded-pill text-nowrap" data-bs-toggle="modal" data-bs-target="#modalAddRole">
            <i class="bi bi-plus-circle me-1"></i> Buat Role Baru
        </button>
    </div>

    <div class="border-0 border-4 shadow-sm card border-top border-danger rounded-3">
        <div class="p-0 card-body table-responsive">
            <table class="table mb-0 align-middle table-hover">
                <thead class="bg-light fw-bold text-muted border-bottom">
                    <tr>
                        <th class="py-3 ps-4" width="20%">Nama Role / Jabatan</th>
                        <th class="py-3" width="60%">Daftar Izin Menu (Permissions)</th>
                        <th class="py-3 pe-4 text-end" width="20%">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($roles as $role)
                    <tr>
                        <td class="py-3 ps-4 fw-bold text-dark fs-6">
                            @if($role->name === 'Super Admin')
                                <span class="text-danger"><i class="bi bi-star-fill me-1"></i> {{ $role->name }}</span>
                            @else
                                <i class="bi bi-shield-check text-secondary me-1"></i> {{ $role->name }}
                            @endif
                        </td>
                        <td class="py-3">
                            <div class="flex-wrap gap-1 d-flex">
                                @if($role->name === 'Super Admin')
                                    <span class="px-3 border badge bg-danger-subtle text-danger border-danger-subtle rounded-pill">Bypass Semua Akses (Full Power)</span>
                                @else
                                    @forelse($role->permissions as $perm)
                                        <span class="px-2 border rounded shadow-sm badge bg-light text-dark border-secondary-subtle">
                                            {{ str_replace('_', ' ', strtoupper($perm->name)) }}
                                        </span>
                                    @empty
                                        <span class="text-muted small fst-italic">Belum diberikan izin apapun.</span>
                                    @endforelse
                                @endif
                            </div>
                        </td>
                        <td class="py-3 pe-4 text-end text-nowrap">
                            @if($role->name !== 'Super Admin')
                                <button type="button" class="px-3 shadow-sm btn btn-sm btn-outline-danger rounded-pill fw-bold me-1" data-bs-toggle="modal" data-bs-target="#modalEditRole{{ $role->id }}">
                                    <i class="bi bi-gear-fill me-1"></i> Atur Izin
                                </button>
                                <form action="{{ route('roles.destroy', $role->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus Role ini? Pengguna yang memakai role ini akan kehilangan akses!');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="px-3 shadow-sm btn btn-sm btn-outline-secondary rounded-pill fw-bold">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            @else
                                <span class="px-3 py-2 badge bg-secondary rounded-pill"><i class="bi bi-lock-fill"></i> Terkunci</span>
                            @endif
                        </td>
                    </tr>

                    {{-- MODAL EDIT ROLE --}}
                    @if($role->name !== 'Super Admin')
                    <div class="modal fade" id="modalEditRole{{ $role->id }}" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered modal-lg">
                            <div class="overflow-hidden border-0 shadow-lg modal-content rounded-4">
                                <form action="{{ route('roles.update', $role->id) }}" method="POST">
                                    @csrf
                                    @method('PUT')
                                    <div class="py-3 text-white border-0 modal-header bg-danger">
                                        <h5 class="modal-title fw-bold"><i class="bi bi-shield-lock me-2"></i>Edit Role: {{ $role->name }}</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="p-4 modal-body bg-light text-start">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold small text-muted">Nama Jabatan / Role</label>
                                            <input type="text" name="name" class="shadow-sm form-control fw-bold" value="{{ $role->name }}" required>
                                        </div>

                                        <label class="mt-3 form-label fw-bold small text-dark"><i class="bi bi-ui-checks-grid text-danger me-1"></i> Centang Izin Menu (Permissions)</label>
                                        <div class="p-3 bg-white border rounded shadow-sm">
                                            <div class="row g-3">
                                                @foreach($permissions as $perm)
                                                <div class="col-md-4 col-sm-6">
                                                    <div class="form-check form-switch">
                                                        <input class="cursor-pointer form-check-input" type="checkbox" name="permissions[]" value="{{ $perm->name }}" id="perm_{{ $role->id }}_{{ $perm->id }}" {{ $role->hasPermissionTo($perm->name) ? 'checked' : '' }}>
                                                        <label class="form-check-label small fw-semibold" style="cursor:pointer;" for="perm_{{ $role->id }}_{{ $perm->id }}">
                                                            {{ str_replace('_', ' ', strtoupper($perm->name)) }}
                                                        </label>
                                                    </div>
                                                </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                    <div class="p-3 bg-white modal-footer border-top">
                                        <button type="button" class="px-4 border btn btn-light rounded-pill fw-bold" data-bs-dismiss="modal">Batal</button>
                                        <button type="submit" class="px-4 text-white shadow-sm btn btn-danger rounded-pill fw-bold">Update Role</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    @endif

                    @empty
                    <tr><td colspan="3" class="py-5 text-center text-muted">Belum ada data Role.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if(isset($roles) && $roles->hasPages())
        <div class="pt-3 pb-2 bg-white border-0 card-footer rounded-bottom-4">{{ $roles->links() }}</div>
        @endif
    </div>
</div>

{{-- MODAL TAMBAH ROLE BARU --}}
<div class="modal fade" id="modalAddRole" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="overflow-hidden border-0 shadow-lg modal-content rounded-4">
            <form action="{{ route('roles.store') }}" method="POST">
                @csrf
                <div class="py-3 text-white border-0 modal-header bg-dark">
                    <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Buat Grup Role Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="p-4 modal-body bg-light text-start">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Nama Jabatan / Grup <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="shadow-sm form-control" placeholder="Cth: Staff HRD, Auditor, Manager Gudang..." required>
                    </div>

                    <label class="mt-3 form-label fw-bold small text-dark"><i class="bi bi-ui-checks-grid text-danger me-1"></i> Otoritas Izin Awal (Bisa dikosongkan)</label>
                    <div class="p-3 bg-white border rounded shadow-sm">
                        <div class="row g-3">
                            @foreach($permissions as $perm)
                            <div class="col-md-4 col-sm-6">
                                <div class="form-check form-switch">
                                    <input class="cursor-pointer form-check-input" type="checkbox" name="permissions[]" value="{{ $perm->name }}" id="perm_new_{{ $perm->id }}">
                                    <label class="form-check-label small fw-semibold text-muted" style="cursor:pointer;" for="perm_new_{{ $perm->id }}">
                                        {{ str_replace('_', ' ', strtoupper($perm->name)) }}
                                    </label>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="p-3 bg-white modal-footer border-top">
                    <button type="button" class="px-4 border btn btn-light rounded-pill fw-bold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="px-4 text-white shadow-sm btn btn-dark rounded-pill fw-bold">Simpan Role Baru</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
