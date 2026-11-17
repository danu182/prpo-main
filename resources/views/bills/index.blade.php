@extends('layouts.app')

@push('css')
    <style>
        .table-hover tbody tr:hover { background-color: #f8f9fa; transition: background-color 0.2s ease; }
        .dropdown-item.text-danger:hover { background-color: #dc3545; color: white !important; }
        .overdue-row { border-left: 4px solid #dc3545; }
        .normal-row { border-left: 4px solid transparent; }
        .nav-pills .nav-link.active { background-color: #0d6efd; box-shadow: 0 0.125rem 0.25rem rgba(13, 110, 253, 0.4); }
        .nav-pills .nav-link { color: #6c757d; font-weight: 600; border-radius: 50rem; padding: 0.5rem 1.5rem; transition: all 0.3s; }
        .nav-pills .nav-link:hover:not(.active) { background-color: #e9ecef; color: #000; }
        .kpi-card { transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .kpi-card:hover { transform: translateY(-2px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.08)!important; }
    </style>
@endpush

@section('content')
<div class="px-0 py-4 container-fluid">

    {{-- HEADER --}}
    <div class="gap-3 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-end">
        <div>
            <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-wallet2 me-2 text-primary"></i>Daftar Tagihan Opex</h4>
            <p class="mb-0 text-muted small">Pantau seluruh tagihan operasional, vendor, dan status pembayaran.</p>
        </div>
        <a href="{{ route('bills.create') }}" class="px-4 shadow-sm btn btn-primary rounded-pill fw-bold hover-shadow">
            <i class="bi bi-plus-lg me-1"></i> Buat Tagihan Baru
        </a>
    </div>

    {{-- KPI SUMMARY CARDS --}}
    <div class="mb-4 row g-3">
        <div class="col-md-4">
            <div class="border-0 border-4 shadow-sm card rounded-4 border-start border-primary kpi-card h-100">
                <div class="p-3 card-body d-flex align-items-center justify-content-between">
                    <div>
                        <div class="mb-1 text-muted small fw-bold text-uppercase" style="font-size: 0.7rem;">Total Tagihan (Sesuai Filter)</div>
                        <h4 class="mb-0 fw-bolder text-dark">Rp {{ number_format($summary['total_nominal'] ?? 0, 0, ',', '.') }}</h4>
                        <div class="mt-1 small text-muted">{{ $summary['total_count'] ?? 0 }} Dokumen ditemukan</div>
                    </div>
                    <div class="p-3 rounded-circle bg-primary-subtle text-primary">
                        <i class="bi bi-receipt fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="border-0 border-4 shadow-sm card rounded-4 border-start border-warning kpi-card h-100">
                <div class="p-3 card-body d-flex align-items-center justify-content-between">
                    <div>
                        <div class="mb-1 text-muted small fw-bold text-uppercase" style="font-size: 0.7rem;">Menunggu Pembayaran (Unpaid)</div>
                        <h4 class="mb-0 fw-bolder text-warning-emphasis">Rp {{ number_format($summary['total_unpaid'] ?? 0, 0, ',', '.') }}</h4>
                        <div class="mt-1 small text-muted">{{ $summary['count_unpaid'] ?? 0 }} Tagihan belum lunas</div>
                    </div>
                    <div class="p-3 rounded-circle bg-warning-subtle text-warning-emphasis">
                        <i class="bi bi-hourglass-split fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <a href="{{ route('bills.index', array_merge(request()->query(), ['overdue' => request('overdue') == '1' ? null : '1'])) }}" class="text-decoration-none">
                <div class="border-0 border-4 shadow-sm card rounded-4 border-start border-danger kpi-card h-100 {{ request('overdue') == '1' ? 'bg-danger-subtle' : '' }}">
                    <div class="p-3 card-body d-flex align-items-center justify-content-between">
                        <div>
                            <div class="mb-1 text-danger small fw-bold text-uppercase" style="font-size: 0.7rem;">
                                Lewat Jatuh Tempo (Overdue)
                                <span class="badge bg-danger ms-1">{{ request('overdue') == '1' ? 'Filter Aktif' : 'Klik Filter' }}</span>
                            </div>
                            <h4 class="mb-0 fw-bolder text-danger">Rp {{ number_format($summary['total_overdue'] ?? 0, 0, ',', '.') }}</h4>
                            <div class="mt-1 small text-danger-emphasis">{{ $summary['count_overdue'] ?? 0 }} Tagihan terlambat bayar</div>
                        </div>
                        <div class="p-3 text-white rounded-circle bg-danger">
                            <i class="bi bi-exclamation-triangle-fill fs-4"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    {{-- NAV TABS --}}
    <div class="flex-wrap gap-2 mb-3 d-flex justify-content-between align-items-center">
        <ul class="p-2 bg-white border shadow-sm nav nav-pills rounded-pill d-inline-flex">
            <li class="nav-item">
                <a class="nav-link {{ request('tab') != 'recurring' ? 'active' : '' }}" href="{{ route('bills.index', request()->except('tab', 'page')) }}">
                    <i class="bi bi-receipt-cutoff me-1"></i> Semua Tagihan
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request('tab') == 'recurring' ? 'active' : '' }}" href="{{ route('bills.index', array_merge(request()->except('page'), ['tab' => 'recurring'])) }}">
                    <i class="bi bi-arrow-repeat me-1"></i> Langganan Aktif (Recurring)
                </a>
            </li>
        </ul>

        @if(request()->anyFilled(['company_id', 'vendor', 'status', 'search', 'month', 'year', 'start_date', 'end_date', 'overdue', 'bill_type']))
            <a href="{{ route('bills.index', ['tab' => request('tab')]) }}" class="px-3 shadow-sm btn btn-sm btn-outline-danger rounded-pill fw-bold">
                <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Semua Filter
            </a>
        @endif
    </div>

    {{-- CARD FILTER --}}
    <div class="mb-4 border-0 shadow-sm card rounded-4 bg-light">
        <div class="p-3 card-body">
            <form action="{{ route('bills.index') }}" method="GET">
                @if(request()->has('tab')) <input type="hidden" name="tab" value="{{ request('tab') }}"> @endif
                @if(request()->has('overdue')) <input type="hidden" name="overdue" value="{{ request('overdue') }}"> @endif

                {{-- BARIS 1: FILTER ATRIBUT --}}
                <div class="mb-2 row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="mb-1 small fw-bold text-muted" style="font-size: 0.7rem;">PT / PERUSAHAAN</label>
                        <select name="company_id" class="border-0 shadow-sm form-select form-select-sm">
                            <option value="">-- Semua Perusahaan --</option>
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}" {{ request('company_id') == $company->id ? 'selected' : '' }}>
                                    {{ $company->code ? '['.$company->code.'] ' : '' }}{{ $company->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="mb-1 small fw-bold text-muted" style="font-size: 0.7rem;">JENIS TAGIHAN</label>
                        <select name="bill_type" class="border-0 shadow-sm form-select form-select-sm">
                            <option value="">-- Semua Jenis --</option>
                            <option value="parent" {{ request('bill_type') == 'parent' ? 'selected' : '' }}>Induk Berulang</option>
                            <option value="auto" {{ request('bill_type') == 'auto' ? 'selected' : '' }}>Auto-Recurring (Turunan)</option>
                            <option value="manual" {{ request('bill_type') == 'manual' ? 'selected' : '' }}>Manual (Sekali)</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="mb-1 small fw-bold text-muted" style="font-size: 0.7rem;">STATUS</label>
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

                    <div class="col-md-2">
                        <label class="mb-1 small fw-bold text-muted" style="font-size: 0.7rem;">VENDOR / SUPPLIER</label>
                        <input type="text" name="vendor" class="border-0 shadow-sm form-control form-control-sm"
                               placeholder="Nama vendor..." value="{{ request('vendor') }}">
                    </div>

                    <div class="col-md-3">
                        <label class="mb-1 small fw-bold text-muted" style="font-size: 0.7rem;">PENCARIAN CEPAT</label>
                        <div class="rounded shadow-sm input-group input-group-sm">
                            <span class="bg-white border-0 input-group-text text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="border-0 form-control ps-0"
                                   placeholder="No Tagihan / Judul..." value="{{ request('search') }}">
                        </div>
                    </div>
                </div>

                {{-- BARIS 2: FILTER WAKTU --}}
                <div class="pt-2 mt-2 border-top row g-2 align-items-end">
                    <div class="col-md-2">
                        <label class="mb-1 small fw-bold text-primary" style="font-size: 0.7rem;"><i class="bi bi-calendar-event me-1"></i>ACUAN TANGGAL</label>
                        <select name="date_type" class="border-0 shadow-sm form-select form-select-sm fw-semibold text-dark">
                            <option value="invoice_date" {{ request('date_type', 'invoice_date') == 'invoice_date' ? 'selected' : '' }}>Tgl Tagihan (Invoice)</option>
                            <option value="due_date" {{ request('date_type') == 'due_date' ? 'selected' : '' }}>Tgl Jatuh Tempo</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="mb-1 small fw-bold text-muted" style="font-size: 0.7rem;">BULAN</label>
                        <select name="month" id="filter_month" class="border-0 shadow-sm form-select form-select-sm">
                            <option value="">-- Semua Bulan --</option>
                            @php
                                $months = [
                                    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                                    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                                    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
                                ];
                            @endphp
                            @foreach($months as $num => $name)
                                <option value="{{ $num }}" {{ request('month') == $num ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="mb-1 small fw-bold text-muted" style="font-size: 0.7rem;">TAHUN</label>
                        <select name="year" id="filter_year" class="border-0 shadow-sm form-select form-select-sm">
                            <option value="">-- Semua Tahun --</option>
                            @foreach($availableYears as $yr)
                                <option value="{{ $yr }}" {{ request('year') == $yr ? 'selected' : '' }}>{{ $yr }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="mb-1 small fw-bold text-muted" style="font-size: 0.7rem;">DARI TANGGAL</label>
                        <input type="date" name="start_date" id="filter_start_date" class="border-0 shadow-sm form-control form-control-sm" value="{{ request('start_date') }}">
                    </div>

                    <div class="col-md-2">
                        <label class="mb-1 small fw-bold text-muted" style="font-size: 0.7rem;">SAMPAI TANGGAL</label>
                        <input type="date" name="end_date" id="filter_end_date" class="border-0 shadow-sm form-control form-control-sm" value="{{ request('end_date') }}">
                    </div>

                    <div class="col-md-2">
                        <button type="submit" class="shadow-sm btn btn-dark btn-sm w-100 fw-bold">
                            <i class="bi bi-funnel-fill me-1"></i> Terapkan Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- CARD TABEL DATA --}}
    <div class="border-0 shadow-sm card rounded-4">
        <div class="table-responsive" style="padding-bottom: 120px;">
            <table class="table mb-0 align-middle table-hover">
                <thead class="bg-secondary bg-opacity-10">
                    <tr>
                        <th class="px-4 py-3 text-secondary small text-uppercase fw-bold" width="18%">No. Dokumen</th>
                        <th class="py-3 text-secondary small text-uppercase fw-bold" width="22%">Judul Tagihan</th>
                        <th class="py-3 text-secondary small text-uppercase fw-bold" width="18%">Vendor & PT</th>
                        <th class="py-3 text-secondary small text-uppercase fw-bold text-end" width="13%">Nominal</th>
                        <th class="py-3 text-center text-secondary small text-uppercase fw-bold" width="13%">Jatuh Tempo</th>
                        <th class="py-3 text-center text-secondary small text-uppercase fw-bold" width="10%">Status</th>
                        <th class="px-4 py-3 text-secondary small text-uppercase fw-bold text-end" width="6%">Aksi</th>
                    </tr>
                </thead>
                <tbody class="border-top-0">
                    @forelse($bills as $bill)
                    @php
                        $statusSlug = optional($bill->status)->slug ?? 'unknown';
                        $statusName = optional($bill->status)->name ?? 'UNKNOWN';
                        $statusColor = optional($bill->status)->color ?? 'secondary';

                        $dueDate = $bill->due_date ? \Carbon\Carbon::parse($bill->due_date)->startOfDay() : null;
                        $today = now()->startOfDay();
                        $isUnpaid = !in_array($statusSlug, ['paid', 'rejected', 'cancelled']);
                        $isOverdue = $dueDate && $dueDate->lt($today) && $isUnpaid;
                        $daysDiff = $dueDate ? $today->diffInDays($dueDate, false) : null;

                        // Deteksi apakah tagihan ini adalah anak/turunan dari Induk Berulang
                        $billSig = strtolower(trim(($bill->title ?? '') . '|' . ($bill->vendor_name ?? '') . '|' . ($bill->company_id ?? '')));
                        $isAutoChild = !$bill->is_recurring && (
                            !empty($bill->parent_id)
                            || !empty($bill->parent_bill_id)
                            || str_contains(strtolower($bill->notes ?? ''), 'otomatis')
                            || str_contains(strtolower($bill->notes ?? ''), 'recurring')
                            || str_contains(strtolower($bill->description ?? ''), 'otomatis')
                            || (isset($recurringSignatures) && in_array($billSig, $recurringSignatures))
                        );
                    @endphp
                    <tr class="{{ $isOverdue ? 'overdue-row bg-danger bg-opacity-10' : 'normal-row' }}">

                        {{-- KOLOM 1: NOMOR & IDENTITAS JENIS TAGIHAN --}}
                        <td class="px-4 py-3">
                            <a href="{{ route('bills.show', $bill->bill_number) }}" class="mb-1 fw-bold text-decoration-none text-primary d-block">
                                {{ $bill->bill_number }}
                            </a>
                            <div class="small text-muted">
                                <i class="bi bi-calendar-check me-1"></i> Tgl: {{ \Carbon\Carbon::parse($bill->invoice_date)->format('d M Y') }}
                            </div>

                            @if($bill->is_recurring)
                                <span class="mt-1 border badge bg-info-subtle text-info-emphasis border-info-subtle" style="font-size: 0.65rem;" title="Tagihan Induk Langganan Aktif (Setiap {{ $bill->recurring_interval }} {{ $bill->recurring_period }})">
                                    <i class="bi bi-arrow-repeat me-1"></i> Induk Berulang
                                </span>
                            @elseif($isAutoChild)
                                <span class="mt-1 border badge" style="background-color: #f3e8ff; color: #6b21a8; border-color: #d8b4fe !important; font-size: 0.65rem;" title="Dibuat otomatis oleh sistem dari siklus berulang">
                                    <i class="bi bi-robot me-1"></i> Auto-Recurring
                                </span>
                            @else
                                <span class="mt-1 border badge bg-light text-secondary border-secondary-subtle" style="font-size: 0.65rem;" title="Dibuat secara manual tanpa perulangan">
                                    <i class="bi bi-pencil-square me-1"></i> Manual
                                </span>
                            @endif
                        </td>

                        {{-- KOLOM 2: JUDUL & DESKRIPSI --}}
                        <td class="py-3">
                            <div class="mb-1 fw-bold text-dark text-wrap" style="max-width: 250px;" title="{{ $bill->title }}">
                                {{ str_ireplace('Tagihan Opex - ', '', $bill->title ?? '-') }}
                            </div>
                            <div class="small text-muted text-truncate" style="max-width: 250px;" title="{{ $bill->description }}">
                                {{ $bill->description ?: 'Tidak ada keterangan khusus.' }}
                            </div>
                        </td>

                        {{-- KOLOM 3: VENDOR & PT --}}
                        <td class="py-3">
                            <div class="mb-1 fw-bold text-dark text-truncate" style="max-width: 200px;" title="{{ $bill->vendor_name }}">
                                <i class="bi bi-shop text-warning me-1"></i> {{ $bill->vendor_name }}
                            </div>
                            <div class="small text-muted text-truncate" style="max-width: 200px;" title="{{ $bill->company->name ?? '-' }}">
                                <i class="bi bi-building me-1"></i> {{ $bill->company->name ?? '-' }}
                            </div>
                        </td>

                        {{-- KOLOM 4: NOMINAL --}}
                        <td class="py-3 text-end">
                            <div class="fw-bold text-dark fs-6">
                                {{ $bill->currency }} {{ number_format($bill->amount, 0, ',', '.') }}
                            </div>
                        </td>

                        {{-- KOLOM 5: JATUH TEMPO & HITUNG MUNDUR --}}
                        <td class="py-3 text-center">
                            <div class="small fw-semibold {{ $isOverdue ? 'text-danger' : 'text-dark' }}">
                                {{ $dueDate ? $dueDate->format('d M Y') : '-' }}
                            </div>
                            @if($dueDate && $isUnpaid)
                                @if($daysDiff < 0)
                                    <div class="px-2 mt-1 badge bg-danger" style="font-size: 0.65rem;">Telat {{ abs($daysDiff) }} Hari</div>
                                @elseif($daysDiff == 0)
                                    <div class="px-2 mt-1 badge bg-warning text-dark" style="font-size: 0.65rem;">Jatuh Tempo Hari Ini</div>
                                @elseif($daysDiff <= 7)
                                    <div class="px-2 mt-1 border badge bg-warning-subtle text-warning-emphasis border-warning" style="font-size: 0.65rem;">H-{{ $daysDiff }}</div>
                                @endif
                            @endif
                        </td>

                        {{-- KOLOM 6: STATUS --}}
                        <td class="py-3 text-center">
                            <span class="badge bg-{{ $statusColor }}-subtle text-{{ $statusColor }} border border-{{ $statusColor }} rounded-pill px-3 py-2 text-uppercase" style="font-size: 0.7rem;">
                                {{ $statusName }}
                            </span>
                        </td>

                        {{-- KOLOM 7: AKSI --}}
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
                        <td colspan="7" class="py-5 text-center text-muted bg-light">
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
    document.addEventListener('DOMContentLoaded', function() {
        const monthEl = document.getElementById('filter_month');
        const yearEl = document.getElementById('filter_year');
        const startEl = document.getElementById('filter_start_date');
        const endEl = document.getElementById('filter_end_date');

        function clearMonthYear() {
            if (startEl.value || endEl.value) {
                monthEl.value = '';
                yearEl.value = '';
            }
        }

        function clearDateRange() {
            if (monthEl.value || yearEl.value) {
                startEl.value = '';
                endEl.value = '';
            }
        }

        startEl.addEventListener('change', clearMonthYear);
        endEl.addEventListener('change', clearMonthYear);
        monthEl.addEventListener('change', clearDateRange);
        yearEl.addEventListener('change', clearDateRange);
    });

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
