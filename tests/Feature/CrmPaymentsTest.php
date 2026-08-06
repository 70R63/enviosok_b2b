<?php

namespace Tests\Feature;

use App\Models\B2cCotizacion;
use App\Models\B2cMovimientoSaldo;
use App\Models\B2cRecarga;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class CrmPaymentsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>false]]);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('users', function(Blueprint $t){$t->id();$t->string('name');$t->string('email');$t->string('password');$t->rememberToken();$t->timestamps();});
        Schema::create('roles', function(Blueprint $t){$t->id();$t->string('slug');$t->timestamps();});
        Schema::create('users_roles', function(Blueprint $t){$t->unsignedBigInteger('user_id');$t->unsignedBigInteger('roles_id');});
        Schema::create('crm_clients', function(Blueprint $t){$t->id();$t->string('commercial_status')->nullable();$t->timestamp('reviewed_at')->nullable();$t->string('lead_status')->nullable();});
        Schema::create('b2c_identity_verifications', fn(Blueprint $t)=>$t->id());
        Schema::create('b2c_adeudos', function(Blueprint $t){$t->id();$t->string('estatus')->nullable();});
        Schema::create('b2c_invoice_requests', function(Blueprint $t){$t->id();$t->unsignedBigInteger('cotizacion_id');$t->string('status')->nullable();});
        Schema::create('b2c_cotizaciones', function(Blueprint $t){$t->id();$t->unsignedBigInteger('user_id')->nullable();foreach(['logistico','servicio','estatus','referencia','guia_id','tracking_number','guia_estatus','payment_id','payment_status','payment_external_reference','payment_verified_currency'] as $c)$t->string($c)->nullable();foreach(['precio','precio_sin_seguro','seguro_monto','payment_verified_amount'] as $c)$t->decimal($c,12,2)->nullable();$t->timestamp('payment_verified_at')->nullable();$t->timestamps();});
        Schema::create('b2c_recargas', function(Blueprint $t){$t->id();$t->unsignedBigInteger('user_id');$t->decimal('monto',12,2);foreach(['estatus','mp_preference_id','mp_payment_id','mp_status','referencia'] as $c)$t->string($c)->nullable();$t->timestamps();});
        Schema::create('b2c_movimientos_saldo', function(Blueprint $t){$t->id();$t->unsignedBigInteger('user_id');$t->string('tipo');$t->decimal('monto',12,2);$t->decimal('saldo_anterior',12,2);$t->decimal('saldo_nuevo',12,2);$t->string('referencia')->nullable();$t->string('estatus');$t->timestamps();});
    }

    public function test_sidebar_route_access_tabs_filters_and_pagination(): void
    {
        $admin=$this->user('admin');
        B2cCotizacion::create(['user_id'=>$admin->id,'payment_status'=>'approved','payment_id'=>'MP-FIND','estatus'=>'PAGADA','precio'=>100]);
        for($i=0;$i<26;$i++) B2cRecarga::create(['user_id'=>$admin->id,'monto'=>300,'estatus'=>'PENDIENTE','referencia'=>'R-'.$i]);
        $response=$this->actingAs($admin)->get(route('crm.pagos.index',['pago_referencia'=>'MP-FIND','tab'=>'recharges']));
        $response->assertOk()->assertSee('Pagos de envíos')->assertSee('Recargas prepago')->assertSee('MP-FIND')->assertSee('recargas_page=2',false);
        $sidebar=file_get_contents(resource_path('views/crm/partials/sidebar.blade.php'));
        $this->assertStringContainsString("route('crm.pagos.index')",$sidebar);$this->assertStringNotContainsString('<a href="#">Pagos</a>',$sidebar);
    }

    public function test_payment_methods_recharge_states_review_and_null_placeholder(): void
    {
        $admin=$this->user('admin');
        B2cCotizacion::create(['user_id'=>$admin->id,'payment_status'=>'approved','payment_id'=>'MP-1','estatus'=>'PAGADA','precio'=>150]);
        B2cCotizacion::create(['user_id'=>$admin->id,'payment_status'=>'saldo_prepago','estatus'=>'PAGADA','precio'=>200]);
        $pending=B2cRecarga::create(['user_id'=>$admin->id,'monto'=>300,'estatus'=>'PENDIENTE','mp_status'=>'approved']);
        $credited=B2cRecarga::create(['user_id'=>$admin->id,'monto'=>500,'estatus'=>'APROBADA','mp_status'=>'approved','referencia'=>'RECARGA-X']);
        B2cMovimientoSaldo::create(['user_id'=>$admin->id,'tipo'=>'RECARGA','monto'=>500,'saldo_anterior'=>100,'saldo_nuevo'=>600,'referencia'=>'RECARGA-'.$credited->id,'estatus'=>'APLICADO']);
        $this->actingAs($admin)->get(route('crm.pagos.index'))->assertOk()->assertSee('Mercado Pago')->assertSee('Saldo prepago')->assertSee('Aprobada no acreditada')->assertSee('Revisión requerida')->assertSee('Acreditada')->assertSee('—');
        $this->actingAs($admin)->get(route('crm.pagos.recargas.show',$pending))->assertOk()->assertSee('Revisión requerida')->assertSee('Sólo lectura');
    }

    public function test_details_hide_secrets_and_internal_costs_and_unauthorized_is_forbidden(): void
    {
        $admin=$this->user('admin');$plain=$this->user('cliente');
        $quote=B2cCotizacion::create(['user_id'=>$admin->id,'payment_status'=>'approved','payment_id'=>'PAY-DETAIL','estatus'=>'PAGADA','precio'=>250]);
        $this->actingAs($admin)->get(route('crm.pagos.envios.show',$quote))->assertOk()->assertSee('PAY-DETAIL')->assertDontSee('provider_base_price')->assertDontSee('provider_total')->assertDontSee('access_token')->assertDontSee('payment_verification_payload');
        $this->actingAs($plain)->get(route('crm.pagos.index'))->assertForbidden();
    }

    private function user(string $role): User
    {
        $user=User::create(['name'=>$role,'email'=>$role.'@example.test','password'=>'x']);
        $roleId=DB::table('roles')->insertGetId(['slug'=>$role,'created_at'=>now(),'updated_at'=>now()]);
        DB::table('users_roles')->insert(['user_id'=>$user->id,'roles_id'=>$roleId]); return $user;
    }
}
