<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AccurateTestController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\SalesOrderController; // <--- Tambahkan ini

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Redirect root
Route::get('/', function () {
    return redirect('/dashboard');
});

// === AUTH ACCURATE (Controller Lama) ===
Route::get('/accurate/login', [AccurateTestController::class, 'login'])->name('accurate.login');
Route::get('/accurate/callback', [AccurateTestController::class, 'callback'])->name('accurate.callback');
Route::get('/accurate/open-db', [AccurateTestController::class, 'openDatabase']);

// === GUDANG & SO ===
Route::middleware(['web'])->group(function () {
    
    // --- Dashboard & Warehouse (Existing) ---
    Route::get('/dashboard', [WarehouseController::class, 'dashboard']);
    Route::get('/scan-so', [WarehouseController::class, 'scanSO']);

    // Proses Scan
    Route::post('/scan-process/submit', [WarehouseController::class, 'submitDeliveryOrder']);
    Route::get('/print-do/{id}', [WarehouseController::class, 'printDeliveryOrder']);
    Route::get('/scan-process/{id}', [WarehouseController::class, 'scanProcess']);
    
    // Setup Dummy Data
    Route::get('/setup-data', [WarehouseController::class, 'generateDummyData']);
    Route::get('/setup-stock', [WarehouseController::class, 'fillDummyStock']);

    // --- SALES ORDER MANUAL (BARU) ---
    Route::get('/sales-order/create', [SalesOrderController::class, 'create']);
    Route::post('/sales-order/store', [SalesOrderController::class, 'store']);
});