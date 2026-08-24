<?php

namespace App\Domain\Shipping\Local\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class LocalShippingQuoteSnapshot extends Model
{
    protected $fillable = ['tenant_id','service_id','origin','destination','package_type','weight_kg','dimensions','distance_meters','pricing_strategy','matched_tariff','amount','currency','expires_at'];
    protected $casts = ['origin'=>'array','destination'=>'array','dimensions'=>'array','matched_tariff'=>'array','weight_kg'=>'decimal:2','amount'=>'decimal:2','expires_at'=>'immutable_datetime'];
    protected static function booted(): void { self::creating(fn (self $snapshot) => $snapshot->uuid ??= (string) Str::uuid()); }
    public function service(): BelongsTo { return $this->belongsTo(LocalShippingService::class, 'service_id'); }
}
