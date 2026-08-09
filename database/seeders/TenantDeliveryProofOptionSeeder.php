<?php

namespace Database\Seeders;

use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Shipping\LastMile\DeliveryRequirementService;
use Illuminate\Database\Seeder;

final class TenantDeliveryProofOptionSeeder extends Seeder
{
    public function run(DeliveryRequirementService $requirements): void
    {
        Tenant::query()->orderBy('id')->eachById(fn (Tenant $tenant) => $requirements->defaultOption($tenant));
    }
}
