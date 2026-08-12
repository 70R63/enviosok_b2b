<?php

namespace App\Domain\Network\Commerce;

use App\Domain\Network\Commerce\Models\{TenantSaasFiscalProfile, TenantSaasInvoiceRequest, TenantSaasOrder};
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use RuntimeException;

final class TenantSaasInvoiceService
{
    public function request(TenantSaasOrder $order, TenantSaasFiscalProfile $profile, User $actor): TenantSaasInvoiceRequest
    {
        if ($order->tenant_id !== $profile->tenant_id || $order->payment_status !== 'APPROVED' || !$order->paid_at) {
            throw new RuntimeException('ORDER_NOT_INVOICEABLE');
        }
        $attempt = $order->attempts()->where('status', 'APPROVED')->whereNotNull('provider_payment_id')->latest('approved_at')->first();
        if (!$attempt) throw new RuntimeException('VERIFIED_PAYMENT_REQUIRED');

        try {
            return DB::transaction(fn () => TenantSaasInvoiceRequest::firstOrCreate(
                ['tenant_saas_order_id' => $order->id],
                [
                    'tenant_id' => $order->tenant_id,
                    'platform_payment_attempt_id' => $attempt->id,
                    'requested_by_user_id' => $actor->id,
                    'status' => 'REQUESTED',
                    'subtotal' => $order->subtotal,
                    'tax_amount' => $order->tax_amount,
                    'total' => $order->total_amount,
                    'currency' => $order->currency,
                    'fiscal_snapshot' => $profile->snapshot(),
                    'requested_at' => now(),
                ],
            ));
        } catch (QueryException $exception) {
            return TenantSaasInvoiceRequest::where('tenant_saas_order_id', $order->id)->firstOrFail();
        }
    }

    public function issue(TenantSaasInvoiceRequest $invoice, UploadedFile $pdf, UploadedFile $xml, User $actor): TenantSaasInvoiceRequest
    {
        abort_unless(in_array($invoice->status, ['REQUESTED', 'PROCESSING'], true), 409);
        $base = 'tenant-saas-invoices/'.$invoice->tenant_id.'/'.$invoice->uuid;
        $pdfPath = $pdf->storeAs($base, 'invoice.pdf', 'local');
        try {
            $xmlPath = $xml->storeAs($base, 'invoice.xml', 'local');
            $invoice->update(['status'=>'ISSUED', 'pdf_path'=>$pdfPath, 'xml_path'=>$xmlPath, 'issued_by_user_id'=>$actor->id, 'issued_at'=>now(), 'rejected_at'=>null, 'rejection_reason'=>null]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($pdfPath);
            throw $exception;
        }
        return $invoice->fresh();
    }
}
