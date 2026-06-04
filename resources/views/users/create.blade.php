@extends('layouts.app')

@section('content')

@if ($errors->any())
    <div class="alert alert-danger">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="container pb-5">
    <div class="mb-4">
        <a href="{{ route('users.index') }}" class="btn btn-outline-secondary rounded-pill fw-bold">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar
        </a>
    </div>

    <div class="mx-auto border-0 shadow-sm card rounded-4" style="max-width: 800px;">
        <div class="py-3 bg-white card-header border-bottom">
            <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-person-plus-fill me-2 text-primary"></i>Tambah Pengguna Baru</h5>
        </div>
        <div class="p-4 card-body">
            <form action="{{ route('users.store') }}" method="POST">
                @csrf

                <div class="row g-4">
                    <div class="col-md-12">
                        <label class="form-label fw-bold small text-muted text-uppercase">Nama Lengkap</label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="Masukkan nama lengkap" required>
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted text-uppercase">Alamat Email</label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" placeholder="email@perusahaan.com" required>
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted text-uppercase">Jabatan / Role</label>
                        <select name="role" class="form-select @error('role') is-invalid @enderror" required>
                            <option value="">-- Pilih Jabatan --</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->name }}" {{ old('role') == $role->name ? 'selected' : '' }}>
                                    {{ $role->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('role') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted text-uppercase">Password</label>
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted text-uppercase">Konfirmasi Password</label>
                        <input type="password" name="password_confirmation" class="form-control" required>
                    </div>
                </div>

                <div class="pt-3 mt-5 border-top text-end">
                    <button type="reset" class="px-4 btn btn-light rounded-pill me-2">Reset</button>
                    <button type="submit" class="px-5 shadow-sm btn btn-primary rounded-pill fw-bold">
                        <i class="bi bi-check-circle me-1"></i> Simpan Pengguna
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
