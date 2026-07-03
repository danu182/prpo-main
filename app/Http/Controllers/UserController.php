<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Department; // <-- Import Model Department
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    // 1. TAMPILKAN DAFTAR PENGGUNA (DENGAN MODAL)
    public function index(Request $request)
    {
        $search = $request->input('search');

        // 🔥 Tambahkan 'department' di dalam with()
        $users = User::with(['company', 'department', 'roles'])
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('job_title', 'like', "%{$search}%");
            })
            ->orderBy('name', 'asc')
            ->paginate(10)
            ->withQueryString();

        $companies = Company::orderBy('name', 'asc')->get();
        $departments = Department::orderBy('name', 'asc')->get(); // 🔥 Panggil Data Departemen
        $roles = Role::orderBy('name', 'asc')->get();

        return view('users.index', compact('users', 'search', 'companies', 'departments', 'roles'));
    }

    // 2. SIMPAN PENGGUNA BARU
    public function store(Request $request)
    {
        $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|string|email|max:255|unique:users',
            'password'      => 'required|string|min:6|confirmed',
            'company_id'    => 'nullable|exists:companies,id',
            'department_id' => 'nullable|exists:departments,id', // 🔥 Validasi Departemen
            'job_title'     => 'nullable|string|max:255',
            'roles'         => 'nullable|array'
        ]);

        try {
            $user = User::create([
                'name'          => $request->name,
                'email'         => $request->email,
                'password'      => Hash::make($request->password),
                'company_id'    => $request->company_id,
                'department_id' => $request->department_id, // 🔥 Simpan Departemen
                'job_title'     => $request->job_title,
                'is_active'     => 1,
            ]);

            if ($request->has('roles')) {
                $user->syncRoles($request->roles);
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
            'department_id' => 'nullable|exists:departments,id', // 🔥 Validasi Departemen
            'job_title'     => 'nullable|string|max:255',
            'is_active'     => 'required|boolean',
            'roles'         => 'nullable|array'
        ]);

        try {
            $user->name          = $request->name;
            $user->email         = $request->email;
            $user->company_id    = $request->company_id;
            $user->department_id = $request->department_id; // 🔥 Update Departemen
            $user->job_title     = $request->job_title;
            $user->is_active     = $request->is_active;

            if ($request->filled('password')) {
                $request->validate(['password' => 'min:6|confirmed']);
                $user->password = Hash::make($request->password);
            }

            $user->save();
            $user->syncRoles($request->roles ?? []);

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
}
