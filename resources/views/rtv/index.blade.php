@extends('layouts.app')

@section('content')
<div class="pb-5 container-fluid text-dark">

    <div class="mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-arrow-return-left me-2 text-danger"></i>Riwayat Return to Vendor (RTV)</h4>
            <div class="mt-1 text-muted small">Daftar barang yang dikembalikan ke pihak Supplier/Vendor.</div>
        </div>

        <div class="gap-2 mt-3 mt-md-0 d-flex">
            <form action="{{ route('rtv.index') }}" method="GET" class="d-flex">
                <div class="input-group">
                    <input type="text" name="search" class="form-control form-control-sm border-secondary-subtle" placeholder="Cari No RTV, Vendor..." value="{{ request('search') }}">
                    <button class="px-3 btn btn-sm btn-primary" type="submit"><i class="bi bi-search"></i></button>
                </div>
            </form>

            {{-- 🔥 TOMBOL BUAT RETUR BARU (Diarahkan ke daftar GR) 🔥 --}}
            <a href="{{ route('gr.index') }}" class="shadow-sm btn btn-sm btn-danger fw-bold d-flex align-items-center">
                <i class="bi bi-plus-lg me-1"></i> Buat Retur Baru
            </a>
        </div>
    </div>

    <div class="border-0 shadow-sm card rounded-4">
        <div class="p-0 card-body table-responsive rounded-4">
            <table class="table mb-0 align-middle table-hover">
                <thead class="text-white small text-uppercase" style="background-color: #212529;">
                    <tr>
                        <th class="py-3 ps-4">No Dokumen (RTV)</th>
                        <th class="py-3">Tanggal Retur</th>
                        <th class="py-3">Referensi GR & PO</th>
                        <th class="py-3">Vendor</th>
                        <th class="py-3">No. SJ Keluar</th>
                        <th class="py-3 text-center pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rtvs as $rtv)
                        <tr class="border-bottom">
                            <td class="py-3 ps-4">
                                <a href="{{ route('rtv.show', $rtv->id) }}" class="fw-bold text-danger text-decoration-none">
                                    {{ $rtv->rtv_number }}
                                </a>
                            </td>
                            <td class="text-muted">{{ \Carbon\Carbon::parse($rtv->return_date)->format('d M Y') }}</td>
                            <td>
                                <div class="small fw-bold text-success">{{ optional($rtv->goodsReceipt)->gr_number }}</div>
                                <div class="small text-primary" style="font-size: 0.7rem;">PO: {{ optional(optional($rtv->goodsReceipt)->po)->po_number }}</div>
                            </td>
                            <td class="fw-bold text-dark">{{ optional($rtv->vendor)->name }}</td>
                            <td class="text-muted small">{{ $rtv->delivery_note_number ?? '-' }}</td>
                            <td class="py-3 text-center pe-4">
                                <a href="{{ route('rtv.show', $rtv->rtv_number) }}" class="px-3 btn btn-sm btn-outline-danger rounded-pill fw-bold">
                                    <i class="bi bi-eye-fill"></i> Detail
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-4 text-center text-muted">
                                <i class="mb-2 bi bi-inboxes fs-1 d-block text-secondary"></i>
                                Belum ada dokumen retur yang diterbitkan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4 d-flex justify-content-center">
        {{ $rtvs->links() }}
    </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Cek apakah ada flash data 'print_url' dari controller
        @if(session('print_url'))
            Swal.fire({
                title: 'RTV Berhasil Dibuat! 🎉',
                text: "Apakah Anda ingin mencetak dokumen fisik Return To Vendor (RTV) ini untuk dibawa supir?",
                icon: 'success',
                showCancelButton: true,
                confirmButtonColor: '#0d6efd',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-printer"></i> Ya, Cetak Sekarang',
                cancelButtonText: 'Tutup'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Buka tab baru untuk print
                    window.open('{{ session('print_url') }}', '_blank');
                }
            });
        @endif
    });
</script>
@endpush
