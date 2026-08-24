<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The priority-sorted list of middleware.
     *
     * @var string[]
     */
    protected $middlewarePriority = [
        \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
        \Illuminate\Routing\Middleware\ThrottleRequests::class,
        \Illuminate\Routing\Middleware\ThrottleRequestsWithRedis::class,
        \Illuminate\Contracts\Session\Middleware\AuthenticatesSessions::class,
        \App\Http\Middleware\ResolveNetworkTenant::class,
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
        \Illuminate\Auth\Middleware\Authorize::class,
    ];

    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array<int, class-string|string>
     */
    protected $middleware = [
        \App\Http\Middleware\TrustHosts::class,
        \App\Http\Middleware\TrustProxies::class,
        \App\Http\Middleware\IsolateWebchatCors::class,
        \Illuminate\Http\Middleware\HandleCors::class,
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        \App\Http\Middleware\SecurityHeaders::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \App\Http\Middleware\UseZigoPortalSessionCookie::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'api' => [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array<string, class-string|string>
     */
    protected $routeMiddleware = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'auth.session' => \Illuminate\Session\Middleware\AuthenticateSession::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        'roles' => \App\Http\Middleware\RolesMiddleware::class, //MIDDELWARE DE ROLES
        'validaToken'   => \App\Http\Middleware\ValidaToken::class,
        'AccesosApi'   => \App\Http\Middleware\AccesosApi::class,
        'zigo.api' => \App\Http\Middleware\ValidateZigoApiKey::class,
        'zigo.api.v1.request' => \App\Http\Middleware\ApiV1RequestId::class,
        'zigo.api.v1.auth' => \App\Http\Middleware\AuthenticateTenantApiV1::class,
        'zigo.api.v1.scope' => \App\Http\Middleware\RequireTenantApiScope::class,
        'zigo.api.v1.meter' => \App\Http\Middleware\MeterTenantApiV1::class,
        'zigo.product' => \App\Http\Middleware\EnsureApiProductAccess::class,
        'zigo.portal' => \App\Http\Middleware\EnsureZigoPortalHost::class,
        'ensure.zigo.portal' => \App\Http\Middleware\EnsureZigoPortalHost::class,
        'network.superadmin' => \App\Http\Middleware\EnsureNetworkSuperAdmin::class,
        'network.auth' => \App\Http\Middleware\AuthenticateNetwork::class,
        'network.two-factor' => \App\Http\Middleware\EnsureNetworkTwoFactor::class,
        'tenant.resolve' => \App\Http\Middleware\ResolveNetworkTenant::class,
        'tenant.auth' => \App\Http\Middleware\AuthenticateTenant::class,
        'tenant.members.manage' => \App\Http\Middleware\EnsureTenantMemberManager::class,
        'tenant.subscription' => \App\Http\Middleware\EnsureTenantSubscriptionAccess::class,
        'tenant.entitlement' => \App\Http\Middleware\EnsureTenantEntitlement::class,
        'tenant.admin.access' => \App\Http\Middleware\EnsureTenantAdminAccess::class,
        'tenant.workspace.operational' => \App\Http\Middleware\EnsureTenantOperationalWorkspace::class,
        'tenant.support.staff' => \App\Http\Middleware\EnsureTenantSupportStaff::class,
        'tenant.driver' => \App\Http\Middleware\AuthenticateDriver::class,
        'driver.central.context' => \App\Http\Middleware\ResolveCentralDriverContext::class,
        'driver.private' => \App\Http\Middleware\ProtectDriverResponse::class,
        'tenant.customer' => \App\Http\Middleware\AuthenticateTenantCustomer::class,
        'zigo.surface.host' => \App\Http\Middleware\EnsureConfiguredSurfaceHost::class,
        'payments.edge.headers' => \App\Http\Middleware\SecurePaymentEdgeHeaders::class,
        'zigo.corporate.host' => \App\Http\Middleware\EnsureCorporateSurfaceHost::class,
    ];
}
