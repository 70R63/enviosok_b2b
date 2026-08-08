<?php

use App\Http\Controllers\Network\LocalShippingController;
use App\Http\Controllers\Network\ModuleController;
use App\Http\Controllers\Network\NetworkAuthController;
use App\Http\Controllers\Network\NetworkDashboardController;
use App\Http\Controllers\Network\NetworkLaunchpadController;
use App\Http\Controllers\Network\NetworkTopologyController;
use App\Http\Controllers\Network\PlanController;
use App\Http\Controllers\Network\SubscriptionController;
use App\Http\Controllers\Network\TenantBrandingController;
use App\Http\Controllers\Network\TenantController;
use App\Http\Controllers\Network\TenantDomainController;
use App\Http\Controllers\Network\TenantMembershipController as NetworkTenantMembershipController;
use App\Http\Controllers\Tenant\TenantAdminController;
use App\Http\Controllers\Tenant\TenantAuthController;
use App\Http\Controllers\Tenant\TenantB2cController;
use App\Http\Controllers\Tenant\TenantConfigurationController;
use App\Http\Controllers\Tenant\TenantHomeController;
use App\Http\Controllers\Tenant\TenantMemberController;
use App\Http\Controllers\Tenant\TenantOperationController;
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
        Route::match(['put', 'patch'], '/tenants/{tenant}', [TenantController::class, 'update'])->name('tenants.update');
        Route::get('/tenants/{tenant}/preview', [TenantHomeController::class, 'preview'])->name('tenants.preview');
        Route::get('/tenants/{tenant}/domains', [TenantDomainController::class, 'index'])->name('tenants.domains.index');
        Route::post('/tenants/{tenant}/domains', [TenantDomainController::class, 'store'])->name('tenants.domains.store');
        Route::patch('/tenants/{tenant}/domains/{domain}', [TenantDomainController::class, 'update'])->name('tenants.domains.update');
        Route::delete('/tenants/{tenant}/domains/{domain}', [TenantDomainController::class, 'disable'])->name('tenants.domains.disable');
        Route::get('/tenants/{tenant}/branding', [TenantBrandingController::class, 'edit'])->name('tenants.branding.edit');
        Route::match(['put', 'patch'], '/tenants/{tenant}/branding', [TenantBrandingController::class, 'update'])->name('tenants.branding.update');
        Route::get('/tenants/{tenant}/members', [NetworkTenantMembershipController::class, 'index'])->name('tenants.members.index');
        Route::post('/tenants/{tenant}/members', [NetworkTenantMembershipController::class, 'store'])->name('tenants.members.store');
        Route::patch('/tenants/{tenant}/members/{membership}', [NetworkTenantMembershipController::class, 'update'])->name('tenants.members.update');
        Route::get('/tenants/{tenant}/subscriptions', [SubscriptionController::class, 'index'])->name('tenants.subscriptions.index');
        Route::post('/tenants/{tenant}/subscriptions', [SubscriptionController::class, 'store'])->name('tenants.subscriptions.store');
        Route::patch('/tenants/{tenant}/subscriptions/{subscription}/status', [SubscriptionController::class, 'status'])->name('tenants.subscriptions.status');
        Route::post('/tenants/{tenant}/usage', [SubscriptionController::class, 'usage'])->name('tenants.usage.store');
        Route::get('/modules', [ModuleController::class, 'index'])->name('modules.index');
        Route::get('/modules/create', [ModuleController::class, 'create'])->name('modules.create');
        Route::post('/modules', [ModuleController::class, 'store'])->name('modules.store');
        Route::get('/modules/{module}/edit', [ModuleController::class, 'edit'])->name('modules.edit');
        Route::match(['put', 'patch'], '/modules/{module}', [ModuleController::class, 'update'])->name('modules.update');
        Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');
        Route::get('/plans/create', [PlanController::class, 'create'])->name('plans.create');
        Route::post('/plans', [PlanController::class, 'store'])->name('plans.store');
        Route::get('/plans/{plan}', [PlanController::class, 'show'])->name('plans.show');
        Route::get('/plans/{plan}/edit', [PlanController::class, 'edit'])->name('plans.edit');
        Route::match(['put', 'patch'], '/plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
        Route::get('/local-shipping', [LocalShippingController::class, 'index'])->name('local-shipping.index');
        Route::post('/local-shipping/zones', [LocalShippingController::class, 'storeZone'])->name('local-shipping.zones.store');
        Route::post('/local-shipping/zones/{zone}/postal-codes', [LocalShippingController::class, 'storePostalCode'])->name('local-shipping.postal-codes.store');
        Route::patch('/local-shipping/zones/{zone}/toggle', [LocalShippingController::class, 'toggleZone'])->name('local-shipping.zones.toggle');
        Route::post('/local-shipping/services', [LocalShippingController::class, 'storeService'])->name('local-shipping.services.store');
        Route::patch('/local-shipping/services/{service}/toggle', [LocalShippingController::class, 'toggleService'])->name('local-shipping.services.toggle');
    });

Route::get('/white-label', [TenantHomeController::class, 'home'])->middleware(['tenant.resolve', 'tenant.subscription'])->name('tenant.home');

Route::middleware(['tenant.resolve', 'tenant.subscription', 'tenant.entitlement:B2C'])->name('tenant.b2c.')->group(function (): void {
    Route::get('/cotizar', [TenantB2cController::class, 'create'])->middleware('tenant.entitlement:SHIPPING')->name('quote.create');
    Route::post('/cotizar', [TenantB2cController::class, 'store'])->middleware(['tenant.entitlement:SHIPPING', 'throttle:20,1'])->name('quote.store');
    Route::get('/tracking', [TenantB2cController::class, 'tracking'])->middleware('tenant.entitlement:TRACKING')->name('tracking');
    Route::get('/tracking/{tracking}', [TenantB2cController::class, 'track'])->middleware('tenant.entitlement:TRACKING')->name('tracking.show');
});

Route::middleware('tenant.resolve')->prefix('admin')->name('tenant.admin.')->group(function (): void {
    Route::get('/login', [TenantAuthController::class, 'create'])->name('login');
    Route::post('/login', [TenantAuthController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
    Route::middleware('tenant.auth')->group(function (): void {
        Route::post('/logout', [TenantAuthController::class, 'destroy'])->name('logout');
        Route::middleware('tenant.subscription')->group(function (): void {
            Route::get('/', [TenantAdminController::class, 'dashboard'])->name('dashboard');
            Route::get('/plan', [TenantAdminController::class, 'plan'])->name('plan');
            Route::get('/configuracion', [TenantConfigurationController::class, 'edit'])->name('configuration.edit');
            Route::patch('/configuracion', [TenantConfigurationController::class, 'update'])->name('configuration.update');
            Route::get('/operations', [TenantOperationController::class, 'index'])->middleware('tenant.entitlement:B2C')->name('operations.index');
            Route::post('/operations/{operation}/confirm', [TenantOperationController::class, 'confirm'])->middleware(['tenant.entitlement:B2C', 'tenant.entitlement:SHIPPING'])->name('operations.confirm');
            Route::get('/operations/{operation}', [TenantOperationController::class, 'show'])->middleware('tenant.entitlement:SHIPPING')->name('operations.show');
            Route::post('/operations/{operation}/local-shipment', [TenantOperationController::class, 'shipment'])->middleware('tenant.entitlement:SHIPPING')->name('operations.shipment');
            Route::get('/operations/{operation}/guide.pdf', [TenantOperationController::class, 'guide'])->middleware('tenant.entitlement:SHIPPING')->name('operations.guide');
            Route::middleware('tenant.members.manage')->group(function (): void {
                Route::get('/users', [TenantMemberController::class, 'index'])->name('users.index');
                Route::patch('/users/{membership}', [TenantMemberController::class, 'update'])->name('users.update');
            });
        });
    });
});
