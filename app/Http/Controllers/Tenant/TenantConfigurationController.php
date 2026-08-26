<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Network\Tenancy\TenantAccessService;
use App\Domain\Network\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateTenantConfigurationRequest;
use App\Domain\Network\ProductShell\TenantWorkspaceResolver;

final class TenantConfigurationController extends Controller
{
    public function edit(TenantContext $context, TenantAccessService $access, TenantWorkspaceResolver $workspaces)
    {
        $tenant = $context->tenant()->load(['branding', 'domains']);
        $domain = $tenant->domains->firstWhere('is_primary', true);

        return view('tenant.admin.configuration', [
            'tenant' => $tenant,
            'branding' => $tenant->branding,
            'domain' => $domain,
            'canEdit' => $access->hasRole(['owner', 'admin'], auth()->user()),
            'previewUrl' => $domain && $domain->status === 'verified' ? 'https://'.$domain->domain.'/white-label' : null,
            'isAiWorkspace' => $workspaces->resolveForPresentation($tenant)->value === 'zigo_ai',
        ]);
    }

    public function update(UpdateTenantConfigurationRequest $request, TenantContext $context)
    {
        $tenant = $context->tenant();
        $data = $request->safe()->except(['logo', 'hero_image', 'favicon']);
        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store("tenant-branding/{$tenant->uuid}", 'public');
        }
        if ($request->hasFile('favicon')) {
            $data['favicon_path'] = $request->file('favicon')->store("tenant-branding/{$tenant->uuid}", 'public');
        }
        if ($request->hasFile('hero_image')) {
            $data['hero_image_path'] = $request->file('hero_image')->store("tenant-branding/{$tenant->uuid}", 'public');
        }
        $tenant->branding()->updateOrCreate(['tenant_id' => $tenant->id], $data);

        return back()->with('success', 'Configuración actualizada.');
    }
}
