<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\StockController;
use App\Support\Catalog;
use Illuminate\Support\Facades\Route;

Route::get('/login', fn () => view('auth.login'))->middleware('guest')->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware(['guest', 'throttle:20,1']);
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/', DashboardController::class)->middleware('permission:dashboard');
    Route::view('/account', 'account');
    Route::post('/account/password', [AuthController::class, 'password'])->middleware('throttle:6,1');
    Route::get('/business-logo', [AdminController::class, 'logo']);
    Route::get('/appointments', [AppointmentController::class, 'index'])->middleware('can-appointments');
    Route::post('/appointments/{id}/status', [AppointmentController::class, 'status'])->whereNumber('id')->middleware('can-appointments');
    Route::middleware('permission:appointments')->group(function () {
        Route::get('/appointments/create', [AppointmentController::class, 'form']);
        Route::get('/appointments/{id}/edit', [AppointmentController::class, 'form'])->whereNumber('id');
        Route::post('/appointments', [AppointmentController::class, 'save']);
        Route::put('/appointments/{id}', [AppointmentController::class, 'save'])->whereNumber('id');
    });
    Route::middleware('permission:sales')->group(function () {
        Route::get('/sales', [SaleController::class, 'index']);
        Route::get('/sales/{id}', [SaleController::class, 'show'])->whereNumber('id');
        Route::get('/sales/{id}/logo', [SaleController::class, 'logo'])->whereNumber('id');
    });
    Route::get('/checkout', [SaleController::class, 'create'])->middleware('permission:checkout');
    Route::post('/checkout', [SaleController::class, 'store'])->middleware(['permission:checkout', 'throttle:30,1']);
    Route::post('/sales/{id}/refund', [SaleController::class, 'refund'])->middleware('permission:refunds')->whereNumber('id');
    Route::get('/stock', [StockController::class, 'index'])->middleware('permission:stock');
    Route::post('/stock', [StockController::class, 'store'])->middleware('permission:stock');
    Route::get('/reports', [ReportController::class, 'index'])->middleware('permission:reports');
    Route::get('/settings', [AdminController::class, 'settings'])->middleware('permission:settings');
    Route::post('/settings', [AdminController::class, 'saveSettings'])->middleware('permission:settings');
    Route::get('/users', [AdminController::class, 'users'])->middleware('permission:users');
    Route::post('/users', [AdminController::class, 'saveUser'])->middleware('permission:users');
    Route::put('/users/{id}', [AdminController::class, 'saveUser'])->middleware('permission:users')->whereNumber('id');
    Route::get('/audit', [AdminController::class, 'audit'])->middleware('permission:audit');
    Route::get('/customers/{id}', [ProfileController::class, 'customer'])->whereNumber('id')->middleware('permission:customers');
    Route::get('/pets/{id}', [ProfileController::class, 'pet'])->whereNumber('id')->middleware('permission:pets');
    foreach (array_keys(Catalog::definitions()) as $module) {
        Route::middleware('permission:'.$module)->group(function () use ($module) {
            Route::get('/'.$module, [CatalogController::class, 'index'])->defaults('module', $module);
            Route::get('/'.$module.'/create', [CatalogController::class, 'form'])->defaults('module', $module);
            Route::get('/'.$module.'/{id}/edit', [CatalogController::class, 'form'])->defaults('module', $module)->whereNumber('id');
            Route::post('/'.$module, [CatalogController::class, 'save'])->defaults('module', $module);
            Route::put('/'.$module.'/{id}', [CatalogController::class, 'save'])->defaults('module', $module)->whereNumber('id');
            Route::post('/'.$module.'/{id}/archive',[CatalogController::class, 'archive'])->defaults('module',$module)->whereNumber('id');
        });
    }
});
