<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/b2c/dashboard';
    protected $namespace = 'App\Http\Controllers';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/web.php'));

            Route::middleware('web')
                ->group(base_path('routes/network.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
        foreach (['start'=>'session_creations_per_minute','message'=>'messages_per_minute','confirm'=>'confirmations_per_minute','poll'=>'polls_per_minute'] as $operation=>$setting) {
            RateLimiter::for('ai-webchat-'.$operation, fn (Request $request) => Limit::perMinute((int) config('ai.webchat.'.$setting))
                ->by(hash('sha256',(string)$request->route('publicKey').'|'.$request->ip().'|'.($operation==='start'?'':(string)$request->bearerToken()))));
        }

        RateLimiter::for('onboarding-checkout', function (Request $request) {
            return Limit::perMinute(10)
                ->by(hash('sha256', (string) $request->route('token').'|'.$request->ip()))
                ->response(function (Request $request, array $headers) {
                    return redirect()->route('zigo-platform.onboarding.summary', [
                        'token' => (string) $request->route('token'),
                    ])->withErrors([
                        'payment' => 'Has realizado varios intentos de pago. Espera un momento e inténtalo nuevamente.',
                    ])->withHeaders($headers);
                });
        });

        RateLimiter::for(
            'devops-package-store',
            fn (Request $request) => Limit::perMinute(10)
                ->by($this->devOpsRateLimitKey($request, 'store'))
        );
        RateLimiter::for(
            'devops-package-validate',
            fn (Request $request) => Limit::perMinute(5)
                ->by($this->devOpsRateLimitKey(
                    $request,
                    'validate',
                    true
                ))
        );
        RateLimiter::for(
            'devops-package-deploy',
            fn (Request $request) => Limit::perMinute(2)
                ->by($this->devOpsRateLimitKey(
                    $request,
                    'deploy',
                    true
                ))
        );
        RateLimiter::for(
            'devops-package-rollback',
            fn (Request $request) => Limit::perMinute(2)
                ->by($this->devOpsRateLimitKey(
                    $request,
                    'rollback',
                    true
                ))
        );
        RateLimiter::for(
            'devops-health-run',
            fn (Request $request) => Limit::perMinute(5)
                ->by($this->devOpsRateLimitKey($request, 'health'))
        );
        foreach (['token' => 2, 'frequency' => 5, 'quote' => 3] as $operation => $limit) {
            RateLimiter::for('devops-xperta-' . $operation, fn (Request $request) => Limit::perMinute($limit)
                ->by($this->devOpsRateLimitKey($request, 'xperta-' . $operation)));
        }
    }

    private function devOpsRateLimitKey(
        Request $request,
        string $action,
        bool $includeDeployment = false
    ): string {
        $userId = $request->user()?->getAuthIdentifier() ?? 'guest';
        $key = "{$action}:{$userId}";

        if ($includeDeployment) {
            $deployment = $request->route('deployment');
            $deploymentId = is_object($deployment)
                && method_exists($deployment, 'getKey')
                    ? $deployment->getKey()
                    : $deployment;
            $key .= ':' . ($deploymentId ?? 'unknown');
        }

        return $key;
    }
}
