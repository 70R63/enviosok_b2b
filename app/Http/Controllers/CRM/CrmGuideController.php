<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\B2cAdeudo;
use App\Models\B2cCotizacion;
use App\Models\Guia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Services\Shipping\GuideRecoveryService;
use App\Services\Exports\GuideExportQuery;

class CrmGuideController extends Controller
{
    public function index(Request $request, GuideRecoveryService $recovery, GuideExportQuery $guideQuery)
    {
        $search = trim((string) $request->get('search', ''));
        $debtFilter = strtoupper(trim((string) (
            $request->get('adeudo') ?: 'TODOS'
        )));

        $filters = $guideQuery->filters($request);
        $query = $guideQuery->make($filters)
            ->where(function ($query) {
                $query->whereNotNull('guia_id')->orWhereNotNull('tracking_number');
            })
            ->whereNotNull('user_id')
            ->with([
                'adeudos' => function ($query) {
                    $query->latest('id');
                },
            ]);

        if ($debtFilter === 'PENDIENTES') {
            $query->whereHas('adeudos', function ($debtQuery) {
                $debtQuery->whereIn('estatus', [
                    B2cAdeudo::STATUS_PENDING,
                    B2cAdeudo::STATUS_PAYMENT_STARTED,
                ]);
            });
        } elseif ($debtFilter === 'SIN_ADEUDOS') {
            $query->doesntHave('adeudos');
        }

        $cotizaciones = $query
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $recoveryQuotes = B2cCotizacion::query()->with('user:id,name,email')
            ->where(function ($q) { $q->whereNotNull('guia_last_error_code')->orWhere(function($q){$q->where(function($q){$q->whereNotNull('guia_id')->orWhereNotNull('tracking_number');})->whereNull('documento');})->orWhere(function($q){$q->whereNotNull('quote_expires_at')->whereIn('payment_status',['approved','saldo_prepago']);}); })
            ->latest('id')->limit(100)->get()->filter(function($q)use($recovery){$case=$recovery->syncCase($q);$q->recovery_case=$case;return $case!==null;});

        return view('crm.guias.index', compact(
            'cotizaciones',
            'search',
            'debtFilter'
            ,'recoveryQuotes'
            ,'filters'
        ));
    }

    public function show(B2cCotizacion $cotizacion)
    {
        $this->ensureGuideOwner($cotizacion);

        $cotizacion->load([
            'user:id,name,email',
            'adeudos' => function ($query) {
                $query->with('creator:id,name,email')->latest('id');
            },
        ]);

        $guia = $this->findLegacyGuide($cotizacion);

        $suggestedQuotedCost = round((float) (
            $cotizacion->provider_base_price
            ?: ($guia->precio ?? 0)
        ), 2);

        $suggestedQuotedWeight = round((float) (
            $cotizacion->peso_facturable
            ?: $cotizacion->peso
            ?: ($guia->peso ?? 0)
        ), 2);

        $suggestedRealWeight = round((float) (
            $guia->peso_bascula
            ?? $guia->rastreo_peso
            ?? $suggestedQuotedWeight
        ), 2);

        return view('crm.guias.show', compact(
            'cotizacion',
            'guia',
            'suggestedQuotedCost',
            'suggestedQuotedWeight',
            'suggestedRealWeight'
        ));
    }

    public function storeDebt(
        Request $request,
        B2cCotizacion $cotizacion
    ) {
        $this->ensureGuideOwner($cotizacion);

        $data = $request->validate([
            'tipo' => [
                'required',
                Rule::in(['REPESAJE', 'AJUSTE_OPERATIVO']),
            ],
            'concepto' => ['required', 'string', 'max:255'],
            'peso_cotizado' => ['nullable', 'numeric', 'min:0'],
            'peso_real' => ['nullable', 'numeric', 'min:0'],
            'costo_cotizado' => ['required', 'numeric', 'min:0'],
            'costo_real' => ['required', 'numeric', 'min:0.01'],
            'referencia_xperta' => [
                'required',
                'string',
                'max:150',
                Rule::unique('b2c_adeudos', 'referencia_xperta')
                    ->where(function ($query) use ($cotizacion) {
                        return $query->where(
                            'cotizacion_id',
                            $cotizacion->id
                        );
                    }),
            ],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ]);

        $quotedCost = round((float) $data['costo_cotizado'], 2);
        $realCost = round((float) $data['costo_real'], 2);
        $amount = round($realCost - $quotedCost, 2);

        if ($amount <= 0) {
            return back()
                ->withInput()
                ->with(
                    'error',
                    'El costo real debe ser mayor al costo cotizado '
                    . 'para generar un adeudo.'
                );
        }

        $debt = DB::transaction(function () use (
            $cotizacion,
            $data,
            $quotedCost,
            $realCost,
            $amount
        ) {
            $locked = B2cCotizacion::query()
                ->whereKey($cotizacion->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureGuideOwner($locked);

            return B2cAdeudo::create([
                'user_id' => $locked->user_id,
                'cotizacion_id' => $locked->id,
                'guia_id' => $locked->guia_id,
                'tracking_number' => $locked->tracking_number,
                'tipo' => $data['tipo'],
                'concepto' => trim($data['concepto']),
                'peso_cotizado' => $data['peso_cotizado'] ?? null,
                'peso_real' => $data['peso_real'] ?? null,
                'costo_cotizado' => $quotedCost,
                'costo_real' => $realCost,
                'monto' => $amount,
                'referencia_xperta' => strtoupper(
                    trim($data['referencia_xperta'])
                ),
                'observaciones' => $data['observaciones'] ?? null,
                'estatus' => B2cAdeudo::STATUS_PENDING,
                'payment_external_reference' => null,
                'created_by' => auth()->id(),
            ]);
        });

        return redirect()
            ->route('crm.adeudos.show', $debt)
            ->with(
                'success',
                'Adeudo registrado y asignado al usuario propietario '
                . 'de la guía.'
            );
    }

    private function ensureGuideOwner(B2cCotizacion $cotizacion): void
    {
        if (
            !$cotizacion->user_id
            || (
                !$cotizacion->guia_id
                && !$cotizacion->tracking_number
            )
        ) {
            abort(404);
        }
    }

    private function findLegacyGuide(
        B2cCotizacion $cotizacion
    ): ?Guia {
        $query = Guia::withoutGlobalScopes();

        if ($cotizacion->guia_id) {
            return $query->find($cotizacion->guia_id);
        }

        if ($cotizacion->tracking_number) {
            return $query
                ->where(
                    'tracking_number',
                    $cotizacion->tracking_number
                )
                ->first();
        }

        return null;
    }
}
