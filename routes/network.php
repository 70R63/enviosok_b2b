<?php

use App\Http\Controllers\Network\LocalShippingController;
use App\Http\Controllers\Network\ModuleController;
use App\Http\Controllers\Network\NetworkAuthController;
use App\Http\Controllers\Network\NetworkTwoFactorController;
use App\Http\Controllers\Network\NetworkOnboardingController;
use App\Http\Controllers\Network\NetworkDashboardController;
use App\Http\Controllers\Network\NetworkLaunchpadController;
use App\Http\Controllers\Network\NetworkTopologyController;
use App\Http\Controllers\Network\PlanController;
use App\Http\Controllers\Network\SubscriptionController;
use App\Http\Controllers\Network\TenantBrandingController;
use App\Http\Controllers\Network\TenantController;
use App\Http\Controllers\Network\TenantDomainController;
use App\Http\Controllers\Network\TenantMembershipController as NetworkTenantMembershipController;
use App\Http\Controllers\Tenant\DriverAuthController;
use App\Http\Controllers\Tenant\DriverConsoleController;
use App\Http\Controllers\Tenant\TenantAdminController;
use App\Http\Controllers\Tenant\TenantAuthController;
use App\Http\Controllers\Tenant\OwnerActivationController;
use App\Http\Controllers\Tenant\TenantSetupController;
use App\Http\Controllers\Tenant\TenantB2cController;
use App\Http\Controllers\Tenant\TenantConfigurationController;
use App\Http\Controllers\Tenant\TenantDeliveryProofOptionController;
use App\Http\Controllers\Tenant\TenantDriverController;
use App\Http\Controllers\Tenant\TenantHomeController;
use App\Http\Controllers\Tenant\TenantMemberController;
use App\Http\Controllers\Tenant\TenantOperationController;
use App\Http\Controllers\Tenant\CustomerAuthController;
use App\Http\Controllers\Tenant\CustomerPortalController;
use App\Http\Controllers\Tenant\CustomerJourneyController;
use App\Http\Controllers\DeliveryEvidenceController;
use App\Http\Controllers\Driver\CentralDriverAuthController;
use App\Http\Controllers\Driver\DriverPwaController;
use App\Http\Controllers\Driver\DriverWorkspaceController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Tenant\TenantPaymentConnectionController;
use App\Http\Controllers\Tenant\CustomerPaymentController;
use App\Http\Controllers\Network\NetworkPaymentController;
use App\Http\Controllers\Network\NetworkSaasInvoiceController;
use App\Http\Controllers\Payments\PaymentEdgeHealthController;
use App\Http\Controllers\Tenant\TenantSaasController;
use App\Http\Controllers\Tenant\TenantSaasInvoiceController;
use App\Http\Controllers\Network\CommercialCatalogController;
use App\Http\Controllers\Network\NetworkApiHubController;
use App\Http\Controllers\Network\NetworkOperationsController;
use App\Http\Controllers\Tenant\TenantApiHubController;
use App\Http\Controllers\Support\{CustomerSupportController,DriverSupportController,TenantSupportController,NetworkSupportController};

Route::domain(config('zigo_driver.host'))->prefix('driver')->name('driver.')->group(function (): void {
    Route::get('/manifest.webmanifest', [DriverPwaController::class, 'manifest'])->name('manifest');
    Route::get('/offline', [DriverPwaController::class, 'offline'])->name('offline');
    Route::get('/service-worker.js', [DriverPwaController::class, 'serviceWorker'])->name('service-worker');
    Route::get('/login', [CentralDriverAuthController::class, 'create'])->name('login');
    Route::post('/login', [CentralDriverAuthController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
    Route::middleware('auth')->group(function (): void {
        Route::get('/workspaces', [DriverWorkspaceController::class, 'index'])->name('workspaces.index');
        Route::post('/workspaces', [DriverWorkspaceController::class, 'select'])->name('workspaces.select');
        Route::post('/logout', [CentralDriverAuthController::class, 'destroy'])->name('logout');
        Route::middleware(['driver.central.context', 'driver.private'])->group(function (): void {
            Route::get('/', [DriverConsoleController::class, 'index'])->name('dashboard');
            Route::get('/deliveries', [DriverConsoleController::class, 'deliveries'])->name('deliveries');
            Route::get('/earnings', [DriverConsoleController::class, 'earnings'])->name('earnings');
            Route::get('/profile', [DriverConsoleController::class, 'profile'])->name('profile');
            Route::get('/support', [DriverSupportController::class, 'index'])->name('support');
            Route::post('/support/tickets', [DriverSupportController::class, 'store'])->name('support.store');
            Route::get('/support/tickets/{ticket}', [DriverSupportController::class, 'show'])->name('support.show');
            Route::post('/support/tickets/{ticket}/respuestas', [DriverSupportController::class, 'reply'])->name('support.reply');
            Route::get('/support/tickets/{ticket}/adjuntos/{attachment}', [DriverSupportController::class, 'attachment'])->name('support.attachment');
            Route::post('/availability', [DriverConsoleController::class, 'availability'])->name('availability');
            Route::get('/shipments/{shipment}', [DriverConsoleController::class, 'show'])->name('shipments.show');
            Route::post('/shipments/{shipment}/transition', [DriverConsoleController::class, 'transition'])->name('shipments.transition');
            Route::get('/shipments/{shipment}/proof', [DriverConsoleController::class, 'proofForm'])->name('shipments.proof');
            Route::post('/shipments/{shipment}/proof', [DriverConsoleController::class, 'storeProof'])->name('shipments.proof.store');
            Route::get('/shipments/{shipment}/failure', [DriverConsoleController::class, 'failureForm'])->name('shipments.failure');
            Route::post('/shipments/{shipment}/failure', [DriverConsoleController::class, 'storeFailure'])->name('shipments.failure.store');
        });
    });
});

Route::domain(config('zigo_surfaces.payments.host'))->group(function (): void {
    Route::get('/payments/health', PaymentEdgeHealthController::class)
        ->middleware('payments.edge.headers')->name('payments.health');
    Route::get('/payments/mercado-pago/oauth/callback', [TenantPaymentConnectionController::class, 'callback'])
        ->middleware(['throttle:10,1', 'payments.edge.headers'])->name('payments.mercado-pago.oauth.callback');
});

Route::middleware('zigo.surface.host:network')->prefix('network')->name('network.')->group(function (): void {
    Route::get('/login', [NetworkAuthController::class, 'create'])->name('login');
    Route::post('/login', [NetworkAuthController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
    Route::get('/security/two-factor/enroll', [NetworkTwoFactorController::class, 'enrollment'])->middleware('throttle:10,1')->name('two-factor.enroll');
    Route::post('/security/two-factor/enroll', [NetworkTwoFactorController::class, 'confirmEnrollment'])->middleware('throttle:5,1')->name('two-factor.enroll.confirm');
    Route::get('/security/two-factor/challenge', [NetworkTwoFactorController::class, 'challenge'])->middleware('throttle:10,1')->name('two-factor.challenge');
    Route::post('/security/two-factor/challenge', [NetworkTwoFactorController::class, 'verify'])->middleware('throttle:5,1')->name('two-factor.verify');
});

Route::middleware(['zigo.surface.host:network', 'network.auth', 'network.superadmin', 'network.two-factor'])
    ->prefix('network')
    ->name('network.')
    ->group(function (): void {
        Route::get('/', NetworkLaunchpadController::class)->name('launchpad');
        Route::post('/logout', [NetworkAuthController::class, 'destroy'])->name('logout');
        Route::post('/security/two-factor/recovery-codes', [NetworkTwoFactorController::class, 'regenerate'])->middleware('throttle:2,1')->name('two-factor.recovery.regenerate');
        Route::delete('/security/users/{user}/two-factor', [NetworkTwoFactorController::class, 'reset'])->middleware('throttle:3,1')->name('two-factor.reset');
        Route::get('/topology', NetworkTopologyController::class)->name('topology');
        Route::get('/dashboard', NetworkDashboardController::class)->name('dashboard');
        Route::get('/onboarding', [NetworkOnboardingController::class, 'index'])->name('onboarding.index');
        Route::get('/onboarding/{uuid}', [NetworkOnboardingController::class, 'show'])->name('onboarding.show');
        Route::post('/onboarding/{uuid}/retry', [NetworkOnboardingController::class, 'retry'])
            ->middleware('throttle:10,1')->name('onboarding.retry');
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
        Route::get('/tenants/{tenant}/users', [NetworkOperationsController::class, 'tenantUsers'])->name('tenants.users.index');
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
        Route::delete('/plans/{plan}', [PlanController::class, 'destroy'])->name('plans.destroy');
        Route::get('/users', [NetworkOperationsController::class, 'users'])->name('users.index');
        Route::patch('/tenants/{tenant}/users/{membership}', [NetworkOperationsController::class, 'membership'])->name('users.update');
        Route::post('/tenants/{tenant}/users/{membership}/reset-password', [NetworkOperationsController::class, 'reset'])->name('users.reset');
        Route::post('/tenants/{tenant}/users/{membership}/resend-activation', [NetworkOperationsController::class, 'activation'])->name('users.activation');
        Route::get('/subscriptions', [NetworkOperationsController::class, 'subscriptions'])->name('subscriptions.index');
        Route::get('/subscriptions/{subscription}', [NetworkOperationsController::class, 'subscription'])->name('subscriptions.show');
        Route::patch('/subscriptions/{subscription}/status', [NetworkOperationsController::class, 'subscriptionStatus'])->name('subscriptions.status');
        Route::post('/subscriptions/{subscription}/reminder', [NetworkOperationsController::class, 'subscriptionReminder'])->name('subscriptions.reminder');
        Route::post('/subscriptions/{subscription}/renewal', [NetworkOperationsController::class, 'subscriptionRenewal'])->name('subscriptions.renewal');
        Route::post('/subscriptions/{subscription}/suspend-expired', [NetworkOperationsController::class, 'subscriptionSuspend'])->name('subscriptions.suspend-expired');
        Route::post('/tenants/{tenant}/archive', [NetworkOperationsController::class, 'tenantArchive'])->name('tenants.archive');
        Route::delete('/tenants/{tenant}', [NetworkOperationsController::class, 'tenantDelete'])->name('tenants.destroy');
        Route::get('/local-shipping', [LocalShippingController::class, 'index'])->name('local-shipping.index');
        Route::get('/payments', [NetworkOperationsController::class, 'payments'])->name('payments.index');
        Route::get('/payments/{attempt}', [NetworkOperationsController::class, 'payment'])->name('payments.show');
        Route::post('/saas-invoices/{invoice}/issue', [NetworkSaasInvoiceController::class, 'issue'])->name('saas-invoices.issue');
        Route::get('/commercial-products', [CommercialCatalogController::class, 'index'])->name('commercial-products.index');
        Route::get('/catalog', [CommercialCatalogController::class, 'index'])->name('catalog.index');
        Route::post('/catalog', [CommercialCatalogController::class, 'store'])->name('catalog.store');
        Route::put('/catalog/{product}', [CommercialCatalogController::class, 'update'])->name('catalog.update');
        Route::get('/saas-orders', [CommercialCatalogController::class, 'orders'])->name('saas-orders.index');
        Route::get('/support', [NetworkSupportController::class, 'index'])->name('support.index');
        Route::post('/support', [NetworkSupportController::class, 'store'])->name('support.store');
        Route::get('/support/{ticket}', [NetworkSupportController::class, 'show'])->name('support.show');
        Route::post('/support/{ticket}/respuestas', [NetworkSupportController::class, 'reply'])->name('support.reply');
        Route::get('/api-hub', NetworkApiHubController::class)->name('api-hub.index');
        Route::get('/audit', [NetworkOperationsController::class, 'audit'])->name('audit.index');
        Route::patch('/support/{ticket}', [NetworkSupportController::class, 'update'])->name('support.update');
        Route::get('/support/{ticket}/adjuntos/{attachment}', [NetworkSupportController::class, 'attachment'])->name('support.attachment');
        Route::get('/delivery-proofs/{proof}/{kind}', [DeliveryEvidenceController::class, 'network'])->name('delivery-proofs.evidence');
        Route::post('/local-shipping/zones', [LocalShippingController::class, 'storeZone'])->name('local-shipping.zones.store');
        Route::post('/local-shipping/zones/{zone}/postal-codes', [LocalShippingController::class, 'storePostalCode'])->name('local-shipping.postal-codes.store');
        Route::patch('/local-shipping/zones/{zone}/toggle', [LocalShippingController::class, 'toggleZone'])->name('local-shipping.zones.toggle');
        Route::post('/local-shipping/services', [LocalShippingController::class, 'storeService'])->name('local-shipping.services.store');
        Route::patch('/local-shipping/services/{service}/toggle', [LocalShippingController::class, 'toggleService'])->name('local-shipping.services.toggle');
    });

Route::get('/', [TenantHomeController::class, 'home'])->middleware(['tenant.resolve', 'tenant.subscription', 'tenant.entitlement:B2C'])->name('tenant.customer.landing');
Route::get('/white-label', [TenantHomeController::class, 'home'])->middleware(['tenant.resolve', 'tenant.subscription'])->name('tenant.home');

Route::middleware(['tenant.resolve', 'tenant.subscription', 'tenant.entitlement:B2C'])->name('tenant.customer.')->group(function (): void {
    Route::get('/ingresar', [CustomerAuthController::class, 'create'])->name('login');
    Route::post('/ingresar', [CustomerAuthController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
    Route::get('/registro', [CustomerAuthController::class, 'registration'])->name('register');
    Route::post('/registro', [CustomerAuthController::class, 'register'])->middleware('throttle:5,1')->name('register.store');
    Route::post('/salir', [CustomerAuthController::class, 'destroy'])->name('logout');
    Route::post('/cotizar/seleccionar', [TenantB2cController::class, 'select'])->name('quote.select');
    Route::get('/rastreo', [TenantB2cController::class, 'tracking'])->middleware('tenant.entitlement:TRACKING')->name('tracking');
    Route::get('/rastreo/{tracking}', [TenantB2cController::class, 'track'])->middleware('tenant.entitlement:TRACKING')->name('tracking.show');
    Route::middleware('tenant.customer')->prefix('app')->name('app.')->group(function (): void {
        Route::get('/', [CustomerPortalController::class, 'dashboard'])->name('dashboard');
        Route::get('/cotizar', [CustomerPortalController::class, 'quote'])->middleware('tenant.entitlement:SHIPPING')->name('quote');
        Route::post('/cotizar', [TenantB2cController::class, 'store'])->middleware(['tenant.entitlement:SHIPPING', 'throttle:20,1'])->name('quote.store');
        Route::post('/cotizar/seleccionar', [TenantB2cController::class, 'select'])->name('quote.select');
        Route::get('/envios', [CustomerPortalController::class, 'index'])->name('shipments');
        Route::get('/envios/{shipment}', [CustomerPortalController::class, 'show'])->name('shipments.show');
        Route::get('/envios/{shipment}/guia.pdf', [CustomerPortalController::class, 'guide'])->name('shipments.guide');
        Route::get('/perfil', [CustomerPortalController::class, 'profile'])->name('profile');
        Route::patch('/perfil', [CustomerPortalController::class, 'updateProfile'])->name('profile.update');
        Route::get('/ayuda', [CustomerPortalController::class, 'support'])->name('support');
        Route::get('/ayuda/tickets', [CustomerSupportController::class, 'index'])->name('support.tickets');
        Route::get('/ayuda/tickets/nuevo', [CustomerSupportController::class, 'create'])->name('support.create');
        Route::post('/ayuda/tickets', [CustomerSupportController::class, 'store'])->name('support.store');
        Route::get('/ayuda/tickets/{ticket}', [CustomerSupportController::class, 'show'])->name('support.show');
        Route::post('/ayuda/tickets/{ticket}/respuestas', [CustomerSupportController::class, 'reply'])->name('support.reply');
        Route::get('/ayuda/tickets/{ticket}/adjuntos/{attachment}', [CustomerSupportController::class, 'attachment'])->name('support.attachment');
        Route::get('/evidencia', [CustomerPortalController::class, 'proofOptions'])->name('evidence');
        Route::get('/envio/nuevo', [CustomerJourneyController::class, 'shipping'])->name('journey.shipping');
        Route::post('/envio/nuevo', [CustomerJourneyController::class, 'storeShipping'])->name('journey.shipping.store');
        Route::get('/envio/evidencia', [CustomerJourneyController::class, 'evidence'])->name('journey.evidence');
        Route::post('/envio/evidencia', [CustomerJourneyController::class, 'storeEvidence'])->name('journey.evidence.store');
        Route::get('/checkout/{checkout}/resumen', [CustomerJourneyController::class, 'summary'])->name('checkout.summary');
        Route::post('/checkout/{checkout}/continuar', [CustomerJourneyController::class, 'continuePayment'])->name('checkout.continue');
        Route::get('/checkout/{checkout}/pago', [CustomerJourneyController::class, 'payment'])->name('checkout.payment');
        Route::post('/checkout/{checkout}/pago/mercado-pago', [CustomerPaymentController::class, 'create'])->middleware('throttle:6,1')->name('checkout.mercado-pago.create');
        Route::get('/checkout/{checkout}/pago/retorno/{result}', [CustomerPaymentController::class, 'returned'])->middleware('throttle:20,1')->name('checkout.mercado-pago.return');
        Route::post('/envios/{shipment}/recoleccion', [CustomerJourneyController::class, 'pickup'])->name('shipments.pickup');
    });
});

Route::middleware(['tenant.resolve', 'tenant.subscription', 'tenant.entitlement:B2C'])->name('tenant.b2c.')->group(function (): void {
    Route::get('/cotizar', [TenantB2cController::class, 'create'])->middleware('tenant.entitlement:SHIPPING')->name('quote.create');
    Route::post('/cotizar', [TenantB2cController::class, 'store'])->middleware(['tenant.entitlement:SHIPPING', 'throttle:20,1'])->name('quote.store');
    Route::get('/tracking', [TenantB2cController::class, 'tracking'])->middleware('tenant.entitlement:TRACKING')->name('tracking');
    Route::get('/tracking/{tracking}', [TenantB2cController::class, 'track'])->middleware('tenant.entitlement:TRACKING')->name('tracking.show');
});

Route::middleware('tenant.resolve')->prefix('admin')->name('tenant.admin.')->group(function (): void {
    Route::get('/login', [TenantAuthController::class, 'create'])->name('login');
    Route::post('/login', [TenantAuthController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
    Route::get('/activate/{applicationToken}/{token}', [OwnerActivationController::class, 'create'])
        ->middleware('throttle:10,1')->name('activation.create');
    Route::post('/activate/{applicationToken}', [OwnerActivationController::class, 'store'])
        ->middleware('throttle:10,1')->name('activation.store');
    Route::middleware('tenant.auth')->group(function (): void {
        Route::post('/logout', [TenantAuthController::class, 'destroy'])->name('logout');
        Route::middleware(['tenant.subscription', 'tenant.admin.access'])->group(function (): void {
            Route::get('/', [TenantAdminController::class, 'dashboard'])->name('dashboard');
            Route::get('/setup', [TenantSetupController::class, 'show'])->name('setup');
            Route::post('/setup', [TenantSetupController::class, 'store'])->name('setup.store');
            Route::get('/plan', [TenantAdminController::class, 'plan'])->name('plan');
            Route::get('/marketplace', [TenantSaasController::class, 'marketplace'])->name('marketplace');
            Route::post('/marketplace/contratar', [TenantSaasController::class, 'purchase'])->middleware('throttle:8,1')->name('marketplace.purchase');
            Route::get('/compras', [TenantSaasController::class, 'purchases'])->name('purchases');
            Route::get('/facturacion', [TenantSaasInvoiceController::class, 'index'])->name('billing.index');
            Route::put('/facturacion/datos-fiscales', [TenantSaasInvoiceController::class, 'profile'])->name('billing.profile');
            Route::post('/facturacion/compras/{order}/solicitar', [TenantSaasInvoiceController::class, 'request'])->name('billing.request');
            Route::get('/facturacion/facturas/{invoice}/{format}', [TenantSaasInvoiceController::class, 'download'])->name('billing.download');
            Route::get('/compras/{order}/pago', [TenantSaasController::class, 'payment'])->name('saas.payment');
            Route::post('/compras/{order}/pago', [TenantSaasController::class, 'checkout'])->middleware('throttle:6,1')->name('saas.checkout');
            Route::get('/compras/{order}/retorno/{result}', [TenantSaasController::class, 'returned'])->middleware('throttle:20,1')->name('saas.return');
            Route::get('/api', [TenantApiHubController::class, 'index'])->name('api-hub.index');
            Route::post('/api/clients', [TenantApiHubController::class, 'client'])->name('api-hub.clients.store');
            Route::post('/api/clients/{client}/keys', [TenantApiHubController::class, 'key'])->name('api-hub.keys.store');
            Route::delete('/api/clients/{client}/keys/{key}', [TenantApiHubController::class, 'revoke'])->name('api-hub.keys.revoke');
            Route::post('/api/clients/{client}/webhooks', [TenantApiHubController::class, 'webhook'])->name('api-hub.webhooks.store');
            Route::post('/api/webhooks/{webhook}/rotate', [TenantApiHubController::class, 'rotateWebhook'])->name('api-hub.webhooks.rotate');
            Route::patch('/api/webhooks/{webhook}', [TenantApiHubController::class, 'webhookStatus'])->name('api-hub.webhooks.update');
            Route::middleware('tenant.support.staff')->group(function (): void {
                Route::get('/soporte', [TenantSupportController::class, 'index'])->name('support.index');
                Route::get('/soporte/zigo', [TenantSupportController::class, 'zigo'])->name('support.zigo');
                Route::post('/soporte/zigo', [TenantSupportController::class, 'storeZigo'])->name('support.zigo.store');
                Route::get('/soporte/{ticket}', [TenantSupportController::class, 'show'])->name('support.show');
                Route::post('/soporte/{ticket}/respuestas', [TenantSupportController::class, 'reply'])->name('support.reply');
                Route::patch('/soporte/{ticket}', [TenantSupportController::class, 'update'])->name('support.update');
                Route::get('/soporte/{ticket}/adjuntos/{attachment}', [TenantSupportController::class, 'attachment'])->name('support.attachment');
            });
            Route::get('/configuracion', [TenantConfigurationController::class, 'edit'])->name('configuration.edit');
            Route::patch('/configuracion', [TenantConfigurationController::class, 'update'])->name('configuration.update');
            Route::get('/configuracion/entregas', [TenantDeliveryProofOptionController::class, 'index'])->name('delivery-proof-options.index');
            Route::get('/configuracion/pagos', [TenantPaymentConnectionController::class, 'index'])->name('payments.index');
            Route::get('/configuracion/pagos/mercado-pago/conectar', [TenantPaymentConnectionController::class, 'connect'])->middleware('throttle:5,1')->name('payments.mercado-pago.connect');
            Route::delete('/configuracion/pagos/mercado-pago', [TenantPaymentConnectionController::class, 'disconnect'])->middleware('throttle:5,1')->name('payments.mercado-pago.disconnect');
            Route::post('/configuracion/entregas', [TenantDeliveryProofOptionController::class, 'store'])->name('delivery-proof-options.store');
            Route::put('/configuracion/entregas/{option}', [TenantDeliveryProofOptionController::class, 'update'])->name('delivery-proof-options.update');
            Route::get('/operations', [TenantOperationController::class, 'index'])->middleware('tenant.entitlement:B2C')->name('operations.index');
            Route::post('/operations/{operation}/confirm', [TenantOperationController::class, 'confirm'])->middleware(['tenant.entitlement:B2C', 'tenant.entitlement:SHIPPING'])->name('operations.confirm');
            Route::get('/operations/{operation}', [TenantOperationController::class, 'show'])->middleware('tenant.entitlement:SHIPPING')->name('operations.show');
            Route::get('/delivery-proofs/{proof}/{kind}', [DeliveryEvidenceController::class, 'tenant'])->name('delivery-proofs.evidence');
            Route::get('/delivery-failures/{attempt}/photo', [DeliveryEvidenceController::class, 'tenantFailed'])->name('delivery-failures.photo');
            Route::post('/operations/{operation}/local-shipment', [TenantOperationController::class, 'shipment'])->middleware('tenant.entitlement:SHIPPING')->name('operations.shipment');
            Route::get('/operations/{operation}/guide.pdf', [TenantOperationController::class, 'guide'])->middleware('tenant.entitlement:SHIPPING')->name('operations.guide');
            Route::post('/operations/{operation}/pickup-request', [TenantOperationController::class, 'requestPickup'])->middleware('tenant.entitlement:SHIPPING')->name('operations.pickup-request');
            Route::middleware('tenant.entitlement:DRIVER')->group(function (): void {
                Route::get('/drivers', [TenantDriverController::class, 'index'])->name('drivers.index');
                Route::get('/dispatch/pickups', [TenantDriverController::class, 'dispatchQueue'])->name('dispatch.pickups');
                Route::post('/drivers', [TenantDriverController::class, 'store'])->name('drivers.store');
                Route::get('/drivers/{driver}', [TenantDriverController::class, 'show'])->name('drivers.show');
                Route::patch('/drivers/{driver}/toggle', [TenantDriverController::class, 'toggle'])->name('drivers.toggle');
                Route::put('/drivers/{driver}/compensation', [TenantDriverController::class, 'updateCompensation'])->name('drivers.compensation.update');
                Route::post('/local-shipments/{shipment}/driver', [TenantDriverController::class, 'assign'])->name('drivers.assign');
                Route::post('/local-shipments/{shipment}/transition', [TenantDriverController::class, 'transition'])->name('drivers.transition');
            });
            Route::middleware('tenant.members.manage')->group(function (): void {
                Route::get('/users', [TenantMemberController::class, 'index'])->name('users.index');
                Route::patch('/users/{membership}', [TenantMemberController::class, 'update'])->name('users.update');
            });
        });
    });
});

Route::middleware('tenant.resolve')->prefix('driver')->name('tenant.driver.')->group(function (): void {
    Route::get('/login', [DriverAuthController::class, 'create'])->name('login');
    Route::post('/login', [DriverAuthController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
    Route::middleware(['tenant.driver', 'tenant.subscription', 'tenant.entitlement:DRIVER', 'driver.private'])->group(function (): void {
        Route::post('/logout', [DriverAuthController::class, 'destroy'])->name('logout');
        Route::get('/', [DriverConsoleController::class, 'index'])->name('dashboard');
        Route::get('/deliveries', [DriverConsoleController::class, 'deliveries'])->name('deliveries');
        Route::get('/earnings', [DriverConsoleController::class, 'earnings'])->name('earnings');
        Route::get('/profile', [DriverConsoleController::class, 'profile'])->name('profile');
        Route::get('/support', [DriverSupportController::class, 'index'])->name('support');
        Route::post('/support/tickets', [DriverSupportController::class, 'store'])->name('support.store');
        Route::get('/support/tickets/{ticket}', [DriverSupportController::class, 'show'])->name('support.show');
        Route::post('/support/tickets/{ticket}/respuestas', [DriverSupportController::class, 'reply'])->name('support.reply');
        Route::get('/support/tickets/{ticket}/adjuntos/{attachment}', [DriverSupportController::class, 'attachment'])->name('support.attachment');
        Route::post('/availability', [DriverConsoleController::class, 'availability'])->name('availability');
        Route::get('/shipments/{shipment}', [DriverConsoleController::class, 'show'])->name('shipments.show');
        Route::post('/shipments/{shipment}/transition', [DriverConsoleController::class, 'transition'])->name('shipments.transition');
        Route::get('/shipments/{shipment}/proof', [DriverConsoleController::class, 'proofForm'])->name('shipments.proof');
        Route::post('/shipments/{shipment}/proof', [DriverConsoleController::class, 'storeProof'])->name('shipments.proof.store');
        Route::get('/shipments/{shipment}/failure', [DriverConsoleController::class, 'failureForm'])->name('shipments.failure');
        Route::post('/shipments/{shipment}/failure', [DriverConsoleController::class, 'storeFailure'])->name('shipments.failure.store');
    });
});
