<?php

namespace Tests\Feature;

use App\Domain\Network\Billing\Models\{Entitlement, Subscription};
use App\Domain\Network\Catalog\Models\{Module, Plan};
use App\Domain\Network\Channels\B2C\Models\{TenantCustomerProfile, TenantOperation};
use App\Domain\Network\Channels\B2C\Models\TenantCustomerCheckout;
use App\Domain\Network\Channels\B2C\Models\TenantCustomerAddress;
use App\Domain\Support\Models\SupportTicket;
use App\Domain\Network\Channels\B2C\CustomerCheckoutService;
use App\Domain\Network\Channels\B2C\CustomerCheckoutFulfillmentService;
use App\Domain\Shipping\LastMile\Models\TenantDeliveryProofOption;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Shipping\Local\Models\LocalShipment;
use App\Domain\Shipping\Local\Models\LocalShippingQuoteSnapshot;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Hash, Schema};
use Tests\TestCase;

class ZigoCustomerPortalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') $this->markTestSkipped('Requires SQLite :memory:.');
        $this->schema();
        DB::table('zigo_postal_codes')->insert([
            ['codigo_postal'=>'64000','asentamiento'=>'Centro','tipo_asentamiento'=>'Colonia','municipio'=>'Monterrey','estado'=>'Nuevo León','ciudad'=>'Monterrey','activo'=>true],
            ['codigo_postal'=>'57300','asentamiento'=>'Benito Juárez','tipo_asentamiento'=>'Colonia','municipio'=>'Nezahualcóyotl','estado'=>'México','ciudad'=>'Nezahualcóyotl','activo'=>true],
        ]);
    }

    public function test_tenant_root_is_branded_and_unknown_or_platform_hosts_are_not_hijacked(): void
    {
        $tenant = $this->tenant('pilot');
        $tenant->branding()->create(['brand_name' => 'RapidGo Local', 'primary_color' => '#2457D6']);
        $this->get($this->url($tenant, '/'))->assertOk()->assertSee('Envía fácil con')->assertSee('RapidGo Local')->assertSee('Powered by ZIGO');
        $this->get('http://unknown.zigo.local/')->assertNotFound();
        $this->get('http://'.config('zigo_driver.host').'/')->assertNotFound();
    }

    public function test_registration_reuses_global_user_and_creates_no_membership(): void
    {
        $tenant = $this->tenant('register');
        $user = User::create(['name' => 'Ana', 'email' => 'ana@example.test', 'password' => Hash::make('Password!123'), 'empresa_id' => 1]);
        $payload = ['name' => 'Ana Cliente', 'email' => 'ana@example.test', 'password' => 'Password!123', 'password_confirmation' => 'Password!123', 'terms' => '1'];
        $this->post($this->url($tenant, '/registro'), $payload)->assertRedirect('/app');
        $this->assertSame(1, User::where('email', 'ana@example.test')->count());
        $this->assertDatabaseHas('tenant_customer_profiles', ['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active']);
        $this->assertDatabaseMissing('network_tenant_memberships', ['tenant_id' => $tenant->id, 'user_id' => $user->id]);
    }

    public function test_login_requires_profile_for_current_tenant_and_cross_tenant_is_blocked(): void
    {
        $a = $this->tenant('customer-a'); $b = $this->tenant('customer-b');
        $user = User::create(['name' => 'Customer', 'email' => 'customer@example.test', 'password' => Hash::make('secret-pass'), 'empresa_id' => 1]);
        TenantCustomerProfile::create(['tenant_id' => $a->id, 'user_id' => $user->id, 'status' => 'active']);
        $this->post($this->url($b, '/ingresar'), ['email' => $user->email, 'password' => 'secret-pass'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post($this->url($a, '/ingresar'), ['email' => $user->email, 'password' => 'secret-pass'])->assertRedirect('/app');
        $this->get($this->url($b, '/app'))->assertForbidden();
        $this->get($this->url($a, '/admin'))->assertRedirect();
        $this->get($this->url($a, '/driver'))->assertRedirect('/driver/login');
        $this->get($this->url($a, '/network'))->assertRedirect('/network/login');
    }

    public function test_anonymous_selected_quote_survives_registration_without_duplicate_operation(): void
    {
        $tenant=$this->tenant('anonymous-journey');
        $operation=TenantOperation::create(['tenant_id'=>$tenant->id,'subscription_id'=>$tenant->subscriptions()->first()->id,'channel'=>'b2c','status'=>'quoted','provider'=>'ZIGO_LOCAL','service_code'=>'LOCAL','metadata'=>['selected_quote'=>['service'=>'Local','price'=>100],'quoted_package'=>['type'=>'sobre','weight'=>1]]]);
        $this->withSession(['tenant_customer.pending_quote'=>['tenant_id'=>$tenant->id,'operation_uuid'=>$operation->uuid]])->post($this->url($tenant,'/registro'),['name'=>'Nuevo','email'=>'new-journey@example.test','password'=>'Password!123','password_confirmation'=>'Password!123','terms'=>'1'])->assertRedirect('/app/envio/nuevo')->assertSessionHas('tenant_customer.active_operation',$operation->uuid);
        $this->assertDatabaseCount('network_tenant_operations',1); $this->assertNotNull($operation->fresh()->customer_profile_id);
    }

    public function test_customer_dashboard_and_shipments_only_show_owned_records(): void
    {
        $tenant = $this->tenant('ownership');
        $owner = $this->customer($tenant, 'owner@example.test'); $other = $this->customer($tenant, 'other@example.test');
        $own = $this->shipment($tenant, $owner, 'ZLCUSTOMEROWN001', 'OUT_FOR_DELIVERY');
        $foreign = $this->shipment($tenant, $other, 'ZLCUSTOMEROTHER1', 'DELIVERED');
        $this->actingAs($owner->user)->get($this->url($tenant, '/app'))->assertOk()->assertSee($own->tracking_number)->assertDontSee($foreign->tracking_number)->assertSee('En reparto')->assertDontSee('OUT_FOR_DELIVERY')
            ->assertSee('Pendientes de pago')->assertSee('Nuevo envío')->assertSee('href="/app/cotizar"', false)->assertSee('customer-links', false)
            ->assertDontSee('class="z-card z-stack tenant-quote-form"', false)->assertDontSee('data-settlement-wrap="destination"', false);
        $this->get($this->url($tenant, '/app/cotizar'))->assertOk()->assertSee('Datos de origen')->assertSee('Datos de destino')
            ->assertSee('data-complete-quote', false)->assertSee('Peso volumétrico')->assertSee('Guardar esta dirección')
            ->assertSee('name="origin_city"', false)->assertSee('name="destination_state"', false)
            ->assertDontSee('name="pickup_requested"', false)->assertSee("municipalityField.value=''", false);
        $this->get($this->url($tenant, '/app/envios'))->assertOk()->assertSee($own->tracking_number)->assertDontSee($foreign->tracking_number)->assertSee('customer-shipment-card', false);
        $this->get($this->url($tenant, '/app/envios/'.$own->uuid))->assertOk()->assertSee('Secret address')->assertSeeInOrder(['Rastrear','Descargar guía','Ayuda']);
        $senderSnapshot = $own->sender_snapshot;
        $guide = $this->get($this->url($tenant, '/app/envios/'.$own->uuid.'/guia.pdf'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $guide->getContent());
        $this->assertSame($senderSnapshot, $own->fresh()->sender_snapshot);
        $this->get($this->url($tenant, '/app/envios/'.$foreign->uuid))->assertNotFound();
        $this->get($this->url($tenant, '/app/envios/'.$foreign->uuid.'/guia.pdf'))->assertNotFound();
        $foreignTenant = $this->tenant('ownership-foreign');
        $foreignProfile = $this->customer($foreignTenant, 'ownership-foreign@example.test');
        $foreignTenantShipment = $this->shipment($foreignTenant, $foreignProfile, 'ZLCUSTOMERFOREIGN', 'CREATED');
        $this->get($this->url($tenant, '/app/envios/'.$foreignTenantShipment->uuid.'/guia.pdf'))->assertNotFound();
    }

    public function test_public_tracking_is_private_and_spanish_route_matches_legacy(): void
    {
        $tenant = $this->tenant('tracking'); $profile = $this->customer($tenant, 'track@example.test');
        $shipment = $this->shipment($tenant, $profile, 'ZLPRIVACYTRACK01', 'OUT_FOR_DELIVERY');
        $shipment->events()->create(['status' => 'OUT_FOR_DELIVERY', 'event_code' => 'PRIVATE', 'description' => 'Private internal note', 'occurred_at' => now()]);
        foreach (['/tracking/', '/rastreo/'] as $prefix) {
            $response = $this->get($this->url($tenant, $prefix.$shipment->tracking_number))->assertOk()->assertSee('En reparto');
            foreach (['Secret address', '8112345678', 'Private internal note', 'provider_cost'] as $private) $response->assertDontSee($private);
        }
    }

    public function test_customer_private_tracking_is_owned_and_uses_customer_navigation(): void
    {
        $tenant = $this->tenant('private-customer-tracking');
        $owner = $this->customer($tenant, 'private-track-owner@example.test');
        $other = $this->customer($tenant, 'private-track-other@example.test');
        $shipment = $this->shipment($tenant, $owner, 'ZLPRIVATECUSTOMER1', 'IN_TRANSIT');

        $this->actingAs($owner->user)->get($this->url($tenant, '/app/envios/'.$shipment->uuid.'/tracking'))
            ->assertOk()->assertSee('Volver a mis envíos')->assertDontSee('Consultar otra guía');
        $this->actingAs($other->user)->get($this->url($tenant, '/app/envios/'.$shipment->uuid.'/tracking'))->assertNotFound();

        $foreignTenant = $this->tenant('private-customer-foreign');
        $foreignCustomer = $this->customer($foreignTenant, 'private-track-foreign@example.test');
        $this->actingAs($foreignCustomer->user)->get($this->url($foreignTenant, '/app/envios/'.$shipment->uuid.'/tracking'))->assertNotFound();

        $this->actingAs($owner->user)->get($this->url($tenant, '/app/envios/'.$shipment->uuid))
            ->assertOk()->assertSee('/app/envios/'.$shipment->uuid.'/tracking', false)
            ->assertSee('shipment-grid', false)->assertSee('@media(max-width:767px)', false)
            ->assertSee('z-card tracking-history', false)->assertSee('z-card shipment-summary', false);
    }

    public function test_customer_tracking_hub_requires_customer_and_lists_only_owned_shipments(): void
    {
        $tenant = $this->tenant('tracking-hub');
        $owner = $this->customer($tenant, 'hub-owner@example.test');
        $other = $this->customer($tenant, 'hub-other@example.test');
        $own = $this->shipment($tenant, $owner, 'ZLHUBOWNER000001', 'PICKED_UP');
        $foreign = $this->shipment($tenant, $other, 'ZLHUBOTHER000001', 'READY_FOR_PICKUP');

        $this->get($this->url($tenant, '/app/rastrear'))->assertRedirect('/ingresar');
        $response = $this->actingAs($owner->user)->get($this->url($tenant, '/app/rastrear'))
            ->assertOk()->assertSee('Tus envíos')->assertSee($own->tracking_number)
            ->assertDontSee($foreign->tracking_number)->assertSee('Recolectado')
            ->assertSee('/app/envios/'.$own->uuid.'/tracking', false);
        $this->assertSame(1, substr_count($response->getContent(), 'Ver rastreo'));

        $this->get($this->url($tenant, '/app'))->assertOk()
            ->assertSee('href="/app/rastrear"', false);
        $this->get($this->url($tenant, '/app/envios/'.$own->uuid.'/tracking'))
            ->assertOk()->assertSee('customer-header', false)->assertDontSee('Consultar otra guía');
    }

    public function test_known_tracking_statuses_use_canonical_labels_on_customer_admin_and_public_surfaces(): void
    {
        $tenant = $this->tenant('canonical-statuses');
        $profile = $this->customer($tenant, 'canonical-status@example.test');
        $shipment = $this->shipment($tenant, $profile, 'ZL260813TCYIATPU7U', 'PICKED_UP');
        $shipment->events()->delete();
        foreach ([
            ['CREATED', '2026-08-13 18:40:00'],
            ['READY_FOR_PICKUP', '2026-08-14 15:12:00'],
            ['PICKED_UP', '2026-08-14 22:36:00'],
        ] as [$status, $occurredAt]) {
            $shipment->events()->create(['status' => $status, 'event_code' => $status, 'occurred_at' => $occurredAt]);
        }
        $tenant->memberships()->create(['user_id' => $profile->user_id, 'role' => 'owner', 'status' => 'active']);

        foreach (['/app/envios/'.$shipment->uuid, '/app/envios/'.$shipment->uuid.'/tracking'] as $path) {
            $this->actingAs($profile->user)->get($this->url($tenant, $path))->assertOk()
                ->assertSee('Recolectado')->assertSee('Recolección solicitada')->assertSee('Envío creado')
                ->assertDontSee('Actualización de envío');
        }
        $this->actingAs($profile->user)->get($this->url($tenant, '/admin/operations/'.$shipment->operation->uuid.'/tracking'))
            ->assertOk()->assertSee('TENANT ADMIN')->assertSee('Recolectado')
            ->assertSee('Recolección solicitada')->assertSee('Envío creado')->assertDontSee('Actualización de envío');
        $this->get($this->url($tenant, '/rastreo/'.$shipment->tracking_number))->assertOk()
            ->assertSee('Recolectado')->assertSee('Recolección solicitada')->assertSee('Envío creado')
            ->assertSee('Consultar otra guía')->assertDontSee('Actualización de envío')
            ->assertDontSee('customer-header', false)->assertDontSee('Mis direcciones');

        $this->assertSame(
            'https://canonical-statuses.zigo.local/rastreo/ZL260813TCYIATPU7U',
            app(\App\Domain\Shipping\Local\LocalGuideService::class)->trackingUrl($shipment, 'canonical-statuses.zigo.local')
        );
    }

    public function test_service_selection_is_session_bound_and_inactive_proof_is_hidden(): void
    {
        $tenant = $this->tenant('selection'); $profile = $this->customer($tenant, 'select@example.test');
        $operation = TenantOperation::create(['tenant_id' => $tenant->id, 'subscription_id' => $tenant->subscriptions()->first()->id, 'channel' => 'b2c', 'status' => 'quoted', 'metadata' => ['final_price' => 100]]);
        $snapshot = LocalShippingQuoteSnapshot::create([
            'tenant_id' => $tenant->id, 'origin' => ['address' => 'Origen 1, Centro, 64000 Monterrey, Nuevo León, México', 'postal_code' => '64000'],
            'destination' => ['address' => 'Destino 2, Centro, 64000 Monterrey, Nuevo León, México', 'postal_code' => '64000'],
            'package_type' => 'sobre', 'weight_kg' => 1, 'dimensions' => null, 'distance_meters' => null,
            'pricing_strategy' => 'FLAT', 'matched_tariff' => ['amount' => '120.00'], 'amount' => '120.00', 'currency' => 'MXN', 'expires_at' => now()->addMinutes(30),
        ]);
        $session = ['tenant_customer.quote_result' => ['tenant_id' => $tenant->id, 'operation_uuid' => $operation->uuid, 'options' => [['snapshot_uuid' => $snapshot->uuid, 'provider' => 'ZIGO_LOCAL', 'service_code' => 'LOCAL', 'service' => 'Mismo día', 'price' => 999, 'currency' => 'MXN']]]];
        $this->actingAs($profile->user)->withSession($session)->post($this->url($tenant, '/app/cotizar/seleccionar'), ['operation_uuid' => $operation->uuid, 'option' => 0])->assertRedirect('/app/envio/nuevo');
        $this->assertSame($profile->id, $operation->fresh()->customer_profile_id);
        $this->assertSame('LOCAL', $operation->fresh()->service_code);
        $this->assertSame($tenant->id, $snapshot->tenant_id);
        $this->assertSame('120.00', $operation->fresh()->metadata['selected_quote']['price']);
        $this->assertSame($snapshot->uuid, $operation->fresh()->metadata['selected_quote_snapshot_uuid']);
        $this->assertDatabaseCount('network_usage_events', 0);
        DB::table('tenant_delivery_proof_options')->insert([
            ['uuid' => '10000000-0000-4000-8000-000000000001', 'tenant_id' => $tenant->id, 'code' => 'ACTIVE', 'name' => 'Firma al recibir', 'receiver_policy' => 'ANY_PERSON_AT_ADDRESS', 'max_delivery_attempts' => 3, 'currency' => 'MXN', 'is_active' => 1, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['uuid' => '10000000-0000-4000-8000-000000000002', 'tenant_id' => $tenant->id, 'code' => 'INACTIVE', 'name' => 'No ofrecer', 'receiver_policy' => 'ANY_PERSON_AT_ADDRESS', 'max_delivery_attempts' => 3, 'currency' => 'MXN', 'is_active' => 0, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->get($this->url($tenant, '/app/evidencia'))->assertOk()->assertSee('Firma al recibir')->assertDontSee('No ofrecer');
    }

    public function test_customer_logout_invalidates_session(): void
    {
        $tenant = $this->tenant('logout'); $profile = $this->customer($tenant, 'logout@example.test');
        $this->actingAs($profile->user)->post($this->url($tenant, '/salir'))->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_customer_address_crud_derives_sepomex_defaults_and_is_owner_scoped(): void
    {
        $tenant=$this->tenant('addresses');$owner=$this->customer($tenant,'address-owner@example.test');$other=$this->customer($tenant,'address-other@example.test');
        $payload=$this->addressPayload(['alias'=>'Casa','is_default_origin'=>1]);
        $this->actingAs($owner->user)->get($this->url($tenant,'/app/direcciones'))->assertOk()->assertSee('Mis direcciones')->assertSee('address-card-grid', false)->assertDontSee('Drivers');
        $this->post($this->url($tenant,'/app/direcciones'),$payload)->assertRedirect();
        $address=TenantCustomerAddress::sole();
        $this->assertSame('Monterrey',$address->municipality);$this->assertSame('Nuevo León',$address->state);$this->assertTrue($address->is_default_origin);
        $this->post($this->url($tenant,'/app/direcciones'),$this->addressPayload(['alias'=>'Oficina','street'=>'Morelos','exterior'=>'20','is_default_origin'=>1]))->assertRedirect();
        $this->assertFalse($address->fresh()->is_default_origin);$this->assertSame(1,TenantCustomerAddress::where('is_default_origin',true)->count());
        $this->put($this->url($tenant,'/app/direcciones/'.$address->uuid),$this->addressPayload(['alias'=>'Casa actualizada','is_default_destination'=>1]))->assertRedirect();
        $this->assertSame('Casa actualizada',$address->fresh()->alias);$this->assertTrue($address->fresh()->is_default_destination);
        $this->actingAs($other->user)->get($this->url($tenant,'/app/direcciones/'.$address->uuid.'/editar'))->assertNotFound();
        $foreign=$this->tenant('addresses-foreign');$foreignProfile=$this->customer($foreign,'address-foreign@example.test');
        $this->actingAs($foreignProfile->user)->get($this->url($foreign,'/app/direcciones/'.$address->uuid.'/editar'))->assertNotFound();
        $this->actingAs($owner->user)->delete($this->url($tenant,'/app/direcciones/'.$address->uuid))->assertRedirect();
        $this->assertFalse($address->fresh()->is_active);
    }

    public function test_address_validation_rejects_invalid_postal_and_settlement(): void
    {
        $tenant=$this->tenant('address-validation');$profile=$this->customer($tenant,'address-validation@example.test');$this->actingAs($profile->user);
        $this->post($this->url($tenant,'/app/direcciones'),$this->addressPayload(['postal_code'=>'00000']))->assertSessionHasErrors('postal_code');
        $this->post($this->url($tenant,'/app/direcciones'),$this->addressPayload(['settlement'=>'Manipulada']))->assertSessionHasErrors('settlement');
        $this->assertDatabaseCount('tenant_customer_addresses',0);
    }

    public function test_customer_help_and_tenant_support_keep_customer_tenant_and_zigo_channels_separate(): void
    {
        $tenant=$this->tenant('support-owner');$customer=$this->customer($tenant,'support-owner@example.test');$other=$this->customer($tenant,'support-other@example.test');
        $foreignTenant=$this->tenant('support-foreign');$foreignCustomer=$this->customer($foreignTenant,'support-foreign@example.test');
        $own=SupportTicket::create(['tenant_id'=>$tenant->id,'customer_profile_id'=>$customer->id,'requester_user_id'=>$customer->user_id,'requester_type'=>'CUSTOMER','scope'=>'TENANT_OPERATIONAL','category'=>'DELIVERY','priority'=>'NORMAL','status'=>'OPEN','subject'=>'Ticket propio','description'=>'Ayuda propia']);
        $otherTicket=SupportTicket::create(['tenant_id'=>$tenant->id,'customer_profile_id'=>$other->id,'requester_user_id'=>$other->user_id,'requester_type'=>'CUSTOMER','scope'=>'TENANT_OPERATIONAL','category'=>'DELIVERY','priority'=>'NORMAL','status'=>'OPEN','subject'=>'Ticket ajeno','description'=>'No visible']);
        $foreignTicket=SupportTicket::create(['tenant_id'=>$foreignTenant->id,'customer_profile_id'=>$foreignCustomer->id,'requester_user_id'=>$foreignCustomer->user_id,'requester_type'=>'CUSTOMER','scope'=>'TENANT_OPERATIONAL','category'=>'DELIVERY','priority'=>'NORMAL','status'=>'OPEN','subject'=>'Ticket otro tenant','description'=>'No visible']);
        $this->actingAs($customer->user)->get($this->url($tenant,'/app/ayuda'))->assertOk()->assertSee('Centro de ayuda de')->assertSee('Ticket propio')->assertDontSee('Ticket ajeno')->assertDontSee('Ticket otro tenant')->assertDontSee('Soporte ZIGO');
        $this->get($this->url($tenant,'/app/ayuda/tickets/'.$otherTicket->uuid))->assertNotFound();
        $this->post($this->url($tenant,'/app/ayuda/tickets'),['category'=>'OTHER','subject'=>'Nueva solicitud','description'=>'Necesito ayuda'])->assertRedirect();
        $this->assertDatabaseHas('support_tickets',['tenant_id'=>$tenant->id,'customer_profile_id'=>$customer->id,'requester_type'=>'CUSTOMER','scope'=>'TENANT_OPERATIONAL','subject'=>'Nueva solicitud']);
        $owner=$this->customer($tenant,'support-admin@example.test')->user;$tenant->memberships()->create(['user_id'=>$owner->id,'role'=>'owner','status'=>'active']);
        $this->actingAs($owner)->get($this->url($tenant,'/admin/soporte'))->assertOk()->assertSee('Ticket propio')->assertDontSee('Ticket otro tenant');
        $this->get($this->url($tenant,'/admin/soporte/'.$foreignTicket->uuid))->assertNotFound();
        $this->post($this->url($tenant,'/admin/soporte/'.$own->uuid.'/respuestas'),['message'=>'Respuesta del tenant'])->assertRedirect();
        $this->assertDatabaseHas('support_ticket_messages',['ticket_id'=>$own->id,'author_user_id'=>$owner->id,'visibility'=>'PUBLIC','message'=>'Respuesta del tenant']);
        $this->get($this->url($tenant,'/admin/soporte/zigo'))->assertOk()->assertSee('ZIGO');
    }

    public function test_saved_address_options_respect_type_route_and_journey_save_is_deduplicated(): void
    {
        $tenant=$this->tenant('address-journey');$profile=$this->customer($tenant,'address-journey@example.test');$this->actingAs($profile->user);
        foreach ([['Origen guardado','origin','Origen','1'],['Destino guardado','destination','Destino','2'],['Ambos','both','Común','3']] as [$alias,$type,$street,$exterior]) {
            $this->post($this->url($tenant,'/app/direcciones'),$this->addressPayload(compact('alias','street','exterior')+['address_type'=>$type]))->assertRedirect();
        }
        [$operation]=$this->quotedOperation($tenant,$profile);$session=['tenant_customer.active_operation'=>$operation->uuid];
        $shipping=$this->withSession($session)->get($this->url($tenant,'/app/envio/nuevo'))->assertOk();
        $shipping->assertSee('Origen guardado')->assertSee('Destino guardado')->assertSee('Ambos');
        $incompatible=TenantCustomerAddress::create(['tenant_id'=>$tenant->id,'customer_profile_id'=>$profile->id]+$this->addressPayload(['alias'=>'Otra ruta','address_type'=>'origin','postal_code'=>'57300','settlement'=>'Benito Juárez'])+['municipality'=>'Nezahualcóyotl','state'=>'México','is_active'=>true]);
        $this->withSession($session)->post($this->url($tenant,'/app/envio/nuevo'),$this->shippingPayload()+['sender_address_uuid'=>$incompatible->uuid])->assertSessionHasErrors('sender_address_uuid');
        $payload=$this->shippingPayload()+['save_sender_address'=>1,'sender_address_alias'=>'Nueva casa','save_recipient_address'=>1,'recipient_address_alias'=>'Nuevo destino'];
        $this->withSession($session)->post($this->url($tenant,'/app/envio/nuevo'),$payload)->assertRedirect();
        $this->assertDatabaseHas('tenant_customer_addresses',['tenant_id'=>$tenant->id,'customer_profile_id'=>$profile->id,'alias'=>'Nueva casa']);
        $count=TenantCustomerAddress::where('tenant_id',$tenant->id)->where('customer_profile_id',$profile->id)->count();
        $this->withSession($session)->post($this->url($tenant,'/app/envio/nuevo'),$payload)->assertRedirect();
        $this->assertSame($count,TenantCustomerAddress::where('tenant_id',$tenant->id)->where('customer_profile_id',$profile->id)->count());
    }

    public function test_checkout_amounts_are_server_calculated_and_duplicate_submit_is_idempotent(): void
    {
        $tenant = $this->tenant('checkout'); $profile = $this->customer($tenant, 'checkout@example.test');
        $operation = TenantOperation::create(['tenant_id'=>$tenant->id,'subscription_id'=>$tenant->subscriptions()->first()->id,'customer_profile_id'=>$profile->id,'channel'=>'b2c','status'=>'quoted','provider'=>'ZIGO_LOCAL','service_code'=>'LOCAL','metadata'=>[
            'origin_postal_code'=>'64000','destination_postal_code'=>'64000','quoted_package'=>['type'=>'sobre','weight'=>1],
            'selected_quote'=>['service'=>'Mismo día','price'=>120,'currency'=>'MXN'],'final_price'=>999999,
            'shipping_data'=>['sender'=>['name'=>'A','phone'=>'1','address'=>'A','postal_code'=>'64000'],'recipient'=>['name'=>'B','phone'=>'2','address'=>'B','postal_code'=>'64000'],'package'=>['type'=>'sobre','weight'=>1]],
        ]]);
        $proof = TenantDeliveryProofOption::create(['tenant_id'=>$tenant->id,'code'=>'SIGN','name'=>'Firma','require_receiver_name'=>true,'require_receiver_type'=>false,'require_signature'=>true,'require_photo'=>false,'require_gps'=>false,'receiver_policy'=>'RECIPIENT_ONLY','max_delivery_attempts'=>2,'surcharge_amount'=>15,'currency'=>'MXN','is_default'=>true,'is_active'=>true,'sort_order'=>1]);
        $service = app(CustomerCheckoutService::class);
        $first = $service->create($tenant,$profile,$operation,$proof); $second = $service->create($tenant,$profile,$operation,$proof);
        $this->assertTrue($first->is($second)); $this->assertSame('120.00',$first->shipping_amount); $this->assertSame('15.00',$first->evidence_amount); $this->assertSame('135.00',$first->subtotal_amount); $this->assertSame('0.1600',$first->tax_rate); $this->assertSame('21.60',$first->tax_amount); $this->assertSame('156.60',$first->total_amount);
        $this->assertSame('sobre',$first->quote_snapshot['package']['type']); $this->assertEquals(135.0,$first->quote_snapshot['subtotal']); $this->assertEquals(0.16,$first->quote_snapshot['tax_rate']); $this->assertEquals(21.6,$first->quote_snapshot['tax_amount']); $this->assertEquals(156.6,$first->quote_snapshot['total']); $this->assertDatabaseCount('tenant_customer_checkouts',1);
    }

    public function test_preliminary_quote_cannot_create_checkout_or_reach_payment(): void
    {
        $tenant = $this->tenant('preliminary-gate');
        $profile = $this->customer($tenant, 'preliminary-gate@example.test');
        [$operation] = $this->quotedOperation($tenant, $profile);
        $metadata = $operation->metadata;
        $metadata['selected_quote']['preliminary'] = true;
        $metadata['shipping_data'] = ['sender'=>[],'recipient'=>[],'package'=>[]];
        $operation->update(['metadata'=>$metadata]);

        try {
            app(CustomerCheckoutService::class)->create($tenant,$profile,$operation->fresh());
            $this->fail('A preliminary quote must not create a checkout.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(422,$exception->getStatusCode());
        }
        $this->assertDatabaseMissing('tenant_customer_checkouts',['tenant_operation_id'=>$operation->id]);
    }

    public function test_optional_evidence_summary_renders_without_evidence_and_keeps_server_total(): void
    {
        $tenant = $this->tenant('summary-no-proof');
        $profile = $this->customer($tenant, 'summary-no-proof@example.test');
        $checkout = $this->checkoutForView($tenant, $profile);

        $this->assertSame([], $checkout->proof_option_snapshot);
        $this->assertSame('179.00', $checkout->shipping_amount);
        $this->assertSame('0.00', $checkout->evidence_amount);
        $this->assertSame('179.00', $checkout->subtotal_amount);
        $this->assertSame('28.64', $checkout->tax_amount);
        $this->assertSame('207.64', $checkout->total_amount);
        $this->actingAs($profile->user)->get($this->url($tenant, '/app/checkout/'.$checkout->uuid.'/resumen'))
            ->assertOk()->assertSee('IVA (16%)')->assertSee('$28.64')->assertSee('TOTAL')->assertSee('$207.64 MXN')->assertDontSee('Evidencia');
    }

    public function test_optional_evidence_summary_renders_selected_evidence(): void
    {
        $tenant = $this->tenant('summary-with-proof');
        $profile = $this->customer($tenant, 'summary-with-proof@example.test');
        $proof = TenantDeliveryProofOption::create([
            'tenant_id' => $tenant->id, 'code' => 'PHOTO', 'name' => 'Fotografía de entrega',
            'receiver_policy' => 'ANY_PERSON_AT_ADDRESS', 'max_delivery_attempts' => 2,
            'surcharge_amount' => 25, 'currency' => 'MXN', 'is_active' => true, 'sort_order' => 1,
        ]);
        $checkout = $this->checkoutForView($tenant, $profile, $proof);

        $this->assertSame('204.00', $checkout->subtotal_amount);
        $this->assertSame('32.64', $checkout->tax_amount);
        $this->assertSame('236.64', $checkout->total_amount);
        $this->actingAs($profile->user)->get($this->url($tenant, '/app/checkout/'.$checkout->uuid.'/resumen'))
            ->assertOk()->assertSee('Evidencia')->assertSee('Fotografía de entrega')->assertSee('$25.00')->assertSee('$236.64 MXN');
    }

    public function test_optional_evidence_payment_renders_without_proof_or_misleading_copy(): void
    {
        $tenant = $this->tenant('payment-no-proof');
        $profile = $this->customer($tenant, 'payment-no-proof@example.test');
        $checkout = $this->checkoutForView($tenant, $profile);

        $this->actingAs($profile->user)->get($this->url($tenant, '/app/checkout/'.$checkout->uuid.'/pago'))
            ->assertOk()->assertSee('Servicio seleccionado')->assertSee('IVA (16%)')->assertSee('$28.64')->assertSee('$207.64 MXN')
            ->assertDontSee('Servicio y evidencia seleccionados');
    }

    public function test_store_shipping_creates_once_and_retries_redirect_to_immutable_checkout(): void
    {
        $tenant = $this->tenant('shipping-idempotent');
        $profile = $this->customer($tenant, 'shipping-idempotent@example.test');
        [$operation, $snapshot] = $this->quotedOperation($tenant, $profile);
        $session = ['tenant_customer.active_operation' => $operation->uuid];
        $payload = $this->shippingPayload();

        $first = $this->actingAs($profile->user)->withSession($session)->post($this->url($tenant, '/app/envio/nuevo'), $payload);
        $first->assertRedirect();
        $checkout = TenantCustomerCheckout::sole();
        $first->assertRedirect('/app/checkout/'.$checkout->uuid.'/resumen');
        $this->assertSame(1, TenantCustomerCheckout::count());
        $originalSnapshot = $checkout->shipping_data_snapshot;

        $this->withSession($session)->post($this->url($tenant, '/app/envio/nuevo'), $payload)
            ->assertRedirect('/app/checkout/'.$checkout->uuid.'/resumen');
        $this->assertSame(1, TenantCustomerCheckout::count());

        $changed = $payload;
        $changed['sender']['name'] = 'Nombre modificado';
        $changed['recipient']['phone'] = '9999999999';
        $this->withSession($session)->post($this->url($tenant, '/app/envio/nuevo'), $changed)
            ->assertRedirect('/app/checkout/'.$checkout->uuid.'/resumen');

        $this->assertSame(1, TenantCustomerCheckout::count());
        $this->assertSame($originalSnapshot, $checkout->fresh()->shipping_data_snapshot);
        $this->assertSame($originalSnapshot, $operation->fresh()->metadata['shipping_data']);
        $this->assertSame($snapshot->origin, $originalSnapshot['sender']['address']);
    }

    public function test_store_shipping_does_not_reuse_checkout_from_another_operation_tenant_or_customer(): void
    {
        $tenant = $this->tenant('shipping-scope');
        $owner = $this->customer($tenant, 'shipping-owner@example.test');
        $other = $this->customer($tenant, 'shipping-other@example.test');
        [$foreignOperation] = $this->quotedOperation($tenant, $other);
        $this->actingAs($other->user)
            ->withSession(['tenant_customer.active_operation' => $foreignOperation->uuid])
            ->post($this->url($tenant, '/app/envio/nuevo'), $this->shippingPayload())
            ->assertRedirect();
        $foreignCheckout = TenantCustomerCheckout::where('tenant_operation_id', $foreignOperation->id)->sole();
        [$ownOperation] = $this->quotedOperation($tenant, $owner);

        $this->actingAs($owner->user)
            ->withSession(['tenant_customer.active_operation' => $foreignOperation->uuid])
            ->post($this->url($tenant, '/app/envio/nuevo'), $this->shippingPayload())
            ->assertNotFound();

        $this->withSession(['tenant_customer.active_operation' => $ownOperation->uuid])
            ->post($this->url($tenant, '/app/envio/nuevo'), $this->shippingPayload())
            ->assertRedirect();

        $ownCheckout = TenantCustomerCheckout::where('tenant_operation_id', $ownOperation->id)->sole();
        $this->assertNotSame($foreignCheckout->uuid, $ownCheckout->uuid);
        $this->assertSame($owner->id, $ownCheckout->customer_profile_id);
        $this->assertSame($tenant->id, $ownCheckout->tenant_id);
        $this->assertSame(2, TenantCustomerCheckout::count());

        $secondTenant = $this->tenant('shipping-other-tenant');
        $secondProfile = $this->customer($secondTenant, 'shipping-second-tenant@example.test');
        $this->actingAs($secondProfile->user)
            ->withSession(['tenant_customer.active_operation' => $ownOperation->uuid])
            ->post($this->url($secondTenant, '/app/envio/nuevo'), $this->shippingPayload())
            ->assertNotFound();
        $this->assertSame(2, TenantCustomerCheckout::count());
    }

    public function test_pending_checkout_has_no_usage_shipment_or_guide_and_is_customer_scoped(): void
    {
        $tenant=$this->tenant('gate'); $owner=$this->customer($tenant,'gate-owner@example.test'); $other=$this->customer($tenant,'gate-other@example.test');
        $operation=TenantOperation::create(['tenant_id'=>$tenant->id,'subscription_id'=>$tenant->subscriptions()->first()->id,'customer_profile_id'=>$owner->id,'channel'=>'b2c','status'=>'quoted','provider'=>'ZIGO_LOCAL','service_code'=>'LOCAL','metadata'=>['selected_quote'=>['service'=>'Local','price'=>100,'currency'=>'MXN'],'quoted_package'=>['type'=>'sobre','weight'=>1],'shipping_data'=>['sender'=>[],'recipient'=>[],'package'=>['type'=>'sobre','weight'=>1]]]]);
        $proof=TenantDeliveryProofOption::create(['tenant_id'=>$tenant->id,'code'=>'SIMPLE','name'=>'Simple','receiver_policy'=>'ANY_PERSON_AT_ADDRESS','max_delivery_attempts'=>2,'surcharge_amount'=>0,'currency'=>'MXN','is_active'=>true,'sort_order'=>1]);
        $checkout=app(CustomerCheckoutService::class)->pending(app(CustomerCheckoutService::class)->create($tenant,$owner,$operation,$proof));
        $this->assertSame('PENDING_PAYMENT',$checkout->status); $this->assertSame('quoted',$operation->fresh()->status);
        $this->assertDatabaseCount('network_usage_events',0); $this->assertDatabaseCount('local_shipments',0);
        $this->actingAs($other->user)->get($this->url($tenant,'/app/checkout/'.$checkout->uuid.'/pago'))->assertNotFound();
        $this->actingAs($owner->user)->get($this->url($tenant,'/app'))->assertOk()->assertSee('Pago pendiente')->assertSee('Continuar pago');
        $this->get($this->url($tenant,'/app/envios'))->assertOk()->assertSeeInOrder(['GUÍAS GENERADAS','Pendientes de pago'])->assertSee('Local')->assertSee('Continuar pago')->assertDontSee('ZL000PENDING');
    }

    public function test_expired_checkout_never_creates_shipment(): void
    {
        $tenant=$this->tenant('expired'); $profile=$this->customer($tenant,'expired@example.test');
        $operation=TenantOperation::create(['tenant_id'=>$tenant->id,'subscription_id'=>$tenant->subscriptions()->first()->id,'customer_profile_id'=>$profile->id,'channel'=>'b2c','status'=>'quoted','metadata'=>[]]);
        $checkout=TenantCustomerCheckout::create(['tenant_id'=>$tenant->id,'customer_profile_id'=>$profile->id,'tenant_operation_id'=>$operation->id,'status'=>'PENDING_PAYMENT','payment_status'=>'PENDING','currency'=>'MXN','shipping_amount'=>100,'evidence_amount'=>0,'total_amount'=>100,'quote_snapshot'=>[],'shipping_data_snapshot'=>[],'proof_option_snapshot'=>[],'expires_at'=>now()->subMinute()]);
        $this->actingAs($profile->user)->get($this->url($tenant,'/app/checkout/'.$checkout->uuid.'/pago'))->assertOk()->assertSee('Esta cotización expiró');
        $this->assertSame('EXPIRED',$checkout->fresh()->status); $this->assertDatabaseCount('local_shipments',0); $this->assertDatabaseCount('network_usage_events',0);
    }

    public function test_paid_return_presents_real_guide_and_pending_browser_return_never_approves(): void
    {
        $tenant = $this->tenant('paid-return');
        $owner = $this->customer($tenant, 'paid-owner@example.test');
        $other = $this->customer($tenant, 'paid-other@example.test');
        TenantDeliveryProofOption::query()->delete();
        $checkout = $this->checkoutForView($tenant, $owner);
        $checkout = app(CustomerCheckoutService::class)->pending($checkout);
        $shipment = app(CustomerCheckoutFulfillmentService::class)->approve($checkout, 'MERCADO_PAGO', 'verified-payment-1');

        $this->assertSame('PAID', $checkout->fresh()->status);
        $this->actingAs($owner->user)->get($this->url($tenant, '/app/envios'))->assertOk()->assertSee('Sin pagos pendientes')->assertSee($shipment->tracking_number);
        $this->assertNotEmpty($shipment->guide_snapshot);
        $this->assertSame($shipment->tracking_number, $shipment->guide_snapshot['tracking_number']);
        $senderBefore = $shipment->sender_snapshot;
        $recipientBefore = $shipment->recipient_snapshot;

        $this->actingAs($owner->user)->get($this->url($tenant, '/app/envios/'.$shipment->uuid))
            ->assertOk()->assertSee('Origen 1')->assertSee('Destino 2');
        $this->get($this->url($tenant, '/app/envios/'.$shipment->uuid.'/guia.pdf'))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame($senderBefore, $shipment->fresh()->sender_snapshot);
        $this->assertSame($recipientBefore, $shipment->fresh()->recipient_snapshot);

        $return = $this->actingAs($owner->user)->get($this->url($tenant, '/app/checkout/'.$checkout->uuid.'/pago/retorno/success'));
        $return->assertOk()->assertSee('Tu envío está listo')->assertSee($shipment->tracking_number)
            ->assertSee($shipment->service_code)->assertSee('Origen')->assertSee('Destino')->assertSee('$207.64 MXN')
            ->assertSee('Descargar guía')->assertSee('Rastrear envío')->assertSee('Ver mis envíos');

        $this->actingAs($other->user)->get($this->url($tenant, '/app/checkout/'.$checkout->uuid.'/pago/retorno/success'))->assertNotFound();
        $foreignTenant = $this->tenant('paid-return-foreign');
        $foreignCustomer = $this->customer($foreignTenant, 'paid-foreign@example.test');
        $this->actingAs($foreignCustomer->user)->get($this->url($foreignTenant, '/app/checkout/'.$checkout->uuid.'/pago/retorno/success'))->assertNotFound();

        $pending = $this->checkoutForView($tenant, $owner);
        $pending = app(CustomerCheckoutService::class)->pending($pending);
        $this->actingAs($owner->user)->get($this->url($tenant, '/app/checkout/'.$pending->uuid.'/pago/retorno/success'))
            ->assertOk()->assertDontSee('Tu envío está listo');
        $this->assertSame('PENDING_PAYMENT', $pending->fresh()->status);
        $this->assertSame('PENDING', $pending->payment_status);
    }

    protected function customer(Tenant $tenant, string $email): TenantCustomerProfile
    {
        $user = User::create(['name' => 'Customer', 'email' => $email, 'password' => Hash::make('secret-pass'), 'empresa_id' => 1]);
        return TenantCustomerProfile::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active', 'display_name' => 'Customer']);
    }

    private function shipment(Tenant $tenant, TenantCustomerProfile $profile, string $tracking, string $status): LocalShipment
    {
        $operation = TenantOperation::create(['tenant_id' => $tenant->id, 'subscription_id' => $tenant->subscriptions()->first()->id, 'customer_profile_id' => $profile->id, 'channel' => 'b2c', 'status' => 'confirmed', 'provider' => 'ZIGO_LOCAL', 'service_code' => 'LOCAL', 'metadata' => []]);
        $shipment = LocalShipment::create(['tenant_id' => $tenant->id, 'tenant_operation_id' => $operation->id, 'tracking_number' => $tracking, 'service_code' => 'LOCAL', 'status' => $status, 'sender_snapshot' => ['name' => 'Sender', 'address' => 'Secret address', 'postal_code' => '64000', 'phone' => '8112345678'], 'recipient_snapshot' => ['name' => 'Recipient', 'address' => 'Secret address', 'postal_code' => '64000'], 'package_snapshot' => ['type' => 'caja', 'weight' => 1], 'pricing_snapshot' => ['final_price' => 120, 'provider_cost' => 80], 'guide_snapshot' => ['service_code' => 'LOCAL', 'sender' => ['name' => 'Sender', 'address' => 'Secret address', 'postal_code' => '64000', 'phone' => '8112345678'], 'recipient' => ['name' => 'Recipient', 'address' => 'Secret address', 'postal_code' => '64000'], 'package' => ['type' => 'caja', 'weight' => 1]]]);
        $shipment->events()->create(['status' => $status, 'event_code' => 'STATUS', 'occurred_at' => now()]);
        return $shipment;
    }

    protected function tenant(string $slug): Tenant
    {
        $plan = Plan::create(['code' => strtoupper($slug), 'name' => $slug, 'status' => 'active', 'currency' => 'MXN']);
        $tenant = Tenant::create(['name' => $slug, 'slug' => $slug, 'status' => 'active']);
        $tenant->domains()->create(['domain' => $slug.'.zigo.local', 'type' => 'subdomain', 'environment' => 'local', 'is_primary' => true, 'status' => 'verified', 'verified_at' => now()]);
        $subscription = Subscription::create(['tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'status' => 'active', 'started_at' => now(), 'current_period_start' => now(), 'current_period_end' => now()->addMonth()]);
        $legacyOwner = User::create(['name'=>'Legacy owner','email'=>'owner-'.$slug.'@example.test','password'=>Hash::make('secret-pass'),'empresa_id'=>1000+$tenant->id]);
        $tenant->memberships()->create(['user_id'=>$legacyOwner->id,'role'=>'owner','status'=>'active']);
        foreach (['B2C','SHIPPING','TRACKING'] as $code) { $module = Module::create(['code' => $code.$tenant->id, 'name' => $code, 'type' => 'addon', 'is_active' => true, 'sort_order' => 1]); Entitlement::create(['subscription_id' => $subscription->id, 'tenant_id' => $tenant->id, 'module_id' => $module->id, 'code' => $code, 'is_enabled' => true, 'source' => 'plan']); }
        return $tenant;
    }

    protected function url(Tenant $tenant, string $path): string { return 'http://'.$tenant->primaryDomain()->value('domain').$path; }

    private function quotedOperation(Tenant $tenant, TenantCustomerProfile $profile): array
    {
        $snapshot = LocalShippingQuoteSnapshot::create([
            'tenant_id' => $tenant->id, 'origin' => ['address'=>'Centro, 64000, Monterrey, Nuevo León, México','street' => null,'postal_code'=>'64000','settlement'=>'Centro','municipality'=>'Monterrey','state'=>'Nuevo León'], 'destination' => ['address'=>'Centro, 64000, Monterrey, Nuevo León, México','street' => null,'postal_code'=>'64000','settlement'=>'Centro','municipality'=>'Monterrey','state'=>'Nuevo León'],
            'package_type' => 'sobre', 'weight_kg' => 1, 'dimensions' => [], 'distance_meters' => 1000,
            'pricing_strategy' => 'flat', 'matched_tariff' => [], 'amount' => 179, 'currency' => 'MXN', 'expires_at' => now()->addHour(),
        ]);
        $operation = TenantOperation::create([
            'tenant_id' => $tenant->id, 'subscription_id' => $tenant->subscriptions()->first()->id,
            'customer_profile_id' => $profile->id, 'channel' => 'b2c', 'status' => 'quoted', 'provider' => 'ZIGO_LOCAL', 'service_code' => 'LOCAL',
            'metadata' => ['origin_postal_code' => '64000', 'destination_postal_code' => '64000', 'quoted_package' => ['type' => 'sobre', 'weight' => 1],
                'selected_quote' => ['service' => 'Directo', 'price' => 179, 'currency' => 'MXN'], 'selected_quote_snapshot_uuid' => $snapshot->uuid],
        ]);
        return [$operation, $snapshot];
    }

    private function shippingPayload(): array
    {
        return [
            'sender' => ['name' => 'Remitente', 'phone' => '8111111111', 'email' => 'remitente@example.test', 'street' => 'Origen', 'exterior' => '1', 'interior' => '2', 'references' => 'Puerta azul'],
            'recipient' => ['name' => 'Destinatario', 'phone' => '8222222222', 'email' => null, 'street' => 'Destino', 'exterior' => '2', 'interior' => null, 'references' => 'Frente al parque'],
            'reference' => 'Pedido RC4.9.1',
        ];
    }

    private function addressPayload(array $overrides=[]): array
    {
        return array_replace(['address_type'=>'both','alias'=>'Casa','contact_name'=>'Customer','company'=>null,'phone'=>'8111111111','email'=>'customer@example.test','street'=>'Juárez','exterior'=>'10','interior'=>null,'postal_code'=>'64000','settlement'=>'Centro','references'=>'Portón azul'], $overrides);
    }

    private function checkoutForView(Tenant $tenant, TenantCustomerProfile $profile, ?TenantDeliveryProofOption $proof = null): TenantCustomerCheckout
    {
        [$operation] = $this->quotedOperation($tenant, $profile);
        $metadata = $operation->metadata;
        $metadata['shipping_data'] = [
            'sender' => ['name' => 'Remitente', 'phone' => '8111111111', 'address' => ['street' => 'Origen 1']],
            'recipient' => ['name' => 'Destinatario', 'phone' => '8222222222', 'address' => ['street' => 'Destino 2']],
            'package' => ['type' => 'sobre', 'weight' => '1.00'],
        ];
        $operation->update(['metadata' => $metadata]);
        return app(CustomerCheckoutService::class)->create($tenant, $profile, $operation->fresh(), $proof);
    }

    private function schema(): void
    {
        Schema::create('users', fn(Blueprint $t) => tap($t, fn($t) => [$t->id(),$t->string('name'),$t->string('email')->unique(),$t->string('password'),$t->unsignedBigInteger('empresa_id'),$t->rememberToken(),$t->timestamps()]));
        Schema::create('zigo_postal_codes', fn(Blueprint $t) => tap($t, fn($t) => [$t->id(),$t->string('codigo_postal',5),$t->string('asentamiento'),$t->string('tipo_asentamiento')->nullable(),$t->string('municipio'),$t->string('estado'),$t->string('ciudad')->nullable(),$t->boolean('activo')->default(true)]));
        Schema::create('network_plans', fn(Blueprint $t) => tap($t, fn($t) => [$t->id(),$t->string('code'),$t->string('name'),$t->string('status'),$t->string('currency'),$t->unsignedInteger('included_operations')->nullable(),$t->timestamps()]));
        Schema::create('network_modules', fn(Blueprint $t) => tap($t, fn($t) => [$t->id(),$t->string('code')->unique(),$t->string('name'),$t->string('type'),$t->boolean('is_active'),$t->unsignedSmallInteger('sort_order'),$t->timestamps()]));
        Schema::create('network_tenants', fn(Blueprint $t) => tap($t, fn($t) => [$t->id(),$t->uuid('uuid')->unique(),$t->string('name'),$t->string('slug')->unique(),$t->string('status'),$t->unsignedBigInteger('current_plan_id')->nullable(),$t->timestamps()]));
        Schema::create('network_tenant_domains', fn(Blueprint $t) => tap($t, fn($t) => [$t->id(),$t->unsignedBigInteger('tenant_id'),$t->string('domain')->unique(),$t->string('type'),$t->string('environment'),$t->boolean('is_primary'),$t->string('status'),$t->timestamp('verified_at')->nullable(),$t->timestamps()]));
        Schema::create('network_tenant_brandings', fn(Blueprint $t) => tap($t, fn($t) => [$t->id(),$t->unsignedBigInteger('tenant_id')->unique(),$t->string('brand_name')->nullable(),$t->string('logo_path')->nullable(),$t->string('hero_image_path')->nullable(),$t->string('primary_color')->nullable(),$t->string('secondary_color')->nullable(),$t->string('accent_color')->nullable(),$t->string('favicon_path')->nullable(),$t->string('support_email')->nullable(),$t->string('support_phone')->nullable(),$t->timestamps()]));
        Schema::create('network_tenant_memberships', fn(Blueprint $t) => tap($t, fn($t) => [$t->id(),$t->unsignedBigInteger('tenant_id'),$t->unsignedBigInteger('user_id'),$t->string('role'),$t->string('status'),$t->timestamps()]));
        Schema::create('network_subscriptions', fn(Blueprint $t) => tap($t, fn($t) => [$t->id(),$t->uuid('uuid')->unique(),$t->unsignedBigInteger('tenant_id'),$t->unsignedBigInteger('plan_id'),$t->string('status'),$t->unsignedInteger('operations_limit')->nullable(),$t->timestamp('started_at'),$t->timestamp('current_period_start'),$t->timestamp('current_period_end'),$t->timestamp('trial_ends_at')->nullable(),$t->timestamp('grace_ends_at')->nullable(),$t->timestamp('canceled_at')->nullable(),$t->timestamp('ended_at')->nullable(),$t->timestamps()]));
        Schema::create('network_entitlements', fn(Blueprint $t) => tap($t, fn($t) => [$t->id(),$t->unsignedBigInteger('subscription_id'),$t->unsignedBigInteger('tenant_id'),$t->unsignedBigInteger('module_id'),$t->string('code'),$t->boolean('is_enabled'),$t->unsignedInteger('limit_value')->nullable(),$t->string('source'),$t->timestamps()]));
        Schema::create('network_usage_events', fn(Blueprint $t) => tap($t, fn($t) => [$t->id(),$t->uuid('uuid')->nullable(),$t->unsignedBigInteger('tenant_id'),$t->unsignedBigInteger('subscription_id')->nullable(),$t->string('metric'),$t->unsignedInteger('quantity'),$t->string('idempotency_key')->nullable(),$t->timestamp('occurred_at'),$t->json('metadata')->nullable(),$t->timestamp('created_at')->nullable()]));
        Schema::create('tenant_customer_profiles', fn(Blueprint $t) => tap($t, fn($t) => [$t->id(),$t->uuid('uuid')->unique(),$t->unsignedBigInteger('tenant_id'),$t->unsignedBigInteger('user_id'),$t->string('status'),$t->string('display_name')->nullable(),$t->string('phone')->nullable(),$t->timestamps(),$t->unique(['tenant_id','user_id'])]));
        Schema::create('tenant_customer_addresses', fn(Blueprint $t) => tap($t, fn($t) => [$t->id(),$t->uuid('uuid')->unique(),$t->unsignedBigInteger('tenant_id'),$t->unsignedBigInteger('customer_profile_id'),$t->string('address_type'),$t->string('alias'),$t->string('contact_name'),$t->string('company')->nullable(),$t->string('phone'),$t->string('email')->nullable(),$t->string('street'),$t->string('exterior'),$t->string('interior')->nullable(),$t->string('postal_code'),$t->string('settlement'),$t->string('municipality'),$t->string('state'),$t->string('references')->nullable(),$t->boolean('is_default_origin')->default(false),$t->boolean('is_default_destination')->default(false),$t->boolean('is_active')->default(true),$t->timestamps()]));
        Schema::create('support_tickets', fn(Blueprint $t) => tap($t, fn($t) => [$t->id(),$t->uuid('uuid')->unique(),$t->unsignedBigInteger('tenant_id')->nullable(),$t->unsignedBigInteger('requester_user_id'),$t->unsignedBigInteger('customer_profile_id')->nullable(),$t->string('requester_type'),$t->string('scope'),$t->string('channel')->default('WEB'),$t->string('category'),$t->string('priority')->default('NORMAL'),$t->string('status')->default('OPEN'),$t->string('subject'),$t->text('description'),$t->unsignedBigInteger('assigned_user_id')->nullable(),$t->string('assigned_team')->nullable(),$t->unsignedBigInteger('related_operation_id')->nullable(),$t->unsignedBigInteger('related_shipment_id')->nullable(),$t->unsignedBigInteger('related_checkout_id')->nullable(),$t->unsignedBigInteger('related_driver_profile_id')->nullable(),$t->string('public_reference')->unique(),$t->timestamp('first_response_at')->nullable(),$t->timestamp('resolved_at')->nullable(),$t->timestamp('closed_at')->nullable(),$t->timestamps()]));
        Schema::create('support_ticket_messages', fn(Blueprint $t) => tap($t, fn($t) => [$t->id(),$t->unsignedBigInteger('ticket_id'),$t->unsignedBigInteger('author_user_id'),$t->string('visibility')->default('PUBLIC'),$t->text('message'),$t->timestamps()]));
        Schema::create('support_ticket_attachments', fn(Blueprint $t) => tap($t, fn($t) => [$t->id(),$t->unsignedBigInteger('ticket_id'),$t->unsignedBigInteger('message_id')->nullable(),$t->unsignedBigInteger('uploaded_by_user_id'),$t->string('original_name'),$t->string('stored_path'),$t->string('mime'),$t->unsignedBigInteger('size'),$t->timestamps()]));
        Schema::create('support_ticket_events', fn(Blueprint $t) => tap($t, fn($t) => [$t->id(),$t->unsignedBigInteger('ticket_id'),$t->unsignedBigInteger('actor_user_id')->nullable(),$t->string('type'),$t->json('metadata')->nullable(),$t->timestamp('created_at')->nullable()]));
        Schema::create('tenant_customer_checkouts', fn(Blueprint $t) => tap($t, fn($t) => [$t->id(),$t->uuid('uuid')->unique(),$t->unsignedBigInteger('tenant_id'),$t->unsignedBigInteger('customer_profile_id'),$t->unsignedBigInteger('tenant_operation_id')->unique(),$t->string('status'),$t->string('payment_status'),$t->string('currency'),$t->decimal('shipping_amount',12,2),$t->decimal('evidence_amount',12,2),$t->decimal('subtotal_amount',12,2)->nullable(),$t->decimal('tax_rate',5,4)->nullable(),$t->decimal('tax_amount',12,2)->nullable(),$t->decimal('total_amount',12,2),$t->json('quote_snapshot'),$t->json('shipping_data_snapshot'),$t->json('proof_option_snapshot'),$t->string('payment_provider')->nullable(),$t->string('payment_reference')->nullable(),$t->timestamp('expires_at')->nullable(),$t->timestamp('paid_at')->nullable(),$t->timestamps()]));
        Schema::create('local_shipping_quote_snapshots', fn(Blueprint $t) => tap($t, fn($t) => [$t->id(),$t->uuid('uuid')->unique(),$t->unsignedBigInteger('tenant_id'),$t->unsignedBigInteger('service_id')->nullable(),$t->json('origin'),$t->json('destination'),$t->string('package_type'),$t->decimal('weight_kg',8,2),$t->json('dimensions')->nullable(),$t->unsignedInteger('distance_meters')->nullable(),$t->string('pricing_strategy'),$t->json('matched_tariff')->nullable(),$t->decimal('amount',12,2),$t->char('currency',3),$t->timestamp('expires_at')->nullable(),$t->timestamps()]));
        Schema::create('network_tenant_operations', fn(Blueprint $t) => tap($t, fn($t) => [$t->id(),$t->uuid('uuid')->unique(),$t->unsignedBigInteger('tenant_id'),$t->unsignedBigInteger('subscription_id')->nullable(),$t->string('channel'),$t->string('status'),$t->string('source_type')->nullable(),$t->unsignedBigInteger('source_id')->nullable(),$t->string('provider')->nullable(),$t->string('service_code')->nullable(),$t->string('external_reference')->nullable(),$t->unsignedBigInteger('created_by_user_id')->nullable(),$t->unsignedBigInteger('customer_profile_id')->nullable(),$t->json('metadata')->nullable(),$t->timestamps()]));
        Schema::create('local_shipments', fn(Blueprint $t) => tap($t, fn($t) => [$t->id(),$t->uuid('uuid')->unique(),$t->unsignedBigInteger('tenant_id'),$t->unsignedBigInteger('tenant_operation_id'),$t->string('tracking_number')->unique(),$t->string('service_code'),$t->string('status'),$t->json('sender_snapshot'),$t->json('recipient_snapshot'),$t->json('package_snapshot'),$t->json('pricing_snapshot'),$t->json('guide_snapshot'),$t->unsignedBigInteger('created_by_user_id')->nullable(),$t->timestamps()]));
        Schema::create('local_tracking_events', fn(Blueprint $t) => tap($t, fn($t) => [$t->id(),$t->unsignedBigInteger('local_shipment_id'),$t->string('status'),$t->string('event_code'),$t->text('description')->nullable(),$t->timestamp('occurred_at'),$t->unsignedBigInteger('created_by_user_id')->nullable(),$t->json('metadata')->nullable(),$t->timestamp('created_at')->nullable()]));
        Schema::create('local_shipment_delivery_requirements', fn(Blueprint $t) => tap($t, fn($t) => [$t->id(),$t->unsignedBigInteger('tenant_id'),$t->unsignedBigInteger('local_shipment_id')->unique(),$t->string('proof_option_code_snapshot'),$t->string('proof_option_name_snapshot'),$t->boolean('require_receiver_name'),$t->boolean('require_receiver_type'),$t->boolean('require_signature'),$t->boolean('require_photo'),$t->boolean('require_gps'),$t->string('receiver_policy'),$t->unsignedSmallInteger('max_delivery_attempts'),$t->decimal('surcharge_amount_snapshot',10,2)->nullable(),$t->char('currency_snapshot',3),$t->timestamp('created_at')->nullable()]));
        Schema::create('tenant_delivery_proof_options', fn(Blueprint $t) => tap($t, fn($t) => [$t->id(),$t->uuid('uuid'),$t->unsignedBigInteger('tenant_id'),$t->string('code'),$t->string('name'),$t->text('description')->nullable(),$t->boolean('require_receiver_name')->default(false),$t->boolean('require_receiver_type')->default(false),$t->boolean('require_signature')->default(false),$t->boolean('require_photo')->default(false),$t->boolean('require_gps')->default(false),$t->string('receiver_policy'),$t->unsignedSmallInteger('max_delivery_attempts'),$t->decimal('surcharge_amount',12,2)->default(0),$t->string('currency'),$t->boolean('is_default')->default(false),$t->boolean('is_active'),$t->unsignedSmallInteger('sort_order'),$t->timestamps()]));
    }
}
