@extends('layouts.app')

@push('css')
<style>
    /* Styling Card & Layout */
    .item-card {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px;
        margin-bottom: 24px; position: relative;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        border-left: 5px solid #f59e0b; /* Orange untuk Edit */
    }
    .btn-delete-item {
        position: absolute; top: 15px; right: 15px;
        color: #ef4444; background: #fef2f2; border: none;
        width: 32px; height: 32px; border-radius: 8px;
        display: flex; align-items: center; justify-content: center;
        transition: 0.2s; z-index: 10;
    }
    .btn-delete-item:hover { background: #fee2e2; transform: scale(1.1); }

    .vendor-section {
        background-color: #f8fafc; border-radius: 12px; padding: 15px;
        margin-top: 15px; border: 1px dashed #cbd5e1;
    }
    .form-control:focus, .form-select:focus {
        border-color: #f59e0b; box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
    }
    .custom-file-label { cursor: pointer; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
</style>
@endpush

@section('content')

<form action="{{ route('pr.update', $pr->id) }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')

    <div class="mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 fw-bold text-dark">Edit PR: {{ $pr->pr_number }}</h4>
            <p class="mb-0 text-muted small">Revisi permintaan atau update vendor.</p>
        </div>
        <div class="gap-2 d-none d-md-flex">
            <a href="{{ route('pr.index') }}" class="px-4 border shadow-sm btn btn-light rounded-pill fw-bold text-secondary">
                <i class="bi bi-x-lg me-1"></i> Batal
            </a>
            <button type="submit" class="px-5 text-white shadow-sm btn btn-warning rounded-pill fw-bold">
                <i class="bi bi-save me-1"></i> Simpan Perubahan
            </button>
        </div>
    </div>

    <H1></H1>

    <div class="mb-4 border-0 shadow-sm card rounded-4">
        <div class="p-4 card-body">
            <h6 class="mb-3 fw-bold text-dark"><i class="bi bi-pencil-square me-2 text-warning"></i>Informasi Umum</h6>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="fw-bold small text-muted">Tanggal Request</label>
                    <input type="date" name="request_date" class="form-control" value="{{ $pr->request_date }}" required>
                </div>

                <div class="col-md-3">
                    <label class="fw-bold small text-muted">Tanggal Dibutuhkan</label>
                    <input type="date" name="need_date" class="form-control" value="{{ $pr->need_date }}" required>
                </div>

                <div class="col-md-3">
                    <label class="fw-bold small text-muted">Departemen / PT</label>
                    <select name="company_id" class="form-select" required>
                        <option value="">-- Pilih --</option>
                        @foreach($companies as $c)
                            <option value="{{ $c->id }}" {{ $pr->company_id == $c->id ? 'selected' : '' }}>
                                {{ $c->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="fw-bold small text-muted">Keterangan</label>
                    <input type="text" name="description" class="form-control" value="{{ $pr->description }}" required>
                </div>
            </div>
        </div>
    </div>

    <div id="items-container">
        {{-- LOOP ITEM --}}
        @foreach($pr->items as $iIdx => $item)
        <div class="item-card item-row" data-index="{{ $iIdx }}">
            {{-- Hidden ID Item (Penting untuk Update) --}}
            <input type="hidden" name="items[{{ $iIdx }}][id]" value="{{ $item->id }}">

            <button type="button" class="btn-delete-item" onclick="removeItem(this)" title="Hapus Barang">
                <i class="bi bi-trash"></i>
            </button>

            <h6 class="mb-3 fw-bold text-dark"><span class="badge bg-warning text-dark rounded-circle me-2">{{ $iIdx + 1 }}</span> Detail Barang</h6>

            <div class="mb-3 row g-3">
                <div class="col-md-8">
                    <label class="small text-muted fw-bold">Pilih Barang</label>
                    <select name="items[{{ $iIdx }}][item_id]" class="form-select" required>
                        @foreach($items as $mItem)
                            <option value="{{ $mItem->id }}" {{ $mItem->id == $item->item_id ? 'selected' : '' }}>
                                {{ $mItem->code }} - {{ $mItem->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="small text-muted fw-bold">Qty</label>
                    <input type="number" name="items[{{ $iIdx }}][qty]" class="form-control" value="{{ $item->qty }}" required>
                </div>
            </div>

            <div class="vendor-section">
                <div class="vendor-container" id="vendor-container-{{ $iIdx }}">
                    {{-- LOOP VENDOR --}}
                    @foreach($item->vendorQuotes as $vIdx => $quote)
                    <div class="p-3 mb-3 bg-white border shadow-sm vendor-row rounded-3 position-relative">
                        {{-- Hidden ID Vendor Quote (Penting untuk Update) --}}
                        <input type="hidden" name="items[{{ $iIdx }}][vendors][{{ $vIdx }}][id]" value="{{ $quote->id }}">

                        <div class="top-0 p-2 position-absolute end-0">
                            <button type="button" class="btn btn-sm text-danger" onclick="removeVendor(this)"><i class="bi bi-x-lg"></i></button>
                        </div>

                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="mb-1 small text-muted">Vendor</label>
                                <select name="items[{{ $iIdx }}][vendors][{{ $vIdx }}][vendor_id]" class="form-select form-select-sm">
                                    @foreach($vendors as $v)
                                        <option value="{{ $v->id }}" {{ $v->id == $quote->vendor_id ? 'selected' : '' }}>{{ $v->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- HARGA & MATA UANG (PERBAIKAN VARIABEL DI SINI) --}}
                            <div class="col-md-4">
                                <label class="mb-1 small text-muted">Harga & Mata Uang</label>
                                <div class="input-group input-group-sm">
                                    <select name="items[{{ $iIdx }}][vendors][{{ $vIdx }}][currency]" class="form-select bg-light text-center fw-bold" style="max-width: 80px;">
                                        @foreach($currencies as $curr)
                                            <option value="{{ $curr->code }}" {{ $quote->currency == $curr->code ? 'selected' : '' }}>
                                                {{ $curr->code }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <input type="number"
                                           name="items[{{ $iIdx }}][vendors][{{ $vIdx }}][price]"
                                           class="form-control"
                                           value="{{ $quote->quoted_price }}"
                                           placeholder="0">
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="mb-1 small text-muted">Lampiran</label>
                                <div class="input-group input-group-sm">
                                    <input type="file" name="items[{{ $iIdx }}][vendors][{{ $vIdx }}][file]" class="form-control d-none" id="file-{{ $iIdx }}-{{ $vIdx }}" onchange="updateFileName(this)">
                                    <label for="file-{{ $iIdx }}-{{ $vIdx }}" class="btn {{ $quote->getFirstMediaUrl('vendor_quotes') ? 'btn-success text-white' : 'btn-outline-secondary' }} w-100 text-start custom-file-label d-flex align-items-center justify-content-between">
                                        <span class="file-text text-truncate">
                                            <i class="bi bi-paperclip me-1"></i>
                                            {{ $quote->getFirstMediaUrl('vendor_quotes') ? 'Ganti File' : 'Upload' }}
                                        </span>
                                        @if($media = $quote->getFirstMedia('vendor_quotes'))
                                            <a href="{{ $media->getUrl() }}" target="_blank" class="text-white ms-2" onclick="event.stopPropagation()"><i class="bi bi-eye"></i></a>
                                        @endif
                                    </label>
                                </div>
                            </div>

                            <div class="mt-2 col-md-6">
                                <input type="url" name="items[{{ $iIdx }}][vendors][{{ $vIdx }}][link]" class="form-control form-control-sm" value="{{ $quote->reference_link }}" placeholder="Link URL (Opsional)...">
                            </div>
                            <div class="mt-2 col-md-6">
                                <input type="text" name="items[{{ $iIdx }}][vendors][{{ $vIdx }}][notes]" class="form-control form-control-sm" value="{{ $quote->notes }}" placeholder="Catatan (Garansi/Pajak)...">
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                <button type="button" class="mt-2 border btn btn-sm btn-light text-primary fw-bold" onclick="addVendor({{ $iIdx }})">
                    <i class="bi bi-plus-lg"></i> Tambah Vendor
                </button>
            </div>
        </div>
        @endforeach
    </div>

    <div class="pb-5 mb-5 text-center">
        <button type="button" class="px-4 py-2 border shadow-sm btn btn-white border-warning text-warning fw-bold rounded-pill" id="btn-add-item">
            <i class="bi bi-plus-circle-fill me-1"></i> Tambah Barang Lain
        </button>
    </div>

    <div class="px-3 py-3 bg-white shadow-lg fixed-bottom border-top d-md-none" style="z-index: 1050;">
        <div class="gap-2 d-flex">
            <a href="{{ route('pr.index') }}" class="border btn btn-light fw-bold w-100">Batal</a>
            <button type="submit" class="text-white shadow-sm btn btn-warning fw-bold w-100">Simpan</button>
        </div>
    </div>
    <div style="height: 80px;"></div>

</form>

{{-- TEMPLATE UNTUK ITEM BARU (HIDDEN) --}}
<div id="template-area" class="d-none">
    <div class="item-card item-row" data-index="XX">
        <input type="hidden" name="items[XX][id]" value="">
        <button type="button" class="btn-delete-item" onclick="removeItem(this)"><i class="bi bi-trash"></i></button>

        <h6 class="mb-3 fw-bold text-dark"><span class="badge bg-warning text-dark rounded-circle me-2">#</span> Detail Barang</h6>

        <div class="mb-3 row g-3">
            <div class="col-md-8">
                <select name="items[XX][item_id]" class="form-select"><option value="">-- Pilih Barang --</option>
                    @foreach($items as $item) <option value="{{ $item->id }}">{{ $item->code }} - {{ $item->name }}</option> @endforeach
                </select>
            </div>
            <div class="col-md-4"><input type="number" name="items[XX][qty]" class="form-control" placeholder="1"></div>
        </div>

        <div class="vendor-section">
            <div class="vendor-container" id="vendor-container-XX">
                {{-- VENDOR ROW TEMPLATE --}}
                <div class="p-3 mb-3 bg-white border shadow-sm vendor-row rounded-3 position-relative">
                    <input type="hidden" name="items[XX][vendors][0][id]" value="">

                    <div class="top-0 p-2 position-absolute end-0"><button type="button" class="btn btn-sm text-danger" onclick="removeVendor(this)"><i class="bi bi-x-lg"></i></button></div>

                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="mb-1 small text-muted">Vendor</label>
                            <select name="items[XX][vendors][0][vendor_id]" class="form-select form-select-sm"><option value="">- Vendor -</option>
                                @foreach($vendors as $v) <option value="{{ $v->id }}">{{ $v->name }}</option> @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="mb-1 small text-muted">Harga & Mata Uang</label>
                            <div class="input-group input-group-sm">
                                <select name="items[XX][vendors][0][currency]" class="form-select bg-light text-center fw-bold" style="max-width: 80px;">
                                    @foreach($currencies as $curr) <option value="{{ $curr->code }}">{{ $curr->code }}</option> @endforeach
                                </select>
                                <input type="number" name="items[XX][vendors][0][price]" class="form-control" placeholder="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="mb-1 small text-muted">Lampiran</label>
                            <div class="input-group input-group-sm">
                                <input type="file" name="items[XX][vendors][0][file]" class="form-control d-none" id="file-XX-0" onchange="updateFileName(this)">
                                <label for="file-XX-0" class="btn btn-outline-secondary w-100 text-start custom-file-label"><i class="bi bi-paperclip"></i> <span class="file-text">Upload</span></label>
                            </div>
                        </div>
                        <div class="mt-2 col-md-6"><input type="url" name="items[XX][vendors][0][link]" class="form-control form-control-sm" placeholder="Link URL..."></div>
                        <div class="mt-2 col-md-6"><input type="text" name="items[XX][vendors][0][notes]" class="form-control form-control-sm" placeholder="Catatan..."></div>
                    </div>
                </div>
            </div>
            <button type="button" class="mt-2 border btn btn-sm btn-light text-primary fw-bold" onclick="addVendor('XX')">+ Vendor</button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    // Inisialisasi index item berdasarkan jumlah item yang ada
    let itemIdx = {{ count($pr->items) > 0 ? count($pr->items) - 1 : 0 }};

    // --- 1. TAMBAH ITEM BARU ---
    document.getElementById('btn-add-item').addEventListener('click', function() {
        itemIdx++;
        let container = document.getElementById('items-container');
        let templateHTML = document.getElementById('template-area').innerHTML;

        // Replace XX dengan index baru secara global
        let newHTML = templateHTML.replace(/XX/g, itemIdx);

        let tempDiv = document.createElement('div');
        tempDiv.innerHTML = newHTML;
        let newElement = tempDiv.firstElementChild;

        // Set ID container vendor
        let vendorContainer = newElement.querySelector('.vendor-container');
        vendorContainer.id = `vendor-container-${itemIdx}`;

        // Set onclick tombol tambah vendor
        let btnAddV = newElement.querySelector('button[onclick^="addVendor"]');
        btnAddV.setAttribute('onclick', `addVendor(${itemIdx})`);

        container.appendChild(newElement);
    });

    // --- 2. TAMBAH VENDOR BARU ---
    window.addVendor = function(parentIndex) {
        let container = document.getElementById(`vendor-container-${parentIndex}`);
        let templateHTML = document.getElementById('template-area').innerHTML;

        // Ambil elemen vendor-row dari template bersih
        let tempDiv = document.createElement('div');
        tempDiv.innerHTML = templateHTML;
        let sourceRow = tempDiv.querySelector('.vendor-row');
        let newRow = sourceRow.cloneNode(true);

        // Buat index unik untuk vendor baru
        let newVendorIdx = Date.now() + Math.floor(Math.random() * 1000);

        // --- UPDATE NAME ATTRIBUTES ---
        // Ganti XX dengan parentIndex dan 0 dengan newVendorIdx

        // ID (hidden)
        newRow.querySelector('input[name*="[id]"]').name = `items[${parentIndex}][vendors][${newVendorIdx}][id]`;

        // Vendor ID
        newRow.querySelector('select[name*="[vendor_id]"]').name = `items[${parentIndex}][vendors][${newVendorIdx}][vendor_id]`;

        // Currency & Price
        newRow.querySelector('select[name*="[currency]"]').name = `items[${parentIndex}][vendors][${newVendorIdx}][currency]`;
        newRow.querySelector('input[name*="[price]"]').name = `items[${parentIndex}][vendors][${newVendorIdx}][price]`;

        // Link & Notes
        newRow.querySelector('input[name*="[link]"]').name = `items[${parentIndex}][vendors][${newVendorIdx}][link]`;
        newRow.querySelector('input[name*="[notes]"]').name = `items[${parentIndex}][vendors][${newVendorIdx}][notes]`;

        // File Input & Label
        let fileInp = newRow.querySelector('input[type="file"]');
        let fileLbl = newRow.querySelector('.custom-file-label');
        if(fileInp && fileLbl) {
            let uniqID = `file-${parentIndex}-${newVendorIdx}`;
            fileInp.name = `items[${parentIndex}][vendors][${newVendorIdx}][file]`;
            fileInp.id = uniqID;
            fileLbl.setAttribute('for', uniqID);

            // Reset tampilan label
            fileLbl.classList.remove('btn-success', 'text-white');
            fileLbl.classList.add('btn-outline-secondary');
            fileLbl.querySelector('.file-text').textContent = "Upload";
        }

        container.appendChild(newRow);
    }

    // --- 3. HELPER FUNCTIONS ---
    window.removeItem = function(btn) {
        if(confirm('Hapus item ini?')) btn.closest('.item-row').remove();
    }

    window.removeVendor = function(btn) {
        btn.closest('.vendor-row').remove();
    }

    window.updateFileName = function(input) {
        let label = input.nextElementSibling;
        let textSpan = label.querySelector('.file-text');
        if (input.files && input.files[0]) {
            textSpan.textContent = input.files[0].name;
            label.classList.remove('btn-outline-secondary');
            label.classList.add('btn-success', 'text-white');
        } else {
            textSpan.textContent = "Upload";
            label.classList.remove('btn-success', 'text-white');
            label.classList.add('btn-outline-secondary');
        }
    }
</script>
@endpush
