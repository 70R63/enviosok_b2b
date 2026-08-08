<?php

namespace Tests\Feature;

use App\Domain\Network\Catalog\Models\Module;
use App\Domain\Network\Catalog\Models\Plan;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Models\Roles\Roles;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NetworkFoundationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            $this->markTestSkipped('Network tests require isolated SQLite :memory:.');
        }

        $this->createIsolatedSchema();
    }

    public function test_network_requires_authentication(): void
    {
        $this->get('/network')->assertRedirect('/login');
    }

    public function test_non_sysadmin_receives_403(): void
    {
        $this->actingAs($this->userWithRole('admin'))->get('/network')->assertForbidden();
    }

    public function test_sysadmin_can_enter_and_dashboard_has_no_legacy_table_dependency(): void
    {
        $this->actingAs($this->userWithRole('sysadmin'))->get('/network')
            ->assertOk()->assertSee('ZIGO NETWORK')->assertSee('Pendiente dominio Usage');
    }

    public function test_tenant_slug_is_unique(): void
    {
        Tenant::create(['name'=>'Uno','slug'=>'uno','status'=>'active']);
        $this->expectException(QueryException::class);
        DB::table('network_tenants')->insert([
            'uuid'=>(string) \Illuminate\Support\Str::uuid(), 'name'=>'Dos', 'slug'=>'uno', 'status'=>'active',
            'created_at'=>now(), 'updated_at'=>now(),
        ]);
    }

    public function test_tenant_uuid_is_generated_and_unique(): void
    {
        $first = Tenant::create(['name'=>'Uno','slug'=>'uno','status'=>'active']);
        $this->assertNotEmpty($first->uuid);
        $this->expectException(QueryException::class);
        DB::table('network_tenants')->insert([
            'uuid'=>$first->uuid, 'name'=>'Dos', 'slug'=>'dos', 'status'=>'active',
            'created_at'=>now(), 'updated_at'=>now(),
        ]);
    }

    public function test_module_code_is_unique(): void
    {
        Module::create(['code'=>'CRM','name'=>'CRM','type'=>'addon','is_active'=>true,'sort_order'=>1]);
        $this->expectException(QueryException::class);
        Module::create(['code'=>'CRM','name'=>'Otro','type'=>'addon','is_active'=>true,'sort_order'=>2]);
    }

    public function test_plan_code_is_unique(): void
    {
        Plan::create(['code'=>'START','name'=>'Start','status'=>'active','currency'=>'MXN']);
        $this->expectException(QueryException::class);
        Plan::create(['code'=>'START','name'=>'Otro','status'=>'active','currency'=>'MXN']);
    }

    public function test_plan_module_relationship_works(): void
    {
        $plan=Plan::create(['code'=>'START','name'=>'Start','status'=>'active','currency'=>'MXN']);
        $module=Module::create(['code'=>'B2C','name'=>'B2C','type'=>'channel','is_active'=>true,'sort_order'=>1]);
        $plan->modules()->attach($module,['is_included'=>true,'limit_value'=>100]);
        $this->assertSame(100,$plan->fresh()->modules->first()->pivot->limit_value);
    }

    public function test_invalid_tenant_status_is_rejected(): void
    {
        $this->actingAs($this->userWithRole('sysadmin'))->post('/network/tenants',[
            'name'=>'Inválido','slug'=>'invalido','status'=>'deleted',
        ])->assertSessionHasErrors('status');
        $this->assertDatabaseCount('network_tenants',0);
    }

    private function userWithRole(string $slug): User
    {
        $user=User::forceCreate(['name'=>'Test','email'=>$slug.'@example.test','password'=>'secret','empresa_id'=>1]);
        $role=Roles::create(['name'=>$slug,'slug'=>$slug]);
        $user->roles()->attach($role->id);
        return $user;
    }

    private function createIsolatedSchema(): void
    {
        foreach (['network_plan_modules','network_plans','network_modules','network_tenants','users_roles','roles','users'] as $table) Schema::dropIfExists($table);
        Schema::create('users',function(Blueprint $t){$t->id();$t->string('name');$t->string('email')->unique();$t->string('password');$t->unsignedBigInteger('empresa_id');$t->rememberToken();$t->timestamps();});
        Schema::create('roles',function(Blueprint $t){$t->id();$t->string('name');$t->string('slug');$t->timestamps();});
        Schema::create('users_roles',function(Blueprint $t){$t->unsignedBigInteger('user_id');$t->unsignedBigInteger('roles_id');});
        Schema::create('network_tenants',function(Blueprint $t){$t->id();$t->uuid('uuid')->unique();$t->string('name');$t->string('slug')->unique();$t->string('status');$t->timestamps();});
        Schema::create('network_modules',function(Blueprint $t){$t->id();$t->string('code')->unique();$t->string('name');$t->text('description')->nullable();$t->string('type');$t->boolean('is_active');$t->unsignedSmallInteger('sort_order');$t->timestamps();});
        Schema::create('network_plans',function(Blueprint $t){$t->id();$t->string('code')->unique();$t->string('name');$t->text('description')->nullable();$t->string('status');$t->decimal('monthly_price',12,2)->nullable();$t->decimal('annual_price',12,2)->nullable();$t->char('currency',3);$t->unsignedInteger('included_operations')->nullable();$t->timestamps();});
        Schema::create('network_plan_modules',function(Blueprint $t){$t->id();$t->unsignedBigInteger('plan_id');$t->unsignedBigInteger('module_id');$t->boolean('is_included');$t->unsignedInteger('limit_value')->nullable();$t->timestamps();$t->unique(['plan_id','module_id']);});
    }
}
