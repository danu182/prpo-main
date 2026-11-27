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
            <a href="{{ route('items.import_index') }}" class="mb-2 text-decoration-none text-muted small fw-bold d-inline-block">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Riwayat Import
            </a>
            <h3 class="mb-0 fw-bold"><i class="bi bi-shield-check text-warning me-2"></i> Ruang Karantina Master Barang</h3>
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
                <div class="p-3 bg-primary bg-opacity-10 rounded-3 me-3 text-primary"><i class="bi bi-box-seam fs-3"></i></div>
                <div>
                    <div class="small text-muted fw-bold">Nomor Batch Import</div>
                    <h5 class="mb-0 fw-bold">{{ $batch->batch_number }}</h5>
                    @php
                        $statusColor = 'secondary';
                        $statusName = strtoupper($batch->status);
                        if($batch->status == 'pending') { $statusColor = 'warning text-dark'; $statusName = 'MENUNGGU APPROVAL'; }
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
                <div class="mb-2 small text-muted fw-bold">Dokumen Pendukung</div>
                @if($batch->attachments && $batch->attachments->count() > 0)
                    <div class="flex-wrap gap-1 d-flex">
                        @foreach($batch->attachments as $att)
                            <a href="{{ asset('storage/' . $att->file_path) }}" target="_blank" class="p-2 border shadow-sm badge bg-light text-dark text-decoration-none">
                                <i class="bi bi-paperclip me-1"></i> {{ $att->file_name }}
                            </a>
                        @endforeach
                    </div>
                @elseif(!empty($batch->support_doc))
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
                    $batchStatus = strtolower(trim($batch->status));

                    $currentApproval = \App\Models\DocumentApproval::with('role')
                        ->where('document_id', $batch->id)
                        ->where('document_type', get_class($batch))
                        ->where('status', 'PENDING')
                        ->orderBy('step_order', 'asc')
                        ->first();

                    $isApprover = false;
                    $isSuperAdmin = false;

                    if ($currentApproval && auth()->check()) {
                        $user = auth()->user();
                        $uid = $currentApproval->user_id ?? $currentApproval->approver_id ?? $currentApproval->specific_user_id ?? null;

                        $roles = $user->getRoleNames()->map(fn($role) => strtolower(trim($role)))->toArray();
                        if ($user->id === 1 || in_array('super admin', $roles) || in_array('super administrator', $roles)) {
                            $isSuperAdmin = true;
                            $isApprover = true;
                        }

                        if (!$isSuperAdmin) {
                            if ($uid && (int)$user->id === (int)$uid) {
                                $isApprover = true;
                            } elseif ($currentApproval->role) {
                                $roleTarget = strtolower(trim($currentApproval->role->name));
                                if (in_array($roleTarget, $roles)) {
                                    $isApprover = true;
                                }
                            }
                        }
                    }
                @endphp

                {{-- KONDISI 1: DRAFT / REJECTED --}}
                @if(in_array($batchStatus, ['draft', 'rejected']))
                    <form id="formSubmitApproval" action="{{ route('items.import.submit_approval', $batch->id) }}" method="POST" class="w-100">
                        @csrf
                        <button type="button" id="btnSubmitApproval" class="mb-2 shadow-sm btn btn-warning w-100 fw-bold rounded-pill" {{ $errCount > 0 ? 'disabled' : '' }}>
                            Ajukan Approval <i class="bi bi-send-check ms-1"></i>
                        </button>
                    </form>

                    <form id="formCancelDraft" action="{{ route('items.import_staging.cancel', $batch->id) }}" method="POST" class="w-100">
                        @csrf
                        @method('DELETE')
                        <button type="button" id="btnCancelDraft" class="border-2 btn btn-outline-danger w-100 fw-bold rounded-pill small">
                            <i class="bi bi-trash3-fill me-1"></i> Batalkan Draft
                        </button>
                    </form>

                {{-- KONDISI 2: PENDING & USER ADALAH ATASAN --}}
                @elseif($batchStatus === 'pending' && $isApprover)
                    <div class="mb-2 small text-warning fw-bold">
                        <i class="bi bi-exclamation-circle-fill me-1"></i> Menunggu Keputusan Anda
                        @if($isSuperAdmin) <br><span style="font-size: 10px; color:#fff;">(Super Admin Access)</span> @endif
                    </div>

                    <form action="{{ route('items.import.decide', $batch->id) }}" method="POST" class="mb-2 w-100">
                        @csrf
                        <input type="hidden" name="action" value="APPROVE">
                        <button type="button" class="shadow-sm btn btn-success w-100 fw-bold rounded-pill btn-approve-final">
                            <i class="bi bi-check-circle-fill me-1"></i> Setujui (Approve)
                        </button>
                    </form>

                    <form action="{{ route('items.import.decide', $batch->id) }}" method="POST" class="w-100">
                        @csrf
                        <input type="hidden" name="action" value="REJECT">
                        <button type="button" class="border-2 btn btn-outline-danger w-100 fw-bold rounded-pill small btn-reject-final">
                            <i class="bi bi-x-circle me-1"></i> Tolak (Reject)
                        </button>
                    </form>

                {{-- KONDISI 3: PENDING TAPI USER BUKAN ATASAN --}}
                @elseif($batchStatus === 'pending' && !$isApprover)
                    <div class="mb-2 display-4 text-warning"><i class="bi bi-hourglass-split"></i></div>
                    <div class="small fw-bold">Menunggu Persetujuan</div>
                    <div class="text-white-50" style="font-size: 0.75rem;">Giliran: <strong>{{ strtoupper($currentApproval->role->name ?? 'Atasan') }}</strong></div>

                {{-- KONDISI 4: APPROVED --}}
                @elseif($batchStatus === 'approved')
                    <div class="mb-2 display-4 text-success"><i class="bi bi-check-circle-fill"></i></div>
                    <h6 class="mb-0 fw-bold text-success">TELAH DISETUJUI</h6>
                    <div class="text-white-50" style="font-size: 0.75rem;">Barang telah masuk ke Katalog Utama</div>

                @else
                    <div class="small fw-bold">Status: {{ strtoupper($batchStatus) }}</div>
                @endif
            </div>
        </div>
    </div>

    {{-- TABEL DATA MASTER BARANG --}}
    <div class="border-0 border-4 shadow-sm card rounded-4 border-top border-warning">
        <div class="py-3 bg-white card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold">Data Master Barang dari Excel ({{ $batch->details->count() }} Baris)</h6>
            <span class="px-3 py-2 badge bg-{{ $errCount > 0 ? 'danger' : 'success' }} rounded-pill">
                <i class="bi bi-{{ $errCount > 0 ? 'exclamation-triangle' : 'check-circle' }}-fill me-1"></i>
                {{ $errCount > 0 ? $errCount . ' Error Ditemukan (Perbaiki Baris Merah)' : 'Semua Data Valid' }}
            </span>
        </div>
        <div class="p-0 card-body table-responsive table-container">
            <table class="table mb-0 align-middle table-hover table-staging" style="min-width: 1200px;">
                <thead class="text-uppercase small text-muted">
                    <tr>
                        <th class="ps-4">No</th>
                        <th>Nama Master Barang</th>
                        <th>Kategori</th>
                        <th>Satuan (UOM)</th>
                        <th>Tipe & Pelacakan SN</th>
                        <th>Spesifikasi</th>
                        <th>Status Validasi Sistem</th>
                        @if(in_array($batchStatus, ['draft', 'rejected']))
                            <th class="text-center pe-4">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($batch->details as $index => $d)
                    <tr class="{{ $d->is_valid ? 'is-valid' : 'is-invalid' }}">
                        <td class="ps-4 fw-bold text-secondary">{{ $index + 1 }}</td>

                        {{-- NAMA BARANG --}}
                        <td>
                            <div class="fw-bold text-dark">{{ $d->name ?: '-' }}</div>
                            @php
                                $existingItem = \App\Models\Item::where('name', $d->name)->first();
                            @endphp
                            @if($existingItem)
                                <span class="badge bg-info text-dark" style="font-size: 0.65rem;">Update: {{ $existingItem->code }}</span>
                            @else
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size: 0.65rem;">SKU Baru ({{ 'SKU-' . strtoupper($d->category_code ?: 'UMM') . '-...' }})</span>
                            @endif
                        </td>

                        {{-- KATEGORI --}}
                        <td>
                            <span class="badge bg-light text-dark border fw-bold">{{ strtoupper($d->category_code ?: '-') }}</span>
                        </td>

                        {{-- SATUAN --}}
                        <td>
                            <span class="badge bg-secondary-subtle text-dark fw-bold">{{ strtoupper($d->uom_code ?: '-') }}</span>
                        </td>

                        {{-- TIPE & SERIAL NUMBER --}}
                        <td>
                            <span class="badge bg-dark">{{ strtoupper($d->item_type_code ?: '-') }}</span>
                            @if($d->is_trackable)
                                <span class="badge bg-warning text-dark ms-1"><i class="bi bi-upc-scan me-1"></i>Wajib SN</span>
                            @else
                                <span class="badge bg-light text-muted border ms-1">Non-SN</span>
                            @endif
                        </td>

                        {{-- SPESIFIKASI --}}
                        <td class="small text-muted" style="max-width: 260px;">
                            {{ $d->specification ?: '-' }}
                        </td>

                        {{-- VALIDASI --}}
                        <td>
                            @if($d->is_valid)
                                <span class="text-success small fw-bold"><i class="bi bi-check-circle-fill me-1"></i> Data Aman</span>
                            @else
                                <span class="text-danger small fw-bold"><i class="bi bi-x-circle-fill me-1"></i> Error:</span>
                                <div class="mt-1 small text-danger" style="font-size: 0.75rem;">{{ $d->validation_error }}</div>
                            @endif
                        </td>

                        {{-- TOMBOL EDIT BARIS --}}
                        @if(in_array($batchStatus, ['draft', 'rejected']))
                            <td class="text-center pe-4">
                                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill fw-bold" data-bs-toggle="modal" data-bs-target="#editDetailModal{{ $d->id }}">
                                    <i class="bi bi-pencil-square me-1"></i> Edit
                                </button>
                            </td>
                        @endif
                    </tr>

                    {{-- MODAL EDIT BARIS STAGING --}}
                    @if(in_array($batchStatus, ['draft', 'rejected']))
                    <div class="modal fade" id="editDetailModal{{ $d->id }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <form action="{{ route('items.import_staging.update_detail', $d->id) }}" method="POST" class="modal-content rounded-4 border-0 shadow">
                                @csrf
                                @method('PUT')
                                <div class="modal-header">
                                    <h6 class="modal-title fw-bold">Perbaiki Data Barang (Baris #{{ $index + 1 }})</h6>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold">Nama Barang <span class="text-danger">*</span></label>
                                        <input type="text" name="name" class="form-control" value="{{ $d->name }}" required>
                                    </div>
                                    <div class="row g-2 mb-3">
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold">Kategori <span class="text-danger">*</span></label>
                                            <select name="category_code" class="form-select" required>
                                                @foreach($categories as $cat)
                                                    <option value="{{ $cat->code }}" {{ strtoupper($d->category_code) == strtoupper($cat->code) ? 'selected' : '' }}>
                                                        {{ $cat->code }} - {{ $cat->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold">Satuan <span class="text-danger">*</span></label>
                                            <select name="uom_code" class="form-select" required>
                                                @foreach($uoms as $u)
                                                    <option value="{{ $u->code }}" {{ strtoupper($d->uom_code) == strtoupper($u->code) ? 'selected' : '' }}>
                                                        {{ $u->code }} - {{ $u->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold">Tipe Barang <span class="text-danger">*</span></label>
                                            <select name="item_type_code" class="form-select" required>
                                                @foreach($itemTypes as $it)
                                                    <option value="{{ $it->code }}" {{ strtoupper($d->item_type_code) == strtoupper($it->code) ? 'selected' : '' }}>
                                                        {{ $it->code }} - {{ $it->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold">Wajib Serial Number (SN)? <span class="text-danger">*</span></label>
                                        <select name="is_trackable" class="form-select" required>
                                            <option value="0" {{ !$d->is_trackable ? 'selected' : '' }}>Tidak (Non-SN)</option>
                                            <option value="1" {{ $d->is_trackable ? 'selected' : '' }}>Ya (Wajib Lacak SN)</option>
                                        </select>
                                    </div>
                                    <div class="mb-0">
                                        <label class="form-label small fw-bold">Spesifikasi</label>
                                        <textarea name="specification" class="form-control" rows="2">{{ $d->specification }}</textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-primary rounded-pill fw-bold">Simpan Perubahan</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    @endif

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

        document.querySelectorAll('.btn-approve-final').forEach(btn => {
            btn.addEventListener('click', function() {
                const form = this.closest('form');
                Swal.fire({
                    title: 'Mengesahkan Import?',
                    text: "Barang akan dipindahkan ke Katalog Utama.",
                    icon: 'success',
                    showCancelButton: true,
                    confirmButtonColor: '#198754',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Ya, Sahkan!',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Mengesahkan...',
                            text: 'Sistem sedang bekerja menyimpan katalog master.',
                            icon: 'info',
                            showConfirmButton: false,
                            allowOutsideClick: false
                        });
                        form.submit();
                    }
                });
            });
        });

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

        const btnCancel = document.getElementById('btnCancelDraft');
        if (btnCancel) {
            btnCancel.addEventListener('click', function(e) {
                e.preventDefault();
                Swal.fire({
                    title: 'Batalkan Pengajuan?',
                    text: 'Semua baris data & lampiran karantina ini akan dihapus permanen.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    cancelButtonText: 'Tutup',
                    confirmButtonText: 'Ya, Hapus Draft!'
                }).then((result) => {
                    if(result.isConfirmed) {
                        Swal.fire({
                            title: 'Menghapus...',
                            text: 'Membersihkan data karantina.',
                            icon: 'info',
                            showConfirmButton: false,
                            allowOutsideClick: false
                        });
                        document.getElementById('formCancelDraft').submit();
                    }
                });
            });
        }
    });
</script>
@endpush
@endsection
