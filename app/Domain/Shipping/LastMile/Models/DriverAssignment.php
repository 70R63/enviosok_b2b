<?php

namespace App\Domain\Shipping\LastMile\Models;

use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Shipping\Local\Models\LocalShipment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class DriverAssignment extends Model
{
    protected $table = 'local_driver_assignments';

    protected $fillable = ['tenant_id', 'local_shipment_id', 'active_shipment_id', 'driver_profile_id', 'assigned_by_user_id', 'assigned_at', 'unassigned_at', 'status'];

    protected $casts = ['assigned_at' => 'datetime', 'unassigned_at' => 'datetime'];

    protected static function booted(): void
    {
        self::creating(fn (self $assignment) => $assignment->uuid ??= (string) Str::uuid());
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(LocalShipment::class, 'local_shipment_id');
    }

    public function driverProfile(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class, 'driver_profile_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }
}
