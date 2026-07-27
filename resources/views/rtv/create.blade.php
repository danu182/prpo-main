@extends('layouts.app')

@push('css')
<style>
    .rtv-header-card { border-left: 5px solid #dc3545 !important; }
    .item-row.active-return { background-color: rgba(220, 53, 69, 0.05); }
    .sn-checkbox-wrapper { max-height: 120px; overflow-y: auto; }
</style>
@endpush

@section('content')
<div class="container pb-5 text-dark">

    <div class="mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-box-arrow-up-right me-2 text-danger"></i>Form Return to Vendor (RTV)</h4>
            <div class="mt-1 text-muted small">Retur barang berdasarkan Penerimaan <strong class="text-primary">{{ $gr->gr_number }}</strong></div>
        </div>
        <a href="{{ route('gr.index') }}" class="bg-white shadow-sm btn btn-outline-secondary rounded-pill fw-bold">
            <i class="bi bi-arrow-left me-1"></i> Batal
        </a>
    </div>

    <form action="{{ route('rtv.store', $gr->gr_number) }}" method="POST" id="rtvForm" enctype="multipart/form-data">
        @csrf

        {{-- 1. INFO REFERENSI DOKUMEN --}}
        <div class="mb-4 border-0 shadow-sm card rounded-4 rtv-header-card bg-light">
            <div class="p-4 card-body">
                <div class="row g-3">
                    <div class="col-md-4 border-end">
                        <label class="mb-1 text-muted small fw-bold text-uppercase d-block">Referensi GR</label>
                        <span class="fw-bold text-dark fs-6">{{ $gr->gr_number }}</span>
                    </div>
                    <div class="col-md-4 border-end">
                        <label class="mb-1 text-muted small fw-bold text-uppercase d-block">Referensi PO</label>
                        <span class="fw-bold text-primary fs-6">{{ $gr->po->po_number }}</span>
                    </div>
                    <div class="col-md-4">
                        <label class="mb-1 text-muted small fw-bold text-uppercase d-block">Vendor / Supplier</label>
                        <span class="fw-bold text-dark fs-6">{{ $gr->po->vendor->name }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- 2. INFO HEADER RETUR & LAMPIRAN --}}
        <div class="mb-4 border-0 shadow-sm card rounded-4">
            <div class="py-3 bg-white card-header border-bottom">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-card-text me-2 text-danger"></i>Detail Pengiriman Retur</h6>
            </div>
            <div class="p-4 card-body">
                <div class="row g-4">
                    <div class="col-md-3">
                        <label class="mb-1 fw-bold small text-muted">Tanggal Retur Fisik <span class="text-danger">*</span></label>
                        <input type="date" name="return_date" class="shadow-sm form-control" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="mb-1 fw-bold small text-muted">No. Surat Jalan Keluar (Opsional)</label>
                        <input type="text" name="delivery_note_number" class="shadow-sm form-control" placeholder="Cth: SJ-OUT/2026/001">
                        <small class="text-muted" style="font-size: 0.7rem;">Nomor SJ untuk dibawa supir vendor.</small>
                    </div>

                    {{-- WADAH MULTI UPLOAD LAMPIRAN --}}
                    <div class="col-md-5">
                        <div class="mb-2 d-flex justify-content-between align-items-center">
                            <label class="mb-0 fw-bold small text-muted">Foto Rusak / Bukti Retur</label>
                            <button type="button" class="px-2 py-0 btn btn-sm btn-outline-danger" style="font-size: 0.7rem;" onclick="addRtvFileRow()">
                                <i class="bi bi-plus"></i> Tambah File
                            </button>
                        </div>
                        <div id="rtvFileContainer">
                            <div class="mb-1 shadow-sm input-group input-group-sm rtv-file-row">
                                <input type="file" name="attachments[]" class="bg-white form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png">
                                <button type="button" class="btn btn-outline-danger" onclick="removeRtvFileRow(this)"><i class="bi bi-x"></i></button>
                            </div>
                        </div>
                    </div>

                    <div class="mt-2 col-12">
                        <label class="mb-1 fw-bold small text-muted">Catatan Umum</label>
                        <input type="text" name="notes" class="shadow-sm form-control" placeholder="Catatan tambahan retur...">
                    </div>
                </div>
            </div>
        </div>

        {{-- 3. TABEL ITEM YANG BISA DIRETUR --}}
        <div class="mb-4 border-0 shadow-sm card rounded-4">
            <div class="py-3 bg-white card-header border-bottom">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-box-seam me-2 text-danger"></i>Pilih Barang yang Diretur</h6>
            </div>
            <div class="p-0 card-body table-responsive">
                <table class="table mb-0 align-middle table-hover">
                    <thead class="bg-light text-muted small text-uppercase fw-bold">
                        <tr>
                            <th class="py-3 ps-4" width="25%">Nama Barang & Kode</th>
                            <th class="py-3 text-center" width="12%">Pernah Diterima</th>
                            <th class="py-3 text-center" width="25%">Qty & Satuan Retur <span class="text-danger">*</span></th>
                            <th class="py-3 pe-4" width="38%">Alasan & Pilih Serial Number</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($returnableItems as $index => $item)
                        @php
                            $masterItem = $item->item;
                            $baseUomName = optional($masterItem->uom)->name ?? 'PCS';

                            // 🔥 PENENTUAN NAMA SPESIFIK VS NAMA MASTER 🔥
                            $masterName = optional($masterItem)->name ?? '-';
                            // Tarik nama spesifik dari dokumen PO
                            $specificName = optional($item->purchaseOrderItem)->item_name ?? $masterName;

                            // Ambil data yang sudah dirapikan dari Controller
                            $grUomText = $item->gr_uom_text;
                            $grConvRate = $item->gr_conv_rate;
                            $maxReturnable = (float) $item->max_returnable;

                            // Hitung Max Base Qty (Eceran maksimal yang bisa diretur)
                            $maxBaseQty = $maxReturnable * $grConvRate;

                            $cleanGrUomName = trim(preg_replace('/ \(Isi:.*\)/i', '', $grUomText));

                            $isSnItem = ($masterItem->is_trackable || $masterItem->is_asset);
                            $hasSnList = !empty($item->available_sn_list);
                            $requiresSnAction = ($isSnItem && $hasSnList);
                        @endphp

                        <tr class="item-row" id="row_{{ $item->id }}">
                            <td class="py-3 ps-4">
                                {{-- 🔥 MENAMPILKAN NAMA SPESIFIK & MASTER SECARA BERSUSUN 🔥 --}}
                                <h6 class="mb-0 fw-bold text-dark text-uppercase">{{ $specificName }}</h6>

                                @if(strtolower(trim($specificName)) !== strtolower(trim($masterName)))
                                    <div class="mb-1 text-muted" style="font-size: 0.75rem;">
                                        <i class="bi bi-box me-1"></i>Master: {{ $masterName }}
                                    </div>
                                @endif

                                <span class="mt-1 border badge bg-secondary-subtle text-secondary border-secondary-subtle">{{ $masterItem->code }}</span>

                                {{-- 🔥 PERBAIKAN: Gunakan $item->id, bukan $index 🔥 --}}
                                <input type="hidden" name="items[{{ $item->id }}][gr_item_id]" value="{{ $item->id }}">

                                @if($requiresSnAction)
                                    <span class="mt-1 border badge bg-warning-subtle text-warning-emphasis border-warning ms-1"><i class="bi bi-upc-scan me-1"></i>Pilih SN Wajib</span>
                                @endif
                            </td>

                            <td class="text-center fw-bold text-success fs-6">
                                {{ (float) $item->qty_received }} <br>
                                <span class="text-muted fw-normal" style="font-size: 0.65rem;">{{ $grUomText }}</span>
                                <input type="hidden" id="max_{{ $item->id }}" value="{{ $maxReturnable }}">
                            </td>

                            <td>
                                <div class="mb-1 shadow-sm input-group input-group-sm">
                                    {{-- 🔥 PERBAIKAN: Gunakan $item->id, bukan $index 🔥 --}}
                                    <input type="number" name="items[{{ $item->id }}][qty_returned]"
                                           id="qty-input-{{ $item->id }}"
                                           class="text-center form-control fw-bold text-danger border-danger qty-input"
                                           value="0" min="0" max="{{ $maxReturnable }}" step="any"
                                           placeholder="Maks: {{ $maxReturnable }}"
                                           {{ $requiresSnAction ? 'readonly' : "oninput=checkQty({$item->id})" }}>

                                    {{-- 🔥 PERBAIKAN: Gunakan $item->id, bukan $index 🔥 --}}
                                    <select name="items[{{ $item->id }}][uom]" class="form-select border-danger bg-danger-subtle text-danger fw-bold" style="max-width: 140px;" data-current-conv="{{ $grConvRate }}" onchange="changeUomRTV(this, {{ $item->id }}, {{ $maxBaseQty }})">
                                        <option value="{{ $grUomText }}" data-conv="{{ $grConvRate }}">{{ $grUomText }} [GR]</option>
                                        @if(strtolower($baseUomName) !== strtolower($cleanGrUomName))
                                            <option value="{{ $baseUomName }}" data-conv="1">{{ $baseUomName }} (Ecer)</option>
                                        @endif
                                    </select>
                                </div>
                                <div class="mt-1 text-center text-muted" style="font-size: 0.65rem;" id="help-text-{{ $item->id }}">
                                    Maks: <strong class="text-danger" id="max-val-{{ $item->id }}">{{ $maxReturnable }}</strong> <span id="uom-text-{{ $item->id }}">{{ $cleanGrUomName }}</span>
                                </div>
                            </td>

                            <td class="py-3 pe-4">
                                {{-- 🔥 PERBAIKAN: Gunakan $item->id, bukan $index 🔥 --}}
                                <select name="items[{{ $item->id }}][return_reason_id]" id="reason_{{ $item->id }}" class="mb-2 form-select form-select-sm reason-select" disabled required>
                                    <option value="">-- Pilih Alasan Retur --</option>
                                    @foreach($reasons as $reason)
                                        <option value="{{ $reason->id }}">{{ $reason->name }}</option>
                                    @endforeach
                                </select>

                                {{-- 🔥 PERBAIKAN: Gunakan $item->id, bukan $index 🔥 --}}
                                <input type="text" name="items[{{ $item->id }}][notes]" class="mb-2 form-control form-control-sm" placeholder="Catatan item (opsional)...">

                                @if($requiresSnAction)
                                    <div class="p-2 border rounded bg-light border-warning-subtle sn-checkbox-wrapper">
                                        <div class="mb-1 fw-bold text-dark" style="font-size: 0.7rem;"><i class="bi bi-upc-scan me-1"></i>Centang Unit yang Rusak/Retur:</div>
                                        @foreach($item->available_sn_list as $sn)
                                            <div class="mb-1 form-check">
                                                {{-- 🔥 PERBAIKAN: Gunakan $item->id, bukan $index 🔥 --}}
                                                <input class="form-check-input sn-check-{{ $item->id }}" type="checkbox" name="items[{{ $item->id }}][sn][]" value="{{ $sn }}" id="sn_{{ $item->id }}_{{ $loop->index }}" onchange="countCheckedSn({{ $item->id }})">
                                                <label class="form-check-label fw-bold text-primary" style="font-size: 0.75rem;" for="sn_{{ $item->id }}_{{ $loop->index }}">
                                                    {{ $sn }}
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                @elseif($isSnItem && !$hasSnList)
                                    <div class="mt-1 text-warning small fst-italic"><i class="bi bi-info-circle"></i> Item wajib lacak, tapi diretur sbg stok biasa (Data Sebelum Peraturan).</div>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ACTION BAR --}}
        <div class="p-3 bg-white shadow-lg border-top fixed-bottom" style="z-index: 1050;">
            <div class="container gap-3 d-flex justify-content-end align-items-center">
                <span class="text-muted small me-3"><i class="bi bi-info-circle me-1"></i>Centang SN atau isi Qty pada barang yang diretur.</span>
                <button type="button" class="px-5 shadow btn btn-danger rounded-pill fw-bold" id="btnSubmitRTV">
                    <i class="bi bi-box-arrow-up-right me-1"></i> Proses Retur
                </button>
            </div>
        </div>
        <div style="height: 100px;"></div>

    </form>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // SCRIPT MULTI UPLOAD LAMPIRAN
    function addRtvFileRow() {
        const container = document.getElementById('rtvFileContainer');
        const div = document.createElement('div');
        div.className = 'input-group input-group-sm mb-1 rtv-file-row shadow-sm';
        div.innerHTML = `
            <input type="file" name="attachments[]" class="bg-white form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png">
            <button type="button" class="btn btn-outline-danger" onclick="removeRtvFileRow(this)"><i class="bi bi-x"></i></button>
        `;
        container.appendChild(div);
    }
    function removeRtvFileRow(btn) {
        if (document.querySelectorAll('.rtv-file-row').length > 1) btn.closest('.rtv-file-row').remove();
        else btn.closest('.rtv-file-row').querySelector('input[type="file"]').value = '';
    }

    // PENGHITUNG OTOMATIS JIKA CHECKBOX SN DICENTANG
    function countCheckedSn(itemId) {
        let checkboxes = document.querySelectorAll('.sn-check-' + itemId + ':checked');
        let totalBaseDicentang = checkboxes.length;

        let selectElement = document.querySelector(`select[name="items[${itemId}][uom]"]`);
        let currentConvRate = parseFloat(selectElement.getAttribute('data-current-conv')) || 1;

        let autoQty = totalBaseDicentang / currentConvRate;

        let qtyInput = document.getElementById('qty-input-' + itemId);
        qtyInput.value = autoQty;
        checkQty(itemId);
    }

    // UPDATE MAX PLACEHOLDER OTOMATIS JIKA UOM DIGANTI
    function changeUomRTV(selectElement, itemId, sisaBaseQty) {
        let selectedOption = selectElement.options[selectElement.selectedIndex];
        let newConvRate = parseFloat(selectedOption.getAttribute('data-conv')) || 1;
        let oldConvRate = parseFloat(selectElement.getAttribute('data-current-conv')) || 1;

        let qtyInput = document.getElementById(`qty-input-${itemId}`);
        let currentQty = parseFloat(qtyInput.value) || 0;

        let newQty = (currentQty * oldConvRate) / newConvRate;
        let newMaxVal = Math.floor(sisaBaseQty / newConvRate);

        qtyInput.setAttribute('max', newMaxVal);
        qtyInput.setAttribute('placeholder', 'Maks: ' + newMaxVal);

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

        let cleanUomName = selectedOption.text.replace(/ \[GR\]|\(Ecer\)/gi, '').trim();
        if (helpUom) helpUom.innerText = cleanUomName;

        checkQty(itemId);
    }

    function checkQty(itemId) {
        const qtyInput = document.getElementById('qty-input-' + itemId);
        const maxQty = parseFloat(document.getElementById('max_' + itemId).value);
        const reasonSelect = document.getElementById('reason_' + itemId);
        const row = document.getElementById('row_' + itemId);

        let currentQty = parseFloat(qtyInput.value) || 0;

        if (currentQty > maxQty) {
            qtyInput.value = maxQty;
            currentQty = maxQty;
        }

        if (currentQty > 0) {
            reasonSelect.disabled = false;
            row.classList.add('active-return');
        } else {
            reasonSelect.disabled = true;
            reasonSelect.value = '';
            row.classList.remove('active-return');
        }
    }

    document.getElementById('btnSubmitRTV').addEventListener('click', function() {
        const form = document.getElementById('rtvForm');
        let hasReturn = false;

        document.querySelectorAll('.qty-input').forEach(function(input) {
            if ((parseFloat(input.value) || 0) > 0) hasReturn = true;
        });

        if (!hasReturn) {
            Swal.fire('Peringatan!', 'Minimal 1 barang harus diisi angkanya / dicentang SN-nya untuk diproses retur.', 'warning');
            return;
        }

        if (!form.checkValidity()) { form.reportValidity(); return; }

        Swal.fire({
            title: 'Konfirmasi Retur',
            html: "Barang akan ditarik dari stok dan dikembalikan ke vendor.<br><br><small class='text-danger'>*Status SN yang dicentang akan menjadi Returned dan ditarik dari Gudang.</small>",
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d', confirmButtonText: 'Ya, Proses Retur!',
            borderRadius: '15px'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({ title: 'Memproses...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                form.submit();
            }
        });
    });
</script>
@endpush
