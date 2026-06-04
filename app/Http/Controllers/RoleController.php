<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    // 1. Tampilkan Daftar Role & Permission
    public function index(Request $request)
    {
        $search = $request->input('search');

        $roles = Role::with('permissions')
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('name', 'asc')
            ->paginate(10);

        // Ambil semua daftar izin (Permission) yang ada di sistem
        $permissions = Permission::orderBy('name', 'asc')->get();

        return view('roles.index', compact('roles', 'search', 'permissions'));
    }

    // 2. Simpan Role Baru
    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|unique:roles,name|max:255',
            'permissions' => 'nullable|array'
        ]);

        try {
            $role = Role::create(['name' => $request->name]);

            if ($request->has('permissions')) {
                $role->syncPermissions($request->permissions);
            }

            return back()->with('success', 'Grup Hak Akses (Role) baru berhasil dibuat!');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal membuat role: ' . $e->getMessage());
        }
    }

    // 3. Update Role & Izinnya
    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        if ($role->name === 'Super Admin') {
            return back()->with('error', 'Akses Ditolak: Hak Akses Super Admin tidak boleh diubah!');
        }

        $request->validate([
            'name'        => 'required|string|max:255|unique:roles,name,' . $id,
            'permissions' => 'nullable|array'
        ]);

        try {
            $role->update(['name' => $request->name]);
            $role->syncPermissions($request->permissions ?? []);

            return back()->with('success', 'Hak Akses (Role) berhasil diperbarui!');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal memperbarui role: ' . $e->getMessage());
        }
    }

    // 4. Hapus Role
    public function destroy($id)
    {
        $role = Role::findOrFail($id);

        if ($role->name === 'Super Admin') {
            return back()->with('error', 'Akses Ditolak: Role Super Admin tidak bisa dihapus!');
        }

        try {
            $role->delete();
            return back()->with('success', 'Role berhasil dihapus!');
        } catch (\Exception $e) {
            return back()->with('error', 'Role tidak bisa dihapus karena masih digunakan.');
        }
    }
}
