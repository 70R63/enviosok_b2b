<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class B2cFiscalProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'razon_social',
        'rfc',
        'codigo_postal_fiscal',
        'direccion_fiscal',
        'regimen_fiscal',
        'uso_cfdi',
        'email_facturacion',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class
        );
    }

    public function estaCompleto(): bool
    {
        return filled($this->razon_social)
            && filled($this->rfc)
            && filled(
                $this->codigo_postal_fiscal
            )
            && filled($this->regimen_fiscal)
            && filled($this->uso_cfdi)
            && filled(
                $this->email_facturacion
            );
    }

    public function usoCfdiEsCompatible(): bool
    {
        $usosCfdiPorRegimen = config(
            'b2c_fiscal.usos_cfdi_por_regimen',
            []
        );

        $usosPermitidos =
            $usosCfdiPorRegimen[
                (string) $this->regimen_fiscal
            ] ?? [];

        return in_array(
            (string) $this->uso_cfdi,
            $usosPermitidos,
            true
        );
    }
}
