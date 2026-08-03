<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZigoHealthCheck extends Model
{
    protected $fillable = ['environment', 'check_key', 'status', 'http_status', 'duration_ms', 'message', 'checked_by_user_id', 'checked_at'];
    protected $casts = ['checked_at' => 'datetime', 'http_status' => 'integer', 'duration_ms' => 'integer'];
    public function checkedBy(): BelongsTo { return $this->belongsTo(User::class, 'checked_by_user_id'); }
}
