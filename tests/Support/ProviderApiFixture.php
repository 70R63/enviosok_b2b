<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class ProviderApiFixture
{
    public static function schema(): void
    {
        ProviderTrackingFixture::schema();
        Schema::create('tenant_saas_orders', fn (Blueprint $t) => $t->id());
        (require database_path('migrations/2026_08_17_100000_create_tenant_api_hub_v1.php'))->up();
        (require database_path('migrations/2026_10_13_140000_add_provider_api_boundary.php'))->up();
        (require database_path('migrations/2026_10_13_150000_create_provider_payable_ledger.php'))->up();
    }
}
