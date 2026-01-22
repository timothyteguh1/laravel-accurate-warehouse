<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AccurateTestController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\InventoryController;

// === 1. GUEST AREA (Halaman Login & Register Staff) ===
Route::middleware('guest')->group(function () {
    Route::get('/', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.post');
});

// === 2. AUTH AREA (Area Kerja Staff) ===
Route::middleware('auth')->group(function () {
    
    // Logout Staff
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // === INTEGRASI ACCURATE (Admin Only) ===
    Route::get('/accurate/auth', [AccurateTestController::class, 'login'])->name('accurate.auth');
    Route::get('/accurate/callback', [AccurateTestController::class, 'callback'])->name('accurate.callback');
    Route::get('/accurate/open-db', [AccurateTestController::class, 'openDatabase'])->name('accurate.open_db');

    // === GUDANG DASHBOARD ===
    Route::get('/dashboard', [WarehouseController::class, 'dashboard']);
    
    // Fitur Operasional Gudang
    Route::get('/scan-so', [WarehouseController::class, 'scanSOListPage']); 
    Route::get('/scan-process/{id}', [WarehouseController::class, 'scanSODetailPage']);
    Route::post('/scan-process/submit', [WarehouseController::class, 'submitDOWithLocalLookup']);
    Route::get('/print-do/{id}', [WarehouseController::class, 'printDeliveryOrder']);
    
    // Fitur Tambahan
    Route::get('/sales-order/create', [SalesOrderController::class, 'create']);
    Route::post('/sales-order/store', [SalesOrderController::class, 'store']);
    Route::get('/setup-data', [WarehouseController::class, 'generateDummyData']);
    Route::get('/setup-stock', [WarehouseController::class, 'fillDummyStock']);
    Route::get('/history-do', [WarehouseController::class, 'historyDOPage']);
    Route::get('/find-do-print/{soNumber}', [WarehouseController::class, 'searchAndPrintDO']);
    Route::get('/inventory', [InventoryController::class, 'index']);
});