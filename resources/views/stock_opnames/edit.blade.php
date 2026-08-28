@extends('layouts.app')

@push('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<style>
    .select2-container--bootstrap-5 .select2-selection { border-radius: 8px; border-color: #dee2e6; min-height: 38px; }
    /* CSS UX Anti-Hantu untuk SN yang sudah dipilih */
    .hide-selected-sn .select2-results__option[aria-selected="true"] {
        display: none !important;
    }
</style>
@endpush

@section('content')
<div class="container-fluid pb-5 text-dark">
    <div class="mb-4">
        <h3 class="fw-bold text-dark mb-1"><i class="bi bi-pencil-square me-2 text-warning"></i> Input Hasil Hitung Fisik</h3>
        <div class="text-muted">Dokumen Opname: <strong class="text-primary">{{ $opname->document_number }}</strong></div>
    </div>

    @if(session('error'))
        <div class="alert alert-danger border-0 shadow-sm rounded-3 fw-bold"><i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}</div>
    @endif

    <div class="alert alert-info border-0 shadow-sm rounded-4 fw-bold p-3 mb-4 d-flex align-items-center border-start border-4 border-info">
        <i class="bi bi-shield-lock-fill fs-4 me-3 text-info"></i>
        <div>
            <span class="d-block text-dark">Mode Blind Count Aktif</span>
            <small class="fw-normal text-muted">Stok sistem disembunyikan. Pindahkan angka persis seperti yang tertulis di Lembar Kerja oleh staf gudang.</small>
        </div>
    </div>

    <form action="{{ route('stock-opnames.update', $opname->id) }}" method="POST" enctype="multipart/form-data" id="form-opname-edit">
        @csrf
        @method('PUT')
        
        {{-- Sembunyikan ID Gudang agar bisa diakses oleh Ajax --}}
        <input type="hidden" id="global_warehouse_id" value="{{ $opname->warehouse_id }}">

        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark mb-0"><i class="bi bi-list-check me-2 text-primary"></i>Daftar Barang di Lokasi: {{ optional($opname->warehouse)->name }}</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-muted small text-uppercase">
                            <tr>
                                <th class="ps-4 py-3" width="30%">Barang</th>
                                <th width="30%">Qty Hitung Fisik (Dari Kertas) <span class="text-danger">*</span></th>
                                <th class="pe-4" width="40%">Keterangan / Alasan Selisih</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($opname->items as $item)
                            <tr class="item-row" data-row-id="{{ $item->id }}" data-item-id="{{ $item->item_id }}" data-trackable="{{ optional($item->item)->is_trackable ? 'true' : 'false' }}">
                                <td class="ps-4 py-3">
                                    <div class="fw-bold text-dark">{{ optional($item->item)->name }}</div>
                                    <span class="badge bg-secondary-subtle text-secondary mt-1 border">{{ optional($item->item)->code }}</span>
                                    @if(optional($item->item)->is_trackable)
                                        <span class="badge bg-warning-subtle text-warning border-warning-subtle ms-1"><i class="bi bi-upc-scan"></i> Serial Number</span>
                                    @endif
                                </td>

                                <td>
                                    <div class="input-group shadow-sm">
                                        {{-- 🔥 WADAH RAHASIA UNTUK STOK SISTEM 🔥 --}}
                                        <input type="hidden" class="sys-stock" value="{{ (float) $item->system_qty }}">
                                        
                                        {{-- VALUE DIKOSONGKAN AGAR USER WAJIB NGETIK MANUAL --}}
                                        <input type="number" name="items[{{ $item->id }}][actual_qty]" class="form-control fw-bold border-warning text-dark bg-warning-subtle text-center real-stock"
                                               value="{{ $item->actual_qty > 0 ? (float) $item->actual_qty : '' }}"
                                               placeholder="Ketik angka..." min="0" step="any" required>
                                        <span class="input-group-text bg-light fw-bold text-muted">{{ $item->base_uom }}</span>
                                    </div>
                                </td>

                                <td class="pe-4">
                                    <input type="text" name="items[{{ $item->id }}][notes]" class="form-control border-light shadow-sm bg-light" value="{{ $item->notes }}" placeholder="Ketik alasan jika ada...">
                                </td>
                            </tr>
                            {{-- 🔥 BARIS RAHASIA UNTUK WADAH SN (KHUSUS TRACKABLE) 🔥 --}}
                            @if(optional($item->item)->is_trackable)
                            <tr class="sn-row" id="sn-row-{{ $item->id }}" style="display: none; background-color: #f8f9fa;">
                                <td colspan="3" class="px-4 py-3 border-bottom">
                                    <div class="p-3 bg-white border rounded shadow-sm sn-container border-warning" id="sn-container-{{ $item->id }}">
                                        <!-- Akan diisi otomatis oleh JavaScript -->
                                    </div>
                                </td>
                            </tr>
                            @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Area Upload Bukti Fisik --}}
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark mb-0"><i class="bi bi-images me-2 text-primary"></i>Upload Bukti Hitung <span class="text-danger">*</span></h6>
                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill fw-bold shadow-sm" id="btn-add-file">
                    <i class="bi bi-plus-lg me-1"></i> Tambah Dokumen
                </button>
            </div>
            <div class="card-body p-4">
                <div class="text-muted small mb-3">
                    Wajib: Lampirkan foto kertas yang telah dicoret-coret staf gudang. Jika file berada di folder yang berbeda, klik tombol <strong>Tambah Dokumen</strong> di atas.
                </div>

                <div id="file-upload-container">
                    <div class="input-group mb-2 shadow-sm file-row">
                        <span class="input-group-text bg-white"><i class="bi bi-file-earmark-pdf text-danger"></i></span>
                        <input type="file" name="attachments[]" class="form-control form-control-lg bg-light" accept=".pdf,.jpg,.jpeg,.png" required>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tombol Aksi --}}
        <div class="text-end">
            <a href="{{ route('stock-opnames.show', $opname->id) }}" class="btn btn-light fw-bold px-4 rounded-pill border me-2 shadow-sm">Batal</a>
            <button type="submit" class="btn btn-success fw-bold px-5 rounded-pill shadow-sm" id="btnSubmit">
                <i class="bi bi-save me-1"></i> Simpan Hasil Hitungan
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Logika Dinamis Upload File
        document.getElementById('btn-add-file').addEventListener('click', function() {
            let container = document.getElementById('file-upload-container');
            let row = document.createElement('div');
            row.className = 'input-group mb-2 shadow-sm file-row';
            let iconSpan = document.createElement('span');
            iconSpan.className = 'input-group-text bg-white';
            iconSpan.innerHTML = '<i class="bi bi-file-earmark-pdf text-danger"></i>';
            let input = document.createElement('input');
            input.type = 'file';
            input.name = 'attachments[]'; 
            input.className = 'form-control form-control-lg bg-light';
            input.accept = '.pdf,.jpg,.jpeg,.png';
            let btnRemove = document.createElement('button');
            btnRemove.type = 'button';
            btnRemove.className = 'btn btn-outline-danger';
            btnRemove.innerHTML = '<i class="bi bi-trash"></i>';
            btnRemove.onclick = function() { row.remove(); };
            row.appendChild(iconSpan);
            row.appendChild(input);
            row.appendChild(btnRemove);
            container.appendChild(row);
        });

        // =====================================================================
        // 🔥 RADAR INTELIJEN: DETEKSI SERIAL NUMBER (SAMA SEPERTI ADJUSTMENT) 🔥
        // =====================================================================
        let activeAjaxRequests = {};
        let warehouseId = $('#global_warehouse_id').val();

        $('.real-stock').on('input', function() {
            let tr = $(this).closest('tr.item-row');
            let rowId = tr.data('row-id'); // Ini adalah ID StockOpnameItem
            let itemId = tr.data('item-id'); // Ini adalah ID Master Item
            let isTrackable = tr.data('trackable') === true; // Pastikan jadi boolean

            // Jika bukan trackable, abaikan saja
            if (!isTrackable) return;

            let sys = parseFloat(tr.find('.sys-stock').val()) || 0;
            let real = parseFloat($(this).val());
            
            let snRow = $(`#sn-row-${rowId}`);
            let snContainer = $(`#sn-container-${rowId}`);

            if (activeAjaxRequests[rowId]) {
                activeAjaxRequests[rowId].abort();
            }

            // Jika input kosong atau selisih 0, sembunyikan baris SN
            if (isNaN(real) || real === sys) {
                snRow.hide();
                snContainer.empty();
                return;
            }

            let diff = real - sys;
            let absDiff = Math.abs(diff);

            // Buka baris SN
            snRow.show();
            snContainer.empty();

            if (diff > 0) {
                // SURPLUS: Minta SN Baru
                snContainer.append(`<div class="mb-2 text-success fw-bold"><i class="bi bi-plus-circle-fill me-1"></i> Mode Blind Count: Masukkan ${absDiff} Serial Number (SN) baru yang ditemukan di gudang:</div>`);
                let inputHtml = '<div class="row g-2">';
                for(let i=0; i < absDiff; i++) {
                    inputHtml += `
                        <div class="col-md-4">
                            <input type="text" name="items[${rowId}][new_sns][]" class="form-control form-control-sm border-success fw-bold text-dark" placeholder="Ketik SN #${i+1}..." required>
                        </div>
                    `;
                }
                inputHtml += '</div>';
                snContainer.append(inputHtml);

            } else if (diff < 0) {
                // DEFISIT: Pilih SN yang Hilang
                snContainer.append(`<div class="mb-2 text-danger fw-bold" id="loading-sn-${rowId}"><span class="spinner-border spinner-border-sm me-2"></span> Mencari data SN di gudang...</div>`);
                
                activeAjaxRequests[rowId] = $.get("{{ route('stock-adjustments.search-sns') }}", { item_id: itemId, warehouse_id: warehouseId }, function(data) {
                    
                    snContainer.find(`#loading-sn-${rowId}`).remove();
                    snContainer.append(`<div class="mb-2 text-danger fw-bold"><i class="bi bi-dash-circle-fill me-1"></i> Mode Blind Count: Pilih ${absDiff} Serial Number (SN) yang tidak Anda temukan di gudang:</div>`);

                    let options = data.map(sn => `<option value="${sn.id}">${sn.text}</option>`).join('');
                    
                    let selectHtml = `
                        <select name="items[${rowId}][lost_sns][]" class="form-select border-danger select2-lost-sn" multiple="multiple" required style="width: 100%;">
                            ${options}
                        </select>
                        <small class="mt-1 text-muted d-block">Sistem membatasi maksimal pilihan sejumlah ${absDiff} unit sesuai selisih fisik vs sistem.</small>
                    `;
                    snContainer.append(selectHtml);
                    
                    snContainer.find('.select2-lost-sn').select2({
                        theme: 'bootstrap-5',
                        dropdownCssClass: 'hide-selected-sn',
                        placeholder: "-- Pilih SN yang Hilang/Tidak Ada --",
                        maximumSelectionLength: absDiff,
                        closeOnSelect: false 
                    });
                }).fail(function(jqXHR) {
                    if(jqXHR.statusText !== 'abort') {
                        snContainer.find(`#loading-sn-${rowId}`).html('<span class="text-danger small"><i class="bi bi-x-circle me-1"></i> Gagal memuat daftar Serial Number.</span>');
                    }
                }).always(function() {
                    delete activeAjaxRequests[rowId];
                });
            }
        });

        // Submit Form (Kunci & Validasi)
        $('#form-opname-edit').on('submit', function(e) {
            e.preventDefault();
            let form = this;

            Swal.fire({
                title: 'Simpan Hasil Opname?',
                text: "Pastikan semua angka fisik (dan identitas Serial Number jika ada selisih) sudah dimasukkan dengan benar. Data ini akan dikirim untuk Approval.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-check-circle me-1"></i> Ya, Simpan!',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    let btn = document.getElementById('btnSubmit');
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Menyimpan...';
                    btn.disabled = true;
                    form.submit();
                }
            });
        });
    });
</script>
@endpush