@extends('layouts.app')

@push('css')
<style>
    .stock-limit-section { transition: all 0.3s ease; }
    /* Mempercantik tampilan Switch Bootstrap */
    .form-switch .form-check-input { width: 2.5em; height: 1.25em; cursor: pointer; }
    .form-switch .form-check-label { cursor: pointer; padding-top: 2px; }
</style>
@endpush

@section('content')
<div class="container-fluid pb-5 text-dark">

    <div class="mb-4">
        <a href="{{ route('items.index') }}" class="mb-2 text-decoration-none text-muted small fw-bold d-inline-block">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Katalog
        </a>
        <h4 class="mb-0 fw-bold text-dark">
            <i class="bi bi-plus-circle-fill text-dark me-2"></i> Tambah Master Barang / Jasa
        </h4>
        <div class="mt-1 text-muted small">Daftarkan item baru ke dalam sistem sebelum digunakan pada transaksi.</div>
    </div>

    @if($errors->any())
        <div class="border-0 border-4 shadow-sm alert alert-danger rounded-3 border-start border-danger">
            <div class="fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i> Terdapat Kesalahan:</div>
            <ul class="mt-2 mb-0 small">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('items.store') }}" method="POST">
        @csrf

        {{-- ========================================== --}}
        {{-- 1. INFORMASI UTAMA --}}
        {{-- ========================================== --}}
        <div class="mb-4 border-0 shadow-sm card rounded-4">
            <div class="py-3 bg-white card-header border-bottom">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-info-square-fill me-2 text-primary"></i>Informasi Utama</h6>
            </div>
            <div class="p-4 card-body bg-light">
                <div class="row g-4">
                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-muted">Kode Barang</label>
                        <input type="text" class="shadow-sm form-control fw-bold text-success bg-success-subtle border-success" value="Dibuat Otomatis" readonly disabled>
                        <div class="mt-1 form-text" style="font-size: 0.7rem;">(Format: SKU / AST / JSA)</div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-bold small text-muted">Nama Barang / Jasa <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="shadow-sm form-control border-primary" placeholder="Cth: Indomie Goreng Spesial" required value="{{ old('name') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">Kategori <span class="text-danger">*</span></label>
                        <select name="category_id" class="shadow-sm form-select" required>
                            <option value="">-- Pilih Kategori --</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" {{ old('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->code }} - {{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label fw-bold small text-muted">Spesifikasi Bawaan (Pabrik)</label>
                        <textarea name="specification" class="shadow-sm form-control" rows="2" placeholder="Spesifikasi teknis atau rincian opsional...">{{ old('specification') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        {{-- ========================================== --}}
        {{-- 2. KEMASAN MULTI-UOM --}}
        {{-- ========================================== --}}
        <div class="mb-4 border-0 border-4 shadow-sm card rounded-4 border-start border-success">
            <div class="py-3 bg-white card-header border-bottom">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-boxes me-2 text-success"></i>Manajemen Kemasan (Satuan & Konversi)</h6>
            </div>
            <div class="p-4 card-body bg-light">

                {{-- SATUAN DASAR --}}
                <div class="p-3 mb-4 bg-white border shadow-sm row align-items-end rounded-3 border-success-subtle">
                    <div class="mb-2 col-md-12">
                        <h6 class="mb-0 fw-bold text-success"><i class="bi bi-1-circle-fill me-1"></i>Satuan Dasar Terkecil (Wajib)</h6>
                        <small class="text-muted">Pilih satuan eceran paling kecil yang digunakan untuk menghitung stok di gudang.</small>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-bold small text-muted">Pilih Satuan Dasar <span class="text-danger">*</span></label>
                        <select name="uom_id" class="shadow-sm form-select border-success fw-bold" required id="base_uom_select">
                            <option value="">-- Pilih Satuan Terkecil --</option>
                            @foreach($uoms as $uom)
                                <option value="{{ $uom->id }}" data-name="{{ $uom->name }}">{{ $uom->code }} - {{ $uom->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pb-1 col-md-7 text-muted small">
                        <em>Contoh: Pcs, Bungkus, Lembar, Botol. <b>Jangan pilih Kardus di sini!</b></em>
                    </div>
                </div>

                {{-- KEMASAN BESAR DINAMIS --}}
                <h6 class="mb-3 fw-bold text-dark">Kemasan Alternatif (Pembelian / Penjualan)</h6>
                <div class="table-responsive">
                    <table class="table mb-3 bg-white border table-bordered border-secondary-subtle" id="table-uom">
                        <thead class="text-center bg-light text-muted small text-uppercase">
                            <tr>
                                <th class="py-2" width="35%">Nama Kemasan</th>
                                <th class="py-2" width="25%">Isi (Konversi)</th>
                                <th class="py-2" width="30%">Barcode Kemasan (Opsional)</th>
                                <th class="py-2" width="10%">Hapus</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <select name="uoms[0][uom_name]" class="shadow-sm form-select form-select-sm">
                                        <option value="">-- Pilih Kemasan --</option>
                                        @foreach($uoms as $uom)
                                            <option value="{{ $uom->name }}">{{ $uom->name }} ({{ $uom->code }})</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <div class="shadow-sm input-group input-group-sm">
                                        <input type="number" name="uoms[0][conversion_qty]" class="text-center form-control text-danger fw-bold" placeholder="Cth: 40" min="1" step="0.01">
                                        <span class="px-2 input-group-text bg-light text-muted base-uom-label" style="font-size: 0.75rem;">[Pilih Sat. Dasar]</span>
                                    </div>
                                </td>
                                <td>
                                    <input type="text" name="uoms[0][barcode]" class="shadow-sm form-control form-control-sm" placeholder="Scan Barcode...">
                                </td>
                                <td class="text-center align-middle">
                                    <button type="button" class="btn btn-sm btn-outline-danger rounded-circle btn-remove-uom" disabled>
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <button type="button" class="shadow-sm btn btn-outline-success rounded-pill fw-bold" id="btn-add-uom">
                    <i class="bi bi-plus-circle me-1"></i> Tambah Kemasan Lain
                </button>
            </div>
        </div>

        {{-- ========================================== --}}
        {{-- 3. KARAKTERISTIK BARANG & STOK LIMIT --}}
        {{-- ========================================== --}}
        <div class="mb-4 row g-4">
            <div class="col-lg-6">
                <div class="border-0 shadow-sm card rounded-4 h-100">
                    <div class="py-3 bg-white card-header border-bottom">
                        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-sliders me-2 text-warning"></i>Karakteristik & Lacak</h6>
                    </div>
                    <div class="p-4 card-body bg-light">

                        <div class="mb-4">
                            <label class="form-label fw-bold small text-muted">Tipe Barang / Jasa? <span class="text-danger">*</span></label>
                            <select name="item_type_code" id="input_type" class="shadow-sm form-select border-success trigger-dependency" required>
                                <option value="">-- Pilih Tipe --</option>
                                @foreach($itemTypes as $type)
                                    <option value="{{ $type->code }}" {{ old('item_type_code') == $type->code ? 'selected' : '' }}>
                                        {{ $type->code }} - {{ $type->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- 🔥 Diubah Menjadi Switch agar terbaca oleh $request->has() di Controller 🔥 --}}
                        <div class="p-3 bg-white border shadow-sm rounded-3 border-warning-subtle">
                            <div class="form-check form-switch">
                                <input class="form-check-input border-warning" type="checkbox" role="switch" id="input_trc" name="is_trackable" value="1" {{ old('is_trackable') ? 'checked' : '' }}>
                                <label class="form-check-label fw-bold text-dark ms-2" for="input_trc">Lacak Fisik Individu (S/N)?</label>
                            </div>
                            <div class="mt-1 small text-muted ms-1">
                                Aktifkan jika barang ini membutuhkan pencatatan Serial Number, Plat Kendaraan, atau IMEI saat masuk gudang.
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="border-0 border-4 shadow-sm card rounded-4 border-start border-danger h-100 stock-limit-section">
                    <div class="py-3 bg-white card-header border-bottom">
                        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-speedometer2 me-2 text-danger"></i>Pengaturan Batas Stok Gudang</h6>
                    </div>
                    <div class="p-4 card-body bg-light">
                        <div class="mb-4 alert alert-light border-secondary-subtle small text-muted">
                            Sistem akan memberikan peringatan jika stok menyentuh batas minimum. <b>(Angka menggunakan Satuan Dasar Terkecil)</b>.
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Stok Minimum (Batas Bahaya)</label>
                            <div class="shadow-sm input-group">
                                <input type="number" name="min_stock" class="form-control limit-input" step="0.01" min="0" placeholder="Contoh: 5" value="{{ old('min_stock') }}">
                                <span class="input-group-text bg-light text-muted base-uom-label" style="font-size: 0.8rem;">[Pilih Sat. Dasar]</span>
                            </div>
                        </div>
                        <div>
                            <label class="form-label small fw-bold text-muted">Stok Maksimum (Batas Overstock)</label>
                            <div class="shadow-sm input-group">
                                <input type="number" name="max_stock" class="form-control limit-input" step="0.01" min="0" placeholder="Contoh: 50" value="{{ old('max_stock') }}">
                                <span class="input-group-text bg-light text-muted base-uom-label" style="font-size: 0.8rem;">[Pilih Sat. Dasar]</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- TOMBOL SUBMIT --}}
        <div class="mb-5 text-end">
            <button type="submit" class="px-5 py-2 shadow-lg btn btn-dark rounded-pill fw-bold fs-5">
                <i class="bi bi-save me-2"></i> Simpan Item ke Database
            </button>
        </div>

    </form>
</div>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
    $(document).ready(function() {

        // ==========================================
        // 1. FUNGSI KUNCI/BUKA PENGATURAN STOK
        // ==========================================
        function checkDependencies() {
            let itemType = $('#input_type').val();
            let limitSection = $('.stock-limit-section');
            let limitInputs = limitSection.find('.limit-input');

            // Limit stok HANYA berlaku untuk barang STOK (STK).
            // Jika tipe JSA (Jasa) atau SFT (Software), matikan limit stoknya.
            if (itemType === "JSA" || itemType === "NST" || itemType === "SFT") {
                limitSection.css({ 'opacity': '0.4', 'pointer-events': 'none' });
                limitInputs.prop('disabled', true).val('');

                // Jasa/Software tidak bisa dilacak fisiknya, matikan switch trackable
                $('#input_trc').prop('checked', false).prop('disabled', true);
            } else {
                limitSection.css({ 'opacity': '1', 'pointer-events': 'auto' });
                limitInputs.prop('disabled', false);

                // Aktifkan kembali switch trackable
                $('#input_trc').prop('disabled', false);
            }
        }

        $('#input_type').on('change', checkDependencies);
        checkDependencies(); // Jalankan saat pertama kali load

        // ==========================================
        // 2. UPDATE LABEL SATUAN DI UOM & STOK
        // ==========================================
        $('#base_uom_select').on('change', function() {
            let selectedText = $(this).find('option:selected').data('name');
            if (!selectedText) {
                selectedText = '[Pilih Sat. Dasar]';
            }
            $('.base-uom-label').text(selectedText).hide().fadeIn(300);
        });

        // Generate Dropdown Options untuk jQuery
        let uomOptions = `
            <option value="">-- Pilih Kemasan --</option>
            @foreach($uoms as $uom)
                <option value="{{ $uom->name }}">{{ $uom->name }} ({{ $uom->code }})</option>
            @endforeach
        `;

        // ==========================================
        // 3. TAMBAH & HAPUS BARIS KEMASAN
        // ==========================================
        let uomIndex = 1;
        $('#btn-add-uom').on('click', function() {
            let baseUom = $('#base_uom_select').find('option:selected').data('name') || '[Pilih Sat. Dasar]';
            let tr = `
                <tr>
                    <td>
                        <select name="uoms[${uomIndex}][uom_name]" class="shadow-sm form-select form-select-sm" required>
                            ${uomOptions}
                        </select>
                    </td>
                    <td>
                        <div class="shadow-sm input-group input-group-sm">
                            <input type="number" name="uoms[${uomIndex}][conversion_qty]" class="text-center form-control text-danger fw-bold" placeholder="Cth: 12" min="1" step="0.01" required>
                            <span class="px-2 input-group-text bg-light text-muted base-uom-label" style="font-size: 0.75rem;">${baseUom}</span>
                        </div>
                    </td>
                    <td>
                        <input type="text" name="uoms[${uomIndex}][barcode]" class="shadow-sm form-control form-control-sm" placeholder="Scan Barcode...">
                    </td>
                    <td class="text-center align-middle">
                        <button type="button" class="btn btn-sm btn-outline-danger rounded-circle btn-remove-uom" title="Hapus Kemasan">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
            $('#table-uom tbody').append(tr);
            uomIndex++;
        });

        $('#table-uom tbody').on('click', '.btn-remove-uom', function() {
            $(this).closest('tr').remove();
        });
    });
</script>
@endpush
