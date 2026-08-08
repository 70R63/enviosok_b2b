<?php

use App\Http\Controllers\Network\ModuleController;
use App\Http\Controllers\Network\NetworkAuthController;
use App\Http\Controllers\Network\NetworkDashboardController;
use App\Http\Controllers\Network\NetworkLaunchpadController;
use App\Http\Controllers\Network\NetworkTopologyController;
use App\Http\Controllers\Network\PlanController;
use App\Http\Controllers\Network\TenantController;
use App\Http\Controllers\Network\TenantDomainController;
use App\Http\Controllers\Network\TenantBrandingController;
use App\Http\Controllers\Network\TenantMembershipController as NetworkTenantMembershipController;
use App\Http\Controllers\Tenant\TenantHomeController;
use App\Http\Controllers\Tenant\TenantAdminController;
use App\Http\Controllers\Tenant\TenantAuthController;
use App\Http\Controllers\Tenant\TenantMemberController;
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
        Route::get('/tenants/{tenant}/edit', [TenantController::class, 'edit'])->name('tenants.edit');
        Route::match(['put','patch'],'/tenants/{tenant}', [TenantController::class, 'update'])->name('tenants.update');
        Route::get('/tenants/{tenant}/preview', [TenantHomeController::class, 'preview'])->name('tenants.preview');
        Route::get('/tenants/{tenant}/domains', [TenantDomainController::class, 'index'])->name('tenants.domains.index');
        Route::post('/tenants/{tenant}/domains', [TenantDomainController::class, 'store'])->name('tenants.domains.store');
        Route::patch('/tenants/{tenant}/domains/{domain}', [TenantDomainController::class, 'update'])->name('tenants.domains.update');
        Route::delete('/tenants/{tenant}/domains/{domain}', [TenantDomainController::class, 'disable'])->name('tenants.domains.disable');
        Route::get('/tenants/{tenant}/branding', [TenantBrandingController::class, 'edit'])->name('tenants.branding.edit');
        Route::match(['put','patch'],'/tenants/{tenant}/branding', [TenantBrandingController::class, 'update'])->name('tenants.branding.update');
        Route::get('/tenants/{tenant}/members', [NetworkTenantMembershipController::class, 'index'])->name('tenants.members.index');
        Route::post('/tenants/{tenant}/members', [NetworkTenantMembershipController::class, 'store'])->name('tenants.members.store');
        Route::patch('/tenants/{tenant}/members/{membership}', [NetworkTenantMembershipController::class, 'update'])->name('tenants.members.update');
        Route::get('/modules', [ModuleController::class, 'index'])->name('modules.index');
        Route::get('/modules/create', [ModuleController::class, 'create'])->name('modules.create');
        Route::post('/modules', [ModuleController::class, 'store'])->name('modules.store');
        Route::get('/modules/{module}/edit', [ModuleController::class, 'edit'])->name('modules.edit');
        Route::match(['put','patch'],'/modules/{module}', [ModuleController::class, 'update'])->name('modules.update');
        Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');
        Route::get('/plans/create', [PlanController::class, 'create'])->name('plans.create');
        Route::post('/plans', [PlanController::class, 'store'])->name('plans.store');
        Route::get('/plans/{plan}', [PlanController::class, 'show'])->name('plans.show');
        Route::get('/plans/{plan}/edit', [PlanController::class, 'edit'])->name('plans.edit');
        Route::match(['put','patch'],'/plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
    });

Route::get('/white-label', [TenantHomeController::class, 'home'])->middleware('tenant.resolve')->name('tenant.home');

Route::middleware('tenant.resolve')->prefix('admin')->name('tenant.admin.')->group(function (): void {
    Route::get('/login', [TenantAuthController::class, 'create'])->name('login');
    Route::post('/login', [TenantAuthController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
    Route::middleware('tenant.auth')->group(function (): void {
        Route::get('/', [TenantAdminController::class, 'dashboard'])->name('dashboard');
        Route::post('/logout', [TenantAuthController::class, 'destroy'])->name('logout');
        Route::get('/plan', [TenantAdminController::class, 'plan'])->name('plan');
        Route::middleware('tenant.members.manage')->group(function (): void {
            Route::get('/users', [TenantMemberController::class, 'index'])->name('users.index');
            Route::patch('/users/{membership}', [TenantMemberController::class, 'update'])->name('users.update');
        });
    });
});
