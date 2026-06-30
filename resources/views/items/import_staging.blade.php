@extends('layouts.app')

@push('css')
<style>
    .table-staging tbody tr.is-invalid { background-color: #fff5f5; border-left: 4px solid #dc3545; }
    .table-staging tbody tr.is-valid { border-left: 4px solid #198754; }
    .table-container { max-height: 550px; overflow-y: auto; }
    .table-staging thead th { position: sticky; top: 0; background: #f8f9fa; z-index: 10; border-bottom: 2px solid #dee2e6; }
</style>
@endpush

@section('content')
<div class="pb-5 container-fluid text-dark" style="max-width: 1500px;">

    {{-- HEADER --}}
    <div class="mb-4 d-flex justify-content-between align-items-end">
        <div>
            <a href="{{ route('fixed-assets.index') }}" class="mb-2 text-decoration-none text-muted small fw-bold d-inline-block">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Master Aset
            </a>
            <h3 class="mb-0 fw-bold"><i class="bi bi-shield-check text-warning me-2"></i> Ruang Karantina Aset Tetap</h3>
        </div>
    </div>

    {{-- TAMPILKAN ERROR/SUCCESS --}}
    @if(session('error'))
        <div class="border-0 border-4 shadow-sm alert alert-danger alert-dismissible fade show rounded-4 border-start border-danger">
            <i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('success'))
        <div class="border-0 border-4 shadow-sm alert alert-success alert-dismissible fade show rounded-4 border-start border-success">
            <i class="bi bi-check-circle-fill me-2 text-success"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="mb-4 row g-3">
        {{-- KOLOM NOMOR BATCH --}}
        <div class="col-md-5">
            <div class="flex-row p-3 border-0 shadow-sm card rounded-4 h-100 d-flex align-items-center">
                <div class="p-3 bg-primary bg-opacity-10 rounded-3 me-3 text-primary"><i class="bi bi-pc-display fs-3"></i></div>
                <div>
                    <div class="small text-muted fw-bold">Nomor Batch Import</div>
                    <h5 class="mb-0 fw-bold">{{ $batch->batch_number }}</h5>
                    @php
                        $statusColor = 'secondary';
                        $statusName = strtoupper($batch->status);
                        if($batch->status == 'waiting_approval') { $statusColor = 'warning text-dark'; $statusName = 'MENUNGGU APPROVAL'; }
                        if($batch->status == 'approved') { $statusColor = 'success'; $statusName = 'DISETUJUI (SAH)'; }
                        if($batch->status == 'rejected') { $statusColor = 'danger'; $statusName = 'DITOLAK'; }
                    @endphp
                    <span class="badge bg-{{ $statusColor }}">Status: {{ $statusName }}</span>
                </div>
            </div>
        </div>

        {{-- KOLOM LAMPIRAN --}}
        <div class="col-md-4">
            <div class="p-3 border-0 shadow-sm card rounded-4 h-100 d-flex flex-column justify-content-center">
                <div class="mb-2 small text-muted fw-bold">Dokumen Pendukung (BAST / PO)</div>
                @if($batch->support_doc)
                    <div>
                        <a href="{{ asset('storage/' . $batch->support_doc) }}" target="_blank" class="p-2 border shadow-sm badge bg-light text-dark text-decoration-none">
                            <i class="bi bi-paperclip me-1"></i> Lihat Dokumen Dilampirkan
                        </a>
                    </div>
                @else
                    <span class="small text-muted fst-italic">Tidak ada dokumen dilampirkan.</span>
                @endif
            </div>
        </div>

        {{-- KOLOM TOMBOL AKSI --}}
        <div class="col-md-3">
            <div class="p-3 text-center text-white border-0 shadow-sm card rounded-4 h-100 bg-dark d-flex flex-column justify-content-center">
                @php
                    $errCount = $batch->details->where('is_valid', false)->count();

                    // Cek giliran persetujuan
                    $currentApproval = \App\Models\DocumentApproval::with('role')
                        ->where('document_id', $batch->id)
                        ->where('document_type', get_class($batch))
                        ->where('status', 'PENDING')
                        ->orderBy('step_order', 'asc')
                        ->first();

                    // Hak Akses (Approver atau Super Admin)
                    $isApprover = false;
                    if ($currentApproval && auth()->check()) {
                        $user = auth()->user();
                        $isApprover = $user->hasRole($currentApproval->role->name) ||
                                      $user->hasRole('Super Admin') ||
                                      $user->hasRole('Super Administrator') ||
                                      $user->id === 1;
                    }
                @endphp

                {{-- KONDISI 1: DRAFT / REJECTED --}}
                @if(in_array(strtolower($batch->status), ['draft', 'rejected']))

                    <form id="formSubmitApproval" action="{{ route('fixed-assets.submit_approval', $batch->id) }}" method="POST" class="w-100">
                        @csrf
                        <button type="button" id="btnSubmitApproval" class="mb-2 shadow-sm btn btn-warning w-100 fw-bold rounded-pill" {{ $errCount > 0 ? 'disabled' : '' }}>
                            Ajukan Approval <i class="bi bi-send-check ms-1"></i>
                        </button>
                    </form>

                    <form id="formCancelDraft" action="{{ route('fixed-assets.cancel_import', $batch->id) }}" method="POST" class="w-100">
                        @csrf @method('DELETE')
                        <button type="button" id="btnCancelDraft" class="border-2 btn btn-outline-danger w-100 fw-bold rounded-pill small">Batalkan Draft</button>
                    </form>

                {{-- KONDISI 2: WAITING APPROVAL & USER ADALAH ATASAN --}}
                @elseif(strtolower($batch->status) === 'waiting_approval' && $isApprover)

                    <div class="mb-2 small text-warning fw-bold"><i class="bi bi-exclamation-circle-fill me-1"></i> Menunggu Keputusan Anda</div>

                    <form action="{{ route('fixed-assets.decide', $batch->id) }}" method="POST" class="mb-2 w-100">
                        @csrf
                        <input type="hidden" name="action" value="APPROVE">
                        <button type="button" class="shadow-sm btn btn-success w-100 fw-bold rounded-pill btn-approve-final">
                            <i class="bi bi-check-circle-fill me-1"></i> Setujui (Approve)
                        </button>
                    </form>

                    <form action="{{ route('fixed-assets.decide', $batch->id) }}" method="POST" class="w-100">
                        @csrf
                        <input type="hidden" name="action" value="REJECT">
                        <button type="button" class="border-2 btn btn-outline-danger w-100 fw-bold rounded-pill small btn-reject-final">
                            <i class="bi bi-x-circle me-1"></i> Tolak (Reject)
                        </button>
                    </form>

                {{-- KONDISI 3: WAITING APPROVAL TAPI USER BUKAN ATASAN --}}
                @elseif(strtolower($batch->status) === 'waiting_approval' && !$isApprover)

                    <div class="mb-2 display-4 text-warning"><i class="bi bi-hourglass-split"></i></div>
                    <div class="small fw-bold">Menunggu Persetujuan</div>
                    <div class="text-white-50" style="font-size: 0.75rem;">Giliran: <strong>{{ strtoupper($currentApproval->role->name ?? 'Atasan') }}</strong></div>

                {{-- KONDISI 4: APPROVED --}}
                @elseif(strtolower($batch->status) === 'approved')

                    <div class="mb-2 display-4 text-success"><i class="bi bi-check-circle-fill"></i></div>
                    <h6 class="mb-0 fw-bold text-success">TELAH DISETUJUI</h6>
                    <div class="text-white-50" style="font-size: 0.75rem;">Aset telah masuk ke Buku Utama</div>

                @endif

            </div>
        </div>
    </div>

    {{-- TABEL DATA --}}
    <div class="border-0 border-4 shadow-sm card rounded-4 border-top border-warning">
        <div class="py-3 bg-white card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold">Data Aset dari Excel ({{ $batch->details->count() }} Baris)</h6>
            <span class="badge bg-{{ $errCount > 0 ? 'danger' : 'success' }} rounded-pill px-3 py-2">
                <i class="bi bi-{{ $errCount > 0 ? 'exclamation-triangle' : 'check-circle' }}-fill me-1"></i>
                {{ $errCount > 0 ? $errCount . ' Error Ditemukan (Hapus Baris Error)' : 'Semua Data Valid' }}
            </span>
        </div>
        <div class="p-0 card-body table-responsive table-container">
            <table class="table mb-0 align-middle table-hover table-staging" style="min-width: 1400px;">
                <thead class="text-uppercase small text-muted">
                    <tr>
                        <th class="ps-4">No</th>
                        <th>Katalog Item</th>
                        <th>Nama Fisik Aset & S/N</th>
                        <th>Nilai & Tgl Beli</th>
                        <th>Penempatan & Milik</th>
                        <th>Status Validasi Sistem</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($batch->details as $index => $d)
                    <tr class="{{ $d->is_valid ? 'is-valid' : 'is-invalid' }}">
                        <td class="ps-4 fw-bold text-secondary">{{ $index+1 }}</td>

                        {{-- IDENTITAS MASTER BARANG --}}
                        <td>
                            @if($d->kode_barang)
                                <span class="badge bg-primary">{{ $d->kode_barang }}</span>
                            @else
                                <span class="badge bg-warning text-dark"><i class="bi bi-stars"></i> Auto-Create Item</span>
                            @endif
                        </td>

                        {{-- NAMA FISIK ASET --}}
                        <td>
                            <div class="fw-bold text-dark">{{ $d->nama_spesifik_aset ?: '(Sesuai Katalog)' }}</div>
                            @if($d->serial_number)<div class="small text-muted"><i class="bi bi-upc-scan me-1"></i> S/N: {{ $d->serial_number }}</div>@endif
                            @if($d->label_akuntansi)<div class="small text-muted"><i class="bi bi-tag me-1"></i> Akuntansi: {{ $d->label_akuntansi }}</div>@endif
                        </td>

                        {{-- HARGA & TANGGAL --}}
                        <td>
                            <div class="fw-bold text-success">{{ $d->mata_uang }} {{ number_format((float)$d->harga_beli, 0, ',', '.') }}</div>
                            <div class="small text-muted"><i class="bi bi-calendar-check me-1"></i> {{ $d->tanggal_perolehan ?: '-' }}</div>
                        </td>

                        {{-- PENEMPATAN --}}
                        <td>
                            <div class="small fw-bold text-dark"><i class="bi bi-building"></i> {{ $d->nama_pt }}</div>
                            <div class="small text-muted"><i class="bi bi-box-seam"></i> {{ $d->nama_gudang }}</div>
                            <div class="mt-1">
                                <span class="badge bg-secondary" style="font-size: 0.65rem;">{{ $d->status_aset }}</span>
                                @if($d->nama_peminjam) <span class="badge bg-info text-dark" style="font-size: 0.65rem;"><i class="bi bi-person"></i> {{ $d->nama_peminjam }}</span> @endif
                            </div>
                        </td>

                        {{-- VALIDASI --}}
                        <td class="pe-4">
                            @if($d->is_valid)
                                <span class="text-success small fw-bold"><i class="bi bi-check-circle-fill me-1"></i> Data Aman</span>
                            @else
                                <span class="text-danger small fw-bold"><i class="bi bi-x-circle-fill me-1"></i> Error:</span>
                                <div class="mt-1 small text-danger" style="font-size: 0.75rem;">{{ $d->validation_error }}</div>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {

        // SWEETALERT AJUKAN APPROVAL
        const btnSubmit = document.getElementById('btnSubmitApproval');
        if (btnSubmit) {
            btnSubmit.addEventListener('click', function() {
                Swal.fire({
                    title: 'Ajukan ke Atasan?',
                    text: "Pastikan data Excel sudah benar dan tidak ada yang error.",
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#ffc107',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Ya, Ajukan!',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({ title: 'Memproses...', text: 'Mengirim notifikasi ke Atasan.', icon: 'info', showConfirmButton: false });
                        document.getElementById('formSubmitApproval').submit();
                    }
                });
            });
        }

        // SWEETALERT BATAL DRAFT
        const btnCancel = document.getElementById('btnCancelDraft');
        if (btnCancel) {
            btnCancel.addEventListener('click', function() {
                Swal.fire({
                    title: 'Batalkan Pengajuan?',
                    text: "Semua baris data & lampiran akan dihapus permanen.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonText: 'Batal',
                    confirmButtonText: 'Ya, Hapus'
                }).then((r) => {
                    if(r.isConfirmed) {
                        Swal.fire({ title: 'Menghapus...', text: 'Membersihkan data karantina.', icon: 'info', showConfirmButton: false });
                        document.getElementById('formCancelDraft').submit();
                    }
                });
            });
        }

        // SWEETALERT PERSETUJUAN ATASAN (APPROVE)
        document.querySelectorAll('.btn-approve-final').forEach(btn => {
            btn.addEventListener('click', function() {
                const form = this.closest('form');
                Swal.fire({
                    title: 'Mengesahkan Import Aset?',
                    text: "Aset akan dipindahkan ke Buku Utama, dan sistem akan meng-Generate Item Baru (jika kode dikosongkan).",
                    icon: 'success',
                    showCancelButton: true,
                    confirmButtonColor: '#198754',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Ya, Sahkan Aset!',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Mengesahkan...',
                            text: 'Sistem sedang bekerja memindahkan aset dan membuat item baru.',
                            icon: 'info',
                            showConfirmButton: false,
                            allowOutsideClick: false
                        });
                        form.submit();
                    }
                });
            });
        });

        // SWEETALERT PENOLAKAN ATASAN (REJECT)
        document.querySelectorAll('.btn-reject-final').forEach(btn => {
            btn.addEventListener('click', function() {
                const form = this.closest('form');
                Swal.fire({
                    title: 'Tolak Pengajuan?',
                    text: "Berkas akan dikembalikan ke status Draft untuk diperbaiki.",
                    icon: 'error',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Ya, Tolak!',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Menolak...',
                            text: 'Mengembalikan ke staff.',
                            icon: 'info',
                            showConfirmButton: false
                        });
                        form.submit();
                    }
                });
            });
        });

    });
</script>
@endpush
@endsection
