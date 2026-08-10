<?php

namespace Tests\Feature;

use App\Http\Controllers\B2C\CotizacionPublicaController;
use App\Models\B2cCotizacion;
use App\Models\User;
use App\Services\ZigoCommercialQuoteService;
use App\Services\ZigoProviderQuoteObservationService;
use App\Services\ZigoProviderRateService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

final class B2cQuoteCheckoutAuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Schema::create('users', function (Blueprint $table): void {
            $table->id(); $table->string('name'); $table->string('email')->unique();
            $table->string('apellido_paterno')->nullable(); $table->unsignedBigInteger('empresa_id')->nullable();
            $table->string('password'); $table->rememberToken(); $table->timestamps();
        });
        Schema::create('network_tenant_domains', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('network_tenant_id');
            $table->string('domain')->unique(); $table->string('status'); $table->timestamps();
        });
        Schema::create('roles', function (Blueprint $table): void {
            $table->id(); $table->string('slug'); $table->timestamps();
        });
        Schema::create('users_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id'); $table->unsignedBigInteger('roles_id');
        });
        Schema::create('b2c_cotizaciones', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('user_id')->nullable();
            $table->string('referencia')->nullable(); $table->string('service_code')->nullable();
            $table->string('quote_request_fingerprint')->nullable();
            $table->string('cp_origen')->nullable(); $table->string('cp_destino')->nullable();
            $table->string('colonia_origen')->nullable(); $table->string('colonia_destino')->nullable();
            $table->string('ciudad_origen')->nullable(); $table->string('ciudad_destino')->nullable();
            $table->string('estado_origen')->nullable(); $table->string('estado_destino')->nullable();
            $table->string('tipo_envio')->nullable(); $table->decimal('peso',10,2)->nullable();
            $table->string('medidas')->nullable(); $table->string('logistico')->nullable();
            $table->string('servicio')->nullable(); $table->decimal('precio',10,2)->nullable();
            $table->decimal('precio_sin_seguro',10,2)->nullable();
            $table->decimal('seguro_monto',10,2)->default(0); $table->boolean('requiere_seguro_envio')->default(false);
            $table->decimal('valor_declarado',10,2)->default(0); $table->decimal('seguro_porcentaje',10,2)->default(0);
            $table->decimal('seguro_iva_porcentaje',10,2)->default(0); $table->string('estatus')->nullable();
            foreach (['remitente_nombre','remitente_telefono','remitente_email','remitente_direccion','remitente_num_ext','remitente_num_int',
                'destinatario_nombre','destinatario_telefono','destinatario_email','destinatario_direccion','destinatario_num_ext','destinatario_num_int','contenido'] as $field) {
                $table->string($field)->nullable();
            }
            $table->timestamps();
        });
        Schema::create('sepomex', fn (Blueprint $table) => $table->string('d_codigo')->nullable());
        Schema::create('b2c_saldos', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('user_id'); $table->decimal('saldo',10,2)->default(0); $table->timestamps();
        });
        Schema::create('b2c_direcciones', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('user_id'); $table->boolean('activo')->default(true);
            $table->string('tipo'); $table->string('cp'); $table->boolean('favorita')->default(false);
            $table->boolean('principal')->default(false); $table->timestamps();
        });
    }

    public function test_box_checkout_requires_authentication_without_route_middleware(): void
    {
        $quote = B2cCotizacion::create(['referencia' => 'LANDING_PUBLICA','tipo_envio'=>'caja']);
        $this->get(route('b2c.checkout', $quote))->assertRedirect(route('login'));
        $this->withSession(['_token' => 'test-csrf'])
            ->post(route('b2c.checkout.procesar', $quote), ['_token' => 'test-csrf'])
            ->assertRedirect(route('login'));
    }

    public function test_intended_checkout_claims_public_quote_after_login(): void
    {
        $quote = B2cCotizacion::create([
            'referencia' => 'LANDING_PUBLICA', 'service_code' => 'terrestre',
            'cp_origen' => '09800', 'cp_destino' => '57820', 'tipo_envio' => 'caja',
            'peso' => 1, 'logistico' => 'Estafeta', 'servicio' => 'Terrestre', 'precio' => 260.56,
        ]);
        $user = User::create([
            'name' => 'Cliente', 'email' => 'cliente@example.test',
            'password' => Hash::make('password-test'),
        ]);
        $roleId = DB::table('roles')->insertGetId(['slug'=>'cliente','created_at'=>now(),'updated_at'=>now()]);
        DB::table('users_roles')->insert(['user_id'=>$user->id,'roles_id'=>$roleId]);
        $metadata = Mockery::mock(ZigoProviderQuoteObservationService::class);
        $metadata->shouldReceive('selectionMetadata')->once()->andReturn([
            'estimated_delivery_date' => '2026-08-06', 'periodicity_name' => 'Diaria',
            'zone_code' => '1', 'is_reexpedition' => false,
        ]);
        $this->app->instance(ZigoProviderQuoteObservationService::class, $metadata);

        $this->withSession(['b2c_pending_checkout_id' => $quote->id])
            ->get(route('b2c.checkout', $quote))
            ->assertRedirect(route('login'));
        $csrf = session()->token();
        $this->post('/login', [
            'email' => 'cliente@example.test', 'password' => 'password-test',
            '_token' => $csrf,
        ])->assertRedirect(route('b2c.checkout', $quote));
        $this->get(route('b2c.checkout', $quote))
            ->assertOk()->assertSee('06/08/2026')->assertSee('Diaria')->assertSee('Regular');

        $this->assertSame($user->id, (int) $quote->fresh()->user_id);
        $this->assertSame(260.56, (float) $quote->fresh()->precio);
    }

    public function test_registration_returns_to_selected_box_checkout(): void
    {
        $quote = B2cCotizacion::create([
            'referencia'=>'LANDING_PUBLICA','tipo_envio'=>'caja','service_code'=>'terrestre',
            'quote_request_fingerprint'=>'register-fp','cp_origen'=>'09800','cp_destino'=>'57820',
        ]);
        DB::table('roles')->insert(['slug'=>'cliente','created_at'=>now(),'updated_at'=>now()]);
        $metadata = Mockery::mock(ZigoProviderQuoteObservationService::class);
        $metadata->shouldReceive('selectionMetadata')->once()->andReturn([]);
        $this->app->instance(ZigoProviderQuoteObservationService::class, $metadata);

        $this->withSession([
            'b2c_pending_checkout_id'=>$quote->id,
            'url.intended'=>route('b2c.checkout',$quote),
            'tipo_envio'=>'caja','_token'=>'register-csrf',
        ])->post(route('b2c.register.store'), [
            '_token'=>'register-csrf','name'=>'Nueva','apellido_paterno'=>'Cuenta',
            'email'=>'nueva@example.test','password'=>'password-test','password_confirmation'=>'password-test',
        ])->assertRedirect(route('b2c.checkout',$quote));

        $this->get(route('b2c.checkout',$quote))->assertOk();
        $this->assertNotNull($quote->fresh()->user_id);
    }

    public function test_guest_can_open_and_process_own_envelope_checkout(): void
    {
        $quote = B2cCotizacion::create([
            'referencia'=>'LANDING_PUBLICA','tipo_envio'=>'sobre','service_code'=>'terrestre',
            'quote_request_fingerprint'=>'envelope-fp','cp_origen'=>'09800','cp_destino'=>'57820',
            'ciudad_origen'=>'CDMX','estado_origen'=>'CDMX','ciudad_destino'=>'Puebla','estado_destino'=>'Puebla',
            'peso'=>1,'precio'=>260.56,'precio_sin_seguro'=>260.56,
        ]);
        $metadata = Mockery::mock(ZigoProviderQuoteObservationService::class);
        $metadata->shouldReceive('selectionMetadata')->once()->andReturn([]);
        $this->app->instance(ZigoProviderQuoteObservationService::class, $metadata);
        $session = ['_token'=>'envelope-csrf','b2c_public_checkout'=>['id'=>$quote->id,'fingerprint'=>'envelope-fp','tipo_envio'=>'sobre']];

        $this->withSession($session)->get(route('b2c.checkout',$quote))->assertOk();
        $response = $this->withSession($session)->post(route('b2c.checkout.procesar',$quote), [
            '_token'=>'envelope-csrf',
            'remitente_nombre'=>'Origen','remitente_telefono'=>'5555555555','remitente_email'=>'origen@example.test',
            'remitente_direccion'=>'Calle 1','remitente_num_ext'=>'1','ciudad_origen'=>'CDMX','estado_origen'=>'CDMX',
            'destinatario_nombre'=>'Destino','destinatario_telefono'=>'5555555556','destinatario_email'=>'destino@example.test',
            'destinatario_direccion'=>'Calle 2','destinatario_num_ext'=>'2','ciudad_destino'=>'Puebla','estado_destino'=>'Puebla',
            'contenido'=>'Documentos','valor_declarado'=>0,
        ]);
        $response->assertRedirect(route('b2c.pago',$quote));
        $this->assertSame(260.56,(float)$quote->fresh()->precio);
        $this->assertNull($quote->fresh()->user_id);
    }

    public function test_guest_cannot_change_id_to_open_another_envelope(): void
    {
        $mine = B2cCotizacion::create(['referencia'=>'LANDING_PUBLICA','tipo_envio'=>'sobre','quote_request_fingerprint'=>'mine']);
        $other = B2cCotizacion::create(['referencia'=>'LANDING_PUBLICA','tipo_envio'=>'sobre','quote_request_fingerprint'=>'other']);
        $session = ['b2c_public_checkout'=>['id'=>$mine->id,'fingerprint'=>'mine','tipo_envio'=>'sobre']];
        $this->withSession($session)->get(route('b2c.checkout',$other))->assertForbidden();
    }

    public function test_owner_can_open_checkout_and_other_user_is_forbidden(): void
    {
        $box = B2cCotizacion::create(['user_id'=>10,'referencia'=>'LANDING_PUBLICA','tipo_envio'=>'caja']);
        $envelope = B2cCotizacion::create(['user_id'=>10,'referencia'=>'LANDING_PUBLICA','tipo_envio'=>'sobre']);
        $owner = new User(); $owner->id = 10; $owner->exists = true;
        $other = new User(); $other->id = 11; $other->exists = true;
        $metadata = Mockery::mock(ZigoProviderQuoteObservationService::class);
        $metadata->shouldReceive('selectionMetadata')->twice()->andReturn([]);
        $this->app->instance(ZigoProviderQuoteObservationService::class, $metadata);

        $this->actingAs($owner)->get(route('b2c.checkout',$box))->assertOk();
        $this->actingAs($owner)->get(route('b2c.checkout',$envelope))->assertOk();
        $this->actingAs($other)->get(route('b2c.checkout',$box))->assertForbidden();
    }

    public function test_guest_selecting_box_is_sent_to_login_with_intended_checkout(): void
    {
        $quote = Mockery::mock(B2cCotizacion::class)->makePartial();
        $quote->id = 88; $quote->tipo_envio = 'caja'; $quote->referencia = 'LANDING_PUBLICA';
        $quote->requiere_seguro_envio = false; $quote->seguro_monto = 0;
        $quote->shouldReceive('update')->once()->andReturnTrue();

        $base = ['logistico'=>'Estafeta','servicio'=>'Terrestre','service_code'=>'terrestre','base_price'=>116,
            'provider_total'=>116,'provider_source'=>'xperta','quote_source'=>'xperta_estafeta','request_fingerprint'=>'fp',
            'quote_expires_at'=>now()->addMinutes(30),'estimated_delivery_date'=>'2026-08-06','zone_code'=>'1',
            'periodicity_name'=>'Diaria','operating_days'=>['lunes'],'is_reexpedition'=>false,'is_ocurre'=>false,
            'restriction'=>false,'restriction_description'=>''];
        $provider = Mockery::mock(ZigoProviderRateService::class);
        $provider->shouldReceive('getOptionsForCotizacion')->once()->andReturn([$base]);
        $this->app->instance(ZigoProviderRateService::class, $provider);
        $commercial = Mockery::mock(ZigoCommercialQuoteService::class);
        $commercial->shouldReceive('calculate')->once()->andReturn(['pricing'=>$this->pricing(),'final_price'=>260.56]);
        $this->app->instance(ZigoCommercialQuoteService::class, $commercial);
        $snapshot = Mockery::mock(ZigoProviderQuoteObservationService::class);
        $snapshot->shouldReceive('attachSelectionMetadata')->once();
        $this->app->instance(ZigoProviderQuoteObservationService::class, $snapshot);

        $response = (new CotizacionPublicaController())->seleccionar(
            Request::create('/', 'POST', ['logistico'=>'Estafeta','servicio'=>'Terrestre']), $quote
        );

        $this->assertSame(route('login'), $response->getTargetUrl());
        $this->assertSame(88, session('b2c_pending_checkout_id'));
        $this->assertSame(route('b2c.checkout', 88), session('url.intended'));
        $this->assertSame('caja', session('tipo_envio'));
    }

    private function pricing(): array
    {
        return ['base_price'=>116.0,'final_price'=>260.56,'margin_percentage'=>0,'fixed_fee'=>0,'margin_amount'=>0,
            'adjustment_type'=>null,'adjustment_value'=>0,'adjustment_amount'=>0,'discount_type'=>null,
            'discount_value'=>0,'discount_amount'=>0,'profit_amount'=>144.56,'customer_segment'=>'anonymous',
            'pricing_rule_id'=>null,'adjustment_id'=>null,'client_pricing_rule_id'=>null];
    }
}
