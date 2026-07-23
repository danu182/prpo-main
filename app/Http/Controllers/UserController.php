<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Department;
use App\Models\Warehouse; // 🔥 Import Model Warehouse
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\UsersImport;
use App\Exports\UsersExport;

class UserController extends Controller
{
    // 1. TAMPILKAN DAFTAR PENGGUNA (DENGAN MODAL)
    public function index(Request $request)
    {
        $search = $request->input('search');

        // 🔥 Tambahkan 'warehouses' di dalam with() agar query efisien
        $users = User::with(['company', 'department', 'roles', 'warehouses'])
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('job_title', 'like', "%{$search}%");
            })
            ->orderBy('name', 'asc')
            ->paginate(10)
            ->withQueryString();

        $companies = Company::orderBy('name', 'asc')->get();
        $departments = Department::orderBy('name', 'asc')->get();
        $roles = Role::orderBy('name', 'asc')->get();
        $warehouses = Warehouse::orderBy('name', 'asc')->get(); // 🔥 Tarik master data Gudang

        return view('users.index', compact('users', 'search', 'companies', 'departments', 'roles', 'warehouses'));
    }

    // 2. SIMPAN PENGGUNA BARU
    public function store(Request $request)
    {
        $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|string|email|max:255|unique:users',
            'password'      => 'required|string|min:6|confirmed',
            'company_id'    => 'nullable|exists:companies,id',
            'department_id' => 'nullable|exists:departments,id',
            'job_title'     => 'nullable|string|max:255',
            'roles'         => 'nullable|array',
            'warehouse_ids' => 'nullable|array' // 🔥 Validasi Array Gudang
        ]);

        try {
            $user = User::create([
                'name'          => $request->name,
                'email'         => $request->email,
                'password'      => Hash::make($request->password),
                'company_id'    => $request->company_id,
                'department_id' => $request->department_id,
                'job_title'     => $request->job_title,
                'is_active'     => 1,
            ]);

            if ($request->has('roles')) {
                $user->syncRoles($request->roles);
            }

            // 🔥 Terapkan Akses Gudang (Untuk Andi/Joko/Budi) 🔥
            if ($request->has('warehouse_ids')) {
                $user->warehouses()->sync($request->warehouse_ids);
            }

            return back()->with('success', 'Pengguna baru ('.$user->name.') berhasil ditambahkan!');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menambahkan user: ' . $e->getMessage());
        }
    }

    // 3. UPDATE DATA PENGGUNA & ROLE
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|string|email|max:255|unique:users,email,' . $id,
            'company_id'    => 'nullable|exists:companies,id',
            'department_id' => 'nullable|exists:departments,id',
            'job_title'     => 'nullable|string|max:255',
            'is_active'     => 'required|boolean',
            'roles'         => 'nullable|array',
            'warehouse_ids' => 'nullable|array' // 🔥 Validasi Array Gudang
        ]);

        try {
            $user->name          = $request->name;
            $user->email         = $request->email;
            $user->company_id    = $request->company_id;
            $user->department_id = $request->department_id;
            $user->job_title     = $request->job_title;
            $user->is_active     = $request->is_active;

            if ($request->filled('password')) {
                $request->validate(['password' => 'min:6|confirmed']);
                $user->password = Hash::make($request->password);
            }

            $user->save();
            $user->syncRoles($request->roles ?? []);

            // 🔥 Update Akses Gudang 🔥
            $user->warehouses()->sync($request->warehouse_ids ?? []);

            return back()->with('success', 'Data & Hak Akses ('.$user->name.') berhasil diperbarui!');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal memperbarui user: ' . $e->getMessage());
        }
    }

    // 4. HAPUS PENGGUNA
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        if (auth()->id() == $user->id) {
            return back()->with('error', 'Akses Ditolak: Anda tidak dapat menghapus akun Anda sendiri!');
        }

        try {
            $user->delete();
            return back()->with('success', 'Pengguna berhasil dihapus permanen!');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menghapus user. Pastikan user ini belum memiliki riwayat transaksi.');
        }
    }



    // Tambahkan fungsi ini di dalam UserController.php

    // 5. HALAMAN KHUSUS IMPORT / EXPORT
    public function importForm()
    {
        return view('users.import');
    }

    // 6. DOWNLOAD TEMPLATE EXCEL
    public function downloadTemplate()
    {
        return Excel::download(new \App\Exports\UsersExport(true), 'Template_Import_Users.xlsx');
    }

    // 7. EXPORT DATA USER KE EXCEL
    public function export()
    {
        return Excel::download(new \App\Exports\UsersExport(false), 'Data_Users_Export.xlsx');
    }

    // 6. PROSES BACA EXCEL & TAMPILKAN PREVIEW (DETEKTIF ERROR)
    public function previewImport(Request $request)
    {
        $request->validate(['file_excel' => 'required|mimes:xlsx,xls,csv|max:2048']);

        try {
            $data = Excel::toArray(new UsersImport, $request->file('file_excel'));
            $rows = $data[0] ?? [];

            $previewData = [];
            $hasError = false;

            // Ambil referensi ID untuk validasi
            $companyIds = Company::pluck('id')->toArray();
            $deptIds = Department::pluck('id')->toArray();

            foreach ($rows as $row) {
                // Lewati baris yang benar-benar kosong
                if (empty($row['name']) && empty($row['email'])) continue;

                $errors = [];

                // 1. Validasi Nama & Email
                if (empty($row['name'])) $errors[] = 'Nama Lengkap wajib diisi.';
                if (empty($row['email'])) {
                    $errors[] = 'Email wajib diisi.';
                } elseif (!filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Format Email tidak valid.';
                } elseif (User::where('email', $row['email'])->exists()) {
                    $errors[] = 'Email sudah terdaftar di sistem.';
                }

                // 2. Validasi ID Perusahaan & Departemen
                if (!empty($row['company_id']) && !in_array($row['company_id'], $companyIds)) {
                    $errors[] = 'ID Perusahaan tidak ditemukan.';
                }
                if (!empty($row['department_id']) && !in_array($row['department_id'], $deptIds)) {
                    $errors[] = 'ID Departemen tidak ditemukan.';
                }

                if (count($errors) > 0) $hasError = true;

                $row['errors'] = $errors;
                $previewData[] = $row;
            }

            session()->put('users_preview_data', $previewData);
            return view('users.preview', compact('previewData', 'hasError'));

        } catch (\Exception $e) {
            return back()->with('error', 'Gagal membaca file Excel: Pastikan format sesuai Template.');
        }
    }

    // 7. EKSEKUSI DATA (HANYA SIMPAN YANG AMAN)
    public function processImport(Request $request)
    {
        $rows = session()->get('users_preview_data');
        if (!$rows) return redirect()->route('users.import_form')->with('error', 'Sesi habis. Upload ulang file Anda.');

        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $countSuccess = 0;
            foreach ($rows as $row) {
                // 🔥 LEWATI BARIS YANG PUNYA ERROR 🔥
                if (count($row['errors']) > 0) continue;

                $user = User::create([
                    'name'          => $row['name'],
                    'email'         => $row['email'],
                    'password'      => Hash::make($row['password'] ?: '123456'),
                    'company_id'    => $row['company_id'] ?: null,
                    'department_id' => $row['department_id'] ?: null,
                    'job_title'     => $row['job_title'] ?: null,
                    'is_active'     => 1,
                ]);

                $roleName = !empty($row['role']) ? trim($row['role']) : 'Staff';
                $user->assignRole($roleName);

                $countSuccess++;
            }

            \Illuminate\Support\Facades\DB::commit();
            session()->forget('users_preview_data');

            return redirect()->route('users.index')->with('success', "$countSuccess Data Karyawan (Valid) berhasil di-import!");
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return redirect()->route('users.import_form')->with('error', 'Gagal menyimpan ke database: ' . $e->getMessage());
        }
    }

}
