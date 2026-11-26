@extends('layouts.app')

@section('content')
<div class="pb-5 container-fluid text-dark">
    <div class="mb-4 d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-cpu-fill text-warning me-2"></i> Riwayat Pengeluaran Part & Upgrade Aset</h4>
            <div class="text-muted small">Catatan pengeluaran sparepart dari gudang ke unit aset beserta referensi PO, dampak nilai buku, dan pencopotan part.</div>
        </div>
        <div class="gap-2 d-flex">
            <a href="{{ route('fixed-assets.index') }}" class="btn btn-outline-secondary rounded-pill fw-bold">
                <i class="bi bi-arrow-left me-1"></i> Daftar Aset
            </a>
            <a href="{{ route('fixed-assets.upgrades.create') }}" class="btn btn-primary rounded-pill fw-bold shadow-sm">
                <i class="bi bi-plus-lg me-1"></i> Input Pengeluaran Part Upgrade
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="border-0 border-4 shadow-sm alert alert-success rounded-4 border-start border-success">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="border-0 border-4 shadow-sm alert alert-danger rounded-4 border-start border-danger">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="border-0 border-4 shadow-sm alert alert-danger rounded-4 border-start border-danger">
            <ul class="mb-0 small">@foreach($errors->all() as $err) <li>{{ $err }}</li> @endforeach</ul>
        </div>
    @endif

    <div class="mb-3 border-0 shadow-sm card rounded-4">
        <div class="p-3 card-body">
            <form method="GET" action="{{ route('fixed-assets.upgrades.index') }}" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="Cari No. Upgrade, No. Aset, Nama Komponen, atau No. PO...">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary fw-bold px-4 rounded-pill"><i class="bi bi-search me-1"></i> Cari</button>
                    @if(request('search'))
                        <a href="{{ route('fixed-assets.upgrades.index') }}" class="btn btn-light border rounded-pill">Reset</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="border-0 shadow-sm card rounded-4">
        <div class="p-0 card-body">
            <div class="table-responsive">
                <table class="table mb-0 align-middle table-hover">
                    <thead class="table-light small text-uppercase text-muted">
                        <tr>
                            <th class="py-3 ps-4">No. Dokumen & Tgl</th>
                            <th class="py-3">Unit Aset Tujuan</th>
                            <th class="py-3">Komponen & Asal PO</th>
                            <th class="py-3 text-end">Biaya</th>
                            <th class="py-3">Perlakuan Nilai & Umur</th>
                            <th class="py-3">Dampak Nilai Buku</th>
                            <th class="py-3 text-center pe-4">Status / Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($upgrades as $upg)
                            @php
                                $upgStatus = trim((string) ($upg->status ?? '')) ?: 'ACTIVE';
                                $isRemoved = in_array($upgStatus, ['REMOVED_GOOD', 'REMOVED_DEFECTIVE']);
                            @endphp
                            <tr class="{{ $isRemoved ? 'table-light text-muted' : '' }}">
                                <td class="ps-4">
                                    <div class="fw-bold {{ $isRemoved ? 'text-decoration-line-through text-secondary' : 'text-primary' }}">
                                        {{ $upg->upgrade_number ?? '-' }}
                                    </div>
                                    <div class="small text-muted">{{ $upg->upgrade_date ? $upg->upgrade_date->format('d M Y') : '-' }}</div>
                                </td>
                                <td>
                                    @if($upg->asset)
                                        <a href="{{ route('asset-capitalizations.show', $upg->asset->id) }}" class="fw-bold text-dark text-decoration-none">
                                            {{ $upg->asset->asset_number }}
                                        </a>
                                        <div class="small text-muted">{{ $upg->asset->name }}</div>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-bold {{ $isRemoved ? 'text-decoration-line-through text-secondary' : 'text-dark' }}">
                                        {{ $upg->component_name }}
                                    </div>
                                    <div class="small text-muted">
                                        Qty: <strong>{{ (float)($upg->qty ?? 1) }}</strong>
                                        @if($upg->warehouse) &bull; Gudang: {{ $upg->warehouse->name }} @endif
                                    </div>
                                    @if($upg->purchaseOrder)
                                        <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle mt-1">
                                            <i class="bi bi-receipt me-1"></i>PO: {{ $upg->purchaseOrder->po_number }}
                                        </span>
                                    @endif
                                </td>
                                <td class="text-end fw-bold {{ $isRemoved ? 'text-decoration-line-through text-muted' : 'text-dark' }}">
                                    Rp {{ number_format($upg->cost, 0, ',', '.') }}
                                </td>
                                <td>
                                    @if($upg->is_capitalized)
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                            {{ $upg->treatment_label }}
                                        </span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary border">
                                            {{ $upg->treatment_label }}
                                        </span>
                                    @endif
                                </td>
                                <td class="small">
                                    @if($upg->book_value_before !== null)
                                        <div>Sebelum: <strong>Rp {{ number_format($upg->book_value_before, 0, ',', '.') }}</strong></div>
                                        <div class="{{ $upg->is_capitalized ? 'text-success fw-bold' : 'text-muted' }}">
                                            Sesudah: Rp {{ number_format($upg->book_value_after, 0, ',', '.') }}
                                        </div>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="text-center pe-4">
                                    @php
                                        $upgStatus = strtoupper(trim((string) ($upg->status ?? '')));
                                        $isRemoved = in_array($upgStatus, ['REMOVED_GOOD', 'REMOVED_DEFECTIVE']) || !empty($upg->removal_number) || !empty($upg->removed_at);
                                    @endphp

                                    @if(!$isRemoved)
                                        <button type="button" class="btn btn-sm btn-outline-danger rounded-pill fw-bold"
                                                data-bs-toggle="modal" data-bs-target="#modalRemoveUpgrade{{ $upg->id }}">
                                            <i class="bi bi-x-circle me-1"></i> Copot Part
                                        </button>
                                    @else
                                        <button type="button" class="btn btn-sm btn-secondary rounded-pill fw-bold opacity-75" disabled style="cursor: not-allowed;">
                                            <i class="bi bi-slash-circle me-1"></i> Sudah Dicopot
                                        </button>
                                        <div class="mt-1">
                                            <span class="badge {{ $upgStatus === 'REMOVED_DEFECTIVE' ? 'bg-danger-subtle text-danger border-danger-subtle' : 'bg-info-subtle text-info-emphasis border-info-subtle' }} border" style="font-size: 0.68rem;">
                                                {{ $upgStatus === 'REMOVED_DEFECTIVE' ? 'Rusak / Karantina RMA' : 'Retur ke Stok Gudang' }}
                                            </span>
                                        </div>
                                        @if($upg->removal_number)
                                            <div class="small text-muted mt-1" style="font-size: 0.68rem;">
                                                {{ $upg->removal_number }} ({{ $upg->removed_at ? \Carbon\Carbon::parse($upg->removed_at)->format('d/m/Y') : '-' }})
                                            </div>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-5 text-center text-muted">
                                    Belum ada riwayat pengeluaran part untuk upgrade aset.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($upgrades->hasPages())
            <div class="p-3 bg-white card-footer border-top d-flex justify-content-end rounded-bottom-4">
                {{ $upgrades->links() }}
            </div>
        @endif
    </div>
</div>

{{-- MODAL COPOT PART DI LUAR TABLE AGAR TIDAK TERPOTONG OVERFLOW --}}
@php
    $allWarehouses = \App\Models\Warehouse::orderBy('name')->get();
@endphp
@foreach($upgrades as $upg)
    @php
        $upgStatus = trim((string) ($upg->status ?? '')) ?: 'ACTIVE';
    @endphp
    @if($upgStatus === 'ACTIVE')
        <div class="modal fade text-start" id="modalRemoveUpgrade{{ $upg->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form action="{{ route('fixed-assets.upgrades.remove_part', $upg->id) }}" method="POST" class="modal-content rounded-4 border-0 shadow">
                    @csrf
                    <div class="modal-header bg-danger text-white rounded-top-4">
                        <h6 class="modal-title fw-bold"><i class="bi bi-tools me-2"></i>Copot / Batalkan Part Upgrade</h6>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-dark">
                        <div class="p-2 mb-3 rounded bg-light border small">
                            <div>Komponen: <strong>{{ $upg->component_name }}</strong> ({{ (float)($upg->qty ?? 1) }} unit)</div>
                            <div>Aset: <strong>{{ optional($upg->asset)->asset_number }}</strong> — {{ optional($upg->asset)->name }}</div>
                            <div>Biaya Tercatat: <strong>Rp {{ number_format($upg->cost, 0, ',', '.') }}</strong></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Tanggal Pencopotan <span class="text-danger">*</span></label>
                            <input type="date" name="removed_at" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Kondisi Fisik Part Saat Dicopot <span class="text-danger">*</span></label>
                            <select name="removal_condition" class="form-select removal-cond-select" data-target="#whReturnWrap{{ $upg->id }}" required>
                                <option value="GOOD_RETURN_STOCK">1. Bagus / Hanya Tidak Cocok (Kembalikan ke Stok Gudang)</option>
                                <option value="DEFECTIVE_NO_STOCK">2. Rusak / Cacat Pabrik (Jangan Masuk Stok Siap Pakai / RMA)</option>
                            </select>
                        </div>

                        <div class="mb-3" id="whReturnWrap{{ $upg->id }}">
                            <label class="form-label small fw-bold">Gudang Tujuan Pengembalian Stok <span class="text-danger">*</span></label>
                            <select name="return_warehouse_id" class="form-select">
                                @foreach($allWarehouses as $wh)
                                    <option value="{{ $wh->id }}" {{ $upg->warehouse_id == $wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-2">
                            <label class="form-label small fw-bold">Alasan Pencopotan <span class="text-danger">*</span></label>
                            <textarea name="removal_reason" class="form-control" rows="2" placeholder="Cth: Slot tidak cocok / Part dead on arrival..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer bg-light rounded-bottom-4">
                        <button type="button" class="btn btn-light border rounded-pill" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger fw-bold rounded-pill">Proses Pencopotan Part</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endforeach
@endsection

@push('scripts')
<script>
    document.addEventListener('change', function (e) {
        if (e.target && e.target.classList.contains('removal-cond-select')) {
            const targetSelector = e.target.getAttribute('data-target');
            const targetEl = document.querySelector(targetSelector);
            if (targetEl) {
                targetEl.style.display = (e.target.value === 'GOOD_RETURN_STOCK') ? 'block' : 'none';
            }
        }
    });
</script>
@endpush

