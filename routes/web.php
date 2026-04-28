<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AccurateTestController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\SalesOrderStatusController;
use App\Http\Controllers\OrinController;

// === 1. GUEST AREA ===
Route::middleware('guest')->group(function () {
    Route::get('/', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.post');
});

// === 2. AUTH AREA ===
Route::middleware('auth')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Accurate OAuth
    Route::get('/accurate/auth', [AccurateTestController::class, 'login'])->name('accurate.auth');
    Route::get('/accurate/callback', [AccurateTestController::class, 'callback'])->name('accurate.callback');
    Route::get('/accurate/open-db', [AccurateTestController::class, 'openDatabase'])->name('accurate.open_db');

    // Dashboard
    Route::get('/dashboard', [WarehouseController::class, 'dashboard']);
    Route::get('/dashboard/refresh', [WarehouseController::class, 'refreshDashboard'])->name('dashboard.refresh');

    // Operasional Gudang
    Route::get('/scan-so', [WarehouseController::class, 'scanSOListPage']);
    Route::get('/scan-process/{id}', [WarehouseController::class, 'scanSODetailPage']);   // handles QUEUE & WAITING
    Route::post('/scan-process/submit', [WarehouseController::class, 'submitDOWithLocalLookup']);
    Route::get('/print-do/{id}', [WarehouseController::class, 'printDeliveryOrder']);
    Route::get('/history-do', [WarehouseController::class, 'historyDOPage']);
    Route::get('/find-do-print/{soNumber}', [WarehouseController::class, 'searchAndPrintDO']);

    // Fitur Lain
    Route::get('/sales-order/create', [SalesOrderController::class, 'create']);
    Route::post('/sales-order/store', [SalesOrderController::class, 'store']);
    Route::get('/inventory', [InventoryController::class, 'index']);

    // Debug SO Status
    Route::prefix('debug')->name('so.status.')->group(function () {
        Route::get('/so-status', [SalesOrderStatusController::class, 'index'])->name('index');
        Route::get('/so-status/{id}', [SalesOrderStatusController::class, 'show'])->name('show');
    });

    Route::get('/debug/so-do-link/{soNumber}', [App\Http\Controllers\WarehouseController::class, 'checkSoDoLink']);
    Route::get('/get-dos-by-so/{soNumber}', [WarehouseController::class, 'getDoListBySo']);
    // Assign Driver (Logistik)
    Route::post('/assign-driver', [WarehouseController::class, 'assignDriver']);
    // Monitor Sopir & Armada
    Route::get('/drivers', [WarehouseController::class, 'driverMonitor']);
    Route::post('/delivery/update-alamat', [App\Http\Controllers\WarehouseController::class, 'updateAlamat']);

    // Route Internal untuk jembatan Live Tracking ORIN
    Route::get('/api/track-driver/{nopol}', [App\Http\Controllers\WarehouseController::class, 'getOrinLocation']);
    Route::post('/delivery/{id}/start', [WarehouseController::class, 'startDelivery'])->name('delivery.start');
    Route::post('/delivery/{id}/end', [WarehouseController::class, 'endDelivery'])->name('delivery.end');

    // ORIN Fleet Monitoring
    Route::prefix('orin')->name('orin.')->group(function () {
        // [Tambahkan route debug disini]
        Route::get('/debug/test', [OrinController::class, 'testConnection'])->name('test.connection');

        Route::get('/', [OrinController::class, 'index'])->name('index');
        Route::get('/device/{vehicleId}', [OrinController::class, 'show'])->name('device');
        Route::get('/', [OrinController::class, 'index'])->name('index');
        Route::get('/device/{vehicleId}', [OrinController::class, 'show'])->name('device');
        Route::get('/device/{vehicleId}/history', [OrinController::class, 'historyRoutes'])->name('history');
        Route::get('/alerts', [OrinController::class, 'alerts'])->name('alerts');

        // JSON API endpoints (peta live, dll)
        Route::get('/api/devices', [OrinController::class, 'apiDevices']);
        Route::get('/api/raw/{vehicleId}', [OrinController::class, 'apiRawData']);
        Route::post('/api/available', [OrinController::class, 'apiUpdateAvailable']);

        // Rute untuk Tombol Kendali Pelacakan (DO)

    });

    // DIHAPUS: Route::get('/waiting-so/{id}', ...) — tidak diperlukan lagi
    // WAITING SO sekarang langsung masuk /scan-process/{id} — controller detect status WAITING otomatis
});