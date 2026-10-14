<?php

namespace App\Domain\Network\Providers;

use App\Domain\Network\Operations\NetworkAdminAuditService;
use App\Domain\Network\Providers\Models\ProviderPayable;
use App\Domain\Network\Providers\Models\ProviderSettlementEntry;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ProviderSettlementService
{
    public function authorize(User $actor): void
    {
        abort_unless($actor->hasRol('sysadmin'), 403);
    }

    /** Consumer is derived from the stored payable; no request tenant/price authority. */
    public function settle(User $actor, string $uuid, array $input): ProviderSettlementEntry
    {
        $this->authorize($actor);
        $data = Validator::make($input, ['amount' => ['required', 'string', 'regex:/^[0-9]{1,10}(\.[0-9]{1,2})?$/'], 'currency' => 'required|regex:/^[A-Z]{3}$/',
            'payment_reference' => 'required|string|max:128|regex:/^[A-Za-z0-9][A-Za-z0-9._:\/-]*$/', 'idempotency_key' => 'required|string|min:8|max:128|regex:/^[A-Za-z0-9._:-]+$/',
            'paid_at' => 'required|date|before_or_equal:now', 'notes' => ['nullable', 'string', 'max:500', 'not_regex:/[\x00-\x1F\x7F]/']])->validate();
        $amount = BigDecimal::of($data['amount'])->toScale(2);
        if (! $amount->isGreaterThan('0')) {
            throw ValidationException::withMessages(['amount' => 'El importe debe ser positivo.']);
        }
        $data['amount'] = (string) $amount;
        $data['idempotency_key'] = strtoupper($data['idempotency_key']);
        $data['payment_reference'] = strtoupper($data['payment_reference']);
        $data['paid_at'] = Carbon::parse($data['paid_at'])->utc()->format('Y-m-d H:i:s');
        $data['notes'] = trim($data['notes'] ?? '') ?: null;
        $fingerprint = hash('sha256', json_encode(array_intersect_key($data, array_flip(['amount', 'currency', 'payment_reference', 'paid_at', 'notes'])) + ['payable_uuid' => $uuid], JSON_THROW_ON_ERROR));
        $payable = ProviderPayable::where('uuid', $uuid)->firstOrFail();

        return DB::transaction(function () use ($actor, $payable, $data, $fingerprint) {
            // Serialize reference reuse across the pair as well as each payable's remaining amount.
            Tenant::whereIn('id', [$payable->consumer_tenant_id, $payable->provider_tenant_id])->orderBy('id')->lockForUpdate()->get();
            $payable = app(ProviderPayableService::class)->sync($payable->fulfillment);
            $payable = ProviderPayable::whereKey($payable->id)->lockForUpdate()->firstOrFail();
            $existing = ProviderSettlementEntry::where('provider_payable_id', $payable->id)->where('idempotency_key', $data['idempotency_key'])->first();
            $reference = ProviderSettlementEntry::where('consumer_tenant_id', $payable->consumer_tenant_id)->where('provider_tenant_id', $payable->provider_tenant_id)
                ->where('currency', $data['currency'])->where('payment_reference', $data['payment_reference'])->first();
            foreach ([$existing, $reference] as $old) {
                if ($old) {
                    abort_unless(hash_equals($old->request_fingerprint, $fingerprint), 409);

                    return $old;
                }
            }
            if ($data['currency'] !== $payable->currency) {
                throw ValidationException::withMessages(['currency' => 'La moneda debe coincidir con la obligación.']);
            }
            if ($payable->financialStatus() !== 'PAYABLE') {
                throw ValidationException::withMessages(['status' => 'Sólo obligaciones PAYABLE pueden liquidarse; REVIEW requiere atención previa.']);
            }
            if (BigDecimal::of($data['amount'])->isGreaterThan($payable->remainingAmount())) {
                throw ValidationException::withMessages(['amount' => 'El importe excede el pendiente.']);
            }
            $before = $payable->financialStatus();
            $entry = ProviderSettlementEntry::forceCreate($data + ['provider_payable_id' => $payable->id, 'consumer_tenant_id' => $payable->consumer_tenant_id,
                'provider_tenant_id' => $payable->provider_tenant_id, 'request_fingerprint' => $fingerprint, 'actor_user_id' => $actor->id, 'created_at' => now()]);
            $status = BigDecimal::of($payable->remainingAmount())->isZero() ? 'SETTLED' : 'PAYABLE';
            app(ProviderPayableService::class)->event($payable, 'settlement:'.$entry->uuid, 'MANUAL_SETTLEMENT', $status, $actor->id, 'NETWORK_UI', $entry->paid_at, $entry->id);
            app(NetworkAdminAuditService::class)->record($actor->id, 'provider.manual_settlement', $entry, ['status' => $before], ['status' => $status, 'amount' => $entry->amount, 'currency' => $entry->currency, 'reference' => $entry->payment_reference]);

            return $entry;
        }, 3);
    }
}
