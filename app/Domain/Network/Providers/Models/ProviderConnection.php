<?php

namespace App\Domain\Network\Providers\Models;

use App\Domain\Network\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

final class ProviderConnection extends Model
{
    public const LOCAL_SHIPPING = 'LOCAL_SHIPPING';

    protected $table = 'network_provider_connections';

    // Configuration is trusted server-side work, never request mass assignment.
    protected $guarded = ['*'];

    protected $attributes = ['status' => 'inactive'];

    protected $casts = [
        'consumer_tenant_id' => 'integer',
        'provider_tenant_id' => 'integer',
        'valid_from' => 'datetime',
        'valid_to' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $connection): void {
            $errors = [];
            if ($connection->consumer_tenant_id <= 0 || $connection->provider_tenant_id <= 0
                || $connection->consumer_tenant_id === $connection->provider_tenant_id) {
                $errors['provider_tenant_id'] = 'Consumer and provider must be distinct persisted tenants.';
            }
            if (! self::isValidCapability($connection->capability)) {
                $errors['capability'] = 'Capability must be an uppercase identifier of at most 64 characters.';
            }
            if (! in_array($connection->status, ['active', 'inactive'], true)) {
                $errors['status'] = 'Connection status must be active or inactive.';
            }
            if ($connection->valid_from !== null && $connection->valid_to !== null
                && $connection->valid_from->gt($connection->valid_to)) {
                $errors['valid_to'] = 'Validity end must be on or after validity start.';
            }
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
        });
    }

    public static function isValidCapability(?string $capability): bool
    {
        return $capability !== null && preg_match('/\A[A-Z][A-Z0-9_]{0,63}\z/', $capability) === 1;
    }

    public function consumer(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'consumer_tenant_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'provider_tenant_id');
    }
}
