<?php

namespace App\Services\CRM;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CrmUserDependencyService
{
    private const DEPENDENCIES = [
        ['guias', 'user_id', 'guías'],
        ['guias', 'usuario_id', 'guías'],
        ['guias_externas', 'user_id', 'guías externas'],
        ['masivas', 'user_id', 'operaciones masivas'],
        ['b2c_cotizaciones', 'user_id', 'cotizaciones B2C'],
        ['pagos', 'usuario_id', 'pagos'],
        ['reporte_pagos', 'user_id', 'reportes de pago'],
        ['ajustes', 'user_id', 'ajustes de saldo'],
        ['b2c_saldos', 'user_id', 'saldos B2C'],
        ['b2c_movimientos_saldo', 'user_id', 'movimientos de saldo'],
        ['b2c_recargas', 'user_id', 'recargas B2C'],
        ['b2c_saldo_reversals', 'user_id', 'reversiones de saldo'],
        ['b2c_saldo_reversals', 'admin_user_id', 'auditoría de saldo'],
        ['b2c_invoice_requests', 'user_id', 'facturación B2C'],
        ['b2c_invoice_requests', 'managed_by_user_id', 'gestión de facturación'],
        ['api_billing_requests', 'managed_by_user_id', 'gestión de facturación API'],
        ['b2c_incidencias', 'user_id', 'incidencias B2C'],
        ['b2c_identity_verifications', 'user_id', 'verificaciones de identidad'],
        ['b2c_identity_status_history', 'user_id', 'historial de identidad'],
        ['b2c_identity_status_history', 'admin_user_id', 'auditoría de identidad'],
        ['b2c_direcciones', 'user_id', 'direcciones B2C'],
        ['b2c_fiscal_profiles', 'user_id', 'perfiles fiscales'],
        ['b2c_adeudos', 'user_id', 'adeudos B2C'],
        ['b2c_adeudos', 'created_by', 'adeudos creados'],
        ['b2c_checkout_debt_allocations', 'user_id', 'asignaciones de adeudo'],
        ['api_clients', 'user_id', 'clientes API'],
        ['zigo_client_pricing_rules', 'user_id', 'reglas comerciales'],
        ['zigo_shipping_agreements', 'created_by', 'convenios creados'],
        ['zigo_shipping_agreements', 'updated_by', 'convenios actualizados'],
        ['zigo_provider_rate_cards', 'created_by', 'tarifarios creados'],
        ['zigo_provider_rate_cards', 'updated_by', 'tarifarios actualizados'],
        ['zigo_provider_rate_references', 'created_by', 'referencias creadas'],
        ['zigo_provider_rate_references', 'updated_by', 'referencias actualizadas'],
    ];

    public function detect(User $user): array
    {
        $found = [];

        foreach (self::DEPENDENCIES as [$table, $column, $label]) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
                continue;
            }

            $count = DB::table($table)->where($column, $user->id)->count();

            if ($count > 0) {
                $found[] = [
                    'table' => $table,
                    'column' => $column,
                    'label' => $label,
                    'count' => $count,
                ];
            }
        }

        return $found;
    }
}
