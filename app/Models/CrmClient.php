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
    ];

    protected $casts = [
        'active' => 'boolean',
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