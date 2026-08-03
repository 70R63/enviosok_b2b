<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZigoDeploymentLog extends Model
{
    public const UPDATED_AT = null;
    protected $fillable = ['deployment_id', 'level', 'step', 'message'];
    public function deployment(): BelongsTo { return $this->belongsTo(ZigoDeployment::class); }
}
