@extends('layouts.app')

@section('content')
<div class="pb-5 container-fluid">

    <div class="mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-diagram-3-fill me-2 text-primary"></i>Pengaturan Matriks Persetujuan</h4>
            <p class="mb-0 text-muted">Atur jumlah lapis persetujuan untuk setiap jenis dokumen.</p>
        </div>
        <div>
            <a href="{{ route('workflows.create') }}" class="shadow-sm btn btn-primary rounded-pill fw-bold">
                <i class="bi bi-plus-circle me-1"></i> Buat Matriks Baru
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="border-0 shadow-sm alert alert-success rounded-4"><i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}</div>
    @endif

    <div class="border-0 shadow-sm card rounded-4">
        <div class="p-4 card-body">
            <table class="table align-middle table-hover">
                <thead class="bg-light text-muted small text-uppercase">
                    <tr>
                        <th width="40%">Jenis Dokumen</th>
                        <th width="20%">Total Lapis (Step)</th>
                        <th width="20%">Status</th>
                        <th width="20%" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($workflows as $wf)
                    <tr>
                        <td>
                            <span class="fw-bold text-dark">{{ $wf->name }}</span><br>
                            <span class="small text-muted">{{ $wf->document_type }}</span>
                            <div class="mt-1">
                                @if($wf->department_id)
                                    <span class="badge bg-info text-dark"><i class="bi bi-tag-fill me-1"></i>Spesifik: {{ $wf->department->name }}</span>
                                @else
                                    <span class="badge bg-secondary"><i class="bi bi-globe me-1"></i>Berlaku Umum / Default</span>
                                @endif
                            </div>
                        </td>
                        <td><span class="badge bg-primary rounded-pill">{{ $wf->steps_count }} Lapis Persetujuan</span></td>
                        <td>
                            @if($wf->is_active)
                                <span class="badge bg-success">Aktif</span>
                            @else
                                <span class="badge bg-secondary">Non-Aktif</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <a href="{{ route('workflows.edit', $wf->id) }}" class="btn btn-sm btn-warning fw-bold text-dark rounded-pill">
                                <i class="bi bi-gear-fill me-1"></i> Atur Formasi
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
