<?php

use App\Http\Controllers\Network\ModuleController;
use App\Http\Controllers\Network\NetworkAuthController;
use App\Http\Controllers\Network\NetworkDashboardController;
use App\Http\Controllers\Network\NetworkLaunchpadController;
use App\Http\Controllers\Network\NetworkTopologyController;
use App\Http\Controllers\Network\PlanController;
use App\Http\Controllers\Network\TenantController;
use Illuminate\Support\Facades\Route;

Route::prefix('network')->name('network.')->group(function (): void {
    Route::get('/login', [NetworkAuthController::class, 'create'])->name('login');
    Route::post('/login', [NetworkAuthController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
});

Route::middleware(['network.auth', 'network.superadmin'])
    ->prefix('network')
    ->name('network.')
    ->group(function (): void {
        Route::get('/', NetworkLaunchpadController::class)->name('launchpad');
        Route::post('/logout', [NetworkAuthController::class, 'destroy'])->name('logout');
        Route::get('/topology', NetworkTopologyController::class)->name('topology');
        Route::get('/dashboard', NetworkDashboardController::class)->name('dashboard');
        Route::get('/tenants', [TenantController::class, 'index'])->name('tenants.index');
        Route::get('/tenants/create', [TenantController::class, 'create'])->name('tenants.create');
        Route::post('/tenants', [TenantController::class, 'store'])->name('tenants.store');
        Route::get('/tenants/{tenant}', [TenantController::class, 'show'])->name('tenants.show');
        Route::get('/modules', [ModuleController::class, 'index'])->name('modules.index');
        Route::get('/modules/create', [ModuleController::class, 'create'])->name('modules.create');
        Route::post('/modules', [ModuleController::class, 'store'])->name('modules.store');
        Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');
        Route::get('/plans/create', [PlanController::class, 'create'])->name('plans.create');
        Route::post('/plans', [PlanController::class, 'store'])->name('plans.store');
        Route::get('/plans/{plan}', [PlanController::class, 'show'])->name('plans.show');
    });
