<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Department;
use App\Models\Warehouse;
use App\Models\User;
use App\Models\JobTitle;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\UsersImport;
use App\Exports\UsersExport;

class UserController extends Controller
{
    // 1. TAMPILKAN DAFTAR PENGGUNA
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search'));

        $users = User::with(['company', 'department', 'roles', 'warehouses'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('job_title', 'like', "%{$search}%");
                });
            })
            ->orderBy('name', 'asc')
            ->paginate(10)
            ->withQueryString();

        $companies   = Company::orderBy('name', 'asc')->get();
        $departments = Department::orderBy('name', 'asc')->get();
        $roles       = Role::orderBy('name', 'asc')->get();
        $warehouses  = Warehouse::orderBy('name', 'asc')->get();
        $jobTitles   = JobTitle::where('is_active', true)->orderBy('name', 'asc')->get();

        return view('users.index', compact('users', 'search', 'companies', 'departments', 'roles', 'warehouses', 'jobTitles'));
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
            'job_title'     => 'required|string|max:255',
            'roles'         => 'nullable|array',
            'warehouse_ids' => 'nullable|array',
            'avatar'        => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'signature'     => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
        ]);

        DB::beginTransaction();
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

            $needsUpdate = false;

            if ($request->hasFile('avatar')) {
                $user->avatar = $request->file('avatar')->store("users/{$user->id}/avatar", 'public');
                $needsUpdate = true;
            }

            if ($request->hasFile('signature')) {
                $user->signature = $request->file('signature')->store("users/{$user->id}/signature", 'public');
                $needsUpdate = true;
            }

            if ($needsUpdate) {
                $user->save();
            }

            if ($request->has('roles')) {
                $user->syncRoles($request->roles);
            }
            if ($request->has('warehouse_ids')) {
                $user->warehouses()->sync($request->warehouse_ids);
            }

            DB::commit();
            return back()->with('success', 'Pengguna baru (' . $user->name . ') berhasil ditambahkan!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Gagal menambahkan user: ' . $e->getMessage());
        }
    }

    // 3. UPDATE DATA PENGGUNA & ROLE
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $rules = [
            'name'          => 'required|string|max:255',
            'email'         => 'required|string|email|max:255|unique:users,email,' . $id,
            'company_id'    => 'nullable|exists:companies,id',
            'department_id' => 'nullable|exists:departments,id',
            'job_title'     => 'required|string|max:255',
            'is_active'     => 'required|boolean',
            'roles'         => 'nullable|array',
            'warehouse_ids' => 'nullable|array',
            'avatar'        => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'signature'     => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
        ];

        if ($request->filled('password')) {
            $rules['password'] = 'string|min:6|confirmed';
        }

        $request->validate($rules);

        DB::beginTransaction();
        try {
            $user->name          = $request->name;
            $user->email         = $request->email;
            $user->company_id    = $request->company_id;
            $user->department_id = $request->department_id;
            $user->job_title     = $request->job_title;
            $user->is_active     = $request->is_active;

            if ($request->filled('password')) {
                $user->password = Hash::make($request->password);
            }

            if ($request->hasFile('avatar')) {
                if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                    Storage::disk('public')->delete($user->avatar);
                }
                $user->avatar = $request->file('avatar')->store("users/{$user->id}/avatar", 'public');
            }

            if ($request->hasFile('signature')) {
                if ($user->signature && Storage::disk('public')->exists($user->signature)) {
                    Storage::disk('public')->delete($user->signature);
                }
                $user->signature = $request->file('signature')->store("users/{$user->id}/signature", 'public');
            }

            $user->save();
            $user->syncRoles($request->roles ?? []);
            $user->warehouses()->sync($request->warehouse_ids ?? []);

            DB::commit();
            return back()->with('success', 'Data & Hak Akses (' . $user->name . ') berhasil diperbarui!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Gagal memperbarui user: ' . $e->getMessage());
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
            $userId = $user->id;
            $user->delete();

            // Bersihkan folder avatar & signature milik user jika penghapusan berhasil
            Storage::disk('public')->deleteDirectory("users/{$userId}");

            return back()->with('success', 'Pengguna berhasil dihapus permanen!');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menghapus user. Pastikan user ini belum memiliki riwayat transaksi.');
        }
    }

    // 5. HALAMAN KHUSUS IMPORT / EXPORT
    public function importForm()
    {
        return view('users.import');
    }

    // 6. DOWNLOAD TEMPLATE EXCEL
    public function downloadTemplate()
    {
        return Excel::download(new UsersExport(true), 'Template_Import_Users.xlsx');
    }

    // 7. EXPORT DATA USER KE EXCEL
    public function export()
    {
        return Excel::download(new UsersExport(false), 'Data_Users_Export.xlsx');
    }

    // 8. PROSES BACA EXCEL & TAMPILKAN PREVIEW
    public function previewImport(Request $request)
    {
        $request->validate(['file_excel' => 'required|mimes:xlsx,xls,csv|max:2048']);

        try {
            $data = Excel::toArray(new UsersImport, $request->file('file_excel'));
            $rows = $data[0] ?? [];

            $previewData = [];
            $hasError    = false;
            $seenEmails  = [];

            // Peta ID => Nama untuk Perusahaan & Departemen
            $companiesMap = Company::pluck('name', 'id')->toArray();    // [6 => 'PT PANDU DEWANATA', ...]
            $deptsMap     = Department::pluck('name', 'id')->toArray(); // [4 => 'EDITORIAL', ...]

            // Peta role case-insensitive: ['staff' => 'Staff', ...]
            $validRoles = Role::pluck('name')->mapWithKeys(fn($r) => [strtolower(trim($r)) => $r])->toArray();

            // Peta jabatan aktif (mendukung ID angka maupun teks nama jabatan)
            $activeJobs = JobTitle::where('is_active', true)->get();
            $jobById    = $activeJobs->pluck('name', 'id')->toArray();
            $jobByName  = $activeJobs->mapWithKeys(fn($j) => [strtolower(trim($j->name)) => $j->name])->toArray();

            foreach ($rows as $row) {
                $name  = trim((string) ($row['name'] ?? ''));
                $email = strtolower(trim((string) ($row['email'] ?? '')));

                if ($name === '' && $email === '') {
                    continue;
                }

                $errors = [];

                if ($name === '') {
                    $errors[] = 'Nama Lengkap wajib diisi.';
                }

                if ($email === '') {
                    $errors[] = 'Email wajib diisi.';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Format Email tidak valid.';
                } elseif (in_array($email, $seenEmails)) {
                    $errors[] = 'Email duplikat di dalam file Excel ini.';
                } elseif (User::where('email', $email)->exists()) {
                    $errors[] = 'Email sudah terdaftar di sistem.';
                }

                if ($email !== '') {
                    $seenEmails[] = $email;
                }

                // Cek Perusahaan & siapkan label teksnya
                $rawCompId   = $row['company_id'] ?? null;
                $companyName = '-';
                if (!empty($rawCompId)) {
                    $compIdInt = (int) $rawCompId;
                    if (isset($companiesMap[$compIdInt])) {
                        $companyName = $companiesMap[$compIdInt];
                    } else {
                        $companyName = 'Tidak Ditemukan';
                        $errors[] = "ID Perusahaan '{$rawCompId}' tidak ditemukan.";
                    }
                }

                // Cek Departemen & siapkan label teksnya
                $rawDeptId = $row['department_id'] ?? null;
                $deptName  = '-';
                if (!empty($rawDeptId)) {
                    $deptIdInt = (int) $rawDeptId;
                    if (isset($deptsMap[$deptIdInt])) {
                        $deptName = $deptsMap[$deptIdInt];
                    } else {
                        $deptName = 'Tidak Ditemukan';
                        $errors[] = "ID Departemen '{$rawDeptId}' tidak ditemukan.";
                    }
                }

                // Validasi & konversi Jabatan (mendukung header job_title_id maupun job_title)
                $rawJob = trim((string) ($row['job_title_id'] ?? $row['job_title'] ?? ''));
                if ($rawJob === '') {
                    $resolvedJob = $jobByName['staff'] ?? 'Staff';
                } elseif (is_numeric($rawJob)) {
                    $jobId = (int) $rawJob;
                    if (isset($jobById[$jobId])) {
                        $resolvedJob = $jobById[$jobId];
                    } else {
                        $resolvedJob = $rawJob;
                        $errors[] = "ID Jabatan '{$rawJob}' tidak ditemukan di Master Jabatan.";
                    }
                } else {
                    if (isset($jobByName[strtolower($rawJob)])) {
                        $resolvedJob = $jobByName[strtolower($rawJob)];
                    } else {
                        $resolvedJob = $rawJob;
                        $errors[] = "Jabatan '{$rawJob}' tidak terdaftar di Master Jabatan.";
                    }
                }

                // Validasi nama Role
                $rawRole = trim((string) ($row['role'] ?? ''));
                if ($rawRole !== '' && !isset($validRoles[strtolower($rawRole)])) {
                    $errors[] = "Role '{$rawRole}' tidak ditemukan di Master Role.";
                }

                if (count($errors) > 0) {
                    $hasError = true;
                }

                $row['name']         = $name;
                $row['email']        = $email;
                $row['company_name'] = $companyName;
                $row['dept_name']    = $deptName;
                $row['job_title']    = $resolvedJob;
                $row['role']         = $rawRole !== '' ? ($validRoles[strtolower($rawRole)] ?? $rawRole) : ($validRoles['staff'] ?? 'Staff');
                $row['errors']       = $errors;
                $previewData[]       = $row;
            }

            if (empty($previewData)) {
                return back()->with('error', 'File Excel kosong atau tidak memiliki baris data user.');
            }

            session()->put('users_preview_data', $previewData);
            return view('users.preview', compact('previewData', 'hasError'));

        } catch (\Exception $e) {
            return back()->with('error', 'Gagal membaca file Excel: Pastikan format sesuai Template.');
        }
    }

    // 9. EKSEKUSI DATA
    public function processImport(Request $request)
    {
        $rows = session()->get('users_preview_data');
        if (!$rows) {
            return redirect()->route('users.import_form')->with('error', 'Sesi habis. Upload ulang file Anda.');
        }

        DB::beginTransaction();
        try {
            $countSuccess = 0;
            foreach ($rows as $row) {
                if (!empty($row['errors'])) {
                    continue;
                }

                $user = User::create([
                    'name'          => $row['name'],
                    'email'         => $row['email'],
                    'password'      => Hash::make(!empty($row['password']) ? $row['password'] : '123456'),
                    'company_id'    => !empty($row['company_id']) ? $row['company_id'] : null,
                    'department_id' => !empty($row['department_id']) ? $row['department_id'] : null,
                    'job_title'     => !empty($row['job_title']) ? $row['job_title'] : 'Staff',
                    'is_active'     => 1,
                ]);

                if (!empty($row['role']) && Role::where('name', $row['role'])->exists()) {
                    $user->assignRole($row['role']);
                }

                $countSuccess++;
            }

            DB::commit();
            session()->forget('users_preview_data');

            return redirect()->route('users.index')->with('success', "$countSuccess Data Karyawan (Valid) berhasil di-import!");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('users.import_form')->with('error', 'Gagal menyimpan ke database: ' . $e->getMessage());
        }
    }
}
