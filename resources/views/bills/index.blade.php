@extends('layouts.app')

@push('css')
    <style>
        .table-hover tbody tr:hover { background-color: #f8f9fa; transition: background-color 0.2s ease; }
        .dropdown-item.text-danger:hover { background-color: #dc3545; color: white !important; }
        .overdue-row { border-left: 4px solid #dc3545; }
        .normal-row { border-left: 4px solid transparent; }
    </style>
@endpush

@section('content')
<div class="container py-4">

    {{-- HEADER --}}
    <div class="mb-4 d-flex justify-content-between align-items-end">
        <div>
            <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-wallet2 me-2 text-primary"></i>Daftar Tagihan Opex</h4>
            <p class="mb-0 text-muted small">Pantau seluruh tagihan operasional, vendor, dan status pembayaran.</p>
        </div>
        <a href="{{ route('bills.create') }}" class="px-4 shadow-sm btn btn-primary rounded-pill fw-bold hover-shadow">
            <i class="bi bi-plus-lg me-1"></i> Buat Tagihan Baru
        </a>
    </div>

    {{-- CARD FILTER --}}
    <div class="mb-4 border-0 shadow-sm card rounded-4 bg-light">
        <div class="p-3 card-body">
            <form action="{{ route('bills.index') }}" method="GET">
                <div class="row g-2 align-items-end">
                    {{-- Filter Perusahaan --}}
                    <div class="col-md-3">
                        <label class="mb-1 small fw-bold text-muted" style="font-size: 0.75rem;">PT / PERUSAHAAN</label>
                        <select name="company_id" class="border-0 shadow-sm form-select form-select-sm">
                            <option value="">-- Semua Perusahaan --</option>
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}" {{ request('company_id') == $company->id ? 'selected' : '' }}>
                                    {{ $company->code ? '['.$company->code.'] ' : '' }}{{ $company->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Filter Vendor --}}
                    <div class="col-md-3">
                        <label class="mb-1 small fw-bold text-muted" style="font-size: 0.75rem;">VENDOR / SUPPLIER</label>
                        <input type="text" name="vendor" class="border-0 shadow-sm form-control form-control-sm"
                               placeholder="Ketik nama vendor..." value="{{ request('vendor') }}">
                    </div>

                    {{-- Filter Status (Value menggunakan SLUG) --}}
                    <div class="col-md-2">
                        <label class="mb-1 small fw-bold text-muted" style="font-size: 0.75rem;">STATUS</label>
                        <select name="status" class="border-0 shadow-sm form-select form-select-sm">
                            <option value="">-- Semua Status --</option>
                            <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>DRAFT</option>
                            <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>PENDING</option>
                            <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>APPROVED</option>
                            <option value="partial" {{ request('status') == 'partial' ? 'selected' : '' }}>PARTIAL</option>
                            <option value="paid" {{ request('status') == 'paid' ? 'selected' : '' }}>PAID</option>
                            <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>REJECTED</option>
                        </select>
                    </div>

                    {{-- Global Search --}}
                    <div class="col-md-3">
                        <label class="mb-1 small fw-bold text-muted" style="font-size: 0.75rem;">PENCARIAN</label>
                        <div class="rounded shadow-sm input-group input-group-sm">
                            <span class="bg-white border-0 input-group-text text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="border-0 form-control ps-0"
                                   placeholder="No Tagihan / Deskripsi..." value="{{ request('search') }}">
                        </div>
                    </div>

                    {{-- Tombol Action --}}
                    <div class="col-md-1">
                        <button type="submit" class="shadow-sm btn btn-dark btn-sm w-100 fw-bold"><i class="bi bi-filter"></i> Filter</button>
                    </div>
                </div>

                @if(request()->anyFilled(['company_id', 'vendor', 'status', 'search']))
                    <div class="mt-2 text-end">
                        <a href="{{ route('bills.index') }}" class="text-decoration-none small text-danger fw-bold">
                            <i class="bi bi-x-circle me-1"></i> Reset Filter
                        </a>
                    </div>
                @endif
            </form>
        </div>
    </div>

    {{-- CARD TABEL DATA --}}
    <div class="border-0 shadow-sm card rounded-4">
        <div class="table-responsive" style="padding-bottom: 120px;">
            <table class="table mb-0 align-middle table-hover">
                <thead class="bg-secondary bg-opacity-10">
                    <tr>
                        <th class="px-4 py-3 text-secondary small text-uppercase fw-bold" width="25%">Informasi Tagihan</th>
                        <th class="py-3 text-secondary small text-uppercase fw-bold" width="20%">Vendor & PT</th>
                        <th class="py-3 text-secondary small text-uppercase fw-bold text-end" width="15%">Nominal Tagihan</th>
                        <th class="py-3 text-center text-secondary small text-uppercase fw-bold" width="15%">Jatuh Tempo</th>
                        <th class="py-3 text-center text-secondary small text-uppercase fw-bold" width="15%">Status</th>
                        <th class="px-4 py-3 text-secondary small text-uppercase fw-bold text-end" width="10%">Aksi</th>
                    </tr>
                </thead>
                <tbody class="border-top-0">
                    @forelse($bills as $bill)
                    @php
                        // TARIK DATA DARI RELASI STATUS (Gunakan optional agar tidak error jika NULL)
                        $statusSlug = optional($bill->status)->slug ?? 'unknown';
                        $statusName = optional($bill->status)->name ?? 'UNKNOWN';
                        $statusColor = optional($bill->status)->color ?? 'secondary';

                        // Cek apakah tagihan Overdue
                        $isOverdue = \Carbon\Carbon::parse($bill->due_date)->isPast() && !in_array($statusSlug, ['paid', 'rejected', 'canceled']);
                    @endphp
                    <tr class="{{ $isOverdue ? 'overdue-row bg-danger bg-opacity-10' : 'normal-row' }}">
                        <td class="px-4 py-3">
                            <a href="{{ route('bills.show', $bill->bill_number) }}" class="mb-1 fw-bold text-decoration-none text-primary d-block">
                                {{ $bill->bill_number }}
                            </a>
                            <div class="small text-muted">
                                <i class="bi bi-calendar-check me-1"></i> Tgl: {{ \Carbon\Carbon::parse($bill->invoice_date)->format('d M Y') }}
                            </div>
                            @if($bill->is_recurring)
                                <span class="mt-1 border badge bg-info-subtle text-info-emphasis border-info-subtle" style="font-size: 0.65rem;">
                                    <i class="bi bi-arrow-repeat"></i> Berulang
                                </span>
                            @endif
                        </td>
                        <td class="py-3">
                            <div class="mb-1 fw-bold text-dark text-truncate" style="max-width: 200px;" title="{{ $bill->vendor_name }}">
                                <i class="bi bi-shop text-warning me-1"></i> {{ $bill->vendor_name }}
                            </div>
                            <div class="small text-muted text-truncate" style="max-width: 200px;" title="{{ $bill->company->name ?? '-' }}">
                                <i class="bi bi-building me-1"></i> {{ $bill->company->name ?? '-' }}
                            </div>
                        </td>
                        <td class="py-3 text-end">
                            <div class="fw-bold text-dark fs-6">
                                {{ $bill->currency }} {{ number_format($bill->amount, 0, ',', '.') }}
                            </div>
                        </td>
                        <td class="py-3 text-center">
                            <div class="small fw-semibold {{ $isOverdue ? 'text-danger' : 'text-muted' }}">
                                {{ $bill->due_date ? \Carbon\Carbon::parse($bill->due_date)->format('d M Y') : '-' }}
                            </div>
                            @if($isOverdue)
                                <div class="px-2 mt-1 badge bg-danger" style="font-size: 0.65rem;">OVERDUE</div>
                            @endif
                        </td>
                        <td class="py-3 text-center">
                            {{-- BADGE STATUS SEKARANG LANGSUNG BACA DARI DATABASE! --}}
                            <span class="badge bg-{{ $statusColor }}-subtle text-{{ $statusColor }} border border-{{ $statusColor }} rounded-pill px-3 py-2 text-uppercase">
                                {{ $statusName }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-end">
                            <div class="dropdown">
                                <button class="border shadow-sm btn btn-light btn-sm rounded-circle" type="button" data-bs-toggle="dropdown" data-bs-boundary="window">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="py-2 border-0 shadow dropdown-menu dropdown-menu-end rounded-3">
                                    <li>
                                        <a class="py-2 dropdown-item" href="{{ route('bills.show', $bill->bill_number) }}">
                                            <i class="bi bi-eye me-2 text-primary"></i> Lihat Detail
                                        </a>
                                    </li>
                                    <li>
                                        <a class="py-2 dropdown-item" href="{{ route('bills.print', $bill->bill_number) }}" target="_blank">
                                            <i class="bi bi-printer me-2 text-dark"></i> Cetak Dokumen
                                        </a>
                                    </li>

                                    {{-- AKSI BERDASARKAN SLUG STATUS --}}
                                    @if(in_array($statusSlug, ['pending', 'draft', 'rejected']))
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <a class="py-2 dropdown-item" href="{{ route('bills.edit', $bill->bill_number) }}">
                                                <i class="bi bi-pencil me-2 text-warning"></i> Edit Tagihan
                                            </a>
                                        </li>
                                        <li>
                                            <form action="{{ route('bills.destroy', $bill->bill_number) }}" method="POST" class="form-delete">
                                                @csrf
                                                @method('DELETE')
                                                <button type="button" class="py-2 dropdown-item text-danger btn-delete">
                                                    <i class="bi bi-trash me-2"></i> Hapus
                                                </button>
                                            </form>
                                        </li>
                                    @endif

                                    @if(in_array($statusSlug, ['approved', 'partial']))
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <a class="py-2 dropdown-item fw-bold text-success" href="{{ route('payments.process', $bill->bill_number) }}">
                                                <i class="bi bi-cash-coin me-2"></i> Input Pembayaran
                                            </a>
                                        </li>
                                    @endif
                                </ul>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="py-5 text-center text-muted bg-light">
                            <i class="mb-3 opacity-50 bi bi-inbox fs-1 d-block"></i>
                            <h6 class="fw-bold">Belum Ada Tagihan</h6>
                            <p class="mb-0 small">Tidak ada data tagihan yang ditemukan atau sesuai dengan filter Anda.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($bills->hasPages())
            <div class="py-3 bg-white card-footer border-top rounded-bottom-4">
                {{ $bills->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Konfirmasi Hapus Data
    document.addEventListener('click', function (e) {
        if (e.target.closest('.btn-delete')) {
            const button = e.target.closest('.btn-delete');
            const form = button.closest('.form-delete');

            Swal.fire({
                title: 'Hapus Tagihan?',
                text: "Data tagihan, rincian item, dan lampiran dokumen akan dihapus permanen dan tidak dapat dikembalikan!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-trash3-fill me-1"></i> Ya, Hapus Permanen!',
                cancelButtonText: 'Batal',
                reverseButtons: true,
                borderRadius: '1rem'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Menghapus Data...',
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading() }
                    });
                    form.submit();
                }
            });
        }
    });

    @if(session('success'))
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });
        Toast.fire({ icon: 'success', title: "{{ session('success') }}" });
    @endif

    @if(session('error'))
        Swal.fire({
            icon: 'error',
            title: 'Aksi Ditolak!',
            text: "{{ session('error') }}",
            borderRadius: '1rem'
        });
    @endif
</script>
@endpush
