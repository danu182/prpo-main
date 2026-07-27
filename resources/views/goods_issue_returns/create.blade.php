@extends('layouts.app')

@push('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<style>
    /* 🔥 Kustomisasi Select2 Premium 🔥 */
    .select2-container--bootstrap-5 .select2-selection {
        border-radius: 8px;
        border-color: #dee2e6;
        min-height: 38px;
        font-size: 0.875rem;
    }
    .select2-container--bootstrap-5 .select2-selection--multiple {
        padding-bottom: 2px;
    }
    /* Tag Biru untuk Aset */
    .asset-select-container .select2-selection__choice {
        background-color: #0dcaf0 !important;
        color: #fff !important;
        border: none !important;
        border-radius: 6px !important;
        font-size: 0.75rem !important;
        font-weight: 600 !important;
        margin-top: 4px !important;
    }
    /* Tag Kuning untuk SN Lacak */
    .sn-select-container .select2-selection__choice {
        background-color: #ffc107 !important;
        color: #000 !important;
        border: none !important;
        border-radius: 6px !important;
        font-size: 0.75rem !important;
        font-weight: 600 !important;
        margin-top: 4px !important;
    }
    .select2-results__option { font-size: 0.85rem; padding: 8px 12px; }
</style>
@endpush

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
                        <input type="text" name="returned_by_name" class="shadow-sm form-control bg-light" value="{{ $gi->requester_name }}" required readonly>
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
                            $baseUomName = optional($masterItem->uom)->name ?? 'PCS';

                            // 🔥 LOGIKA BARU: CEK HISTORI NYATA TRANSAKSI LAMA 🔥
                            $hasHistoryAsset = isset($item->held_assets) && count($item->held_assets) > 0;
                            $hasHistorySn = !empty($item->held_sns);

                            // Jika punya histori Aset, paksa jadi Aset. Jika punya histori SN, paksa jadi SN.
                            if ($hasHistoryAsset) {
                                $isAsset = true; $isTrackable = false;
                            } elseif ($hasHistorySn) {
                                $isAsset = false; $isTrackable = true;
                            } else {
                                // Jika tidak ada histori sama sekali, baru ikuti Master Barang
                                $isAsset = optional($masterItem)->is_asset;
                                $isTrackable = optional($masterItem)->is_trackable;
                            }

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
                            $maxBaseQty = $sisaBisaRetur * $giConvRate;
                        @endphp
                        <tr>
                            <td class="py-3 ps-4">
                                {{-- 🔥 TARIK NAMA DARI DOKUMEN GI LAMA 🔥 --}}
                                <strong class="text-dark">{{ $item->item_name ?? optional($masterItem)->name }}</strong>

                                <div class="mt-1 small text-muted">{{ optional($masterItem)->code }}</div>
                                @if($isAsset)
                                    <span class="mt-1 border badge bg-primary-subtle text-primary border-primary-subtle" style="font-size: 0.65rem;">[ASET TETAP]</span>
                                @endif
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
                                    <div class="asset-select-container">
                                        <select name="items[{{ $index }}][returned_asset_numbers][]" class="shadow-sm form-select border-info select-asset-return" multiple data-index="{{ $item->id }}">
                                            @foreach($item->held_assets ?? [] as $ast)
                                                <option value="{{ $ast->asset_number }}">{{ $ast->asset_number }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-text small text-info"><i class="bi bi-info-circle"></i> Pilih Aset yang dikembalikan.</div>
                                    <input type="hidden" name="items[{{ $index }}][qty_returned]" class="qty-hidden-{{ $item->id }} qty-retur-input" value="0">

                                @elseif($isTrackable)
                                    <div class="sn-select-container">
                                        <select name="items[{{ $index }}][returned_minor_sns][]" class="shadow-sm form-select border-warning select-asset-return" multiple data-index="{{ $item->id }}">
                                            @foreach($item->held_sns ?? [] as $sn)
                                                <option value="{{ $sn }}">{{ $sn }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-text small text-warning-emphasis"><i class="bi bi-upc-scan"></i> Pilih SN yang dikembalikan.</div>
                                    <input type="hidden" name="items[{{ $index }}][qty_returned]" class="qty-hidden-{{ $item->id }} qty-retur-input" value="0">

                                @else
                                    <div class="mb-1 shadow-sm input-group input-group-sm">
                                        <input type="number" name="items[{{ $index }}][qty_returned]" id="qty-input-{{ $item->id }}" class="text-center form-control qty-retur-input border-success fw-bold text-dark" max="{{ $sisaBisaRetur }}" min="0" step="any" value="0" oninput="checkQty({{ $item->id }})">
                                        <select name="items[{{ $index }}][uom]" class="form-select border-success bg-success-subtle text-dark fw-bold" style="max-width: 135px;" data-current-conv="{{ $giConvRate }}" onchange="changeUom(this, {{ $item->id }}, {{ $maxBaseQty }})">
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
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(document).ready(function() {
        // 1. Inisialisasi Select2 untuk pilihan Aset Tetap & Trackable SN
        $('.select-asset-return').select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: 'Klik untuk memilih unit yang diretur...'
        });

        // 2. 🔥 SIHIR UTAMA: Hitung otomatis Qty dari opsi SN / Aset yang dipilih 🔥
        $('.select-asset-return').on('change', function() {
            let itemId = $(this).data('index');
            let hiddenQtyInput = $(`.qty-hidden-${itemId}`);

            // Hitung berapa jumlah unit yang dicentang / dipilih oleh user
            let selectedCount = $(this).val() ? $(this).val().length : 0;

            // Masukkan angkanya ke input kuantitas tersembunyi agar terbaca saat form disubmit
            if(hiddenQtyInput.length) {
                hiddenQtyInput.val(selectedCount);
            }
        });
    });

    // 3. 🔥 FITUR UOM: Mengonversi maksimal stok secara realtime saat UOM diganti 🔥
    function changeUom(selectElement, itemId, maxBaseQty) {
        let selectedOption = selectElement.options[selectElement.selectedIndex];
        let newConvRate = parseFloat(selectedOption.getAttribute('data-conv')) || 1;
        let oldConvRate = parseFloat(selectElement.getAttribute('data-current-conv')) || 1;

        let qtyInput = document.getElementById(`qty-input-${itemId}`);
        let currentQty = parseFloat(qtyInput.value) || 0;

        let newQty = (currentQty * oldConvRate) / newConvRate;
        let newMaxVal = Math.floor(maxBaseQty / newConvRate);

        qtyInput.setAttribute('max', newMaxVal);

        if (currentQty > 0) {
            qtyInput.value = parseFloat(newQty.toFixed(2));
            if (parseFloat(qtyInput.value) > newMaxVal) qtyInput.value = newMaxVal;
        }

        selectElement.setAttribute('data-current-conv', newConvRate);

        let helpMax = document.getElementById(`max-val-${itemId}`);
        let helpUom = document.getElementById(`uom-text-${itemId}`);
        let hiddenMax = document.getElementById(`max_${itemId}`);

        if (helpMax) helpMax.innerText = newMaxVal;
        if (hiddenMax) hiddenMax.value = newMaxVal;

        let cleanUomName = selectedOption.text.replace(/ \[GI\]|\(Ecer\)/gi, '').trim();
        if (helpUom) helpUom.innerText = cleanUomName;

        checkQty(itemId);
    }

    // 4. Batasi Input agar tidak melewati Max (Untuk Barang Stok Biasa)
    function checkQty(itemId) {
        const qtyInput = document.getElementById('qty-input-' + itemId);
        const maxQtyEl = document.getElementById('max_' + itemId);

        if (qtyInput && maxQtyEl) {
            const maxQty = parseFloat(maxQtyEl.value) || 0;
            let currentQty = parseFloat(qtyInput.value) || 0;
            if (currentQty > maxQty) {
                qtyInput.value = maxQty;
            }
        }
    }

    // 5. Proses Submit dengan Validasi Ekstra
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
                text: 'Anda tidak memasukkan kuantitas retur sama sekali. Silakan isi / pilih minimal 1 unit barang yang akan dikembalikan.',
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
