@extends('layouts.app')

@push('css')
<style>
    .skeleton { background: #e2e5e7; background-image: linear-gradient(90deg, rgba(255,255,255,0), rgba(255,255,255,0.6), rgba(255,255,255,0)); background-size: 200px 100%; background-repeat: no-repeat; border-radius: 4px; display: inline-block; animation: shimmer 1.5s infinite linear; }
    @keyframes shimmer { 0% { background-position: -200px 0; } 100% { background-position: calc(200px + 100%) 0; } }
    .sk-text { height: 16px; width: 100%; margin-bottom: 6px; }
    .sk-text-short { height: 12px; width: 60%; }
    .sk-btn { height: 32px; width: 32px; border-radius: 50%; }
    .logo-thumbnail { width: 45px; height: 45px; object-fit: contain; border-radius: 8px; border: 1px solid #dee2e6; background: #fff; padding: 2px; }
</style>
@endpush

@section('content')
<div class="px-0 pb-5 container-fluid text-dark">

    <div class="mb-4 row align-items-center">
        <div class="mb-3 col-lg-5 mb-lg-0">
            <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-buildings-fill me-2 text-primary"></i> Master Perusahaan (Entities)</h4>
            <div class="mt-1 text-muted small">Kelola data entitas perusahaan, kantor pusat, dan cabang.</div>
        </div>
        <div class="col-lg-7">
            <div class="flex-wrap gap-2 d-flex justify-content-lg-end">
                <form action="{{ route('companies.index') }}" method="GET" class="d-flex flex-grow-1 flex-md-grow-0">
                    <div class="overflow-hidden border shadow-sm input-group rounded-pill">
                        <input type="text" name="search" class="px-4 bg-white border-0 form-control" placeholder="Cari Nama atau Kode..." value="{{ request('search') }}">
                        <button class="px-4 text-white border-0 btn btn-primary fw-bold" type="submit"><i class="bi bi-search"></i></button>
                    </div>
                </form>
                <a href="{{ route('companies.create') }}" class="px-4 shadow-sm btn btn-dark rounded-pill fw-bold"><i class="bi bi-plus-lg me-1"></i> Tambah PT</a>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="border-0 border-4 shadow-sm alert alert-success rounded-3 fw-bold border-start border-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="border-0 border-4 shadow-sm card border-top border-primary rounded-3">
        <div class="p-0 card-body table-responsive">
            <table class="table mb-0 align-middle table-hover">
                <thead class="bg-light fw-bold text-muted border-bottom">
                    <tr>
                        <th class="py-3 ps-4" width="25%">Logo & Nama PT</th>
                        <th class="py-3" width="15%">Kode PT</th>
                        <th class="py-3" width="25%">Kontak & Email</th>
                        <th class="py-3" width="20%">NPWP</th>
                        <th class="py-3 pe-4 text-end" width="15%">Aksi</th>
                    </tr>
                </thead>

                {{-- SKELETON LOADING --}}
                <tbody id="skeleton-table">
                    @for($i = 0; $i < 3; $i++)
                    <tr>
                        <td class="py-3 ps-4"><div class="skeleton sk-text" style="width: 80%;"></div></td>
                        <td class="py-3"><div class="skeleton sk-text" style="width: 50%;"></div></td>
                        <td class="py-3"><div class="skeleton sk-text" style="width: 70%;"></div></td>
                        <td class="py-3"><div class="skeleton sk-text" style="width: 60%;"></div></td>
                        <td class="py-3 pe-4 text-end"><div class="skeleton sk-btn"></div></td>
                    </tr>
                    @endfor
                </tbody>

                {{-- ACTUAL DATA --}}
                <tbody id="actual-table" class="d-none">
                    @forelse($companies as $company)
                    <tr>
                        <td class="py-3 ps-4">
                            <div class="d-flex align-items-center">
                                @if($company->logo_path)
                                    <img src="{{ asset('storage/' . $company->logo_path) }}" class="shadow-sm logo-thumbnail me-3">
                                @else
                                    <div class="shadow-sm logo-thumbnail me-3 d-flex align-items-center justify-content-center bg-light text-muted">
                                        <i class="opacity-50 bi bi-image fs-5"></i>
                                    </div>
                                @endif
                                <div>
                                    <div class="fw-bold text-dark fs-6">{{ $company->name }}</div>
                                    @if($company->is_head_office)
                                        <span class="px-2 py-1 mt-1 border badge bg-primary-subtle text-primary border-primary-subtle" style="font-size: 0.65rem;"><i class="bi bi-star-fill me-1"></i> Head Office</span>
                                    @else
                                        <span class="px-2 py-1 mt-1 border badge bg-secondary-subtle text-secondary" style="font-size: 0.65rem;"><i class="bi bi-building me-1"></i> Cabang</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="py-3 fw-bold text-primary font-monospace">{{ $company->code }}</td>
                        <td class="py-3">
                            <div class="mb-1 small text-muted"><i class="bi bi-envelope-at me-1"></i> {{ $company->email ?? '-' }}</div>
                            <div class="small text-muted"><i class="bi bi-telephone me-1"></i> {{ $company->phone ?? '-' }}</div>
                        </td>
                        <td class="py-3 fw-semibold text-secondary">{{ $company->tax_id ?? 'Tidak Ada Data' }}</td>
                        <td class="py-3 pe-4 text-end text-nowrap">
                            <a href="{{ route('companies.show', $company->id) }}" class="px-3 shadow-sm btn btn-sm btn-outline-info rounded-pill fw-bold me-1" title="Detail PT">
                                <i class="bi bi-eye"></i> Detail
                            </a>
                            <a href="{{ route('companies.edit', $company->id) }}" class="px-3 shadow-sm btn btn-sm btn-outline-warning rounded-pill fw-bold" title="Edit PT">
                                <i class="bi bi-pencil-square"></i> Edit
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="py-5 text-center text-muted">
                            <i class="mb-3 opacity-50 bi bi-buildings text-secondary display-6 d-block"></i>
                            <p class="mb-0 small">Belum ada data perusahaan.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($companies->hasPages())
        <div class="pt-3 pb-2 bg-white border-0 card-footer rounded-bottom-4">{{ $companies->links() }}</div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener("DOMContentLoaded", function() {
        setTimeout(() => {
            document.getElementById('skeleton-table').classList.add('d-none');
            document.getElementById('actual-table').classList.remove('d-none');
        }, 500);
    });
</script>
@endpush
