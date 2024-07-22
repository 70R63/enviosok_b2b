<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReportesRequest;
use App\Http\Requests\UpdateReportesRequest;
use App\Models\Empresa;

use Log;
use App\Models\Servicio;

class ReportesController extends Controller
{


    const INDEX_r = "clientes.index";

    const DASH_v = "clientes.dashboard";
    const CREAR_v = "clientes.crear";
    const EDITAR_v = "clientes.editar";
    const SHOW_v = "clientes.show";


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);    
            $tabla = array();
            $servicioCombo = Servicio::pluck("nombre","id")->toArray();
            $servicioCombo[0] = "TODOS";
            //dd($servicioCombo);
            ksort($servicioCombo);

            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            return view("reportes.ventas.index"
                    ,compact("servicioCombo")
                );

        } catch (Exception $e) {
            Log::info(__CLASS__." ".__FUNCTION__." Exception");    
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreReportesRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreReportesRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\API\Reportes  $reportes
     * @return \Illuminate\Http\Response
     */
    public function show(Reportes $reportes)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateReportesRequest  $request
     * @param  \App\Models\API\Reportes  $reportes
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateReportesRequest $request, Reportes $reportes)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\API\Reportes  $reportes
     * @return \Illuminate\Http\Response
     */
    public function destroy(Reportes $reportes)
    {
        //
    }
}
