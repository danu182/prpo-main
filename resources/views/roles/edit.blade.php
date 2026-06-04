@extends('layouts.app')

@section('content')
<div class="container pb-5">
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <a href="{{ route('roles.index') }}" class="btn btn-outline-secondary rounded-pill fw-bold">
            <i class="bi bi-arrow-left me-1"></i> Kembali
        </a>
    </div>

    <div class="border-0 shadow-sm card rounded-4">
        <div class="py-3 bg-white card-header border-bottom">
            <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-pencil-square me-2 text-primary"></i>Edit Hak Akses Role</h5>
        </div>
        <div class="p-4 card-body">
            <form action="{{ route('roles.update', $role->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="mb-4 w-50">
                    <label class="form-label fw-bold small text-muted text-uppercase">Nama Jabatan / Role <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control fw-bold" value="{{ $role->name }}" required>
                </div>

                <h6 class="pb-2 mb-3 fw-bold text-dark border-bottom">Pilih Hak Akses (Permissions)</h6>
                <div class="row g-3">
                    @foreach($allPermissions as $permission)
                        <div class="col-md-3 col-sm-4">
                            <div class="p-3 border rounded form-check form-switch bg-light hover-shadow" style="transition: 0.2s;">
                                <input class="form-check-input" type="checkbox" role="switch"
                                       id="perm_{{ $permission->id }}"
                                       name="permissions[]"
                                       value="{{ $permission->name }}"
                                       {{ in_array($permission->id, $rolePermissions) ? 'checked' : '' }}
                                       style="transform: scale(1.2); margin-left: -2em;">
                                <label class="ms-2 small fw-bold text-dark form-check-label" style="cursor: pointer;" for="perm_{{ $permission->id }}">
                                    {{ str_replace('_', ' ', strtoupper($permission->name)) }}
                                </label>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-5">
                    <button type="submit" class="px-5 shadow-sm btn btn-primary rounded-pill fw-bold">
                        <i class="bi bi-save me-1"></i> Update Role
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
