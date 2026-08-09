<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Network\Tenancy\TenantAccessService;
use App\Domain\Network\Tenancy\TenantContext;
use App\Domain\Shipping\LastMile\DeliveryRequirementService;
use App\Domain\Shipping\LastMile\Models\TenantDeliveryProofOption;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class TenantDeliveryProofOptionController extends Controller
{
    public function index(TenantContext $context, TenantAccessService $access, DeliveryRequirementService $requirements)
    {
        $this->authorizeView($access); $requirements->defaultOption($context->tenant());
        return view('tenant.admin.delivery-proof-options', ['tenant' => $context->tenant()->load('branding'), 'options' => TenantDeliveryProofOption::where('tenant_id', $context->id())->orderBy('sort_order')->orderBy('name')->get(), 'canManage' => $access->hasRole(['owner', 'admin'], auth()->user())]);
    }
    public function store(Request $request, TenantContext $context, TenantAccessService $access) { $this->authorizeManage($access); $this->persist($context->id(), null, $this->validated($request, $context->id())); return back()->with('success', 'Opción de evidencia creada.'); }
    public function update(Request $request, string $option, TenantContext $context, TenantAccessService $access) { $this->authorizeManage($access); $item = TenantDeliveryProofOption::where('tenant_id', $context->id())->where('uuid', $option)->firstOrFail(); $this->persist($context->id(), $item, $this->validated($request, $context->id(), $item->id)); return back()->with('success', 'Opción actualizada. Los snapshots históricos no cambiaron.'); }

    private function validated(Request $request, int $tenantId, ?int $ignore = null): array
    {
        $data = $request->validate(['code' => ['required','alpha_dash','max:40',Rule::unique('tenant_delivery_proof_options')->where('tenant_id',$tenantId)->ignore($ignore)], 'name' => ['required','string','max:120'], 'description' => ['nullable','string','max:300'], 'require_receiver_name' => ['nullable','boolean'], 'require_receiver_type' => ['nullable','boolean'], 'require_signature' => ['nullable','boolean'], 'require_photo' => ['nullable','boolean'], 'require_gps' => ['nullable','boolean'], 'receiver_policy' => ['required',Rule::in(TenantDeliveryProofOption::RECEIVER_POLICIES)], 'max_delivery_attempts' => ['required','integer','between:1,10'], 'surcharge_amount' => ['nullable','numeric','min:0'], 'currency' => ['required','string','size:3','regex:/^[A-Za-z]{3}$/'], 'is_default' => ['nullable','boolean'], 'is_active' => ['nullable','boolean'], 'sort_order' => ['required','integer','min:0']]);
        foreach (array_merge(TenantDeliveryProofOption::EVIDENCE_FIELDS, ['require_receiver_type','is_default','is_active']) as $field) $data[$field] = $request->boolean($field);
        if (! collect(TenantDeliveryProofOption::EVIDENCE_FIELDS)->contains(fn ($field) => $data[$field])) throw ValidationException::withMessages(['evidence' => 'Habilita al menos un mecanismo real de evidencia: receptor, firma, fotografía o GPS.']);
        if ($data['is_default'] && ! $data['is_active']) throw ValidationException::withMessages(['is_default' => 'La opción predeterminada debe estar activa.']);
        $data['code'] = strtoupper($data['code']); $data['currency'] = strtoupper($data['currency']); return $data;
    }
    private function persist(int $tenantId, ?TenantDeliveryProofOption $option, array $data): void
    {
        DB::transaction(function () use ($tenantId,$option,$data): void { DB::table('network_tenants')->where('id',$tenantId)->lockForUpdate()->first(); if ($data['is_default']) TenantDeliveryProofOption::where('tenant_id',$tenantId)->where('is_default',true)->when($option,fn($q)=>$q->where('id','!=',$option->id))->update(['is_default'=>false]); $option ? $option->update($data) : TenantDeliveryProofOption::create(['tenant_id'=>$tenantId]+$data); if (!TenantDeliveryProofOption::where('tenant_id',$tenantId)->where('is_active',true)->where('is_default',true)->exists()) { $fallback=TenantDeliveryProofOption::where('tenant_id',$tenantId)->where('is_active',true)->orderBy('sort_order')->first(); if($fallback)$fallback->update(['is_default'=>true]); } });
    }
    private function authorizeView(TenantAccessService $access): void { abort_unless($access->hasRole(['owner','admin','operator'],auth()->user()),403); }
    private function authorizeManage(TenantAccessService $access): void { abort_unless($access->hasRole(['owner','admin'],auth()->user()),403); }
}
