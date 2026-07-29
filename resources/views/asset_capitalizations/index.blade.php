@extends('layouts.app')

@section('content')
<div class="px-0 container-fluid text-dark">
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 fw-bold text-dark">
                <i class="bi bi-card-list text-info me-2"></i> Daftar Pengakuan Aset
            </h4>
            <div class="mt-1 text-muted small">Kelola dan pantau seluruh aset yang telah dikapitalisasi dari gudang.</div>
        </div>
        <a href="{{ route('asset-capitalizations.create') }}" class="px-4 shadow-sm btn btn-primary fw-bold rounded-pill">
            <i class="bi bi-plus-lg me-1"></i> Tambah Pengakuan Aset
        </a>
    </div>

    @if(session('success'))
        <div class="shadow-sm alert alert-success fw-bold rounded-3"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="shadow-sm alert alert-danger fw-bold rounded-3"><i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}</div>
    @endif

    <div class="border-0 shadow-sm card rounded-4">
        <div class="p-0 card-body">
            <div class="table-responsive">
                <table class="table mb-0 align-middle table-hover">
                    <thead class="table-light">
                        <tr>
                            <th class="px-4 py-3 text-uppercase small fw-bold text-muted">No Aset</th>
                            <th class="py-3 text-uppercase small fw-bold text-muted">Nama Barang & Foto</th>
                            <th class="py-3 text-uppercase small fw-bold text-muted">Serial Number</th>
                            <th class="py-3 text-center text-uppercase small fw-bold text-muted">Status</th>
                            <th class="py-3 text-uppercase small fw-bold text-muted">Tanggal Diakui</th>
                            <th class="px-4 py-3 text-uppercase small fw-bold text-muted text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($assets as $asset)
                            @php
                                // Deteksi apakah aset ini sudah dibatalkan
                                $isVoid = str_contains($asset->notes, '[DIBATALKAN');
                            @endphp
                            <tr class="{{ $isVoid ? 'bg-danger-subtle' : '' }}">
                                <td class="px-4 py-3 fw-bold">
                                    <span class="{{ $isVoid ? 'text-danger text-decoration-line-through' : 'text-primary' }}">
                                        {{ $asset->asset_number }}
                                    </span>
                                </td>
                                <td class="py-3">
                                    <div class="d-flex align-items-center">
                                        {{-- 🔥 THUMBNAIL FOTO ASET DARI TABEL ASSET_PHOTOS 🔥 --}}
                                        @if($asset->photos->isNotEmpty())
                                            <img src="{{ asset('storage/' . $asset->photos->first()->file_path) }}" class="shadow-sm img-thumbnail me-3 rounded-3" style="width: 55px; height: 55px; object-fit: cover; border: 2px solid #dee2e6;" alt="Aset">
                                        @else
                                            <div class="border bg-light text-secondary d-flex justify-content-center align-items-center me-3 rounded-3" style="width: 55px; height: 55px;">
                                                <i class="bi bi-image text-muted fs-4"></i>
                                            </div>
                                        @endif

                                        <div>
                                            <div class="fw-bold {{ $isVoid ? 'text-danger' : 'text-dark' }}">{{ $asset->name }}</div>
                                            @if($asset->item)
                                                <div class="small text-muted"><i class="bi bi-box me-1"></i>Master: {{ $asset->item->code }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 fw-bold text-dark">{{ $asset->serial_number ?: '-' }}</td>
                                <td class="py-3 text-center">
                                    @if($isVoid)
                                        <span class="border shadow-sm badge bg-danger border-danger">BATAL / VOID</span>
                                    @else
                                        <span class="shadow-sm badge bg-success">{{ optional($asset->status)->name ?? 'Tersedia' }}</span>
                                    @endif
                                </td>
                                <td class="py-3">
                                    <div class="text-dark">{{ $asset->created_at->format('d M Y') }}</div>
                                    <div class="small text-muted">{{ $asset->created_at->format('H:i') }}</div>
                                </td>
                                <td class="px-4 py-3 text-end">
                                    <a href="{{ route('asset-capitalizations.show', $asset->id) }}" class="btn btn-sm {{ $isVoid ? 'btn-danger' : 'btn-info text-white' }} fw-bold rounded-pill px-3 shadow-sm">
                                        Detail
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-5 text-center text-muted">
                                    <i class="mb-2 opacity-50 bi bi-images fs-1 d-block text-secondary"></i>
                                    <span class="fw-bold">Belum ada data pengakuan aset.</span>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Pagination --}}
        @if($assets->hasPages())
            <div class="p-3 bg-white card-footer border-top d-flex justify-content-end rounded-bottom-4">
                {{ $assets->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
