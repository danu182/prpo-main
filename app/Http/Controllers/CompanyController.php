<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        $companies = Company::when($search, function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%");
            })
            ->orderBy('is_head_office', 'desc') // Head Office selalu di atas
            ->orderBy('name', 'asc')
            ->paginate(10)
            ->withQueryString();

        return view('companies.index', compact('companies', 'search'));
    }

    public function create()
    {
        return view('companies.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'           => 'required|string|max:255|unique:companies,code',
            'name'           => 'required|string|max:255',
            'phone'          => 'nullable|string|max:50',
            'email'          => 'nullable|email|max:100',
            'tax_id'         => 'nullable|string|max:100',
            'address'        => 'nullable|string',
            'logo'           => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048', // Maksimal 2MB
        ]);

        try {
            DB::beginTransaction();

            $validated['is_head_office'] = $request->has('is_head_office');

            // Proses Upload Logo dengan Struktur Folder Spesifik
            if ($request->hasFile('logo')) {
                // Membuat nama folder sesuai dengan struktur yang diminta: master/vendor/KODE_PT
                // Kita gunakan str_replace untuk memastikan tidak ada spasi atau karakter ilegal pada nama folder
                $safeCode = str_replace([' ', '/', '\\'], '_', $validated['code']);
                $directory = 'master/vendor/' . $safeCode;

                $validated['logo_path'] = $request->file('logo')->store($directory, 'public');
            }

            Company::create($validated);

            DB::commit();
            return redirect()->route('companies.index')->with('success', "Perusahaan {$validated['name']} berhasil ditambahkan!");
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    public function edit(Company $company)
    {
        return view('companies.edit', compact('company'));
    }

    public function update(Request $request, Company $company)
    {
        $validated = $request->validate([
            'code'           => 'required|string|max:255|unique:companies,code,' . $company->id,
            'name'           => 'required|string|max:255',
            'phone'          => 'nullable|string|max:50',
            'email'          => 'nullable|email|max:100',
            'tax_id'         => 'nullable|string|max:100',
            'address'        => 'nullable|string',
            'logo'           => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048',
        ]);

        try {
            DB::beginTransaction();

            $validated['is_head_office'] = $request->has('is_head_office');

            // Proses Update Logo dengan Struktur Folder Spesifik
            if ($request->hasFile('logo')) {
                // Hapus logo lama jika ada (Sistem akan mencari di path yang lama dan menghapusnya)
                if ($company->logo_path) {
                    Storage::disk('public')->delete($company->logo_path);
                }

                // Menentukan folder baru berdasarkan kode yang disubmit
                $safeCode = str_replace([' ', '/', '\\'], '_', $validated['code']);
                $directory = 'master/vendor/' . $safeCode;

                $validated['logo_path'] = $request->file('logo')->store($directory, 'public');
            }

            $company->update($validated);

            DB::commit();
            return redirect()->route('companies.index')->with('success', 'Data Perusahaan berhasil diperbarui!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Gagal update data: ' . $e->getMessage());
        }
    }


    // ====================================================
    // TAMPILKAN DETAIL PERUSAHAAN (SHOW)
    // ====================================================
    public function show(Company $company)
    {
        // Opsional: Jika nanti Anda punya relasi seperti $company->warehouses atau $company->users,
        // Anda bisa memanggilnya di sini menggunakan $company->load('warehouses');

        return view('companies.show', compact('company'));
    }



    public function destroy(Company $company)
    {
        try {
            // Hapus gambar dari storage jika ada
            if ($company->logo_path) {
                Storage::disk('public')->delete($company->logo_path);
            }
            $company->delete();
            return redirect()->route('companies.index')->with('success', 'Perusahaan berhasil dihapus!');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menghapus perusahaan, data mungkin sedang digunakan.');
        }
    }
}
