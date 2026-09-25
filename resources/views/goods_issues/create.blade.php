@extends('layouts.app')

@section('content')
<div class="pb-5 container-fluid text-dark">
    <div class="gap-3 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-box-arrow-up-right me-2 text-danger"></i>Form Pengeluaran Barang</h4>
            <div class="mt-1 text-muted small">Serahkan stok, aset, atau inventaris kepada karyawan operasional.</div>
        </div>
        <a href="{{ route('goods-issues.index') }}" class="bg-white shadow-sm btn btn-outline-secondary rounded-pill fw-bold">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar
        </a>
    </div>

    <form action="{{ route('goods-issues.store') }}" method="POST" id="giForm">
        @csrf

        @if ($errors->any())
            <div class="mb-4 shadow-sm alert alert-danger rounded-4">
                <div class="mb-1 fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i>Gagal Menyimpan:</div>
                <ul class="mb-0 small">
                    @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                </ul>
            </div>
        @endif

        <div class="mb-4 border-0 shadow-sm card rounded-4">
            <div class="p-4 card-body">
                <div class="row g-4">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-dark">Tanggal Keluar <span class="text-danger">*</span></label>
                        <input type="date" name="issue_date" class="shadow-sm form-control border-secondary-subtle" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-dark">Penerima / Karyawan <span class="text-danger">*</span></label>
                        <select name="requester_name" id="requester_select" class="form-select select2-init border-secondary-subtle" required onchange="autoFillDept()">
                            <option value="">-- Pilih Karyawan --</option>
                            @foreach($users as $user)
                                <option value="{{ $user->name }}" data-dept="{{ optional($user->department)->name ?? 'Tanpa Dept' }}">{{ $user->name }} • {{ optional($user->company)->code ?? 'HO' }} ({{ optional($user->department)->name ?? 'Tanpa Dept' }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-dark">Asal Gudang <span class="text-danger">*</span></label>
                        <select name="warehouse_id" id="warehouse_id" class="border-2 shadow-sm form-select fw-bold text-danger border-danger" required onchange="resetAllItems()">
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-dark">Departemen / Proyek</label>
                        <input type="text" name="department" id="department_input" class="bg-light form-control border-secondary-subtle" placeholder="Cth: Finance / Proyek A..." readonly>
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-4 border-0 shadow-sm card rounded-4 border-top border-danger border-3">
            <div class="px-4 py-3 bg-white card-header border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-cart me-2 text-danger"></i>Rincian Keranjang Pengeluaran</h6>
                <button type="button" class="px-3 btn btn-sm btn-primary rounded-pill fw-bold" onclick="addRow()"><i class="bi bi-plus-lg me-1"></i> Tambah Barang</button>
            </div>

            <div class="p-0 card-body table-responsive">
                <table class="table mb-0 align-middle table-hover" id="itemsTable">
                    <thead class="bg-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4" width="30%">Cari Barang / Aset</th>
                            <th width="25%">Pilih Batch <span class="fw-normal text-primary" style="font-size: 0.65rem;" title="Live Search Aktif">(🔍 Live Search Aktif)</span></th>
                            <th width="20%">Kuantitas / Pemilihan Unit (SN)</th>
                            <th width="20%">Catatan Ref.</th>
                            <th width="5%" class="text-center pe-4"><i class="bi bi-trash"></i></th>
                        </tr>
                    </thead>
                    <tbody id="itemsContainer">
                        {{-- Baris akan ditambahkan via JS --}}
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mb-4 border-0 shadow-sm card rounded-4">
            <div class="p-4 card-body">
                <label class="form-label small fw-bold text-dark"><i class="bi bi-chat-left-text me-2 text-primary"></i>Catatan Umum / Tujuan Pengeluaran</label>
                <textarea name="notes" class="shadow-sm bg-light form-control border-secondary-subtle" rows="3" placeholder="Cth: Pemberian fasilitas laptop baru untuk tim lapangan..."></textarea>

                <div class="mt-4 d-flex justify-content-end">
                    <button type="button" onclick="confirmSubmit()" class="px-5 shadow-sm btn btn-danger fw-bold rounded-pill">
                        <i class="bi bi-send-check me-2"></i> Konfirmasi Pengeluaran
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<style>
    .select2-container--bootstrap-5 .select2-selection { border-radius: 8px; font-size: 0.85rem; border-color: #dee2e6; min-height: 38px; }
    .select2-container--bootstrap-5.select2-container--focus .select2-selection { border-color: #0d6efd; box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15); }
    .item-row { transition: all 0.2s; }
    .item-row:hover { background-color: #f8f9fa; }
    .select2-container--bootstrap-5 .select2-dropdown .select2-results__options .select2-results__option { font-size: 0.8rem; }
</style>
@endpush

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    let rowCount = 0;

    $(document).ready(function() {
        $('.select2-init').select2({ theme: 'bootstrap-5', width: '100%' });
        addRow();
    });

    function autoFillDept() {
        let selectedOption = $('#requester_select').find(':selected');
        let dept = selectedOption.data('dept');
        $('#department_input').val(dept || '');
    }

    function resetAllItems() {
        $('#itemsContainer').empty();
        addRow();
        Swal.fire({
            toast: true, position: 'top-end', icon: 'info', title: 'Keranjang di-reset karena perubahan gudang asal.', showConfirmButton: false, timer: 3000
        });
    }

    function addRow() {
        rowCount++;
        const tr = document.createElement('tr');
        tr.className = 'item-row border-bottom';
        tr.id = `row_${rowCount}`;

        tr.innerHTML = `
            <td class="py-3 ps-4">
                <select name="items[${rowCount}][item_id]" class="form-select form-select-sm item-select" required></select>
                <div class="mt-2 text-muted small stock-info" id="stock_info_${rowCount}"></div>
                <div class="mt-2 input-group input-group-sm d-none" id="specific_name_container_${rowCount}">
                    <span class="input-group-text bg-light text-muted" style="font-size: 0.7rem;">Nama Spesifik (Pilih dari Riwayat)</span>
                    <select name="items[${rowCount}][item_name]" class="form-select fw-bold text-primary name-select" id="specific_name_${rowCount}"></select>
                </div>
            </td>
            <td class="py-3">
                <select name="items[${rowCount}][inventory_stock_id]" class="form-select form-select-sm batch-select fw-bold border-info-subtle text-info-emphasis" id="batch_${rowCount}"></select>
            </td>
            <td class="py-3">
                <div class="qty-container" id="qty_container_${rowCount}">
                    <label class="mb-1 fw-bold text-dark" style="font-size: 0.65rem;" id="qty_label_${rowCount}">Kuantitas Keluar</label>
                    <div class="mb-1 shadow-sm input-group input-group-sm">
                        <input type="number" name="items[${rowCount}][qty_issued]" class="text-center form-control fw-bold text-danger qty-input" id="qty_${rowCount}" step="0.01" min="0.01" oninput="checkMax(${rowCount})" data-global-max="0" required>
                        <span class="input-group-text uom-text fw-bold bg-light" id="uom_${rowCount}">PCS</span>
                    </div>
                    <div class="text-muted" style="font-size: 0.65rem;">Max: <span class="max-qty text-danger fw-bold" id="max_qty_${rowCount}">0</span></div>
                </div>

                <div class="mt-2 sn-container d-none" id="sn_container_${rowCount}">
                    <label class="mb-1 fw-bold text-warning-emphasis" style="font-size: 0.65rem;"><i class="bi bi-upc-scan me-1"></i>Pilih Serial Number (Wajib)</label>
                    <select class="form-select form-select-sm sn-select" id="sn_${rowCount}" multiple="multiple"></select>
                </div>
            </td>
            <td class="py-3">
                <textarea name="items[${rowCount}][notes]" class="form-control form-control-sm border-secondary-subtle" rows="2" placeholder="Catatan opsional baris ini..."></textarea>
            </td>
            <td class="py-3 text-center pe-4">
                <button type="button" class="shadow-sm btn btn-outline-danger btn-sm rounded-circle" onclick="removeRow(this)"><i class="bi bi-trash"></i></button>
            </td>
        `;
        document.getElementById('itemsContainer').appendChild(tr);
        initSelect2(rowCount);
    }

    function removeRow(btn) {
        if(document.querySelectorAll('.item-row').length > 1) {
            btn.closest('tr').remove();
        } else {
            Swal.fire({ toast: true, position: 'top-end', icon: 'warning', title: 'Minimal 1 barang harus ada!', showConfirmButton: false, timer: 3000 });
        }
    }

    function checkMax(rowId) {
        let input = document.getElementById(`qty_${rowId}`);
        let max = parseFloat(input.getAttribute('max')) || 0;
        let val = parseFloat(input.value) || 0;

        if (val > max) {
            input.value = max;
        }
    }

    function initSnSelect2(rowId, ajaxUrl, placeholderText) {
        let snSelect = $(`#sn_${rowId}`);
        if (snSelect.hasClass("select2-hidden-accessible")) {
            snSelect.select2('destroy');
        }
        snSelect.empty();
        snSelect.select2({
            theme: 'bootstrap-5',
            placeholder: placeholderText,
            ajax: {
                url: ajaxUrl,
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        search: params.term, // 🔍 Ini yang memungkinkan Live Search langsung dari ketikan user
                        item_id: $(`#row_${rowId} .item-select`).val(),
                        warehouse_id: $('#warehouse_id').val()
                    };
                },
                processResults: function(data) {
                    return { results: data };
                }
            }
        });
    }

    function initSelect2(rowId) {
        $(`#row_${rowId} .item-select`).select2({
            theme: 'bootstrap-5',
            placeholder: 'Ketik Kode / Nama Barang...',
            ajax: {
                url: '{{ route("goods-issues.search_items") }}',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        search: params.term,
                        warehouse_id: $('#warehouse_id').val()
                    };
                },
                processResults: function(data) {
                    return { results: data };
                }
            }
        }).on('select2:select', function(e) {
            let data = e.params.data;

            let isAsset = (data.is_asset == 1 || data.is_asset === true || data.is_asset === '1');
            let isTrackable = (data.is_trackable == 1 || data.is_trackable === true || data.is_trackable === '1');

            let hasAssetStock = (parseInt(data.available_asset) > 0);
            let hasBulkStock = (parseInt(data.available_bulk) > 0);

            $(`#stock_info_${rowId}`).html(`<span class="shadow-sm badge bg-success">Stok Tersedia: ${data.stock}</span>`);
            $(`#uom_${rowId}`).text(data.base_uom_name);
            $(`#max_qty_${rowId}`).text(data.stock);

            $(`#qty_${rowId}`).attr('data-global-max', data.stock);
            $(`#qty_${rowId}`).attr('max', data.stock).val('');

            let nameSelect = $(`#specific_name_${rowId}`);
            nameSelect.empty();
            if(data.historical_names && data.historical_names.length > 0) {
                data.historical_names.forEach(function(name) {
                    let newOption = new Option(name, name, false, false);
                    nameSelect.append(newOption);
                });
            } else {
                nameSelect.append(new Option(data.raw_name, data.raw_name, true, true));
            }
            nameSelect.trigger('change');
            $(`#specific_name_container_${rowId}`).removeClass('d-none');

            // 1. Reset Semua Tampilan & Matikan Event Lama
            $(`#qty_label_${rowId}`).text('Kuantitas Keluar');
            $(`#qty_container_${rowId}`).removeClass('d-none');
            $(`#qty_${rowId}`).attr('required', 'required').removeAttr('readonly').removeClass('bg-light text-muted');

            $(`#sn_container_${rowId}`).addClass('d-none');
            $(`#sn_${rowId}`).removeAttr('required').off('change.auto_sync'); // Matikan auto-sync sblmnya
            $(`#batch_${rowId}`).closest('td').find('.select2').removeClass('opacity-50').css('pointer-events', 'auto');

            // 2. LOGIKA KEPUTUSAN
            if (isAsset && hasAssetStock) {
                // KONDISI A: ASET TETAP MURNI (Sembunyikan Qty)
                $(`#qty_container_${rowId}`).addClass('d-none');
                $(`#qty_${rowId}`).removeAttr('required');

                $(`#sn_container_${rowId}`).removeClass('d-none');
                $(`#sn_${rowId}`).attr('required', 'required').attr('name', `items[${rowId}][asset_ids][]`);
                $(`#batch_${rowId}`).closest('td').find('.select2').addClass('opacity-50').css('pointer-events', 'none');

                initSnSelect2(rowId, '{{ route("goods-issues.search_assets") }}', 'Ketik / Cari Aset Tetap di sini...');

            } else if (hasBulkStock && (isTrackable || isAsset)) {
                // KONDISI B: BARANG BER-SN (Qty MUNCUL TAPI DIKUNCI, AUTO-SYNC AKTIF!)
                $(`#qty_label_${rowId}`).html('Kuantitas Keluar <span class="badge bg-warning text-dark ms-1" style="font-size:0.55rem;">(Otomatis)</span>');
                $(`#qty_${rowId}`).attr('readonly', 'readonly').addClass('bg-light text-muted').val('');

                $(`#sn_container_${rowId}`).removeClass('d-none');
                $(`#sn_${rowId}`).attr('required', 'required').attr('name', `items[${rowId}][sn_list][]`);

                initSnSelect2(rowId, '{{ route("goods-issues.search_sns") }}', 'Ketik Serial Number di sini untuk mencari...');

                // 🔥 KUNCI: SYNC QTY DENGAN JUMLAH SN YANG DIPILIH 🔥
                $(`#sn_${rowId}`).on('change.auto_sync', function() {
                    let count = $(this).val() ? $(this).val().length : '';
                    $(`#qty_${rowId}`).val(count);
                    checkMax(rowId);
                });

            } else {
                // KONDISI C: BARANG BIASA
                $(`#batch_${rowId}`).val(null).trigger('change');
            }
        });

        // 🔥 SELECT2 BATCH MODE
        $(`#row_${rowId} .batch-select`).select2({
            theme: 'bootstrap-5',
            placeholder: '⚡ Mode Otomatis (FIFO)',
            allowClear: true,
            minimumResultsForSearch: 0,
            ajax: {
                url: '{{ route("goods-issues.search_batches") }}',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        search: params.term,
                        item_id: $(`#row_${rowId} .item-select`).val(),
                        warehouse_id: $('#warehouse_id').val()
                    };
                },
                processResults: function(data) {
                    return { results: data };
                }
            }
        }).on('select2:select', function(e) {
            let data = e.params.data;
            let qtyInput = $(`#qty_${rowId}`);

            if (data.id === '') {
                let globalMax = qtyInput.attr('data-global-max');
                $(`#max_qty_${rowId}`).text(globalMax);
                qtyInput.attr('max', globalMax);
            } else {
                $(`#max_qty_${rowId}`).text(data.sisa);
                qtyInput.attr('max', data.sisa);
            }
            checkMax(rowId);
        }).on('select2:clear', function(e) {
             let qtyInput = $(`#qty_${rowId}`);
             let globalMax = qtyInput.attr('data-global-max');
             $(`#max_qty_${rowId}`).text(globalMax);
             qtyInput.attr('max', globalMax);
        });
    }

    function confirmSubmit() {
        const form = document.getElementById('giForm');
        let hasReceipt = false;

        document.querySelectorAll('.qty-input').forEach(function(input) {
            if ((parseFloat(input.value) || 0) > 0) {
                hasReceipt = true;
            }
        });

        let hasAsset = document.querySelectorAll('select[name*="[asset_ids][]"]').length > 0;

        if (!hasReceipt && !hasAsset) {
            Swal.fire({
                icon: 'warning',
                title: 'Perhatian!',
                text: 'Minimal 1 barang harus diisi Qty Keluarnya!',
                confirmButtonColor: '#198754'
            });
            return;
        }

        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        Swal.fire({
            title: 'Konfirmasi Pengeluaran',
            text: "Barang akan keluar dari gudang dan stok akan terpotong secara permanen. Lanjutkan?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Keluarkan!',
            cancelButtonText: 'Batal',
            borderRadius: '15px'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({ title: 'Memproses...', text: 'Memotong stok dan mencatat referensi...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
                form.submit();
            }
        });
    }
</script>
@endpush
