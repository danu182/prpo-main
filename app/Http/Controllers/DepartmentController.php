<?php
namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->search;

        $departments = Department::when($search, function($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%");
            })
            ->orderBy('name', 'asc')
            ->paginate(15);

        return view('departments.index', compact('departments'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:departments,code',
        ], [
            'code.unique' => 'Kode Departemen ini sudah digunakan.'
        ]);

        Department::create([
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'is_active' => $request->has('is_active') ? 1 : 0,
        ]);

        return redirect()->route('departments.index')->with('success', 'Departemen berhasil ditambahkan!');
    }


    // Fungsi untuk menampilkan halaman Show
    public function show(Department $department)
    {
        // Load relasi users agar tabel karyawan bisa tampil
        $department->load('users');
        return view('departments.show', compact('department'));
    }


    // Fungsi untuk menampilkan halaman Edit
    public function edit(Department $department)
    {
        return view('departments.edit', compact('department'));
    }

    public function update(Request $request, Department $department)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:departments,code,' . $department->id,
        ], [
            'code.unique' => 'Kode Departemen ini sudah digunakan.'
        ]);

        $department->update([
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'is_active' => $request->has('is_active') ? 1 : 0,
        ]);

        return redirect()->route('departments.index')->with('success', 'Departemen berhasil diperbarui!');
    }

    public function destroy(Department $department)
    {
        // 1. CEK RELASI: Apakah departemen ini sedang dipakai oleh User?
        if ($department->users()->exists()) {
            return redirect()->route('departments.index')->with('error', 'Gagal menghapus! Departemen ini sedang digunakan oleh ' . $department->users()->count() . ' User.');
        }

        // 2. Jika aman, lakukan penghapusan
        try {
            $department->delete();
            return redirect()->route('departments.index')->with('success', 'Departemen berhasil dihapus!');
        } catch (\Exception $e) {
            // Tangkap error jika ada relasi database lain yang tersembunyi
            return redirect()->route('departments.index')->with('error', 'Gagal menghapus! Departemen ini terikat dengan data lain di sistem.');
        }
    }
}
