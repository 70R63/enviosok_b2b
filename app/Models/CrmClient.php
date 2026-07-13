<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\ApiClient;

class CrmClient extends Model
{
    protected $table = 'crm_clients';

    protected $fillable = [
        'client_type',
        'commercial_status',
        'name',
        'company_name',
        'contact_name',
        'email',
        'phone',
        'tax_id',
        'source',
        'notes',
        'active',
        'lead_status',
        'lead_priority',
        'last_contact_at',
        'next_follow_up_at',
        'internal_notes',
        'reviewed_at',
    ];

    protected $casts = [
        'active' => 'boolean',
        'last_contact_at' => 'datetime',
        'next_follow_up_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function apiClient()
    {
        return $this->hasOne(ApiClient::class, 'crm_client_id');
    }

    public function getClientTypeLabelAttribute(): string
    {
        return match ($this->client_type) {
            'b2c' => 'B2C',
            'b2b' => 'B2B',
            'api' => 'API',
            'mixto' => 'Mixto',
            default => 'Sin clasificar',
        };
    }

    public function getCommercialStatusLabelAttribute(): string
    {
        return match ($this->commercial_status) {
            'prospecto' => 'Prospecto',
            'activo' => 'Activo',
            'suspendido' => 'Suspendido',
            'perdido' => 'Perdido',
            default => 'Sin estado',
        };
    }
}