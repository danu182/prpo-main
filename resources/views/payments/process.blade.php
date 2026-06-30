@extends('layouts.app')

@section('content')

{{-- HEADER & TOMBOL KEMBALI --}}
<div class="mb-4 d-flex align-items-center justify-content-between">
    <div class="d-flex align-items-center">
        <a href="{{ route('payments.index') }}" class="border shadow-sm btn btn-light rounded-circle me-3">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h4 class="mb-0 fw-bold text-dark">Proses Pembayaran</h4>
            <div class="text-muted small">Input pembayaran untuk: <span class="badge bg-secondary">{{ $bill->bill_number }}</span></div>
        </div>
    </div>
    {{-- Status Badge di Header --}}
    <div>
        @if($bill->status == 'PAID')
            <span class="px-3 py-2 badge bg-success rounded-pill"><i class="bi bi-check-circle me-1"></i> LUNAS</span>
        @elseif($bill->status == 'PARTIAL')
            <span class="px-3 py-2 badge bg-warning text-dark rounded-pill"><i class="bi bi-clock-history me-1"></i> CICILAN</span>
        @else
            <span class="px-3 py-2 badge bg-info text-primary rounded-pill"><i class="bi bi-plus-circle me-1"></i> BARU</span>
        @endif
    </div>
</div>

@if(session('error'))
    <div class="mb-4 alert alert-danger rounded-4">
        <i class="bi bi-exclamation-triangle me-2"></i> {{ session('error') }}
    </div>
@endif


<div class="row g-4">
    {{-- KOLOM KIRI: RINCIAN TAGIHAN --}}
    <div class="col-lg-5">
        <div class="border-0 shadow-sm card rounded-4">
            <div class="py-3 bg-white card-header border-bottom-0">
                <h6 class="mb-0 fw-bold text-secondary"><i class="bi bi-receipt me-2"></i>Rincian Tagihan</h6>
            </div>
            <div class="pt-0 card-body">
                <div class="p-3 mb-3 border-0 rounded-3 bg-light">
                    <small class="mb-1 text-uppercase text-muted fw-bold d-block" style="font-size: 0.65rem;">Vendor Penerima</small>
                    <div class="fw-bold fs-5 text-primary">{{ $bill->vendor_name }}</div>
                    <div class="small text-muted">{{ $bill->title }}</div>
                </div>

                <div class="mb-4 border rounded table-responsive">
                    <div class="mb-4 border rounded shadow-sm table-responsive">
                    <table class="table mb-0 align-middle table-sm small">
                        <thead class="bg-light text-muted">
                            <tr>
                                <th class="py-2 ps-3">Deskripsi Item / Biaya</th>
                                <th class="py-2 text-end pe-3">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- 1. Looping Item Utama --}}
                            @foreach($bill->items as $item)
                            <tr>
                                <td class="py-2 ps-3">
                                    <span class="fw-bold text-dark">{{ $item->name }}</span>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary ms-1">x{{ (int)$item->qty }}</span>
                                    @if($item->description)
                                        <div class="text-muted" style="font-size: 0.7rem;">{{ $item->description }}</div>
                                    @endif
                                </td>
                                <td class="py-2 text-end fw-bold text-dark pe-3">{{ $bill->currency }} {{ number_format($item->amount, 0, ',', '.') }}</td>
                            </tr>
                            @endforeach

                            {{-- 2. Looping Biaya Tambahan (Bila Ada) --}}
                            @if($bill->charges->count() > 0)
                                @foreach($bill->charges as $charge)
                                <tr>
                                    <td class="py-2 ps-3 text-muted" style="font-size: 0.75rem;">
                                        <i class="bi bi-plus-circle me-1"></i> {{ optional($charge->chargeType)->name ?? 'Biaya Tambahan' }}
                                        @if($charge->note) ({{ $charge->note }}) @endif
                                    </td>
                                    <td class="py-2 text-end text-muted pe-3" style="font-size: 0.75rem;">
                                        + {{ $bill->currency }} {{ number_format($charge->amount, 0, ',', '.') }}
                                    </td>
                                </tr>
                                @endforeach
                            @endif

                            {{-- 3. Looping Potongan Ekstra (Bila Ada) --}}
                            @if($bill->discounts->count() > 0)
                                @foreach($bill->discounts as $discount)
                                <tr>
                                    <td class="py-2 ps-3 text-danger" style="font-size: 0.75rem;">
                                        <i class="bi bi-dash-circle me-1"></i> {{ optional($discount->discountType)->name ?? 'Potongan Ekstra' }}
                                        @if($discount->note) ({{ $discount->note }}) @endif
                                    </td>
                                    <td class="py-2 text-end text-danger pe-3" style="font-size: 0.75rem;">
                                        - {{ $bill->currency }} {{ number_format($discount->amount, 0, ',', '.') }}
                                    </td>
                                </tr>
                                @endforeach
                            @endif
                        </tbody>
                        <tfoot class="bg-primary bg-opacity-10 fw-bold text-primary">
                            <tr>
                                <td class="py-3 ps-3 text-uppercase" style="font-size: 0.8rem;">TOTAL TAGIHAN BERSIH</td>
                                <td class="py-3 text-end fs-6 pe-3">{{ $bill->currency }} {{ number_format($bill->amount, 0, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                </div>

                @php
                    $totalPaid = $bill->payments->sum('amount_paid');
                    $remaining = $bill->amount - $totalPaid;
                    $percent = $bill->amount > 0 ? ($totalPaid / $bill->amount) * 100 : 0;
                @endphp

                <div class="p-3 bg-white border shadow-sm rounded-4">
                    <div class="mb-2 d-flex justify-content-between align-items-center">
                        <span class="small fw-bold text-muted">Sudah Dibayar</span>
                        <span class="fw-bold text-success">{{ $bill->currency }} {{ number_format($totalPaid, 0, ',', '.') }}</span>
                    </div>
                    <div class="mb-3 d-flex justify-content-between align-items-center">
                        <span class="small fw-bold text-muted">Sisa Kekurangan</span>
                        <span class="fw-bold text-danger fs-5">{{ $bill->currency }} {{ number_format($remaining, 0, ',', '.') }}</span>
                    </div>
                    <div class="progress rounded-pill" style="height: 10px;">
                        <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" role="progressbar" style="width: {{ $percent }}%"></div>
                    </div>
                    <div class="mt-2 text-center small text-muted">Progres Pelunasan: {{ round($percent) }}%</div>
                </div>

                {{-- Letakkan di resources/views/payments/process.blade.php --}}

                <div class="mb-4 border-0 shadow-sm card rounded-4">
                    <div class="card-body">
                        <h6 class="mb-3 fw-bold"><i class="bi bi-filter me-2"></i>Filter Cetak Rekap</h6>
                        <form action="{{ route('payments.statement.print', $bill->bill_number) }}" method="GET" target="_blank">
                            <div class="row g-2">
                                <div class="col-md-5">
                                    <label class="small text-muted">Dari Tanggal</label>
                                    <input type="date" name="start_date" class="form-control form-control-sm" value="{{ date('Y-m-01') }}">
                                </div>
                                <div class="col-md-5">
                                    <label class="small text-muted">Sampai Tanggal</label>
                                    <input type="date" name="end_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}">
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-dark btn-sm w-100 rounded-pill">
                                        <i class="bi bi-printer"></i> Cetak
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>




                {{-- audit trail star --}}
                <div class="mt-4 border-0 shadow-sm card rounded-4">
                <div class="py-3 bg-white card-header">
                    <h6 class="mb-0 fw-bold text-secondary"><i class="bi bi-journal-text me-2"></i>Audit Trail (Log Aktivitas)</h6>
                </div>
                <div class="card-body">
                <div class="timeline-small">
                    @php
                        // 🔥 PERBAIKAN: Gunakan $bill->id, BUKAN $bill->bill_number
                        $logs = \App\Models\History::where('record_type', \App\Models\BillRequest::class)
                                    ->where('record_id', $bill->id)
                                    ->latest()
                                    ->get();
                    @endphp

                @forelse($logs as $log)
                    <div class="mb-3 d-flex">
                        <div class="text-center me-3" style="width: 50px;">
                            @if(str_contains($log->action, 'VOID'))
                                <i class="bi bi-x-circle-fill text-danger fs-5"></i>
                            @else
                                <i class="bi bi-check-circle-fill text-success fs-5"></i>
                            @endif
                            <div class="mx-auto vr h-100"></div>
                        </div>
                        <div>
                            <div class="fw-bold small">{{ $log->action }}</div>
                            <div class="text-muted" style="font-size: 0.75rem;">
                                {{ $log->note }} <br>
                                <span class="text-primary fw-bold">{{ $log->user->name ?? 'System' }}</span>
                                • {{ $log->created_at->format('d M Y H:i') }} WIB
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="italic text-center text-muted small">Belum ada riwayat aktivitas.</p>
                @endforelse
            </div>
        </div>
    </div>
                {{-- audit trail end --}}


            </div>
        </div>
    </div>



    {{-- KOLOM KANAN: FORM INPUT & RIWAYAT --}}
    <div class="col-lg-7">
        <div class="border-0 shadow-sm card rounded-4">
            <div class="py-3 bg-white card-header border-bottom-0">
                <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-plus-circle me-2"></i>Form Input Pembayaran</h6>
            </div>
            <div class="p-4 pt-0 card-body">
                @if($remaining <= 0)
                    <div class="py-5 text-center">
                        <div class="mb-3 display-1 text-success"><i class="bi bi-check-circle-fill"></i></div>
                        <h4 class="fw-bold text-dark">Tagihan Lunas!</h4>
                        <p class="text-muted">Semua kewajiban pembayaran telah selesai.</p>
                        <a href="{{ route('payments.index') }}" class="px-4 btn btn-outline-primary rounded-pill">Kembali</a>
                    </div>
                @else
                    {{-- PERBAIKAN: Menambahkan id="formPembayaran" --}}

                    {{-- Tambahkan ini untuk melihat error validasi --}}
                    @if ($errors->any())
                        <div class="mb-4 alert alert-danger alert-dismissible fade show rounded-4" role="alert">
                            <ul class="mb-0 small">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    <form action="{{ route('payments.store', $bill->bill_number) }}" method="POST" enctype="multipart/form-data" id="formPembayaran">
                        @csrf


                        {{-- 2. Tampilkan Alert Error yang Menarik --}}
                        @if ($errors->any())
                            <div class="mb-4 border-0 shadow-sm alert alert-danger rounded-4">
                                <div class="d-flex">
                                    <i class="bi bi-exclamation-octagon-fill fs-4 me-3"></i>
                                    <div>
                                        <div class="fw-bold">Gagal Menyimpan Data:</div>
                                        <ul class="mb-0 small">
                                            @foreach ($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label fw-bold small text-uppercase">Dibayarkan Oleh PT <span class="text-danger">*</span></label>
                                <select name="paid_by_company_id" class="form-select border-primary bg-light" required>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}" {{ $company->id == $bill->company_id ? 'selected' : '' }}>
                                            {{ $company->name }} {{ $company->code ? '('.$company->code.')' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-uppercase">Nominal Bayar <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="text-white input-group-text bg-primary fw-bold">{{ $bill->currency }}</span>
                                    <input type="number" name="amount_paid" class="form-control fw-bold" value="{{ $remaining }}" max="{{ $remaining }}" step="any" required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-uppercase">Tanggal Transaksi <span class="text-danger">*</span></label>
                                <input type="date" name="payment_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-bold small text-uppercase">Metode Pembayaran <span class="text-danger">*</span></label>
                                <select name="payment_method_id" id="paymentMethodSelect" class="form-select" required>
                                    <option value="">-- Pilih Metode --</option>
                                    @foreach($paymentMethods as $method)
                                        <option value="{{ $method->id }}" data-req="{{ $method->require_reference }}">
                                            {{ $method->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-bold small text-uppercase">
                                    No. Ref / Kode Transaksi <span id="refRequiredStar" class="text-danger d-none">*</span>
                                </label>
                                <input type="text" name="transaction_reference" id="transactionRefInput" class="form-control" placeholder="No. bukti transfer/cek">
                            </div>

                            {{-- 🔥 PERBAIKAN: Form Upload Dinamis dengan Keterangan 🔥 --}}
                            <div class="col-md-12">
                                <label class="form-label fw-bold small text-uppercase">Upload Bukti & Dokumen <span class="text-danger">*</span></label>

                                <div id="dynamicFileContainer">
                                    <div class="mb-2 input-group file-row">
                                        <input type="file" name="payment_proofs[]" class="form-control border-primary bg-light" required>
                                        <input type="text" name="payment_proof_descriptions[]" class="form-control" placeholder="Msl: Bukti Transfer / Faktur..." required>
                                        <button class="btn btn-outline-danger remove-file" type="button" disabled>
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>

                                <button type="button" class="mt-1 btn btn-outline-primary btn-sm rounded-pill fw-bold" id="addFileRow">
                                    <i class="bi bi-plus-lg me-1"></i> Tambah Lampiran Lain
                                </button>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-bold small text-uppercase">Keterangan / Note</label>
                                <textarea name="note" class="form-control" rows="2" placeholder="Catatan..."></textarea>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="d-grid">
                            {{-- Button type="button" agar tidak langsung submit --}}
                            <button type="button" id="btnSubmitProses" class="py-2 shadow-sm btn btn-primary fw-bold rounded-pill">
                                <i class="bi bi-save2 me-2"></i> Simpan Pembayaran
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </div>

        {{-- RIWAYAT PEMBAYARAN --}}
        @if($bill->payments->count() > 0)
        <div class="mt-4">
            <h6 class="mb-3 fw-bold text-dark"><i class="bi bi-clock-history me-2"></i>Riwayat Pembayaran Termin</h6>
            @foreach($bill->payments as $history)
                <div class="mb-3 overflow-hidden border-0 shadow-sm card rounded-4">
                    <div class="py-3 card-body">
                        <div class="row align-items-center">
                            <div class="col-md-5">
                                <div class="mb-1 fw-bold text-dark fs-6">{{ $history->payment_number }}</div>
                                <div class="mb-2 border badge bg-light text-secondary">
                                    {{ $history->paymentMethod->name ?? 'Metode Lain' }}
                                </div>
                                <div class="small text-muted">
                                    <i class="bi bi-calendar-event me-1"></i> {{ \Carbon\Carbon::parse($history->payment_date)->format('d M Y') }}
                                    <i class="bi bi-clock me-1"></i> {{ $history->created_at->format('H:i') }} WIB
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="fw-bold text-success fs-5">{{ $bill->currency }} {{ number_format($history->amount_paid, 0, ',', '.') }}</div>
                                <div class="small text-muted text-truncate">Ref: {{ $history->transaction_reference ?? '-' }}</div>
                                {{-- Menampilkan Lampiran & Keterangan (KONSEP CUSTOM) --}}
                                @php
                                    // Tarik data dari tabel baru
                                    $attachments = \DB::table('payment_attachments')->where('bill_payment_id', $history->id)->get();
                                @endphp

                                @if($attachments->count() > 0)
                                    <div class="pt-2 mt-2 border-top">
                                        <span class="mb-2 d-block small fw-bold text-muted">Lampiran:</span>
                                        @foreach($attachments as $media)
                                            <a href="{{ asset('storage/' . $media->file_path) }}" target="_blank" class="p-2 mb-1 border badge bg-light text-dark text-decoration-none me-1" style="white-space: normal; text-align: left;">
                                                <i class="bi bi-paperclip text-primary"></i> {{ $media->file_name }}
                                                <br><span class="text-muted fw-normal"><i class="bi bi-arrow-return-right"></i> {{ $media->description ?? 'Dokumen Pendukung' }}</span>
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            <div class="col-md-3 text-end">
                                {{-- Tombol Cetak Kuitansi Satuan --}}
                                <a href="{{ route('payments.receipt.print', $history->payment_number) }}" target="_blank" class="btn btn-outline-secondary btn-sm rounded-circle" title="Cetak Kuitansi">
                                    <i class="bi bi-printer"></i>
                                </a>

                                {{-- Tombol Batalkan Pembayaran (Void) --}}
                                <button type="button"
                                        class="p-2 shadow-sm btn btn-outline-danger btn-sm rounded-circle btn-void-payment"
                                        data-id="{{ $history->payment_number }}"
                                        data-number="{{ $history->payment_number }}"
                                        title="Batalkan Pembayaran">
                                    <i class="bi bi-trash-fill"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        @endif
    </div>
</div>

@endsection

@push('css')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endpush


@push('scripts')
{{-- Load SweetAlert2 CDN --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {

        // ============================================================
        // 1. LOGIKA PILIHAN METODE BAYAR (VALIDASI INPUT)
        // ============================================================
        const methodSelect = document.getElementById('paymentMethodSelect');
        const refInput = document.getElementById('transactionRefInput');
        const starRef = document.getElementById('refRequiredStar');

        if (methodSelect) {
            methodSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                // Cek data-req, default '0' jika tidak ada
                const isRequired = (selectedOption.getAttribute('data-req') || '0') === '1';

                if (isRequired) {
                    refInput.setAttribute('required', 'required');
                    if(starRef) starRef.classList.remove('d-none');
                    refInput.placeholder = "Nomor referensi wajib diisi";
                } else {
                    refInput.removeAttribute('required');
                    if(starRef) starRef.classList.add('d-none');
                    refInput.placeholder = "Opsional untuk metode ini";
                }
            });
        }

        // ============================================================
        // 2. LOGIKA TOMBOL SIMPAN (KONFIRMASI SEBELUM SUBMIT)
        // ============================================================
        const btnSubmit = document.getElementById('btnSubmitProses');

        if(btnSubmit) {
            btnSubmit.addEventListener('click', function() {
                const form = document.getElementById('formPembayaran');

                // 1. Cek Validasi HTML5 dulu
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return; // Stop jika tidak valid
                }

                // 2. Tampilkan Konfirmasi
                Swal.fire({
                    title: 'Konfirmasi Pembayaran',
                    text: "Pastikan nominal dan tanggal sudah sesuai.",
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#0d6efd',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Ya, Proses!',
                    cancelButtonText: 'Batal',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        // --- PERUBAHAN UTAMA DI SINI ---

                        // A. Matikan Tombol agar tidak bisa diklik lagi
                        btnSubmit.disabled = true;

                        // B. Ubah Teks Tombol (Opsional, agar user tahu sedang proses)
                        // Menggunakan spinner Bootstrap jika tersedia
                        btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Memproses...';

                        // C. Hapus class shadow/efek hover agar terlihat "mati"
                        btnSubmit.classList.remove('shadow-sm');
                        btnSubmit.classList.add('disabled');

                        // D. Tampilkan SweetAlert Loading (Overlay layar penuh)
                        Swal.fire({
                            title: 'Menyimpan...',
                            text: 'Mohon tunggu, jangan tutup halaman ini.',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            didOpen: () => { Swal.showLoading(); }
                        });

                        // E. Kirim Form
                        form.submit();
                    }
                });
            });
        }

        // ============================================================
        // 3. LOGIKA TOMBOL HAPUS / VOID (POPUP ALASAN)
        // ============================================================
        // Menggunakan Event Delegation di body agar tombol selalu terdeteksi
        document.body.addEventListener('click', function(e) {

            // Cek apakah yang diklik adalah tombol .btn-void-payment atau ikon di dalamnya
            const targetButton = e.target.closest('.btn-void-payment');

            if (targetButton) {
                // PENTING: Matikan aksi default agar tidak refresh/submit otomatis
                e.preventDefault();

                const paymentId = targetButton.dataset.id;
                const paymentNo = targetButton.dataset.number;

                Swal.fire({
                    title: 'Batalkan Pembayaran?',
                    text: `Anda akan membatalkan nomor: ${paymentNo}. Masukkan alasan pembatalan:`,
                    icon: 'warning',
                    input: 'textarea', // Munculkan input text area
                    inputPlaceholder: 'Contoh: Salah input nominal / Transfer gagal...',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545', // Merah
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Ya, Batalkan!',
                    cancelButtonText: 'Kembali',
                    reverseButtons: true,
                    inputValidator: (value) => {
                        if (!value) {
                            return 'Wajib mengisi alasan pembatalan untuk Audit Trail!';
                        }
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Tampilkan Loading
                        Swal.fire({
                            title: 'Memproses Pembatalan...',
                            allowOutsideClick: false,
                            didOpen: () => { Swal.showLoading(); }
                        });

                        // --- MEMBUAT FORM SECARA DINAMIS VIA JAVASCRIPT ---
                        const form = document.createElement('form');
                        form.method = 'POST';

                        // PERBAIKAN PENTING: URL ini harus cocok dengan Route::delete('/{id}') di dalam prefix 'payments'
                        // Hasilnya akan menjadi: domain.com/payments/10
                        // Arahkan URL ke rute destroy yang baru (menggunakan kata /destroy/ di depannya)
                        form.action = `/payments/destroy/${paymentId}`;

                        // 1. CSRF Token (Wajib di Laravel)
                        const csrfToken = document.querySelector('meta[name="csrf-token"]')
                            ? document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            : '{{ csrf_token() }}';

                        const csrfInput = document.createElement('input');
                        csrfInput.type = 'hidden';
                        csrfInput.name = '_token';
                        csrfInput.value = csrfToken;
                        form.appendChild(csrfInput);

                        // 2. Method Spoofing (DELETE)
                        const methodInput = document.createElement('input');
                        methodInput.type = 'hidden';
                        methodInput.name = '_method';
                        methodInput.value = 'DELETE';
                        form.appendChild(methodInput);

                        // 3. Masukkan Alasan (Void Reason)
                        const reasonInput = document.createElement('input');
                        reasonInput.type = 'hidden';
                        reasonInput.name = 'void_reason'; // Nama field harus sama dengan di Controller ($request->void_reason)
                        reasonInput.value = result.value;
                        form.appendChild(reasonInput);

                        // 4. Tempel form ke Body dan Submit
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            }
        });


        // ============================================================
        // 4. LOGIKA FORM UPLOAD FILE DINAMIS
        // ============================================================
        const fileContainer = document.getElementById('dynamicFileContainer');
        const btnAddFile = document.getElementById('addFileRow');

        if (btnAddFile) {
            btnAddFile.addEventListener('click', function() {
                const newRow = document.createElement('div');
                newRow.className = 'mb-2 input-group file-row';
                newRow.innerHTML = `
                    <input type="file" name="payment_proofs[]" class="form-control border-primary bg-light" required>
                    <input type="text" name="payment_proof_descriptions[]" class="form-control" placeholder="Msl: Bukti Transfer / Faktur..." required>
                    <button class="btn btn-danger remove-file" type="button">
                        <i class="bi bi-trash"></i>
                    </button>
                `;
                fileContainer.appendChild(newRow);
            });

            // Delegasi event untuk tombol hapus baris
            fileContainer.addEventListener('click', function(e) {
                if (e.target.closest('.remove-file')) {
                    const row = e.target.closest('.file-row');
                    // Jangan izinkan hapus jika hanya tersisa 1 baris
                    if (fileContainer.querySelectorAll('.file-row').length > 1) {
                        row.remove();
                    }
                }
            });
        }


    });
</script>
@endpush

