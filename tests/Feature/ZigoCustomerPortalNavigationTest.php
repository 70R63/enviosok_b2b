<?php

namespace Tests\Feature;

use App\Domain\Network\Channels\B2C\Models\TenantOperation;
use App\Models\User;

final class ZigoCustomerPortalNavigationTest extends ZigoCustomerPortalTest
{
    public function test_real_customer_navigation_is_tenant_aware_and_logout_is_post_only(): void
    {
        $tenant = $this->tenant('navigation');
        $profile = $this->customer($tenant, 'navigation@example.test');
        $operation = TenantOperation::create([
            'tenant_id'=>$tenant->id,'subscription_id'=>$tenant->subscriptions()->first()->id,
            'customer_profile_id'=>$profile->id,'channel'=>'b2c','status'=>'quoted',
            'metadata'=>[
                'origin_postal_code'=>'64000','destination_postal_code'=>'64000',
                'quoted_package'=>['type'=>'sobre','weight'=>1],
                'selected_quote'=>['service'=>'Local','price'=>100,'currency'=>'MXN'],
            ],
        ]);
        $session = ['tenant_customer.active_operation'=>$operation->uuid];
        foreach (['/app','/app/cotizar','/app/envios','/app/ayuda','/app/perfil','/app/envio/nuevo'] as $path) {
            $this->actingAs($profile->user)->withSession($session)->get($this->url($tenant, $path))->assertOk();
        }
        $page = $this->actingAs($profile->user)->get($this->url($tenant, '/app'));
        foreach (['/app','/app/cotizar','/app/envios','/app/ayuda','/app/perfil','/salir'] as $path) {
            $page->assertSee($path, false);
        }
        $page->assertDontSee('href="#"', false)->assertDontSee('RapidGo');

        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($route) => $route->getName() === 'tenant.customer.logout');
        $this->assertSame(['POST'], $route->methods());
        $this->get($this->url($tenant, '/salir'))->assertStatus(405);
    }

    public function test_new_customer_uses_tenant_legacy_company_and_never_gets_admin_membership(): void
    {
        $tenant = $this->tenant('new-customer');
        $legacyEmpresaId = $tenant->memberships()->where('role','owner')
            ->join('users','users.id','=','network_tenant_memberships.user_id')->value('users.empresa_id');
        $this->post($this->url($tenant, '/registro'), [
            'name'=>'Cliente Nuevo','email'=>'new-customer@example.test','password'=>'Password!123',
            'password_confirmation'=>'Password!123','terms'=>'1',
        ])->assertRedirect('/app');
        $customer = User::where('email','new-customer@example.test')->firstOrFail();
        $this->assertSame((int)$legacyEmpresaId, (int)$customer->empresa_id);
        $this->assertDatabaseHas('tenant_customer_profiles',['tenant_id'=>$tenant->id,'user_id'=>$customer->id]);
        $this->assertDatabaseMissing('network_tenant_memberships',['tenant_id'=>$tenant->id,'user_id'=>$customer->id]);
        $this->actingAs($customer)->get($this->url($tenant, '/admin'))->assertRedirect();
    }
}
