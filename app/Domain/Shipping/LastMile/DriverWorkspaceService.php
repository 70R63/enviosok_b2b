<?php

namespace App\Domain\Shipping\LastMile;

use App\Domain\Network\Billing\EntitlementService;
use App\Domain\Network\Billing\SubscriptionAccessService;
use App\Domain\Shipping\LastMile\Models\DriverProfile;
use App\Models\User;
use Illuminate\Support\Collection;

final class DriverWorkspaceService
{
    public function __construct(
        private SubscriptionAccessService $subscriptions,
        private EntitlementService $entitlements,
    ) {}

    /** @return Collection<int, DriverProfile> */
    public function availableFor(User $user): Collection
    {
        return DriverProfile::query()
            ->with(['tenant.branding'])
            ->where('user_id', $user->getKey())
            ->where('status', 'ACTIVE')
            ->whereHas('tenant', function ($tenant) use ($user): void {
                $tenant->where('status', 'active')->whereHas('memberships', fn ($membership) => $membership
                    ->where('user_id', $user->getKey())
                    ->where('role', 'driver')
                    ->where('status', 'active'));
            })
            ->get()
            ->filter(fn (DriverProfile $profile) => $this->subscriptions->isOperational($profile->tenant)
                && $this->entitlements->has($profile->tenant, 'DRIVER'))
            ->values();
    }

    public function resolve(User $user, ?string $profileUuid): ?DriverProfile
    {
        $available = $this->availableFor($user);
        if ($profileUuid !== null) {
            return $available->first(fn (DriverProfile $profile) => hash_equals((string) $profile->uuid, $profileUuid));
        }

        return $available->count() === 1 ? $available->first() : null;
    }
}
