<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ZigoDeployment extends Model
{
    protected $fillable = ['environment', 'package_name', 'branch', 'commit_hash', 'package_sha256', 'status', 'requested_by_user_id', 'approved_by_user_id', 'started_at', 'finished_at', 'backup_path', 'rollback_available', 'summary'];
    protected $casts = ['started_at' => 'datetime', 'finished_at' => 'datetime', 'rollback_available' => 'boolean'];

    public function files(): HasMany { return $this->hasMany(ZigoDeploymentFile::class, 'deployment_id'); }
    public function logs(): HasMany { return $this->hasMany(ZigoDeploymentLog::class, 'deployment_id'); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by_user_id'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by_user_id'); }
    public function releases(): HasMany { return $this->hasMany(ZigoDevOpsRelease::class, 'deployment_id'); }
    public function alerts(): HasMany { return $this->hasMany(ZigoDevOpsAlert::class, 'deployment_id'); }
}
