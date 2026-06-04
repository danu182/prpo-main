<?php

namespace App\Http\Controllers;

use App\Models\Company;
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

        $users = User::with(['company', 'roles'])
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('job_title', 'like', "%{$search}%");
            })
            ->orderBy('name', 'asc')
            ->paginate(10)
            ->withQueryString();

        $companies = Company::orderBy('name', 'asc')->get();
        // Ambil semua role kecuali Super Admin untuk form tambah user biasa
        $roles = Role::orderBy('name', 'asc')->get();

        return view('users.index', compact('users', 'search', 'companies', 'roles'));
    }

    // 2. SIMPAN PENGGUNA BARU
    public function store(Request $request)
    {
        $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => 'required|string|email|max:255|unique:users',
            'password'   => 'required|string|min:6|confirmed',
            'company_id' => 'nullable|exists:companies,id',
            'job_title'  => 'nullable|string|max:255',
            'roles'      => 'nullable|array' // Bisa pilih banyak departemen
        ]);

        try {
            $user = User::create([
                'name'       => $request->name,
                'email'      => $request->email,
                'password'   => Hash::make($request->password),
                'company_id' => $request->company_id,
                'job_title'  => $request->job_title,
                'is_active'  => 1, // Default langsung aktif
            ]);

            // Berikan Hak Akses (Role)
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
            'name'       => 'required|string|max:255',
            'email'      => 'required|string|email|max:255|unique:users,email,' . $id,
            'company_id' => 'nullable|exists:companies,id',
            'job_title'  => 'nullable|string|max:255',
            'is_active'  => 'required|boolean',
            'roles'      => 'nullable|array'
        ]);

        try {
            // Update data dasar
            $user->name       = $request->name;
            $user->email      = $request->email;
            $user->company_id = $request->company_id;
            $user->job_title  = $request->job_title;
            $user->is_active  = $request->is_active;

            // Jika password diisi, berarti mau ganti password
            if ($request->filled('password')) {
                $request->validate(['password' => 'min:6|confirmed']);
                $user->password = Hash::make($request->password);
            }

            $user->save();

            // FUNGSI SAKTI: Sinkronisasi Role
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

        // Proteksi agar tidak bisa menghapus diri sendiri
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
