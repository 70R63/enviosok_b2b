<?php

namespace App\Domain\Network\Commerce\Models;

use App\Domain\Network\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;

final class TenantSaasFiscalProfile extends Model
{
    protected $fillable = ['tenant_id', 'legal_name', 'tax_id', 'fiscal_postal_code', 'fiscal_regime', 'cfdi_use', 'billing_email'];

    public function tenant() { return $this->belongsTo(Tenant::class); }

    public function snapshot(): array
    {
        return $this->only(['legal_name', 'tax_id', 'fiscal_postal_code', 'fiscal_regime', 'cfdi_use', 'billing_email']);
    }
}
