<?php
namespace App\Http\Controllers\Tenant;
use App\Domain\Network\Channels\B2C\Models\TenantCustomerCheckout; use App\Domain\Network\Tenancy\TenantContext; use App\Domain\Payments\Models\TenantPaymentConnection; use App\Domain\Payments\TenantPaymentService; use App\Http\Controllers\Controller; use Illuminate\Http\Request;
final class CustomerPaymentController extends Controller{
 public function create(Request $r,string $checkout,TenantContext $c,TenantPaymentService $s){$item=$this->checkout($r,$c,$checkout);$conn=TenantPaymentConnection::where('tenant_id',$c->id())->where('provider','MERCADO_PAGO')->where('status','CONNECTED')->firstOrFail();$attempt=$s->createAttempt($item,$conn);if(!$attempt->init_point)$attempt=$s->initialize($attempt);return redirect()->away($attempt->getRawOriginal('init_point'));}
 public function returned(Request $r,string $checkout,string $result,TenantContext $c){abort_unless(in_array($result,['success','pending','failure'],true),404);$item=$this->checkout($r,$c,$checkout);return view('tenant.customer.journey.payment-return',['tenant'=>$c->tenant()->load('branding'),'checkout'=>$item->fresh(),'result'=>$result]);}
 private function checkout(Request $r,TenantContext $c,string $uuid):TenantCustomerCheckout{$p=$r->attributes->get('customer_profile');return TenantCustomerCheckout::where('tenant_id',$c->id())->where('customer_profile_id',$p->id)->where('uuid',$uuid)->firstOrFail();}
}
