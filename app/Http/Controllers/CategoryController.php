<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        // Menampilkan semua kategori termasuk yang non-aktif (agar bisa diaktifkan lagi)
        $categories = Category::with('parent')->latest()->get();
        return view('categories.index', compact('categories'));
    }

    public function create()
    {
        $parentCategories = Category::where('parent_id', null)->orderBy('name')->get();
        return view('categories.create', compact('parentCategories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:categories,code',
            'parent_id' => 'nullable|exists:categories,id',
        ]);

        Category::create([
            'name' => $request->name,
            'code' => $request->code,
            'parent_id' => $request->parent_id,
            // 'is_active' => true, // Default aktif
        ]);

        return redirect()->route('categories.index')->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function edit(Category $category)
    {
        // Jangan tampilkan dirinya sendiri di dropdown parent
        // $parentCategories = Category::where('id', '!=', $category->id)->where('is_active', true)->orderBy('name')->get();
        // $parentCategories = Category::where('id', '!=', $category->id)->orderBy('name')->get();
        $parentCategories = Category::where('parent_id', null)->orderBy('name')->get();
        return view('categories.edit', compact('category', 'parentCategories'));
    }

    public function update(Request $request, Category $category)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:categories,code,' . $category->id,
            'parent_id' => 'nullable|exists:categories,id',
            // 'is_active' => 'required|boolean',
        ]);

        $category->update([
            'name' => $request->name,
            'code' => $request->code,
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
