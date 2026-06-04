@extends('layouts.app')

@section('content')
<div class="container pb-5">

    <div class="mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-diagram-3-fill me-2 text-primary"></i>Pengaturan Matriks Persetujuan</h4>
            <p class="mb-0 text-muted">Atur jumlah lapis persetujuan untuk setiap jenis dokumen.</p>
        </div>
        <div>
            <a href="{{ route('workflows.create') }}" class="btn btn-primary rounded-pill fw-bold shadow-sm">
                <i class="bi bi-plus-circle me-1"></i> Buat Matriks Baru
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success shadow-sm border-0 rounded-4"><i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}</div>
    @endif

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <table class="table table-hover align-middle">
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
