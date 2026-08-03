<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZigoDeploymentFile extends Model
{
    protected $fillable = ['deployment_id', 'relative_path', 'existed_before', 'backup_fingerprint', 'deployed_fingerprint'];
    protected $casts = ['existed_before' => 'boolean'];
    public function deployment(): BelongsTo { return $this->belongsTo(ZigoDeployment::class); }
}
