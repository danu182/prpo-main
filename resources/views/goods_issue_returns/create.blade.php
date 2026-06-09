@extends('layouts.app')

@section('content')
<div class="container pb-5 text-dark">
    <div class="mb-4">
        <a href="{{ route('goods-issues.index') }}" class="mb-2 text-decoration-none text-muted small fw-bold d-inline-block">
            <i class="bi bi-arrow-left me-1"></i> Batal & Kembali
        </a>
        <h4 class="mb-0 fw-bold text-dark">
            <i class="bi bi-arrow-return-left text-warning me-2"></i> Form Retur Pengeluaran Barang
        </h4>
        <div class="mt-1 text-muted small">Kembalikan sisa barang operasional ke gudang dari Ref: <strong class="text-danger">{{ $gi->gi_number }}</strong>.</div>
    </div>

    @if(session('error'))
        <div class="shadow-sm alert alert-danger rounded-3 fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}</div>
    @endif

    <form action="{{ route('goods-issue-returns.store', $gi->id) }}" method="POST" id="form-retur">
        @csrf

        {{-- KARTU INFORMASI RETUR & GUDANG --}}
        <div class="mb-4 border-0 border-4 shadow-sm card rounded-4 border-start border-warning">
            <div class="p-4 card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Tanggal Dikembalikan <span class="text-danger">*</span></label>
                        <input type="date" name="return_date" class="shadow-sm form-control" value="{{ date('Y-m-d') }}" required max="{{ date('Y-m-d') }}">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Dikembalikan Oleh <span class="text-danger">*</span></label>
                        <input type="text" name="returned_by_name" class="shadow-sm form-control" value="{{ $gi->requester_name }}" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Terima ke Gudang <span class="text-danger">*</span></label>
                        <select name="warehouse_id" class="shadow-sm form-select border-warning" required>
                            <option value="">-- Pilih Gudang Tujuan --</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}" {{ (isset($asalGudangId) && $asalGudangId == $wh->id) ? 'selected' : '' }}>
                                    {{ $wh->name }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text text-muted" style="font-size: 0.7rem;">
                            <i class="bi bi-info-circle-fill text-primary"></i> Pilih gudang tujuan pengembalian.
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Catatan Retur</label>
                        <input type="text" name="notes" class="shadow-sm form-control" placeholder="Cth: Sisa material proyek...">
                    </div>
                </div>
            </div>
        </div>

        {{-- KARTU DAFTAR BARANG --}}
        <div class="border-0 border-4 shadow-sm card rounded-4 border-top border-warning">
            <div class="px-4 pt-4 pb-3 bg-white card-header">
                <h6 class="mb-0 fw-bold text-dark">Daftar Barang Yang Bisa Diretur</h6>
            </div>
            <div class="p-0 card-body table-responsive">
                <table class="table mb-0 align-middle">
                    <thead class="text-center bg-light text-muted small border-bottom text-uppercase">
                        <tr>
                            <th class="ps-4 text-start">Nama Barang</th>
                            <th width="12%">Total Dipinjam</th>
                            <th width="12%">Sisa Boleh Retur</th>
                            <th width="28%">Qty / Pilih Aset Dikembalikan <span class="text-danger">*</span></th>
                            <th width="20%">Keterangan Kondisi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($returnableItems as $index => $item)
                        @php
                            $masterItem = $item->item;
                            $isAsset = optional($masterItem)->is_asset;
                            $isTrackable = optional($masterItem)->is_trackable;
                            $baseUomName = optional($masterItem->uom)->name ?? 'PCS';

                            // 1. Ekstrak Data UOM & Konversi dari Histori GI Aslinya
                            $rawGiUom = $item->getRawOriginal('uom') ?: 'PCS';
                            $cleanGiUom = trim(preg_replace('/ \(Isi:?.*\)/i', '', $rawGiUom));

                            $giConvRate = 1;
                            if (preg_match('/Isi\s*([0-9.]+)/i', $rawGiUom, $matches)) {
                                $giConvRate = (float) $matches[1];
                            } elseif ($item->uom_id) {
                                $uomDb = collect(optional($masterItem)->itemUoms)->where('id', $item->uom_id)->first();
                                if ($uomDb) $giConvRate = (float) $uomDb->conversion_qty;
                            }

                            // 2. Hitung Sisa Kuota
                            $sisaBisaRetur = (float)$item->qty_issued - (float)($item->qty_returned ?? 0);
                            $maxBaseQty = $sisaBisaRetur * $giConvRate; // Konversi murni ke Pcs Eceran
                        @endphp
                        <tr>
                            <td class="py-3 ps-4">
                                <div class="fw-bold text-dark">{{ optional($masterItem)->name }}</div>
                                <span class="mt-1 border badge bg-secondary-subtle text-secondary">{{ optional($masterItem)->code }}</span>
                                <input type="hidden" name="items[{{ $index }}][gi_item_id]" value="{{ $item->id }}">
                            </td>

                            <td class="text-center fw-bold text-danger">
                                {{ (float)$item->qty_issued }} <br>
                                <span class="text-muted fw-normal" style="font-size: 0.65rem;">{{ $rawGiUom }}</span>
                            </td>

                            <td class="text-center fw-bold text-success">
                                {{ $sisaBisaRetur }} <br>
                                <span class="text-muted fw-normal" style="font-size: 0.65rem;">{{ $rawGiUom }}</span>
                            </td>

                            {{-- 🔥 KOLOM INPUT / SELECT ASET 🔥 --}}
                            <td>
                                @if($isAsset)
                                    {{-- MODE MAJOR ASSET (ASET TETAP) --}}
                                    @php
                                        preg_match_all('/AST\/[0-9]{4}\/[0-9]{2}\/[0-9]{4}/', $item->notes, $matches);
                                        $borrowedAssets = $matches[0];
                                    @endphp
                                    <select name="items[{{ $index }}][returned_asset_numbers][]" class="shadow-sm form-select border-warning select-asset-return" multiple data-index="{{ $item->id }}">
                                        @foreach($borrowedAssets as $astNum)
                                            <option value="{{ $astNum }}">{{ $astNum }}</option>
                                        @endforeach
                                    </select>
                                    <div class="form-text small text-muted"><i class="bi bi-info-circle"></i> Tahan CTRL untuk pilih lebih dari 1.</div>
                                    <input type="hidden" name="items[{{ $index }}][qty_returned]" class="qty-hidden-{{ $item->id }} qty-retur-input" value="0">

                                @elseif($isTrackable)
                                    {{-- MODE MINOR ASSET (INVENTARIS DENGAN SN) --}}
                                    @php
                                        $borrowedSns = array_filter(array_map('trim', explode('|', preg_replace('/Satuan.*/', '', $item->notes))));
                                    @endphp
                                    <select name="items[{{ $index }}][returned_minor_sns][]" class="shadow-sm form-select border-warning select-asset-return" multiple data-index="{{ $item->id }}">
                                        @foreach($borrowedSns as $sn)
                                            <option value="{{ $sn }}">{{ $sn }}</option>
                                        @endforeach
                                    </select>
                                    <div class="form-text small text-muted"><i class="bi bi-info-circle"></i> Pilih SN yang diretur.</div>
                                    <input type="hidden" name="items[{{ $index }}][qty_returned]" class="qty-hidden-{{ $item->id }} qty-retur-input" value="0">

                                @else
                                    {{-- 🔥 MODE BARANG STOK BIASA (DENGAN UOM DROPDOWN DINAMIS) 🔥 --}}
                                    <div class="mb-1 shadow-sm input-group input-group-sm">
                                        <input type="number" name="items[{{ $index }}][qty_returned]" id="qty-input-{{ $item->id }}" class="text-center form-control qty-retur-input border-warning fw-bold text-dark" max="{{ $sisaBisaRetur }}" min="0" step="any" value="0" oninput="checkQty({{ $item->id }})">
                                        <select name="items[{{ $index }}][uom]" class="form-select border-warning bg-warning-subtle text-dark fw-bold" style="max-width: 135px;" data-current-conv="{{ $giConvRate }}" onchange="changeUom(this, {{ $item->id }}, {{ $maxBaseQty }})">
                                            <option value="{{ $rawGiUom }}" data-conv="{{ $giConvRate }}">{{ $rawGiUom }} [GI]</option>
                                            @if(strtolower($baseUomName) !== strtolower($cleanGiUom))
                                                <option value="{{ $baseUomName }}" data-conv="1">{{ $baseUomName }} (Ecer)</option>
                                            @endif
                                        </select>
                                    </div>
                                    <div class="mt-1 text-center text-muted" style="font-size: 0.65rem;">
                                        Maks: <strong class="text-danger" id="max-val-{{ $item->id }}">{{ $sisaBisaRetur }}</strong> <span id="uom-text-{{ $item->id }}">{{ $cleanGiUom }}</span>
                                        <input type="hidden" id="max_{{ $item->id }}" value="{{ $sisaBisaRetur }}">
                                    </div>
                                @endif
                            </td>

                            <td class="pe-4">
                                <input type="text" name="items[{{ $index }}][notes]" class="shadow-sm form-control" placeholder="Cth: Kondisi baik...">
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4 card-footer bg-light text-end rounded-bottom-4">
                <button type="button" id="btnSubmitReturn" class="px-5 shadow-sm btn btn-warning text-dark rounded-pill fw-bold">
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
    // 1. Script Aset Tetap / Trackable: Hitung otomatis Qty dari opsi yang dipilih
    document.querySelectorAll('.select-asset-return').forEach(selectEl => {
        selectEl.addEventListener('change', function() {
            let indexId = this.getAttribute('data-index');
            let hiddenQtyInput = document.querySelector(`.qty-hidden-${indexId}`);

            let selectedCount = Array.from(this.selectedOptions).length;
            if(hiddenQtyInput) hiddenQtyInput.value = selectedCount;
        });
    });

    // 2. 🔥 FITUR AJAIB UOM: Mengonversi maksimal stok secara realtime saat UOM diganti 🔥
    function changeUom(selectElement, itemId, maxBaseQty) {
        let selectedOption = selectElement.options[selectElement.selectedIndex];
        let newConvRate = parseFloat(selectedOption.getAttribute('data-conv')) || 1;
        let oldConvRate = parseFloat(selectElement.getAttribute('data-current-conv')) || 1;

        let qtyInput = document.getElementById(`qty-input-${itemId}`);
        let currentQty = parseFloat(qtyInput.value) || 0;

        // Pertahankan nilai riil (Jika awalnya 1 Pack, diubah ke Pcs otomatis jadi 10 Pcs)
        let newQty = (currentQty * oldConvRate) / newConvRate;

        // Batas maksimal baru menggunakan pembulatan ke bawah agar tidak over-limit
        let newMaxVal = Math.floor(maxBaseQty / newConvRate);

        qtyInput.setAttribute('max', newMaxVal);

        if (currentQty > 0) {
            qtyInput.value = parseFloat(newQty.toFixed(2));
            if (parseFloat(qtyInput.value) > newMaxVal) qtyInput.value = newMaxVal;
        }

        // Update data konversi saat ini
        selectElement.setAttribute('data-current-conv', newConvRate);

        // Update Text Tampilan Bawah (Help Text)
        let helpMax = document.getElementById(`max-val-${itemId}`);
        let helpUom = document.getElementById(`uom-text-${itemId}`);
        let hiddenMax = document.getElementById(`max_${itemId}`);

        if (helpMax) helpMax.innerText = newMaxVal;
        if (hiddenMax) hiddenMax.value = newMaxVal;

        let cleanUomName = selectedOption.text.replace(/ \[GI\]|\(Ecer\)/gi, '').trim();
        if (helpUom) helpUom.innerText = cleanUomName;

        checkQty(itemId);
    }

    // 3. Batasi Input agar tidak melewati Max
    function checkQty(itemId) {
        const qtyInput = document.getElementById('qty-input-' + itemId);
        const maxQty = parseFloat(document.getElementById('max_' + itemId).value);

        let currentQty = parseFloat(qtyInput.value) || 0;
        if (currentQty > maxQty) {
            qtyInput.value = maxQty;
        }
    }

    // 4. Proses Submit dengan Validasi Ekstra
    document.getElementById('btnSubmitReturn').addEventListener('click', function(e) {
        e.preventDefault();
        let form = document.getElementById('form-retur');

        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        let qtyInputs = document.querySelectorAll('.qty-retur-input');
        let totalRetur = 0;

        qtyInputs.forEach(input => {
            totalRetur += (parseFloat(input.value) || 0);
        });

        if (totalRetur <= 0) {
            Swal.fire({
                title: 'Peringatan!',
                text: 'Anda tidak memasukkan kuantitas retur sama sekali. Silakan isi minimal 1 barang / aset yang akan dikembalikan.',
                icon: 'warning',
                confirmButtonColor: '#ffc107',
                customClass: { confirmButton: 'text-dark fw-bold rounded-pill px-4' }
            });
            return;
        }

        Swal.fire({
            title: 'Proses Retur Barang?',
            text: "Barang / Aset akan langsung dikembalikan ke gudang.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ffc107',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="bi bi-check-circle me-1"></i> Ya, Kembalikan!',
            cancelButtonText: 'Batal',
            customClass: {
                confirmButton: 'text-dark fw-bold rounded-pill px-4',
                cancelButton: 'rounded-pill px-4'
            },
            borderRadius: '15px'
        }).then((result) => {
            if (result.isConfirmed) {
                let btn = document.getElementById('btnSubmitReturn');
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Memproses...';
                btn.disabled = true;
                form.submit();
            }
        });
    });
</script>
@endpush
