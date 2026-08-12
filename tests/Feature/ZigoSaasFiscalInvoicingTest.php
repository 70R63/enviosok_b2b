<?php

namespace Tests\Feature;

use App\Domain\Network\Commerce\Models\{PlatformPaymentAttempt,TenantSaasFiscalProfile,TenantSaasInvoiceRequest,TenantSaasOrder};
use App\Domain\Network\Commerce\TenantSaasInvoiceService;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};

final class ZigoSaasFiscalInvoicingTest extends ZigoSaasOnboardingPaymentProvisioningTest
{
    protected function setUp(): void
    {
        parent::setUp();
        (require database_path('migrations/2026_08_21_100000_create_tenant_saas_fiscal_invoicing.php'))->up();
        Storage::fake('local');
    }

    public function test_only_verified_paid_order_is_invoiceable_and_request_is_idempotent(): void
    {
        [$tenant,$user,$order,$attempt,$profile]=$this->fixture();
        $service=app(TenantSaasInvoiceService::class);
        $first=$service->request($order,$profile,$user);$second=$service->request($order,$profile,$user);
        $this->assertSame($first->id,$second->id);$this->assertSame(1,TenantSaasInvoiceRequest::count());
        $this->assertSame('1000.00',$first->subtotal);$this->assertSame('160.00',$first->tax_amount);$this->assertSame('1160.00',$first->total);$this->assertSame('MXN',$first->currency);
        $profile->update(['legal_name'=>'Changed SA']);$this->assertSame('Demo SA de CV',$first->fresh()->fiscal_snapshot['legal_name']);
        $order->update(['payment_status'=>'PENDING']);$other=$this->order($tenant,$user,'unpaid');
        $this->expectException(\RuntimeException::class);$service->request($other,$profile,$user);
    }

    public function test_documents_are_private_issued_and_preserved(): void
    {
        [$tenant,$user,$order,$attempt,$profile]=$this->fixture();$service=app(TenantSaasInvoiceService::class);
        $invoice=$service->request($order,$profile,$user);
        $issued=$service->issue($invoice,UploadedFile::fake()->create('invoice.pdf',2,'application/pdf'),UploadedFile::fake()->createWithContent('invoice.xml','<?xml version="1.0"?><cfdi/>'),$user);
        $this->assertSame('ISSUED',$issued->status);$this->assertNotNull($issued->issued_at);Storage::disk('local')->assertExists($issued->pdf_path);Storage::disk('local')->assertExists($issued->xml_path);
        $this->assertStringStartsWith('tenant-saas-invoices/'.$tenant->id.'/',$issued->pdf_path);
    }

    public function test_schema_is_separate_from_b2c_and_routes_are_protected_surfaces(): void
    {
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('tenant_saas_fiscal_profiles'));$this->assertTrue(DB::getSchemaBuilder()->hasTable('tenant_saas_invoice_requests'));
        $routes=collect(app('router')->getRoutes()->getRoutes());
        foreach(['tenant.admin.billing.index','tenant.admin.billing.profile','tenant.admin.billing.request','tenant.admin.billing.download','network.saas-invoices.issue'] as$name)$this->assertNotNull($routes->first(fn($route)=>$route->getName()===$name));
    }

    private function fixture(): array
    {
        $plan=\App\Domain\Network\Catalog\Models\Plan::create(['code'=>'FISCAL','name'=>'Fiscal','status'=>'active','monthly_price'=>'1000.00','currency'=>'MXN']);
        $tenant=Tenant::create(['name'=>'Demo','slug'=>'fiscal-demo','status'=>'active','current_plan_id'=>$plan->id]);
        $user=User::create(['name'=>'Owner','email'=>'fiscal@example.test','password'=>'secret','empresa_id'=>1]);
        $order=$this->order($tenant,$user,'paid');
        $attempt=PlatformPaymentAttempt::create(['tenant_id'=>$tenant->id,'saas_order_id'=>$order->id,'provider'=>'MERCADO_PAGO','status'=>'APPROVED','provider_payment_id'=>'verified-fiscal','external_reference'=>'fiscal-ref','amount'=>'1160.00','currency'=>'MXN','approved_at'=>now()]);
        $profile=TenantSaasFiscalProfile::create(['tenant_id'=>$tenant->id,'legal_name'=>'Demo SA de CV','tax_id'=>'DEM010101AA1','fiscal_postal_code'=>'64000','fiscal_regime'=>'601','cfdi_use'=>'G03','billing_email'=>'billing@example.test']);
        return[$tenant,$user,$order,$attempt,$profile];
    }

    private function order(Tenant $tenant,User$user,string$key): TenantSaasOrder
    {
        return TenantSaasOrder::create(['tenant_id'=>$tenant->id,'commercial_product_id'=>1,'created_by_user_id'=>$user->id,'purchase_key'=>'fiscal-'.$key,'status'=>$key==='paid'?'ACTIVATED':'PENDING_PAYMENT','payment_status'=>$key==='paid'?'APPROVED':'PENDING','quantity'=>1,'unit_amount'=>'1000.00','subtotal'=>'1000.00','tax_amount'=>'160.00','total_amount'=>'1160.00','currency'=>'MXN','purchase_snapshot'=>['name'=>'ZIGO Esencial anual'],'paid_at'=>$key==='paid'?now():null]);
    }
}
