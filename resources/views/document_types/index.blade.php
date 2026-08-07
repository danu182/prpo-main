@extends('layouts.app')

@section('content')
<div class="container-fluid pb-5">
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-file-earmark-medical-fill me-2 text-primary"></i>Master Jenis Dokumen</h4>
            <p class="mb-0 text-muted">Daftar modul yang mendukung sistem Matriks Persetujuan.</p>
        </div>
        <a href="{{ route('document-types.create') }}" class="btn btn-primary rounded-pill fw-bold shadow-sm">
            <i class="bi bi-plus-circle me-1"></i> Tambah Dokumen Baru
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm rounded-4">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger border-0 shadow-sm rounded-4">{{ session('error') }}</div>
    @endif

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <table class="table table-hover align-middle">
                <thead class="bg-light text-muted small text-uppercase">
                    <tr>
                        <th>Nama Dokumen</th>
                        <th>Namespace Model (Class)</th>
                        <th>Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($types as $type)
                    <tr>
                        <td><span class="fw-bold">{{ $type->name }}</span></td>
                        <td><code>{{ $type->model_class }}</code></td>
                        <td>
                            <span class="badge {{ $type->is_active ? 'bg-success' : 'bg-secondary' }}">
                                {{ $type->is_active ? 'Aktif' : 'Non-Aktif' }}
                            </span>
                        </td>
                        <td class="text-center">
                            <a href="{{ route('document-types.edit', $type->id) }}" class="btn btn-sm btn-light border rounded-pill">
                                <i class="bi bi-pencil-fill text-warning"></i>
                            </a>
                            <form action="{{ route('document-types.destroy', $type->id) }}" method="POST" class="d-inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-light border rounded-pill" onclick="return confirm('Hapus dokumen ini?')">
                                    <i class="bi bi-trash-fill text-danger"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
