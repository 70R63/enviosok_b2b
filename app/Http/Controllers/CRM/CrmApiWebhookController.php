<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use App\Models\ApiWebhookDelivery;
use App\Models\ApiWebhookEndpoint;
use App\Services\ApiHub\Webhooks\BillingWebhookPublisher;
use App\Services\ApiHub\Webhooks\WebhookSigner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CrmApiWebhookController extends Controller
{
    public function index(ApiClient $apiClient): View
    {
        $endpoints = ApiWebhookEndpoint::query()
            ->where('api_client_id', $apiClient->id)
            ->withCount([
                'deliveries',
                'deliveries as pending_deliveries_count' =>
                    fn ($query) => $query->whereIn(
                        'status',
                        [
                            ApiWebhookDelivery::STATUS_PENDING,
                            ApiWebhookDelivery::STATUS_RETRY,
                        ]
                    ),
            ])
            ->orderBy('environment')
            ->orderBy('name')
            ->get();

        $deliveries = ApiWebhookDelivery::query()
            ->with([
                'endpoint:id,name,url,environment',
                'billingRequest:id,external_id',
            ])
            ->where('api_client_id', $apiClient->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $webhooksProductEnabled = $apiClient
            ->products()
            ->where('api_products.code', 'WEBHOOKS')
            ->where('api_products.active', true)
            ->wherePivot('active', true)
            ->exists();

        $supportedEvents =
            BillingWebhookPublisher::supportedEvents();

        return view(
            'crm.api_hub.webhooks.index',
            compact(
                'apiClient',
                'endpoints',
                'deliveries',
                'webhooksProductEnabled',
                'supportedEvents'
            )
        );
    }

    public function store(
        Request $request,
        ApiClient $apiClient,
        WebhookSigner $signer
    ): RedirectResponse {
        $this->ensureProductEnabled($apiClient);

        $validated = $this->validateEndpoint($request);
        $plainSecret = $signer->generateSecret();

        ApiWebhookEndpoint::create([
            'api_client_id' => $apiClient->id,
            'name' => trim($validated['name']),
            'environment' => $validated['environment'],
            'url' => trim($validated['url']),
            'secret_encrypted' =>
                Crypt::encryptString($plainSecret),
            'secret_prefix' => substr($plainSecret, 0, 22),
            'events' => array_values($validated['events']),
            'active' => true,
        ]);

        return redirect()
            ->route('crm.api-hub.webhooks.index', $apiClient)
            ->with(
                'success',
                'Endpoint webhook creado correctamente.'
            )
            ->with('new_webhook_secret', $plainSecret);
    }

    public function update(
        Request $request,
        ApiClient $apiClient,
        ApiWebhookEndpoint $webhookEndpoint
    ): RedirectResponse {
        $this->ensureOwnership($apiClient, $webhookEndpoint);
        $this->ensureProductEnabled($apiClient);

        $validated = $this->validateEndpoint(
            $request,
            false,
            $webhookEndpoint->environment
        );

        $webhookEndpoint->update([
            'name' => trim($validated['name']),
            'url' => trim($validated['url']),
            'events' => array_values($validated['events']),
        ]);

        return redirect()
            ->route('crm.api-hub.webhooks.index', $apiClient)
            ->with(
                'success',
                'Configuración webhook actualizada.'
            );
    }

    public function toggle(
        ApiClient $apiClient,
        ApiWebhookEndpoint $webhookEndpoint
    ): RedirectResponse {
        $this->ensureOwnership($apiClient, $webhookEndpoint);

        $webhookEndpoint->update([
            'active' => ! $webhookEndpoint->active,
        ]);

        return redirect()
            ->route('crm.api-hub.webhooks.index', $apiClient)
            ->with(
                'success',
                'Estado del endpoint webhook actualizado.'
            );
    }

    public function rotateSecret(
        ApiClient $apiClient,
        ApiWebhookEndpoint $webhookEndpoint,
        WebhookSigner $signer
    ): RedirectResponse {
        $this->ensureOwnership($apiClient, $webhookEndpoint);

        $plainSecret = $signer->generateSecret();

        DB::transaction(function () use (
            $webhookEndpoint,
            $plainSecret,
            $signer
        ): void {
            $webhookEndpoint->update([
                'secret_encrypted' =>
                    Crypt::encryptString($plainSecret),
                'secret_prefix' => substr($plainSecret, 0, 22),
            ]);

            ApiWebhookDelivery::query()
                ->where(
                    'api_webhook_endpoint_id',
                    $webhookEndpoint->id
                )
                ->whereIn('status', [
                    ApiWebhookDelivery::STATUS_PENDING,
                    ApiWebhookDelivery::STATUS_RETRY,
                ])
                ->get()
                ->each(function (
                    ApiWebhookDelivery $delivery
                ) use ($plainSecret, $signer): void {
                    $delivery->update([
                        'signature' => $signer->sign(
                            $plainSecret,
                            $delivery->signature_timestamp,
                            $delivery->payload_json
                        ),
                    ]);
                });
        });

        return redirect()
            ->route('crm.api-hub.webhooks.index', $apiClient)
            ->with(
                'success',
                'Secreto webhook rotado correctamente.'
            )
            ->with('new_webhook_secret', $plainSecret);
    }

    private function validateEndpoint(
        Request $request,
        bool $includeEnvironment = true,
        ?string $fixedEnvironment = null
    ): array {
        $supported = implode(
            ',',
            array_keys(
                BillingWebhookPublisher::supportedEvents()
            )
        );

        $rules = [
            'name' => [
                'required',
                'string',
                'max:191',
            ],
            'url' => [
                'required',
                'url',
                'max:2048',
            ],
            'events' => [
                'required',
                'array',
                'min:1',
            ],
            'events.*' => [
                'required',
                'string',
                'distinct',
                'in:' . $supported,
            ],
        ];

        if ($includeEnvironment) {
            $rules['environment'] = [
                'required',
                'in:sandbox,production',
            ];
        }

        $validated = $request->validate($rules);
        $environment = $includeEnvironment
            ? $validated['environment']
            : $fixedEnvironment;

        if (
            $environment === 'production'
            && ! Str::startsWith(
                strtolower($validated['url']),
                'https://'
            )
        ) {
            throw ValidationException::withMessages([
                'url' =>
                    'Los webhooks de producción requieren una URL HTTPS.',
            ]);
        }

        if (! $includeEnvironment) {
            $validated['environment'] = $fixedEnvironment;
        }

        return $validated;
    }

    private function ensureOwnership(
        ApiClient $apiClient,
        ApiWebhookEndpoint $webhookEndpoint
    ): void {
        if ($webhookEndpoint->api_client_id !== $apiClient->id) {
            abort(404);
        }
    }

    private function ensureProductEnabled(
        ApiClient $apiClient
    ): void {
        $enabled = $apiClient
            ->products()
            ->where('api_products.code', 'WEBHOOKS')
            ->where('api_products.active', true)
            ->wherePivot('active', true)
            ->exists();

        if (! $enabled) {
            throw ValidationException::withMessages([
                'product' =>
                    'El producto WEBHOOKS debe estar habilitado para este cliente.',
            ]);
        }
    }
}
