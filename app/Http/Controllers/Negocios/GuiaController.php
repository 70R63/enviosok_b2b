<?php

namespace App\Http\Controllers\Negocios;

use App\Http\Controllers\Controller;
use App\Http\Requests\Negocios\IndexGuiaRequest;
use App\Models\Guia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GuiaController extends Controller
{
    public function index(IndexGuiaRequest $request)
    {
        $filters = $request->validated();
        $empresaId = (int) $request->user()->empresa_id;

        $guias = $this->ownedGuidesQuery($request)
            ->leftJoin('ltds', 'ltds.id', '=', 'guias.ltd_id')
            ->leftJoin('servicios', 'servicios.id', '=', 'guias.servicio_id')
            ->leftJoin('rastreo_estatus', 'rastreo_estatus.id', '=', 'guias.rastreo_estatus')
            ->leftJoin('clientes', function ($join) use ($empresaId): void {
                $join->on('clientes.id', '=', 'guias.cia_d')
                    ->where('clientes.empresa_id', '=', $empresaId);
            })
            ->when(isset($filters['tracking_number']), function (Builder $query) use ($filters): void {
                $tracking = addcslashes($filters['tracking_number'], '\\%_');
                $query->where('guias.tracking_number', 'like', $tracking.'%');
            })
            ->when(isset($filters['numero_solicitud']), function (Builder $query) use ($filters): void {
                $query->where('guias.numero_solicitud', $filters['numero_solicitud']);
            })
            ->when(isset($filters['rastreo_estatus']), function (Builder $query) use ($filters): void {
                $query->where('guias.rastreo_estatus', $filters['rastreo_estatus']);
            })
            ->when(isset($filters['fecha_inicio']), function (Builder $query) use ($filters): void {
                $query->whereDate('guias.created_at', '>=', $filters['fecha_inicio']);
            })
            ->when(isset($filters['fecha_fin']), function (Builder $query) use ($filters): void {
                $query->whereDate('guias.created_at', '<=', $filters['fecha_fin']);
            })
            ->select([
                'guias.id',
                'guias.tracking_number',
                'guias.numero_solicitud',
                'guias.created_at',
                'guias.piezas',
                'guias.precio',
                'guias.documento',
                'ltds.nombre as paqueteria_nombre',
                'servicios.nombre as servicio_nombre',
                'clientes.nombre as destinatario_nombre',
                'clientes.contacto as destinatario_contacto',
                'rastreo_estatus.nombre as rastreo_estatus_nombre',
            ])
            ->orderByDesc('guias.id')
            ->paginate(20)
            ->withQueryString();

        $estados = DB::table('rastreo_estatus')
            ->where('estatus', 1)
            ->orderBy('nombre')
            ->pluck('nombre', 'id');

        return view('negocios.guias.index', compact('guias', 'estados', 'filters'));
    }

    public function show(Request $request, int $guia)
    {
        $ownedGuide = $this->findOwnedGuideOrFail($request, $guia, [
            'guias.id',
            'guias.empresa_id',
            'guias.tracking_number',
            'guias.numero_solicitud',
            'guias.created_at',
            'guias.rastreo_estatus',
            'guias.ultima_fecha',
            'guias.pickup_fecha',
            'guias.quien_recibio',
            'guias.ltd_id',
            'guias.servicio_id',
            'guias.cia',
            'guias.cia_d',
            'guias.piezas',
            'guias.peso',
            'guias.dimensiones',
            'guias.contenido',
            'guias.valor_envio',
            'guias.precio',
            'guias.documento',
        ]);

        $empresaId = (int) $request->user()->empresa_id;

        $relations = DB::table('guias')
            ->leftJoin('ltds', 'ltds.id', '=', 'guias.ltd_id')
            ->leftJoin('servicios', 'servicios.id', '=', 'guias.servicio_id')
            ->leftJoin('rastreo_estatus', 'rastreo_estatus.id', '=', 'guias.rastreo_estatus')
            ->leftJoin('sucursals', function ($join) use ($empresaId): void {
                $join->on('sucursals.id', '=', 'guias.cia')
                    ->where('sucursals.empresa_id', '=', $empresaId);
            })
            ->leftJoin('clientes', function ($join) use ($empresaId): void {
                $join->on('clientes.id', '=', 'guias.cia_d')
                    ->where('clientes.empresa_id', '=', $empresaId);
            })
            ->where('guias.id', $ownedGuide->id)
            ->where('guias.empresa_id', $empresaId)
            ->where('guias.estatus', 1)
            ->select([
                'ltds.nombre as paqueteria_nombre',
                'servicios.nombre as servicio_nombre',
                'rastreo_estatus.nombre as rastreo_estatus_nombre',
                'sucursals.nombre as origen_nombre',
                'sucursals.contacto as origen_contacto',
                'sucursals.direccion as origen_direccion',
                'sucursals.direccion2 as origen_direccion2',
                'sucursals.cp as origen_cp',
                'sucursals.colonia as origen_colonia',
                'sucursals.ciudad as origen_ciudad',
                'sucursals.entidad_federativa as origen_estado',
                'clientes.nombre as destino_nombre',
                'clientes.contacto as destino_contacto',
                'clientes.direccion as destino_direccion',
                'clientes.direccion2 as destino_direccion2',
                'clientes.cp as destino_cp',
                'clientes.colonia as destino_colonia',
                'clientes.ciudad as destino_ciudad',
                'clientes.entidad_federativa as destino_estado',
            ])
            ->first();

        return view('negocios.guias.show', [
            'guia' => $ownedGuide,
            'relations' => $relations,
        ]);
    }

    public function etiqueta(Request $request, int $guia): StreamedResponse
    {
        $ownedGuide = $this->findOwnedGuideOrFail($request, $guia, [
            'guias.id',
            'guias.empresa_id',
            'guias.tracking_number',
            'guias.documento',
        ]);

        $documento = trim((string) $ownedGuide->documento);
        abort_if($documento === '', 404);
        abort_if(str_contains($documento, "\0"), 404);
        abort_if(str_contains($documento, '\\'), 404);
        abort_if(str_contains($documento, '..'), 404);
        abort_if(parse_url($documento, PHP_URL_SCHEME) !== null, 404);
        abort_if(str_starts_with($documento, '/'), 404);
        abort_if(preg_match('/^[A-Za-z]:/', $documento) === 1, 404);
        abort_unless($documento === basename($documento), 404);
        abort_unless(strtolower(pathinfo($documento, PATHINFO_EXTENSION)) === 'pdf', 404);

        $disk = Storage::disk('public');
        abort_unless($disk->exists($documento), 404);

        $tracking = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $ownedGuide->tracking_number);
        $downloadName = ($tracking !== '' ? $tracking : 'guia-'.$ownedGuide->id).'.pdf';

        return $disk->download($documento, $downloadName, [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function ownedGuidesQuery(Request $request): Builder
    {
        return Guia::withoutGlobalScope('guia_empresa')
            ->where('guias.empresa_id', (int) $request->user()->empresa_id)
            ->where('guias.estatus', 1);
    }

    private function findOwnedGuideOrFail(Request $request, int $id, array $columns): Guia
    {
        return $this->ownedGuidesQuery($request)
            ->whereKey($id)
            ->firstOrFail($columns);
    }
}
