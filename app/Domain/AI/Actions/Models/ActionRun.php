<?php

namespace App\Domain\AI\Actions\Models;

use App\Domain\AI\Actions\Enums\{ActionEffect,ActionRunStatus};
use App\Domain\AI\Agents\Models\{Agent,AgentContractVersion,AgentVersion};
use App\Domain\AI\Conversations\Models\{Conversation,ConversationMessage};
use App\Domain\AI\Runtime\Models\RuntimeRun;
use App\Domain\AI\Tenancy\AiTenantModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class ActionRun extends AiTenantModel
{
    protected $table='ai_action_runs'; protected $guarded=['*'];
    protected $casts=['effect'=>ActionEffect::class,'status'=>ActionRunStatus::class,'input'=>'encrypted:array','output'=>'encrypted:array','requested_at'=>'datetime','confirmed_at'=>'datetime','started_at'=>'datetime','execution_expires_at'=>'datetime','completed_at'=>'datetime','failed_at'=>'datetime'];
    public function getRouteKeyName():string{return'uuid';}
    public function isAiAppendOnly():bool{return true;}
    protected function immutableIdentityAttributes():array{return['uuid','agent_id','agent_version_id','contract_version_id','conversation_id','source_message_id','runtime_run_id','conversation_turn_token_hash','action_key','effect','idempotency_key','input','status','output','safe_error_code','requested_at','confirmed_at','confirmed_by_user_id','started_at','execution_expires_at','completed_at','failed_at'];}
    protected static function booted():void{parent::booted();static::creating(function(self$m):void{$m->uuid??=(string)Str::uuid();if(!preg_match('/^[a-z0-9][a-z0-9_.-]{0,63}$/D',(string)$m->action_key)||!preg_match('/^[a-f0-9]{64}$/D',(string)$m->idempotency_key)||!is_array($m->input)||!in_array($m->status,[ActionRunStatus::Requested,ActionRunStatus::AwaitingConfirmation],true))throw new \DomainException('Invalid initial Action Run state.');});}
    public function beginAutomatic():void{if($this->originalAiStatus()!==ActionRunStatus::Requested->value)throw new \DomainException('Action is not available for execution.');$this->persistNamedLifecycle(['status','started_at'],function(){$this->status=ActionRunStatus::Executing;$this->started_at=now();});}
    public function begin(int$userId):void{if($this->originalAiStatus()!==ActionRunStatus::AwaitingConfirmation->value)throw new \DomainException('Action is not awaiting confirmation.');$this->persistNamedLifecycle(['status','confirmed_at','confirmed_by_user_id','started_at'],function()use($userId){$this->status=ActionRunStatus::Executing;$this->confirmed_at=now();$this->confirmed_by_user_id=$userId;$this->started_at=now();});}
    public function succeed(array$output,?string$tokenHash=null):void{if($this->originalAiStatus()!==ActionRunStatus::Executing->value)throw new \DomainException('Action is not executing.');$this->assertTurnToken($tokenHash);$this->persistNamedLifecycle(['status','output','completed_at'],function()use($output){$this->status=ActionRunStatus::Succeeded;$this->output=$output;$this->completed_at=now();});}
    public function fail(string$code,?string$tokenHash=null):void{if($this->originalAiStatus()!==ActionRunStatus::Executing->value||!in_array($code,['action_not_registered','action_not_allowed','action_output_invalid','action_failed'],true))throw new \DomainException('Action cannot fail with that state or code.');$this->assertTurnToken($tokenHash);$this->persistNamedLifecycle(['status','safe_error_code','failed_at'],function()use($code){$this->status=ActionRunStatus::Failed;$this->safe_error_code=$code;$this->failed_at=now();});}
    public function bindTurn(string $tokenHash, \DateTimeInterface $expiresAt):void{if(!in_array($this->originalAiStatus(),[ActionRunStatus::Requested->value,ActionRunStatus::AwaitingConfirmation->value],true)||!preg_match('/^[a-f0-9]{64}$/D',$tokenHash))throw new \DomainException('Action cannot bind that turn.');$this->persistNamedLifecycle(['conversation_turn_token_hash','execution_expires_at'],function()use($tokenHash,$expiresAt){$this->conversation_turn_token_hash=$tokenHash;$this->execution_expires_at=$expiresAt;});}
    public function requireReconciliation(?string$tokenHash=null):void{if($this->originalAiStatus()!==ActionRunStatus::Executing->value)throw new \DomainException('Action is not executing.');$this->assertTurnToken($tokenHash);$this->persistNamedLifecycle(['status','safe_error_code','failed_at'],function(){$this->status=ActionRunStatus::ReconciliationRequired;$this->safe_error_code='action_outcome_unknown';$this->failed_at=now();});}
    private function assertTurnToken(?string$tokenHash):void{if(!\Illuminate\Support\Facades\Schema::hasColumn($this->getTable(),'conversation_turn_token_hash'))return;$stored=$this->conversation_turn_token_hash;if(!is_string($stored)||!is_string($tokenHash)||!hash_equals($stored,$tokenHash))throw new \App\Domain\AI\Conversations\Exceptions\ConversationTurnSupersededException;}
    public function conversation():BelongsTo{return$this->belongsTo(Conversation::class);} public function sourceMessage():BelongsTo{return$this->belongsTo(ConversationMessage::class,'source_message_id');} public function runtimeRun():BelongsTo{return$this->belongsTo(RuntimeRun::class);} public function agent():BelongsTo{return$this->belongsTo(Agent::class);} public function agentVersion():BelongsTo{return$this->belongsTo(AgentVersion::class);} public function contractVersion():BelongsTo{return$this->belongsTo(AgentContractVersion::class,'contract_version_id');} public function confirmer():BelongsTo{return$this->belongsTo(User::class,'confirmed_by_user_id');}
}
