<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Network\Channels\B2C\TenantB2cQuoteService;
use App\Domain\Network\Channels\B2C\Exceptions\TenantQuoteUnavailableException;
use App\Domain\Network\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

final class TenantB2cController extends Controller
{
    public function create(TenantContext $context)
    {
        return view('tenant.b2c.quote', ['tenant' => $context->tenant()->load('branding'), 'result' => null]);
    }

    public function store(Request $request, TenantContext $context, TenantB2cQuoteService $quotes)
    {
        $data = $request->validate([
            'cp_origen' => ['required', 'regex:/^\d{5}$/'], 'cp_destino' => ['required', 'regex:/^\d{5}$/'],
            'tipo_envio' => ['required', 'in:sobre,caja'], 'peso' => ['required', 'numeric', 'min:0.1', 'max:70'],
            'length' => ['required_if:tipo_envio,caja', 'nullable', 'numeric', 'min:1', 'max:300'],
            'width' => ['required_if:tipo_envio,caja', 'nullable', 'numeric', 'min:1', 'max:300'],
            'height' => ['required_if:tipo_envio,caja', 'nullable', 'numeric', 'min:1', 'max:300'],
        ]);
        $tenant = $context->tenant()->load('branding');
        try {
            $result = $quotes->quote($tenant, $data);
        } catch (TenantQuoteUnavailableException) {
            return back()->withInput()->withErrors([
                'quote' => 'No fue posible obtener tarifas disponibles para este envío en este momento. Verifica los datos o intenta nuevamente.',
            ]);
        }

        return view('tenant.b2c.quote', ['tenant' => $tenant, 'result' => $result]);
    }

    public function tracking(TenantContext $context)
    {
        return view('tenant.b2c.tracking', ['tenant' => $context->tenant()->load('branding')]);
    }
}
