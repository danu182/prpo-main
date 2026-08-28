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
<div class="pb-5 container-fluid text-dark">

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
            <a href="{{ route('inventory.smart_restock') }}" class="shadow-sm btn btn-danger fw-bold rounded-pill d-flex align-items-center me-2 btn-hover-lift">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                Smart Restock
                @if(isset($criticalStocks) && $criticalStocks->count() > 0)
                    <span class="bg-white shadow-sm badge text-danger rounded-pill ms-2">{{ $criticalStocks->count() }}</span>
                @endif
            </a>
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

    {{-- 🔥 ALERT BARANG KRITIS (MULTI-GUDANG) 🔥 --}}
    @if(isset($criticalStocks) && $criticalStocks->count() > 0)
        <div class="p-4 mb-4 border-0 shadow-sm alert alert-danger rounded-4">
            <div class="mb-3 d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill fs-3 me-3 text-danger"></i>
                <div>
                    <h5 class="mb-1 fw-bolder text-danger">PERHATIAN KOMANDAN!</h5>
                    <div class="text-dark small">
                        Terdapat <strong class="text-danger">{{ $criticalStocks->count() }} kasus stok</strong> yang telah mencapai atau melewati batas minimum gudang. Segera lakukan pengadaan (PR)!
                    </div>
                </div>
            </div>

            <div class="bg-white border shadow-sm table-responsive rounded-3 border-danger-subtle">
                <table class="table mb-0 align-middle table-hover table-sm">
                    <thead class="bg-danger bg-opacity-10 text-danger" style="font-size: 0.75rem;">
                        <tr>
                            <th class="py-2 ps-3">NAMA BARANG & GUDANG</th>
                            <th class="py-2 text-center">SISA FISIK</th>
                            <th class="py-2 text-center">BATAS MIN</th>
                            <th class="py-2 text-center pe-3">SARAN ORDER (PR)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($criticalStocks as $stock)
                            @php
                                $itemMaster = $stock->item;
                                $warehouse = $stock->warehouse;
                                $currentQty = (float)($stock->stock_qty ?? 0);
                                $minQty = (float)($stock->min_stock ?? 0);
                                $maxQty = (float)($stock->max_stock ?? ($minQty * 3));
                                $suggestedOrder = max(1, $maxQty - $currentQty);
                                $uom = $itemMaster->unit ?? 'PCS';
                            @endphp
                            <tr class="border-bottom border-danger-subtle">
                                <td class="py-2 ps-3">
                                    <div class="fw-bolder text-dark">{{ optional($itemMaster)->name ?? 'Item Terhapus' }}</div>
                                    <div class="mt-1 d-flex align-items-center" style="font-size: 0.7rem;">
                                        <span class="badge bg-secondary-subtle text-secondary me-1">{{ optional($itemMaster)->code }}</span>
                                        <span class="badge bg-danger-subtle text-danger"><i class="bi bi-shop me-1"></i>{{ optional($warehouse)->name ?? 'Gudang Pusat' }}</span>
                                    </div>
                                </td>
                                <td class="py-2 text-center fw-bolder text-danger">
                                    {{ $currentQty }} <span class="fw-normal small text-muted">{{ $uom }}</span>
                                </td>
                                <td class="py-2 text-center text-dark fw-bold small">
                                    {{ $minQty }} <span class="fw-normal text-muted">{{ $uom }}</span>
                                </td>
                                <td class="py-2 text-center pe-3">
                                    <a href="{{ route('inventory.smart_restock') }}" class="btn btn-sm btn-outline-primary rounded-pill fw-bold" style="font-size: 0.75rem;">
                                        <i class="bi bi-cart-plus me-1"></i> {{ $suggestedOrder }} {{ $uom }}
                                    </a>
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
                    @forelse($stocks as $item)
                        @php
                                // =====================================================================
                                // 🔥 OTAK SINKRONISASI MUTLAK (SINGLE SOURCE OF TRUTH) 🔥
                                // Disamakan persis dengan kalkulasi di Kartu Mutasi Stok
                                // =====================================================================

                                // 1. Hitung murni dari riwayat mutasi (IN - OUT)
                                $inQuery = \App\Models\StockMutation::where('item_id', $item->id)->where('type', 'IN')->where('notes', 'not like', '%[DE-CAPITALIZE]%');
                                if (!empty($warehouseId)) $inQuery->where('warehouse_id', $warehouseId);
                                $totalIn = (float) $inQuery->sum('qty');

                                $outQuery = \App\Models\StockMutation::where('item_id', $item->id)->where('type', 'OUT')->where('notes', 'not like', '%[CAPITALIZE]%');
                                if (!empty($warehouseId)) $outQuery->where('warehouse_id', $warehouseId);
                                $totalOut = (float) $outQuery->sum('qty');

                                $realTotalStock = $totalIn - $totalOut;

                                // 2. Hitung Aset Terdaftar (Available)
                                $realAsset = 0;
                                if (class_exists('\App\Models\FixedAsset')) {
                                    $qAsset = \App\Models\FixedAsset::where('item_id', $item->id)
                                        ->whereHas('status', function($q) { $q->where('slug', 'available'); });
                                    if (!empty($warehouseId)) {
                                        $qAsset->where('warehouse_id', $warehouseId);
                                    }
                                    $realAsset = (float) $qAsset->count();
                                }

                                // 3. Stok Fisik Biasa (Total Mutasi dikurangi Aset)
                                $realBulk = max(0, $realTotalStock - $realAsset);
                            @endphp

                        <tr>
                            <td class="py-3 ps-4">
                                <span class="border badge bg-secondary-subtle text-secondary border-secondary-subtle rounded-pill">
                                    <i class="bi bi-shop me-1"></i>
                                    {{ $warehouseId ? $warehouses->firstWhere('id', $warehouseId)->name : 'Semua Gudang' }}
                                </span>
                            </td>
                            <td class="py-3 text-muted" style="font-family: monospace;">{{ $item->code }}</td>
                            <td class="py-3 fw-bold text-dark">{{ $item->name }}</td>

                            {{-- 🔥 TAMPILAN STOK GABUNGAN & RINCIANNYA YANG SUDAH KEBAL ERROR 🔥 --}}
                            <td class="py-3 text-center">
                                <div class="fw-bold fs-6 {{ $realTotalStock > 0 ? 'text-success' : 'text-danger' }}">
                                    {{ $realTotalStock }}
                                    <span class="fw-normal text-muted ms-1" style="font-size: 0.75rem;">{{ optional($item->uom)->name ?? 'PCS' }}</span>
                                </div>

                                {{-- Rincian jika stok lebih dari 0 --}}
                                @if($realTotalStock > 0)
                                    <div class="mt-1" style="font-size: 0.65rem;">
                                        @if($realBulk > 0)
                                            <span class="px-2 py-1 border rounded bg-light text-secondary me-1">Biasa: {{ $realBulk }}</span>
                                        @endif
                                        @if($realAsset > 0)
                                            <span class="px-2 py-1 border rounded bg-info-subtle text-info-emphasis border-info-subtle">Aset: {{ $realAsset }}</span>
                                        @endif
                                    </div>
                                @endif
                            </td>

                            <td class="text-center align-middle pe-4">
                                <div class="gap-2 d-flex justify-content-end">
                                    {{-- Tombol Kartu Stok --}}
                                    <a href="{{ route('inventory.show', $item->id) . ($warehouseId ? '?warehouse_id='.$warehouseId : '') }}" class="px-3 btn btn-sm btn-outline-info rounded-pill fw-bold">
                                        <i class="bi bi-clock-history me-1"></i> Kartu Stok
                                    </a>

                                    {{-- Tombol Kapitalisasi --}}
                                    @if($item->is_stockable && !$item->is_asset)
                                    <a href="{{ route('inventory.capitalize.form', $item->code) }}" class="px-3 btn btn-sm btn-outline-warning rounded-pill fw-bold" title="Sulap Stok Biasa Menjadi Aset">
                                        <i class="bi bi-magic me-1"></i> Jadikan Aset
                                    </a>
                                    @endif

                                    <button type="button" class="btn btn-sm btn-outline-warning rounded-pill fw-bold" onclick="openLimitModal({{ $item->id }})" title="Seting Min/Max Gudang">
                                        <i class="bi bi-gear-fill"></i>
                                    </button>


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
                    <button type="submit" class="px-4 shadow-sm btn btn-success rounded-pill fw-bold">
                        <i class="bi bi-eye me-1"></i> Preview Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>



{{-- 🔥 MODAL SETTING MIN/MAX MULTI-GUDANG 🔥 --}}
<div class="modal fade" id="modalStockLimit" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="border-0 shadow-lg modal-content rounded-4">
            <div class="modal-header bg-warning bg-opacity-10 border-bottom-0">
                <h5 class="modal-title fw-bolder text-dark"><i class="bi bi-sliders text-warning me-2"></i>Seting Batas Stok (Min/Max)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('inventory.update_stock_limits') }}" method="POST">
                @csrf
                {{-- 🔥 TAMBAHAN BARU: INPUT HIDDEN UNTUK ID BARANG 🔥 --}}
                <input type="hidden" name="item_id" id="limitItemId" value="">

                <div class="p-4 modal-body">
                    <div class="mb-3">
                        <div class="small text-muted fw-bold text-uppercase">Nama Barang</div>
                        <h5 class="mb-0 fw-bolder text-primary" id="limitItemName">Memuat...</h5>
                    </div>
                    <!-- (Tabel dan isi lainnya biarkan sama seperti sebelumnya) -->
                    <div class="table-responsive">
                        <table class="table align-middle table-borderless">
                            <thead class="border-bottom">
                                <tr>
                                    <th class="text-secondary small fw-bold">NAMA GUDANG</th>
                                    <th class="text-center text-secondary small fw-bold">STOK FISIK</th>
                                    <th class="text-center text-secondary small fw-bold" width="25%">BATAS MINIMUM</th>
                                    <th class="text-center text-secondary small fw-bold" width="25%">BATAS MAKSIMUM</th>
                                </tr>
                            </thead>
                            <tbody id="limitTableBody">
                                <tr><td colspan="4" class="py-4 text-center text-muted">Memuat data gudang...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0 rounded-bottom-4">
                    <button type="button" class="px-4 btn btn-light fw-bold rounded-pill" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="px-4 shadow-sm btn btn-warning fw-bold rounded-pill"><i class="bi bi-save-fill me-1"></i>Simpan Seting</button>
                </div>
            </form>
        </div>
    </div>
</div>


@endsection

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

<script>
    function openLimitModal(itemId) {
        // Tampilkan Modal
        var myModal = new bootstrap.Modal(document.getElementById('modalStockLimit'));
        myModal.show();

        // Reset Konten
        document.getElementById('limitItemName').innerText = "Memuat data...";
        document.getElementById('limitItemId').value = itemId; // Suntikkan ID ke form
        document.getElementById('limitTableBody').innerHTML = '<tr><td colspan="4" class="py-4 text-center text-muted"><div class="spinner-border text-warning spinner-border-sm me-2"></div>Memuat gudang...</td></tr>';

        // Gunakan Base URL Laravel agar tidak bocor
        let ajaxUrl = "{{ url('inventory') }}/" + itemId + "/stock-limits";

        // Tembak AJAX ke Controller
        fetch(ajaxUrl)
            .then(response => {
                if(!response.ok) throw new Error("Terjadi kesalahan pada server (500)");
                return response.json();
            })
            .then(data => {
                document.getElementById('limitItemName').innerText = data.item_name;

                let rowsHtml = '';
                data.stocks.forEach((stock, index) => {
                    let color = stock.current_qty <= stock.min_stock ? 'text-danger' : 'text-success';
                    rowsHtml += `
                        <tr class="border-bottom border-light">
                            <td class="fw-bold text-dark"><i class="bi bi-shop me-2 text-muted"></i>${stock.warehouse}</td>
                            <td class="text-center fw-bolder ${color}">${stock.current_qty} <span class="fw-normal small text-muted">${data.uom}</span></td>
                            <td>
                                <input type="hidden" name="limits[${index}][warehouse_id]" value="${stock.warehouse_id}">
                                <div class="overflow-hidden border shadow-sm input-group input-group-sm rounded-3">
                                    <input type="number" name="limits[${index}][min_stock]" class="text-center border-0 form-control fw-bold text-dark" value="${stock.min_stock}" min="0" step="any">
                                    <!-- 🔥 LABEL SATUAN UOM 🔥 -->
                                    <span class="px-2 border-0 input-group-text bg-light text-muted small fw-bold">${data.uom}</span>
                                </div>
                            </td>
                            <td>
                                <div class="overflow-hidden border shadow-sm input-group input-group-sm rounded-3">
                                    <input type="number" name="limits[${index}][max_stock]" class="text-center border-0 form-control fw-bold text-primary" value="${stock.max_stock}" min="0" step="any">
                                    <!-- 🔥 LABEL SATUAN UOM 🔥 -->
                                    <span class="px-2 border-0 input-group-text bg-light text-muted small fw-bold">${data.uom}</span>
                                </div>
                            </td>
                        </tr>
                    `;
                });

                document.getElementById('limitTableBody').innerHTML = rowsHtml;
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('limitTableBody').innerHTML = '<tr><td colspan="4" class="py-4 text-center text-danger fw-bold">Gagal memuat data. Silakan cek Inspect Element -> Network (F12).</td></tr>';
            });
    }
</script>

@endpush
