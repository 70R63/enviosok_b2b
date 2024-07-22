<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reportes\StoreRepesajeRequest;
use App\Http\Requests\Reportes\UpdateRepesajeRequest;
//use App\Models\Reportes\Repesaje;

use Log;

use App\Models\Servicio;
class RepesajeController extends Controller
{
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
            return view("reportes.repesajes.index"
                    ,compact("servicioCombo")
                );

        } catch (Exception $e) {
            Log::info(__CLASS__." ".__FUNCTION__." Exception");    
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreRepesajeRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreRepesajeRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Reportes\Repesaje  $repesaje
     * @return \Illuminate\Http\Response
     */
    public function show(Repesaje $repesaje)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Reportes\Repesaje  $repesaje
     * @return \Illuminate\Http\Response
     */
    public function edit(Repesaje $repesaje)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateRepesajeRequest  $request
     * @param  \App\Models\Reportes\Repesaje  $repesaje
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateRepesajeRequest $request, Repesaje $repesaje)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Reportes\Repesaje  $repesaje
     * @return \Illuminate\Http\Response
     */
    public function destroy(Repesaje $repesaje)
    {
        //
    }
}
