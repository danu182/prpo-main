@extends('layouts.app')

@push('css')
<style>
    /* 🔥 KUSTOMISASI KARTU MODERN 🔥 */
    .summary-card {
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        background: #fff;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .summary-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
    }

    /* 🔥 KUSTOMISASI TABEL SAAS 🔥 */
    .card-table-wrapper {
        border-radius: 16px;
        overflow: hidden;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        background: #fff;
    }
    .table-modern { margin-bottom: 0; }
    .table-modern thead th {
        background-color: #f8fafc;
        color: #64748b;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 1.2rem 1.5rem;
        border-bottom: 1px solid #e2e8f0;
        font-weight: 700;
        border-top: none;
    }
    .table-modern tbody td {
        padding: 1.25rem 1.5rem;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
        font-size: 0.9rem;
    }
    .table-modern tbody tr { transition: all 0.2s ease; background-color: #ffffff; }
    .table-modern tbody tr:hover { background-color: #f8fafc !important; }

    /* 🔥 KARTU AKSI / STATUS 🔥 */
    .action-card { border-radius: 16px; border: none; box-shadow: 0 6px 12px rgba(0,0,0,0.08); }
    .action-card.bg-dark-custom { background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color: white; }
    .action-card.bg-success-custom { background: linear-gradient(135deg, #10b981 0%, #15803d 100%); color: white; }
    .action-card.bg-warning-custom { background: linear-gradient(135deg, #f59e0b 0%, #b45309 100%); color: white; }
</style>
@endpush

@section('content')
{{-- DIBUAT FULL WIDTH TANPA MAX-WIDTH AGAR MENGISI LAYAR PENUH --}}
<div class="pb-5 container-fluid text-dark px-md-4">

    <div class="mb-4 d-flex justify-content-between align-items-end">
        <div>
            <a href="{{ route('fixed-assets.index') }}" class="mb-3 border shadow-sm btn btn-sm btn-light rounded-pill fw-bold text-secondary">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Master Aset
            </a>
            <h3 class="mb-0 fw-bold text-dark"><i class="bi bi-shield-check text-warning me-2"></i> Karantina Aset Tetap</h3>
            <p class="mt-1 mb-0 text-muted small">Review dan validasi data aset hasil import sebelum disahkan ke dalam buku utama.</p>
        </div>
    </div>

    <div class="mb-4 row g-4">
        {{-- KARTU 1: INFO BATCH --}}
        <div class="col-xl-5 col-lg-5">
            <div class="p-4 summary-card h-100 d-flex align-items-center">
                <div class="p-3 bg-primary bg-opacity-10 rounded-4 me-4 text-primary d-flex align-items-center justify-content-center" style="width: 70px; height: 70px;">
                    <i class="bi bi-file-earmark-spreadsheet fs-1"></i>
                </div>
                <div>
                    <div class="mb-1 tracking-wide small text-muted fw-bold text-uppercase">Nomor Batch Import</div>
                    <h4 class="mb-2 fw-bold text-dark">{{ $batch->batch_number }}</h4>
                    <span class="badge bg-{{ $batch->statusInfo->color }} px-3 py-2 rounded-pill shadow-sm"><i class="bi bi-info-circle me-1"></i> Status: {{ $batch->statusInfo->name }}</span>
                </div>
            </div>
        </div>

        {{-- KARTU 2: DOKUMEN PENDUKUNG --}}
        <div class="col-xl-4 col-lg-4">
            <div class="p-4 summary-card h-100 d-flex flex-column justify-content-center">
                <div class="mb-3 small text-muted fw-bold text-uppercase"><i class="bi bi-paperclip me-2"></i>Dokumen BAST / Pendukung</div>
                @if($batch->support_doc)
                    <a href="{{ asset('storage/' . $batch->support_doc) }}" target="_blank" class="p-3 transition border shadow-sm btn btn-light text-primary text-start fw-bold rounded-4 w-100">
                        <i class="align-middle bi bi-file-earmark-pdf-fill text-danger fs-4 me-2"></i> Lihat Dokumen Bukti
                    </a>
                @else
                    <div class="p-3 text-center border border-dashed rounded-4 bg-light">
                        <span class="small text-muted fst-italic"><i class="bi bi-folder-x me-1"></i> Tidak ada dokumen dilampirkan.</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- KARTU 3: STATUS / AKSI APPROVAL --}}
        <div class="col-xl-3 col-lg-3">
            @php
                $errCount = $batch->details->where('is_valid', false)->count();
                $currentApproval = \App\Models\DocumentApproval::with('role')->where('document_id', $batch->id)
                    ->where('document_type', get_class($batch))->where('status', 'PENDING')->first();

                $isApprover = false;
                if ($currentApproval && auth()->check()) {
                    $user = auth()->user();
                    $isApprover = $user->id === 1 ||
                                  $user->hasRole($currentApproval->role->name) ||
                                  $user->hasAnyRole(['super-admin', 'Super Admin', 'super_admin', 'Super Administrator']);
                }
            @endphp

            @if(in_array(strtolower($batch->status), ['draft', 'rejected']))
                <div class="p-4 text-center action-card bg-dark-custom h-100 d-flex flex-column justify-content-center">
                    <form id="formSubmitApproval" action="{{ route('fixed-assets.submit_approval', $batch->id) }}" method="POST" class="w-100">
                        @csrf
                        <button type="button" class="mb-3 shadow btn btn-warning btn-lg w-100 fw-bold rounded-pill btn-ajukan-approval" {{ $errCount > 0 ? 'disabled' : '' }}>
                            Ajukan Approval <i class="bi bi-send-check ms-1"></i>
                        </button>
                    </form>
                    <form id="formCancelDraft" action="{{ route('fixed-assets.cancel_import', $batch->id) }}" method="POST" class="w-100">
                        @csrf @method('DELETE')
                        <button type="button" class="border-2 btn btn-outline-light w-100 fw-bold rounded-pill btn-batal-draft">Batalkan Draft</button>
                    </form>
                </div>

            @elseif(strtolower($batch->status) === 'waiting_approval' && $isApprover)
                <div class="p-4 text-center action-card bg-dark-custom h-100 d-flex flex-column justify-content-center">
                    <div class="mb-3 small text-warning fw-bold"><i class="bi bi-exclamation-circle-fill me-1"></i> Menunggu Keputusan Anda</div>
                    <form action="{{ route('fixed-assets.decide', $batch->id) }}" method="POST" class="mb-2 w-100">
                        @csrf <input type="hidden" name="action" value="APPROVE">
                        <button type="button" class="shadow btn btn-success w-100 fw-bold rounded-pill btn-approve-final">
                            <i class="bi bi-check-circle-fill me-1"></i> Setujui (Approve)
                        </button>
                    </form>
                    <form action="{{ route('fixed-assets.decide', $batch->id) }}" method="POST" class="w-100">
                        @csrf <input type="hidden" name="action" value="REJECT">
                        <button type="button" class="text-white border-2 btn btn-outline-danger w-100 fw-bold rounded-pill small btn-reject-final" style="border-color: #ef4444!important;">
                            <i class="bi bi-x-circle me-1"></i> Tolak (Reject)
                        </button>
                    </form>
                </div>

            @elseif(strtolower($batch->status) === 'waiting_approval' && !$isApprover)
                <div class="p-4 text-center action-card bg-warning-custom h-100 d-flex flex-column justify-content-center">
                    <div class="mb-2 text-white opacity-75 display-4"><i class="bi bi-hourglass-split"></i></div>
                    <div class="text-white fs-5 fw-bold">Menunggu Persetujuan</div>
                    <div class="mt-1 text-white-50 small">Giliran: <strong class="text-white">{{ strtoupper($currentApproval->role->name ?? 'Atasan') }}</strong></div>
                </div>

            @elseif(strtolower($batch->status) === 'approved')
                <div class="p-4 text-center action-card bg-success-custom h-100 d-flex flex-column justify-content-center align-items-center">
                    <div class="mb-2 text-white opacity-75 display-4"><i class="bi bi-check-circle-fill"></i></div>
                    <h5 class="mb-0 tracking-wide text-white fw-bold">TELAH DISETUJUI</h5>
                    <div class="mt-1 text-white-50 small">Aset berhasil diregistrasi</div>
                </div>
            @endif
        </div>
    </div>

    {{-- TABEL DATA MODERN --}}
    <div class="mb-5 border-4 card-table-wrapper border-top border-warning">
        <div class="px-4 py-3 bg-white card-header border-bottom-0 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-list-ul me-2 text-warning"></i>Rincian Data Aset ({{ $batch->details->count() }} Baris)</h6>
            <span class="badge bg-{{ $errCount > 0 ? 'danger' : 'success' }}-subtle text-{{ $errCount > 0 ? 'danger' : 'success' }} border border-{{ $errCount > 0 ? 'danger' : 'success' }}-subtle rounded-pill px-3 py-2 fw-bold shadow-sm">
                @if($errCount > 0)
                    <i class="bi bi-exclamation-triangle-fill me-1"></i> {{ $errCount }} Error Ditemukan
                @else
                    <i class="bi bi-check-circle-fill me-1"></i> Semua Data Valid
                @endif
            </span>
        </div>
        <div class="p-0 card-body table-responsive">
            <table class="table align-middle table-modern">
                <thead>
                    <tr>
                        <th class="ps-4" width="15%">Item Master</th>
                        <th width="25%">Nama Aset Fisik</th>
                        <th width="20%">Akuntansi & S/N</th>
                        <th width="15%">Lokasi & Status</th>
                        <th width="15%">Harga Beli</th>
                        <th class="text-center pe-4" width="10%">Validasi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($batch->details as $d)
                    <tr>
                        <td class="ps-4">
                            @if($d->kode_barang)
                                <span class="px-2 py-1 shadow-sm badge bg-primary">{{ $d->kode_barang }}</span>
                            @else
                                <span class="px-2 py-1 shadow-sm badge bg-warning text-dark"><i class="bi bi-stars"></i> Auto-Create</span>
                            @endif
                        </td>
                        <td>
                            <div class="fw-bold text-dark">{{ $d->nama_spesifik_aset ?: 'Sesuai Master' }}</div>
                        </td>
                        <td>
                            <div class="mb-1 small text-muted"><span class="fw-bold text-dark">L:</span> {{ $d->label_akuntansi ?: '-' }}</div>
                            <div class="small text-muted"><span class="fw-bold text-dark">S:</span> {{ $d->serial_number ?: '-' }}</div>
                        </td>
                        <td>
                            <div class="mb-1 small fw-bold text-dark"><i class="bi bi-building text-primary me-1"></i> {{ $d->nama_pt }}</div>
                            <div class="mb-1 small text-muted"><i class="bi bi-box-seam text-info me-1"></i> {{ $d->nama_gudang }}</div>
                            <span class="border badge bg-secondary-subtle text-secondary border-secondary-subtle">{{ $d->status_aset }}</span>
                        </td>
                        <td class="fw-bold text-success">
                            {{ $d->mata_uang }} {{ number_format((float)$d->harga_beli, 0, ',', '.') }}
                        </td>
                        <td class="text-center pe-4">
                            @if($d->is_valid)
                                <i class="bi bi-check-circle-fill text-success fs-4 drop-shadow"></i>
                            @else
                                <i class="bi bi-x-circle-fill text-danger fs-4 drop-shadow" title="{{ $d->validation_error }}"></i>
                                <div class="mt-1 small fw-bold text-danger">{{ $d->validation_error }}</div>
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


@push('scripts')
{{-- Panggil Library SweetAlert2 --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    document.addEventListener("DOMContentLoaded", function() {

        // ==========================================
        // 1. SWEETALERT UNTUK TOMBOL AJUKAN APPROVAL
        // ==========================================
        document.querySelectorAll('.btn-ajukan-approval').forEach(btn => {
            btn.addEventListener('click', function() {
                const form = this.closest('form');
                Swal.fire({
                    title: 'Ajukan ke Atasan?',
                    text: "Dokumen ini akan masuk ke daftar persetujuan (Approval Matrix). Anda tidak dapat mengubah data ini lagi saat dalam proses persetujuan.",
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#f59e0b', // Kuning Warning
                    cancelButtonColor: '#64748b',
                    confirmButtonText: '<i class="bi bi-send-check me-1"></i> Ya, Ajukan!',
                    cancelButtonText: 'Batal',
                    customClass: { popup: 'rounded-4 shadow-lg border-0' }
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Mengirim...',
                            text: 'Mohon tunggu, sedang mengirim ke matriks persetujuan.',
                            icon: 'info',
                            showConfirmButton: false,
                            allowOutsideClick: false,
                            customClass: { popup: 'rounded-4 shadow-lg border-0' }
                        });
                        form.submit();
                    }
                });
            });
        });

        // ==========================================
        // 2. SWEETALERT UNTUK TOMBOL BATAL DRAFT
        // ==========================================
        document.querySelectorAll('.btn-batal-draft').forEach(btn => {
            btn.addEventListener('click', function() {
                const form = this.closest('form');
                Swal.fire({
                    title: 'Batalkan Draft Karantina?',
                    text: "Semua data yang telah diunggah di draft ini akan dihapus secara permanen dari sistem karantina.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626', // Merah Danger
                    cancelButtonColor: '#64748b',
                    confirmButtonText: '<i class="bi bi-trash-fill me-1"></i> Ya, Hapus!',
                    cancelButtonText: 'Batal',
                    customClass: { popup: 'rounded-4 shadow-lg border-0' }
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Menghapus...',
                            text: 'Sistem sedang membersihkan data karantina.',
                            icon: 'info',
                            showConfirmButton: false,
                            allowOutsideClick: false,
                            customClass: { popup: 'rounded-4 shadow-lg border-0' }
                        });
                        form.submit();
                    }
                });
            });
        });

        // ==========================================
        // 3. SWEETALERT UNTUK TOMBOL APPROVE (FINAL)
        // ==========================================
        document.querySelectorAll('.btn-approve-final').forEach(btn => {
            btn.addEventListener('click', function() {
                const form = this.closest('form');

                Swal.fire({
                    title: 'Mengesahkan Import Aset?',
                    text: "Aset akan resmi masuk ke Buku Utama. Sistem otomatis membuat Master Barang baru jika kode dikosongkan.",
                    icon: 'success',
                    showCancelButton: true,
                    confirmButtonColor: '#10b981', // Hijau Sukses
                    cancelButtonColor: '#64748b',
                    confirmButtonText: '<i class="bi bi-check-circle-fill me-1"></i> Ya, Sahkan Aset!',
                    cancelButtonText: 'Batal',
                    customClass: { popup: 'rounded-4 shadow-lg border-0' }
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Mengesahkan...',
                            text: 'Sistem sedang memindahkan aset dan merekam pergerakan stok.',
                            icon: 'info',
                            showConfirmButton: false,
                            allowOutsideClick: false,
                            customClass: { popup: 'rounded-4 shadow-lg border-0' }
                        });
                        form.submit();
                    }
                });
            });
        });

        // ==========================================
        // 4. SWEETALERT UNTUK TOMBOL REJECT (FINAL)
        // ==========================================
        document.querySelectorAll('.btn-reject-final').forEach(btn => {
            btn.addEventListener('click', function() {
                const form = this.closest('form');

                Swal.fire({
                    title: 'Tolak Pengajuan?',
                    text: "Berkas Karantina ini akan dikembalikan ke status Draft untuk diperbaiki oleh staf.",
                    icon: 'error',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626', // Merah Danger
                    cancelButtonColor: '#64748b',
                    confirmButtonText: '<i class="bi bi-x-circle me-1"></i> Ya, Tolak!',
                    cancelButtonText: 'Batal',
                    customClass: { popup: 'rounded-4 shadow-lg border-0' }
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Menolak...',
                            text: 'Mengembalikan berkas ke staf pengunggah.',
                            icon: 'info',
                            showConfirmButton: false,
                            allowOutsideClick: false,
                            customClass: { popup: 'rounded-4 shadow-lg border-0' }
                        });
                        form.submit();
                    }
                });
            });
        });

    });
</script>
@endpush
