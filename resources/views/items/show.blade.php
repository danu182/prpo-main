@extends('layouts.app')

@push('css')
<style>
    .card-hover-shadow { transition: all 0.3s ease; }
    .card-hover-shadow:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important; }
    /* Transisi mulus saat form batas stok disabled */
    .stock-limit-section { transition: all 0.3s ease; }

    /* Scrollbar Minimalis untuk Kotak Gudang */
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #a8a8a8; }
</style>
@endpush

@section('content')
<div class="container pb-5 text-dark">

    {{-- HEADER HALAMAN & TOMBOL KEMBALI --}}
    <div class="mb-4 d-flex justify-content-between align-items-end">
        <div>
            <a href="{{ route('items.index') }}" class="mb-2 text-decoration-none text-muted small fw-bold d-inline-block">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Katalog
            </a>
            <h4 class="mb-0 fw-bold text-dark">
                <i class="bi bi-radar me-2 text-primary"></i> Lacak Inventaris Karyawan
            </h4>
            <div class="mt-1 text-muted small">Melihat daftar karyawan yang sedang memegang / meminjam barang ini.</div>
        </div>
        <div>
            <button type="button" class="px-4 shadow-sm btn btn-warning text-dark fw-bold rounded-pill" data-bs-toggle="modal" data-bs-target="#modalEditItem">
                <i class="bi bi-pencil-square me-1"></i> Edit Master Barang
            </button>
        </div>
    </div>

    {{-- TAMPILKAN PESAN SUKSES / ERROR JIKA ADA --}}
    @if(session('success'))
        <div class="border-0 border-4 shadow-sm alert alert-success alert-dismissible fade show rounded-3 border-start border-success">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="border-0 border-4 shadow-sm alert alert-danger alert-dismissible fade show rounded-3 border-start border-danger">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- KARTU INFO MASTER BARANG --}}
    <div class="mb-4 border-0 shadow-sm card rounded-4 bg-light">
        <div class="p-4 card-body">
            <div class="row align-items-center">

                {{-- KOLOM KIRI: INFO BARANG --}}
                <div class="border-2 col-md-8 border-end border-secondary-subtle">
                    <div class="mb-2 d-flex align-items-center">
                        <span class="px-3 py-2 border shadow-sm badge bg-dark rounded-pill fs-6 me-3 border-secondary">{{ $item->code }}</span>
                        <h4 class="mb-0 fw-bold text-dark">{{ $item->name }}</h4>
                    </div>
                    <p class="mt-2 mb-0 text-muted small lh-sm">
                        <i class="bi bi-info-circle me-1"></i> Spesifikasi: <br>
                        {{ $item->specification ?? 'Tidak ada spesifikasi khusus.' }}
                    </p>
                </div>

                {{-- KOLOM KANAN: STOK & LOKASI --}}
                <div class="mt-3 col-md-4 ps-md-4 mt-md-0 d-flex flex-column align-items-md-end align-items-start">

                    {{-- Total Stok --}}
                    <div class="mb-2 text-md-end text-start">
                        <span class="mb-1 text-muted small fw-bold text-uppercase d-block">Total Stok Keseluruhan:</span>
                        <h3 class="fw-bold {{ $item->current_stock > 0 ? 'text-success' : 'text-danger' }} mb-0">
                            {{ (float) $item->current_stock }} <span class="fs-6 fw-normal text-muted">{{ $item->unit }}</span>
                        </h3>
                    </div>

                    {{-- 🔥 KOTAK RINCIAN LOKASI GUDANG 🔥 --}}
                    @if($item->is_stockable && count($stockPerWarehouse) > 0)
                        <div class="mt-2 bg-white border shadow-sm text-start rounded-3 w-100" style="max-width: 260px;">
                            <div class="px-3 py-2 bg-info-subtle border-bottom border-info-subtle rounded-top-3 text-info-emphasis fw-bold" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                                <i class="bi bi-geo-alt-fill me-1 text-danger"></i> RINCIAN LOKASI GUDANG
                            </div>
                            <div class="p-2 custom-scrollbar" style="max-height: 120px; overflow-y: auto;">
                                @foreach($stockPerWarehouse as $sw)
                                    <div class="pb-1 mb-1 d-flex justify-content-between align-items-center border-bottom border-light">
                                        <span class="text-dark small fw-medium text-truncate pe-2" title="{{ optional($sw->warehouse)->name ?? 'Gudang Utama' }}">
                                            <i class="bi bi-shop me-2 text-secondary"></i>{{ optional($sw->warehouse)->name ?? 'Gudang Utama' }}
                                        </span>
                                        <span class="border shadow-sm badge bg-primary-subtle text-primary rounded-pill border-primary-subtle">{{ (float) $sw->total_qty }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Label Status Pelacakan --}}
                    <div class="mt-3">
                        @if($item->is_trackable)
                            <span class="px-3 border shadow-sm badge bg-primary-subtle text-primary border-primary-subtle rounded-pill"><i class="bi bi-person-badge me-1"></i> Dilacak sbg Inventaris</span>
                        @endif
                    </div>

                </div>
            </div>
        </div>
    </div>

    {{-- TABEL DAFTAR PEMEGANG BARANG --}}
    <div class="overflow-hidden border-0 border-4 shadow-sm card rounded-4 border-top border-primary">
        <div class="py-3 bg-white card-header border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-people-fill me-2 text-primary"></i>Daftar Karyawan Pemegang Barang</h6>
            <span class="badge bg-primary rounded-pill">{{ count($holders) }} Karyawan</span>
        </div>

        <div class="p-0 card-body">
            @if(count($holders) > 0)
                <div class="table-responsive">
                    <table class="table mb-0 align-middle table-hover">
                        <thead class="bg-light text-muted small text-uppercase">
                            <tr>
                                <th class="py-3 ps-4" width="5%">No</th>
                                <th class="py-3" width="25%">Nama Karyawan</th>
                                <th class="py-3" width="25%">Departemen / Perusahaan</th>
                                <th class="py-3 text-center" width="15%">Qty Dipegang</th>
                                <th class="py-3 pe-4" width="30%">Catatan Ref (No. Seri) & Kepemilikan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($holders as $index => $holder)
                                <tr class="border-bottom card-hover-shadow">
                                    <td class="py-3 ps-4 text-muted fw-bold">{{ $index + 1 }}</td>
                                    <td>
                                        <div class="fw-bold text-dark fs-6">{{ $holder->employee_name }}</div>
                                    </td>
                                    <td class="text-muted small fw-bold">
                                        <i class="bi bi-building me-1 text-secondary"></i> {{ $holder->company_name }}
                                    </td>
                                    <td class="text-center">
                                        <span class="px-3 py-2 shadow-sm badge bg-danger rounded-pill fs-6">
                                            {{ (float) $holder->qty }} {{ $item->unit }}
                                        </span>
                                    </td>

                                    {{-- 🔥 KOLOM CATATAN DAN KEPEMILIKAN PT 🔥 --}}
                                    <td class="pe-4 small text-muted" style="line-height: 1.5;">
                                        @if($holder->specific_details)
                                            @php
                                                $finalNotes = '-';

                                                // JIKA INI ASET TETAP (MAJOR)
                                                if ($item->is_asset) {
                                                    preg_match_all('/AST\/[0-9]{4}\/[0-9]{2}\/[0-9]{4}/', $holder->specific_details, $matches);
                                                    $astNumbers = $matches[0];

                                                    if (!empty($astNumbers)) {
                                                        // Tarik data LIVE dari Master Aset beserta Nama PT (Company)
                                                        $liveAssets = \App\Models\FixedAsset::with('company')->whereIn('asset_number', $astNumbers)->get();
                                                        $formattedNotes = [];

                                                        foreach ($liveAssets as $liveAst) {
                                                            $info = "<strong class='text-dark'>" . $liveAst->asset_number . "</strong>";
                                                            if($liveAst->serial_number) { $info .= " (SN: <span class='text-primary'>" . $liveAst->serial_number . "</span>)"; }
                                                            if($liveAst->accounting_asset_number) { $info .= " [FA: " . $liveAst->accounting_asset_number . "]"; }

                                                            // Tambahkan Milik PT
                                                            $ptName = $liveAst->company ? $liveAst->company->name : 'Milik Perusahaan';
                                                            $info .= "<br><span class='text-muted' style='font-size:0.7rem;'><i class='bi bi-building me-1'></i>Milik: {$ptName}</span>";

                                                            if($liveAst->spesifikasi_detail) {
                                                                $info .= "<br><span class='text-muted' style='font-size:0.7rem;'>Spek: " . $liveAst->spesifikasi_detail . "</span>";
                                                            }
                                                            $formattedNotes[] = '• ' . $info;
                                                        }
                                                        $finalNotes = implode('<br><br>', $formattedNotes);
                                                    } else {
                                                        $finalNotes = str_replace(' | ', '<br>• ', '• ' . $holder->specific_details);
                                                    }
                                                }
                                                // JIKA INI MINOR ASSET (LACAK PEGAWAI / HARDISK DKK)
                                                else {
                                                    $snArray = array_filter(array_map('trim', explode('|', $holder->specific_details)));

                                                    if (count($snArray) > 0) {
                                                        // Lacak Pembeli (PO) Terakhir untuk Barang Minor Ini
                                                        $latestPo = \App\Models\PurchaseOrder::with('company')->whereHas('items', function($q) use ($item) {
                                                            $q->where('item_id', $item->id);
                                                        })->latest('id')->first();

                                                        $ptNameMinor = ($latestPo && $latestPo->company) ? $latestPo->company->name : 'Milik Perusahaan';

                                                        $formattedNotes = [];
                                                        foreach ($snArray as $sn) {
                                                            $formattedNotes[] = "• <strong class='text-dark'>{$sn}</strong><br><span class='text-muted' style='font-size: 0.7rem;'><i class='bi bi-building me-1'></i>Milik: {$ptNameMinor}</span>";
                                                        }
                                                        $finalNotes = implode('<br><br>', $formattedNotes);
                                                    } else {
                                                        $finalNotes = str_replace(' | ', '<br>• ', '• ' . $holder->specific_details);
                                                    }
                                                }
                                            @endphp
                                            {!! $finalNotes !!}
                                        @else
                                            <span class="opacity-50 fst-italic">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="py-5 text-center text-muted">
                    <i class="mb-3 opacity-25 bi bi-person-x display-1 d-block text-secondary"></i>
                    <h5 class="fw-bold text-dark">Belum Ada Pemegang</h5>
                    <p class="mb-0 small">Barang ini belum diserahkan (di-Goods Issue) kepada karyawan mana pun.</p>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- MODAL EDIT ITEM LANGSUNG DI HALAMAN SHOW --}}
<div class="modal fade" id="modalEditItem" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="overflow-hidden border-0 shadow-lg modal-content rounded-4">
            <form action="{{ route('items.update', $item->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="py-3 border-0 modal-header bg-warning">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-pencil-square me-2"></i>Edit Master Barang</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="p-4 modal-body bg-light text-start">

                    <div class="mb-3 row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">Kode Barang</label>
                            <input type="text" name="code" class="shadow-sm form-control fw-bold" value="{{ $item->code }}" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-bold small text-muted">Nama Barang / Jasa</label>
                            <input type="text" name="name" class="shadow-sm form-control" value="{{ $item->name }}" required>
                        </div>
                    </div>

                    <div class="mb-3 row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">Satuan (Unit)</label>
                            <input type="text" name="unit" class="shadow-sm form-control" value="{{ $item->unit }}" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-bold small text-muted">Kategori</label>
                            <select name="category_id" class="shadow-sm form-select" required>
                                <option value="">-- Pilih Kategori --</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" {{ $item->category_id == $cat->id ? 'selected' : '' }}>
                                        {{ $cat->code }} - {{ $cat->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mb-3 row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">Lacak di Gudang?</label>
                            <select name="is_stockable" class="shadow-sm form-select border-success trigger-dependency">
                                <option value="1" {{ $item->is_stockable ? 'selected' : '' }}>📦 Ya (Stok)</option>
                                <option value="0" {{ !$item->is_stockable ? 'selected' : '' }}>🛠️ Tidak (Non-Stok)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">Apakah ini Aset Tetap?</label>
                            <select name="is_asset" class="shadow-sm form-select border-info trigger-dependency">
                                <option value="0" {{ !$item->is_asset ? 'selected' : '' }}>➖ Bukan Aset</option>
                                <option value="1" {{ $item->is_asset ? 'selected' : '' }}>💻 Ya (Aset)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">Lacak Pemegang (Inventaris)?</label>
                            <select name="is_trackable" class="shadow-sm form-select border-warning">
                                <option value="0" {{ !(isset($item->is_trackable) && $item->is_trackable) ? 'selected' : '' }}>➖ Tidak</option>
                                <option value="1" {{ (isset($item->is_trackable) && $item->is_trackable) ? 'selected' : '' }}>👤 Ya (Minor Asset)</option>
                            </select>
                        </div>
                    </div>

                    {{-- 🔥 FORM MIN/MAX STOCK (MODAL EDIT) 🔥 --}}
                    <div class="p-3 mb-3 bg-white border shadow-sm rounded-3 border-danger-subtle stock-limit-section">
                        <h6 class="mb-3 fw-bold text-danger"><i class="bi bi-speedometer2 me-1"></i> Pengaturan Batas Stok Gudang</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">Stok Minimum (Batas Bahaya)</label>
                                <input type="number" name="min_stock" class="shadow-sm form-control limit-input" value="{{ $item->min_stock }}" step="0.01" min="0" placeholder="Contoh: 5">
                                <div class="mt-1 form-text" style="font-size: 0.7rem;">Peringatan jika stok menyentuh angka ini.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">Stok Maksimum (Batas Overstock)</label>
                                <input type="number" name="max_stock" class="shadow-sm form-control limit-input" value="{{ $item->max_stock }}" step="0.01" min="0" placeholder="Contoh: 50">
                                <div class="mt-1 form-text" style="font-size: 0.7rem;">Saran order PR. Kosongkan jika tak terbatas.</div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold small text-muted">Spesifikasi Bawaan (Pabrik)</label>
                        <textarea name="specification" class="shadow-sm form-control" rows="3">{{ $item->specification }}</textarea>
                    </div>
                </div>
                <div class="p-3 bg-white modal-footer border-top">
                    <button type="button" class="px-4 border btn btn-light rounded-pill fw-bold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="px-4 shadow-sm btn btn-warning text-dark rounded-pill fw-bold"><i class="bi bi-save me-1"></i> Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
    $(document).ready(function() {
        // Fungsi untuk menghidupkan/mematikan pengaturan Min/Max Stock
        function checkDependencies(modal) {
            let isAsset = modal.find('select[name="is_asset"]').val();
            let isStockable = modal.find('select[name="is_stockable"]').val();

            let limitSection = modal.find('.stock-limit-section');
            let limitInputs = limitSection.find('.limit-input');

            // Jika dia "Aset Tetap" ATAU "Tidak Dilacak di Gudang"
            if (isAsset === "1" || isStockable === "0") {
                limitSection.css({ 'opacity': '0.4', 'pointer-events': 'none' }); // Redupkan & matikan klik
                limitInputs.prop('disabled', true);
            } else {
                limitSection.css({ 'opacity': '1', 'pointer-events': 'auto' }); // Terangkan kembali
                limitInputs.prop('disabled', false); // Buka kunci input
            }
        }

        // Panggil fungsi ini saat dropdown diubah
        $('.trigger-dependency').on('change', function() {
            let modal = $(this).closest('.modal');
            checkDependencies(modal);
        });

        // Panggil fungsi saat modal mau dibuka
        $('.modal').on('show.bs.modal', function () {
            checkDependencies($(this));
        });
    });
</script>
@endpush
@endsection
