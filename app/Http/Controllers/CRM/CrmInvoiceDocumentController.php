<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\B2cInvoiceRequest;
use App\Services\Billing\InvoiceDocumentDownloadService;
use Symfony\Component\HttpFoundation\Response;

class CrmInvoiceDocumentController extends Controller
{
    public function download(
        B2cInvoiceRequest $invoiceRequest,
        string $format,
        InvoiceDocumentDownloadService $documents
    ): Response {
        return $documents->download(
            $invoiceRequest,
            $format
        );
    }
}
