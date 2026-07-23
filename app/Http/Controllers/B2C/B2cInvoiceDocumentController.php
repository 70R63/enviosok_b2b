<?php

namespace App\Http\Controllers\B2C;

use App\Http\Controllers\Controller;
use App\Models\B2cInvoiceRequest;
use App\Services\Billing\InvoiceDocumentDownloadService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class B2cInvoiceDocumentController extends Controller
{
    public function download(
        Request $request,
        B2cInvoiceRequest $invoiceRequest,
        string $format,
        InvoiceDocumentDownloadService $documents
    ): Response {
        abort_unless(
            (int) $invoiceRequest->user_id
                === (int) $request->user()->getAuthIdentifier(),
            403
        );

        return $documents->download(
            $invoiceRequest,
            $format
        );
    }
}
