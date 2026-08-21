<?php
namespace App\Domain\AI\Tenancy;
use App\Domain\AI\Support\Exceptions\AiTenantMismatchException;
use App\Domain\Network\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
abstract class AiTenantModel extends Model
{
    public function newEloquentBuilder($query): Builder
    {
        return new AiTenantBuilder($query);
    }

    public function immutableAiAttributes(): array
    {
        return array_values(array_unique(array_merge(['tenant_id'], $this->immutableIdentityAttributes())));
    }

    protected function immutableIdentityAttributes(): array
    {
        return [];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new AiTenantScope());
        static::creating(function (self $model): void {
            $tenantId = app(AiTenantBoundary::class)->requireTenantId();
            if ($model->getAttribute('tenant_id') !== null && (int) $model->getAttribute('tenant_id') !== $tenantId) {
                throw new AiTenantMismatchException('AI model tenant does not match the active tenant.');
            }
            $model->setAttribute('tenant_id', $tenantId);
        });
        static::saving(function (self $model): void {
            if (! $model->exists) return;
            foreach ($model->immutableAiAttributes() as $attribute) {
                if ($model->isDirty($attribute)) {
                    throw new \App\Domain\AI\Support\Exceptions\AiImmutableAttributeException("AI model attribute {$attribute} is immutable.");
                }
            }
        });
    }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
