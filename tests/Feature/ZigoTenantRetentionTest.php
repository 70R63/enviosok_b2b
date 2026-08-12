<?php

namespace Tests\Feature;

use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Network\Tenancy\TenantLifecycleService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class ZigoTenantRetentionTest extends ZigoNetworkOperationsConsoleTest
{
    protected function setUp(): void { parent::setUp(); (require database_path('migrations/2026_08_20_110000_add_tenant_retention_fields.php'))->up(); }

    public function test_archiving_preserves_history_and_paid_tenant_cannot_be_deleted(): void
    {
        $tenant=Tenant::create(['name'=>'Paid tenant','slug'=>'paid-tenant','status'=>'suspended','current_plan_id'=>1]);
        DB::table('platform_payment_attempts')->insert(['uuid'=>(string)\Illuminate\Support\Str::uuid(),'tenant_id'=>$tenant->id,'provider'=>'MERCADO_PAGO','status'=>'APPROVED','external_reference'=>'paid-ref','amount'=>100,'currency'=>'MXN','approved_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        $service=app(TenantLifecycleService::class);
        $this->assertFalse($service->canPermanentlyDeleteTenant($tenant));
        $service->archive($tenant,'Retención solicitada');
        $this->assertSame('archived',$tenant->fresh()->status);
        $this->assertDatabaseHas('platform_payment_attempts',['tenant_id'=>$tenant->id,'status'=>'APPROVED']);
        $this->expectException(ValidationException::class); $service->deleteEmpty($tenant,'No procede');
    }

    public function test_ticket_or_subscription_blocks_delete_but_empty_tenant_can_be_deleted_and_reason_is_required(): void
    {
        $service=app(TenantLifecycleService::class);
        $ticketTenant=Tenant::create(['name'=>'Ticket tenant','slug'=>'ticket-tenant','status'=>'inactive','current_plan_id'=>1]);
        DB::table('support_tickets')->insert(['uuid'=>(string)\Illuminate\Support\Str::uuid(),'tenant_id'=>$ticketTenant->id,'requester_user_id'=>1,'requester_type'=>'NETWORK','scope'=>'ZIGO_PLATFORM','channel'=>'WEB','category'=>'GENERAL','priority'=>'NORMAL','status'=>'OPEN','subject'=>'History','description'=>'History','public_reference'=>'SUP-1','created_at'=>now(),'updated_at'=>now()]);
        $this->assertFalse($service->canPermanentlyDeleteTenant($ticketTenant));
        $empty=Tenant::create(['name'=>'Empty','slug'=>'empty-tenant','status'=>'inactive','current_plan_id'=>null]);
        try {$service->deleteEmpty($empty,''); $this->fail('Reason must be required');} catch (ValidationException) {}
        $service->deleteEmpty($empty,'Creado por error, sin historial');
        $this->assertDatabaseMissing('network_tenants',['id'=>$empty->id]);
    }

    public function test_retention_migration_is_reversible_on_sqlite(): void
    {
        $migration=require database_path('migrations/2026_08_20_110000_add_tenant_retention_fields.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('network_tenants','archived_at'));
        $migration->up();
        $this->assertTrue(Schema::hasColumn('network_tenants','archive_reason'));
    }
}
