    @extends('layouts.app')

@section('content')
<div class="pb-5 container-fluid text-dark">
    <div class="gap-3 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-rulers text-primary me-2"></i> Master Satuan (UOM)</h4>
            <div class="mt-1 text-muted small">Kelola satuan dasar dan alternatif untuk barang (PCS, BOX, RIM).</div>
        </div>
        <div class="gap-2 d-flex">
            <form action="{{ route('uoms.index') }}" method="GET" class="d-flex">
                <div class="overflow-hidden border shadow-sm input-group rounded-pill">
                    <input type="text" name="search" class="px-4 border-0 form-control" placeholder="Cari satuan..." value="{{ request('search') }}">
                    <button class="px-3 border-0 btn btn-primary fw-bold" type="submit"><i class="bi bi-search"></i></button>
                    @if(request('search'))
                        <a href="{{ route('uoms.index') }}" class="btn btn-light text-danger"><i class="bi bi-x-lg"></i></a>
                    @endif
                </div>
            </form>
            <button class="px-4 shadow-sm btn btn-dark rounded-pill fw-bold" data-bs-toggle="modal" data-bs-target="#modalAddUom">
                <i class="bi bi-plus-lg me-1"></i> Tambah UOM
            </button>
        </div>
    </div>

    @if($errors->any())
        <div class="border-4 shadow-sm alert alert-danger border-start border-danger rounded-4 alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i> <strong>Gagal:</strong> {{ $errors->first() }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('success'))
        <div class="border-4 shadow-sm alert alert-success border-start border-success rounded-4 alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-2 text-success"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="border-4 shadow-sm alert alert-danger border-start border-danger rounded-4 alert-dismissible fade show">
            <i class="bi bi-x-octagon-fill me-2 text-danger"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="border-0 border-4 shadow-sm card border-top border-primary rounded-4">
        <div class="p-0 card-body table-responsive">
            <table class="table mb-0 align-middle table-hover">
                <thead class="bg-light small text-muted text-uppercase fw-bold">
                    <tr>
                        <th class="py-3 ps-4" width="5%">No</th>
                        <th class="py-3" width="15%">Kode Satuan</th>
                        <th class="py-3" width="25%">Nama Kepanjangan</th>
                        <th class="py-3" width="35%">Keterangan</th>
                        <th class="py-3 pe-4 text-end" width="20%">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($uoms as $uom)
                        <tr>
                            <td class="py-3 ps-4 text-muted small">{{ $uoms->firstItem() + $loop->index }}</td>
                            <td class="py-3"><span class="border badge bg-primary-subtle text-primary border-primary-subtle fs-6">{{ $uom->code }}</span></td>
                            <td class="py-3 fw-bold text-dark">{{ $uom->name }}</td>
                            <td class="py-3 small text-muted fst-italic">{{ $uom->description ?? '-' }}</td>
                            <td class="py-3 pe-4 text-end">
                                <button class="px-3 btn btn-sm btn-outline-info rounded-pill fw-bold" data-bs-toggle="modal" data-bs-target="#modalEditUom{{ $uom->id }}">
                                    <i class="bi bi-pencil-square"></i> Edit
                                </button>
                                <form action="{{ route('uoms.destroy', $uom->id) }}" method="POST" class="d-inline-block" onsubmit="return confirm('Yakin ingin menghapus Satuan ini?');">
                                    @csrf @method('DELETE')
                                    <button class="px-3 btn btn-sm btn-outline-danger rounded-pill fw-bold"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>

                        {{-- MODAL EDIT --}}
                        <div class="modal fade" id="modalEditUom{{ $uom->id }}" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="border-0 shadow-lg modal-content rounded-4">
                                    <form action="{{ route('uoms.update', $uom->id) }}" method="POST">
                                        @csrf @method('PUT')
                                        <div class="text-white border-0 modal-header bg-info">
                                            <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i> Edit Satuan (UOM)</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="p-4 modal-body text-start">
                                            <div class="mb-3">
                                                <label class="form-label fw-bold small text-muted">Kode Satuan (Misal: PCS) <span class="text-danger">*</span></label>
                                                <input type="text" name="code" class="form-control text-uppercase fw-bold" value="{{ $uom->code }}" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-bold small text-muted">Nama Panjang (Misal: Pieces) <span class="text-danger">*</span></label>
                                                <input type="text" name="name" class="form-control" value="{{ $uom->name }}" required>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label fw-bold small text-muted">Keterangan / Deskripsi</label>
                                                <textarea name="description" class="form-control" rows="2">{{ $uom->description }}</textarea>
                                            </div>
                                        </div>
                                        <div class="p-3 modal-footer border-top bg-light">
                                            <button type="button" class="px-4 border btn btn-light rounded-pill fw-bold" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="px-4 text-white shadow-sm btn btn-info rounded-pill fw-bold">Simpan Perubahan</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <tr><td colspan="5" class="py-5 text-center text-muted"><i class="mb-3 opacity-25 bi bi-rulers display-4 d-block"></i> Belum ada data Master Satuan (UOM).</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($uoms->hasPages())
            <div class="pt-3 pb-2 bg-white border-0 card-footer rounded-bottom-4">{{ $uoms->links() }}</div>
        @endif
    </div>
</div>

{{-- MODAL TAMBAH UOM --}}
<div class="modal fade" id="modalAddUom" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="border-0 shadow-lg modal-content rounded-4">
            <form action="{{ route('uoms.store') }}" method="POST">
                @csrf
                <div class="text-white border-0 modal-header bg-dark">
                    <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i> Tambah Satuan (UOM) Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="p-4 modal-body text-start">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Kode Satuan (Misal: BOX, LSN) <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control text-uppercase fw-bold border-primary" placeholder="Cth: PCS" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Nama Panjang <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="Cth: Pieces" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-bold small text-muted">Keterangan / Deskripsi</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Opsional..."></textarea>
                    </div>
                </div>
                <div class="p-3 modal-footer border-top bg-light">
                    <button type="button" class="px-4 border btn btn-light rounded-pill fw-bold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="px-4 shadow-sm btn btn-primary rounded-pill fw-bold"><i class="bi bi-save me-1"></i> Simpan Satuan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
