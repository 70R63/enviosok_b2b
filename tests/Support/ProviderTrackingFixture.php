<?php

namespace Tests\Support;

use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Network\Tenancy\Models\TenantMembership;
use App\Domain\Shipping\LastMile\Models\DriverProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ProviderTrackingFixture
{
    public static function schema(): void
    {
        ProviderAcceptanceFixture::schema();
        Schema::drop('local_driver_assignments');
        foreach (['2026_08_10_100000_create_driver_last_mile_tables.php',
            '2026_08_11_100000_create_driver_presence_earnings_tables.php',
            '2026_08_12_100000_create_driver_delivery_evidence_tables.php',
            '2026_08_13_100000_create_configurable_delivery_proof_tables.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
    }

    public static function grantDriver(array $fixture): void
    {
        DB::table('network_entitlements')->insert(['tenant_id' => $fixture['provider']->id,
            'subscription_id' => DB::table('network_subscriptions')->where('tenant_id', $fixture['provider']->id)->value('id'),
            'code' => 'DRIVER', 'is_enabled' => true]);
    }

    public static function driver(Tenant $tenant, string $suffix = 'one'): DriverProfile
    {
        $user = User::create(['name' => 'Driver '.$suffix, 'email' => 'driver-'.$suffix.'@example.test', 'password' => 'unused']);
        TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'role' => 'driver', 'status' => 'active']);

        return DriverProfile::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'code' => 'D-'.$suffix,
            'status' => 'ACTIVE', 'availability_status' => 'AVAILABLE', 'last_seen_at' => now()]);
    }

    public static function signature(): string
    {
        $file = UploadedFile::fake()->image('signature.png', 200, 80);

        return file_get_contents($file->getRealPath());
    }

    public static function proofData(): array
    {
        return ['received_by_name' => 'Allowed Receiver', 'receiver_type' => 'RECIPIENT', 'receiver_notes' => 'Private receiver note',
            'latitude' => 25.67, 'longitude' => -100.30, 'accuracy_meters' => 5];
    }
}
