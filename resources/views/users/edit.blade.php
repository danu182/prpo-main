@extends('layouts.app')

@section('content')
<div class="container-fluid pb-5 text-dark">

    <div class="gap-3 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h4 class="mb-0 fw-bold text-dark">
                <i class="bi bi-people-fill me-2 text-primary"></i> Manajemen Pengguna (Users)
            </h4>
            <div class="mt-1 text-muted small">Kelola data karyawan, hak akses aplikasi, dan status keaktifan.</div>
        </div>

        <div class="gap-2 d-flex flex-column flex-md-row">
            <form action="{{ route('users.index') }}" method="GET" class="d-flex">
                <div class="shadow-sm input-group">
                    <span class="bg-white input-group-text border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="border-start-0 form-control" placeholder="Cari nama/email..." value="{{ request('search') }}">
                    <button class="btn btn-primary" type="submit">Cari</button>
                </div>
            </form>
            <button type="button" class="px-4 shadow-sm btn btn-dark fw-bold rounded-pill text-nowrap" data-bs-toggle="modal" data-bs-target="#modalAddUser">
                <i class="bi bi-person-plus-fill me-1"></i> Tambah Pengguna
            </button>
        </div>
    </div>

    <div class="border-0 shadow-sm card rounded-4">
        <div class="p-0 card-body table-responsive">
            <table class="table mb-0 align-middle table-hover">
                <thead class="bg-light fw-bold text-muted border-bottom">
                    <tr>
                        <th class="py-3 ps-4">Pegawai</th>
                        <th class="py-3">Perusahaan (PT)</th>
                        <th class="py-3">Hak Akses (Role)</th>
                        <th class="py-3 text-center">Status</th>
                        <th class="py-3 pe-4 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                    <tr>
                        <td class="py-3 ps-4">
                            <div class="fw-bold text-dark">{{ $user->name }}</div>
                            <div class="text-muted small">
                                <i class="bi bi-envelope me-1"></i> {{ $user->email }}
                                @if($user->job_title)
                                    <br><i class="bi bi-person-badge me-1"></i> {{ $user->job_title }}
                                @endif
                            </div>
                        </td>
                        <td class="fw-semibold text-secondary">
                            {{ optional($user->company)->name ?? '-' }}
                        </td>
                        <td>
                            <div class="flex-wrap gap-1 d-flex">
                                @forelse($user->roles as $role)
                                    @if($role->name === 'Super Admin')
                                        <span class="badge bg-danger rounded-pill"><i class="bi bi-star-fill me-1"></i> Super Admin</span>
                                    @else
                                        <span class="px-2 border badge bg-light text-dark rounded-pill border-secondary-subtle">
                                            {{ $role->name }}
                                        </span>
                                    @endif
                                @empty
                                    <span class="text-muted small fst-italic">Tanpa Akses</span>
                                @endforelse
                            </div>
                        </td>
                        <td class="text-center">
                            @if($user->is_active)
                                <span class="px-3 py-1 border badge bg-success-subtle text-success border-success-subtle rounded-pill">Aktif</span>
                            @else
                                <span class="px-3 py-1 border badge bg-secondary-subtle text-secondary border-secondary-subtle rounded-pill">Nonaktif</span>
                            @endif
                        </td>
                        <td class="pe-4 text-end text-nowrap">
                            <button type="button" class="px-3 shadow-sm btn btn-sm btn-outline-primary rounded-pill fw-bold me-1" data-bs-toggle="modal" data-bs-target="#modalEditUser{{ $user->id }}">
                                <i class="bi bi-pencil-square me-1"></i> Edit
                            </button>

                            @if(auth()->id() !== $user->id)
                            <form action="{{ route('users.destroy', $user->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus permanen pengguna ini?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="px-3 shadow-sm btn btn-sm btn-outline-danger rounded-pill fw-bold">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            @else
                            <button type="button" class="px-3 shadow-sm btn btn-sm btn-light rounded-pill fw-bold text-muted disabled" title="Anda tidak bisa menghapus diri sendiri">
                                <i class="bi bi-trash"></i>
                            </button>
                            @endif
                        </td>
                    </tr>

                    {{-- MODAL EDIT USER --}}
                    <div class="modal fade" id="modalEditUser{{ $user->id }}" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered modal-lg">
                            <div class="overflow-hidden border-0 shadow-lg modal-content rounded-4">
                                <form action="{{ route('users.update', $user->id) }}" method="POST">
                                    @csrf
                                    @method('PUT')
                                    <div class="py-3 text-white border-0 modal-header bg-primary">
                                        <h5 class="modal-title fw-bold"><i class="bi bi-person-lines-fill me-2"></i>Edit Pengguna: {{ $user->name }}</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="p-4 modal-body bg-light text-start">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold small text-muted">Nama Lengkap <span class="text-danger">*</span></label>
                                                <input type="text" name="name" class="shadow-sm form-control" value="{{ $user->name }}" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold small text-muted">Email (Login) <span class="text-danger">*</span></label>
                                                <input type="email" name="email" class="shadow-sm form-control" value="{{ $user->email }}" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold small text-muted">Perusahaan (PT)</label>
                                                <select name="company_id" class="shadow-sm form-select">
                                                    <option value="">-- Tanpa Perusahaan --</option>
                                                    @foreach($companies as $company)
                                                        <option value="{{ $company->id }}" {{ $user->company_id == $company->id ? 'selected' : '' }}>{{ $company->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold small text-muted">Jabatan / Job Title</label>
                                                <input type="text" name="job_title" class="shadow-sm form-control" value="{{ $user->job_title }}" placeholder="Cth: Staff Gudang">
                                            </div>
                                        </div>

                                        <div class="mt-4 row g-3">
                                            <div class="col-md-6">
                                                <div class="p-3 border border-warning bg-warning-subtle rounded-3">
                                                    <label class="form-label fw-bold small text-dark"><i class="bi bi-key-fill me-1"></i>Ganti Password</label>
                                                    <input type="password" name="password" class="mb-2 shadow-sm form-control form-control-sm" placeholder="Ketik password baru...">
                                                    <input type="password" name="password_confirmation" class="shadow-sm form-control form-control-sm" placeholder="Ulangi password baru...">
                                                    <small class="mt-1 text-muted d-block" style="font-size: 0.7rem;">Biarkan kosong jika tidak ingin mengubah password.</small>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold small text-muted">Status Akun <span class="text-danger">*</span></label>
                                                <select name="is_active" class="mb-3 shadow-sm form-select" required>
                                                    <option value="1" {{ $user->is_active ? 'selected' : '' }}>🟢 Aktif (Bisa Login)</option>
                                                    <option value="0" {{ !$user->is_active ? 'selected' : '' }}>🔴 Nonaktif / Resign (Tidak Bisa Login)</option>
                                                </select>

                                                <label class="form-label fw-bold small text-dark"><i class="bi bi-shield-lock-fill text-danger me-1"></i> Berikan Hak Akses (Role)</label>
                                                <div class="p-2 bg-white border rounded shadow-sm" style="max-height: 120px; overflow-y: auto;">
                                                    @foreach($roles as $role)
                                                        <div class="form-check">
                                                            <input class="cursor-pointer form-check-input" type="checkbox" name="roles[]" value="{{ $role->name }}" id="editRole{{ $user->id }}_{{ $role->id }}" {{ $user->hasRole($role->name) ? 'checked' : '' }}>
                                                            <label class="form-check-label small fw-semibold" for="editRole{{ $user->id }}_{{ $role->id }}">
                                                                {{ $role->name }}
                                                            </label>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="p-3 bg-white modal-footer border-top">
                                        <button type="button" class="px-4 border btn btn-light rounded-pill fw-bold" data-bs-dismiss="modal">Batal</button>
                                        <button type="submit" class="px-4 text-white shadow-sm btn btn-primary rounded-pill fw-bold">Update Pengguna</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    @empty
                    <tr><td colspan="5" class="py-5 text-center text-muted">Belum ada data Pengguna.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if(isset($users) && $users->hasPages())
        <div class="pt-3 pb-2 bg-white border-0 card-footer rounded-bottom-4">{{ $users->links() }}</div>
        @endif
    </div>
</div>

{{-- MODAL TAMBAH USER BARU --}}
<div class="modal fade" id="modalAddUser" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="overflow-hidden border-0 shadow-lg modal-content rounded-4">
            <form action="{{ route('users.store') }}" method="POST">
                @csrf
                <div class="py-3 text-white border-0 modal-header bg-dark">
                    <h5 class="modal-title fw-bold"><i class="bi bi-person-plus-fill me-2"></i>Daftarkan Pengguna Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="p-4 modal-body bg-light text-start">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="shadow-sm form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Email (Login) <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="shadow-sm form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="shadow-sm form-control" required minlength="6">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Konfirmasi Password <span class="text-danger">*</span></label>
                            <input type="password" name="password_confirmation" class="shadow-sm form-control" required minlength="6">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Perusahaan (PT)</label>
                            <select name="company_id" class="shadow-sm form-select">
                                <option value="">-- Tanpa Perusahaan --</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}">{{ $company->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Jabatan / Job Title</label>
                            <input type="text" name="job_title" class="shadow-sm form-control" placeholder="Cth: Staff Gudang">
                        </div>
                        <div class="mt-3 col-12">
                            <label class="form-label fw-bold small text-dark"><i class="bi bi-shield-lock-fill text-danger me-1"></i> Berikan Hak Akses (Role)</label>
                            <div class="p-3 bg-white border rounded shadow-sm">
                                <div class="row g-2">
                                    @foreach($roles as $role)
                                        <div class="col-md-4 col-sm-6">
                                            <div class="form-check">
                                                <input class="cursor-pointer form-check-input" type="checkbox" name="roles[]" value="{{ $role->name }}" id="addRole{{ $role->id }}">
                                                <label class="form-check-label small fw-semibold" for="addRole{{ $role->id }}">
                                                    {{ $role->name }}
                                                </label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="p-3 bg-white modal-footer border-top">
                    <button type="button" class="px-4 border btn btn-light rounded-pill fw-bold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="px-4 text-white shadow-sm btn btn-dark rounded-pill fw-bold">Simpan Pengguna</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
