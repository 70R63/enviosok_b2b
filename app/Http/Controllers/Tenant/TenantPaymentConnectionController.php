<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Network\Tenancy\Models\TenantMembership;
use App\Domain\Network\Tenancy\{TenantAccessService, TenantContext};
use App\Domain\Payments\Contracts\PaymentProvider;
use App\Domain\Payments\Exceptions\MercadoPagoOAuthException;
use App\Domain\Payments\Models\TenantPaymentConnection;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class TenantPaymentConnectionController extends Controller
{
    public function index(TenantContext $context, TenantAccessService $access)
    {
        return view('tenant.admin.payments', [
            'tenant' => $context->tenant()->load('branding'),
            'connection' => TenantPaymentConnection::where('tenant_id', $context->id())->where('provider', 'MERCADO_PAGO')->first(),
            'canManage' => $access->canManageTenant(auth()->user()),
            'environment' => config('zigo_payments.providers.mercado_pago.environment'),
        ]);
    }

    public function connect(Request $request, TenantContext $context, TenantAccessService $access, PaymentProvider $provider)
    {
        abort_unless($access->canManageTenant($request->user()), 403);
        abort_unless(config('zigo_payments.providers.mercado_pago.enabled'), 503);
        $store = config('zigo_payments.providers.mercado_pago.oauth_cache_store');
        abort_if(app()->environment(['staging', 'production']) && config('cache.stores.'.$store.'.driver') === 'array', 503);

        $state = Str::random(64);
        $verifier = Str::random(96);
        $this->correlationCache()->put('zigo_mp_oauth:'.hash('sha256', $state), [
            'verifier' => $verifier,
            'tenant_id' => $context->id(),
            'user_id' => $request->user()->id,
            'return_url' => $request->getSchemeAndHttpHost().'/admin/configuracion/pagos',
        ], now()->addMinutes(10));
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return redirect()->away($provider->authorizationUrl($state, $challenge));
    }

    public function callback(Request $request, PaymentProvider $provider)
    {
        $state = (string) $request->query('state');
        $key = 'zigo_mp_oauth:'.hash('sha256', $state);
        $cache = $this->correlationCache();
        $correlation = $cache->lock($key.':consume', 5)->block(2, fn () => $cache->pull($key));
        abort_unless(is_array($correlation) && filled($request->query('code')), 403);
        $membership = TenantMembership::where('tenant_id', $correlation['tenant_id'])
            ->where('user_id', $correlation['user_id'])->where('status', 'active')
            ->whereIn('role', ['owner', 'admin'])->firstOrFail();
        try {
            $tokens = $provider->exchangeAuthorizationCode((string) $request->query('code'), $correlation['verifier']);
        } catch (MercadoPagoOAuthException) {
            return redirect($correlation['return_url'])->with(
                'error',
                'No fue posible conectar Mercado Pago. Intenta autorizar la cuenta nuevamente.'
            );
        }
        $connection = TenantPaymentConnection::firstOrCreate(
            ['tenant_id' => $membership->tenant_id, 'provider' => 'MERCADO_PAGO'],
            ['status' => 'PENDING']
        );
        $provider->storeConnectionTokens($connection, $tokens);

        return redirect($correlation['return_url'])->with('success', 'Mercado Pago quedó conectado.');
    }

    public function disconnect(Request $request, TenantContext $context, TenantAccessService $access)
    {
        abort_unless($access->canManageTenant($request->user()), 403);
        TenantPaymentConnection::where('tenant_id', $context->id())->where('provider', 'MERCADO_PAGO')->update([
            'status' => 'DISCONNECTED', 'access_token' => null, 'refresh_token' => null,
            'token_expires_at' => null, 'disconnected_at' => now(),
        ]);

        return back()->with('success', 'Mercado Pago fue desconectado.');
    }

    private function correlationCache(): Repository
    {
        return Cache::store(config('zigo_payments.providers.mercado_pago.oauth_cache_store'));
    }
}
