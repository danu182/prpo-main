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

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Halaman Depan (Redirect ke Login)
Route::get('/', function () {
    return redirect()->route('login');
});

// GROUP ROUTE: Hanya bisa diakses user yang sudah Login
Route::middleware('auth')->group(function () {

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ====================================================
    // 1. MODUL PURCHASE REQUEST (PR) -> Izin: view_pr
    // ====================================================
    Route::group(['prefix' => 'pr', 'middleware' => ['can:view_pr']], function () {
        Route::get('/', [PurchaseRequestController::class, 'index'])->name('pr.index');
        Route::get('/create', [PurchaseRequestController::class, 'create'])->name('pr.create');
        Route::post('/', [PurchaseRequestController::class, 'store'])->name('pr.store');
        Route::get('/{id}/edit', [PurchaseRequestController::class, 'edit'])->name('pr.edit');
        Route::put('/{id}', [PurchaseRequestController::class, 'update'])->name('pr.update');
        Route::get('/{id}', [PurchaseRequestController::class, 'show'])->name('pr.show');
        Route::post('/{id}/decide', [PurchaseRequestController::class, 'decide'])->name('pr.decide');
        Route::delete('/{id}', [PurchaseRequestController::class, 'destroy'])->name('pr.destroy');
        Route::get('/{id}/print', [PurchaseRequestController::class, 'print'])->name('pr.print');
        Route::post('/{id}/cancel', [PurchaseRequestController::class, 'cancel'])->name('pr.cancel');
        Route::post('/pr/{id}/reject-all', [PurchaseRequestController::class, 'rejectAll'])->name('pr.rejectAll');
        Route::post('/pr-item/{id}/force-close', [PurchaseRequestController::class, 'forceCloseItem'])->name('pr.item.forceClose');
    });

    // ====================================================
    // 2. MODUL PURCHASE ORDER (PO) -> Izin: view_po / create_po
    // ====================================================
    Route::group(['prefix' => 'po'], function () {
        Route::get('/po', [PurchaseOrderController::class, 'index'])->name('po.index')->middleware('can:view_po');
        Route::get('/po/process-pr/{id}', [PurchaseOrderController::class, 'processPr'])->name('po.process_pr')->middleware('can:view_po');
        Route::post('/po/store-from-pr/{id}', [PurchaseOrderController::class, 'storeFromPr'])->name('po.store_from_pr')->middleware('can:view_po');
        Route::get('/po/po.show/{id}', [PurchaseOrderController::class, 'show'])->name('po.show')->middleware('can:view_po');
        Route::get('/po/po.edit/{id}', [PurchaseOrderController::class, 'edit'])->name('po.edit')->middleware('can:view_po');
        Route::put('/po/po/update/{id}', [PurchaseOrderController::class, 'update'])->name('po.update')->middleware('can:view_po');

        // Rute Persetujuan (Approval) khusus Role Tertentu
        Route::post('/po/{id}/submit-approval', [PurchaseOrderController::class, 'submitApproval'])->name('po.submit_approval');
        Route::post('/po/{id}/approve', [PurchaseOrderController::class, 'approve'])->name('po.approve')->middleware(['role:manager|direktur|Super Admin']);
        Route::post('/po/{id}/reject', [PurchaseOrderController::class, 'reject'])->name('po.reject')->middleware(['role:manager|direktur|Super Admin']);

        Route::delete('/po/delete-attachment/{id}', [PurchaseOrderController::class, 'deleteAttachment'])->name('po.attachment.destroy');
        Route::post('/po/{id}/cancel', [PurchaseOrderController::class, 'cancel'])->name('po.cancel')->middleware('can:view_po');
    });

    // ====================================================
    // 3. MODUL PENERIMAAN BARANG (GR) -> Izin: view_gr
    // ====================================================
    Route::group(['prefix' => 'receive', 'middleware' => ['can:view_gr']], function () {

        // 1. PROSES PEMBUATAN GR (Berdasarkan ID PO)
        Route::get('/po/{id}', [GoodsReceiptController::class, 'create'])->name('gr.create');
        Route::post('/po/{id}', [GoodsReceiptController::class, 'store'])->name('gr.store');

        // 2. PROSES MELIHAT DOKUMEN GR (Berdasarkan ID GR)
        Route::get('/{id}/show', [GoodsReceiptController::class, 'show'])->name('gr.show');
        Route::get('/{id}/print', [GoodsReceiptController::class, 'print'])->name('gr.print');

    });

    Route::prefix('gr')->name('gr.')->middleware(['can:view_gr'])->group(function () {
        Route::get('/', [GoodsReceiptController::class, 'index'])->name('index');
        Route::get('/create/{po_id}', [GoodsReceiptController::class, 'create'])->name('create');
        Route::post('/', [GoodsReceiptController::class, 'store'])->name('store');
    });

    // ====================================================
    // 4. MODUL WAREHOUSE & INVENTORY -> Izin Masing-masing
    // ====================================================
    // Aset Tetap
    Route::group(['prefix' => 'asset', 'middleware' => ['can:view_assets']], function () {
        Route::get('/fixed-assets', [\App\Http\Controllers\FixedAssetController::class, 'index'])->name('fixed-assets.index');
        Route::post('/fixed-assets', [\App\Http\Controllers\FixedAssetController::class, 'store'])->name('fixed-assets.store');
        Route::put('/fixed-assets/{id}', [\App\Http\Controllers\FixedAssetController::class, 'update'])->name('fixed-assets.update');

        // TAMBAHKAN RUTE CETAK BAST INI:
        Route::get('/{id}/bast', [\App\Http\Controllers\FixedAssetController::class, 'printBast'])->name('fixed-assets.bast');

        //  TAMBAHKAN RUTE CETAK BAPA:
        Route::get('/fixed-assets/{id}/bapa', [\App\Http\Controllers\FixedAssetController::class, 'printBapa'])->name('fixed-assets.bapa');

        // TAMBAHKAN RUTE CETAK BAPP (Penghapusan):
        Route::get('/fixed-assets/{id}/bapp', [\App\Http\Controllers\FixedAssetController::class, 'printBapp'])->name('fixed-assets.bapp');


    });

    Route::prefix('assets')->name('assets.')->middleware(['can:view_assets'])->group(function () {
        Route::get('/', [AssetController::class, 'index'])->name('index');
        Route::get('/pending', [AssetController::class, 'indexPending'])->name('pending');
        Route::post('/{id}/register', [AssetController::class, 'registerAsset'])->name('register');
        Route::post('/{id}/mutate', [AssetController::class, 'mutate'])->name('mutate');

        Route::get('/import', [AssetController::class, 'showImportForm'])->name('import.form');

        //  Rute untuk Download Template
        Route::get('/download-template', [AssetController::class, 'downloadTemplate'])->name('download_template');

        // Rute untuk Proses Import (Yang kita buat tadi)
        Route::post('/import', [AssetController::class, 'import'])->name('import');





    });

    // Penyesuaian Stok & Pengeluaran (GI)
    Route::group(['prefix' => 'adjustments', 'middleware' => ['can:manage_gi']], function () {
        Route::get('/stock-adjustments/create', [\App\Http\Controllers\StockAdjustmentController::class, 'create'])->name('stock-adjustments.create');
        Route::post('/stock-adjustments', [\App\Http\Controllers\StockAdjustmentController::class, 'store'])->name('stock-adjustments.store');
    });

    // --- MODUL RETURN TO VENDOR (RTV) ---
    Route::prefix('rtv')->name('rtv.')->group(function () {

        Route::get('/', [\App\Http\Controllers\ReturnToVendorController::class, 'index'])->name('index');
        Route::get('/{id}/show', [\App\Http\Controllers\ReturnToVendorController::class, 'show'])->name('show');


        // Form retur dari spesifik Goods Receipt
        Route::get('/create/{gr_id}', [\App\Http\Controllers\ReturnToVendorController::class, 'create'])->name('create');
        Route::post('/store/{gr_id}', [\App\Http\Controllers\ReturnToVendorController::class, 'store'])->name('store');
    });


    Route::group(['prefix' => 'goods-issues', 'middleware' => ['can:manage_gi']], function () {
        Route::get('/create', [\App\Http\Controllers\GoodsIssueController::class, 'create'])->name('goods-issues.create');
        Route::post('/', [\App\Http\Controllers\GoodsIssueController::class, 'store'])->name('goods-issues.store');
        Route::get('/{id}', [\App\Http\Controllers\GoodsIssueController::class, 'show'])->name('goods-issues.show');
    });

    // ====================================================
    // INVENTORY & ITEM MASTER
    // ====================================================
    Route::group(['prefix' => 'inventory', 'middleware' => ['can:view_inventory']], function () {
        // Gunakan 'inventory' sebagai nama resource-nya, bukan '/'
        Route::resource('inventory', InventoryController::class)->only(['index', 'show']);
        Route::post('/usage', [InventoryController::class, 'storeUsage'])->name('inventory.usage');
    });


    // PERBAIKAN RUTE ITEMS: Tanpa prefix group, langsung deklarasi resource
    Route::resource('items', \App\Http\Controllers\ItemController::class)
        ->except(['create', 'edit', 'show'])
        ->middleware('can:manage_items');

    // ====================================================
    // 5. MODUL FINANCE (BILLS, AP, PAYMENTS) -> Izin Masing-masing
    // ====================================================
    Route::group(['prefix' => 'invoices', 'middleware' => ['can:view_invoices']], function () {
        Route::get('/vendor-invoices', [\App\Http\Controllers\VendorInvoiceController::class, 'index'])->name('vendor-invoices.index');
        Route::post('/vendor-invoices/from-gr/{grId}', [\App\Http\Controllers\VendorInvoiceController::class, 'createFromGr'])->name('vendor-invoices.createFromGr');
        Route::get('/vendor-invoices/{id}', [\App\Http\Controllers\VendorInvoiceController::class, 'show'])->name('vendor-invoices.show');
        Route::put('/vendor-invoices/{id}', [\App\Http\Controllers\VendorInvoiceController::class, 'update'])->name('vendor-invoices.update');
        Route::post('/vendor-invoices/bulk-from-gr', [\App\Http\Controllers\VendorInvoiceController::class, 'createBulkFromGr'])->name('vendor-invoices.createBulkFromGr');
        Route::post('/vendor-invoices/{id}/pay', [\App\Http\Controllers\VendorInvoiceController::class, 'storePayment'])->name('vendor-invoices.storePayment');
    });

    Route::group(['prefix' => 'bill', 'middleware' => ['can:view_bills']], function () {
        Route::resource('bills', BillRequestController::class);

        Route::post('/bills/{id}/approve', [BillRequestController::class, 'approve'])->name('bills.approve');
        Route::post('/bills/{id}/reject', [BillRequestController::class, 'reject'])->name('bills.reject');

        Route::post('bills/{id}/pay', [BillRequestController::class, 'markAsPaid'])->name('bills.markAsPaid');
        Route::delete('/bills/{id}/attachment/{mediaId}', [BillRequestController::class, 'destroyAttachment'])->name('bills.destroyAttachment');
        Route::get('/bills/{id}/print', [BillRequestController::class, 'printPdf'])->name('bills.print');
        Route::post('/bills/{id}/mark-as-paid', [BillRequestController::class, 'markAsPaid'])->name('bills.markAsPaid');


    });

    Route::prefix('payments')->name('payments.')->middleware(['can:view_payments'])->group(function () {
        Route::get('/', [BillPaymentController::class, 'index'])->name('index');
        Route::get('/{id}/process', [BillPaymentController::class, 'process'])->name('process');
        Route::post('/{id}/store', [BillPaymentController::class, 'store'])->name('store');
        Route::delete('/{id}', [BillPaymentController::class, 'destroy'])->name('destroy');
        Route::get('/receipt/{payment_id}/print', [BillPaymentController::class, 'printReceipt'])->name('receipt.print');
        Route::get('/{bill_id}/statement/print', [BillPaymentController::class, 'printStatement'])->name('statement.print');
        Route::get('/payments/print-statement/{id}', [BillPaymentController::class, 'printStatement'])->name('payments.statement.print'); // Alias route
    });

    // ====================================================
    // 6. MODUL SETTINGS / ADMIN -> Izin: manage_roles
    // ====================================================
    // INILAH GEMBOK UTAMA YANG MENENDANG BUDI KELUAR!
    Route::resource('roles', \App\Http\Controllers\RoleController::class)->middleware('can:manage_roles');
    Route::resource('users', \App\Http\Controllers\UserController::class)->middleware('can:manage_roles');

});

Route::prefix('reports')->name('reports.')->middleware(['can:view_payments'])->group(function () {



    Route::get('/', [\App\Http\Controllers\ReportController::class, 'index'])->name('index');
    Route::get('/generate', [\App\Http\Controllers\ReportController::class, 'generate'])->name('generate'); // <--- TAMBAH INI
    // --- MODUL LAPORAN (REPORTS) ---
    // Perhatikan: /reports dan reports. dihapus dari dalam sini
    Route::get('/finance', [\App\Http\Controllers\FinanceReportController::class, 'index'])->name('finance');

    Route::get('/finance/pdf', [\App\Http\Controllers\FinanceReportController::class, 'exportPdf'])->name('finance.pdf');

    Route::get('/finance/excel', [\App\Http\Controllers\FinanceReportController::class, 'exportExcel'])->name('finance.excel');

});



// Load Route Authentication (Login, Register, Logout)
require __DIR__.'/auth.php';
