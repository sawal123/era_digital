<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

use App\Http\Controllers\PosController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\StoreProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReceivableController;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // POS Routes
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('/pos', [PosController::class, 'store'])->name('pos.store');
    Route::get('/pos/print/{invoice_number}', [PosController::class, 'printInvoice'])->name('pos.print');
    Route::get('/api/digital-accounts/search', [PosController::class, 'searchDigitalAccounts'])->name('digital-accounts.search');

    // CRUD Master Kategori & Produk
    Route::resource('categories', CategoryController::class);
    Route::resource('products', ProductController::class);
    Route::resource('customers', CustomerController::class);

    // Laporan Penjualan
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::delete('/reports/{transaction}', [ReportController::class, 'destroy'])->name('reports.destroy');

    // Pengeluaran CRUD
    Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');

    // Restock / Belanja Barang (Stok Masuk)
    Route::get('/purchases', [PurchaseController::class, 'index'])->name('purchases.index');
    Route::post('/purchases', [PurchaseController::class, 'store'])->name('purchases.store');

    // Catatan Piutang & Pelunasan
    Route::get('/receivables', [ReceivableController::class, 'index'])->name('receivables.index');
    Route::post('/receivables/{transaction}/pay', [ReceivableController::class, 'pay'])->name('receivables.pay');

    // Pengaturan Toko
    Route::get('/settings/store', [StoreProfileController::class, 'edit'])->name('settings.store.edit');
    Route::post('/settings/store', [StoreProfileController::class, 'update'])->name('settings.store.update');
});

require __DIR__.'/settings.php';
