@extends('layouts.app')

@push('css')
<style>
    .stat-card { border-radius: 16px; border: none; background: linear-gradient(145deg, #ffffff 0%, #f8f9fa 100%); transition: all 0.3s ease; }
    .stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(0,0,0,0.08) !important; }

    .restock-table { border-collapse: separate; border-spacing: 0 8px; margin-top: -8px; }
    .restock-table thead th { border-bottom: none; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 1px; color: #adb5bd; padding-bottom: 10px; }
    .restock-table tbody tr { background-color: #ffffff; box-shadow: 0 2px 6px rgba(0,0,0,0.02); border-radius: 12px; transition: all 0.2s ease; }
    .restock-table tbody tr:hover { box-shadow: 0 5px 15px rgba(0,0,0,0.08); transform: scale(1.002); }
    .restock-table td { vertical-align: middle; border-top: 1px solid #f1f3f5; border-bottom: 1px solid #f1f3f5; transition: all 0.3s ease; }
    .restock-table td:first-child { border-left: 1px solid #f1f3f5; border-top-left-radius: 12px; border-bottom-left-radius: 12px; }
    .restock-table td:last-child { border-right: 1px solid #f1f3f5; border-top-right-radius: 12px; border-bottom-right-radius: 12px; }

    .qty-input { border: 1px solid #dee2e6; border-radius: 8px; text-align: center; font-weight: bold; width: 100px; height: 35px; color: #0d6efd; transition: all 0.3s ease; }
    .qty-input:focus { border-color: #0d6efd; box-shadow: 0 0 0 0.2rem rgba(13,110,253,0.15); outline: none; }

    .progress-thin { height: 6px; border-radius: 10px; background-color: #e9ecef; }

    /* Checkbox Styles */
    .custom-row-check { width: 22px; height: 22px; border: 2px solid #869099; cursor: pointer; border-radius: 4px; box-shadow: inset 0 1px 2px rgba(0,0,0,0.05); transition: all 0.2s ease-in-out; }
    .custom-row-check:checked { border-color: #0d6efd; background-color: #0d6efd; }
    .custom-switch-check { transform: scale(1.3); margin-left: 0.5rem !important; cursor: pointer; border: 2px solid #869099; }
    .custom-switch-check:checked { border-color: #0d6efd; background-color: #0d6efd; }

    /* 🔥 TAMPILAN BARIS NON-AKTIF (INACTIVE) YANG LEBIH JELAS 🔥 */
    .row-inactive { background-color: #f8f9fa !important; border-left: 4px solid #dee2e6 !important; box-shadow: none !important; }
    .row-inactive td { border-color: #e9ecef; }
    .row-inactive .text-dark { color: #6c757d !important; } /* Mengubah teks hitam menjadi abu-abu gelap agar tetap terbaca */
    .row-inactive .badge { opacity: 0.7; filter: grayscale(100%); } /* Membuat label/badge menjadi abu-abu */
    .row-inactive .qty-input { background-color: #e9ecef; border-color: #ced4da; color: #adb5bd; cursor: not-allowed; } /* Tampilan input terkunci */
    .row-inactive h5, .row-inactive .text-danger, .row-inactive .text-warning { color: #adb5bd !important; } /* Memudarkan angka stok fisik */
    .row-inactive .progress-bar { background-color: #ced4da !important; }
</style>
@endpush

@section('content')
<div class="px-0 container-fluid">

    <div class="gap-3 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h3 class="mb-1 fw-bolder text-dark"><span class="text-danger me-2"><i class="bi bi-exclamation-triangle-fill"></i></span>Smart Restock Gudang</h3>
            <div class="text-muted small fw-medium">Sistem pintar yang mendeteksi barang di bawah batas minimum dan menyarankan pembuatan PR massal.</div>
        </div>
    </div>

    @if(session('error'))
        <div class="mb-4 border-0 shadow-sm alert alert-danger rounded-4 d-flex align-items-center"><i class="bi bi-x-circle-fill fs-5 me-3"></i>{{ session('error') }}</div>
    @endif

    <div class="mb-4 row g-4">
        <div class="col-md-6">
            <div class="shadow-sm card stat-card h-100">
                <div class="p-4 card-body d-flex align-items-center">
                    <div class="bg-danger bg-opacity-10 text-danger rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 56px; height: 56px;"><i class="bi bi-box-seam-fill fs-3"></i></div>
                    <div>
                        <div class="mb-1 text-muted small fw-bold text-uppercase">Item Berstatus Kritis</div>
                        <h3 class="mb-0 fw-bolder text-dark">{{ $criticalItems->count() }} <span class="fs-6 text-muted fw-normal">Barang</span></h3>
                    </div>

                    {{-- FILTER GUDANG --}}
                    <div class="mb-4 bg-white border-0 shadow-sm card rounded-4 ms-auto">
                        <div class="p-3 card-body d-flex align-items-center">
                            <i class="bi bi-funnel-fill text-primary fs-4 me-3"></i>
                            <form action="{{ route('inventory.smart_restock') }}" method="GET" class="mb-0 d-flex align-items-center w-100">
                                <label class="fw-bold me-3 text-dark text-nowrap">Filter Lokasi Gudang:</label>
                                <select name="warehouse_id" class="w-auto shadow-sm form-select fw-bold border-primary-subtle text-primary" onchange="this.form.submit()">
                                    <option value="">-- Semua Gudang (Hak Akses Pusat) --</option>
                                    @foreach($warehouses as $wh)
                                        <option value="{{ $wh->id }}" {{ $warehouseId == $wh->id ? 'selected' : '' }}>
                                            {{ $wh->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @if($warehouseId)
                                    <a href="{{ route('inventory.smart_restock') }}" class="shadow-sm btn btn-sm btn-outline-danger ms-3 rounded-pill fw-bold">
                                        <i class="bi bi-x-circle me-1"></i>Reset Filter
                                    </a>
                                @endif
                            </form>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <form action="{{ route('inventory.generate_mass_pr') }}" method="POST" id="restockForm">
        @csrf

        <div class="p-3 mb-3 bg-white border shadow-sm d-flex justify-content-between align-items-center rounded-4">

            <div class="mb-0 form-check form-switch d-flex align-items-center ps-0">
                <input class="m-0 form-check-input custom-switch-check ms-2" type="checkbox" id="checkAll" checked role="switch">
                <label class="form-check-label fw-bolder text-dark ms-3 fs-6" for="checkAll" style="cursor:pointer;">Pilih Semua Barang</label>
            </div>

            <div class="gap-2 d-flex align-items-center">
                <label class="mb-0 small fw-bold text-muted text-nowrap">Bebankan Ke:</label>
                <select name="company_id" class="shadow-sm form-select fw-bold border-primary-subtle text-primary" required style="min-width: 250px; cursor: pointer;">
                    <option value="">-- Pilih PT Penanggung --</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" {{ (auth()->user()->company_id == $company->id) ? 'selected' : '' }}>
                            {{ $company->name }}
                        </option>
                    @endforeach
                </select>
                <button type="submit" class="px-4 shadow-sm btn btn-primary fw-bold rounded-pill text-nowrap" id="btnSubmit">
                    <i class="bi bi-magic me-1"></i> Generate Mass PR
                </button>
            </div>
        </div>

        @if($criticalItems->count() > 0)
            <div class="px-1 pb-4 table-responsive">
                <table class="table align-middle restock-table w-100">
                    <thead>
                        <tr>
                            <th class="text-center ps-2" width="5%"><i class="bi bi-check2-square fs-6"></i></th>
                            <th width="35%">Nama Barang & SKU</th>
                            <th class="text-center" width="20%">Status Stok Saat Ini</th>
                            <th class="text-center" width="20%">Batas Min - Max</th>
                            <th class="text-center pe-4" width="20%">Usulan Pesan (Qty)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($criticalItems as $index => $stock)
                            @php
                                $itemMaster = $stock->item;
                                $warehouse = $stock->warehouse;

                                $current = (float)($stock->stock_qty ?? 0);
                                $min = (float)($stock->min_stock ?? 0);
                                $max = (float)($stock->max_stock ?? ($min * 3));

                                $pendingPr = (float)($stock->pending_qty ?? 0);

                                $pct = $max > 0 ? ($current / $max) * 100 : 0;
                                $color = $current <= 0 ? 'danger' : 'warning';

                                $suggestedQty = max(1, $max - ($current + $pendingPr));
                            @endphp
                            <tr class="border-start border-4 border-{{ $color }} item-row">

                                <td class="text-center ps-3">
                                    <div class="d-flex justify-content-center align-items-center">
                                        <input class="m-0 form-check-input row-check custom-row-check" type="checkbox" name="items[{{ $index }}][is_selected]" value="1" checked>
                                    </div>
                                    <input type="hidden" name="items[{{ $index }}][item_id]" value="{{ $itemMaster->id }}">
                                    <input type="hidden" name="items[{{ $index }}][warehouse_name]" value="{{ optional($warehouse)->name ?? 'Gudang Pusat' }}">
                                </td>

                                <td class="py-3">
                                    <div class="fw-bolder text-dark itemName">{{ optional($itemMaster)->name }}</div>
                                    <div class="mt-1 small text-muted">
                                        <span class="border badge bg-secondary-subtle text-secondary border-secondary-subtle me-1">{{ optional($itemMaster)->code ?? 'NO-SKU' }}</span>
                                        <span class="border badge bg-primary-subtle text-primary border-primary-subtle"><i class="bi bi-shop me-1"></i> {{ optional($warehouse)->name ?? 'Gudang Pusat' }}</span>
                                    </div>
                                </td>
                                <td class="py-3 text-center">
                                    <h5 class="mb-1 fw-bolder text-{{ $color }}">{{ $current }} <span class="fs-6 fw-normal text-muted">{{ optional($itemMaster)->unit ?? 'PCS' }}</span></h5>

                                    @if($pendingPr > 0)
                                        <div class="mt-2"><span class="px-2 border badge bg-info-subtle text-info border-info-subtle rounded-pill" style="font-size: 0.7rem;" title="Sudah ada PR yang sedang diproses"><i class="bi bi-truck me-1"></i> +{{ $pendingPr }} Sedang di-PR-kan</span></div>
                                    @else
                                        <div class="mx-auto mt-2 progress progress-thin w-75">
                                            <div class="progress-bar bg-{{ $color }}" style="width: {{ $pct }}%"></div>
                                        </div>
                                    @endif

                                    @if($current <= 0)
                                        <div class="mt-1 small text-danger fw-bold">Stok Habis / Minus!</div>
                                    @endif
                                </td>
                                <td class="py-3 text-center small fw-bold text-secondary">
                                    Min: <span class="text-dark">{{ $min }}</span> &nbsp;|&nbsp; Max: <span class="text-dark">{{ $max }}</span>
                                </td>
                                <td class="py-3 text-center pe-4">
                                    <div class="d-flex justify-content-center align-items-center">
                                        <input type="number" name="items[{{ $index }}][qty]" class="shadow-sm qty-input" value="{{ $suggestedQty }}" min="1" step="any" required>
                                        <span class="ms-2 small fw-bold text-muted">{{ optional($itemMaster)->unit ?? 'PCS' }}</span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="py-5 mt-3 text-center border-0 shadow-sm card rounded-4">
                <div class="py-5 card-body">
                    <i class="mb-3 opacity-50 bi bi-shield-check text-success d-block" style="font-size: 4rem;"></i>
                    <h4 class="fw-bolder text-dark">Stok Gudang Aman!</h4>
                    <p class="mb-0 text-muted">Luar biasa! Saat ini tidak ada satupun barang yang menyentuh batas minimum.<br>Gudang Anda dalam kondisi sangat sehat.</p>
                </div>
            </div>
        @endif

    </form>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const checkAll = document.getElementById('checkAll');
        const rowChecks = document.querySelectorAll('.row-check');

        if(checkAll) {
            checkAll.addEventListener('change', function() {
                rowChecks.forEach(cb => {
                    cb.checked = this.checked;
                    toggleRowOpacity(cb);
                });
            });
        }

        rowChecks.forEach(cb => {
            cb.addEventListener('change', function() {
                toggleRowOpacity(this);
                if(checkAll) checkAll.checked = Array.from(rowChecks).every(c => c.checked);
            });
        });

        // 🔥 LOGIKA VISUAL BARU UNTUK BARIS NON-AKTIF 🔥
        function toggleRowOpacity(checkbox) {
            const row = checkbox.closest('.item-row');
            const inputQty = row.querySelector('.qty-input');

            if(checkbox.checked) {
                row.classList.remove('row-inactive');
                inputQty.removeAttribute('readonly');
            } else {
                row.classList.add('row-inactive');
                inputQty.setAttribute('readonly', 'true');
            }
        }

        document.getElementById('restockForm').addEventListener('submit', function(e) {
            const checkedBoxes = document.querySelectorAll('.row-check:checked');
            if(checkedBoxes.length === 0 && rowChecks.length > 0) {
                e.preventDefault();
                alert('Pilih minimal 1 barang untuk dibuatkan PR!');
            }
        });
    });
</script>
@endpush
