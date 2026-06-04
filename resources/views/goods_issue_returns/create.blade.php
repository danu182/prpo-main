@extends('layouts.app')

@section('content')
<div class="container pb-5 text-dark">
    <div class="mb-4">
        <a href="{{ route('goods-issues.index') }}" class="text-decoration-none text-muted small fw-bold mb-2 d-inline-block">
            <i class="bi bi-arrow-left me-1"></i> Batal & Kembali
        </a>
        <h4 class="mb-0 fw-bold text-dark">
            <i class="bi bi-arrow-return-left text-warning me-2"></i> Form Retur Pengeluaran Barang
        </h4>
        <div class="mt-1 text-muted small">Kembalikan sisa barang operasional ke gudang dari Ref: <strong class="text-danger">{{ $gi->gi_number }}</strong>.</div>
    </div>

    @if(session('error'))
        <div class="alert alert-danger shadow-sm rounded-3 fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}</div>
    @endif

    <form action="{{ route('goods-issue-returns.store', $gi->id) }}" method="POST" id="form-retur">
        @csrf

        {{-- KARTU INFORMASI RETUR & GUDANG --}}
        <div class="card border-0 shadow-sm rounded-4 mb-4 border-start border-4 border-warning">
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Tanggal Dikembalikan <span class="text-danger">*</span></label>
                        <input type="date" name="return_date" class="form-control shadow-sm" value="{{ date('Y-m-d') }}" required max="{{ date('Y-m-d') }}">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Dikembalikan Oleh <span class="text-danger">*</span></label>
                        <input type="text" name="returned_by_name" class="form-control shadow-sm" value="{{ $gi->requester_name }}" required>
                    </div>

                    {{-- 🔥 DROPDOWN GUDANG (DIKUNCI / DISABLED) 🔥 --}}
                    {{-- 🔥 DROPDOWN GUDANG (SUDAH DIBUKA GEMBOKNYA / FLEKSIBEL) 🔥 --}}
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Terima ke Gudang <span class="text-danger">*</span></label>

                        {{-- Sekarang SELECT ini yang akan mengirimkan datanya ke Controller --}}
                        <select name="warehouse_id" class="form-select shadow-sm border-warning" required>
                            <option value="">-- Pilih Gudang Tujuan --</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}" {{ (isset($asalGudangId) && $asalGudangId == $wh->id) ? 'selected' : '' }}>
                                    {{ $wh->name }}
                                </option>
                            @endforeach
                        </select>

                        {{-- ❌ INPUT HIDDEN LAMA SUDAH DIHAPUS DI SINI ❌ --}}

                        <div class="form-text text-muted" style="font-size: 0.7rem;">
                            <i class="bi bi-info-circle-fill text-primary"></i> Pilih gudang tujuan pengembalian.
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Catatan Retur</label>
                        <input type="text" name="notes" class="form-control shadow-sm" placeholder="Cth: Sisa material proyek...">
                    </div>
                </div>
            </div>
        </div>

        {{-- KARTU DAFTAR BARANG --}}
        <div class="card border-0 shadow-sm rounded-4 border-top border-4 border-warning">
            <div class="card-header bg-white pt-4 pb-3 px-4">
                <h6 class="fw-bold mb-0 text-dark">Daftar Barang Yang Bisa Diretur</h6>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table mb-0 align-middle">
                    <thead class="bg-light text-muted small border-bottom text-uppercase text-center">
                        <tr>
                            <th class="ps-4 text-start">Nama Barang</th>
                            <th width="12%">Total Dipinjam</th>
                            <th width="12%">Sisa Boleh Retur</th>
                            <th width="25%">Qty / Pilih Aset Dikembalikan</th>
                            <th width="20%">Keterangan Kondisi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($returnableItems as $index => $item)
                        @php
                            $isAsset = optional($item->item)->is_asset;
                            $sisaBisaRetur = (float)$item->qty_issued - (float)($item->qty_returned ?? 0);
                        @endphp
                        <tr>
                            <td class="ps-4 py-3">
                                <div class="fw-bold text-dark">{{ optional($item->item)->name }}</div>
                                <span class="badge bg-secondary-subtle text-secondary border mt-1">{{ optional($item->item)->code }}</span>
                                <input type="hidden" name="items[{{ $index }}][gi_item_id]" value="{{ $item->id }}">
                            </td>
                            <td class="text-center fw-bold text-danger">{{ (float)$item->qty_issued }}</td>
                            <td class="text-center fw-bold text-success">{{ $sisaBisaRetur }}</td>

                            {{-- 🔥 KOLOM INPUT / SELECT ASET 🔥 --}}
                            <td>
                                @if($isAsset)
                                    {{-- MODE MAJOR ASSET (ASET TETAP) --}}
                                    @php
                                        preg_match_all('/AST\/[0-9]{4}\/[0-9]{2}\/[0-9]{4}/', $item->notes, $matches);
                                        $borrowedAssets = $matches[0];
                                    @endphp
                                    <select name="items[{{ $index }}][returned_asset_numbers][]" class="form-select shadow-sm border-warning select-asset-return" multiple data-index="{{ $index }}">
                                        @foreach($borrowedAssets as $astNum)
                                            <option value="{{ $astNum }}">{{ $astNum }}</option>
                                        @endforeach
                                    </select>
                                    <div class="form-text small text-muted"><i class="bi bi-info-circle"></i> Tahan CTRL untuk pilih lebih dari 1.</div>
                                    <input type="hidden" name="items[{{ $index }}][qty_returned]" class="qty-hidden-{{ $index }} qty-retur-input" value="0">

                                @elseif(isset($item->item->is_trackable) && $item->item->is_trackable)
                                    {{-- MODE MINOR ASSET (INVENTARIS DENGAN SN) --}}
                                    @php
                                        // Asumsi catatan peminjaman Minor Asset dipisah dengan tanda pipa |
                                        $borrowedSns = array_filter(array_map('trim', explode('|', $item->notes)));
                                    @endphp
                                    <select name="items[{{ $index }}][returned_minor_sns][]" class="form-select shadow-sm border-warning select-asset-return" multiple data-index="{{ $index }}">
                                        @foreach($borrowedSns as $sn)
                                            <option value="{{ $sn }}">{{ $sn }}</option>
                                        @endforeach
                                    </select>
                                    <div class="form-text small text-muted"><i class="bi bi-info-circle"></i> Pilih SN yang diretur.</div>
                                    <input type="hidden" name="items[{{ $index }}][qty_returned]" class="qty-hidden-{{ $index }} qty-retur-input" value="0">

                                @else
                                    {{-- MODE BARANG STOK BIASA (TANPA SN) --}}
                                    <input type="number" name="items[{{ $index }}][qty_returned]" class="form-control text-center shadow-sm qty-retur-input border-warning" max="{{ $sisaBisaRetur }}" min="0" step="0.01" value="0">
                                @endif
                            </td>

                            <td class="pe-4">
                                <input type="text" name="items[{{ $index }}][notes]" class="form-control shadow-sm" placeholder="Cth: Kondisi baik...">
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-light p-4 text-end rounded-bottom-4">
                <button type="button" id="btnSubmitReturn" class="btn btn-warning text-dark px-5 rounded-pill fw-bold shadow-sm">
                    <i class="bi bi-box-arrow-in-down me-2"></i> Proses Retur Barang
                </button>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Script khusus untuk menghitung Qty otomatis jika yang dipilih adalah Aset
    document.querySelectorAll('.select-asset-return').forEach(selectEl => {
        selectEl.addEventListener('change', function() {
            let index = this.getAttribute('data-index');
            let hiddenQtyInput = document.querySelector(`.qty-hidden-${index}`);

            // Hitung berapa opsi yang dipilih
            let selectedCount = Array.from(this.selectedOptions).length;

            // Masukkan jumlahnya ke hidden input
            if(hiddenQtyInput) {
                hiddenQtyInput.value = selectedCount;
            }
        });
    });

    document.getElementById('btnSubmitReturn').addEventListener('click', function(e) {
        e.preventDefault();

        let form = document.getElementById('form-retur');

        // 1. Validasi Bawaan HTML5 (Max, Min, Required)
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        // 2. Validasi Ekstra: Pastikan minimal ada 1 barang yang di-retur (> 0)
        let qtyInputs = document.querySelectorAll('.qty-retur-input');
        let totalRetur = 0;

        qtyInputs.forEach(input => {
            let val = parseFloat(input.value) || 0;
            totalRetur += val;
        });

        if (totalRetur <= 0) {
            Swal.fire({
                title: 'Peringatan!',
                text: 'Anda tidak memasukkan kuantitas retur sama sekali. Silakan isi minimal 1 barang / aset yang akan dikembalikan ke gudang.',
                icon: 'warning',
                confirmButtonColor: '#ffc107',
                customClass: { confirmButton: 'text-dark fw-bold rounded-pill px-4' }
            });
            return;
        }

        // 3. Tampilkan SweetAlert Konfirmasi
        Swal.fire({
            title: 'Proses Retur Barang?',
            text: "Barang / Aset akan langsung dikembalikan ke gudang.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ffc107',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="bi bi-check-circle me-1"></i> Ya, Kembalikan ke Gudang!',
            cancelButtonText: 'Batal',
            customClass: {
                confirmButton: 'text-dark fw-bold rounded-pill px-4',
                cancelButton: 'rounded-pill px-4'
            },
            borderRadius: '15px'
        }).then((result) => {
            if (result.isConfirmed) {
                // Kunci tombol agar tidak dobel submit
                let btn = document.getElementById('btnSubmitReturn');
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Memproses...';
                btn.disabled = true;

                form.submit();
            }
        });
    });
</script>
@endpush
