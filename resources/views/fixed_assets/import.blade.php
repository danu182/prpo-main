@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">Upload Data Aset Lama</div>
        <div class="card-body">
            <form action="{{ route('assets.import.process') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label>Pilih File Excel (.xlsx)</label>
                    <input type="file" name="file" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-success">Proses Import</button>
                <a href="{{ route('assets.index') }}" class="btn btn-secondary">Kembali</a>
            </form>
        </div>
    </div>
</div>
@endsection
