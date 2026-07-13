<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\ZigoPricingRule;
use App\Models\ZigoPricingAdjustment;
use App\Models\ZigoClientPricingRule;
use App\Services\ZigoPricingService;
use Illuminate\Http\Request;

class CrmPricingController extends Controller
{
    public function index()
    {
        $pricingRules = ZigoPricingRule::orderBy('customer_segment')
            ->orderBy('package_type')
            ->get();

        $adjustments = ZigoPricingAdjustment::latest()
            ->get();

        $clientRules = ZigoClientPricingRule::latest()
            ->get();

        return view('crm.pricing.index', compact(
            'pricingRules',
            'adjustments',
            'clientRules'
        ));
    }

    public function simulate(Request $request)
    {
        $data = $request->validate([
            'carrier' => ['required', 'string'],
            'customer_segment' => ['required', 'string'],
            'plan' => ['nullable', 'string'],
            'package_type' => ['required', 'string'],
            'base_price' => ['required', 'numeric', 'min:1'],
            'crm_client_id' => ['nullable', 'integer'],
            'api_client_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
        ]);

        $result = app(ZigoPricingService::class)->calculate($data);

        return redirect()
            ->route('crm.pricing.index')
            ->with('simulation', $result)
            ->withInput();
    }

    public function toggleRule(ZigoPricingRule $rule)
    {
        $rule->update([
            'active' => !$rule->active,
        ]);

        return back()->with('success', 'Regla base actualizada.');
    }

    public function toggleAdjustment(ZigoPricingAdjustment $adjustment)
    {
        $adjustment->update([
            'active' => !$adjustment->active,
        ]);

        return back()->with('success', 'Ajuste global actualizado.');
    }

    public function toggleClientRule(ZigoClientPricingRule $clientRule)
    {
        $clientRule->update([
            'active' => !$clientRule->active,
        ]);

        return back()->with('success', 'Regla por cliente actualizada.');
    }

    public function storeRule(Request $request)
{
    $data = $request->validate([
        'name' => ['required', 'string', 'max:191'],
        'carrier' => ['required', 'string', 'max:50'],
        'customer_segment' => ['required', 'in:anonymous,b2c,b2b,api'],
        'plan' => ['nullable', 'string', 'max:50'],
        'package_type' => ['required', 'in:sobre,caja,all'],
        'margin_percentage' => ['required', 'numeric', 'min:0'],
        'fixed_fee' => ['nullable', 'numeric', 'min:0'],
        'min_price' => ['nullable', 'numeric', 'min:0'],
    ]);

    ZigoPricingRule::create([
        'name' => $data['name'],
        'carrier' => strtoupper($data['carrier']),
        'customer_segment' => $data['customer_segment'],
        'plan' => $data['plan'] ?: null,
        'package_type' => $data['package_type'],
        'margin_percentage' => $data['margin_percentage'],
        'fixed_fee' => $data['fixed_fee'] ?? 0,
        'min_price' => $data['min_price'] ?? null,
        'active' => true,
    ]);

    return back()->with('success', 'Regla base de margen creada correctamente.');
}

public function storeAdjustment(Request $request)
{
    $data = $request->validate([
        'name' => ['required', 'string', 'max:191'],
        'carrier' => ['required', 'string', 'max:50'],
        'customer_segment' => ['required', 'in:anonymous,b2c,b2b,api,all'],
        'package_type' => ['required', 'in:sobre,caja,all'],
        'adjustment_type' => ['required', 'in:surcharge_percentage,surcharge_fixed,discount_percentage,discount_fixed'],
        'adjustment_value' => ['required', 'numeric', 'min:0'],
        'max_uses' => ['nullable', 'integer', 'min:1'],
        'starts_at' => ['nullable', 'date'],
        'ends_at' => ['nullable', 'date'],
    ]);

    ZigoPricingAdjustment::create([
        'name' => $data['name'],
        'carrier' => strtoupper($data['carrier']),
        'customer_segment' => $data['customer_segment'],
        'package_type' => $data['package_type'],
        'adjustment_type' => $data['adjustment_type'],
        'adjustment_value' => $data['adjustment_value'],
        'max_uses' => $data['max_uses'] ?? null,
        'starts_at' => $data['starts_at'] ?? null,
        'ends_at' => $data['ends_at'] ?? null,
        'active' => true,
    ]);

    return back()->with('success', 'Ajuste global creado correctamente.');
}

public function storeClientRule(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'crm_client_id' => ['nullable', 'integer'],
            'api_client_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'customer_segment' => ['nullable', 'in:b2c,b2b,api'],
            'package_type' => ['required', 'in:sobre,caja,all'],
            'discount_type' => ['required', 'in:percentage,fixed'],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
        ]);

        if (empty($data['crm_client_id']) && empty($data['api_client_id']) && empty($data['user_id'])) {
            return back()->with('error', 'Debes indicar al menos CRM Client, API Client o User.');
        }

        ZigoClientPricingRule::create([
            'name' => $data['name'],
            'crm_client_id' => $data['crm_client_id'] ?? null,
            'api_client_id' => $data['api_client_id'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'customer_segment' => $data['customer_segment'] ?? null,
            'package_type' => $data['package_type'],
            'discount_type' => $data['discount_type'],
            'discount_value' => $data['discount_value'],
            'max_uses' => $data['max_uses'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'active' => true,
        ]);

        return back()->with('success', 'Promoción por cliente creada correctamente.');
    }
}