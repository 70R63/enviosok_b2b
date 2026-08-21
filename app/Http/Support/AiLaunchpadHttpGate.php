<?php
namespace App\Http\Support;
use App\Domain\AI\Core\AiEntitlementGate;
use App\Domain\AI\Support\Exceptions\{AiDisabledException,AiEntitlementException,AiTenantContextException};
use App\Domain\AI\Support\Exceptions\AiTenantMismatchException;
use App\Domain\AI\Tenancy\AiTenantBoundary;
use App\Domain\Network\Tenancy\TenantAccessService;
use App\Models\User;
final class AiLaunchpadHttpGate
{
    public function __construct(private AiEntitlementGate $entitlements,private TenantAccessService $access,private AiTenantBoundary $tenants){}
    public function ensure(?User $actor):void
    {
        try{$this->entitlements->ensureAllowed();}
        catch(AiDisabledException|AiTenantContextException){abort(404);}
        catch(AiEntitlementException){abort(403);}
        abort_unless($this->access->canManageTenant($actor),403);
    }
    public function assertCurrentTenant(object $resource):void
    {
        try{$this->tenants->assertResourceBelongsToCurrentTenant($resource);}
        catch(AiTenantMismatchException){abort(404);}
    }
}
