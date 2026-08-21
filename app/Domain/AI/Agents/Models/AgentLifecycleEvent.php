<?php

namespace App\Domain\AI\Agents\Models;

use App\Domain\AI\Support\Exceptions\AiImmutableAttributeException;
use App\Domain\AI\Agents\Data\AuthorizedAiLifecycleActor;
use App\Domain\AI\Agents\Enums\AgentLifecycleEventType;
use App\Domain\AI\Tenancy\AiTenantModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AgentLifecycleEvent extends AiTenantModel
{
    private bool $authorizedAppend = false;
    public $timestamps = false;
    protected $table = 'ai_agent_lifecycle_events';
    protected $guarded = ['*'];
    protected $casts = ['metadata' => 'array', 'occurred_at' => 'datetime'];

    protected function immutableIdentityAttributes(): array
    {
        return ['actor_user_id','aggregate_type','aggregate_id','resource_type','resource_id','event','from_status','to_status','reason','metadata','occurred_at'];
    }

    protected static function booted(): void
    {
        parent::booted();
        static::creating(function(self$model):void{if(!$model->authorizedAppend)throw new AiImmutableAttributeException('AI lifecycle audit creation requires the authorized audit boundary.');});
        static::deleting(fn() => throw new AiImmutableAttributeException('AI lifecycle audit events are append-only.'));
    }

    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_user_id'); }
    public function isAiAppendOnly():bool{return true;}
    public function update(array$attributes=[],array$options=[]):never{throw new AiImmutableAttributeException('AI lifecycle audit events are append-only.');}
    public function updateQuietly(array$attributes=[],array$options=[]):never{throw new AiImmutableAttributeException('AI lifecycle audit events are append-only.');}
    /** @internal Called exclusively by AgentLifecycleAudit. */
    public function appendFromAudit(AuthorizedAiLifecycleActor$a,Agent$aggregate,\Illuminate\Database\Eloquent\Model$resource,AgentLifecycleEventType$event,?string$from,?string$to,?string$reason,array$metadata):void{if(\Illuminate\Support\Facades\DB::transactionLevel()<1)throw new \LogicException('AI lifecycle audit requires an active transaction.');$a=app(\App\Domain\AI\Agents\Services\AiLifecycleAuthorization::class)->revalidate($a);$this->authorizedAppend=true;try{$this->actor_user_id=$a->actorUserId;$this->aggregate_type=$aggregate::class;$this->aggregate_id=$aggregate->id;$this->resource_type=$resource::class;$this->resource_id=$resource->getKey();$this->event=$event->value;$this->from_status=$from;$this->to_status=$to;$this->reason=$reason;$this->metadata=$metadata;$this->occurred_at=now();$this->save();}finally{$this->authorizedAppend=false;}}
}
