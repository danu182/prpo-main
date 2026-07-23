@extends('layouts.app') @section('content')
<div class="px-0 container-fluid">
    <div class="mb-3 d-flex justify-content-between align-items-center">
        <h2>Daftar Pengakuan Aset</h2>
        <a href="{{ route('asset-capitalizations.create') }}" class="btn btn-primary">
            + Tambah Pengakuan Aset
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card">
        <div class="card-body">
            <table class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th>No Aset</th>
                        <th>Nama Barang</th>
                        <th>Serial Number</th>
                        <th>Status</th>
                        <th>Tanggal Diakui</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($assets as $row)
                    <tr>
                        <td>{{ $row->asset_number }}</td>
                        <td>{{ $row->item->name ?? '-' }}</td>
                        <td>{{ $row->serial_number ?? '-' }}</td>
                        <td>
                            <span class="badge {{ $row->status_id == 31 ? 'bg-success' : 'bg-secondary' }}">
                                {{ $row->status->name ?? 'Unknown' }}
                            </span>
                        </td>
                        <td>{{ $row->created_at->format('d M Y') }}</td>
                        <td>
                            <a href="{{ route('asset-capitalizations.show', $row->id) }}" class="text-white btn btn-sm btn-info">
                                Detail
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center">Belum ada data aset.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="mt-3">
                {{ $assets->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
