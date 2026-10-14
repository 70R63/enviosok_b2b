<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class ProviderAcceptanceFixture
{
    public static function schema(): void
    {
        ProviderFulfillmentFixture::schema();
        Schema::create('network_tenant_memberships', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('tenant_id');
            $t->foreignId('user_id');
            $t->string('role');
            $t->string('status');
            $t->unique(['tenant_id', 'user_id']);
            $t->timestamps();
        });
        (require database_path('migrations/2026_10_13_130000_create_network_provider_fulfillment_decisions.php'))->up();
    }
}
