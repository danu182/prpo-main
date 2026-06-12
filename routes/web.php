<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseRequestController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\GoodsReceiptController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\BillPaymentController;
use App\Http\Controllers\BillRequestController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\FixedAssetController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\ReturnToVendorController;
use App\Http\Controllers\GoodsIssueController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\VendorInvoiceController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\FinanceReportController;
use App\Http\Controllers\GoodsIssueReturnController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\VendorController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Halaman Depan (Redirect ke Login)
Route::get('/', function () {
    return redirect()->route('login');
});

// =========================================================================
// GEMBOK UTAMA: Hanya bisa diakses user yang sudah Login
// =========================================================================
Route::middleware('auth')->group(function () {

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Rute untuk menandai notifikasi telah dibaca dan mengarahkan ke halaman tujuan
    Route::get('/notifikasi/{id}/baca', [\App\Http\Controllers\DashboardController::class, 'markNotificationAsRead'])->name('notif.read');

    // ====================================================
    // 1. MODUL PURCHASE REQUEST (PR) -> Izin: view_pr
    // ====================================================
    Route::prefix('pr')->name('pr.')->middleware(['can:view_pr'])->group(function () {
        Route::get('/', [PurchaseRequestController::class, 'index'])->name('index');
        Route::get('/create', [PurchaseRequestController::class, 'create'])->name('create');
        Route::post('/', [PurchaseRequestController::class, 'store'])->name('store');
        Route::get('/{slug}/edit', [PurchaseRequestController::class, 'edit'])->name('edit');
        Route::put('/{slug}', [PurchaseRequestController::class, 'update'])->name('update');
        Route::get('/{id}', [PurchaseRequestController::class, 'show'])->name('show');
        Route::post('/{id}/decide', [PurchaseRequestController::class, 'decide'])->name('decide');
        Route::delete('/{id}', [PurchaseRequestController::class, 'destroy'])->name('destroy');
        // Route::get('/{id}/print', [PurchaseRequestController::class, 'print'])->name('print');
        Route::get('/{slug}/print', [PurchaseRequestController::class, 'print'])->name('print');
        Route::post('/{slug}/cancel', [PurchaseRequestController::class, 'cancel'])->name('cancel');
        Route::post('/{id}/reject-all', [PurchaseRequestController::class, 'rejectAll'])->name('rejectAll');
        Route::post('/item/{id}/force-close', [PurchaseRequestController::class, 'forceCloseItem'])->name('item.forceClose');


        // Rute untuk AJAX Search (Harus di atas rute pr/{id} jika ada)
        Route::get('/pr/search-items', [App\Http\Controllers\PurchaseRequestController::class, 'searchItems'])->name('pr.search-items');


    });

    // ====================================================
    // 2. MODUL PURCHASE ORDER (PO) -> Izin: view_po / create_po
    // ====================================================
    Route::prefix('po')->name('po.')->group(function () {
        Route::get('/', [PurchaseOrderController::class, 'index'])->name('index')->middleware('can:view_po');

        // Memproses dari PR (Tetap pakai ID/Slug PR sebagai acuan awal)
        Route::get('/process-pr/{slug}', [PurchaseOrderController::class, 'processPr'])->name('process_pr')->middleware('can:view_po');
        Route::post('/store-from-pr/{slug}', [PurchaseOrderController::class, 'storeFromPr'])->name('store_from_pr')->middleware('can:view_po');

        // Rute PO menggunakan Slug (Nomor PO)
        Route::get('/show/{slug}', [PurchaseOrderController::class, 'show'])->name('show')->middleware('can:view_po');
        Route::get('/edit/{slug}', [PurchaseOrderController::class, 'edit'])->name('edit')->middleware('can:view_po');
        Route::post('/update/{slug}', [PurchaseOrderController::class, 'update'])->name('update')->middleware('can:view_po');

        // Approval Khusus
        // Ganti route approve & reject menjadi:
        Route::post('/decide/{slug}', [PurchaseOrderController::class, 'decide'])->name('decide');

        // Rute Lampiran tetap pakai ID karena ini per file
        // Route::delete('/delete-attachment/{id}', [PurchaseOrderController::class, 'deleteAttachment'])->name('attachment.destroy');
        Route::get('/delete-attachment/{id}', [PurchaseOrderController::class, 'deleteAttachment'])->name('delete_attachment');
        Route::post('/submit-approval/{slug}', [PurchaseOrderController::class, 'submitApproval'])->name('submit_approval');

        // Cancel PO pakai Slug
        Route::post('/{slug}/cancel', [PurchaseOrderController::class, 'cancel'])->name('cancel')->middleware('can:view_po');

        // Route untuk Hapus Lampiran Barang
        Route::get('/po/attachment/item/{id}/delete', [\App\Http\Controllers\PurchaseOrderController::class, 'deleteItemAttachment'])->name('po.delete_item_attachment');

        // Route untuk Hapus Lampiran Header PO
        Route::get('/po/attachment/header/{id}/delete', [\App\Http\Controllers\PurchaseOrderController::class, 'deleteHeaderAttachment'])->name('po.delete_header_attachment');


    });


    // ====================================================
    // 3. MODUL PENERIMAAN BARANG (GR) -> Izin: view_gr
    // ====================================================
    Route::prefix('receive')->name('receive.')->middleware(['can:view_gr'])->group(function () {
        Route::get('/po/{id}', [GoodsReceiptController::class, 'create'])->name('gr.create');
        Route::post('/po/{id}', [GoodsReceiptController::class, 'store'])->name('gr.store');
        Route::get('/{id}/show', [GoodsReceiptController::class, 'show'])->name('gr.show');
        Route::get('/{id}/print', [GoodsReceiptController::class, 'print'])->name('gr.print');
    });

    // ====================================================
    // 3. MODUL PENERIMAAN BARANG (GR) -> Izin: view_gr
    // ====================================================
    Route::prefix('gr')->name('gr.')->middleware(['can:view_gr'])->group(function () {

        Route::get('/', [GoodsReceiptController::class, 'index'])->name('index');

        // Form Create GR & Proses Simpan (Berdasarkan Nomor PO / Slug)
        Route::get('/create/{slug}', [GoodsReceiptController::class, 'create'])->name('create');
        Route::post('/store/{slug}', [GoodsReceiptController::class, 'store'])->name('store');

        // Detail, Cetak GR & Label (Berdasarkan Nomor GR / Slug)
        Route::get('/{slug}/show', [GoodsReceiptController::class, 'show'])->name('show');
        // Route::get('/{slug}/print', [GoodsReceiptController::class, 'print'])->name('print');
        Route::get('/{slug}/print', [GoodsReceiptController::class, 'print'])->name('print')->where('slug', '.*');
        Route::get('/{slug}/print-labels', [GoodsReceiptController::class, 'printLabels'])->name('print_labels');

    });


    // ====================================================
    // 4. MODUL WAREHOUSE & INVENTORY
    // ====================================================
    // Aset Tetap
    Route::prefix('fixed-assets')->name('fixed-assets.')->middleware(['can:view_assets'])->group(function () {

        // 🔥 TAMBAHKAN BARIS INI (Jalur AJAX Pencarian Barang) 🔥
        Route::get('/search-items', [App\Http\Controllers\FixedAssetController::class, 'searchItems'])->name('search-items');

        Route::get('/', [FixedAssetController::class, 'index'])->name('index');
        Route::post('/', [FixedAssetController::class, 'store'])->name('store');
        Route::put('/{id}', [FixedAssetController::class, 'update'])->name('update');
        Route::get('/{id}/bast', [FixedAssetController::class, 'printBast'])->name('bast');
        Route::get('/{id}/bapa', [FixedAssetController::class, 'printBapa'])->name('bapa');
        Route::get('/{id}/bapp', [FixedAssetController::class, 'printBapp'])->name('bapp');

        // // 🔥 TAMBAHKAN 2 BARIS INI UNTUK IMPORT EXCEL 🔥
        // Route::post('/import/preview', [FixedAssetController::class, 'previewImport'])->name('preview_import');
        // Route::post('/import', [FixedAssetController::class, 'import'])->name('import');
        // Route::get('/template/download', [FixedAssetController::class, 'downloadTemplate'])->name('download_template');


        // 🔥 RUTE IMPORT DENGAN PREVIEW (PASTIKAN ADA 3 BARIS INI) 🔥
        Route::post('/import/preview', [FixedAssetController::class, 'previewImport'])->name('preview_import');
        Route::post('/import/process', [FixedAssetController::class, 'processImport'])->name('process_import'); // <-- INI YANG TADI HILANG
        Route::get('/template/download', [FixedAssetController::class, 'downloadTemplate'])->name('download_template');


        // Route untuk halaman Riwayat Import
        Route::get('/import-history', [FixedAssetController::class, 'importHistory'])->name('import_history');

        // Route untuk Cetak PDF BAST per Batch
        Route::get('/import-history/{batch_id}/print-bast', [FixedAssetController::class, 'printBastByBatch'])->name('print_bast_batch');

        // Route untuk halaman Detail Batch Import
        Route::get('/import-history/{batch_id}', [FixedAssetController::class, 'showImportBatch'])->name('show_import_batch');

        // Route untuk Cetak 1 Label QR
        Route::get('/{id}/print-qr', [FixedAssetController::class, 'printQrLabel'])->name('print_qr');

        // Route untuk Cetak Semua Label QR dalam 1 Batch
        Route::get('/import-history/{batch_id}/print-qr', [FixedAssetController::class, 'printMassQrLabel'])->name('print_mass_qr');


        // Jalur Riwayat Hibah
        Route::get('/hibah-history', [App\Http\Controllers\FixedAssetController::class, 'hibahHistory'])->name('hibah_history');


    });

    Route::prefix('assets')->name('assets.')->middleware(['can:view_assets'])->group(function () {
        Route::get('/', [AssetController::class, 'index'])->name('index');
        Route::get('/pending', [AssetController::class, 'indexPending'])->name('pending');
        Route::post('/{id}/register', [AssetController::class, 'registerAsset'])->name('register');
        Route::post('/{id}/mutate', [AssetController::class, 'mutate'])->name('mutate');
        Route::get('/import', [AssetController::class, 'showImportForm'])->name('import.form');
        Route::get('/download-template', [AssetController::class, 'downloadTemplate'])->name('download_template');
        Route::post('/import', [AssetController::class, 'import'])->name('import');
    });

    // =========================================================================
    // Modul Penyesuaian Stok (Stock Opname)
    // =========================================================================
    Route::prefix('stock-adjustments')->name('stock-adjustments.')->middleware(['can:manage_gi'])->group(function () {

        // 🔥 PERBAIKAN: Gunakan '/' untuk Index agar bisa diakses langsung
        Route::get('/', [StockAdjustmentController::class, 'index'])->name('index');

        // AJAX: Ambil Stok Sistem (WAJIB di atas route dengan parameter ID)
        Route::get('/get-stock', [StockAdjustmentController::class, 'getWarehouseStock'])->name('get-stock');

        // Halaman Form Create
        Route::get('/create', [StockAdjustmentController::class, 'create'])->name('create');

        // Proses Simpan (POST ke root prefix)
        Route::post('/', [StockAdjustmentController::class, 'store'])->name('store');

        // 🔥 MISI TAMBAHAN: Jalur untuk halaman Rincian (Show)
        Route::get('/{id}', [StockAdjustmentController::class, 'show'])->name('show');

    });


   // Modul Pengeluaran Barang (Goods Issue)
    Route::prefix('goods-issues')->name('goods-issues.')->group(function () {
        Route::get('/', [App\Http\Controllers\GoodsIssueController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\GoodsIssueController::class, 'create'])->name('create');
        Route::post('/', [App\Http\Controllers\GoodsIssueController::class, 'store'])->name('store');

        // 🔥 Rute API Pencarian (HARUS DI ATAS RUTE SLUG AGAR TIDAK BENTROK) 🔥
        Route::get('/api/search-items', [App\Http\Controllers\GoodsIssueController::class, 'searchItems'])->name('search-items');
        Route::get('/api/search-assets', [App\Http\Controllers\GoodsIssueController::class, 'searchFixedAssets'])->name('search-assets');
        Route::get('/api/search-batches', [App\Http\Controllers\GoodsIssueController::class, 'searchBatches'])->name('search-batches');
        Route::get('/api/search-sns', [App\Http\Controllers\GoodsIssueController::class, 'searchSns'])->name('search-sns'); // <--- INI SUDAH BENAR

        // 🔥 SEMUA RUTE SPESIFIK MENGGUNAKAN SLUG SEKARANG 🔥
        Route::get('/{slug}', [App\Http\Controllers\GoodsIssueController::class, 'show'])->name('show');
        Route::get('/{slug}/print', [App\Http\Controllers\GoodsIssueController::class, 'print'])->name('print');
        Route::get('/{slug}/print-labels', [App\Http\Controllers\GoodsIssueController::class, 'printLabels'])->name('print_labels');
        Route::post('/{slug}/void', [App\Http\Controllers\GoodsIssueController::class, 'voidTransaction'])->name('void');
        Route::get('/{slug}/bast', [App\Http\Controllers\GoodsIssueController::class, 'printBast'])->name('bast');
    });


    // =========================================================
    // 🔙 MODUL 3: GOODS ISSUE RETURNS (RETUR PENGELUARAN)
    // =========================================================
    Route::prefix('goods-issue-returns')->name('goods-issue-returns.')->middleware(['can:manage_gi'])->group(function () {
        Route::get('/', [GoodsIssueReturnController::class, 'index'])->name('index');
        Route::get('/create/{gi_id}', [GoodsIssueReturnController::class, 'create'])->name('create');
        Route::post('/{gi_id}', [GoodsIssueReturnController::class, 'store'])->name('store');
        Route::get('/{id}', [GoodsIssueReturnController::class, 'show'])->name('show');
    });


    // Goods Issue Returns (Retur Pengeluaran)
    Route::prefix('goods-issue-returns')->name('goods-issue-returns.')->middleware(['can:manage_gi'])->group(function () {
        Route::get('/', [GoodsIssueReturnController::class, 'index'])->name('index');
        Route::get('/create/{gi_id}', [GoodsIssueReturnController::class, 'create'])->name('create');
        Route::post('/{gi_id}', [GoodsIssueReturnController::class, 'store'])->name('store');
        Route::get('/{id}', [GoodsIssueReturnController::class, 'show'])->name('show');
    });


    // Employee Inventory Tracking (Minor Assets)
    Route::prefix('employee-inventories')->name('employee-inventories.')->middleware(['auth'])->group(function () {
        Route::get('/', [\App\Http\Controllers\EmployeeInventoryController::class, 'index'])->name('index');
        Route::get('/history/{employee_name}', [\App\Http\Controllers\EmployeeInventoryController::class, 'history'])->name('history');

        // 🔥 PERBAIKAN: Cukup tulis 'print_qr' saja, karena sudah ada awalan dari grup di atas 🔥
        Route::get('/{id}/print-qr', [\App\Http\Controllers\EmployeeInventoryController::class, 'printQrLabel'])->name('print_qr');
    });


    // // ====================================================
    // // 4. MODUL RETURN TO VENDOR (RTV)
    // // ====================================================
    // Route::prefix('rtv')->name('rtv.')->group(function () {
    //     Route::get('/', [\App\Http\Controllers\ReturnToVendorController::class, 'index'])->name('index');
    //     Route::get('/create/{gr_id}', [\App\Http\Controllers\ReturnToVendorController::class, 'create'])->name('create');
    //     Route::post('/store/{gr_id}', [\App\Http\Controllers\ReturnToVendorController::class, 'store'])->name('store');

    //     // Wildcard {id} taruh paling bawah!
    //     Route::get('/{id}/show', [\App\Http\Controllers\ReturnToVendorController::class, 'show'])->name('show');
    // });


    // ====================================================
    // 4. MODUL RETURN TO VENDOR (RTV)
    // ====================================================
    Route::prefix('rtv')->name('rtv.')->group(function () {
        Route::get('/', [\App\Http\Controllers\ReturnToVendorController::class, 'index'])->name('index');

        // 🔥 PASTIKAN ADA ->where('slug', '.*') DI BAGIAN AKHIR INI 🔥
        Route::get('/create/{slug}', [\App\Http\Controllers\ReturnToVendorController::class, 'create'])->name('create')->where('slug', '.*');
        Route::post('/store/{slug}', [\App\Http\Controllers\ReturnToVendorController::class, 'store'])->name('store')->where('slug', '.*');

        Route::get('/{slug}/show', [\App\Http\Controllers\ReturnToVendorController::class, 'show'])->name('show')->where('slug', '.*');

        // 🔥 TAMBAHKAN RUTE PRINT INI KOMANDAN! 🔥
        Route::get('/{slug}/print', [\App\Http\Controllers\ReturnToVendorController::class, 'print'])->name('print')->where('slug', '.*');
    });



   // Master Inventory & Items
    Route::prefix('inventory')->name('inventory.')->middleware(['can:view_inventory'])->group(function () {
        Route::get('/', [InventoryController::class, 'index'])->name('index');

        Route::post('/adjustment', [InventoryController::class, 'storeAdjustment'])->name('adjustment');
        Route::post('/usage', [InventoryController::class, 'storeUsage'])->name('usage');

        // 🔥 RUTE SPESIFIK HARUS DI ATAS WILDCARD 🔥
        Route::get('/template', [InventoryController::class, 'downloadTemplate'])->name('download_template');
        Route::post('/import', [InventoryController::class, 'importSaldoAwal'])->name('import_saldo');

        // 🔥 2 Rute Baru untuk Preview & Eksekusi Import Saldo Awal Gudang
        Route::post('/import/preview', [InventoryController::class, 'previewImport'])->name('preview_import');
        Route::post('/import/process', [InventoryController::class, 'processImport'])->name('process_import');

        // 🔥 RUTE BARU UNTUK DOWNLOAD ERROR EXCEL 🔥
        Route::post('/import/errors', [InventoryController::class, 'downloadErrors'])->name('download_errors');

        // 🔥 INI WILDCARD (LUBANG HITAM), WAJIB DI POSISI PALING BAWAH! 🔥
        Route::get('/{inventory}', [InventoryController::class, 'show'])->name('show');


        Route::get('/capitalize/{slug}', [App\Http\Controllers\InventoryController::class, 'capitalizeForm'])->name('capitalize.form');
        Route::post('/capitalize/{slug}', [App\Http\Controllers\InventoryController::class, 'capitalizeStore'])->name('capitalize.store');

    });

    // =========================================================================
    // Modul Mutasi Antar Gudang
    // =========================================================================
    Route::prefix('stock-transfers')->name('stock-transfers.')->middleware(['can:manage_gi'])->group(function () {

        Route::get('/search-items', [App\Http\Controllers\StockTransferController::class, 'searchItems'])->name('search-items');

        Route::get('/', [App\Http\Controllers\StockTransferController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\StockTransferController::class, 'create'])->name('create');
        Route::post('/', [App\Http\Controllers\StockTransferController::class, 'store'])->name('store');

        // 🔥 JALUR UNTUK CETAK PDF 🔥
        Route::get('/{id}/print', [App\Http\Controllers\StockTransferController::class, 'printTransfer'])->name('print');

        Route::get('/{id}', [App\Http\Controllers\StockTransferController::class, 'show'])->name('show');
    });




    // ====================================================
    // MASTER BARANG & JASA (ITEMS)
    // ====================================================
    Route::prefix('items')->name('items.')->middleware(['can:view_inventory'])->group(function () {

        Route::get('/template-excel', [\App\Http\Controllers\ItemController::class, 'downloadTemplate'])->name('download_template');

        // 🔥 Rute khusus Import Baru
        Route::get('/import', [\App\Http\Controllers\ItemController::class, 'import'])->name('import');

        // 2 Rute Baru untuk Preview & Eksekusi Import Master Barang
        Route::post('/import/preview', [\App\Http\Controllers\ItemController::class, 'previewImport'])->name('preview_import');
        Route::post('/import/process', [\App\Http\Controllers\ItemController::class, 'processImport'])->name('process_import');


        Route::get('/import-staging/{batch_id}', [\App\Http\Controllers\ItemController::class, 'importStaging'])->name('import_staging');

        // 🔥 RUTE BARU UNTUK AKTIF/NONAKTIFKAN BARANG 🔥
        Route::patch('/{item}/toggle-status', [\App\Http\Controllers\ItemController::class, 'toggleStatus'])->name('toggle_status');

        // Halaman Riwayat / Daftar Import
        Route::get('/imports', [\App\Http\Controllers\ItemController::class, 'importIndex'])->name('import_index');

        // Rute untuk mengedit baris Typo di Karantina
        Route::put('/import-staging/detail/{id}', [\App\Http\Controllers\ItemController::class, 'updateStagingDetail'])->name('import_staging.update_detail');

        // Rute untuk membatalkan dan menghapus Draft Karantina
        Route::delete('/import-staging/{batch_id}/cancel', [\App\Http\Controllers\ItemController::class, 'cancelImport'])->name('import_staging.cancel');

        // 🔥 PERBAIKAN: Gembok ->except() DIBUANG agar halaman Create & Edit bisa diakses! 🔥
        Route::resource('/', \App\Http\Controllers\ItemController::class)->parameters(['' => 'item']);



    });


    // =========================================================================
    // 🚀 MODUL VENDOR (MASTER SUPPLIER)
    // =========================================================================
    Route::prefix('vendors')->name('vendors.')->middleware(['can:view_vendors'])->group(function () {

        // 1. Halaman Utama & Pencarian
        Route::get('/', [VendorController::class, 'index'])->name('index');

        // 2. Tambah Vendor Baru
        Route::get('/create', [VendorController::class, 'create'])->name('create');
        Route::post('/', [VendorController::class, 'store'])->name('store');

        // 3. Edit Data Vendor
        Route::get('/{vendor}/edit', [VendorController::class, 'edit'])->name('edit');
        Route::put('/{vendor}', [VendorController::class, 'update'])->name('update');

        // 4. Fitur Khusus: Aktif/Nonaktifkan Vendor (Toggle)
        Route::patch('/{vendor}/toggle-status', [VendorController::class, 'toggleStatus'])->name('toggle_status');

        // Jika Komandan ingin menggunakan Resource Controller agar lebih ringkas, bisa pakai ini:
        // Route::resource('/', VendorController::class)->except(['show', 'destroy'])->parameters(['' => 'vendor']);
    });


    // ====================================================
    // 5. MODUL FINANCE (A/P, BILLS, PAYMENTS)
    // ====================================================
    Route::prefix('vendor-invoices')->name('vendor-invoices.')->middleware(['can:view_invoices'])->group(function () {
        Route::get('/', [VendorInvoiceController::class, 'index'])->name('index');

        // PENTING: Tambahkan ->where('slug', '.*') di semua rute yang punya parameter {slug}
        Route::post('/from-gr/{slug}', [VendorInvoiceController::class, 'createFromGr'])->name('createFromGr')->where('slug', '.*');
        Route::get('/{slug}', [VendorInvoiceController::class, 'show'])->name('show')->where('slug', '.*');
        Route::put('/{slug}', [VendorInvoiceController::class, 'update'])->name('update')->where('slug', '.*');
        Route::post('/bulk-from-gr', [VendorInvoiceController::class, 'createBulkFromGr'])->name('createBulkFromGr');
        Route::post('/{slug}/pay', [VendorInvoiceController::class, 'storePayment'])->name('storePayment')->where('slug', '.*');


        Route::post('/{slug}/upload-attachment', [VendorInvoiceController::class, 'uploadAttachment'])->name('uploadAttachment')->where('slug', '.*');
        Route::delete('/attachment/{id}', [VendorInvoiceController::class, 'deleteAttachment'])->name('deleteAttachment');


        // Rute Void
        Route::delete('/{slug}/cancel-invoice', [VendorInvoiceController::class, 'cancelInvoice'])->name('cancelInvoice')->where('slug', '.*');
        Route::delete('/payment/{id}/cancel', [VendorInvoiceController::class, 'cancelPayment'])->name('cancelPayment');

    });



    Route::prefix('bills')->name('bills.')->middleware(['can:view_bills'])->group(function () {
        Route::get('/', [BillRequestController::class, 'index'])->name('index');
        Route::get('/create', [BillRequestController::class, 'create'])->name('create');
        Route::post('/', [BillRequestController::class, 'store'])->name('store');
        Route::get('/{bill}', [BillRequestController::class, 'show'])->name('show');
        Route::get('/{bill}/edit', [BillRequestController::class, 'edit'])->name('edit');
        Route::put('/{bill}', [BillRequestController::class, 'update'])->name('update');
        Route::delete('/{bill}', [BillRequestController::class, 'destroy'])->name('destroy');

        Route::post('/{id}/approve', [BillRequestController::class, 'approve'])->name('approve');
        Route::post('/{id}/reject', [BillRequestController::class, 'reject'])->name('reject');
        Route::post('/{id}/pay', [BillRequestController::class, 'markAsPaid'])->name('markAsPaid');
        Route::delete('/{id}/attachment/{mediaId}', [BillRequestController::class, 'destroyAttachment'])->name('destroyAttachment');
        Route::get('/{id}/print', [BillRequestController::class, 'printPdf'])->name('print');
    });

    Route::prefix('payments')->name('payments.')->middleware(['can:view_payments'])->group(function () {
        Route::get('/', [BillPaymentController::class, 'index'])->name('index');
        Route::get('/{id}/process', [BillPaymentController::class, 'process'])->name('process');
        Route::post('/{id}/store', [BillPaymentController::class, 'store'])->name('store');
        Route::delete('/{id}', [BillPaymentController::class, 'destroy'])->name('destroy');
        Route::get('/receipt/{payment_id}/print', [BillPaymentController::class, 'printReceipt'])->name('receipt.print');
        Route::get('/{bill_id}/statement/print', [BillPaymentController::class, 'printStatement'])->name('statement.print');
    });

    // ====================================================
    // 6. MODUL LAPORAN (REPORT CENTER) - SEKARANG AMAN! 🔒
    // ====================================================
    Route::prefix('reports')->name('reports.')->middleware(['can:view_reports'])->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/generate', [ReportController::class, 'generate'])->name('generate');

        Route::get('/finance', [FinanceReportController::class, 'index'])->name('finance');
        Route::get('/finance/pdf', [FinanceReportController::class, 'exportPdf'])->name('finance.pdf');
        Route::get('/finance/excel', [FinanceReportController::class, 'exportExcel'])->name('finance.excel');




        // Route untuk Laporan 3-Way Matching
        Route::get('/three-way-matching', [\App\Http\Controllers\ReportController::class, 'threeWayMatching'])->name('3way');

    });

    // ====================================================
    // 7. MODUL SETTINGS / ADMIN -> Izin: manage_roles
    // ====================================================
    Route::resource('roles', RoleController::class)->middleware('can:manage_roles');
    Route::resource('users', UserController::class)->middleware('can:manage_roles');



    // ====================================================
    // X. MODUL MASTER SATUAN (UOM)
    // ====================================================
    Route::prefix('uoms')->name('uoms.')->middleware(['can:view_inventory'])->group(function () {

        // Membuat 4 rute sekaligus: uoms.index, uoms.store, uoms.update, uoms.destroy
        Route::resource('/', \App\Http\Controllers\UomController::class)->except(['create', 'show', 'edit']);

    });

    Route::prefix('asset-capitalizations')->name('asset-capitalizations.')->middleware(['can:view_inventory'])->group(function () {

        // Modul Kapitalisasi / Pengakuan Aset
        Route::get('/create', [App\Http\Controllers\AssetCapitalizationController::class, 'create'])->name('create');
        Route::post('/asset-capitalizations', [App\Http\Controllers\AssetCapitalizationController::class, 'store'])->name('store');
        Route::get('/get-items/{gr_id}', [App\Http\Controllers\AssetCapitalizationController::class, 'getGrItems'])->name('get-items');

    });

    // ====================================================
    // settings
    // ====================================================
    Route::prefix('settings')->group(function () {
        Route::resource('workflows', \App\Http\Controllers\ApprovalWorkflowController::class);
        Route::resource('document-types', \App\Http\Controllers\DocumentTypeController::class);
    });






}); // <--- PENUTUP GEMBOK UTAMA (AUTH)

// Load Route Authentication (Login, Register, Logout)
require __DIR__.'/auth.php';
