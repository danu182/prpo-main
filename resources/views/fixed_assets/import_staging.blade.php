
@extends('layouts.app')

@section('content')
<div class="pb-5 container-fluid text-dark" style="max-width: 1500px;">

    <div class="mb-4 d-flex justify-content-between align-items-end">
        <div>
            <a href="{{ route('fixed-assets.index') }}" class="mb-2 text-decoration-none text-muted small fw-bold d-inline-block">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Master Aset
            </a>
            <h3 class="mb-0 fw-bold"><i class="bi bi-shield-check text-warning me-2"></i> Karantina Aset Tetap</h3>
        </div>
    </div>

    <div class="mb-4 row g-3">
        <div class="col-md-5">
            <div class="flex-row p-3 border-0 shadow-sm card rounded-4 h-100 d-flex align-items-center">
                <div class="p-3 bg-primary bg-opacity-10 rounded-3 me-3 text-primary"><i class="bi bi-file-earmark-spreadsheet fs-3"></i></div>
                <div>
                    <div class="small text-muted fw-bold">Nomor Batch Import</div>
                    <h5 class="mb-0 fw-bold">{{ $batch->batch_number }}</h5>
                    <span class="badge bg-{{ $batch->statusInfo->color }}">Status: {{ $batch->statusInfo->name }}</span>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="p-3 border-0 shadow-sm card rounded-4 h-100">
                <div class="mb-2 small text-muted fw-bold">Dokumen BAST / Pendukung</div>
                @if($batch->support_doc)
                    <a href="{{ asset('storage/' . $batch->support_doc) }}" target="_blank" class="p-2 border shadow-sm badge bg-light text-dark text-decoration-none">
                        <i class="bi bi-paperclip me-1"></i> Lihat Dokumen Bukti
                    </a>
                @else
                    <span class="small text-muted fst-italic">Tidak ada dokumen dilampirkan.</span>
                @endif
            </div>
        </div>

       <div class="col-md-3">
            <div class="p-3 text-center text-white border-0 shadow-sm card rounded-4 h-100 bg-dark d-flex flex-column justify-content-center">
                @php
                    $errCount = $batch->details->where('is_valid', false)->count();
                    $currentApproval = \App\Models\DocumentApproval::with('role')->where('document_id', $batch->id)
                        ->where('document_type', get_class($batch))->where('status', 'PENDING')->first();

                    // 🔥 PERBAIKAN 1: Hak Akses Super Admin dibuat lebih kebal (Bulletproof)
                    $isApprover = false;
                    if ($currentApproval && auth()->check()) {
                        $user = auth()->user();
                        $isApprover = $user->id === 1 ||
                                      $user->hasRole($currentApproval->role->name) ||
                                      $user->hasAnyRole(['super-admin', 'Super Admin', 'super_admin', 'Super Administrator']);
                    }
                @endphp

                @if(in_array(strtolower($batch->status), ['draft', 'rejected']))
                    <form action="{{ route('fixed-assets.submit_approval', $batch->id) }}" method="POST" class="w-100">
                        @csrf
                        <button type="submit" class="mb-2 shadow-sm btn btn-warning w-100 fw-bold rounded-pill" {{ $errCount > 0 ? 'disabled' : '' }}>
                            Ajukan Approval <i class="bi bi-send-check ms-1"></i>
                        </button>
                    </form>
                    <form action="{{ route('fixed-assets.cancel_import', $batch->id) }}" method="POST" class="w-100">
                        @csrf @method('DELETE')
                        <button type="submit" class="border-2 btn btn-outline-danger w-100 fw-bold rounded-pill small" onclick="return confirm('Hapus draft ini?')">Batalkan Draft</button>
                    </form>

                {{-- 🔥 PERBAIKAN 2: Ubah 'pending' menjadi 'waiting_approval' --}}
                @elseif(strtolower($batch->status) === 'waiting_approval' && $isApprover)
                    <div class="mb-2 small text-warning fw-bold"><i class="bi bi-exclamation-circle-fill me-1"></i> Menunggu Keputusan Anda</div>
                    <form action="{{ route('fixed-assets.decide', $batch->id) }}" method="POST" class="mb-2 w-100">
                        @csrf <input type="hidden" name="action" value="APPROVE">
                        <button type="submit" class="shadow-sm btn btn-success w-100 fw-bold rounded-pill" onclick="return confirm('Mengesahkan dan Auto-Create Item?')"><i class="bi bi-check-circle-fill me-1"></i> Setujui (Approve)</button>
                    </form>
                    <form action="{{ route('fixed-assets.decide', $batch->id) }}" method="POST" class="w-100">
                        @csrf <input type="hidden" name="action" value="REJECT">
                        <button type="submit" class="border-2 btn btn-outline-danger w-100 fw-bold rounded-pill small"><i class="bi bi-x-circle me-1"></i> Tolak (Reject)</button>
                    </form>

                {{-- 🔥 PERBAIKAN 3: Jika Menunggu Approval tapi bukan giliran User ini (agar kotak tidak kosong) --}}
                @elseif(strtolower($batch->status) === 'waiting_approval' && !$isApprover)
                    <div class="mb-2 display-4 text-warning"><i class="bi bi-hourglass-split"></i></div>
                    <div class="small fw-bold">Menunggu Persetujuan</div>
                    <div class="text-white-50" style="font-size: 0.75rem;">Giliran: <strong>{{ strtoupper($currentApproval->role->name ?? 'Atasan') }}</strong></div>

                @elseif(strtolower($batch->status) === 'approved')
                    <div class="mb-2 display-4 text-success"><i class="bi bi-check-circle-fill"></i></div>
                    <h6 class="mb-0 fw-bold text-success">TELAH DISETUJUI</h6>
                @endif
            </div>
        </div>


    </div>

    {{-- TABEL DATA --}}
    <div class="border-0 border-4 shadow-sm card rounded-4 border-top border-warning">
        <div class="py-3 bg-white card-header border-bottom-0 d-flex justify-content-between">
            <h6 class="mb-0 fw-bold">Rincian Data Aset ({{ $batch->details->count() }} Baris)</h6>
            <span class="badge bg-{{ $errCount > 0 ? 'danger' : 'success' }} rounded-pill px-3 py-2">
                {{ $errCount > 0 ? $errCount . ' Error Ditemukan' : 'Semua Data Valid' }}
            </span>
        </div>
        <div class="p-0 card-body table-responsive">
            <table class="table mb-0 align-middle table-hover table-striped">
                <thead class="bg-light small text-muted text-uppercase">
                    <tr>
                        <th class="ps-4">Item Master</th>
                        <th>Nama Aset Fisik</th>
                        <th>Akuntansi & S/N</th>
                        <th>Lokasi & Status</th>
                        <th>Harga Beli</th>
                        <th class="text-center pe-4">Validasi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($batch->details as $d)
                    <tr>
                        <td class="ps-4">
                            @if($d->kode_barang)
                                <span class="badge bg-primary">{{ $d->kode_barang }}</span>
                            @else
                                <span class="badge bg-warning text-dark"><i class="bi bi-stars"></i> Auto-Create</span>
                            @endif
                        </td>
                        <td class="fw-bold">{{ $d->nama_spesifik_aset ?: 'Sesuai Master' }}</td>
                        <td>
                            <div class="small">L: {{ $d->label_akuntansi ?: '-' }}</div>
                            <div class="small">S: {{ $d->serial_number ?: '-' }}</div>
                        </td>
                        <td>
                            <div class="small fw-bold text-dark"><i class="bi bi-building"></i> {{ $d->nama_pt }}</div>
                            <div class="small text-muted"><i class="bi bi-box-seam"></i> {{ $d->nama_gudang }}</div>
                            <span class="mt-1 badge bg-secondary">{{ $d->status_aset }}</span>
                        </td>
                        <td class="fw-bold text-success">{{ $d->mata_uang }} {{ number_format((float)$d->harga_beli, 0, ',', '.') }}</td>
                        <td class="text-center pe-4">
                            @if($d->is_valid)
                                <i class="bi bi-check-circle-fill text-success fs-5"></i>
                            @else
                                <i class="bi bi-x-circle-fill text-danger fs-5" title="{{ $d->validation_error }}"></i>
                                <div class="mt-1 small text-danger">{{ $d->validation_error }}</div>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
