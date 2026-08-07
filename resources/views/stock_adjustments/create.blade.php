@extends('layouts.app')

@push('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<style>
    .select2-container--bootstrap-5 .select2-selection { border-radius: 8px; border-color: #dee2e6; min-height: 38px; }
    .diff-box { transition: all 0.3s ease; }
</style>
@endpush

@section('content')
<div class="container-fluid pb-5 text-dark">

    <div class="mb-4">
        <a href="{{ route('inventory.index') }}" class="mb-2 text-decoration-none text-muted small fw-bold d-inline-block">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Master Stok
        </a>
        <h4 class="mb-0 fw-bold text-dark">
            <i class="bi bi-sliders text-warning me-2"></i> Penyesuaian Stok (Stock Adjustment)
        </h4>
        <div class="mt-1 text-muted small">Koreksi perbedaan angka sistem vs fisik aktual beserta penentuan harga perolehan barang.</div>
    </div>

    @if(session('error'))
        <div class="border-0 border-4 shadow-sm alert alert-danger rounded-3 fw-bold border-start border-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form action="{{ route('stock-adjustments.store') }}" method="POST" id="form-opname">
        @csrf

        {{-- CARD 1: INFO HEADER --}}
        <div class="mb-4 border-0 border-4 shadow-sm card rounded-4 border-start border-warning">
            <div class="p-4 card-body">
                <div class="row g-4">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Tanggal BA Opname <span class="text-danger">*</span></label>
                        <input type="date" name="adjustment_date" class="shadow-sm form-control bg-light" value="{{ date('Y-m-d') }}" required max="{{ date('Y-m-d') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Lokasi Gudang Fisik <span class="text-danger">*</span></label>
                        <select name="warehouse_id" id="warehouse_id" class="shadow-sm form-select border-primary fw-bold text-primary" required>
                            <option value="">-- Pilih Lokasi Gudang --</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small fw-bold text-muted">Alasan / Keterangan Berita Acara <span class="text-danger">*</span></label>
                        <input type="text" name="reason" class="shadow-sm form-control" required placeholder="Cth: Penyesuaian Stok Spidol ATK / Hasil Opname">
                    </div>
                </div>
            </div>
        </div>

        {{-- CARD 2: TABEL BARANG --}}
        <div class="border-0 border-4 shadow-sm card rounded-4 border-top border-warning">
            <div class="px-4 py-3 bg-white card-header border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-box-seam me-2 text-warning"></i>Daftar Penyesuaian Barang</h6>
                <button type="button" class="px-3 shadow-sm btn btn-sm btn-primary rounded-pill fw-bold" id="btn-add-item">
                    <i class="bi bi-plus-lg me-1"></i> Tambah Baris
                </button>
            </div>

            <div class="px-4 py-2 bg-warning-subtle text-warning-emphasis small fw-bold border-bottom">
                <i class="bi bi-info-circle me-1"></i> Jika barang belum pernah dibeli, silakan isi <strong>Harga Satuan (Rp)</strong> secara manual untuk menetapkan HPP barang tersebut.
            </div>

            <div class="p-0 card-body table-responsive" style="min-height: 300px;">
                <table class="table mb-0 align-middle table-borderless table-hover">
                    <thead class="bg-light text-muted small text-uppercase border-bottom">
                        <tr>
                            <th class="py-3 ps-4" width="28%">Ketik & Pilih Barang</th>
                            <th class="py-3 text-center" width="10%">Sistem</th>
                            <th class="py-3 text-center" width="15%">Fisik (Real)</th>
                            <th class="py-3 text-center" width="18%">Harga Satuan (HPP)</th>
                            <th class="py-3 text-center" width="22%">Selisih Mutasi</th>
                            <th class="py-3 text-center pe-4" width="7%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="item-tbody">
                        {{-- Baris Dinamis JS --}}
                    </tbody>
                </table>
            </div>

            <div class="p-4 bg-light card-footer border-top text-end rounded-bottom-4">
                <button type="submit" class="px-5 shadow-sm btn btn-warning rounded-pill fw-bold text-dark" id="btnSubmit">
                    <i class="bi bi-check2-circle me-2"></i> Eksekusi & Simpan Opname
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
        let tbody = $('#item-tbody');
        let warehouseSelect = $('#warehouse_id');
        let rowCount = 0;

        // Reset tabel jika gudang diganti
        warehouseSelect.on('change', function() {
            if (tbody.find('tr').length > 0) {
                tbody.find('.item-select-ajax').select2('destroy');
                tbody.empty();
                rowCount = 0;
                addRow();
            }
        });

        $('#btn-add-item').on('click', function() {
            if(!warehouseSelect.val()) {
                Swal.fire('Perhatian', 'Pilih Lokasi Gudang terlebih dahulu!', 'warning');
                return;
            }
            addRow();
        });

        function addRow() {
            let tr = `
                <tr class="border-bottom item-row">
                    <td class="py-3 ps-4">
                        <select name="items[${rowCount}][item_id]" class="form-select item-select-ajax" required></select>
                    </td>
                    <td class="py-3 text-center">
                        <input type="text" class="text-center shadow-none form-control form-control-sm bg-light sys-stock text-muted fw-bold" readonly placeholder="0">
                    </td>
                    <td class="px-2 py-3">
                        <div class="shadow-sm input-group input-group-sm">
                            <span class="bg-white input-group-text"><i class="bi bi-box"></i></span>
                            <input type="number" name="items[${rowCount}][real_stock]" class="text-center form-control real-stock fw-bold text-primary border-primary" step="0.01" min="0" required placeholder="Fisik...">
                        </div>
                    </td>
                    <td class="px-2 py-3">
                        <div class="shadow-sm input-group input-group-sm">
                            <span class="bg-white input-group-text">Rp</span>
                            <input type="number" name="items[${rowCount}][unit_price]" class="form-control unit-price text-end fw-bold" min="0" step="any" placeholder="0" title="Harga perolehan / HPP per unit">
                        </div>
                    </td>
                    <td class="px-2 py-3">
                        <div class="p-2 text-center border rounded diff-box bg-light text-muted small fw-bold">
                            <span class="diff-icon me-1">=</span> <span class="diff-text">0 (Sama)</span>
                        </div>
                    </td>
                    <td class="py-3 text-center pe-4">
                        <button type="button" class="btn btn-sm btn-outline-danger btn-remove rounded-circle" title="Hapus"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>
            `;
            let $tr = $(tr);
            tbody.append($tr);

            // Select2 dengan pencegahan duplikasi barang
            $tr.find('.item-select-ajax').select2({
                theme: 'bootstrap-5',
                placeholder: '-- Cari & Ketik Barang --',
                minimumInputLength: 2,
                ajax: {
                    url: "{{ route('stock-adjustments.search-items') }}",
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        let selectedIds = [];
                        $('.item-select-ajax').each(function() {
                            let val = $(this).val();
                            if (val) selectedIds.push(val);
                        });

                        return {
                            search: params.term,
                            warehouse_id: warehouseSelect.val(),
                            show_all: true,
                            selected_items: selectedIds
                        };
                    },
                    processResults: function (data) { return { results: data }; },
                    cache: true
                }
            });

            rowCount++;
        }

        addRow();

        // Hapus Baris
        tbody.on('click', '.btn-remove', function() {
            if (tbody.find('tr').length > 1) {
                $(this).closest('tr').find('.item-select-ajax').select2('destroy');
                $(this).closest('tr').remove();
            } else {
                Swal.fire('Ups!', 'Minimal harus ada 1 barang.', 'info');
            }
        });

        // Saat Barang Dipilih -> Tembak AJAX untuk cek stok & harga bawaan
        tbody.on('select2:select', '.item-select-ajax', function (e) {
            let data = e.params.data;
            let currentSelect = $(this);
            let tr = currentSelect.closest('tr');
            let sysInput = tr.find('.sys-stock');
            let priceInput = tr.find('.unit-price');

            // Cek Duplikasi
            let isDuplicate = false;
            $('.item-select-ajax').not(currentSelect).each(function() {
                if ($(this).val() == data.id) isDuplicate = true;
            });

            if (isDuplicate) {
                Swal.fire('Ups, Ditolak!', 'Barang ini sudah ada di baris lain.', 'error');
                currentSelect.val(null).trigger('change');
                return;
            }

            sysInput.val('Memuat...');

            $.ajax({
                url: "{{ route('stock-adjustments.get-stock') }}",
                type: "GET",
                data: { item_id: data.id, warehouse_id: warehouseSelect.val() },
                success: function(res) {
                    sysInput.val(res.stock);
                    priceInput.val(res.unit_price || 0); // Isikan harga bawaan master item
                    tr.find('.real-stock').val('').trigger('input');
                },
                error: function() {
                    sysInput.val(0);
                    priceInput.val(0);
                }
            });
        });

        // Kalkulasi Otomatis (Real Time)
        tbody.on('input', '.real-stock, .unit-price', function() {
            let tr = $(this).closest('tr');
            let sys = parseFloat(tr.find('.sys-stock').val()) || 0;
            let real = parseFloat(tr.find('.real-stock').val());
            let price = parseFloat(tr.find('.unit-price').val()) || 0;

            let diffBox = tr.find('.diff-box');
            let diffIcon = tr.find('.diff-icon');
            let diffText = tr.find('.diff-text');

            if (isNaN(real)) {
                diffBox.attr('class', 'p-2 text-center border rounded diff-box bg-light text-muted small fw-bold');
                diffIcon.html('='); diffText.text('0 (Sama)');
                return;
            }

            let diff = real - sys;
            let totalValuasi = Math.abs(diff) * price;
            let formattedRupiah = new Intl.NumberFormat('id-ID').format(totalValuasi);

            if (diff > 0) {
                diffBox.attr('class', 'p-2 text-center border rounded diff-box bg-success-subtle text-success small fw-bold border-success-subtle');
                diffIcon.html('<i class="bi bi-arrow-up-circle-fill"></i>');
                diffText.html('+' + diff + ' (Masuk)<br><small class="text-muted">Rp ' + formattedRupiah + '</small>');
            } else if (diff < 0) {
                diffBox.attr('class', 'p-2 text-center border rounded diff-box bg-danger-subtle text-danger small fw-bold border-danger-subtle');
                diffIcon.html('<i class="bi bi-arrow-down-circle-fill"></i>');
                diffText.html(diff + ' (Keluar)<br><small class="text-muted">Rp ' + formattedRupiah + '</small>');
            } else {
                diffBox.attr('class', 'p-2 text-center border rounded diff-box bg-light text-muted small fw-bold');
                diffIcon.html('='); diffText.text('0 (Sama)');
            }
        });

        // Submit Form
        $('#form-opname').on('submit', function(e) {
            e.preventDefault();
            let form = this;

            Swal.fire({
                title: 'Kunci & Simpan Opname?',
                text: "Pastikan angka fisik dan harga perolehan sudah sesuai. Data ini akan memperbarui Kartu Stok & Valuasi Persediaan.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ffc107',
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
