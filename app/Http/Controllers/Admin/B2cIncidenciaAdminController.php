<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\B2cIncidencia;
use Illuminate\Http\Request;
use Carbon\Carbon;

class B2cIncidenciaAdminController extends Controller
{
    public function index(Request $request)
{
    $estatus = $request->get('estatus');
    $q = trim($request->get('q'));

    $incidencias = B2cIncidencia::with(['user', 'cotizacion'])
        ->when($estatus, function ($query) use ($estatus) {
            $query->where('estatus', $estatus);
        })
        ->latest()
        ->get();

    $cotizaciones = collect();

    if ($q) {
        $cotizaciones = \App\Models\B2cCotizacion::with('user')
            ->where(function ($query) use ($q) {

                if (is_numeric($q)) {
                    $query->where('id', $q);
                }

                $query->orWhere('tracking_number', 'like', "%{$q}%")
                    ->orWhere('payment_id', $q)
                    ->orWhere('payment_external_reference', 'like', "%{$q}%")
                    ->orWhere('payment_status', 'like', "%{$q}%")
                    ->orWhere('estatus', 'like', "%{$q}%")
                    ->orWhere('guia_estatus', 'like', "%{$q}%")
                    ->orWhereHas('user', function ($u) use ($q) {
                        $u->where('name', 'like', "%{$q}%")
                          ->orWhere('email', 'like', "%{$q}%");
                    });
            })
            ->latest()
            ->limit(10)
            ->get();
    }

    return view('admin.incidencias.index', compact(
        'incidencias',
        'estatus',
        'q',
        'cotizaciones'
    ));
}

    public function show(B2cIncidencia $incidencia)
    {
        $incidencia->load(['user', 'cotizacion']);

        return view('admin.incidencias.show', compact('incidencia'));
    }

    public function responder(Request $request, B2cIncidencia $incidencia)
    {
        $data = $request->validate([
            'respuesta_admin' => ['required', 'string', 'max:2000'],
            'estatus' => ['required', 'in:ABIERTA,EN_REVISION,ATENDIDA,CERRADA'],
        ]);

        $incidencia->update([
            'respuesta_admin' => $data['respuesta_admin'],
            'estatus' => $data['estatus'],
            'respondida_at' => Carbon::now(),
            'respondida_por' => auth()->id(),
        ]);

        return redirect()
            ->route('admin.incidencias.show', $incidencia->id)
            ->with('success', 'Incidencia actualizada correctamente.');
    }
}