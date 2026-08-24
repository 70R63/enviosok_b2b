<?php
namespace App\Domain\AI\Channels\Webchat\Models;
use App\Domain\AI\Conversations\Models\Conversation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
final class WebchatSession extends Model
{
    protected $table='ai_webchat_sessions'; protected $guarded=['*']; protected $casts=['expires_at'=>'datetime','last_seen_at'=>'datetime','closed_at'=>'datetime'];
    public function getRouteKeyName():string{return'uuid';}
    protected static function booted():void{static::creating(function(self$m):void{$m->uuid??=(string)Str::uuid();$channel=WebchatChannel::query()->findOrFail($m->webchat_channel_id);$conversation=Conversation::query()->findOrFail($m->conversation_id);if((int)$channel->tenant_id!==(int)$m->tenant_id||(int)$conversation->tenant_id!==(int)$m->tenant_id||(int)$conversation->agent_id!==(int)$channel->agent_id)throw new \DomainException('Webchat Session aggregate is inconsistent.');});}
    public function channel():BelongsTo{return$this->belongsTo(WebchatChannel::class,'webchat_channel_id');}
    public function conversation():BelongsTo{return$this->belongsTo(Conversation::class);}
    public function isAvailable():bool{return$this->closed_at===null&&$this->expires_at?->isFuture();}
}
