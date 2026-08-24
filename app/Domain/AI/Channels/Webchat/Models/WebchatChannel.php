<?php
namespace App\Domain\AI\Channels\Webchat\Models;
use App\Domain\AI\Agents\Models\Agent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
final class WebchatChannel extends Model
{
    protected $table='ai_webchat_channels'; protected $guarded=['*']; protected $casts=['enabled'=>'boolean','allowed_origins'=>'array'];
    public function getRouteKeyName():string{return'uuid';}
    protected static function booted():void{static::creating(function(self$m):void{$m->uuid??=(string)Str::uuid();$m->public_key??='wc_'.rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');$agent=Agent::query()->withoutGlobalScopes()->findOrFail($m->agent_id);if((int)$agent->tenant_id!==(int)$m->tenant_id)throw new \DomainException('Webchat Channel and Agent must share a tenant.');});}
    public function agent():BelongsTo{return$this->belongsTo(Agent::class);} public function creator():BelongsTo{return$this->belongsTo(User::class,'created_by_user_id');}
}
