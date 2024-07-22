<?php
namespace App\Http\Controllers\Saldos;

use App\Http\Controllers\Controller;

use App\Http\Requests\StoreGuiasExternasRequest;
use App\Http\Requests\UpdateGuiasExternasRequest;
use App\Models\Saldos\GuiasExternas;

use Log;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use \Exception;

use App\Negocio\Saldos\Externa as nExterna;

class GuiasExternasController extends Controller
{

    const INDEX_r = "externas.index";

    const DASH_v = "saldos.guiasexternas.dashboard";
    const CREAR_v = "saldos.crear";
    const EDITAR_v = "saldos.editar";
    const SHOW_v = "saldos.show";

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $tabla = array();
        $iniciarBusqueda =true;
        try {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__); 
           
            $nExterna = new nExterna();
            $nExterna->tabla();
            $tabla = $nExterna->getTabla();
                        

            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__); 
            return view(self::DASH_v 
                    ,compact("tabla")
                );


        } catch (ModelNotFoundException $e) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__); 
            $mensajeInterno=$e->getMessage();
            Log::debug(print_r($mensajeInterno,true));
            Log::info("ModelNotFoundException");       
            $mensaje = "ModelNotFoundException - Favor de buscar a tu administrador ";
        
        } catch (QueryException $e) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__); 
            $mensajeInterno=$e->getMessage();
            Log::debug(print_r($mensajeInterno,true));
            Log::info("QueryException");       
            $mensaje = "QueryException - Favor de buscar a tu administrador ";
        
        } catch (\Exception $e) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__); 
            $mensaje=$e->getMessage();
            Log::debug(print_r($mensaje,true));
            Log::info("Error general ");       
        }

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        return view(self::DASH_v 
                    ,compact("tabla", "iniciarBusqueda"))
                ->withErrors( $mensaje);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        abort(403, 'Unauthorized action.');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreGuiasExternasRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreGuiasExternasRequest $request)
    {
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        Log::debug($request->all());

        try {
            
            $nExterna = new nExterna();
            $nExterna->guia($request->fileGuiasExternas);

            
            $tmp = sprintf("'%s, El registro fue exitoso",$request->get('nombre'));
            
            $notices = $nExterna->getMensajes();
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            return \Redirect::route(self::INDEX_r) -> withSuccess ($notices);
        } catch (ModelNotFoundException $e) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__); 
            $mensajeInterno=$e->getMessage();
            Log::debug(print_r($mensajeInterno,true));
            Log::info("ModelNotFoundException");       
            $mensaje = "ModelNotFoundException - Favor de buscar a tu administrador ";
        

        } catch (QueryException $e) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__); 
            $mensajeInterno=$e->getMessage();
            Log::debug(print_r($mensajeInterno,true));
            Log::info("QueryException");       
            $mensaje = "QueryException - Favor de buscar a tu administrador ";
    
         } catch (Exception $e) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__); 
            $mensaje=$e->getMessage();
            Log::debug(print_r($mensaje,true));
            Log::info("Error general ");   

        }

        return \Redirect::back()
                ->withErrors(array($mensaje))
                ->withInput();
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Saldos\GuiasExternas  $guiasExternas
     * @return \Illuminate\Http\Response
     */
    public function show(GuiasExternas $guiasExternas)
    {
        abort(403, 'Unauthorized action.');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Saldos\GuiasExternas  $guiasExternas
     * @return \Illuminate\Http\Response
     */
    public function edit(GuiasExternas $guiasExternas)
    {
        abort(403, 'Unauthorized action.');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateGuiasExternasRequest  $request
     * @param  \App\Models\Saldos\GuiasExternas  $guiasExternas
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateGuiasExternasRequest $request, GuiasExternas $guiasExternas)
    {
        abort(403, 'Unauthorized action.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Saldos\GuiasExternas  $guiasExternas
     * @return \Illuminate\Http\Response
     */
    public function destroy(GuiasExternas $guiasExternas)
    {
        abort(403, 'Unauthorized action.');
    }
}
