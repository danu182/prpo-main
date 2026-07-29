@extends('layouts.app')

@section('content')
<div class="mb-5 container-fluid">
    <div class="mb-4 d-flex align-items-center justify-content-between">
        <div>
            <h4 class="mb-1 fw-bold"><i class="bi bi-clock-history text-primary me-2"></i> Riwayat Perjalanan Aset</h4>
            <p class="mb-0 text-muted" style="font-size: 0.85rem;">Melacak rekam jejak serah terima dan durasi pemakaian aset.</p>
        </div>
        <a href="{{ route('fixed-assets.transactions') }}" class="border shadow-sm btn btn-light fw-bold">
            <i class="bi bi-arrow-left me-1"></i> Kembali
        </a>
    </div>

    <div class="row">
        {{-- PANEL KIRI: INFO ASET --}}
        <div class="mb-4 col-md-4">
            <div class="border-0 shadow-sm card sticky-top" style="top: 90px;">
                <div class="text-white card-header bg-dark fw-bold">
                    <i class="bi bi-info-circle me-2"></i> Detail Aset
                </div>
                <div class="card-body">
                    <div class="mb-4 text-center">
                        <div class="mb-2 opacity-25 display-1 text-primary"><i class="bi bi-pc-display"></i></div>
                        <h5 class="mb-1 fw-bold text-dark">{{ $asset->asset_number }}</h5>
                        <p class="mb-0 text-muted small">{{ $asset->name ?? optional($asset->item)->name }}</p>
                        <span class="mt-2 border badge bg-primary-subtle border-primary text-primary">
                            S/N: {{ $asset->serial_number ?? 'Tidak Ada' }}
                        </span>
                    </div>

                    <ul class="list-group list-group-flush small">
                        <li class="px-0 list-group-item d-flex justify-content-between">
                            <span class="text-muted">Tanggal Perolehan:</span>
                            <strong class="text-end">{{ $asset->acquisition_date ? \Carbon\Carbon::parse($asset->acquisition_date)->format('d M Y') : '-' }}</strong>
                        </li>
                        <li class="px-0 list-group-item d-flex justify-content-between">
                            <span class="text-muted">Pemilik (Entitas):</span>
                            <strong class="text-end">{{ optional($asset->company)->name ?? '-' }}</strong>
                        </li>
                        <li class="px-0 list-group-item d-flex justify-content-between">
                            <span class="text-muted">Status Saat Ini:</span>
                            @if($asset->assigned_to)
                                <strong class="text-danger">Dipakai (In Use)</strong>
                            @else
                                <strong class="text-success">Di Gudang (Available)</strong>
                            @endif
                        </li>
                        @if($asset->assigned_to)
                        <li class="p-3 mt-2 border-0 list-group-item bg-danger-subtle rounded-3">
                            <div class="mb-1 text-danger small">Sedang dipakai oleh:</div>
                            <div class="fw-bold text-dark"><i class="bi bi-person-fill me-1"></i> {{ optional($asset->assignee)->name }}</div>
                            <div class="mt-2 text-danger fw-bold"><i class="bi bi-stopwatch me-1"></i> Durasi Berjalan: {{ $asset->current_usage_duration }}</div>
                        </li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>

        {{-- PANEL KANAN: TIMELINE RIWAYAT --}}
        <div class="col-md-8">
            <div class="border-0 shadow-sm card">
                <div class="p-4 card-body">
                    <h5 class="pb-3 mb-4 fw-bold border-bottom text-dark">Garis Waktu (Timeline) Transaksi</h5>

                    @if($historiesDesc->count() > 0)
                        <div class="position-relative" style="border-left: 3px dashed #dee2e6; margin-left: 15px; padding-left: 30px;">

                            @foreach($historiesDesc as $history)
                                <div class="mb-5 position-relative">
                                    {{-- Ikon Bulat Timeline --}}
                                    @if($history->status == 'HANDOVER')
                                        <div class="text-white border border-4 border-white shadow-sm position-absolute bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; left: -52px; top: -5px;">
                                            <i class="bi bi-person-plus-fill"></i>
                                        </div>
                                    @elseif($history->status == 'RETURNED')
                                        <div class="text-white border border-4 border-white shadow-sm position-absolute bg-danger rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; left: -52px; top: -5px;">
                                            <i class="bi bi-arrow-return-left"></i>
                                        </div>
                                    @else
                                        <div class="text-white border border-4 border-white shadow-sm position-absolute bg-secondary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; left: -52px; top: -5px;">
                                            <i class="bi bi-gear-fill"></i>
                                        </div>
                                    @endif

                                    {{-- Konten Histori --}}
                                    <div class="p-3 border shadow-sm bg-light rounded-4">
                                        <div class="mb-2 d-flex justify-content-between align-items-start">
                                            <div>
                                                @if($history->status == 'HANDOVER')
                                                    <span class="mb-1 badge bg-primary">Aset Diserahkan</span>
                                                @elseif($history->status == 'RETURNED')
                                                    <span class="mb-1 badge bg-danger">Aset Dikembalikan</span>
                                                @else
                                                    <span class="mb-1 badge bg-secondary">{{ $history->status }}</span>
                                                @endif
                                                <div class="text-dark fw-medium small" style="line-height: 1.5;">
                                                    {{ $history->notes }}
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <div class="fw-bold text-dark small">{{ \Carbon\Carbon::parse($history->created_at)->format('d M Y') }}</div>
                                                <div class="text-muted" style="font-size: 0.7rem;">{{ \Carbon\Carbon::parse($history->created_at)->format('H:i') }} WIB</div>
                                            </div>
                                        </div>

                                        <div class="pt-2 mt-3 d-flex justify-content-between align-items-center border-top">
                                            <div class="text-muted" style="font-size: 0.75rem;">
                                                <i class="bi bi-person-badge"></i> Admin: <strong>{{ $history->admin_name }}</strong>
                                            </div>
                                            @if($history->durasi)
                                                <div class="border badge bg-warning text-dark border-warning">
                                                    <i class="bi bi-stopwatch"></i> Pemakaian: {{ $history->durasi }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach

                            {{-- Titik Awal (Registrasi) --}}
                            <div class="mb-0 position-relative">
                                <div class="text-white border border-4 border-white shadow-sm position-absolute bg-success rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; left: -52px; top: -5px;">
                                    <i class="bi bi-box-seam"></i>
                                </div>
                                <div class="p-3 border border-opacity-25 bg-success-subtle rounded-4 border-success">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="mb-1 badge bg-success">Aset Diregistrasi</span>
                                            <div class="text-dark fw-medium small">Aset masuk ke dalam sistem.</div>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold text-dark small">{{ \Carbon\Carbon::parse($asset->created_at)->format('d M Y') }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    @else
                        <div class="py-5 text-center text-muted">
                            <i class="mb-3 opacity-25 bi bi-wind display-1 d-block"></i>
                            <p>Belum ada riwayat transaksi serah terima untuk aset ini.</p>
                        </div>
                    @endif

                </div>
            </div>
        </div>
    </div>
</div>
@endsection
