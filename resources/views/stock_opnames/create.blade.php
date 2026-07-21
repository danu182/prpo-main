@extends('layouts.app')

@section('content')
<div class="container pb-5">
    <div class="mb-4">
        <h4 class="fw-bold text-dark"><i class="bi bi-play-circle me-2 text-primary"></i> Buka Sesi Stock Opname</h4>
        <p class="text-muted">Sistem akan memotret saldo saat ini dan membekukan gudang sementara.</p>
    </div>

    @if(session('error'))
        <div class="shadow-sm alert alert-danger fw-bold rounded-3">{{ session('error') }}</div>
    @endif

    <div class="mx-auto border-0 shadow-sm card rounded-4 col-md-8">
        <div class="p-4 card-body">
            <form action="{{ route('stock-opnames.store') }}" method="POST" id="formOpname">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-bold text-muted small">Pilih Entitas (PT) <span class="text-danger">*</span></label>
                    <select name="company_id" class="form-select bg-light" required>
                        <option value="">-- Pilih PT --</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}">{{ $company->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold text-muted small">Pilih Gudang (Lokasi Opname) <span class="text-danger">*</span></label>
                    <select name="warehouse_id" class="form-select bg-light" required>
                        <option value="">-- Pilih Gudang --</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold text-muted small">Tanggal Mulai <span class="text-danger">*</span></label>
                    <input type="date" name="start_date" class="form-control fw-bold text-primary" value="{{ date('Y-m-d') }}" required>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold text-muted small">Catatan / Instruksi Auditor</label>
                    <textarea name="notes" class="form-control bg-light" rows="3" placeholder="Contoh: Fokus pada audit barang elektronik di Rak A..."></textarea>
                </div>

                <div class="pt-3 text-end border-top">
                    <a href="{{ route('stock-opnames.index') }}" class="px-4 btn btn-light fw-bold rounded-pill me-2">Batal</a>
                    <button type="submit" class="px-5 btn btn-primary fw-bold rounded-pill" onclick="this.innerHTML='<span class=\'spinner-border spinner-border-sm me-2\'></span>Memproses...'; this.form.submit();">
                        <i class="bi bi-camera me-1"></i> Mulai & Potret Saldo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
