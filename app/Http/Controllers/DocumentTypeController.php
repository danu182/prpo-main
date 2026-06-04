<?php

namespace App\Http\Controllers;

use App\Models\DocumentType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DocumentTypeController extends Controller
{
    public function index()
    {
        $types = DocumentType::orderBy('name')->get();
        return view('document_types.index', compact('types'));
    }

    public function create()
    {
        return view('document_types.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'model_class' => 'required|string|unique:document_types,model_class',
            'is_active' => 'boolean'
        ]);

        DocumentType::create($validated);
        return redirect()->route('document-types.index')->with('success', 'Jenis Dokumen berhasil didaftarkan!');
    }

    // Cukup panggil modelnya, Laravel otomatis mencari ID-nya!
    public function edit(DocumentType $documentType)
    {
        return view('document_types.edit', compact('documentType'));
    }

    public function update(Request $request, $id)
    {
        // 1. Cari data aslinya dulu (Lebih aman pakai ID manual untuk hindari error rute)
        $documentType = \App\Models\DocumentType::findOrFail($id);

        // 2. Validasi inputan dari form
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            // Pastikan validasi unique mengecualikan ID yang sedang diedit
            'model_class' => 'required|string|unique:document_types,model_class,' . $documentType->id,
            'is_active'   => 'required|boolean'
        ]);

        try {
            // 3. Eksekusi Update ke Database
            $documentType->update($validated);

            return redirect()->route('document-types.index')->with('success', 'Master Jenis Dokumen berhasil diperbarui!');

        } catch (\Exception $e) {
            // 4. Jika gagal, kembalikan ke form edit bawa inputan sebelumnya
            return back()->withInput()->with('error', 'Gagal memperbarui dokumen: ' . $e->getMessage());
        }
    }

    public function destroy(DocumentType $documentType)
    {
        // Opsional: Beri proteksi agar PR & PO tidak bisa dihapus karena krusial
        if (in_array($documentType->model_class, ['App\Models\PurchaseRequest', 'App\Models\PurchaseOrder'])) {
            return back()->with('error', 'Dokumen sistem inti tidak boleh dihapus!');
        }

        $documentType->delete();
        return redirect()->route('document-types.index')->with('success', 'Jenis Dokumen dihapus.');
    }
}
