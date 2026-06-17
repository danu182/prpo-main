@extends('layouts.app') @section('content')
<div class="container-fluid">
    <div class="mb-3">
        <a href="{{ route('asset-capitalizations.index') }}" class="btn btn-secondary">
            &larr; Kembali ke Daftar
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Detail Aset: {{ $asset->asset_number }}</h4>

            @if($asset->status_id == 30)
                <form id="form-void-asset" action="{{ route('asset-capitalizations.void', $asset->id) }}" method="POST" class="m-0">
                    @csrf
                    <button type="button" class="btn btn-danger" onclick="confirmVoid()">
                        <i class="fas fa-ban"></i> Batalkan Aset
                    </button>
                </form>
            @endif
        </div>
        <div class="card-body">
            <table class="table table-striped">
                <tr>
                    <th width="200">Nomor Aset</th>
                    <td>{{ $asset->asset_number }}</td>
                </tr>
                <tr>
                    <th>Nama Barang (Item)</th>
                    <td>{{ $asset->item->name ?? '-' }}</td>
                </tr>
                <tr>
                    <th>Serial Number</th>
                    <td>{{ $asset->serial_number ?? '-' }}</td>
                </tr>
                <tr>
                    <th>Status Saat Ini</th>
                    <td>
                        <strong>{{ $asset->status->name ?? 'Unknown' }}</strong>
                    </td>
                </tr>
                <tr>
                    <th>Catatan</th>
                    <td>{!! nl2br(e($asset->notes)) !!}</td>
                </tr>
                <tr>
                    <th>Tanggal Diakui</th>
                    <td>{{ $asset->created_at->format('d F Y H:i') }}</td>
                </tr>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    function confirmVoid() {
        Swal.fire({
            title: 'Batalkan Pengakuan Aset?',
            text: "Nilai aset akan di-nol-kan dan 1 Unit stok akan dikembalikan ke gudang asal secara otomatis. Aksi ini tidak dapat dibatalkan ulang.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545', // Warna merah Bootstrap (Danger)
            cancelButtonColor: '#6c757d',  // Warna abu-abu Bootstrap (Secondary)
            confirmButtonText: '<i class="fas fa-check"></i> Ya, Batalkan!',
            cancelButtonText: 'Kembali',
            reverseButtons: true // Memutar posisi tombol agar 'Cancel' di kiri dan 'Ya' di kanan
        }).then((result) => {
            if (result.isConfirmed) {
                // Jika user klik "Ya", maka form baru akan di-submit secara paksa lewat sistem
                document.getElementById('form-void-asset').submit();
            }
        });
    }
</script>

@endpush
