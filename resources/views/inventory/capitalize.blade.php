@extends('layouts.app')

@section('content')
<div class="container pb-5 text-dark">
    <div class="mb-4">
        <a href="{{ route('inventory.index') }}" class="text-decoration-none text-muted small fw-bold"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
        <h4 class="mt-2 fw-bold text-dark"><i class="bi bi-magic text-warning me-2"></i> Kapitalisasi Stok Menjadi Aset Kantor</h4>
        <div class="text-muted small">Ubah takdir barang dari stok biasa menjadi entitas aset ber-Serial Number untuk barang: <strong class="text-primary">{{ $item->code }} - {{ $item->name }}</strong></div>
    </div>

    @if(session('error'))
        <div class="alert alert-danger rounded-3 fw-bold border-0 shadow-sm"><i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}</div>
    @endif

    {{-- 🔥 SIMPAN DEFAULT SPESIFIKASI DARI MASTER BARANG DI SINI AGAR BISA DITARIK OTOMATIS OLEH JS 🔥 --}}
    <input type="hidden" id="default-spec" value="{{ strip_tags($item->specification ?? '') }}">

    <form action="{{ route('inventory.capitalize.store', $item->code) }}" method="POST" id="capitalizeForm">
        @csrf
        
        {{-- UBAH LAYOUT MENJADI ATAS-BAWAH (FULL WIDTH) AGAR TABEL SANGAT LEBAR --}}
        <div class="d-flex flex-column gap-4">
            
            {{-- KOTAK 1: PILIH GUDANG & QTY (DI ATAS) --}}
            <div class="card border-0 shadow-sm rounded-4 p-4">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-geo-alt text-danger me-2"></i>1. Pilih Gudang & Kuantitas</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Asal Gudang Fisik</label>
                        <select name="warehouse_id" id="warehouse_select" class="form-select border-secondary-subtle fw-bold text-secondary py-2" required>
                            <option value="">-- Pilih Gudang --</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}" data-max="{{ $wh->available_regular_stock }}">
                                    {{ $wh->name }} (Tersedia: {{ $wh->available_regular_stock }} {{ $item->uom?->name }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Jumlah Unit yang Dijadikan Aset</label>
                        <input type="number" name="qty" id="qty_input" class="form-control fw-bold border-secondary-subtle text-primary py-2" min="1" disabled required placeholder="Pilih gudang dulu...">
                    </div>
                </div>
            </div>

            {{-- KOTAK 2: TABEL REGISTRASI (DI BAWAH, LEBAR PENUH) --}}
            <div class="card border-0 shadow-sm rounded-4 p-4 d-none" id="sn-card">
                <h6 class="fw-bold text-dark mb-2"><i class="bi bi-upc-scan text-success me-2"></i>2. Registrasi Serial Number Aset</h6>
                <div class="alert alert-warning py-2 small border-0 shadow-sm mb-3"><i class="bi bi-info-circle-fill me-1"></i> Ketikkan Nomor Seri aktual fisik barang yang akan ditempeli stiker *Asset Tag* internal. Spesifikasi sudah ditarik otomatis, silakan edit jika perlu.</div>
                
                <div id="sn-inputs-container">
                    {{-- Input Tabel digenerate via JS --}}
                </div>

                <div class="mt-4 text-end">
                    <button type="submit" class="btn btn-warning fw-bold px-5 py-2 rounded-pill shadow-sm fs-6"><i class="bi bi-shield-check me-2"></i> Sahkan Menjadi Aset</button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const whSelect = document.getElementById('warehouse_select');
        const qtyInput = document.getElementById('qty_input');
        const snCard = document.getElementById('sn-card');
        const snContainer = document.getElementById('sn-inputs-container');

        whSelect.addEventListener('change', function() {
            const selected = this.options[this.selectedIndex];
            const maxStock = parseInt(selected.getAttribute('data-max')) || 0;

            if (this.value && maxStock > 0) {
                qtyInput.disabled = false;
                qtyInput.setAttribute('max', maxStock);
                qtyInput.placeholder = "Maksimal: " + maxStock;
                qtyInput.value = "";
                snCard.classList.add('d-none');
                snContainer.innerHTML = "";
            } else {
                qtyInput.disabled = true;
                qtyInput.value = "";
                qtyInput.placeholder = "Stok tidak tersedia...";
                snCard.classList.add('d-none');
                snContainer.innerHTML = "";
            }
        });

        qtyInput.addEventListener('input', function() {
            const maxStock = parseInt(whSelect.options[whSelect.selectedIndex].getAttribute('data-max')) || 0;
            let val = parseInt(this.value) || 0;

            if (val > maxStock) { this.value = maxStock; val = maxStock; }

            if (val > 0) {
                snCard.classList.remove('d-none');
                
                // 🔥 TARIK SPESIFIKASI BAWAAN DARI MASTER BARANG 🔥
                let defaultSpec = document.getElementById('default-spec').value;
                
                // 🔥 TABEL DENGAN TEXTAREA AGAR TEKS PANJANG TERBACA FULL 🔥
                let tableHtml = `
                    <div class="table-responsive border rounded-3 custom-scrollbar" style="max-height: 500px;">
                        <table class="table table-hover align-middle mb-0" style="min-width: 1200px;">
                            <thead class="table-light sticky-top" style="z-index: 1;">
                                <tr>
                                    <th class="py-3 text-center" width="5%">No</th>
                                    <th class="py-3" width="12%">No. Aset (Sistem)</th>
                                    <th class="py-3" width="18%">No. Aset (Akuntansi) <span class="text-danger">*</span></th>
                                    <th class="py-2" width="18%">
                                        Serial Number (SN) <span class="text-danger">*</span>
                                        <div class="text-muted fw-normal" style="font-size: 0.65rem;">(Atau No. Rangka/IMEI/MAC)</div>
                                    </th>
                                    <th class="py-3" width="32%">Spesifikasi Tambahan</th>
                                    <th class="py-3" width="15%">Kondisi / Catatan</th>
                                </tr>
                            </thead>
                            <tbody>
                `;

                for (let i = 0; i < val; i++) {
                    tableHtml += `
                                <tr>
                                    <td class="text-center fw-bold text-muted">${i + 1}</td>
                                    <td><span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle py-2 px-3 w-100">Auto-Generate</span></td>
                                    <td><input type="text" name="acc_asset_number[]" class="form-control border-primary fw-bold" placeholder="Cth: ACC-00${i+1}" required></td>
                                    <td><input type="text" name="sn[]" class="form-control border-success fw-bold text-success" placeholder="Scan SN / Ketik ID Unik..." required></td>
                                    
                                    {{-- 🔥 UBAH MENJADI TEXTAREA AGAR LEGA 🔥 --}}
                                    <td>
                                        <textarea name="specs[]" class="form-control custom-scrollbar" rows="2" style="font-size: 0.85rem;" placeholder="Warna/Plat/Spek lain...">${defaultSpec}</textarea>
                                    </td>
                                    
                                    {{-- 🔥 CATATAN JUGA DIUBAH JADI TEXTAREA BIAR SIMETRIS 🔥 --}}
                                    <td>
                                        <textarea name="notes[]" class="form-control custom-scrollbar" rows="2" style="font-size: 0.85rem;" placeholder="Opsional..."></textarea>
                                    </td>
                                </tr>
                    `;
                }

                tableHtml += `
                            </tbody>
                        </table>
                    </div>
                `;
                
                snContainer.innerHTML = tableHtml;
            } else {
                snCard.classList.add('d-none');
                snContainer.innerHTML = "";
            }
        });
    });
</script>
@endsection