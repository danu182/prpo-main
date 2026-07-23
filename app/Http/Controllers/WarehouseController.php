<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WarehouseController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        $warehouses = Warehouse::when($search, function ($query) use ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->orderBy('name', 'asc')
            ->paginate(10)
            ->withQueryString();

        return view('warehouses.index', compact('warehouses', 'search'));
    }

    public function create()
    {
        return view('warehouses.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            // 🔥 OTOMATISASI KODE GUDANG (Format: GDG-001, GDG-002, dst)
            $lastWarehouse = Warehouse::orderBy('id', 'desc')->first();
            $nextNumber = $lastWarehouse ? ((int) filter_var($lastWarehouse->code, FILTER_SANITIZE_NUMBER_INT)) + 1 : 1;
            $autoCode = 'GDG-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

            $validated['code'] = $autoCode;
            $validated['is_active'] = true;

            Warehouse::create($validated);

            DB::commit();
            return redirect()->route('warehouses.index')->with('success', "Gudang {$validated['name']} ({$autoCode}) berhasil ditambahkan!");
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    public function show(Warehouse $warehouse)
    {
        return view('warehouses.show', compact('warehouse'));
    }

    public function edit(Warehouse $warehouse)
    {
        return view('warehouses.edit', compact('warehouse'));
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        // Kode gudang tidak diubah lagi saat update agar konsisten
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();
            $warehouse->update($validated);
            DB::commit();

            return redirect()->route('warehouses.index')->with('success', 'Data Gudang berhasil diperbarui!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Gagal update data: ' . $e->getMessage());
        }
    }

    public function toggleStatus(Warehouse $warehouse)
    {
        $warehouse->update(['is_active' => !$warehouse->is_active]);
        $status = $warehouse->is_active ? 'diaktifkan' : 'dinonaktifkan';
        return back()->with('success', "Gudang {$warehouse->name} berhasil {$status}.");
    }
}
