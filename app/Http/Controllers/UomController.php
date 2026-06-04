<?php

namespace App\Http\Controllers;

use App\Models\Uom;
use Illuminate\Http\Request;

class UomController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->search;
        $uoms = Uom::when($search, function($query) use ($search) {
                $query->where('code', 'like', "%{$search}%")
                      ->orWhere('name', 'like', "%{$search}%");
            })
            ->orderBy('name', 'asc')
            ->paginate(10);

        return view('uoms.index', compact('uoms', 'search'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:50|unique:uoms,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string'
        ], [
            'code.unique' => 'Kode Satuan ini sudah terdaftar!'
        ]);

        Uom::create([
            'code' => strtoupper(trim($request->code)),
            'name' => trim($request->name),
            'description' => $request->description
        ]);

        return back()->with('success', 'Master Satuan (UOM) berhasil ditambahkan!');
    }

    public function update(Request $request, $id)
    {
        $uom = Uom::findOrFail($id);

        $request->validate([
            'code' => 'required|string|max:50|unique:uoms,code,' . $uom->id,
            'name' => 'required|string|max:255',
            'description' => 'nullable|string'
        ]);

        $uom->update([
            'code' => strtoupper(trim($request->code)),
            'name' => trim($request->name),
            'description' => $request->description
        ]);

        return back()->with('success', 'Data Satuan (UOM) berhasil diperbarui!');
    }

    public function destroy($id)
    {
        $uom = Uom::findOrFail($id);

        // Cek apakah UOM ini sedang dipakai di tabel items
        if($uom->items()->count() > 0) {
            return back()->with('error', 'Gagal menghapus! Satuan ini sedang digunakan oleh Master Barang.');
        }

        $uom->delete();
        return back()->with('success', 'Satuan (UOM) berhasil dihapus!');
    }
}
