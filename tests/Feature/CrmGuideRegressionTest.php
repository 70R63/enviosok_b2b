<?php

namespace Tests\Feature;

use App\Models\B2cCotizacion;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class CrmGuideRegressionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>false]]);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('users',function(Blueprint $t){$t->id();$t->string('name');$t->string('email');$t->timestamps();});
        Schema::create('crm_clients',function(Blueprint $t){$t->id();$t->string('commercial_status')->nullable();$t->timestamp('reviewed_at')->nullable();$t->string('lead_status')->nullable();$t->timestamps();});
        Schema::create('b2c_cotizaciones',function(Blueprint $t){$t->id();$t->unsignedBigInteger('user_id')->nullable();$t->string('guia_id')->nullable();$t->string('tracking_number')->nullable();$t->string('estatus')->nullable();$t->decimal('provider_base_price',10,2)->nullable();$t->decimal('precio',10,2)->nullable();$t->decimal('peso',10,2)->nullable();$t->decimal('peso_facturable',10,2)->nullable();$t->timestamps();});
        Schema::create('guias',function(Blueprint $t){$t->id();$t->string('tracking_number')->nullable();$t->decimal('precio',10,2)->nullable();$t->decimal('peso',10,2)->nullable();$t->decimal('peso_bascula',10,2)->nullable();$t->decimal('rastreo_peso',10,2)->nullable();$t->timestamps();});
        Schema::create('b2c_adeudos',function(Blueprint $t){$t->id();$t->unsignedBigInteger('user_id');$t->unsignedBigInteger('cotizacion_id');$t->string('guia_id')->nullable();$t->string('tracking_number')->nullable();$t->string('tipo');$t->string('concepto');$t->decimal('peso_cotizado',10,2)->nullable();$t->decimal('peso_real',10,2)->nullable();$t->decimal('costo_cotizado',10,2);$t->decimal('costo_real',10,2);$t->decimal('monto',10,2);$t->string('referencia_xperta');$t->text('observaciones')->nullable();$t->string('estatus');$t->string('payment_external_reference')->nullable();$t->unsignedBigInteger('created_by')->nullable();$t->timestamps();});
        DB::table('users')->insert(['id'=>7,'name'=>'Cliente','email'=>'cliente@example.test','created_at'=>now(),'updated_at'=>now()]);
    }

    public function test_crm_pagination_is_scoped_sized_and_preserves_query_string(): void
    {
        $paginator=(new LengthAwarePaginator(range(1,25),60,25,2,['path'=>'/crm/guias']))->appends(['search'=>'0218429641','adeudo'=>'PENDIENTES']);
        $html=$paginator->links('crm.pagination')->render();
        $this->assertStringContainsString('Anterior',$html);
        $this->assertStringContainsString('Siguiente',$html);
        $this->assertStringContainsString('search=0218429641',$html);
        $css=file_get_contents(resource_path('views/crm/layout.blade.php'));
        $this->assertMatchesRegularExpression('/\.pagination-wrap\s+svg\s*\{[^}]*width:16px;[^}]*height:16px;/s',$css);
        $this->assertDoesNotMatchRegularExpression('/(?<!pagination-wrap)\s+svg\s*\{[^}]*width:16px/s',$css);
    }

    public function test_guide_and_debt_view_contracts_are_preserved(): void
    {
        $index=file_get_contents(resource_path('views/crm/guias/index.blade.php'));
        $show=file_get_contents(resource_path('views/crm/guias/show.blade.php'));
        $this->assertStringContainsString("route('crm.guias.show', \$cotizacion)",$index);
        $this->assertStringContainsString("links('crm.pagination')",$index);
        $this->assertStringContainsString('class="pagination-wrap"',$index);
        $this->assertStringContainsString("route('crm.guias.adeudos.store', \$cotizacion)",$show);
        $this->assertStringContainsString('@csrf',$show);
        $this->assertSame('crm.guias.show',app('router')->getRoutes()->getByName('crm.guias.show')->getName());
        $this->assertContains('POST',app('router')->getRoutes()->getByName('crm.guias.adeudos.store')->methods());
    }

    public function test_detail_opens_and_debt_post_still_creates_the_debt(): void
    {
        $q=B2cCotizacion::create(['user_id'=>7,'guia_id'=>'8050000000112600113484','tracking_number'=>'0218429641','estatus'=>'GUIA_GENERADA','provider_base_price'=>100,'precio'=>120,'peso'=>1,'peso_facturable'=>1]);
        $without=[\App\Http\Middleware\EnsureZigoPortalHost::class,\App\Http\Middleware\Authenticate::class,\App\Http\Middleware\RolesMiddleware::class,\App\Http\Middleware\VerifyCsrfToken::class];
        $this->withoutMiddleware($without)->get(route('crm.guias.show',$q))->assertOk()->assertSee(route('crm.guias.adeudos.store',$q),false);
        $response=$this->withoutMiddleware($without)->post(route('crm.guias.adeudos.store',$q),['tipo'=>'REPESAJE','concepto'=>'Ajuste por peso real','peso_cotizado'=>1,'peso_real'=>2,'costo_cotizado'=>100,'costo_real'=>130,'referencia_xperta'=>'XP-107']);
        $response->assertRedirect();
        $this->assertDatabaseHas('b2c_adeudos',['cotizacion_id'=>$q->id,'monto'=>30,'referencia_xperta'=>'XP-107','estatus'=>'PENDIENTE']);
    }
}
