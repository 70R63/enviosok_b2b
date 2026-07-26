<?php

namespace App\Http\Controllers\CRM;

use App\Exceptions\Billing\CfdiZipValidationException;
use App\Http\Controllers\Controller;
use App\Models\ApiBillingRequest;
use App\Services\ApiHub\Billing\ApiBillingDocumentDownloadService;
use App\Services\ApiHub\Billing\ApiBillingFulfillmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class CrmApiBillingController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'status' => [
                'nullable',
                'in:' . implode(',', [
                    ApiBillingRequest::STATUS_SOLICITADA,
                    ApiBillingRequest::STATUS_EN_PROCESO,
                    ApiBillingRequest::STATUS_FACTURADA,
                    ApiBillingRequest::STATUS_RECHAZADA,
                    ApiBillingRequest::STATUS_CANCELADA,
                ]),
            ],
            'environment' => [
                'nullable',
                'in:sandbox,production',
            ],
            'api_client_id' => [
                'nullable',
                'integer',
                'exists:api_clients,id',
            ],
            'search' => [
                'nullable',
                'string',
                'max:150',
            ],
        ]);

        $query = ApiBillingRequest::query()
            ->with([
                'client:id,name,company_name,email',
                'apiKey:id,name,key_prefix,environment',
                'manager:id,name,email',
            ])
            ->orderByDesc('requested_at')
            ->orderByDesc('id');

        $query
            ->when(
                $validated['status'] ?? null,
                fn (Builder $builder, string $status) =>
                    $builder->where('status', $status)
            )
            ->when(
                $validated['environment'] ?? null,
                fn (Builder $builder, string $environment) =>
                    $builder->where('environment', $environment)
            )
            ->when(
                $validated['api_client_id'] ?? null,
                fn (Builder $builder, int $apiClientId) =>
                    $builder->where('api_client_id', $apiClientId)
            )
            ->when(
                $validated['search'] ?? null,
                function (Builder $builder, string $search): void {
                    $search = trim($search);

                    $builder->where(
                        function (Builder $filter) use ($search): void {
                            $filter
                                ->where(
                                    'external_id',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'customer_rfc',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'customer_name',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'payment_reference',
                                    'like',
                                    "%{$search}%"
                                );
                        }
                    );
                }
            );

        $billingRequests = $query
            ->paginate(15)
            ->withQueryString();

        $statusCounts = ApiBillingRequest::query()
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $apiClients = \App\Models\ApiClient::query()
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'company_name',
            ]);

        return view(
            'crm.api_hub.billing.index',
            compact(
                'billingRequests',
                'statusCounts',
                'apiClients'
            )
        );
    }

    public function show(
        ApiBillingRequest $apiBillingRequest
    ): View {
        $apiBillingRequest->load([
            'client:id,name,company_name,email',
            'apiKey:id,name,key_prefix,environment',
            'manager:id,name,email',
            'items',
        ]);

        $regimenes = config(
            'b2c_fiscal.regimenes',
            []
        );
        $usosCfdi = config(
            'b2c_fiscal.usos_cfdi',
            []
        );

        return view(
            'crm.api_hub.billing.show',
            compact(
                'apiBillingRequest',
                'regimenes',
                'usosCfdi'
            )
        );
    }

    public function startProcessing(
        Request $request,
        ApiBillingRequest $apiBillingRequest
    ): RedirectResponse {
        $updated = ApiBillingRequest::query()
            ->whereKey($apiBillingRequest->getKey())
            ->where(
                'status',
                ApiBillingRequest::STATUS_SOLICITADA
            )
            ->update([
                'status' =>
                    ApiBillingRequest::STATUS_EN_PROCESO,
                'managed_by_user_id' =>
                    $request->user()->getAuthIdentifier(),
                'processing_at' => now(),
                'error_code' => null,
                'error_message' => null,
            ]);

        if ($updated !== 1) {
            return $this->redirectToRequest(
                $apiBillingRequest,
                'error',
                'La solicitud ya no está en estado Solicitada.'
            );
        }

        return $this->redirectToRequest(
            $apiBillingRequest,
            'success',
            'La solicitud API quedó marcada como En proceso.'
        );
    }

    public function updateManagement(
        Request $request,
        ApiBillingRequest $apiBillingRequest
    ): RedirectResponse {
        $validated = $request->validate([
            'internal_notes' => [
                'nullable',
                'string',
                'max:4000',
            ],
        ]);

        $apiBillingRequest->update([
            'internal_notes' =>
                $validated['internal_notes'] ?? null,
            'managed_by_user_id' =>
                $request->user()->getAuthIdentifier(),
        ]);

        return $this->redirectToRequest(
            $apiBillingRequest,
            'success',
            'Las notas internas fueron guardadas.'
        );
    }

    public function reject(
        Request $request,
        ApiBillingRequest $apiBillingRequest
    ): RedirectResponse {
        $validated = $request->validate([
            'rejection_reason' => [
                'required',
                'string',
                'min:10',
                'max:2000',
            ],
        ]);

        $updated = ApiBillingRequest::query()
            ->whereKey($apiBillingRequest->getKey())
            ->whereIn('status', [
                ApiBillingRequest::STATUS_SOLICITADA,
                ApiBillingRequest::STATUS_EN_PROCESO,
            ])
            ->update([
                'status' =>
                    ApiBillingRequest::STATUS_RECHAZADA,
                'managed_by_user_id' =>
                    $request->user()->getAuthIdentifier(),
                'rejection_reason' =>
                    trim($validated['rejection_reason']),
                'cancellation_reason' => null,
                'error_code' => 'MANUAL_REJECTION',
                'error_message' =>
                    trim($validated['rejection_reason']),
                'rejected_at' => now(),
                'cancelled_at' => null,
            ]);

        if ($updated !== 1) {
            return $this->redirectToRequest(
                $apiBillingRequest,
                'error',
                'La solicitud ya no puede rechazarse.'
            );
        }

        return $this->redirectToRequest(
            $apiBillingRequest,
            'success',
            'La solicitud API quedó marcada como Rechazada.'
        );
    }

    public function cancel(
        Request $request,
        ApiBillingRequest $apiBillingRequest
    ): RedirectResponse {
        $validated = $request->validate([
            'cancellation_reason' => [
                'required',
                'string',
                'min:10',
                'max:2000',
            ],
        ]);

        $updated = ApiBillingRequest::query()
            ->whereKey($apiBillingRequest->getKey())
            ->whereIn('status', [
                ApiBillingRequest::STATUS_SOLICITADA,
                ApiBillingRequest::STATUS_EN_PROCESO,
            ])
            ->update([
                'status' =>
                    ApiBillingRequest::STATUS_CANCELADA,
                'managed_by_user_id' =>
                    $request->user()->getAuthIdentifier(),
                'cancellation_reason' =>
                    trim($validated['cancellation_reason']),
                'rejection_reason' => null,
                'error_code' => 'MANUAL_CANCELLATION',
                'error_message' =>
                    trim($validated['cancellation_reason']),
                'cancelled_at' => now(),
                'rejected_at' => null,
            ]);

        if ($updated !== 1) {
            return $this->redirectToRequest(
                $apiBillingRequest,
                'error',
                'La solicitud ya no puede cancelarse.'
            );
        }

        return $this->redirectToRequest(
            $apiBillingRequest,
            'success',
            'La solicitud API quedó marcada como Cancelada.'
        );
    }

    public function storeDocuments(
        Request $request,
        ApiBillingRequest $apiBillingRequest,
        ApiBillingFulfillmentService $fulfillment
    ): RedirectResponse {
        $request->validate([
            'cfdi_zip' => [
                'required',
                'file',
                'max:15360',
            ],
        ]);

        if (! $apiBillingRequest->canUploadDocuments()) {
            return $this->redirectToRequest(
                $apiBillingRequest,
                'error',
                'Los documentos solo pueden cargarse cuando la solicitud está En proceso.'
            );
        }

        try {
            $fulfillment->fulfillFromManualZip(
                $apiBillingRequest,
                $request->file('cfdi_zip'),
                (int) $request->user()->getAuthIdentifier()
            );
        } catch (CfdiZipValidationException $exception) {
            return redirect()
                ->route(
                    'crm.api-hub.billing.show',
                    $apiBillingRequest
                )
                ->withErrors([
                    'cfdi_zip' => $exception->getMessage(),
                ]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);

            return $this->redirectToRequest(
                $apiBillingRequest,
                'error',
                'No fue posible registrar los documentos fiscales.'
            );
        }

        return $this->redirectToRequest(
            $apiBillingRequest,
            'success',
            'El CFDI fue validado y la solicitud API quedó Facturada.'
        );
    }

    public function downloadDocument(
        ApiBillingRequest $apiBillingRequest,
        string $format,
        ApiBillingDocumentDownloadService $documents
    ): Response {
        return $documents->download(
            $apiBillingRequest,
            $format
        );
    }

    private function redirectToRequest(
        ApiBillingRequest $apiBillingRequest,
        string $type,
        string $message
    ): RedirectResponse {
        return redirect()
            ->route(
                'crm.api-hub.billing.show',
                $apiBillingRequest
            )
            ->with($type, $message);
    }
}
