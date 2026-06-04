@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">

            {{-- Alert Sukses --}}
            @if(session('success'))
                <div class="shadow-sm alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="border-0 shadow-sm card rounded-4">
                <div class="py-3 bg-white card-header border-bottom">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-person-gear me-2"></i>Edit Profil Saya</h5>
                </div>
                <div class="p-4 card-body">

                    <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <div class="mb-4 row">
                            {{-- KOLOM KIRI: FOTO PROFIL (AVATAR) --}}
                            <div class="mb-4 text-center col-md-4 mb-md-0 border-end">
                                <label class="mb-3 form-label fw-bold d-block">Foto Profil</label>

                                <div class="mb-3 position-relative d-inline-block">
                                    {{-- Image Preview Avatar --}}
                                    <img id="avatarPreview"
                                         src="{{ $user->avatar ? asset('storage/' . $user->avatar) : 'https://ui-avatars.com/api/?name='.urlencode($user->name).'&background=random&size=150' }}"
                                         class="border shadow-sm rounded-circle object-fit-cover"
                                         width="150" height="150" alt="Avatar">

                                    {{-- Overlay Icon Camera (Hiasan) --}}
                                    <div class="bottom-0 p-1 bg-white border shadow-sm position-absolute end-0 rounded-circle">
                                        <i class="bi bi-camera-fill text-secondary"></i>
                                    </div>
                                </div>

                                <input type="file" name="avatar" id="avatarInput" class="mt-2 form-control form-control-sm" accept="image/*">
                                <small class="mt-1 text-muted d-block" style="font-size: 0.75rem;">JPG, PNG (Max 2MB)</small>
                            </div>

                            {{-- KOLOM KANAN: DATA DIRI --}}
                            <div class="col-md-8 ps-md-4">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Nama Lengkap</label>
                                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}">
                                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold">Email</label>
                                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}">
                                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold">Jabatan / Role</label>
                                    <input type="text" class="form-control bg-light text-muted" value="{{ $user->roles->pluck('name')->first() ?? 'Staff' }}" readonly>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4 border-secondary opacity-10">

                        {{-- SECTION TANDA TANGAN --}}
                        <div class="mb-4 row">
                            <div class="mb-3 col-12">
                                <h6 class="fw-bold text-primary"><i class="bi bi-pen me-2"></i>Tanda Tangan Digital</h6>
                                <p class="mb-0 small text-muted">
                                    Upload scan tanda tangan (Format PNG Transparan). Wajib untuk keperluan dokumen PR & PO.
                                </p>
                            </div>

                            <div class="mb-3 col-md-6">
                                <label class="form-label fw-bold">Upload File</label>
                                <input type="file" name="signature" id="signatureInput" class="form-control @error('signature') is-invalid @enderror" accept="image/*">
                                <small class="mt-1 text-muted d-block">Format: PNG (Disarankan) atau JPG. Max 2MB.</small>
                                @error('signature') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold d-block">Preview Tanda Tangan</label>
                                <div class="p-3 border rounded bg-light d-flex align-items-center justify-content-center position-relative" style="height: 120px; border-style: dashed !important;">

                                    {{-- Image Preview Signature --}}
                                    <img id="signaturePreview"
                                         src="{{ $user->signature ? asset('storage/' . $user->signature) : '' }}"
                                         style="max-height: 100px; max-width: 100%; display: {{ $user->signature ? 'block' : 'none' }};">

                                    {{-- Teks Placeholder jika kosong --}}
                                    <span id="signaturePlaceholder" class="text-muted small fst-italic" style="display: {{ $user->signature ? 'none' : 'block' }};">
                                        Belum ada tanda tangan
                                    </span>

                                </div>
                            </div>
                        </div>

                        <hr class="my-4 border-secondary opacity-10">

                        {{-- SECTION GANTI PASSWORD --}}
                        <div class="p-3 mx-0 mb-4 rounded row bg-light">
                            <div class="mb-2 col-12">
                                <h6 class="mb-0 fw-bold text-danger"><i class="bi bi-shield-lock me-2"></i>Ganti Password</h6>
                                <small class="text-muted" style="font-size: 0.75rem;">Kosongkan jika tidak ingin mengubah password.</small>
                            </div>
                            <div class="mb-3 col-md-6 mb-md-0">
                                <label class="form-label small fw-bold">Password Baru</label>
                                <input type="password" name="password" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Konfirmasi Password</label>
                                <input type="password" name="password_confirmation" class="form-control">
                            </div>
                        </div>

                        {{-- TOMBOL ACTION --}}
                        <div class="gap-2 d-flex justify-content-end">
                            <a href="{{ route('dashboard') }}" class="px-4 border btn btn-light">Batal</a>
                            <button type="submit" class="px-4 shadow-sm btn btn-primary fw-bold">
                                <i class="bi bi-save me-2"></i> Simpan Perubahan
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

{{-- JAVASCRIPT UNTUK PREVIEW GAMBAR --}}
@push('scripts')
<script>
    // 1. Preview Avatar
    document.getElementById('avatarInput').addEventListener('change', function(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('avatarPreview').src = e.target.result;
            }
            reader.readAsDataURL(file);
        }
    });

    // 2. Preview Signature
    document.getElementById('signatureInput').addEventListener('change', function(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.getElementById('signaturePreview');
                const placeholder = document.getElementById('signaturePlaceholder');

                img.src = e.target.result;
                img.style.display = 'block'; // Tampilkan gambar
                placeholder.style.display = 'none'; // Sembunyikan teks "Belum ada..."
            }
            reader.readAsDataURL(file);
        }
    });
</script>
@endpush
