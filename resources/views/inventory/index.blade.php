@extends('layouts.app')

@push('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .select2-container .select2-selection--single { height: 38px !important; border: 1px solid #dee2e6 !important; border-radius: 0.375rem !important; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 36px !important; color: #495057 !important; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px !important; }
</style>
@endpush

@section('content')
<div class="container pb-5 text-dark">

    {{-- HEADER --}}
    <div class="gap-3 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h4 class="mb-0 fw-bold"><i class="bi bi-boxes text-info me-2"></i> Stok Multi-Gudang</h4>
            <div class="mt-1 text-muted small">Pantau ketersediaan barang di setiap gudang (IT, ATK, dll).</div>
        </div>

        <div class="flex-wrap gap-2 d-flex">
            {{-- 🔥 TOMBOL IMPORT SALDO AWAL 🔥 --}}
            <button type="button" class="px-4 shadow-sm btn btn-success fw-bold rounded-pill" data-bs-toggle="modal" data-bs-target="#modalImportSaldo">
                <i class="bi bi-file-earmark-excel me-1"></i> Import Saldo Awal
            </button>

            {{-- TOMBOL REGISTER STOK MANUAL --}}
            <button type="button" class="px-4 shadow-sm btn btn-dark fw-bold rounded-pill" data-bs-toggle="modal" data-bs-target="#modalRegisterStok">
                <i class="bi bi-plus-lg me-1"></i> Register Stok
            </button>
        </div>
    </div>

    {{-- ALERT SUCCESS/ERROR --}}
    @if(session('success'))
        <div class="border-0 border-4 shadow-sm alert alert-success border-start border-success alert-dismissible fade show rounded-4">
            <i class="bi bi-check-circle-fill me-2 text-success"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="border-0 border-4 shadow-sm alert alert-danger border-start border-danger alert-dismissible fade show rounded-4">
            <i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- 🔥 WIDGET ALARM STOK KRITIS (EARLY WARNING SYSTEM) 🔥 --}}
    @if(isset($criticalStocks) && $criticalStocks->count() > 0)
    <div class="mb-4 border-0 border-4 shadow-sm alert alert-danger border-start border-danger rounded-4">
        <div class="mb-3 d-flex align-items-center">
            <i class="bg-white shadow-sm bi bi-exclamation-octagon-fill fs-3 me-3 text-danger rounded-circle"></i>
            <div>
                <h6 class="mb-0 fw-bold text-danger">PERHATIAN KOMANDAN!</h6>
                <div class="small text-dark">Terdapat <strong>{{ $criticalStocks->count() }} barang</strong> yang telah mencapai atau melewati batas minimum. Segera lakukan pengadaan (PR)!</div>
            </div>
        </div>
        <div class="bg-white border shadow-sm table-responsive rounded-3">
            <table class="table mb-0 align-middle table-sm table-hover">
                <thead class="bg-light text-muted small text-uppercase">
                    <tr>
                        <th class="py-2 ps-3">Nama Barang</th>
                        <th class="py-2 text-center">Sisa Fisik (Total)</th>
                        <th class="py-2 text-center">Batas Min</th>
                        <th class="py-2 text-center bg-primary-subtle text-primary border-start border-end">Saran Order (PR)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($criticalStocks as $crit)
                    @php
                        // Menghitung saran order = Max Stock - Current Stock
                        $saranOrder = ($crit->max_stock ?? 0) > $crit->current_stock
                                      ? ((float)$crit->max_stock - (float)$crit->current_stock)
                                      : 'Set Max Stok!';
                    @endphp
                    <tr>
                        <td class="py-2 ps-3 fw-bold text-dark">
                            {{ $crit->name }}
                            <span class="border badge bg-secondary-subtle text-secondary ms-1">{{ $crit->code }}</span>
                        </td>
                        <td class="py-2 text-center fw-bold fs-6 text-danger">{{ (float)$crit->current_stock }} {{ optional($crit->uom)->code ?? '-' }}</td>
                        <td class="py-2 text-center text-muted">{{ (float)$crit->min_stock }} {{ optional($crit->uom)->code ?? '-' }}</td>
                        <td class="py-2 text-center fw-bold text-primary bg-primary-subtle border-start border-end fs-6">
                            <i class="bi bi-cart-plus me-1"></i> {{ is_numeric($saranOrder) ? $saranOrder : $saranOrder }} {{ optional($crit->uom)->code ?? '-' }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- FILTER PENCARIAN & GUDANG --}}
    <div class="mb-4 border-0 shadow-sm card rounded-4">
        <div class="p-3 card-body">
            <form action="{{ route('inventory.index') }}" method="GET" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <select name="warehouse_id" class="shadow-sm form-select border-info" onchange="this.form.submit()">
                        <option value="">-- Semua Gudang --</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}" {{ $warehouseId == $wh->id ? 'selected' : '' }}>
                                {{ $wh->name }} ({{ $wh->code }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <input type="text" name="search" class="shadow-sm form-control" placeholder="Cari nama atau kode barang..." value="{{ request('search') }}">
                </div>
                <div class="gap-2 col-md-2 d-flex">
                    <button class="text-white shadow-sm btn btn-info fw-bold w-100" type="submit"><i class="bi bi-search me-1"></i> Cari</button>
                    @if(request('search') || request('warehouse_id'))
                        <a href="{{ route('inventory.index') }}" class="border shadow-sm btn btn-light"><i class="bi bi-x-lg text-danger"></i></a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- TABEL STOK --}}
    <div class="border-0 border-4 shadow-sm card rounded-4 border-top border-info">
        <div class="p-0 card-body table-responsive">
            <table class="table mb-0 align-middle table-hover">
                <thead class="bg-light fw-bold text-muted small text-uppercase border-bottom">
                    <tr>
                        <th class="py-3 ps-4">Gudang</th>
                        <th class="py-3">Kode Barang</th>
                        <th class="py-3">Nama Barang</th>
                        <th class="py-3 text-center">Stok Tersedia</th>
                        <th class="py-3 pe-4 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- 🔥 SEKARANG KITA LOOPING MASTER ITEM, BUKAN INVENTORY STOCK 🔥 --}}
                    @forelse($stocks as $item)
                        <tr>
                            <td class="py-3 ps-4">
                                <span class="border badge bg-secondary-subtle text-secondary border-secondary-subtle rounded-pill">
                                    <i class="bi bi-shop me-1"></i> 
                                    {{ $warehouseId ? $warehouses->firstWhere('id', $warehouseId)->name : 'Semua Gudang' }}
                                </span>
                            </td>
                            <td class="py-3 text-muted" style="font-family: monospace;">{{ $item->code }}</td>
                            <td class="py-3 fw-bold text-dark">{{ $item->name }}</td>
                            <td class="py-3 text-center">
                                <span class="fw-bold fs-6 {{ $item->total_stock > 0 ? 'text-success' : 'text-danger' }}">
                                    {{ (float)$item->total_stock }} 
                                    <span class="fw-normal text-muted ms-1 fs-6">{{ optional($item->uom)->name ?? 'PCS' }}</span>
                                </span>
                            </td>
                            <td class="text-center align-middle">
                                <div class="d-flex justify-content-center gap-2">
                                    {{-- Tombol Kartu Stok (Selalu Muncul) --}}
                                    <a href="{{ route('inventory.show', $item->id) }}" class="btn btn-sm btn-outline-info rounded-pill fw-bold px-3">
                                        <i class="bi bi-clock-history me-1"></i> Kartu Stok
                                    </a>
                                    
                                    {{-- Tombol Kapitalisasi (Hanya muncul untuk Stok Biasa yang BUKAN Jasa & BUKAN Aset Master) --}}
                                    @if($item->is_stockable && !$item->is_asset)
                                    <a href="{{ route('inventory.capitalize.form', $item->code) }}" class="btn btn-sm btn-outline-warning rounded-pill fw-bold px-3" title="Sulap Stok Biasa Menjadi Aset">
                                        <i class="bi bi-magic me-1"></i> Jadikan Aset
                                    </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-5 text-center text-muted">
                                <i class="mb-3 opacity-25 bi bi-boxes fs-1 d-block"></i>
                                Belum ada data Master Barang berjenis Stok.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($stocks->hasPages())
        <div class="p-3 bg-white border-top card-footer rounded-bottom-4">
            {{ $stocks->links() }}
        </div>
        @endif
    </div>
</div>

{{-- 🔥 MODAL REGISTER STOK (MANUAL) 🔥 --}}
<div class="modal fade" id="modalRegisterStok" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="overflow-hidden border-0 shadow-lg modal-content rounded-4">
            <form action="{{ route('inventory.adjustment') }}" method="POST">
                @csrf
                <div class="py-3 text-white border-0 modal-header bg-dark">
                    <h5 class="modal-title fw-bold"><i class="bi bi-box-seam me-2"></i> Register Stok Manual</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="p-4 modal-body bg-light text-start">

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Pilih Gudang Penempatan <span class="text-danger">*</span></label>
                        <select name="warehouse_id" class="shadow-sm form-select border-info" required>
                            <option value="">-- Pilih Gudang --</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Pilih Barang <span class="text-danger">*</span></label>
                        <select name="item_id" class="shadow-sm form-select select2-items" style="width: 100%" required>
                            <option value="">-- Ketik Nama / Kode Barang --</option>
                            @foreach($allItems as $item)
                                <option value="{{ $item->id }}">{{ $item->code }} - {{ $item->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Jumlah Masuk (Kuantitas) <span class="text-danger">*</span></label>
                        <div class="shadow-sm input-group">
                            <span class="bg-white input-group-text"><i class="bi bi-123 text-muted"></i></span>
                            <input type="number" name="qty" class="form-control fw-bold text-success" step="0.01" min="0.1" placeholder="Contoh: 10" required>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold small text-muted">Keterangan / Referensi</label>
                        <textarea name="notes" class="shadow-sm form-control" rows="2" placeholder="Contoh: Saldo awal dari stock opname..." required></textarea>
                    </div>

                </div>
                <div class="p-3 bg-white modal-footer border-top">
                    <button type="button" class="px-4 border btn btn-light rounded-pill fw-bold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="px-4 text-white shadow-sm btn btn-dark rounded-pill fw-bold">
                        <i class="bi bi-save me-1"></i> Simpan Stok
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- 🔥 MODAL IMPORT EXCEL SALDO AWAL 🔥 --}}
<div class="modal fade" id="modalImportSaldo" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="border-0 shadow-lg modal-content rounded-4">
            {{-- 🔥 PERBAIKAN 1: Arahkan action ke preview_import 🔥 --}}
            <form action="{{ route('inventory.preview_import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="text-white border-0 modal-header bg-success">
                    <h5 class="modal-title fw-bold"><i class="bi bi-cloud-arrow-up me-2"></i> Import Saldo Awal (Excel)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="p-4 modal-body text-start">
                    <div class="shadow-sm alert alert-info small border-info-subtle">
                        <strong><i class="bi bi-info-circle-fill me-1"></i> Petunjuk:</strong> Pastikan Kode Barang sudah terdaftar di Master Barang. Gunakan template standar untuk memasukkan stok ke gudang.
                        <div class="mt-2">
                            <a href="{{ route('inventory.download_template') }}" class="shadow-sm btn btn-sm btn-dark fw-bold rounded-pill">
                                <i class="bi bi-download me-1"></i> Download Template
                            </a>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Pilih File (.xlsx) <span class="text-danger">*</span></label>
                        <input type="file" name="import_file" class="shadow-sm form-control border-success" accept=".xlsx,.xls,.csv" required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-bold small">Unggah Dokumen Bukti (BAST/Memo/PDF) <span class="text-info">(Opsional)</span></label>
                        <input type="file" name="attachment_file" class="shadow-sm form-control" accept=".pdf,.jpg,.jpeg,.png">
                        <div class="form-text" style="font-size: 0.7rem;">Format: PDF/JPG. Maksimal 5MB. Dokumen ini akan menjadi lampiran bukti sah mutasi stok.</div>
                    </div>
                </div>
                <div class="p-3 modal-footer border-top bg-light">
                    <button type="button" class="px-4 border btn btn-light rounded-pill fw-bold" data-bs-dismiss="modal">Batal</button>
                    {{-- 🔥 PERBAIKAN 2: Ubah teks tombol menjadi Preview 🔥 --}}
                    <button type="submit" class="px-4 shadow-sm btn btn-success rounded-pill fw-bold">
                        <i class="bi bi-eye me-1"></i> Preview Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        // Inisialisasi Select2 di dalam Modal agar dropdown barangnya bisa diketik/dicari
        $('.select2-items').select2({
            dropdownParent: $('#modalRegisterStok'),
            theme: "default"
        });
    });
</script>
@endpush
@endsection
