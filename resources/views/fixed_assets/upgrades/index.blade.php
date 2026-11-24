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
                                $isRemoved = in_array($upg->status ?? 'ACTIVE', ['REMOVED_GOOD', 'REMOVED_DEFECTIVE']);
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
                                        $upgStatus = $upg->status ?: 'ACTIVE';
                                    @endphp

                                    @if($upgStatus === 'ACTIVE')
                                        <button type="button" class="btn btn-sm btn-outline-danger rounded-pill fw-bold"
                                                data-bs-toggle="modal" data-bs-target="#modalRemoveUpgrade{{ $upg->id }}">
                                            <i class="bi bi-x-circle me-1"></i> Copot Part
                                        </button>

                                        {{-- Modal Copot Part (biarkan sama seperti sebelumnya) --}}
                                        <div class="modal fade text-start" id="modalRemoveUpgrade{{ $upg->id }}" tabindex="-1" aria-hidden="true">
                                            ...
                                        </div>
                                    @else
                                        <button type="button" class="btn btn-sm btn-secondary rounded-pill fw-bold opacity-75" disabled style="cursor: not-allowed;">
                                            <i class="bi bi-slash-circle me-1"></i> Sudah Dicopot
                                        </button>
                                        <div class="mt-1">
                                            <span class="badge {{ $upgStatus === 'REMOVED_GOOD' ? 'bg-info-subtle text-info-emphasis border-info-subtle' : 'bg-danger-subtle text-danger border-danger-subtle' }} border" style="font-size: 0.68rem;">
                                                {{ $upgStatus === 'REMOVED_GOOD' ? 'Retur ke Stok Gudang' : 'Rusak / Karantina RMA' }}
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
@endsection

@push('scripts')
<script>
    $(document).on('change', '.removal-cond-select', function () {
        let target = $($(this).data('target'));
        if ($(this).val() === 'GOOD_RETURN_STOCK') {
            target.slideDown();
        } else {
            target.slideUp();
        }
    });
</script>
@endpush