<?php

namespace App\Http\Controllers;


use App\Models\ItemImportBatch;
use App\Models\ItemImportAttachment;
use App\Imports\ItemStagingImport;
use Illuminate\Support\Str;
use App\Http\Requests\Items\StoreItemRequest;
use App\Http\Requests\Items\UpdateItemRequest; // 🔥 TAMBAHKAN INI
use App\Models\Category;
use App\Models\Item;
use App\Models\ItemImportDetail;
use App\Models\ItemType;
use App\Models\ItemUom;
use App\Models\Uom;
use App\Services\ItemService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\{Storage};


class ItemController extends Controller
{
    // =========================================================================
    // 1. TAMPILKAN DAFTAR BARANG (INDEX)
    // =========================================================================
    public function index(Request $request)
    {
        $search = $request->input('search');

        // 🔥 Tambahkan 'itemType' ke dalam with() agar website tidak lemot! 🔥
        $items = Item::with(['uom', 'itemType'])->when($search, function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%");
            })
            ->orderBy('name', 'asc')
            ->paginate(10)
            ->withQueryString();

        return view('items.index', compact('items', 'search'));
    }

    // =========================================================================
    // 2. TAMPILKAN FORM TAMBAH BARANG (CREATE)
    // =========================================================================
    public function create()
    {
        $categories = \App\Models\Category::orderBy('code')->get();
        $uoms = \App\Models\Uom::orderBy('code')->get();
        // 🔥 Tambahkan ini untuk mengambil Tipe Barang Aktif 🔥
        $itemTypes = \App\Models\ItemType::where('is_active', true)->orderBy('code')->get();

        return view('items.create', compact('categories', 'uoms', 'itemTypes'));
    }


    // // =========================================================================
    // // 3. PROSES SIMPAN DATA (MENGGUNAKAN FORM REQUEST & SERVICE)
    // // =========================================================================
    // // 🔥 Ubah 'Request' menjadi 'StoreItemRequest' 🔥
    // public function store(Request $request)
    // {
    //     $request->validate([
    //         'name'           => 'required|string|max:255',
    //         'category_id'    => 'required|exists:categories,id',
    //         'uom_id'         => 'required|exists:uoms,id',
    //         'item_type_code' => 'required|exists:item_types,code',
    //         'min_stock'      => 'nullable|numeric|min:0',
    //         'max_stock'      => 'nullable|numeric|min:0',
    //         'specification'  => 'nullable|string',
    //         'uoms'           => 'nullable|array', // Validasi array kemasan alternatif
    //     ]);

    //     try {
    //         DB::transaction(function () use ($request) {
    //             // Generate Slug & Item Code
    //             $baseSlug = \Illuminate\Support\Str::slug($request->name);
    //             $slug = $baseSlug . '-' . \Illuminate\Support\Str::random(4);

    //             $prefix = 'SKU-' . $request->item_type_code . '-';
    //             $lastItem = \App\Models\Item::where('code', 'like', $prefix . '%')->orderBy('id', 'desc')->first();
    //             $nextNumber = $lastItem ? ((int) str_replace($prefix, '', $lastItem->code)) + 1 : 1;
    //             $itemCode = $prefix . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

    //             // 1. Simpan ke Tabel Utama (items)
    //             $item = \App\Models\Item::create([
    //                 'category_id'    => $request->category_id,
    //                 'code'           => $itemCode,
    //                 'slug'           => $slug,
    //                 'name'           => $request->name,
    //                 'current_stock'  => 0,
    //                 'min_stock'      => $request->min_stock ?? 0,
    //                 'max_stock'      => $request->max_stock ?? 0,
    //                 'is_trackable'   => $request->has('is_trackable') ? 1 : 0,
    //                 'is_active'      => 1,
    //                 'specification'  => $request->specification,
    //                 'uom_id'         => $request->uom_id,
    //                 'item_type_code' => $request->item_type_code,
    //             ]);

    //             // 2. Simpan ke Tabel Anak (item_uoms) JIKA ADA INPUTAN
    //             if ($request->has('uoms')) {
    //                 foreach ($request->uoms as $uomData) {
    //                     // Pastikan nama kemasan dan konversi terisi (mencegah baris kosong tersimpan)
    //                     if (!empty($uomData['uom_name']) && !empty($uomData['conversion_qty'])) {
    //                         \Illuminate\Support\Facades\DB::table('item_uoms')->insert([
    //                             'item_id'         => $item->id,
    //                             'uom_name'        => $uomData['uom_name'],
    //                             'conversion_qty'  => $uomData['conversion_qty'],
    //                             'barcode'         => $uomData['barcode'] ?? null,
    //                             'created_at'      => now(),
    //                             'updated_at'      => now(),
    //                         ]);
    //                     }
    //                 }
    //             }
    //         });

    //         return redirect()->route('items.index')->with('success', 'Master Barang & Kemasan berhasil ditambahkan!');

    //     } catch (\Exception $e) {
    //         return back()->withInput()->with('error', 'Gagal menyimpan barang: ' . $e->getMessage());
    //     }
    // }



    // =========================================================================
    // 3. PROSES SIMPAN DATA (MENGGUNAKAN FORM REQUEST & SERVICE)
    // =========================================================================
    public function store(Request $request)
    {
        $request->validate([
            'name'           => 'required|string|max:255',
            'category_id'    => 'required|exists:categories,id',
            'uom_id'         => 'required|exists:uoms,id',
            'item_type_code' => 'required|exists:item_types,code',
            'min_stock'      => 'nullable|numeric|min:0',
            'max_stock'      => 'nullable|numeric|min:0',
            'specification'  => 'nullable|string',
            'uoms'           => 'nullable|array',
        ]);

        try {
            DB::transaction(function () use ($request) {
                // Generate Slug
                $baseSlug = \Illuminate\Support\Str::slug($request->name);
                $slug = $baseSlug . '-' . \Illuminate\Support\Str::random(4);

                // 🔥 1. TARIK DATA KATEGORI UNTUK MENDAPATKAN KODENYA (Misal: CNS) 🔥
                $category = \App\Models\Category::find($request->category_id);
                $categoryCode = $category && $category->code ? strtoupper($category->code) : 'UMM'; // UMM = Umum sbg fallback

                // 🔥 2. BUAT PREFIX BERDASARKAN KODE KATEGORI 🔥
                $prefix = 'SKU-' . $categoryCode . '-';

                // Tambahkan lockForUpdate() agar aman jika 2 admin klik simpan di detik yang sama
                $lastItem = \App\Models\Item::where('code', 'like', $prefix . '%')
                                ->orderBy('id', 'desc')
                                ->lockForUpdate()
                                ->first();

                $nextNumber = $lastItem ? ((int) str_replace($prefix, '', $lastItem->code)) + 1 : 1;
                $itemCode = $prefix . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

                // 3. Simpan ke Tabel Utama (items)
                $item = \App\Models\Item::create([
                    'category_id'    => $request->category_id,
                    'code'           => $itemCode, // Akan menjadi SKU-CNS-00001
                    'slug'           => $slug,
                    'name'           => $request->name,
                    'current_stock'  => 0,
                    'min_stock'      => $request->min_stock ?? 0,
                    'max_stock'      => $request->max_stock ?? 0,
                    'is_trackable'   => $request->has('is_trackable') ? 1 : 0,
                    'is_active'      => 1,
                    'specification'  => $request->specification,
                    'uom_id'         => $request->uom_id,
                    'item_type_code' => $request->item_type_code,
                ]);

                // 4. Simpan ke Tabel Anak (item_uoms) JIKA ADA INPUTAN
                if ($request->has('uoms')) {
                    foreach ($request->uoms as $uomData) {
                        if (!empty($uomData['uom_name']) && !empty($uomData['conversion_qty'])) {
                            \Illuminate\Support\Facades\DB::table('item_uoms')->insert([
                                'item_id'         => $item->id,
                                'uom_name'        => $uomData['uom_name'],
                                'conversion_qty'  => $uomData['conversion_qty'],
                                'barcode'         => $uomData['barcode'] ?? null,
                                'created_at'      => now(),
                                'updated_at'      => now(),
                            ]);
                        }
                    }
                }
            });

            return redirect()->route('items.index')->with('success', 'Master Barang & Kemasan berhasil ditambahkan!');

        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Gagal menyimpan barang: ' . $e->getMessage());
        }
    }


    public function edit(Item $item)
    {
        // ❌ $item = Item::findOrFail($id); --> INI SUDAH TIDAK PERLU! Ajaib kan?
        $item->load('uoms','itemType'); // Load relasi kemasannya

        $categories = \App\Models\Category::orderBy('name', 'asc')->get();
        $uoms = \App\Models\Uom::orderBy('name', 'asc')->get();
        $itemTypes = ItemType::where('is_active', true)->orderBy('code')->get();

        // 🔥 Cek apakah sudah ada transaksi 🔥
        $hasTransactions = $item->hasTransactions();

        return view('items.edit', compact('item', 'categories', 'uoms', 'itemTypes', 'hasTransactions'));
    }



    // =========================================================================
    // 5. PROSES UPDATE DATA (DENGAN FORM REQUEST & TANPA FIND OR FAIL)
    // =========================================================================
    public function update(Request $request, $slug)
    {
        $item = \App\Models\Item::where('slug', $slug)->firstOrFail();

        $request->validate([
            'name'           => 'required|string|max:255',
            'category_id'    => 'required|exists:categories,id',
            'uom_id'         => 'required|exists:uoms,id',
            'item_type_code' => 'required|exists:item_types,code',
            'min_stock'      => 'nullable|numeric|min:0',
            'max_stock'      => 'nullable|numeric|min:0',
            'specification'  => 'nullable|string',
            'uoms'           => 'nullable|array',
        ]);

        try {
            DB::transaction(function () use ($request, $item) {
                // 1. Update slug jika nama berubah
                if ($item->name !== $request->name) {
                    $item->slug = \Illuminate\Support\Str::slug($request->name) . '-' . \Illuminate\Support\Str::random(4);
                }

                // 2. Eksekusi Update Tabel Utama (items)
                $item->update([
                    'category_id'    => $request->category_id,
                    'name'           => $request->name,
                    'slug'           => $item->slug,
                    'min_stock'      => $request->min_stock ?? 0,
                    'max_stock'      => $request->max_stock ?? 0,
                    'is_trackable'   => $request->has('is_trackable') ? 1 : 0,
                    'is_active'      => $request->has('is_active') ? 1 : 0,
                    'specification'  => $request->specification,
                    'uom_id'         => $request->uom_id,
                    'item_type_code' => $request->item_type_code,
                ]);

                // 3. Sinkronisasi Tabel Kemasan (item_uoms)
                // Hapus kemasan lama terlebih dahulu (Reset)
                \Illuminate\Support\Facades\DB::table('item_uoms')->where('item_id', $item->id)->delete();

                // Insert kemasan yang baru dari form edit
                if ($request->has('uoms')) {
                    foreach ($request->uoms as $uomData) {
                        if (!empty($uomData['uom_name']) && !empty($uomData['conversion_qty'])) {
                            \Illuminate\Support\Facades\DB::table('item_uoms')->insert([
                                'item_id'         => $item->id,
                                'uom_name'        => $uomData['uom_name'],
                                'conversion_qty'  => $uomData['conversion_qty'],
                                'barcode'         => $uomData['barcode'] ?? null,
                                'created_at'      => now(),
                                'updated_at'      => now(),
                            ]);
                        }
                    }
                }
            });

            return redirect()->route('items.index')->with('success', 'Data Master Barang & Kemasan berhasil diperbarui!');

        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Gagal memperbarui barang: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // 6. DETAIL BARANG (SHOW)
    // =========================================================================
    public function show(Item $item)
    {
        $item = Item::findOrFail($item);

        $holders = \App\Models\EmployeeInventory::where('item_id', $item)
                        ->where('qty', '>', 0)
                        ->orderBy('employee_name', 'asc')
                        ->get()
                        ->map(function ($holder) {
                            $user = \App\Models\User::with('company')->where('name', $holder->employee_name)->first();
                            $holder->company_name = optional(optional($user)->company)->name ?? 'Kantor Pusat / Umum';
                            return $holder;
                        });

        $categories = \App\Models\Category::orderBy('name', 'asc')->get();

        // 🔥 AMBIL RINCIAN STOK PER GUDANG 🔥
        $stockPerWarehouse = [];
        if ($item->is_stockable) {
            $stockPerWarehouse = \App\Models\InventoryStock::with('warehouse')
                ->selectRaw('warehouse_id, sum(stock_qty) as total_qty')
                ->where('item_id', $item)
                ->groupBy('warehouse_id')
                ->havingRaw('total_qty > 0') // Hanya tampilkan gudang yang ada isinya
                ->get();
        }

        return view('items.show', compact('item', 'holders', 'categories', 'stockPerWarehouse'));
    }

    // =========================================================================
    // 7. HAPUS BARANG (DESTROY)
    // =========================================================================
    public function destroy($id)
    {
        try {
            $item = Item::findOrFail($id);
            $item->delete(); // Ini otomatis akan menghapus relasi ItemUom karena di migration kita pakai cascadeOnDelete
            return back()->with('success', 'Barang berhasil dihapus dari Katalog!');
        } catch (\Exception $e) {
            return back()->with('error', 'Barang tidak bisa dihapus karena sudah memiliki riwayat transaksi (PO/Penerimaan).');
        }
    }



    public function import()
    {
        return view('items.import');
    }

    // =========================================================================
    // 8. DOWNLOAD TEMPLATE EXCEL
    // =========================================================================

    public function downloadTemplate()
    {
        // Pastikan Komandan memanggil Facade Excel dan Class Export-nya
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\ItemTemplateExport,
            'Template_Import_Master_Item.xlsx'
        );
    }

    public function importIndex()
    {
        $batches = ItemImportBatch::with(['creator', 'statusInfo'])
                    ->withCount('details')
                    ->orderBy('created_at', 'desc')
                    ->paginate(10);
        return view('items.import_index', compact('batches'));
    }

    // public function import() { return view('items.import'); }

    public function previewImport(Request $request)
    {
        $request->validate([
            'import_file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'attachments' => 'required|array|min:1',
            'attachments.*' => 'file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        try {
            DB::beginTransaction();

            // 1. GENERATE NOMOR BATCH (RESET PER HARI)
            $today = now()->format('Ymd');
            $prefix = "IMP-{$today}-";
            $lastBatch = ItemImportBatch::where('batch_number', 'like', "{$prefix}%")->orderBy('id', 'desc')->first();
            $nextSeq = $lastBatch ? ((int) substr($lastBatch->batch_number, -3)) + 1 : 1;
            $batchNumber = $prefix . str_pad($nextSeq, 3, '0', STR_PAD_LEFT);

            // 2. SIMPAN EXCEL ASLI (FOLDER AUDIT)
            $excelFile = $request->file('import_file');
            $excelPath = $excelFile->storeAs("master_item_import/{$batchNumber}/data_upload", $excelFile->getClientOriginalName(), 'public');

            $batch = ItemImportBatch::create([
                'batch_number' => $batchNumber,
                'created_by' => Auth::id(),
                'status' => 'draft',
            ]);

            // 3. SIMPAN LAMPIRAN
            foreach ($request->file('attachments') as $file) {
                $path = $file->storeAs("master_item_import/{$batchNumber}/file_pendukung", $file->getClientOriginalName(), 'public');
                ItemImportAttachment::create([
                    'item_import_batch_id' => $batch->id,
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                ]);
            }

            // 4. PARSE EXCEL (HANYA SHEET 1)
            Excel::import(new ItemStagingImport($batch->id), storage_path("app/public/{$excelPath}"));

            DB::commit();
            return redirect()->route('items.import_staging', $batch->id)->with('success', 'Berhasil unggah ke Karantina.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['Gagal: ' . $e->getMessage()]);
        }
    }

    public function importStaging($batch_id)
    {
        $batch = ItemImportBatch::with(['details', 'attachments', 'statusInfo'])->findOrFail($batch_id);
        $categories = Category::orderBy('code')->get();
        $uoms = Uom::orderBy('code')->get();
        // 🔥 Ambil Tipe Barang untuk dropdown Modal
        $itemTypes = \App\Models\ItemType::where('is_active', true)->orderBy('code')->get();

        return view('items.import_staging', compact('batch', 'categories', 'uoms', 'itemTypes'));
    }

    public function updateStagingDetail(Request $request, $id)
    {
        $detail = ItemImportDetail::findOrFail($id);
        $request->validate([
            'name' => 'required|string', 'category_code' => 'required', 'uom_code' => 'required',
            'item_type_code' => 'required', // 🔥 Ganti is_stockable
            'is_asset' => 'required|boolean', 'is_trackable' => 'required|boolean'
        ]);

        $detail->update([
            'name' => strtoupper($request->name), 'category_code' => $request->category_code,
            'uom_code' => $request->uom_code, 'specification' => $request->specification,
            'item_type_code' => $request->item_type_code, // 🔥 Ganti is_stockable
            'is_asset' => $request->is_asset, 'is_trackable' => $request->is_trackable,
        ]);

        // RE-VALIDASI BISNIS
        $errors = [];
        if (!\App\Models\Category::where('code', $detail->category_code)->exists()) $errors[] = "Kategori tidak ditemukan";
        if (!\App\Models\Uom::where('code', $detail->uom_code)->exists()) $errors[] = "Satuan tidak ditemukan";
        if (!\App\Models\ItemType::where('code', $detail->item_type_code)->where('is_active', true)->exists()) $errors[] = "Tipe Barang tidak valid/tidak aktif";

        // Logika Bisnis Aset & Jasa
        if ($detail->is_asset == 1 && $detail->item_type_code === 'JSA') $errors[] = "Jasa tidak bisa dijadikan Aset";
        if ($detail->item_type_code === 'JSA' && $detail->is_trackable == 1) $errors[] = "Jasa tidak bisa dilacak fisik";
        if ($detail->is_asset == 1 && $detail->is_trackable == 0) $errors[] = "Aset WAJIB Dilacak";

        $detail->update([
            'is_valid' => empty($errors),
            'validation_error' => empty($errors) ? null : implode(', ', $errors)
        ]);

        return back()->with('success', 'Data diperbarui.');
    }

    public function cancelImport($batch_id)
    {
        $batch = ItemImportBatch::findOrFail($batch_id);
        Storage::disk('public')->deleteDirectory("master_item_import/{$batch->batch_number}");
        $batch->delete();
        return redirect()->route('items.import_index')->with('success', 'Draft dihapus bersih.');
    }



    // =========================================================================
    // 12. FUNGSI AKTIF / NONAKTIFKAN BARANG (PENGGANTI DELETE)
    // =========================================================================
    public function toggleStatus(Item $item)
    {
        try {
            // ❌ $item = Item::findOrFail($id); --> HAPUS!

            $item->is_active = !$item->is_active;
            $item->save();

            $statusText = $item->is_active ? 'Diaktifkan' : 'Dinonaktifkan';
            return back()->with('success', "Master Barang berhasil {$statusText}!");
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal merubah status barang: ' . $e->getMessage());
        }
    }






}
