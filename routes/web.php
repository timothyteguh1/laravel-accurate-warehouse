<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AccurateTestController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\SalesOrderController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Redirect root
Route::get('/', function () {
    return redirect('/dashboard');
});

// === AUTH ACCURATE ===
Route::get('/accurate/login', [AccurateTestController::class, 'login'])->name('accurate.login');
Route::get('/accurate/callback', [AccurateTestController::class, 'callback'])->name('accurate.callback');
Route::get('/accurate/open-db', [AccurateTestController::class, 'openDatabase']);

// === GUDANG & SO ===
Route::middleware(['web'])->group(function () {
    
    // --- Dashboard ---
    Route::get('/dashboard', [WarehouseController::class, 'dashboard']);

    // --- ALUR LIST -> DETAIL -> SCAN ---
    // 1. List SO (Halaman Antrian)
    Route::get('/scan-so', [WarehouseController::class, 'scanSOListPage']); 

    // 2. Detail SO (Halaman Scan Barang)
    Route::get('/scan-process/{id}', [WarehouseController::class, 'scanSODetailPage']);

    // 3. Submit DO + Close SO (Action)
    Route::post('/scan-process/submit', [WarehouseController::class, 'submitDOWithLocalLookup']);

    // --- Print DO ---
    Route::get('/print-do/{id}', [WarehouseController::class, 'printDeliveryOrder']);
    
    // --- SALES ORDER MANUAL (Optional) ---
    Route::get('/sales-order/create', [SalesOrderController::class, 'create']);
    Route::post('/sales-order/store', [SalesOrderController::class, 'store']);
    
    // Setup Dummy (Optional)
    Route::get('/setup-data', [WarehouseController::class, 'generateDummyData']);
    Route::get('/setup-stock', [WarehouseController::class, 'fillDummyStock']);
});