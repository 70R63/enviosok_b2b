<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\B2cCotizacion;
use App\Models\B2cMovimientoSaldo;
use App\Models\B2cRecarga;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CrmPaymentController extends Controller
{
    public function index(Request $request)
    {
        $shipmentsQuery = B2cCotizacion::query()
            ->with('user:id,name,email')->whereNotNull('user_id')
            ->where(function (Builder $query) {
                $query->whereNotNull('payment_id')->orWhereNotNull('payment_status')
                    ->orWhereNotNull('payment_verified_at')->orWhereIn('estatus', [
                        'PAGO_INICIADO', 'PAGO_PENDIENTE', 'PAGO_RECHAZADO',
                        'PAGADA', 'GUIA_GENERADA', 'ERROR_GENERACION_GUIA',
                    ]);
            });
        $this->filterShipments($shipmentsQuery, $request);
        $shipments = $shipmentsQuery->latest('id')
            ->paginate(25, ['*'], 'pagos_page')->withQueryString();

        $rechargesQuery = B2cRecarga::query()->with('user:id,name,email');
        $this->filterRecharges($rechargesQuery, $request);
        $recharges = $rechargesQuery->latest('id')
            ->paginate(25, ['*'], 'recargas_page')->withQueryString();
        $this->attachMovements($recharges->getCollection());

        return view('crm.payments.index', compact('shipments', 'recharges'));
    }

    public function showShipment(B2cCotizacion $cotizacion)
    {
        $cotizacion->load(['user:id,name,email', 'invoiceRequest']);
        return view('crm.payments.shipment-show', compact('cotizacion'));
    }

    public function showRecharge(B2cRecarga $recarga)
    {
        $recarga->load('user:id,name,email');
        $this->attachMovements(collect([$recarga]));
        return view('crm.payments.recharge-show', compact('recarga'));
    }

    private function filterShipments(Builder $query, Request $request): void
    {
        if ($request->filled('pago_estado')) {
            $query->where('payment_status', strtolower(trim((string) $request->pago_estado)));
        }
        if ($request->metodo === 'saldo') {
            $query->where('payment_status', 'saldo_prepago');
        } elseif ($request->metodo === 'mercadopago') {
            $query->where(function (Builder $q) {
                $q->whereNull('payment_status')->orWhere('payment_status', '<>', 'saldo_prepago');
            });
        }
        $this->dates($query, $request, 'pago_desde', 'pago_hasta', 'updated_at');
        if ($request->filled('pago_cliente')) {
            $term = trim((string) $request->pago_cliente);
            $query->whereHas('user', fn (Builder $q) => $q
                ->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"));
        }
        if ($request->filled('pago_referencia')) {
            $term = trim((string) $request->pago_referencia);
            $query->where(fn (Builder $q) => $q->where('payment_id', 'like', "%{$term}%")
                ->orWhere('payment_external_reference', 'like', "%{$term}%")
                ->orWhere('referencia', 'like', "%{$term}%"));
        }
    }

    private function filterRecharges(Builder $query, Request $request): void
    {
        if ($request->filled('recarga_estado')) {
            $query->where('estatus', strtoupper(trim((string) $request->recarga_estado)));
        }
        if ($request->acreditada === 'si') {
            $this->whereCredited($query, true);
        } elseif ($request->acreditada === 'no') {
            $this->whereCredited($query, false);
        }
        $this->dates($query, $request, 'recarga_desde', 'recarga_hasta', 'created_at');
        if ($request->filled('recarga_usuario')) {
            $term = trim((string) $request->recarga_usuario);
            $query->whereHas('user', fn (Builder $q) => $q
                ->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"));
        }
        if ($request->filled('recarga_referencia')) {
            $term = trim((string) $request->recarga_referencia);
            $query->where(fn (Builder $q) => $q->where('mp_payment_id', 'like', "%{$term}%")
                ->orWhere('referencia', 'like', "%{$term}%"));
        }
    }

    private function whereCredited(Builder $query, bool $credited): void
    {
        $reference = DB::connection()->getDriverName() === 'sqlite'
            ? "'RECARGA-' || b2c_recargas.id"
            : "CONCAT('RECARGA-', b2c_recargas.id)";
        $method = $credited ? 'whereExists' : 'whereNotExists';
        $query->{$method}(function ($movement) use ($reference) {
            $movement->selectRaw('1')->from('b2c_movimientos_saldo')
                ->whereColumn('b2c_movimientos_saldo.user_id', 'b2c_recargas.user_id')
                ->where('b2c_movimientos_saldo.tipo', 'RECARGA')
                ->where('b2c_movimientos_saldo.estatus', 'APLICADO')
                ->whereColumn('b2c_movimientos_saldo.saldo_nuevo', '>', 'b2c_movimientos_saldo.saldo_anterior')
                ->whereRaw('b2c_movimientos_saldo.referencia = ' . $reference);
        });
    }

    private function dates(Builder $query, Request $request, string $from, string $to, string $column): void
    {
        if ($request->filled($from)) $query->whereDate($column, '>=', $request->input($from));
        if ($request->filled($to)) $query->whereDate($column, '<=', $request->input($to));
    }

    private function attachMovements($recharges): void
    {
        if ($recharges->isEmpty()) return;
        $references = $recharges->map(fn (B2cRecarga $r) => 'RECARGA-' . $r->id);
        $movements = B2cMovimientoSaldo::query()->where('tipo', 'RECARGA')
            ->whereIn('referencia', $references)->latest('id')->get()->keyBy('referencia');
        $recharges->each(fn (B2cRecarga $r) => $r->setRelation(
            'movimiento', $movements->get('RECARGA-' . $r->id)
        ));
    }
}
