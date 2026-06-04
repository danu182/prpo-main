@extends('layouts.app')

@push('css')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        /* --- Modern Card & Table Styling --- */
        .card-modern { border: none; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); background: #fff; }

        table.dataTable thead th {
            background-color: #f8f9fa !important;
            color: #64748b;
            font-weight: 700;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #edf2f7 !important;
            padding: 1rem;
        }

        table.dataTable tbody td {
            padding: 1rem;
            vertical-align: middle;
            color: #334155;
            font-size: 0.95rem;
            border-bottom: 1px solid #f1f5f9;
        }

        /* --- Badge & Avatar --- */
        .avatar-xs { width: 30px; height: 30px; border-radius: 50%; object-fit: cover; border: 2px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .page-item.active .page-link { background-color: #0d6efd; border-color: #0d6efd; border-radius: 50%; }
        .page-link { border-radius: 50%; margin: 0 3px; border: none; color: #64748b; width: 35px; height: 35px; display: flex; align-items: center; justify-content: center; }
        .hover-shadow:hover { background-color: #f8f9fa; cursor: pointer; }
    </style>
@endpush

@section('content')

<div class="mt-2 mb-4 d-flex justify-content-between align-items-end">
    <div>
        <h4 class="mb-1 fw-bold text-dark">Purchase Orders (PO)</h4>
        <p class="mb-0 text-muted small">Daftar pesanan pembelian ke vendor.</p>
    </div>
    {{-- Tombol Buat PO biasanya dari PR, tapi jika butuh manual bisa diaktifkan --}}
    {{-- <a href="{{ route('po.create') }}" class="px-4 shadow-sm btn btn-primary rounded-pill fw-bold"><i class="bi bi-plus-lg me-1"></i> Buat PO Manual</a> --}}
</div>

<div class="p-4 card card-modern">

    {{-- FILTER AREA --}}
    <div class="mb-4 row g-2 align-items-end">

        {{-- Filter Status --}}
        <div class="col-md-2">
            <label class="mb-1 small text-muted fw-bold">Status PO</label>
            <select id="filterStatus" class="shadow-sm form-select form-select-sm rounded-pill border-light bg-light">
                <option value="">Semua Status</option>
                @foreach($statuses as $status)
                    <option value="{{ $status->name }}">{{ $status->name }}</option>
                @endforeach
            </select>
        </div>

        {{-- Filter Vendor --}}
        <div class="col-md-3">
            <label class="mb-1 small text-muted fw-bold">Vendor</label>
            <select id="filterVendor" class="shadow-sm form-select form-select-sm rounded-pill border-light bg-light">
                <option value="">Semua Vendor</option>
                @foreach($vendors as $vendor)
                    <option value="{{ $vendor->name }}">{{ $vendor->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-md-2">
            <label class="mb-1 small text-muted fw-bold">Baris</label>
            <select id="lengthChange" class="shadow-sm form-select form-select-sm rounded-pill border-light bg-light">
                <option value="10">10 Baris</option>
                <option value="25">25 Baris</option>
                <option value="50">50 Baris</option>
            </select>
        </div>

        <div class="col-md-4 ms-auto">
            <label class="mb-1 small text-muted fw-bold">Pencarian Cepat</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-light border-end-0 rounded-start-pill ps-3">
                    <i class="bi bi-search text-muted"></i>
                </span>
                <input type="text" id="customSearch" class="shadow-sm form-control bg-light border-start-0 rounded-end-pill" placeholder="Cari No PO, Vendor, atau Nilai...">
            </div>
        </div>
    </div>

    {{-- TABEL DATA --}}
    <div class="table-responsive">
        <table id="poTable" class="table table-hover w-100">
            <thead>
                <tr>
                    <th>No. PO</th>
                    <th>Tanggal</th>
                    <th>Vendor</th>
                    <th>Total Nilai</th>
                    <th class="text-center">Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($orders as $po)
                <tr>
                    {{-- 1. No PO --}}
                    <td class="fw-bold text-primary font-monospace">
                        <a href="{{ route('po.show', $po->id) }}" class="text-decoration-none">
                            {{ $po->po_number }}
                        </a>
                        <div class="small text-muted fst-italic" style="font-size: 0.75rem;">
                            Ref PR: {{ $po->purchaseRequest->pr_number ?? '-' }}
                        </div>
                    </td>

                    {{-- 2. Tanggal PO --}}
                    <td data-sort="{{ $po->po_date }}">
                        {{ \Carbon\Carbon::parse($po->po_date)->format('d M Y') }}
                    </td>

                    {{-- 3. Vendor --}}
                    <td>
                        <div class="fw-bold text-dark">{{ $po->vendor->name ?? '-' }}</div>
                        <small class="text-muted">{{ $po->vendor->phone ?? '' }}</small>
                    </td>

                    {{-- 4. Total Nilai --}}
                    <td class="fw-bold text-dark" data-sort="{{ $po->grand_total }}">
                        {{ $po->currency }} {{ number_format($po->grand_total, 0, ',', '.') }}
                    </td>

                    {{-- 5. STATUS (SUDAH DIPERBAIKI) --}}
                    <td class="text-center">
                        @if($po->status)
                            <span class="badge bg-{{ $po->status->color }} px-3 py-2 rounded-pill fw-normal shadow-sm">
                                {{ $po->status->name }}
                            </span>
                        @else
                            <span class="px-3 py-2 badge bg-secondary rounded-pill fw-normal">Unknown</span>
                        @endif
                    </td>

                    {{-- 6. Aksi --}}
                    <td class="text-end">
                        <div class="btn-group">
                            <a href="{{ route('po.show', $po->id) }}" class="mx-1 border btn btn-sm btn-light rounded-circle" title="Lihat Detail">
                                <i class="bi bi-eye text-primary"></i>
                            </a>

                            {{-- Tombol Cetak --}}
                            <a href="#" class="mx-1 border btn btn-sm btn-light rounded-circle" title="Cetak PDF">
                                <i class="bi bi-printer text-secondary"></i>
                            </a>

                            {{-- Edit hanya jika status Draft/Pending --}}
                            @if($po->status && in_array($po->status->slug, ['pending_approval', 'draft']))
                                <a href="#" class="mx-1 border btn btn-sm btn-light rounded-circle" title="Edit PO">
                                    <i class="bi bi-pencil-square text-warning"></i>
                                </a>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@endsection

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            var table = $('#poTable').DataTable({
                "dom": 'rtip', // Layout custom (tanpa search bawaan)
                "pageLength": 10,
                "ordering": true,
                "order": [[ 0, "desc" ]], // Default sort by No PO Desc
                "columnDefs": [
                    { "orderable": false, "targets": 5 } // Kolom Aksi gak bisa disort
                ],
                "language": {
                    "emptyTable": "<div class='py-5 text-center text-muted'><i class='mb-2 bi bi-cart-x display-6 d-block'></i> Tidak ada data PO ditemukan.</div>",
                    "info": "Menampilkan _START_ s/d _END_ dari _TOTAL_ data",
                    "infoEmpty": "",
                    "infoFiltered": "(difilter dari _MAX_ total data)",
                    "zeroRecords": "Pencarian tidak ditemukan",
                    "paginate": {
                        "next": "<i class='bi bi-chevron-right'></i>",
                        "previous": "<i class='bi bi-chevron-left'></i>"
                    }
                }
            });

            // --- Custom Filter Logic ---

            // 1. Ubah Jumlah Baris
            $('#lengthChange').on('change', function() {
                table.page.len(this.value).draw();
            });

            // 2. Search Box Custom
            $('#customSearch').on('keyup', function() {
                table.search(this.value).draw();
            });

            // 3. Filter Status (Kolom index 4)
            $('#filterStatus').on('change', function() {
                table.column(4).search(this.value).draw();
            });

            // 4. Filter Vendor (Kolom index 2)
            $('#filterVendor').on('change', function() {
                // Gunakan Smart Search false agar exact match jika perlu, atau default regex
                table.column(2).search(this.value).draw();
            });
        });
    </script>
@endpush
