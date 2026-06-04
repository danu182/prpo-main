<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseRequestController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\GoodsReceiptController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\BillPaymentController;
use App\Http\Controllers\BillRequestController;
use App\Http\Controllers\DashboardController; // <--- Pastikan baris ini ada di paling atas
use App\Http\Controllers\InventoryController;



/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Halaman Depan (Redirect ke Login)
Route::get('/', function () {
    return redirect()->route('login');
});

// Dashboard (Setelah Login)
// Route::get('/dashboard', function () {
//     return view('dashboard'); // Pastikan view dashboard.blade.php ada
// })->middleware(['auth', 'verified'])->name('dashboard');


// Route::get('/dashboard', function () {
    // Nanti Anda bisa inject data real di sini
    // $pendingPR = PurchaseRequest::where('status', 'PENDING_APPROVAL')->count();
    // return view('dashboard', compact('pendingPR'));

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

// })->middleware(['auth', 'verified'])->name('dashboard');



// GROUP ROUTE: Hanya bisa diakses user yang sudah Login
Route::middleware('auth')->group(function () {

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');


    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ====================================================
    // 1. MODUL PURCHASE REQUEST (PR)
    // Bisa diakses semua staff untuk request
    // ====================================================
    Route::group(['prefix' => 'pr'], function () {

        // 1. Tampilkan List & Form Buat (URL Statis ditaruh paling atas)
        Route::get('/', [PurchaseRequestController::class, 'index'])->name('pr.index');
        Route::get('/create', [PurchaseRequestController::class, 'create'])->name('pr.create');
        Route::post('/', [PurchaseRequestController::class, 'store'])->name('pr.store');

        // 2. Operasi berdasarkan ID (URL Dinamis ditaruh di bawah)

        // Halaman Edit (Harus ada tambahan /edit di URL-nya)
        Route::get('/{id}/edit', [PurchaseRequestController::class, 'edit'])->name('pr.edit');

        // Proses Update (Simpan Perubahan)
        Route::put('/{id}', [PurchaseRequestController::class, 'update'])->name('pr.update');

        // Halaman Detail / Approval
        Route::get('/{id}', [PurchaseRequestController::class, 'show'])->name('pr.show');

        // Proses Approval/Reject
        Route::post('/{id}/decide', [PurchaseRequestController::class, 'decide'])->name('pr.decide');

        // Hapus (Jika diperlukan nanti)
        Route::delete('/{id}', [PurchaseRequestController::class, 'destroy'])->name('pr.destroy');


        // Tambahkan di dalam Route::group(['prefix' => 'pr'] ...)
        Route::get('/{id}/print', [PurchaseRequestController::class, 'print'])->name('pr.print');

        // Route untuk Cancel
        Route::post('/{id}/cancel', [PurchaseRequestController::class, 'cancel'])->name('pr.cancel');

        Route::post('/pr/{id}/reject-all', [App\Http\Controllers\PurchaseRequestController::class, 'rejectAll'])->name('pr.rejectAll');


        Route::post('/pr-item/{id}/force-close', [App\Http\Controllers\PurchaseRequestController::class, 'forceCloseItem'])->name('pr.item.forceClose');

    });

        // ====================================================
        // 2. MODUL PURCHASE ORDER (PO)
        // Sebaiknya hanya Role Procurement
        // ====================================================
    Route::group(['prefix' => 'po'], function () {

        // 1. Form Pembuatan PO
        // URL: /purchase-orders/create-from-pr/{pr_id}/{vendor_id}
        // Name: po.createFromPr
        // Route::get('/create-from-pr/{pr_id}/{vendor_id?}', [PurchaseOrderController::class, 'createFromPr'])->name('createFromPr')->middleware('permission:create_po');

        // 2. Approval (PERBAIKAN DISINI)
        // Hapus '/purchase-orders' di URL dan 'po.' di name karena sudah otomatis ikut grup

        // URL: /purchase-orders/{id}/approve
        // Name: po.approve
        // Route::post('/{id}/approve', [PurchaseOrderController::class, 'approve'])->name('approve');

        // URL: /purchase-orders/{id}/reject
        // Name: po.reject
        // Route::post('/{id}/reject', [PurchaseOrderController::class, 'reject'])->name('reject');

        // 3. Simpan PO
        // URL: /purchase-orders/store
        // Name: po.store
        // Route::post('/store', [PurchaseOrderController::class, 'store'])->name('store');

        // 4. Index & Detail
        // URL: /purchase-orders/
        // Name: po.index
        // Route::get('/', [PurchaseOrderController::class, 'index'])->name('index')->middleware('permission:view_po');;

        // URL: /purchase-orders/{id}
        // Name: po.show
        // PENTING: Taruh ini paling bawah agar tidak bentrok dengan URL lain seperti /create-from-pr
        // Route::get('/{id}', [PurchaseOrderController::class, 'show'])->name('show');


        // Halaman index
        Route::get('/po', [\App\Http\Controllers\PurchaseOrderController::class, 'index'])
            ->name('po.index')
            ->middleware('permission:view_po'); // Pastikan hanya Admin PO yang bisa akses

        // Halaman Form Pembuatan PO dari PR
        Route::get('/po/process-pr/{id}', [\App\Http\Controllers\PurchaseOrderController::class, 'processPr'])
            ->name('po.process_pr')
            ->middleware('permission:create_po'); // Pastikan hanya Admin PO yang bisa akses

        // Proses Penyimpanan (Sihir Pemecahan PO)
        Route::post('/po/store-from-pr/{id}', [\App\Http\Controllers\PurchaseOrderController::class, 'storeFromPr'])
            ->name('po.store_from_pr')
            ->middleware('permission:create_po');

            // Proses Penyimpanan (Sihir Pemecahan PO)
        Route::get('/po/po.show/{id}', [\App\Http\Controllers\PurchaseOrderController::class, 'show'])
            ->name('po.show')
            ->middleware('permission:view_po');
            // Proses Penyimpanan (Sihir Pemecahan PO)
        Route::get('/po/po.edit/{id}', [\App\Http\Controllers\PurchaseOrderController::class, 'edit'])
            ->name('po.edit')
            ->middleware('permission:create_po');

        // proses update PO
        Route::put('/po/po/update/{id}', [App\Http\Controllers\PurchaseOrderController::class, 'update'])
            ->name('po.update')
            ->middleware('permission:create_po');


        // Rute Persetujuan PO
        Route::post('/po/{id}/submit-approval', [App\Http\Controllers\PurchaseOrderController::class, 'submitApproval'])
            ->name('po.submit_approval');
        Route::post('/po/{id}/approve', [App\Http\Controllers\PurchaseOrderController::class, 'approve'])
            ->name('po.approve')
            ->middleware(['auth', 'role:manager|direktur|Super Admin']);
        Route::post('/po/{id}/reject', [App\Http\Controllers\PurchaseOrderController::class, 'reject'])
            ->name('po.reject')
            ->middleware(['auth', 'role:manager|direktur|Super Admin']);


        // Rute untuk menghapus lampiran spesifik (Gunakan prefix yang unik agar tidak bentrok)
        // Rute Hapus Lampiran PO
        Route::delete('/po/delete-attachment/{id}', [\App\Http\Controllers\PurchaseOrderController::class, 'deleteAttachment'])->name('po.attachment.destroy');

        // Rute Cancel PO
        Route::post('/po/{id}/cancel', [App\Http\Controllers\PurchaseOrderController::class, 'cancel'])->name('po.cancel');

    });


    Route::group(['prefix' => 'receive'], function ()   {
        // Route untuk modul Penerimaan Barang (Goods Receipt)
        Route::get('/po/{id}/receive', [App\Http\Controllers\GoodsReceiptController::class, 'create'])->name('gr.create');
        Route::post('/po/{id}/receive', [App\Http\Controllers\GoodsReceiptController::class, 'store'])->name('gr.store');

        // Route untuk cetak dokumen Penerimaan Barang (BAST/GR)
        Route::get('/gr/{id}/print', [App\Http\Controllers\GoodsReceiptController::class, 'print'])->name('gr.print');
    });

    Route::group(['prefix' => 'asset'], function ()   {
       // RUTE MANAJEMEN ASET TETAP (FIXED ASSET)
        Route::get('/fixed-assets', [App\Http\Controllers\FixedAssetController::class, 'index'])->name('fixed-assets.index');
        Route::post('/fixed-assets', [App\Http\Controllers\FixedAssetController::class, 'store'])->name('fixed-assets.store'); // <--- TAMBAHKAN INI
        Route::put('/fixed-assets/{id}', [App\Http\Controllers\FixedAssetController::class, 'update'])->name('fixed-assets.update');

    });

    Route::group(['prefix' => 'adjustments'], function ()   {
       // RUTE PENYESUAIAN STOK (STOCK OPNAME)
        Route::get('/stock-adjustments/create', [App\Http\Controllers\StockAdjustmentController::class, 'create'])->name('stock-adjustments.create');
        Route::post('/stock-adjustments', [App\Http\Controllers\StockAdjustmentController::class, 'store'])->name('stock-adjustments.store');
    });

    Route::group(['prefix' => 'inventory'], function ()   {
       // RUTE INVENTORY & KARTU STOK
        Route::resource('inventory', App\Http\Controllers\InventoryController::class)->only(['index', 'show']);
    });

    Route::group(['prefix' => 'items'], function ()   {
       // RUTE MASTER BARANG & JASA
        Route::resource('items', App\Http\Controllers\ItemController::class)->except(['create', 'edit', 'show']);

    });

    Route::group(['goods-issues' => 'asset'], function ()   {
       // RUTE PENGELUARAN BARANG (GOODS ISSUE)
        Route::get('/goods-issues/create', [App\Http\Controllers\GoodsIssueController::class, 'create'])->name('goods-issues.create');
        Route::post('/goods-issues', [App\Http\Controllers\GoodsIssueController::class, 'store'])->name('goods-issues.store');
        Route::get('/goods-issues/{id}', [App\Http\Controllers\GoodsIssueController::class, 'show'])->name('goods-issues.show');


    });

    Route::group(['prefix' => 'invoices'], function ()   {
        Route::get('/vendor-invoices', [App\Http\Controllers\VendorInvoiceController::class, 'index'])->name('vendor-invoices.index');
        Route::post('/vendor-invoices/from-gr/{grId}', [App\Http\Controllers\VendorInvoiceController::class, 'createFromGr'])->name('vendor-invoices.createFromGr');
        Route::get('/vendor-invoices/{id}', [App\Http\Controllers\VendorInvoiceController::class, 'show'])->name('vendor-invoices.show');
        Route::put('/vendor-invoices/{id}', [App\Http\Controllers\VendorInvoiceController::class, 'update'])->name('vendor-invoices.update');
        Route::post('/vendor-invoices/bulk-from-gr', [App\Http\Controllers\VendorInvoiceController::class, 'createBulkFromGr'])->name('vendor-invoices.createBulkFromGr');
        Route::post('/vendor-invoices/{id}/pay', [App\Http\Controllers\VendorInvoiceController::class, 'storePayment'])->name('vendor-invoices.storePayment');

    });


    Route::group(['prefix' => 'bill'], function ()   {
        Route::resource('bills', BillRequestController::class);

        // Route khusus approval
        // Route::post('bills/{id}/decide', [BillRequestController::class, 'approveReject'])->name('bills.decide');

        Route::post('/bills/{id}/approve', [BillRequestController::class, 'approve'])->name('bills.approve');
        Route::post('/bills/{id}/reject', [BillRequestController::class, 'reject'])->name('bills.reject');

        Route::post('bills/{id}/pay', [BillRequestController::class, 'markAsPaid'])->name('bills.markAsPaid');

        Route::delete('/bills/{id}/attachment/{mediaId}', [BillRequestController::class, 'destroyAttachment'])
        ->name('bills.destroyAttachment');

        Route::get('/bills/{id}/print', [BillRequestController::class, 'print'])->name('bills.print');

        Route::post('/bills/{id}/mark-as-paid', [BillRequestController::class, 'markAsPaid'])->name('bills.markAsPaid');


    });



    // Menu Utama untuk Tim Finance
    Route::prefix('payments')->name('payments.')->group(function () {
        Route::get('/', [BillPaymentController::class, 'index'])->name('index'); // Daftar hutang (APPROVED)
        Route::get('/{id}/process', [BillPaymentController::class, 'process'])->name('process'); // Form Bayar
        Route::post('/{id}/store', [BillPaymentController::class, 'store'])->name('store'); // Simpan Termin


        // Route::delete('/{payment_id}/destroy', [App\Http\Controllers\BillPaymentController::class, 'destroy'])->name('destroy');
        // Route::delete('/payments/{id}/destroy', [BillPaymentController::class, 'destroy'])->name('payments.destroy');
        Route::delete('/{id}', [BillPaymentController::class, 'destroy'])->name('destroy');

        // Cetak kuitansi tunggal (per cicilan)
        Route::get('/receipt/{payment_id}/print', [App\Http\Controllers\BillPaymentController::class, 'printReceipt'])->name('receipt.print');

        // Cetak rekapitulasi (seluruh cicilan untuk satu bill)
        Route::get('/{bill_id}/statement/print', [App\Http\Controllers\BillPaymentController::class, 'printStatement'])->name('statement.print');

        Route::get('/payments/print-statement/{id}', [BillPaymentController::class, 'printStatement'])->name('payments.statement.print');

    });



    // ====================================================
    // 3. MODUL GOODS RECEIPT (PENERIMAAN BARANG)
    // Sebaiknya Role Gudang/GA/Procurement
    // ====================================================
    Route::prefix('gr')->name('gr.')->group(function () {
        Route::get('/', [GoodsReceiptController::class, 'index'])->name('index');

        // Form GR butuh ID dari PO yang mau diterima
        Route::get('/create/{po_id}', [GoodsReceiptController::class, 'create'])->name('create');
        Route::post('/', [GoodsReceiptController::class, 'store'])->name('store');
    });


    // ====================================================
    // 4. MODUL ASSET MANAGEMENT
    // Sebaiknya Role Finance/Accounting
    // ====================================================
    Route::prefix('assets')->name('assets.')->group(function () {
        // List semua aset
        Route::get('/', [AssetController::class, 'index'])->name('index');

        // List aset "Pending Tagging" (Baru masuk dari GR)
        Route::get('/pending', [AssetController::class, 'indexPending'])->name('pending');

        // Action: Input Nomor Aset (Register)
        Route::post('/{id}/register', [AssetController::class, 'registerAsset'])->name('register');

        // Action: Mutasi Aset (Pindah Tangan/Lokasi)
        Route::post('/{id}/mutate', [AssetController::class, 'mutate'])->name('mutate');
    });


    // ====================================================
    // 5. MODUL INVENTORY (STOK HABIS PAKAI)
    // ====================================================
    Route::prefix('inventory')->name('inventory.')->group(function () {
        // Lihat Stok
        Route::get('/', [InventoryController::class, 'index'])->name('index');

        // Pakai Stok (Barang Keluar)
        Route::post('/usage', [InventoryController::class, 'storeUsage'])->name('usage');
    });


    // ====================================================
    // PROFIL USER (Bawaan Breeze/Laravel)
    // ====================================================
    // Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    // Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    // Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');


    // Rute Manajemen Role Spatie
    Route::resource('roles', \App\Http\Controllers\RoleController::class);


    // Rute Manajemen Pengguna (Hanya bisa diakses oleh yang punya izin, misalnya Super Admin)
Route::resource('users', \App\Http\Controllers\UserController::class);

});

// Load Route Authentication (Login, Register, Logout)
require __DIR__.'/auth.php';
