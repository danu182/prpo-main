<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VendorController extends Controller
{
    // 1. TAMPILKAN DAFTAR VENDOR (INDEX)
    public function index(Request $request)
    {
        $search = $request->input('search');

        $vendors = Vendor::when($search, function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%");
            })
            ->orderBy('name', 'asc')
            ->paginate(10)
            ->withQueryString();

        return view('vendors.index', compact('vendors', 'search'));
    }

    // 2. HALAMAN TAMBAH VENDOR
    public function create()
    {
        $banks = \App\Models\Bank::where('is_active', true)->orderBy('name')->get();
        return view('vendors.create', compact('banks'));
    }

    // 3. PROSES SIMPAN VENDOR BARU
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'                => 'required|string|max:255',
            'email'               => 'nullable|email|max:255',
            'phone'               => 'nullable|string|max:50',
            'tax_id'              => 'nullable|string|max:100',
            'pic_name'            => 'nullable|string|max:255',
            'pic_phone'           => 'nullable|string|max:50',
            'bank_name'           => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:100',
            'bank_account_name'   => 'nullable|string|max:255',
            'payment_terms_days'  => 'required|integer|min:0',
            'address'             => 'nullable|string',
            'email'  => 'nullable|email|max:255|unique:vendors,email',
            'tax_id' => 'nullable|string|max:100|unique:vendors,tax_id', // NPWP tidak boleh kembar
        ]);

        try {
            DB::beginTransaction();

            // Generate Kode Vendor Otomatis (VND-00001)
            $prefix = 'VND-';
            $lastVendor = Vendor::where('code', 'like', $prefix . '%')->orderBy('id', 'desc')->first();
            $nextNumber = $lastVendor ? ((int) substr($lastVendor->code, strlen($prefix))) + 1 : 1;
            $validated['code'] = $prefix . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
            $validated['is_active'] = true;

            Vendor::create($validated);

            DB::commit();
            return redirect()->route('vendors.index')->with('success', "Vendor berhasil ditambahkan dengan Kode: {$validated['code']}");
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    // 4. HALAMAN EDIT VENDOR
    public function edit(Vendor $vendor)
    {
        $banks = \App\Models\Bank::where('is_active', true)->orderBy('name')->get();
        return view('vendors.edit', compact('vendor', 'banks'));
    }

    // 5. PROSES UPDATE VENDOR
    public function update(Request $request, Vendor $vendor)
    {
        $validated = $request->validate([
            'name'                => 'required|string|max:255',
            'email'               => 'nullable|email|max:255',
            'phone'               => 'nullable|string|max:50',
            'tax_id'              => 'nullable|string|max:100',
            'pic_name'            => 'nullable|string|max:255',
            'pic_phone'           => 'nullable|string|max:50',
            'bank_name'           => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:100',
            'bank_account_name'   => 'nullable|string|max:255',
            'payment_terms_days'  => 'required|integer|min:0',
            'address'             => 'nullable|string',
            'email'  => 'nullable|email|max:255|unique:vendors,email,' . $vendor->id,
            'tax_id' => 'nullable|string|max:100|unique:vendors,tax_id,' . $vendor->id,
        ]);

        try {
            $vendor->update($validated);
            return redirect()->route('vendors.index')->with('success', 'Data Vendor berhasil diperbarui!');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Gagal update data: ' . $e->getMessage());
        }
    }

    // 6. AKTIF / NONAKTIFKAN VENDOR
    public function toggleStatus(Vendor $vendor)
    {
        $vendor->update(['is_active' => !$vendor->is_active]);
        $status = $vendor->is_active ? 'diaktifkan' : 'dinonaktifkan';
        return back()->with('success', "Vendor {$vendor->name} berhasil {$status}.");
    }
}
