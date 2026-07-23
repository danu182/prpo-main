@extends('layouts.app')

@push('css')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <style>
        /* Memastikan select2 di dalam modal tidak berantakan */
        .select2-container { width: 100% !important; }
    </style>
@endpush

@section('content')
<div class="pb-5 container-fluid text-dark">

    {{-- HEADER --}}
    <div class="gap-3 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h4 class="mb-0 fw-bold text-dark">
                <i class="bi bi-people me-2 text-primary"></i> Manajemen Pengguna
            </h4>
            <div class="mt-1 text-muted small">Kelola data karyawan, departemen, dan hak akses sistem (Role).</div>
        </div>

        <div class="gap-2 d-flex">
            <form action="{{ route('users.index') }}" method="GET" class="d-flex" style="min-width: 250px;">
                <div class="overflow-hidden border shadow-sm input-group rounded-pill">
                    <input type="text" name="search" class="px-4 bg-white border-0 form-control" placeholder="Cari Nama / Email..." value="{{ request('search') }}">
                    <button class="px-4 text-white border-0 btn btn-primary fw-bold" type="submit">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </form>
            {{-- 🔥 Tombol Baru: Mengarah ke halaman Import/Export 🔥 --}}
            <a href="{{ route('users.import_form') }}" class="px-4 shadow-sm btn btn-outline-success fw-bold rounded-pill text-nowrap" title="Manajemen Data Excel">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> <span class="d-none d-md-inline">Import / Export</span>
            </a>
            <button type="button" class="px-4 shadow-sm btn btn-dark fw-bold rounded-pill text-nowrap" data-bs-toggle="modal" data-bs-target="#modalAdd">
                <i class="bi bi-person-plus me-1"></i> Tambah Karyawan
            </button>
        </div>
    </div>

    @if(session('error'))
        <div class="border-0 shadow-sm alert alert-danger rounded-3 fw-bold">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close float-end" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('success'))
        <div class="border-0 shadow-sm alert alert-success rounded-3 fw-bold">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close float-end" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="border-0 shadow-sm alert alert-danger rounded-3 small">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- TABEL USER --}}
    <div class="border-0 border-4 shadow-sm card border-top border-primary rounded-3">
        <div class="p-0 card-body table-responsive">
            <table class="table mb-0 align-middle table-hover">
                <thead class="bg-light fw-bold text-muted border-bottom">
                    <tr>
                        <th class="py-3 ps-4" width="25%">Profil Karyawan</th>
                        <th class="py-3" width="25%">PT & Departemen</th>
                        <th class="py-3" width="20%">Hak Akses (Roles)</th>
                        <th class="py-3 text-center" width="10%">Status</th>
                        <th class="py-3 pe-4 text-end" width="20%">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                    <tr>
                        <td class="py-3 ps-4">
                            <div class="d-flex align-items-center">
                                @if($user->avatar)
                                    <img src="{{ asset('storage/' . $user->avatar) }}" class="shadow-sm rounded-circle me-3 object-fit-cover" width="45" height="45">
                                @else
                                    <div class="text-white shadow-sm rounded-circle bg-primary d-flex justify-content-center align-items-center me-3 fw-bold" style="width: 45px; height: 45px;">
                                        {{ strtoupper(substr($user->name, 0, 2)) }}
                                    </div>
                                @endif
                                <div>
                                    <div class="fw-bold text-dark">{{ $user->name }}</div>
                                    <div class="small text-muted">{{ $user->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="py-3">
                            <div class="fw-bold text-primary"><i class="bi bi-building me-1"></i> {{ optional($user->company)->name ?? 'Pusat (Tanpa PT)' }}</div>
                            <div class="mt-1 small text-dark"><i class="bi bi-diagram-3 me-1"></i> {{ optional($user->department)->name ?? 'Belum ada Dept' }}</div>
                            <div class="mt-1 small text-muted"><i class="bi bi-person-badge me-1"></i> {{ $user->job_title ?? 'Staf' }}</div>
                        </td>
                        <td class="py-3">
                            <div class="flex-wrap gap-1 mb-1 d-flex">
                                @forelse($user->roles as $role)
                                    <span class="badge {{ $role->name == 'Super Admin' ? 'bg-danger' : 'bg-dark' }} rounded-pill shadow-sm"><i class="bi bi-shield-lock me-1"></i> {{ $role->name }}</span>
                                @empty
                                    <span class="border badge bg-light text-muted fst-italic">Belum ada role</span>
                                @endforelse
                            </div>
                            @if($user->warehouses->count() > 0)
                                <div class="mt-1" style="font-size: 0.7rem;">
                                    <span class="text-info fw-bold">Gudang:</span>
                                    {{ $user->warehouses->pluck('name')->implode(', ') }}
                                </div>
                            @endif
                        </td>
                        <td class="py-3 text-center">
                            @if($user->is_active)
                                <span class="px-3 border badge bg-success-subtle text-success border-success-subtle rounded-pill">Aktif</span>
                            @else
                                <span class="px-3 border badge bg-danger-subtle text-danger border-danger-subtle rounded-pill">Nonaktif</span>
                            @endif
                        </td>
                        <td class="py-3 pe-4 text-end text-nowrap">
                            <button type="button" class="px-3 shadow-sm btn btn-sm btn-outline-primary rounded-pill fw-bold me-1" data-bs-toggle="modal" data-bs-target="#modalEdit{{ $user->id }}">
                                <i class="bi bi-gear-fill me-1"></i> Atur
                            </button>
                            <form action="{{ route('users.destroy', $user->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus user ini secara permanen?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="px-3 shadow-sm btn btn-sm btn-outline-danger rounded-pill fw-bold" {{ auth()->id() == $user->id ? 'disabled' : '' }}>
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>

                    {{-- MODAL EDIT USER --}}
                    <div class="modal fade" id="modalEdit{{ $user->id }}" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered modal-lg">
                            <div class="overflow-hidden border-0 shadow-lg modal-content rounded-4">
                                <form action="{{ route('users.update', $user->id) }}" method="POST">
                                    @csrf
                                    @method('PUT')
                                    <div class="py-3 text-white border-0 modal-header bg-primary">
                                        <h5 class="modal-title fw-bold"><i class="bi bi-person-gear me-2"></i>Edit Pengguna: {{ $user->name }}</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="p-4 modal-body bg-light text-start">

                                        <div class="mb-3 row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold small text-muted">Nama Lengkap</label>
                                                <input type="text" name="name" class="shadow-sm form-control" value="{{ $user->name }}" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold small text-muted">Email Perusahaan</label>
                                                <input type="email" name="email" class="shadow-sm form-control" value="{{ $user->email }}" required>
                                            </div>
                                        </div>

                                        <div class="mb-3 row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold small text-muted">Password Baru <span class="fw-normal text-danger" style="font-size:0.7rem;">(Kosongkan jika tidak diubah)</span></label>
                                                <input type="password" name="password" class="shadow-sm form-control" placeholder="Minimal 6 karakter">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold small text-muted">Konfirmasi Password</label>
                                                <input type="password" name="password_confirmation" class="shadow-sm form-control" placeholder="Ketik ulang password">
                                            </div>
                                        </div>

                                        <hr class="border-dashed text-muted">

                                        <div class="mb-3 row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold small text-muted">Perusahaan (PT)</label>
                                                <select name="company_id" class="shadow-sm form-select">
                                                    <option value="">-- Pusat (Tanpa PT) --</option>
                                                    @foreach($companies as $cmp)
                                                        <option value="{{ $cmp->id }}" {{ $user->company_id == $cmp->id ? 'selected' : '' }}>{{ $cmp->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold small text-muted">Departemen</label>
                                                <select name="department_id" class="shadow-sm form-select">
                                                    <option value="">-- Pilih Departemen --</option>
                                                    @foreach($departments as $dept)
                                                        <option value="{{ $dept->id }}" {{ $user->department_id == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        <div class="mb-4 row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold small text-muted">Jabatan</label>
                                                <input type="text" name="job_title" class="shadow-sm form-control" value="{{ $user->job_title }}">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold small text-muted">Status Akun</label>
                                                <select name="is_active" class="shadow-sm form-select">
                                                    <option value="1" {{ $user->is_active ? 'selected' : '' }}>🟢 Aktif</option>
                                                    <option value="0" {{ !$user->is_active ? 'selected' : '' }}>🔴 Nonaktif</option>
                                                </select>
                                            </div>
                                        </div>

                                        {{-- 🔥 FILTER GUDANG 🔥 --}}
                                        <div class="p-3 mb-4 bg-white border shadow-sm border-info-subtle rounded-3">
                                            <label class="mb-1 form-label fw-bold small text-info-emphasis">
                                                <i class="bi bi-box-seam text-info me-1"></i> Isolasi Akses Gudang <span class="fw-normal text-muted">(Opsional)</span>
                                            </label>
                                            <select name="warehouse_ids[]" class="form-select select2-multiple" multiple="multiple">
                                                @php $userWhs = $user->warehouses->pluck('id')->toArray(); @endphp
                                                @foreach($warehouses as $wh)
                                                    <option value="{{ $wh->id }}" {{ in_array($wh->id, $userWhs) ? 'selected' : '' }}>{{ $wh->name }}</option>
                                                @endforeach
                                            </select>
                                            <div class="mt-2 form-text" style="font-size: 0.75rem;">
                                                Biarkan kosong jika user ini adalah <strong>Super Admin/Manager/Finance</strong> (sistem otomatis memberikan akses penuh).
                                            </div>
                                        </div>

                                        {{-- 🔥 OTORITAS ROLE 🔥 --}}
                                        <div class="p-3 bg-white border rounded shadow-sm">
                                            <label class="mb-1 form-label fw-bold small text-dark">
                                                <i class="bi bi-shield-lock-fill text-warning me-1"></i> Otoritas Hak Akses (Role)
                                            </label>
                                            <div class="mt-0 mb-3 form-text" style="font-size: 0.75rem;">Centang departemen/sistem yang boleh diakses.</div>
                                            <div class="row g-2">
                                                @foreach($roles as $role)
                                                <div class="col-md-4 col-sm-6">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="roles[]" value="{{ $role->name }}" id="role_{{ $user->id }}_{{ $role->id }}" {{ $user->hasRole($role->name) ? 'checked' : '' }}>
                                                        <label class="mt-1 form-check-label small fw-bold text-secondary" for="role_{{ $user->id }}_{{ $role->id }}">
                                                            {{ $role->name }}
                                                        </label>
                                                    </div>
                                                </div>
                                                @endforeach
                                            </div>
                                        </div>

                                    </div>
                                    <div class="p-3 bg-white modal-footer border-top">
                                        <button type="button" class="px-4 border btn btn-light rounded-pill fw-bold" data-bs-dismiss="modal">Batal</button>
                                        <button type="submit" class="px-4 text-white shadow-sm btn btn-primary rounded-pill fw-bold">Simpan Perubahan</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    @empty
                    <tr>
                        <td colspan="5" class="py-5 text-center text-muted">Belum ada data pengguna.</td>
                    </tr>
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
<div class="modal fade" id="modalAdd" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="overflow-hidden border-0 shadow-lg modal-content rounded-4">
            <form action="{{ route('users.store') }}" method="POST">
                @csrf
                <div class="py-3 text-white border-0 modal-header bg-dark">
                    <h5 class="modal-title fw-bold"><i class="bi bi-person-plus-fill me-2"></i>Pendaftaran Karyawan Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="p-4 modal-body bg-light text-start">

                    <div class="mb-3 row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="shadow-sm form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Email Perusahaan <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="shadow-sm form-control" required>
                        </div>
                    </div>

                    <div class="mb-3 row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Buat Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="shadow-sm form-control" placeholder="Minimal 6 karakter" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Konfirmasi Password <span class="text-danger">*</span></label>
                            <input type="password" name="password_confirmation" class="shadow-sm form-control" required>
                        </div>
                    </div>

                    <hr class="border-dashed text-muted">

                    <div class="mb-3 row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Perusahaan (PT)</label>
                            <select name="company_id" class="shadow-sm form-select">
                                <option value="">-- Pusat (Tanpa PT) --</option>
                                @foreach($companies as $cmp)
                                    <option value="{{ $cmp->id }}">{{ $cmp->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Departemen</label>
                            <select name="department_id" class="shadow-sm form-select">
                                <option value="">-- Pilih Departemen --</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mb-4 row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-bold small text-muted">Jabatan</label>
                            <input type="text" name="job_title" class="shadow-sm form-control" placeholder="Cth: Staf Gudang">
                        </div>
                    </div>

                    {{-- 🔥 FILTER GUDANG 🔥 --}}
                    <div class="p-3 mb-4 bg-white border shadow-sm border-info-subtle rounded-3">
                        <label class="mb-1 form-label fw-bold small text-info-emphasis">
                            <i class="bi bi-box-seam text-info me-1"></i> Isolasi Akses Gudang <span class="fw-normal text-muted">(Opsional)</span>
                        </label>
                        <select name="warehouse_ids[]" class="form-select select2-multiple" multiple="multiple">
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                            @endforeach
                        </select>
                        <div class="mt-2 form-text" style="font-size: 0.75rem;">
                            Bisa pilih > 1 gudang. Biarkan kosong jika user ini adalah <strong>Super Admin/Manager/Finance</strong>.
                        </div>
                    </div>

                    {{-- 🔥 OTORITAS ROLE 🔥 --}}
                    <div class="p-3 bg-white border rounded shadow-sm">
                        <label class="mb-1 form-label fw-bold small text-dark">
                            <i class="bi bi-shield-lock-fill text-danger me-1"></i> Otoritas Awal (Role)
                        </label>
                        <div class="mt-2 row g-2">
                            @foreach($roles as $role)
                            <div class="col-md-4 col-sm-6">
                                <div class="form-check">
                                    <input class="cursor-pointer form-check-input" type="checkbox" name="roles[]" value="{{ $role->name }}" id="role_new_{{ $role->id }}">
                                    <label class="mt-1 form-check-label small fw-bold text-secondary" for="role_new_{{ $role->id }}">
                                        {{ $role->name }}
                                    </label>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>

                </div>
                <div class="p-3 bg-white modal-footer border-top">
                    <button type="button" class="px-4 border btn btn-light rounded-pill fw-bold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="px-4 text-white shadow-sm btn btn-dark rounded-pill fw-bold">Simpan Karyawan</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        // Trik Bootstrap 5 Modal x Select2
        $('.modal').on('shown.bs.modal', function () {
            $(this).find('.select2-multiple').select2({
                theme: 'bootstrap-5',
                placeholder: "-- Cari & Pilih Gudang --",
                width: '100%',
                dropdownParent: $(this)
            });
        });
    });
</script>
@endpush
