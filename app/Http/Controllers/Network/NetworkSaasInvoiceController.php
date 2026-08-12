<?php

namespace App\Http\Controllers\Network;

use App\Domain\Network\Commerce\Models\TenantSaasInvoiceRequest;
use App\Domain\Network\Commerce\TenantSaasInvoiceService;
use App\Domain\Network\Operations\NetworkAdminAuditService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

final class NetworkSaasInvoiceController extends Controller
{
    public function issue(Request $request, TenantSaasInvoiceRequest $invoice, TenantSaasInvoiceService $service, NetworkAdminAuditService $audit)
    {
        $data=$request->validate(['pdf'=>['required','file','mimes:pdf','max:10240'],'xml'=>['required','file','mimetypes:application/xml,text/xml','max:10240']]);
        $before=$invoice->only(['status','issued_at']);$issued=$service->issue($invoice,$data['pdf'],$data['xml'],$request->user());
        $audit->record($request->user()->id,'saas_invoice.issued',$issued,$before,$issued->only(['status','issued_at']));
        return back()->with('success','Factura emitida y documentos disponibles.');
    }
}
