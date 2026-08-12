<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Network\Commerce\Models\{TenantSaasFiscalProfile, TenantSaasInvoiceRequest, TenantSaasOrder};
use App\Domain\Network\Commerce\TenantSaasInvoiceService;
use App\Domain\Network\Tenancy\{TenantAccessService, TenantContext};
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

final class TenantSaasInvoiceController extends Controller
{
    public function index(TenantContext $context, TenantAccessService $access)
    {
        abort_unless($access->canManageTenant(auth()->user()), 403);
        $tenant = $context->tenant()->load('branding');
        return view('tenant.admin.billing', [
            'tenant'=>$tenant,
            'profile'=>TenantSaasFiscalProfile::where('tenant_id', $tenant->id)->first(),
            'orders'=>TenantSaasOrder::with(['attempts','invoiceRequest'])->where('tenant_id', $tenant->id)->latest()->get(),
            'invoices'=>TenantSaasInvoiceRequest::with('order')->where('tenant_id', $tenant->id)->latest()->get(),
        ]);
    }

    public function profile(Request $request, TenantContext $context, TenantAccessService $access)
    {
        abort_unless($access->canManageTenant($request->user()), 403);
        $data=$request->validate([
            'legal_name'=>['required','string','max:255'], 'tax_id'=>['required','string','regex:/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/'],
            'fiscal_postal_code'=>['required','digits:5'], 'fiscal_regime'=>['required','digits:3'],
            'cfdi_use'=>['required','regex:/^[A-Z0-9]{3,4}$/'], 'billing_email'=>['required','email:rfc','max:255'],
        ]);
        $data['tax_id']=strtoupper($data['tax_id']);$data['cfdi_use']=strtoupper($data['cfdi_use']);
        TenantSaasFiscalProfile::updateOrCreate(['tenant_id'=>$context->id()],$data);
        return back()->with('success','Datos fiscales guardados.');
    }

    public function request(Request $request, string $order, TenantContext $context, TenantAccessService $access, TenantSaasInvoiceService $service)
    {
        abort_unless($access->canManageTenant($request->user()), 403);
        $saasOrder=TenantSaasOrder::where('tenant_id',$context->id())->where('uuid',$order)->firstOrFail();
        $profile=TenantSaasFiscalProfile::where('tenant_id',$context->id())->firstOrFail();
        try {$service->request($saasOrder,$profile,$request->user());} catch (\RuntimeException) {abort(409,'La compra no cuenta con un pago aprobado verificable.');}
        return back()->with('success','Solicitud de factura registrada.');
    }

    public function download(string $invoice, string $format, TenantContext $context, TenantAccessService $access)
    {
        abort_unless($access->canManageTenant(auth()->user()),403);
        abort_unless(in_array($format,['pdf','xml'],true),404);
        $document=TenantSaasInvoiceRequest::where('tenant_id',$context->id())->where('uuid',$invoice)->where('status','ISSUED')->firstOrFail();
        $path=$format==='pdf'?$document->pdf_path:$document->xml_path;abort_unless($path&&Storage::disk('local')->exists($path),404);
        return Storage::disk('local')->download($path,'factura-zigo-'.$document->uuid.'.'.$format,['Content-Type'=>$format==='pdf'?'application/pdf':'application/xml; charset=UTF-8','X-Content-Type-Options'=>'nosniff','Cache-Control'=>'private, no-store']);
    }
}
