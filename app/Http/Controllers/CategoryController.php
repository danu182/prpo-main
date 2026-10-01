<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{

    public function index(Request $request)
    {
        $search = $request->input('search');

        // Menampilkan data dengan fitur pencarian dan paginasi (15 data per halaman)
        $categories = Category::with('parent')
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(15)
            ->withQueryString(); // Mempertahankan parameter pencarian saat pindah halaman

        return view('categories.index', compact('categories', 'search'));
    }

    public function create()
    {
        $parentCategories = Category::where('parent_id', null)->orderBy('name')->get();
        return view('categories.create', compact('parentCategories'));
    }

    public function store(Request $request)
    {
        // Validasi Anti-Duplikat untuk nama dan kode
        $request->validate([
            'name'      => 'required|string|max:255|unique:categories,name',
            'code'      => 'required|string|max:255|unique:categories,code',
            'parent_id' => 'nullable|exists:categories,id',
        ], [
            'name.unique' => 'Gagal: Nama Kategori ini sudah ada di database!',
            'code.unique' => 'Gagal: Kode Kategori ini sudah dipakai, gunakan kode lain!',
        ]);

        Category::create([
            'name'      => $request->name,
            'code'      => $request->code,
            'parent_id' => $request->parent_id,
            // 'is_active' => true, // Default aktif
        ]);

        return redirect()->route('categories.index')->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function edit(Category $category)
    {
        $parentCategories = Category::where('parent_id', null)->orderBy('name')->get();
        return view('categories.edit', compact('category', 'parentCategories'));
    }

    public function update(Request $request, Category $category)
    {
        // Validasi Anti-Duplikat (Mengecualikan ID yang sedang diedit)
        $request->validate([
            'name'      => 'required|string|max:255|unique:categories,name,' . $category->id,
            'code'      => 'required|string|max:255|unique:categories,code,' . $category->id,
            'parent_id' => 'nullable|exists:categories,id',
            // 'is_active' => 'required|boolean',
        ], [
            'name.unique' => 'Gagal: Nama Kategori ini sudah ada di database!',
            'code.unique' => 'Gagal: Kode Kategori ini sudah dipakai, gunakan kode lain!',
        ]);

        $category->update([
            'name'      => $request->name,
            'code'      => $request->code,
            'parent_id' => $request->parent_id,
            // 'is_active' => $request->is_active,
        ]);

        return redirect()->route('categories.index')->with('success', 'Kategori berhasil diperbarui.');
    }

    public function destroy(Category $category)
    {
        // HARD DELETE DILARANG, HANYA NONAKTIFKAN
        // $category->update(['is_active' => false]);
        // return redirect()->route('categories.index')->with('success', 'Kategori berhasil dinonaktifkan (Tidak dihapus).');
    }
}
