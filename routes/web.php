<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CashRegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\WorkOrderController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/giris', [LoginController::class, 'show'])->name('login');
    Route::post('/giris', [LoginController::class, 'login'])->name('login.attempt');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::post('/cikis', [LoginController::class, 'logout'])->name('logout');

    Route::resource('cariler', AccountController::class)
        ->names('accounts')
        ->parameters(['cariler' => 'account']);

    Route::resource('kasalar', CashRegisterController::class)
        ->names('cash-registers')
        ->parameters(['kasalar' => 'cashRegister']);

    Route::resource('atolye', WorkOrderController::class)
        ->names('work-orders')
        ->parameters(['atolye' => 'workOrder']);
    Route::post('/atolye/{workOrder}/cikis', [WorkOrderController::class, 'deliver'])->name('work-orders.deliver');
    Route::delete('/atolye/{workOrder}/cikis/{delivery}', [WorkOrderController::class, 'destroyDelivery'])
        ->scopeBindings()
        ->name('work-orders.deliveries.destroy');
    Route::get('/atolye/{workOrder}/cikis/{delivery}/fis', [WorkOrderController::class, 'receipt'])
        ->scopeBindings()
        ->name('work-orders.deliveries.receipt');
    Route::post('/atolye/{workOrder}/kapat', [WorkOrderController::class, 'close'])->name('work-orders.close');
    Route::delete('/atolye/{workOrder}/kapat', [WorkOrderController::class, 'reopen'])->name('work-orders.reopen');

    Route::resource('hareketler', TransactionController::class)
        ->except('show')
        ->names('transactions')
        ->parameters(['hareketler' => 'transaction']);

    // Yedekleme (sadece yönetici)
    Route::middleware('admin')->prefix('yedekleme')->name('backups.')->group(function () {
        Route::get('/', [BackupController::class, 'index'])->name('index');
        Route::post('/', [BackupController::class, 'store'])->name('store');
        Route::get('/{backupLog}/indir', [BackupController::class, 'download'])->name('download');
        Route::get('/google/baglan', [BackupController::class, 'connect'])->name('google.connect');
        Route::get('/google/callback', [BackupController::class, 'callback'])->name('google.callback');
        Route::delete('/google', [BackupController::class, 'disconnect'])->name('google.disconnect');
    });
});
