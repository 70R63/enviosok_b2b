<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCoberturasRequest;
use App\Http\Requests\UpdateCoberturasRequest;
use App\Models\Coberturas;
use App\Models\CoberturasLista;
use App\Models\Ltd;

use Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;


class CoberturasController extends Controller
{

    const INDEX_r = "coberturas.index";

    const DASH_v = "coberturas.dashboard";
    const CREAR_v = "coberturas.crear";
    const EDITAR_v = "coberturas.editar";
    const SHOW_v = "coberturas.show";

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            Log::info(__CLASS__." ".__FUNCTION__);    
            $tabla = Coberturas::where('estatus',1)
                    ->get();

            $pluckLtd = Ltd::where('estatus',1)
                                ->pluck('nombre','id');

            return view(self::DASH_v 
                    ,compact("tabla", "pluckLtd")
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
     * @param  \App\Http\Requests\StoreCoberturasRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreCoberturasRequest $request)
    {
        try {
            Log::info(__CLASS__." ".__FUNCTION__);
            $startTime = Carbon::parse(now());
            
            $ltdId = $request->get('ltd_id');

            $ltds = Ltd::where('estatus',1)
                            ->pluck('nombre','id');
            
            $coberturas = new Coberturas();
            $coberturas->ltds_id = $ltdId;
            $coberturas->save();

            $coberturasId = $coberturas->id;
            
            Log::info(__CLASS__." ".__FUNCTION__."Ultimo id ".$coberturasId);

            $file = $request->file('coberturaCSV');
            $handle = fopen($file->getRealPath(), "r");
          
            fgetcsv($handle, 200, ",");
            $registros = 0;
            while ( ($data = fgetcsv($handle, 200, ",")) !==FALSE) {

                //Log::debug($data);

                $cl= new CoberturasLista();
                $cl->coberturas_id = $coberturasId;
                $cl->ltds_id = $ltdId;
                $cl->cp =$data[0];
                $cl->estado =$data[1];
                $cl->municipio =$data[2];
                $cl->reexpedicion =$data[3];
                $cl->garantia =$data[4]; 
                $cl->save();
                
                $registros = $registros + 1;
            };
            
            fclose($handle);

            $estado_cnt = CoberturasLista::where('coberturas_id',$coberturasId)
                            ->distinct()->count('estado');
            $municipio_cnt = CoberturasLista::where('coberturas_id',$coberturasId)
                            ->distinct()->count('municipio');
            $reexpedicion_cnt = CoberturasLista::where('coberturas_id',$coberturasId)
                            ->distinct()->count('reexpedicion');
            $garantia_cnt = CoberturasLista::where('coberturas_id',$coberturasId)
                            ->distinct()->count('garantia');
            $cp_cnt = CoberturasLista::where('coberturas_id',$coberturasId)
                            ->distinct()->count('cp');

            $coberturas = Coberturas::findOrFail($coberturasId);
            $coberturas->municipio_num= $municipio_cnt;
            $coberturas->estado_num= $estado_cnt;
            $coberturas->cp_num= $cp_cnt;
            $coberturas->reexpedicion_num= $reexpedicion_cnt;
            $coberturas->garantia_num= $garantia_cnt;
            $coberturas->save();

            $endTime = Carbon::parse(now());
            $totalDuration =  $startTime->diff($endTime)->format('%H:%I:%S');
            
            Log::info(__CLASS__." ".__FUNCTION__." Total de registros ".$registros);
            Log::info(__CLASS__." ".__FUNCTION__." Tiempo total ".$totalDuration);
            $tmp = sprintf("La tabla base de precio se agrego de forma correcta");
            $notices = array($tmp);
            return \Redirect::route(self::INDEX_r) -> withSuccess ($notices);



        } catch(Exeption $e) {
            Log::info(__CLASS__." ".__FUNCTION__." Exception");
        } 

 
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Coberturas  $coberturas
     * @return \Illuminate\Http\Response
     */
    public function show( $coberturasId)
    {
        try {
            Log::info(__CLASS__." ".__FUNCTION__);    
            $tabla = CoberturasLista::where('estatus',1)
                    ->where('ltds_id', $coberturasId)
                    ->get();

            return view(self::SHOW_v 
                    ,compact("tabla")
                );
        } catch (Exception $e) {
            Log::info(__CLASS__." ".__FUNCTION__." Exception");    
        }
        

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Coberturas  $coberturas
     * @return \Illuminate\Http\Response
     */
    public function edit(Coberturas $coberturas)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateCoberturasRequest  $request
     * @param  \App\Models\Coberturas  $coberturas
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateCoberturasRequest $request, Coberturas $coberturas)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Coberturas  $coberturas
     * @return \Illuminate\Http\Response
     */
    public function destroy(int $coberturasId)
    {
        Log::info(__CLASS__." ".__FUNCTION__);
        try {
            Log::info("Registro a Eliminar ". $coberturasId);
            $tmp = sprintf("Registro eliminado con exito");
            $notices = array($tmp);

            $coberturas = Coberturas::findOrFail($coberturasId);

            $coberturas->estatus = 0;
            $coberturas->save();

            CoberturasLista::where('coberturas_id', $coberturasId)->delete();
  
            return \Redirect::route(self::INDEX_r) -> withSuccess ($notices);

        } catch(\Illuminate\Database\QueryException $ex){ 
            Log::info(__CLASS__." ".__FUNCTION__." "."QueryException");
            Log::debug($ex->getMessage()); 
            return \Redirect::back()
                ->withErrors(array($ex->errorInfo[2]))
                ->withInput();

        } catch (Exception $e) {
            Log::info(__CLASS__." ".__FUNCTION__." Exception");
            Log::debug( $e->getMessage() );
        }
    }
}
