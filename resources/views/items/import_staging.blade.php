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

    <div class="mb-4 d-flex justify-content-between align-items-end">
        <div>
            <a href="{{ route('items.import_index') }}" class="mb-2 text-decoration-none text-muted small fw-bold d-inline-block">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Riwayat Import
            </a>
            <h3 class="mb-0 fw-bold"><i class="bi bi-shield-check text-warning me-2"></i> Ruang Karantina & Validasi</h3>
        </div>
    </div>

    <div class="mb-4 row g-3">
        {{-- KOLOM NOMOR BATCH --}}
        <div class="col-md-4">
            <div class="flex-row p-3 border-0 shadow-sm card rounded-4 h-100 d-flex align-items-center">
                <div class="p-3 bg-primary bg-opacity-10 rounded-3 me-3 text-primary"><i class="bi bi-file-text fs-3"></i></div>
                <div>
                    <div class="small text-muted fw-bold">Nomor Dokumen</div>
                    <h5 class="mb-0 fw-bold">{{ $batch->batch_number }}</h5>
                    <span class="badge bg-{{ $batch->statusInfo->color ?? 'secondary' }}">Status: {{ strtoupper($batch->statusInfo->name ?? $batch->status) }}</span>
                </div>
            </div>
        </div>

        {{-- KOLOM LAMPIRAN --}}
        <div class="col-md-5">
            <div class="p-3 border-0 shadow-sm card rounded-4 h-100">
                <div class="mb-2 small text-muted fw-bold">Lampiran Bukti ({{ $batch->attachments->count() }})</div>
                <div class="flex-wrap gap-2 d-flex">
                    @forelse($batch->attachments as $file)
                        <a href="{{ asset('storage/' . $file->file_path) }}" target="_blank" class="p-2 border shadow-sm badge bg-light text-dark text-decoration-none">
                            <i class="bi bi-paperclip me-1"></i> {{ Str::limit($file->file_name, 20) }}
                        </a>
                    @empty
                        <span class="small text-muted fst-italic">Tidak ada lampiran.</span>
                    @endforelse
                </div>
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

                    <form id="formSubmitApproval" action="{{ route('items.import.submit_approval', $batch->id) }}" method="POST" class="w-100">
                        @csrf
                        <button type="button" id="btnSubmitApproval" class="mb-2 shadow-sm btn btn-warning w-100 fw-bold rounded-pill" {{ $errCount > 0 ? 'disabled' : '' }}>
                            Ajukan Approval <i class="bi bi-send-check ms-1"></i>
                        </button>
                    </form>

                    <form id="formCancelDraft" action="{{ route('items.import_staging.cancel', $batch->id) }}" method="POST" class="w-100">
                        @csrf @method('DELETE')
                        <button type="button" id="btnCancelDraft" class="border-2 btn btn-outline-danger w-100 fw-bold rounded-pill small">Batalkan Draft</button>
                    </form>

                {{-- KONDISI 2: PENDING & USER ADALAH ATASAN --}}
                @elseif(strtolower($batch->status) === 'pending' && $isApprover)

                    <div class="mb-2 small text-warning fw-bold"><i class="bi bi-exclamation-circle-fill me-1"></i> Menunggu Keputusan Anda</div>

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
                        <button type="button" class="border-2 btn btn-outline-danger w-100 fw-bold rounded-pill small" onclick="rejectWithReason(this.form)">
                            <i class="bi bi-x-circle me-1"></i> Tolak (Reject)
                        </button>
                    </form>

                {{-- KONDISI 3: PENDING TAPI USER BUKAN ATASAN --}}
                @elseif(strtolower($batch->status) === 'pending' && !$isApprover)

                    <div class="mb-2 display-4 text-warning"><i class="bi bi-hourglass-split"></i></div>
                    <div class="small fw-bold">Menunggu Persetujuan</div>
                    <div class="text-white-50" style="font-size: 0.75rem;">Giliran: <strong>{{ strtoupper($currentApproval->role->name ?? 'Atasan') }}</strong></div>

                {{-- KONDISI 4: APPROVED --}}
                @elseif(strtolower($batch->status) === 'approved')

                    <div class="mb-2 display-4 text-success"><i class="bi bi-check-circle-fill"></i></div>
                    <h6 class="mb-0 fw-bold text-success">TELAH DISETUJUI</h6>
                    <div class="text-white-50" style="font-size: 0.75rem;">Data sudah masuk ke Katalog</div>
                @endif

            </div>
        </div>
    </div>

    {{-- TABEL DATA --}}
    <div class="border-0 border-4 shadow-sm card rounded-4 border-top border-warning">
        <div class="py-3 bg-white card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold">Data dari Excel ({{ $batch->details->count() }} Baris)</h6>
            <span class="badge bg-{{ $errCount > 0 ? 'danger' : 'success' }} rounded-pill px-3 py-2">
                <i class="bi bi-{{ $errCount > 0 ? 'exclamation-triangle' : 'check-circle' }}-fill me-1"></i>
                {{ $errCount > 0 ? $errCount . ' Error Ditemukan' : 'Semua Data Valid' }}
            </span>
        </div>
        <div class="p-0 card-body table-responsive table-container">
            <table class="table mb-0 align-middle table-hover table-staging" style="min-width: 1200px;">
                <thead class="text-uppercase small text-muted">
                    <tr>
                        <th class="ps-4">No</th>
                        <th>Nama Barang</th>
                        <th class="text-center">Kategori</th>
                        <th class="text-center">Satuan</th>
                        <th class="text-center">Tipe</th>
                        <th>Status Validasi</th>
                        <th class="text-center pe-4">Opsi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($batch->details as $index => $d)
                    <tr class="{{ $d->is_valid ? 'is-valid' : 'is-invalid' }}">
                        <td class="ps-4 fw-bold text-secondary">{{ $index+1 }}</td>
                        <td>
                            <div class="fw-bold text-dark">{{ $d->name }}</div>
                            @if($d->specification)<div class="small text-muted text-truncate" style="max-width: 250px;">{{ $d->specification }}</div>@endif
                        </td>
                        <td class="text-center"><span class="badge bg-secondary">{{ $d->category_code }}</span></td>
                        <td class="text-center"><span class="badge bg-primary">{{ $d->uom_code }}</span></td>

                        <td class="text-center">
                            <span title="Tipe Barang: {{ $d->item_type_code }}" class="fs-5 me-1">
                                @if($d->item_type_code == 'STK') 📦
                                @elseif($d->item_type_code == 'JSA') 🛠️
                                @else ❓ @endif
                            </span>
                            @if($d->is_trackable) <span title="Dilacak S/N" class="fs-5">🔍</span> @endif
                        </td>

                        <td>
                            <span class="text-{{ $d->is_valid ? 'success' : 'danger' }} small fw-bold">
                                <i class="bi bi-{{ $d->is_valid ? 'check-circle' : 'x-circle' }} me-1"></i> {{ $d->is_valid ? 'OK' : $d->validation_error }}
                            </span>
                        </td>
                        <td class="text-center pe-4">
                            @if(in_array(strtolower($batch->status), ['draft', 'rejected']))
                                <button class="px-3 btn btn-sm btn-outline-primary rounded-pill btn-edit-row"
                                    data-id="{{ $d->id }}" data-name="{{ $d->name }}" data-cat="{{ $d->category_code }}"
                                    data-uom="{{ $d->uom_code }}" data-spec="{{ $d->specification }}"
                                    data-type="{{ $d->item_type_code }}" data-trc="{{ $d->is_trackable }}">
                                    <i class="bi bi-pencil-square"></i> Edit
                                </button>
                            @else
                                <span class="small text-muted"><i class="bi bi-lock"></i> Terkunci</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- MODAL EDIT DATA KARANTINA --}}
<div class="modal fade" id="modalEditRow" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="border-0 shadow-lg modal-content rounded-4">
            <form id="formEditRow" method="POST">
                @csrf @method('PUT')
                <div class="text-white border-0 modal-header bg-primary rounded-top-4">
                    <h5 class="modal-title fw-bold">Perbaiki Data</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="p-4 modal-body">
                    <div class="mb-3"><label class="form-label small fw-bold">Nama Barang</label><input type="text" name="name" id="edit_name" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label small fw-bold">Spesifikasi</label><textarea name="specification" id="edit_spec" class="form-control" rows="2"></textarea></div>
                    <div class="mb-3 row g-3">
                        <div class="col-6">
                            <label class="small fw-bold">Kategori</label>
                            <select name="category_code" id="edit_cat" class="form-select" required>
                                @foreach($categories as $c)<option value="{{ $c->code }}">{{ $c->name }}  || {{ $c->code }} </option>@endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold">Satuan</label>
                            <select name="uom_code" id="edit_uom" class="form-select" required>
                                @foreach($uoms as $u)<option value="{{ $u->code }}">{{ $u->code }} || {{ $u->name }}</option>@endforeach
                            </select>
                        </div>
                    </div>

                    <div class="p-3 border bg-light rounded-3">
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="x-small fw-bold">Tipe Barang?</label>
                                <select name="item_type_code" id="edit_type" class="form-select form-select-sm" required>
                                    <option value="">-- Pilih --</option>
                                    @foreach($itemTypes as $type)
                                        <option value="{{ $type->code }}">{{ $type->code }} ({{ $type->name }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="x-small fw-bold">Lacak Fisik (S/N)?</label>
                                <select name="is_trackable" id="edit_trc" class="form-select form-select-sm" required>
                                    <option value="0">➖ Tidak</option>
                                    <option value="1">🔍 Ya</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="border-0 modal-footer bg-light"><button type="submit" class="py-2 shadow-sm btn btn-primary w-100 rounded-pill fw-bold">Simpan & Validasi</button></div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const modalEdit = new bootstrap.Modal(document.getElementById('modalEditRow'), {backdrop: 'static'});

        const optType = document.getElementById('edit_type');
        const optTrack = document.getElementById('edit_trc');

        // BUKA MODAL & ISI DATA
        document.querySelectorAll('.btn-edit-row').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('edit_name').value = this.dataset.name;
                document.getElementById('edit_spec').value = this.dataset.spec || '';
                document.getElementById('edit_cat').value = this.dataset.cat;
                document.getElementById('edit_uom').value = this.dataset.uom;
                optType.value = this.dataset.type;
                optTrack.value = this.dataset.trc;

                document.getElementById('formEditRow').action = `/items/import-staging/detail/${this.dataset.id}`;
                modalEdit.show();
            });
        });

        // 🔥 Logika Pintar Jasa vs Lacak 🔥
        if (optType && optTrack) {
            optType.addEventListener('change', function() {
                if(this.value === "JSA") optTrack.value = "0";
            });
            optTrack.addEventListener('change', function() {
                if(this.value === "1" && optType.value === "JSA") {
                    optType.value = "STK";
                    alert("Jasa tidak memiliki fisik. Sistem otomatis mengubah Tipe menjadi STK.");
                }
            });
        }

        // SWEETALERT AJUKAN APPROVAL
        const btnSubmit = document.getElementById('btnSubmitApproval');
        if (btnSubmit) {
            btnSubmit.addEventListener('click', function() {
                Swal.fire({
                    title: 'Ajukan ke Atasan?',
                    text: "Data yang diajukan tidak dapat di-edit kembali.",
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
                    title: 'Mengesahkan Master Data?',
                    text: "Data akan dipindahkan dari Karantina ke Katalog Utama dan tidak bisa dikembalikan.",
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
                            text: 'Sistem sedang memproses data ke database utama.',
                            icon: 'info',
                            showConfirmButton: false
                        });
                        form.submit();
                    }
                });
            });
        });
    });

    // LOGIKA PENOLAKAN OLEH ATASAN (Minta Alasan)
    window.rejectWithReason = function(formElement) {
        Swal.fire({
            title: 'Tolak Pengajuan?',
            text: "Berikan alasan penolakan untuk dikembalikan ke staf:",
            input: 'textarea',
            inputPlaceholder: 'Contoh: Ada nama barang yang salah eja...',
            icon: 'error',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Tolak Sekarang',
            cancelButtonText: 'Batal',
            inputValidator: (value) => {
                if (!value) return 'Alasan penolakan wajib diisi!'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const reasonInput = document.createElement('input');
                reasonInput.type = 'hidden';
                reasonInput.name = 'note';
                reasonInput.value = result.value;
                formElement.appendChild(reasonInput);
                formElement.submit();
            }
        });
    };
</script>
@endpush
@endsection
