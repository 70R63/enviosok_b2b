<?php
namespace App\Domain\AI\Launchpad\Services;
use App\Domain\AI\Core\{AiEntitlementGate,AiFeatureGate};use App\Domain\AI\Launchpad\{AgentLaunchpadAdvisorRegistry};use App\Domain\AI\Launchpad\Data\LaunchpadIntakeData;use App\Domain\AI\Launchpad\Enums\LaunchpadSessionStatus;use App\Domain\AI\Launchpad\Models\AgentLaunchpadSession;use App\Domain\AI\Tenancy\AiTenantBoundary;use App\Domain\Network\Tenancy\TenantAccessService;use App\Models\User;use Illuminate\Auth\Access\AuthorizationException;
final class GenerateLaunchpadRecommendationService
{
 public function __construct(private AiFeatureGate$features,private AiTenantBoundary$tenants,private AiEntitlementGate$entitlements,private TenantAccessService$access,private AgentLaunchpadAdvisorRegistry$advisors){}
 public function generate(User$actor,LaunchpadIntakeData$intake):AgentLaunchpadSession{$this->features->ensureEnabled();$this->tenants->requireTenant();$this->entitlements->ensureAllowed();if(!$this->access->canManageTenant($actor))throw new AuthorizationException('Only an active owner or admin may use the launchpad.');$r=$this->advisors->resolve()->recommend($intake);$s=new AgentLaunchpadSession();$s->status=LaunchpadSessionStatus::Recommended;$s->objective_code=$intake->objectiveCode;$s->intake=$intake->toArray();$s->recommendation=$r->toArray();$s->advisor_code=$r->advisorCode;$s->recommendation_version=$r->schemaVersion;$s->confidence=$r->confidence;$s->created_by_user_id=$actor->id;$s->save();return$s;}
}
