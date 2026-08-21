<?php
namespace App\Domain\AI\Tenancy;
use App\Domain\AI\Support\Exceptions\AiTenantMismatchException;
use App\Domain\Network\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
abstract class AiTenantModel extends Model
{
    private array $aiLifecycleAllowed = [];
    private bool $aiInsertInProgress = false;

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

    public function allowsAiProtectedUpdate(array $columns): bool
    {
        return $columns !== [] && array_diff($columns, $this->aiLifecycleAllowed) === [];
    }

    public function aiInsertInProgress(): bool { return $this->aiInsertInProgress; }
    public function isAiAppendOnly(): bool { return false; }

    protected function persistNamedLifecycle(array $allowed, callable $mutation): void
    {
        if ($this->aiLifecycleAllowed !== []) throw new \LogicException('Nested lifecycle persistence on one model is prohibited.');
        $this->aiLifecycleAllowed = $allowed;
        try { $mutation(); $this->save(); } finally { $this->aiLifecycleAllowed = []; }
    }

    protected function assertLifecycleActor(\App\Domain\AI\Agents\Data\AuthorizedAiLifecycleActor $actor): void
    {
        if (\Illuminate\Support\Facades\DB::transactionLevel() < 1) {
            throw new \App\Domain\AI\Agents\Exceptions\InvalidAgentTransitionException('Lifecycle persistence requires an active transaction.');
        }
        $fresh = app(\App\Domain\AI\Agents\Services\AiLifecycleAuthorization::class)->revalidate($actor);
        if ((int)$this->tenant_id !== $fresh->tenantId) throw new \App\Domain\AI\Support\Exceptions\AiTenantMismatchException('Authorized lifecycle actor does not match the resource tenant.');
    }

    protected function originalAiStatus(): string
    {
        return (string) $this->getRawOriginal('status');
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
            $model->aiInsertInProgress = true;
        });
        static::created(function (self $model): void { $model->aiInsertInProgress = false; });
        static::saving(function (self $model): void {
            if (! $model->exists) return;
            foreach ($model->immutableAiAttributes() as $attribute) {
                if ($model->isDirty($attribute) && ! in_array($attribute, $model->aiLifecycleAllowed, true)) {
                    throw new \App\Domain\AI\Support\Exceptions\AiImmutableAttributeException("AI model attribute {$attribute} is immutable.");
                }
            }
        });
    }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
