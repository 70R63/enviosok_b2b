<?php
namespace Tests\Feature;
use App\Domain\Network\Commerce\Models\NetworkCommercialProduct;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
final class AiPublicTrialOnboardingTest extends TestCase
{
    public function test_public_landing_links_to_catalog_trial_offer(): void
    {
        Http::preventStrayRequests();
        $trial = new NetworkCommercialProduct(['name'=>'Agentes IA Trial','metadata'=>['trial'=>true]]);
        $trial->uuid = '11111111-1111-4111-8111-111111111111';
        $html = view('agentes-ia.landing', ['product'=>$trial, 'trial'=>$trial])->render();
        $this->assertStringContainsString('Probar gratis 7 días', $html);
        $this->assertStringContainsString('/agentes-ia/comenzar?offer=11111111-1111-4111-8111-111111111111', $html);
    }
}
