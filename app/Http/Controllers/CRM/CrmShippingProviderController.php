<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\B2cCotizacion;
use App\Services\Shipping\Xperta\XpertaQuoteService;
use App\Services\Shipping\Xperta\XpertaTokenService;
use Illuminate\Http\Request;
use Throwable;

class CrmShippingProviderController extends Controller
{
    public function index()
    {
        $status = [
            'active_provider' => config(
                'services.shipping.provider',
                'legacy_estafeta'
            ),
            'xperta_enabled' => (bool) config(
                'services.xperta.enabled',
                false
            ),
            'environment' => config(
                'services.xperta.environment',
                'sandbox'
            ),
            'services' => config('services.xperta.services', []),
            'configured' => [
                'base_url' => filled(
                    config('services.xperta.base_url')
                ),
                'empresa' => filled(
                    config('services.xperta.empresa')
                ),
                'ltd' => filled(
                    config('services.xperta.ltd')
                ),
                'corporativo' => filled(
                    config('services.xperta.corporativo')
                ),
                'email' => filled(
                    config('services.xperta.email')
                ),
                'password' => filled(
                    config('services.xperta.password')
                ),
                'api_key' => filled(
                    config('services.xperta.api_key')
                ),
            ],
        ];

        return view('crm.shipping.index', compact('status'));
    }

    public function testToken(
        XpertaTokenService $tokenService
    ) {
        try {
            $token = $tokenService->encodedToken(true);

            return back()->with(
                'success',
                'Token Xperta generado correctamente. '
                . 'Longitud codificada: '
                . strlen($token)
                . ' caracteres.'
            );
        } catch (Throwable $exception) {
            return back()->with(
                'error',
                $exception->getMessage()
            );
        }
    }

    public function testQuote(
        Request $request,
        XpertaQuoteService $quoteService
    ) {
        $data = $request->validate([
            'cp_origen' => ['required', 'digits:5'],
            'cp_destino' => ['required', 'digits:5'],
            'peso' => ['required', 'numeric', 'min:0.1'],
            'largo' => ['required', 'numeric', 'min:0.1'],
            'ancho' => ['required', 'numeric', 'min:0.1'],
            'alto' => ['required', 'numeric', 'min:0.1'],
            'servicio' => ['required', 'string', 'max:50'],
            'valor_declarado' => ['nullable', 'numeric', 'min:0'],
        ]);

        $cotizacion = new B2cCotizacion([
            'cp_origen' => $data['cp_origen'],
            'cp_destino' => $data['cp_destino'],
            'tipo_envio' => 'caja',
            'peso' => (float) $data['peso'],
            'peso_real' => (float) $data['peso'],
            'medidas' => implode('x', [
                $data['largo'],
                $data['ancho'],
                $data['alto'],
            ]),
            'valor_declarado' => (float) (
                $data['valor_declarado'] ?? 0
            ),
        ]);

        try {
            $result = $quoteService->quote(
                $cotizacion,
                $data['servicio']
            );

            return back()
                ->with('success', 'Cotización Xperta exitosa.')
                ->with('xperta_quote_result', $result);
        } catch (Throwable $exception) {
            return back()
                ->withInput()
                ->with('error', $exception->getMessage());
        }
    }
}
