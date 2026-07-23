@extends('layouts.app') {{-- Sesuaikan dengan nama layout master Anda --}}

@section('content')
<div class="px-0 container-fluid">
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <h3 class="fw-bold text-dark">Master Kategori Aset</h3>
        <button class="btn btn-primary rounded-pill fw-bold" data-bs-toggle="modal" data-bs-target="#modalTambah">
            <i class="bi bi-plus-circle me-1"></i> Tambah Kategori
        </button>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="border-0 shadow-sm card">
        <div class="card-body">
            <table class="table align-middle table-hover">
                <thead class="table-light">
                    <tr>
                        <th width="5%">No</th>
                        <th width="40%">Nama Kategori</th>
                        <th width="25%">Umur Ekonomis</th>
                        <th width="15%" class="text-center">Status</th>
                        <th width="15%" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $index => $cat)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td class="fw-bold">{{ $cat->name }}</td>
                        <td>{{ $cat->useful_life_years }} Tahun</td>
                        <td class="text-center">
                            @if($cat->is_active)
                                <span class="px-3 py-2 badge bg-success rounded-pill">Aktif</span>
                            @else
                                <span class="px-3 py-2 badge bg-danger rounded-pill">Tidak Aktif</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <button class="shadow-sm btn btn-sm btn-warning rounded-circle" data-bs-toggle="modal" data-bs-target="#modalEdit{{ $cat->id }}" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>

                            <form action="{{ route('asset-categories.destroy', $cat->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus kategori ini?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="shadow-sm btn btn-sm btn-danger rounded-circle" title="Hapus">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>

                    {{-- Modal Edit --}}
                    <div class="modal fade" id="modalEdit{{ $cat->id }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <form action="{{ route('asset-categories.update', $cat->id) }}" method="POST">
                                @csrf
                                @method('PUT')
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title fw-bold">Edit Kategori Aset</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Nama Kategori</label>
                                            <input type="text" name="name" class="form-control" value="{{ $cat->name }}" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Umur Ekonomis (Tahun)</label>
                                            <input type="number" name="useful_life_years" class="form-control" value="{{ $cat->useful_life_years }}" min="1" required>
                                        </div>
                                        <div class="mb-3">
                                            <div class="mt-2 form-check form-switch form-check-inline">
                                                <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="flexSwitchCheck{{ $cat->id }}" {{ $cat->is_active ? 'checked' : '' }}>
                                                <label class="form-check-label fw-bold" for="flexSwitchCheck{{ $cat->id }}">Status Aktif</label>
                                            </div>
                                            <small class="mt-1 d-block text-muted">Jika dimatikan, kategori ini tidak akan muncul lagi di pilihan saat menambah aset baru.</small>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Batal</button>
                                        <button type="submit" class="btn btn-primary rounded-pill">Simpan Perubahan</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    @empty
                    <tr>
                        <td colspan="5" class="py-4 text-center text-muted">Belum ada data kategori aset.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Modal Tambah --}}
<div class="modal fade" id="modalTambah" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('asset-categories.store') }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Tambah Kategori Aset Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Kategori</label>
                        <input type="text" name="name" class="form-control" placeholder="Contoh: Laptop & Komputer" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Umur Ekonomis (Tahun)</label>
                        <input type="number" name="useful_life_years" class="form-control" placeholder="Contoh: 4" min="1" required>
                    </div>
                    <div class="mb-3">
                        <div class="mt-2 form-check form-switch form-check-inline">
                            <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="flexSwitchCheckDefault" checked>
                            <label class="form-check-label fw-bold" for="flexSwitchCheckDefault">Status Aktif</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill">Simpan Data</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
