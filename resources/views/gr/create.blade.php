@extends('layouts.app')

@section('content')
<div class="pb-5 container-fluid text-dark">

    {{-- HEADER HALAMAN --}}
    <div class="gap-3 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-box-seam me-2 text-success"></i>Penerimaan Barang (Goods Receipt)</h4>
            <div class="mt-1 text-muted small">
                Terima barang untuk PO <strong class="text-primary">{{ $po->po_number }}</strong> dari Vendor <strong class="text-dark">{{ optional($po->vendor)->name }}</strong>.
            </div>
        </div>
        <a href="{{ route('po.show', $po->po_number) }}" class="bg-white shadow-sm btn btn-outline-secondary rounded-pill fw-bold">
            <i class="bi bi-arrow-left me-1"></i> Batal
        </a>
    </div>

    <form action="{{ route('gr.store', $po->po_number) }}" method="POST" id="grForm" enctype="multipart/form-data">

        @if ($errors->any())
            <div class="mb-4 shadow-sm alert alert-danger">
                <h6 class="mb-2 fw-bold alert-heading">
                    <i class="fas fa-exclamation-triangle me-2"></i> Gagal Menyimpan! Mohon periksa kembali:
                </h6>
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @csrf

        {{-- 1. INFORMASI SURAT JALAN & PENERIMAAN --}}
        <div class="mb-4 border-0 border-4 shadow-sm card rounded-4 border-start border-success">
            <div class="p-4 card-body">
                <h6 class="mb-3 fw-bold text-dark"><i class="bi bi-truck me-2 text-success"></i>Informasi Kedatangan Barang</h6>

                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="mb-1 small fw-bold text-muted">Tanggal Terima <span class="text-danger">*</span></label>
                        <input type="date" name="receipt_date" class="shadow-sm form-control" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="mb-1 small fw-bold text-muted">No. Surat Jalan (Delivery Note) <span class="text-danger">*</span></label>
                        <input type="text" name="delivery_note_number" class="shadow-sm form-control" placeholder="Contoh: SJ-889021..." required>
                    </div>
                    <div class="col-md-5">
                        <div class="mb-2 d-flex justify-content-between align-items-center">
                            <label class="mb-0 small fw-bold text-muted">Lampiran (Foto/Surat Jalan)</label>
                            <button type="button" class="px-2 py-0 btn btn-sm btn-outline-primary" style="font-size: 0.7rem;" onclick="addGrFileRow()">
                                <i class="bi bi-plus"></i> Tambah File
                            </button>
                        </div>

                        <div id="grFileContainer">
                            <div class="mb-1 shadow-sm input-group input-group-sm gr-file-row">
                                <input type="file" name="attachments[]" class="bg-white form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png">
                                <button type="button" class="btn btn-outline-danger" onclick="removeGrFileRow(this)"><i class="bi bi-x"></i></button>
                            </div>
                        </div>
                        <small class="text-muted" style="font-size: 0.65rem;">*Opsional. Maksimal 5MB per file.</small>
                    </div>

                    <div class="mt-4 col-md-12">
                        <label class="mb-1 small fw-bold text-muted">Catatan Penerimaan (Opsional)</label>
                        <input type="text" name="notes" class="shadow-sm form-control" placeholder="Contoh: Kardus sedikit basah, supir telat, dll...">
                    </div>
                </div>

            </div>
        </div>

        {{-- 2. TABEL ITEM YANG DITERIMA --}}
        <div class="mb-4 overflow-hidden border-0 shadow-sm card rounded-4">
            <div class="px-4 py-3 bg-white card-header border-bottom">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-list-check me-2 text-primary"></i>Rincian Barang Datang</h6>
            </div>
            <div class="p-0 card-body table-responsive">
                <table class="table mb-0 align-middle table-hover">
                    <thead class="bg-light text-muted small text-uppercase fw-bold border-bottom">
                        <tr>
                            <th class="py-3 ps-4" width="25%">Nama Barang & Status</th>
                            <th class="py-3 text-center" width="8%">Pesan</th>
                            <th class="py-3 text-center" width="8%">Sisa</th>
                            <th class="py-3 text-center" width="20%">Qty & Satuan Terima</th>
                            <th class="py-3" width="15%">Gudang & Kondisi</th>
                            <th class="py-3 pe-4" width="24%">Catatan Item & Serial Number</th>
                        </tr>
                    </thead>
                    <tbody>

                        @foreach($pendingItems as $index => $item)
                        @php
                            // 🔥 TARIK DATA YANG SUDAH MATANG DARI CONTROLLER 🔥
                            $masterItem = $item->item;
                            $baseUomName = $item->base_uom_name ?? 'PCS';
                            $sisaPo = (float)($item->sisa_po_uom ?? 0);
                            $poUomDisplay = $item->raw_po_uom ?? 'PCS';
                            $poConvFactor = (float)($item->po_conv_factor ?? 1);
                            $finalDesc = $item->final_description ?? '-'; // 👈 INI KUNCINYA!

                            $maxBaseQty = $sisaPo * $poConvFactor;
                            $isTrackable = $masterItem && ($masterItem->is_asset || $masterItem->is_trackable);
                        @endphp
                        <tr class="item-row" id="row_{{ $index }}">
                            <td class="py-3 ps-4">
                                <input type="hidden" name="items[{{ $item->id }}][item_id]" value="{{ $masterItem->id ?? '' }}">

                                <div class="mb-1 fw-bold text-dark">{{ $item->item_name ?? $masterItem->name ?? 'Unknown Item' }}</div>

                                <span class="border badge bg-secondary-subtle text-secondary border-secondary-subtle">{{ $masterItem->code ?? '-' }}</span>
                                @if($isTrackable)
                                    <span class="border badge bg-warning-subtle text-warning-emphasis border-warning"><i class="bi bi-upc-scan me-1"></i>Wajib Lacak (SN)</span>
                                @endif

                                {{-- 🔥 KOTAK CATATAN / ALOKASI CERDAS 🔥 --}}
                                @if(!empty($finalDesc) && $finalDesc !== '-' && $finalDesc !== ($masterItem->name ?? ''))
                                    <div class="p-2 mt-2 border rounded shadow-sm border-info-subtle bg-info-subtle text-dark" style="font-size: 0.75rem;">
                                        <div class="mb-1 fw-bold text-info-emphasis">
                                            <i class="bi bi-info-circle-fill me-1"></i> Catatan & Alokasi:
                                        </div>
                                        {!! nl2br(e($finalDesc)) !!}
                                    </div>
                                @endif
                            </td>
                            <td class="text-center fw-bold text-secondary fs-6">
                                {{ (float)$item->qty_ordered }} <br>
                                <span class="text-muted fw-normal" style="font-size: 0.65rem;">{{ $poUomDisplay }}</span>
                            </td>
                            <td class="text-center fw-bold text-danger fs-6">
                                <span id="sisa-text-{{ $index }}">{{ (float)$sisaPo }}</span> <br>
                                <span class="text-muted fw-normal" style="font-size: 0.65rem;">{{ $poUomDisplay }}</span>
                            </td>
                            <td>
                                <div class="mb-1 shadow-sm input-group input-group-sm">
                                    <input type="number" name="items[{{ $item->id }}][qty_received]" id="qty-input-{{ $index }}" class="text-center form-control fw-bold text-success qty-input" value="0" min="0" max="{{ $sisaPo }}" step="0.01" oninput="checkMaxQty({{ $index }}, {{ $isTrackable ? 'true' : 'false' }})">

                                    <select name="items[{{ $item->id }}][uom_id]" id="uom-select-{{ $index }}" class="form-select border-success bg-success-subtle text-success fw-bold uom-selector" style="max-width: 140px;" data-current-conv="{{ $poConvFactor }}" onchange="changeUom(this, {{ $index }}, {{ $maxBaseQty }})">

                                        <option value="{{ $item->uom_id ?? '' }}" data-name="{{ $poUomDisplay }}" data-conv="{{ $poConvFactor }}" selected>{{ $poUomDisplay }} [PO]</option>

                                        @if(strtolower($baseUomName) !== strtolower(trim(preg_replace('/ \(Isi:.*\)/i', '', $poUomDisplay))))
                                            <option value="" data-name="{{ $baseUomName }}" data-conv="1">{{ $baseUomName }} (Ecer)</option>
                                        @endif

                                        @if(optional($masterItem)->itemUoms)
                                            @foreach($masterItem->itemUoms as $altUom)
                                                @if($altUom->id != $item->uom_id && (float)$altUom->conversion_qty != $poConvFactor)
                                                    @php $valString = $altUom->uom_name . ' (Isi: ' . (float)$altUom->conversion_qty . ')'; @endphp
                                                    <option value="{{ $altUom->id }}" data-name="{{ $valString }}" data-conv="{{ (float)$altUom->conversion_qty }}">
                                                        {{ $valString }}
                                                    </option>
                                                @endif
                                            @endforeach
                                        @endif
                                    </select>

                                    <input type="hidden" name="items[{{ $item->id }}][uom]" id="uom-name-{{ $index }}" value="{{ $poUomDisplay }}">
                                </div>
                                <div class="mt-1 text-muted" style="font-size: 0.65rem;" id="help-text-{{ $index }}">Maks: <strong class="text-danger" id="max-val-{{ $index }}">{{ (float)$sisaPo }}</strong> <span id="uom-text-{{ $index }}">{{ $poUomDisplay }}</span></div>
                            </td>
                            <td>
                                @php
                                    // 🔥 LOGIKA AUTO-DETECT GUDANG DARI TEKS ALOKASI 🔥
                                    $expectedWhId = '';
                                    if ($item->is_smart_restock && !empty($finalDesc)) {
                                        foreach($warehouses as $w) {
                                            // Jika nama gudang (misal: "Gudang Utama") ada di dalam teks alokasi
                                            if (str_contains($finalDesc, $w->name)) {
                                                $expectedWhId = $w->id;
                                                break;
                                            }
                                        }
                                    }
                                @endphp

                                {{-- 🔥 DROPDOWN GUDANG PER BARIS (ANTI-NYASAR) 🔥 --}}
                                <label class="mb-1 fw-bold text-primary" style="font-size: 0.65rem;">Gudang Tujuan:</label>
                                <select name="items[{{ $item->id }}][warehouse_id]" class="mb-2 form-select form-select-sm border-primary wh-selector" data-expected-wh="{{ $expectedWhId }}" onchange="validateWarehouseSelection(this)" {{ $isTrackable ? 'required' : '' }}>
                                    @if(!$isTrackable && !optional($masterItem)->is_stockable)
                                        <option value="">-- Non-Stok --</option>
                                    @else
                                        <option value="">-- Pilih Gudang --</option>
                                        @foreach($warehouses as $indexWh => $wh)
                                            @php
                                                $isSelected = '';
                                                // Jika Smart Restock: Pilih otomatis jika nama gudang cocok
                                                if ($item->is_smart_restock && $expectedWhId == $wh->id) {
                                                    $isSelected = 'selected';
                                                } 
                                                // Jika PR Biasa: Default ke gudang pertama (Gudang Utama)
                                                elseif (!$item->is_smart_restock && $indexWh === 0) {
                                                    $isSelected = 'selected';
                                                }
                                            @endphp
                                            <option value="{{ $wh->id }}" {{ $isSelected }}>{{ $wh->name }}</option>
                                        @endforeach
                                    @endif
                                </select>

                                <label class="mb-1 fw-bold text-muted" style="font-size: 0.65rem;">Kondisi Fisik:</label>
                                <select name="items[{{ $item->id }}][condition_id]" class="form-select form-select-sm">
                                    @foreach($conditions as $cond)
                                        <option value="{{ $cond->id }}">{{ $cond->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="pe-4">
                                <input type="text" name="items[{{ $item->id }}][notes]" class="mb-2 form-control form-control-sm" placeholder="Catatan opsional...">

                                @if($isTrackable)
                                <div class="p-2 border rounded bg-warning-subtle border-warning d-none sn-container" id="sn-container-{{ $index }}">
                                    <div class="mb-1 d-flex justify-content-between align-items-center">
                                        <span class="fw-bold text-dark" style="font-size: 0.7rem;"><i class="bi bi-upc-scan me-1"></i>Input Serial Number:</span>
                                        <span class="badge bg-dark" style="font-size: 0.6rem; cursor:help;" title="Biarkan tulisan [AUTO] jika ingin sistem mengukir SN otomatis"><i class="bi bi-magic me-1"></i>Auto SN</span>
                                    </div>
                                    <div id="sn-inputs-{{ $index }}" class="sn-inputs-wrapper" style="max-height: 150px; overflow-y: auto;"></div>
                                </div>
                                @endif
                            </td>
                        </tr>
                        @endforeach

                    </tbody>
                </table>
            </div>
        </div>

        {{-- 3. PANEL EKSEKUSI --}}
        <div class="mb-4 overflow-hidden shadow-sm card border-success rounded-4">
            <div class="p-4 bg-opacity-50 card-body bg-light">
                <div class="mb-0 border-0 shadow-sm alert alert-success rounded-3 text-dark d-flex align-items-center">
                    <i class="bi bi-info-circle-fill fs-3 me-3 text-success"></i>
                    <div>Pastikan fisik barang dan satuannya sudah sesuai. Sistem akan otomatis menghitung konversi barang ke Kartu Stok & Registrasi Serial Number. <strong>Transaksi ini tidak dapat dibatalkan.</strong></div>
                </div>

                <div class="mt-4 d-flex justify-content-end">
                    <button type="button" onclick="confirmSubmit()" class="px-4 shadow-sm btn btn-success fw-bold rounded-pill">
                        <i class="bi bi-check2-all me-1"></i> Simpan Penerimaan Barang
                    </button>
                </div>
            </div>
        </div>

    </form>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function addGrFileRow() {
        const container = document.getElementById('grFileContainer');
        const div = document.createElement('div');
        div.className = 'input-group input-group-sm mb-1 gr-file-row shadow-sm';
        div.innerHTML = `
            <input type="file" name="attachments[]" class="bg-white form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png">
            <button type="button" class="btn btn-outline-danger" onclick="removeGrFileRow(this)"><i class="bi bi-x"></i></button>
        `;
        container.appendChild(div);
    }

    function removeGrFileRow(btn) {
        if (document.querySelectorAll('.gr-file-row').length > 1) {
            btn.closest('.gr-file-row').remove();
        } else {
            btn.closest('.gr-file-row').querySelector('input[type="file"]').value = '';
        }
    }

    function changeUom(selectElement, index, maxBaseQty) {
        let selectedOption = selectElement.options[selectElement.selectedIndex];
        let newConvRate = parseFloat(selectedOption.getAttribute('data-conv')) || 1;
        let oldConvRate = parseFloat(selectElement.getAttribute('data-current-conv')) || 1;
        let newUomName = selectedOption.getAttribute('data-name') || '';

        let uomNameInput = document.getElementById(`uom-name-${index}`);
        if(uomNameInput) uomNameInput.value = newUomName;

        let qtyInput = document.getElementById(`qty-input-${index}`);
        let currentQty = parseFloat(qtyInput.value) || 0;

        let newQty = (currentQty * oldConvRate) / newConvRate;
        let newMaxVal = parseFloat((maxBaseQty / newConvRate).toFixed(4));

        qtyInput.setAttribute('max', newMaxVal);
        qtyInput.value = parseFloat(newQty.toFixed(2));

        if (parseFloat(qtyInput.value) > newMaxVal) {
            qtyInput.value = newMaxVal;
        }

        selectElement.setAttribute('data-current-conv', newConvRate);

        let helpMax = document.getElementById(`max-val-${index}`);
        let helpUom = document.getElementById(`uom-text-${index}`);
        let sisaText = document.getElementById(`sisa-text-${index}`);

        if (helpMax) helpMax.innerText = newMaxVal;
        if (sisaText) sisaText.innerText = newMaxVal;

        let cleanUomName = selectedOption.text.replace(/ \[PO\]|\(Ecer\)/gi, '').trim();
        if (helpUom) helpUom.innerText = cleanUomName;

        let isTrackable = document.getElementById(`sn-container-${index}`) !== null;
        checkMaxQty(index, isTrackable);
    }

    function checkMaxQty(index, isTrackable) {
        const input = document.getElementById(`qty-input-${index}`);
        let val = parseFloat(input.value) || 0;
        const max = parseFloat(input.getAttribute('max')) || 0;

        if (val > max) {
            input.value = max;
            val = max;
        }

        if (isTrackable) {
            const snContainer = document.getElementById(`sn-container-${index}`);
            const snInputsWrapper = document.getElementById(`sn-inputs-${index}`);

            let selectElement = document.getElementById(`uom-select-${index}`);
            let convRate = parseFloat(selectElement.getAttribute('data-current-conv')) || 1;

            let jumlahKotakFisik = Math.floor(val * convRate);

            if (jumlahKotakFisik > 0) {
                snContainer.classList.remove('d-none');

                const existingInputs = snInputsWrapper.querySelectorAll('input');
                let existingValues = [];
                existingInputs.forEach(inp => existingValues.push(inp.value));

                snInputsWrapper.innerHTML = '';

                for (let i = 0; i < jumlahKotakFisik; i++) {
                    let oldVal = existingValues[i] !== undefined ? existingValues[i] : '[AUTO]';
                    let disabledAttr = oldVal === '[AUTO]' ? 'readonly' : '';
                    let focusEvent = oldVal === '[AUTO]' ? `onfocus="if(this.value==='[AUTO]'){this.value=''; this.removeAttribute('readonly');}" onblur="if(this.value===''){this.value='[AUTO]'; this.setAttribute('readonly', 'readonly');}"` : '';

                    let div = document.createElement('div');
                    div.className = 'mb-1';
                    div.innerHTML = `<input type="text" name="items[${input.name.match(/\d+/)[0]}][sn][]" class="shadow-sm form-control form-control-sm text-success fw-bold border-success-subtle" value="${oldVal}" placeholder="Scan atau ketik SN..." ${disabledAttr} ${focusEvent} required>`;
                    snInputsWrapper.appendChild(div);
                }
            } else {
                snContainer.classList.add('d-none');
                snInputsWrapper.innerHTML = '';
            }
        }
    }

    function confirmSubmit() {
        const form = document.getElementById('grForm');
        let hasReceipt = false;

        document.querySelectorAll('.qty-input').forEach(function(input) {
            if ((parseFloat(input.value) || 0) > 0) {
                hasReceipt = true;
            }
        });

        if (!hasReceipt) {
            Swal.fire({
                icon: 'warning',
                title: 'Perhatian!',
                text: 'Minimal 1 barang harus diisi Qty Terimanya!',
                confirmButtonColor: '#198754'
            });
            return;
        }

        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        Swal.fire({
            title: 'Konfirmasi Penerimaan',
            text: "Barang akan masuk ke gudang, kartu stok akan tercatat, dan transaksi ini tidak dapat dibatalkan. Lanjutkan?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#198754',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Simpan GR!',
            cancelButtonText: 'Batal',
            borderRadius: '15px'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Memproses...',
                    text: 'Menyimpan data dan mengukir Serial Number...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                form.submit();
            }
        });
    }

    // 🔥 FUNGSI ALARM SALAH GUDANG 🔥
    function validateWarehouseSelection(selectElement) {
        let expectedWhId = selectElement.getAttribute('data-expected-wh');
        let selectedWhId = selectElement.value;

        // Jika ini barang Smart Restock (punya target gudang) DAN user memilih gudang yang salah
        if (expectedWhId && selectedWhId !== expectedWhId && selectedWhId !== '') {
            Swal.fire({
                icon: 'error',
                title: 'Awas Salah Kamar! 🚨',
                text: 'Anda memilih Gudang yang BEDA dengan instruksi Rincian Alokasi! Ini bisa membuat kartu stok berantakan. Yakin ingin memindahkan barang ini?',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Tetap Pindahkan',
                cancelButtonText: 'Batal (Kembalikan)'
            }).then((result) => {
                if (!result.isConfirmed) {
                    // Kembalikan otomatis ke gudang yang seharusnya
                    selectElement.value = expectedWhId;
                }
            });
        }
    }


</script>
@endpush
