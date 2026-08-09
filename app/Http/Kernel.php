<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array<int, class-string|string>
     */
    protected $middleware = [
        // \App\Http\Middleware\TrustHosts::class,
        \App\Http\Middleware\TrustProxies::class,
        \Illuminate\Http\Middleware\HandleCors::class,
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
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
        'zigo.product' => \App\Http\Middleware\EnsureApiProductAccess::class,
        'zigo.portal' => \App\Http\Middleware\EnsureZigoPortalHost::class,
        'ensure.zigo.portal' => \App\Http\Middleware\EnsureZigoPortalHost::class,
        'network.superadmin' => \App\Http\Middleware\EnsureNetworkSuperAdmin::class,
        'network.auth' => \App\Http\Middleware\AuthenticateNetwork::class,
        'tenant.resolve' => \App\Http\Middleware\ResolveNetworkTenant::class,
        'tenant.auth' => \App\Http\Middleware\AuthenticateTenant::class,
        'tenant.members.manage' => \App\Http\Middleware\EnsureTenantMemberManager::class,
        'tenant.subscription' => \App\Http\Middleware\EnsureTenantSubscriptionAccess::class,
        'tenant.entitlement' => \App\Http\Middleware\EnsureTenantEntitlement::class,
        'tenant.admin.access' => \App\Http\Middleware\EnsureTenantAdminAccess::class,
        'tenant.driver' => \App\Http\Middleware\AuthenticateDriver::class,
        'driver.central.context' => \App\Http\Middleware\ResolveCentralDriverContext::class,
        'driver.private' => \App\Http\Middleware\ProtectDriverResponse::class,
    ];
}
