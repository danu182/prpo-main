@extends('layouts.app') <!-- Sesuaikan dengan layout Anda -->

@section('content')
<div class="container-fluid">
    <div class="mb-3 d-flex justify-content-between align-items-center">
        <h2>Master Kategori</h2>
        <a href="{{ route('categories.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Tambah Kategori
        </a>
    </div>

    {{-- ALERT SUCCESS & ERROR --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- KOTAK PENCARIAN --}}
    <div class="mb-4 border-0 shadow-sm card">
        <div class="card-body">
            <form action="{{ route('categories.index') }}" method="GET" class="d-flex">
                <input type="text" name="search" class="form-control me-2" placeholder="Cari Kode atau Nama Kategori..." value="{{ request('search') }}">
                <button type="submit" class="px-4 btn btn-secondary">Cari</button>
                @if(request('search'))
                    <a href="{{ route('categories.index') }}" class="btn btn-outline-danger ms-2">Reset</a>
                @endif
            </form>
        </div>
    </div>

    <div class="border-0 shadow-sm card">
        <div class="card-body table-responsive">
            <table class="table align-middle table-bordered table-striped">
                <thead class="table-light">
                    <tr>
                        <th width="5%">No</th>
                        <th width="15%">Kode</th>
                        <th width="30%">Nama Kategori</th>
                        <th width="25%">Parent (Induk)</th>
                        <th width="10%">Status</th>
                        <th width="15%" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $index => $category)
                    <tr>
                        {{-- Logika penomoran untuk pagination --}}
                        <td>{{ ($categories->currentPage() - 1) * $categories->perPage() + $loop->iteration }}</td>
                        <td class="fw-bold">{{ $category->code }}</td>
                        <td>{{ $category->name }}</td>
                        <td>
                            @if($category->parent)
                                <span class="badge bg-info text-dark">{{ $category->parent->name }}</span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            @if(isset($category->is_active) && $category->is_active)
                                <span class="badge bg-success">Aktif</span>
                            @elseif(isset($category->is_active) && !$category->is_active)
                                <span class="badge bg-danger">Non-Aktif</span>
                            @else
                                <span class="badge bg-success">Aktif</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <a href="{{ route('categories.edit', $category->id) }}" class="btn btn-sm btn-warning">Edit</a>

                            @if(isset($category->is_active) && $category->is_active)
                            <form action="{{ route('categories.destroy', $category->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menonaktifkan kategori ini?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger">Nonaktifkan</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="py-4 text-center text-muted">Data kategori tidak ditemukan.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>

            {{-- TOMBOL PAGINATION --}}
            <div class="mt-3 d-flex justify-content-end">
                {{ $categories->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
</div>
@endsection
