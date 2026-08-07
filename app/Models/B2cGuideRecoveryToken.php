<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class B2cGuideRecoveryToken extends Model
{
    protected $fillable = ['cotizacion_id', 'email_hash', 'token_hash', 'attempts', 'max_attempts', 'expires_at', 'last_used_at', 'revoked_at'];
    protected $casts = ['expires_at' => 'datetime', 'last_used_at' => 'datetime', 'revoked_at' => 'datetime'];
    protected $hidden = ['email_hash', 'token_hash'];
    public function cotizacion() { return $this->belongsTo(B2cCotizacion::class, 'cotizacion_id'); }
}
