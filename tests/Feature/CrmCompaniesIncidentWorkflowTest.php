<?php
namespace Tests\Feature;

use App\Models\B2cIncidencia;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class CrmCompaniesIncidentWorkflowTest extends TestCase
{
    protected function setUp():void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        config(['database.default'=>'sqlite','database.connections.sqlite'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>false]]);DB::purge('sqlite');DB::reconnect('sqlite');
        Schema::create('users',function(Blueprint $t){$t->id();$t->string('name');$t->string('email');$t->string('password');$t->unsignedBigInteger('empresa_id')->nullable();$t->rememberToken();$t->timestamps();});
        Schema::create('roles',function(Blueprint $t){$t->id();$t->string('slug');$t->timestamps();});Schema::create('users_roles',function(Blueprint $t){$t->unsignedBigInteger('user_id');$t->unsignedBigInteger('roles_id');});
        Schema::create('crm_clients',function(Blueprint $t){$t->id();$t->string('commercial_status')->nullable();$t->timestamp('reviewed_at')->nullable();$t->string('lead_status')->nullable();});Schema::create('b2c_identity_verifications',function(Blueprint $t){$t->id();$t->string('status')->nullable();});Schema::create('b2c_adeudos',function(Blueprint $t){$t->id();$t->unsignedBigInteger('user_id')->nullable();$t->string('estatus')->nullable();$t->timestamps();});Schema::create('b2c_invoice_requests',function(Blueprint $t){$t->id();$t->unsignedBigInteger('cotizacion_id');$t->string('status')->nullable();});
        Schema::create('empresas',function(Blueprint $t){$t->id();$t->string('nombre');$t->string('rfc')->nullable();$t->string('contacto')->nullable();$t->string('email')->nullable();$t->string('telefono')->nullable();$t->boolean('estatus')->default(true);$t->timestamps();});Schema::create('empresa_empresas',function(Blueprint $t){$t->unsignedBigInteger('id');$t->unsignedBigInteger('empresa_id');});
        Schema::create('b2c_cotizaciones',function(Blueprint $t){$t->id();$t->unsignedBigInteger('user_id')->nullable();$t->string('tracking_number')->nullable();$t->string('payment_status')->nullable();$t->timestamps();});
        Schema::create('b2c_incidencias',function(Blueprint $t){$t->id();$t->string('folio')->unique();$t->unsignedBigInteger('user_id');$t->unsignedBigInteger('cotizacion_id')->nullable();$t->string('tracking_number')->nullable();$t->string('tipo');$t->string('asunto');$t->text('descripcion');$t->text('customer_message')->nullable();$t->string('evidencia')->nullable();$t->string('estatus')->default('ABIERTA');$t->string('prioridad')->default('MEDIA');$t->unsignedBigInteger('assigned_to')->nullable();$t->timestamp('assigned_at')->nullable();$t->text('respuesta_admin')->nullable();$t->text('public_response')->nullable();$t->text('internal_notes')->nullable();$t->timestamp('respondida_at')->nullable();$t->unsignedBigInteger('respondida_por')->nullable();$t->timestamp('resolved_at')->nullable();$t->timestamp('closed_at')->nullable();$t->timestamps();});
        Schema::create('b2c_incidencia_events',function(Blueprint $t){$t->id();$t->unsignedBigInteger('incidencia_id');$t->unsignedBigInteger('user_id')->nullable();$t->string('origin');$t->string('event_type');$t->string('previous_status')->nullable();$t->string('new_status')->nullable();$t->unsignedBigInteger('previous_assigned_to')->nullable();$t->unsignedBigInteger('new_assigned_to')->nullable();$t->string('priority')->nullable();$t->text('public_message')->nullable();$t->text('internal_note')->nullable();$t->timestamps();});
    }

    public function test_company_and_incident_menus_and_company_index():void
    {
        $admin=$this->user('admin');DB::table('empresas')->insert(['nombre'=>'Acme','rfc'=>'ACM010101AAA','estatus'=>1,'created_at'=>now(),'updated_at'=>now()]);
        $this->actingAs($admin)->get(route('crm.empresas.index'))->assertOk()->assertSee('Acme');
        $sidebar=file_get_contents(resource_path('views/crm/partials/sidebar.blade.php'));$this->assertStringContainsString("route('crm.empresas.index')",$sidebar);$this->assertStringContainsString("route('crm.incidencias.index')",$sidebar);$this->assertStringNotContainsString('<a href="#">Empresas</a>',$sidebar);$this->assertStringNotContainsString('<a href="#">Incidencias</a>',$sidebar);
    }

    public function test_same_incident_flows_crm_support_and_customer_with_audit():void
    {
        $admin=$this->user('admin');$support=$this->user('soporte');$otherSupport=$this->user('soporte');$client=$this->user('cliente');
        $incident=$this->incident($client);
        $this->actingAs($admin)->get(route('crm.incidencias.index',['q'=>'INC-00001']))->assertOk()->assertSee('INC-00001');
        $this->actingAs($admin)->patch(route('crm.incidencias.assign',$incident),['assigned_to'=>$support->id,'priority'=>'ALTA'])->assertRedirect();
        $this->assertSame($incident->id,B2cIncidencia::where('folio','INC-00001')->sole()->id);$this->assertSame('ASIGNADA',$incident->fresh()->estatus);
        $this->actingAs($support)->get(route('soporte.incidencias.show',$incident))->assertOk()->assertSee('INC-00001');
        $this->actingAs($otherSupport)->get(route('soporte.incidencias.show',$incident))->assertForbidden();
        $this->actingAs($support)->patch(route('soporte.incidencias.status',$incident),['status'=>'EN_PROCESO'])->assertRedirect();
        $this->actingAs($support)->post(route('soporte.incidencias.follow-up',$incident),['public_response'=>'Tu envío ya fue localizado.','internal_note'=>'Validación interna reservada.','solution'=>1])->assertRedirect();
        $customer=$this->actingAs($client)->get(route('b2c.incidencias.show',$incident));$customer->assertOk()->assertSee('Tu envío ya fue localizado.')->assertDontSee('Validación interna reservada.');
        $this->assertSame('RESUELTA',$incident->fresh()->estatus);$this->assertDatabaseHas('b2c_incidencia_events',['incidencia_id'=>$incident->id,'origin'=>'CRM','event_type'=>'ASSIGNMENT']);$this->assertDatabaseHas('b2c_incidencia_events',['incidencia_id'=>$incident->id,'origin'=>'SOPORTE','event_type'=>'STATUS']);
    }

    public function test_idor_invalid_transition_filters_and_pagination():void
    {
        $admin=$this->user('admin');$client=$this->user('cliente');$other=$this->user('cliente');$incident=$this->incident($client);
        $this->actingAs($other)->get(route('b2c.incidencias.show',$incident))->assertForbidden();
        $this->actingAs($admin)->patch(route('crm.incidencias.status',$incident),['status'=>'CERRADA'])->assertSessionHasErrors('status');$this->assertSame('ABIERTA',$incident->fresh()->estatus);
        for($i=2;$i<=27;$i++)$this->incident($client,'INC-'.str_pad((string)$i,5,'0',STR_PAD_LEFT));
        $this->actingAs($admin)->get(route('crm.incidencias.index',['estatus'=>'ABIERTA']))->assertOk()->assertSee('page=2',false)->assertDontSee('provider_base_price')->assertDontSee('access_token');
    }

    private function user(string $role):User{$n=DB::table('users')->count()+1;$u=User::create(['name'=>$role.$n,'email'=>$role.$n.'@test.local','password'=>'x']);$id=DB::table('roles')->insertGetId(['slug'=>$role,'created_at'=>now(),'updated_at'=>now()]);DB::table('users_roles')->insert(['user_id'=>$u->id,'roles_id'=>$id]);return $u;}
    private function incident(User $user,string $folio='INC-00001'):B2cIncidencia{return B2cIncidencia::create(['folio'=>$folio,'user_id'=>$user->id,'tipo'=>'Entrega','asunto'=>'Demora','descripcion'=>'Mi paquete no llega','customer_message'=>'Mi paquete no llega','estatus'=>'ABIERTA','prioridad'=>'MEDIA']);}
}
