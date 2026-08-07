<?php

namespace App\Http\Controllers\CRM;

use App\Exceptions\Billing\CfdiZipValidationException;
use App\Http\Controllers\Controller;
use App\Models\B2cInvoiceRequest;
use App\Models\B2cInvoiceRequestEvent;
use App\Services\Notifications\CrmAdminNotificationService;
use App\Services\Billing\InvoiceFulfillmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CrmInvoiceRequestController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'status' => [
                'nullable',
                'in:' . implode(',', [
                    B2cInvoiceRequest::STATUS_SOLICITADA,
                    B2cInvoiceRequest::STATUS_EN_PROCESO,
                    B2cInvoiceRequest::STATUS_FACTURADA,
                    B2cInvoiceRequest::STATUS_RECHAZADA,
                    B2cInvoiceRequest::STATUS_CANCELADA,
                ]),
            ],
            'rfc' => ['nullable', 'string', 'max:13'],
            'cotizacion' => ['nullable', 'integer', 'min:1'],
            'cliente' => ['nullable', 'string', 'max:191'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
        ]);

        $query = B2cInvoiceRequest::query()
            ->with([
                'user:id,name,apellido_paterno,apellido_materno,email',
                'cotizacion:id,user_id,estatus,payment_status,payment_external_reference',
                'fiscalProfile:id,user_id,rfc,razon_social',
                'managedBy:id,name,apellido_paterno,apellido_materno,email',
            ])
            ->orderByDesc('solicitada_at')
            ->orderByDesc('id');

        $query
            ->when(
                $validated['status'] ?? null,
                fn (Builder $builder, string $status) => $builder->where('status', $status)
            )
            ->when(
                $validated['rfc'] ?? null,
                fn (Builder $builder, string $rfc) => $builder->where(
                    'rfc',
                    'like',
                    '%' . strtoupper(trim($rfc)) . '%'
                )
            )
            ->when(
                $validated['cotizacion'] ?? null,
                fn (Builder $builder, int $cotizacionId) => $builder->where(
                    'cotizacion_id',
                    $cotizacionId
                )
            )
            ->when(
                $validated['cliente'] ?? null,
                function (Builder $builder, string $cliente): void {
                    $cliente = trim($cliente);

                    $builder->whereHas(
                        'user',
                        function (Builder $userQuery) use ($cliente): void {
                            $userQuery->where(function (Builder $searchQuery) use ($cliente): void {
                                $searchQuery
                                    ->where('name', 'like', "%{$cliente}%")
                                    ->orWhere('apellido_paterno', 'like', "%{$cliente}%")
                                    ->orWhere('apellido_materno', 'like', "%{$cliente}%")
                                    ->orWhere('email', 'like', "%{$cliente}%");
                            });
                        }
                    );
                }
            )
            ->when(
                $validated['fecha_desde'] ?? null,
                fn (Builder $builder, string $fechaDesde) => $builder->whereDate(
                    'solicitada_at',
                    '>=',
                    $fechaDesde
                )
            )
            ->when(
                $validated['fecha_hasta'] ?? null,
                fn (Builder $builder, string $fechaHasta) => $builder->whereDate(
                    'solicitada_at',
                    '<=',
                    $fechaHasta
                )
            );

        $invoiceRequests = $query
            ->paginate(15)
            ->withQueryString();

        $statusCounts = B2cInvoiceRequest::query()
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view(
            'crm.facturacion.index',
            compact('invoiceRequests', 'statusCounts')
        );
    }

    public function show(B2cInvoiceRequest $invoiceRequest): View
    {
        $invoiceRequest->load([
            'user:id,name,apellido_paterno,apellido_materno,email',
            'cotizacion:id,user_id,estatus,payment_status,payment_external_reference,precio,created_at',
            'fiscalProfile:id,user_id,rfc,razon_social',
            'managedBy:id,name,apellido_paterno,apellido_materno,email',
        ]);

        $regimenes = config('b2c_fiscal.regimenes', []);
        $usosCfdi = config('b2c_fiscal.usos_cfdi', []);

        return view(
            'crm.facturacion.show',
            compact('invoiceRequest', 'regimenes', 'usosCfdi')
        );
    }

    public function iniciarAtencion(
        Request $request,
        B2cInvoiceRequest $invoiceRequest
    ): RedirectResponse {
        $actualizada = B2cInvoiceRequest::query()
            ->whereKey($invoiceRequest->getKey())
            ->where('status', B2cInvoiceRequest::STATUS_SOLICITADA)
            ->update([
                'status' => B2cInvoiceRequest::STATUS_EN_PROCESO,
                'managed_by_user_id' => $request->user()->getAuthIdentifier(),
                'attended_at' => now(),
                'error_message' => null,
            ]);

        if ($actualizada !== 1) {
            return redirect()
                ->route('crm.facturacion.show', $invoiceRequest)
                ->with(
                    'error',
                    'La solicitud ya no está en estado Solicitada y no puede iniciarse nuevamente.'
                );
        }

        return redirect()
            ->route('crm.facturacion.show', $invoiceRequest)
            ->with(
                'success',
                'La solicitud quedó marcada como En proceso.'
            );
    }

    public function guardarGestion(
        Request $request,
        B2cInvoiceRequest $invoiceRequest
    ): RedirectResponse {
        $validated = $request->validate(
            [
                'internal_notes' => ['nullable', 'string', 'max:4000'],
            ],
            [
                'internal_notes.max' => 'Las notas internas no pueden exceder 4000 caracteres.',
            ]
        );

        $invoiceRequest->update([
            'internal_notes' => $validated['internal_notes'] ?? null,
            'managed_by_user_id' => $request->user()->getAuthIdentifier(),
        ]);

        return redirect()
            ->route('crm.facturacion.show', $invoiceRequest)
            ->with('success', 'Las notas internas fueron guardadas.');
    }

    public function rechazar(
        Request $request,
        B2cInvoiceRequest $invoiceRequest
    ): RedirectResponse {
        $validated = $request->validate(
            [
                'rejection_reason' => ['required', 'string', 'min:10', 'max:2000'],
                'rejection_category' => ['nullable','in:RFC_INVALIDO,CONSTANCIA_ILEGIBLE,DATOS_INCOMPLETOS,REGIMEN_INCORRECTO,USO_CFDI_INCORRECTO,OTRO'],
            ],
            [
                'rejection_reason.required' => 'Captura el motivo del rechazo.',
                'rejection_reason.min' => 'El motivo del rechazo debe contener al menos 10 caracteres.',
                'rejection_reason.max' => 'El motivo del rechazo no puede exceder 2000 caracteres.',
            ]
        );

        $actualizada = B2cInvoiceRequest::query()
            ->whereKey($invoiceRequest->getKey())
            ->whereIn('status', [
                B2cInvoiceRequest::STATUS_SOLICITADA,
                B2cInvoiceRequest::STATUS_EN_PROCESO,
            ])
            ->update([
                'status' => B2cInvoiceRequest::STATUS_RECHAZADA,
                'rejection_reason' => trim($validated['rejection_reason']),
                'rejection_category' => $validated['rejection_category'] ?? 'OTRO',
                'cancellation_reason' => null,
                'managed_by_user_id' => $request->user()->getAuthIdentifier(),
                'attended_at' => $invoiceRequest->attended_at ?: now(),
                'rejected_at' => now(),
                'cancelled_at' => null,
                'error_message' => null,
            ]);

        if ($actualizada !== 1) {
            return redirect()
                ->route('crm.facturacion.show', $invoiceRequest)
                ->with(
                    'error',
                    'La solicitud ya no puede rechazarse porque cambió de estado.'
                );
        }

        $rejected=$invoiceRequest->fresh();
        B2cInvoiceRequestEvent::create(['invoice_request_id'=>$rejected->id,'user_id'=>$request->user()->getAuthIdentifier(),'event_type'=>'REJECTED','previous_status'=>$invoiceRequest->status,'new_status'=>B2cInvoiceRequest::STATUS_RECHAZADA,'public_reason'=>$rejected->rejection_reason,'internal_note'=>$rejected->internal_notes]);
        app(CrmAdminNotificationService::class)->rejected($rejected->loadMissing('user'));

        return redirect()
            ->route('crm.facturacion.show', $invoiceRequest)
            ->with('success', 'La solicitud quedó marcada como Rechazada.');
    }

    public function cancelar(
        Request $request,
        B2cInvoiceRequest $invoiceRequest
    ): RedirectResponse {
        $validated = $request->validate(
            [
                'cancellation_reason' => ['required', 'string', 'min:10', 'max:2000'],
            ],
            [
                'cancellation_reason.required' => 'Captura el motivo de la cancelación.',
                'cancellation_reason.min' => 'El motivo de la cancelación debe contener al menos 10 caracteres.',
                'cancellation_reason.max' => 'El motivo de la cancelación no puede exceder 2000 caracteres.',
            ]
        );

        $actualizada = B2cInvoiceRequest::query()
            ->whereKey($invoiceRequest->getKey())
            ->whereIn('status', [
                B2cInvoiceRequest::STATUS_SOLICITADA,
                B2cInvoiceRequest::STATUS_EN_PROCESO,
            ])
            ->update([
                'status' => B2cInvoiceRequest::STATUS_CANCELADA,
                'cancellation_reason' => trim($validated['cancellation_reason']),
                'rejection_reason' => null,
                'managed_by_user_id' => $request->user()->getAuthIdentifier(),
                'attended_at' => $invoiceRequest->attended_at ?: now(),
                'cancelled_at' => now(),
                'rejected_at' => null,
                'error_message' => null,
            ]);

        if ($actualizada !== 1) {
            return redirect()
                ->route('crm.facturacion.show', $invoiceRequest)
                ->with(
                    'error',
                    'La solicitud ya no puede cancelarse porque cambió de estado.'
                );
        }

        return redirect()
            ->route('crm.facturacion.show', $invoiceRequest)
            ->with('success', 'La solicitud quedó marcada como Cancelada.');
    }

    public function subirDocumentos(
        Request $request,
        B2cInvoiceRequest $invoiceRequest,
        InvoiceFulfillmentService $fulfillment
    ): RedirectResponse {
        $request->validate(
            [
                'cfdi_zip' => ['required', 'file', 'max:15360'],
            ],
            [
                'cfdi_zip.required' => 'Selecciona el ZIP con el PDF y XML del CFDI.',
                'cfdi_zip.file' => 'El archivo fiscal cargado no es válido.',
                'cfdi_zip.max' => 'El ZIP debe pesar como máximo 15 MB.',
            ]
        );

        if (! $invoiceRequest->puedeCargarDocumentos()) {
            return redirect()
                ->route('crm.facturacion.show', $invoiceRequest)
                ->with(
                    'error',
                    'Los documentos solo pueden cargarse cuando la solicitud está En proceso.'
                );
        }

        $managerId = (int) $request->user()->getAuthIdentifier();

        try {
            $fulfillment->fulfillFromManualZip(
                $invoiceRequest,
                $request->file('cfdi_zip'),
                $managerId
            );
        } catch (CfdiZipValidationException $exception) {
            return redirect()
                ->route('crm.facturacion.show', $invoiceRequest)
                ->withErrors([
                    'cfdi_zip' => $exception->getMessage(),
                ]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()
                ->route('crm.facturacion.show', $invoiceRequest)
                ->with(
                    'error',
                    'No fue posible registrar los documentos fiscales. No se cambió el estado de la solicitud.'
                );
        }

        return redirect()
            ->route('crm.facturacion.show', $invoiceRequest)
            ->with(
                'success',
                'El CFDI fue validado y la solicitud quedó marcada como Facturada.'
            );
    }
}
