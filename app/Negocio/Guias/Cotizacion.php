<?php
namespace App\Negocio\Guias;


use Log;
use DB;

//modelos
use App\Models\Tarifa;
use App\Models\API\Tarifa as TarifaApi;
use App\Models\EmpresaEmpresas;
use App\Models\EmpresaLtd;
use App\Models\API\EmpresaLtd as EmpresaLtdApi;
use App\Models\LtdCobertura;
use App\Models\PostalGrupo;
use App\Models\PostalZona;
use App\Models\DhlTarifas;
use App\Models\Empresa;
use App\Models\API\Empresa as EmpresaApi;
use App\Models\Sucursal;
use App\Models\Cliente;

//Negocio
use App\Negocio\Fedex_tarifas;
use App\Negocio\Saldos\Saldos;

class Cotizacion {

    private $mensaje = array();
    private $tabla = array();
    private $empresaId = 0;
    private $saldo = 0;
    private $tipoPagoId = 0;

     /**
     * Metodo base, Genera la logica para las cotizacion
     * 
     * @param array $parametros
     * @return void
    */

    public function base ($request,$ltd_id = 0, $canal ="WEB"){
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        
        if ($canal==="WEB") {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            if ( is_null($request['sucursal']) ) {
                $empresa_id = $request['clienteIdCombo'];
            } else {
                $empresa_id= Sucursal::where('id',$request['sucursal'])
                        ->value('empresa_id');
            }    
            $empresasLtdQuery = EmpresaLtd::where('empresa_id',$empresa_id);
        } else {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            $empresa_id = $request['empresa_id'];
            $empresasLtdQuery = EmpresaLtdApi::where('empresa_id',$empresa_id);
        }


        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." Empresa id =$empresa_id");
            
        
        if ($ltd_id > 0){
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." Consulta ltd_id=$ltd_id");
            $empresasLtdQuery->where('ltd_id',$ltd_id);
        }

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $empresasLtd = $empresasLtdQuery
                ->pluck('tarifa_clasificacion', 'ltd_id')
                ->toArray();

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." validando empresaLTD");
        Log::debug($empresasLtd);
        

        $tabla = array();
        foreach ($empresasLtd as $ltdId => $clasificacion) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." LTD $ltdId => clasificacion $clasificacion ------------------------------------");
                        
            $tablaTmp = array();

            $servicioIds = Tarifa::select('servicio_id')
                            ->where("ltds_id", $ltdId)
                            ->where("empresa_id", $empresa_id)
                            ->distinct()->get()->pluck('servicio_id')->toArray();
        
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." $canal");
            
            if ($canal === "API") {
                $query = TarifaApi::base($empresa_id, $request['cp_d'], $ltdId);
            } else {
                $query = Tarifa::base($empresa_id, $request['cp_d'], $ltdId);
            }

            switch ($clasificacion) {
                case "1": //FLAT
                    Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." Clasificacion 1 = FLAT");
                    switch ($ltdId) {
                        case "1":
                            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." ltd 1 = FEDEX");

                            $servicioIds = Tarifa::select('servicio_id')
                            ->where("ltds_id", $ltdId)
                            ->where("empresa_id", $empresa_id)
                            ->distinct()->get()->pluck('servicio_id')->toArray();

                            $tablaTmp = array();
                            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." ServiciosID");
                            Log::debug(print_r($servicioIds,true));
                            foreach ($servicioIds as $key => $value) {
                                Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." servicio_id =$value");

                                $query = Tarifa::base($empresa_id, $request['cp_d'], $ltdId);
                                $query = $query->where('servicio_id', $value);
                                
                                $zona = Tarifa::fedexZona($request['cp'],$request['cp_d']);


                                //Log::debug(print_r($query->toSql(),true));
                                if ($zona >=1 && $zona <= 4){
                                    Log::info("zONA 1 A 4");
                                    $costoZona = $query->min("costo");
                                    
                                } else {
                                    Log::info("zONA 5 A 8");
                                    $costoZona = $query->max("costo");
                                    
                                }
                                Log::debug(print_r("-----------------------------",true));
                                Log::debug(print_r($costoZona,true));

                                $tablaTmp = $query->where("costo","like","%".$costoZona."%")
                                    ->get()->toArray()
                                    ;
                                Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
                                Log::debug(print_r($tablaTmp,true));

                                foreach ($tablaTmp as $key => $value) {
                                    $value['zona']=$zona;
                                    $tabla[] = array_merge($tabla, $value);
                                }
                                
                                       
                            }
                            //FIN foreach ($servicioIds as $key => $value) {
                            break;
                        case "2":
                            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." ltd 2 = ESTAFETA");
                            if ($canal === "API") {
                                $query->where("servicio_id",$request['servicio_id']);
                            }
                            
                            $tablaTmp = $query->get()->toArray();
                            
                            foreach ($tablaTmp as $key => $value) {
                                $tablaTmp[$key]['zona'] = "NA";
                            }
                            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
                            $tabla = array_merge($tabla, $tablaTmp);
                        break;
                        default:
                            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." ltd default");
                            $tablaTmp = $query->get()->toArray();
                            
                            foreach ($tablaTmp as $key => $value) {
                                $tablaTmp[$key]['zona'] = "NA";
                            }

                            $tabla = array_merge($tabla, $tablaTmp);

                    }
                    //Fin switch ($ltdId) 
                break;
                case "2"://RANGO
                    Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." Clasificacion 2 = RANGO");

                    $servicioIds = Tarifa::select('servicio_id')
                        ->where("ltds_id", $ltdId)
                        ->where("empresa_id", $empresa_id)
                        ->distinct()->get()->pluck('servicio_id')->toArray();

                    Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." Serviciosd cliente");
                    Log::debug(print_r($servicioIds,true));

                    switch ($ltdId) {
                        case "1":
                            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." ltd 1 = FEDEX");
                            foreach ($servicioIds as $key => $value) {
                                Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." servicio_id =$value");
        
                                $query = Tarifa::base($empresa_id, $request['cp_d'], $ltdId);
                                $tablaTmp = $query->where( 'kg_ini', "<=", $request['pesoFacturado'] )
                                ->where('kg_fin', ">=", $request['pesoFacturado'] )
                                ->where('servicio_id', $value)
                                ->get()->toArray()
                                ;

                                foreach ($tablaTmp as $key => $value) {
                                    $tablaTmp[$key]['zona'] = "NA";
                                }

                                $tabla = array_merge($tabla, $tablaTmp);
                                           
                            }
                            //FIN foreach ($servicioIds as $key => $value) {
                        break;
                        case "2":
                            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." ltd 2 = ESTAFETA");
                           
                            $tablaTmp = array();

                            $query = Tarifa::base($empresa_id, $request['cp_d'], $ltdId);
                            $tablaTmp = $query->where( 'kg_ini', "<=", $request['pesoFacturado'] )
                            ->where('kg_fin', ">=", $request['pesoFacturado'] )
                            ->get()->toArray()
                            ;
                
                            Log::debug(__CLASS__." ".__FUNCTION__." ".__LINE__." Validando Query Rango");
                            Log::debug(print_r($tablaTmp,true));

                                    
                            if (empty($tablaTmp)) {
                                Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." Buscando el ultimo rango");

                                foreach ($servicioIds as $key => $value) {
                                    $tarifaIdsGeneral = Tarifa::select("id", "servicio_id", "kg_fin" )
                                        ->where("empresa_id", $empresa_id)
                                        ->where("ltds_id", $ltdId)
                                        ->where("servicio_id", $value);

                                    $maxKgFin = $tarifaIdsGeneral->max("kg_fin");
                                    Log::debug(__CLASS__." ".__FUNCTION__." ".__LINE__." maxFin =$maxKgFin");
                                    $tarifaIds = $tarifaIdsGeneral
                                        ->where("kg_fin", $maxKgFin)
                                        ->get()->toArray();
                        
                                    Log::debug(print_r($tarifaIds,true));
                                    $query = Tarifa::rangoMaximo($empresa_id, $request['cp_d'], $ltdId, $tarifaIds[0]['id']);
                                    $tablaTmp = $query->get()->toArray();
                                }
                                //fin foreach ($servicioIds as $key => $value) {
                    
                            } else {
                                Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." Tabla con resultado no se busca ultimo rango ");
                            }

                            foreach ($tablaTmp as $key => $value) {
                                $tablaTmp[$key]['zona'] = "NA";
                            }
                            $tabla = array_merge($tabla, $tablaTmp);            
                        break;
                        case "3":
                            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." ltd ".Config('ltd.redpack.id')."=".Config('ltd.redpack.nombre') );
                            foreach ($servicioIds as $key => $value) {
                                Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." servicio_id =$value");
                                $tablaTmp = array();

                                $query = Tarifa::base($empresa_id, $request['cp_d'], $ltdId);
                                $tablaTmp = $query->where( 'kg_ini', "<=", $request['pesoFacturado'] )
                                ->where('kg_fin', ">=", $request['pesoFacturado'] )
                                ->where('servicio_id', $value)
                                ->get()->toArray()
                                ;
                    
                                Log::debug(__CLASS__." ".__FUNCTION__." ".__LINE__." Validando Query Rango");
                                Log::debug(print_r($tablaTmp,true));

                                        
                                if (empty($tablaTmp)) {
                                    Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." Buscando el ultimo rango");
                                    $tarifaIdsGeneral = Tarifa::select("id", "servicio_id", "kg_fin" )
                                        ->where("empresa_id", $empresa_id)
                                        ->where("ltds_id", $ltdId)
                                        ->where("servicio_id", $value);

                                    $maxKgFin = $tarifaIdsGeneral->max("kg_fin");
                                    Log::debug(__CLASS__." ".__FUNCTION__." ".__LINE__." maxFin =$maxKgFin");
                                    $tarifaIds = $tarifaIdsGeneral
                                        ->where("kg_fin", $maxKgFin)
                                        ->get()->toArray();
                        
                                    Log::debug(print_r($tarifaIds,true));
                                    $query = Tarifa::rangoMaximo($empresa_id, $request['cp_d'], $ltdId, $tarifaIds[0]['id']);
                                    $tablaTmp = $query->get()->toArray();
                        
                                }
                                foreach ($tablaTmp as $key => $value) {
                                    $tablaTmp[$key]['zona'] = "NA";
                                }

                                $tabla = array_merge($tabla, $tablaTmp);

                            }
                            //FIN foreach ($servicioIds as $key => $value)
                        break;
                        default:
                            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." DEFAULT");
                    }
                    //FIN switch ($ltdId) {
                break;
                case 3: //RANGO ZONA
                    Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." Clasificacion 3 = ".Config('tarifa.clasificacion.3') );
                     $servicioIds = Tarifa::select('servicio_id')
                        ->where("ltds_id", $ltdId)
                        ->where("empresa_id", $empresa_id)
                        ->distinct()->get()->pluck('servicio_id')->toArray();

                    Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
                    Log::debug( print_r($servicioIds,true) );
                    foreach ($servicioIds as $key => $value) {

                        $query = Tarifa::base($empresa_id, $request['cp_d'], $ltdId);
                        $tablaTmp = $query->where( 'kg_ini', "<=", $request['pesoFacturado'] )
                        ->where('kg_fin', ">=", $request['pesoFacturado'] )
                        ->where('servicio_id', $value);

                        Log::debug(__CLASS__." ".__FUNCTION__." ".__LINE__." Validando Query Rango");
                        Log::debug(print_r($tablaTmp->get()->toArray(),true));
                        
                        $zona = Tarifa::fedexZona($request['cp'],$request['cp_d']);

                        if ($zona >=1 && $zona <= 4){
                            Log::info("zONA 1 A 4");
                            $costoZona = $query->min("costo");
                            
                        } else {
                            Log::info("zONA 5 A 8");
                            $costoZona = $query->max("costo");
                            
                        }
                        Log::debug(__CLASS__." ".__FUNCTION__." ".__LINE__."  costoZona=$costoZona");
                        $tablaTmp = $query->where("costo","like","%".$costoZona."%")->get()->toArray();
                        foreach ($tablaTmp as $key => $value) {
                            $tablaTmp[$key]['zona'] = "NA";
                        }
                        $tabla = array_merge($tabla, $tablaTmp);
                    };
                break;
                case 4: //RANGO FLAT
                    Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." Clasificacion 4 = ".Config('tarifa.clasificacion.4') );

                    $estadoCoberturaOrigen = LtdCobertura::select('estado', 'extendida')
                            ->where('ltd_id',Config('ltd.dhl.id'))
                            ->where('cp',$request['cp'])
                            ->get()->toArray()
                            ;


                    $estadoCoberturaDestino = LtdCobertura::select('estado', 'extendida', 'ocurre' )
                            ->where('ltd_id',Config('ltd.dhl.id'))
                            ->where('cp',$request['cp_d'])
                            ->get()->toArray()
                            ;

                    if ( !(count($estadoCoberturaOrigen) * count($estadoCoberturaDestino) )  ) {
                        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
                        Log::debug("No se cuenta con cobertura");
                        break;
                    }
                    

                    Log::debug(print_r($estadoCoberturaDestino,true));
                    $postalGrupoOrigen = PostalGrupo::select('grupo')
                            ->where('ltd_id',Config('ltd.dhl.id'))
                            ->where('entidad_federativa',$estadoCoberturaOrigen[0])
                            ->get()->pluck('grupo')->toArray()
                            ;

                    $postalGrupoDestino = PostalGrupo::select('grupo')
                            ->where('ltd_id',Config('ltd.dhl.id'))
                            ->where('entidad_federativa',$estadoCoberturaDestino[0])
                            ->get()->pluck('grupo')->toArray()
                            ;
                    Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
                    Log::debug("Grupos Postales ".$postalGrupoOrigen[0]." ".$postalGrupoDestino[0]);
                    $zona = PostalZona::select('zona')
                            ->where('ltd_id',Config('ltd.dhl.id'))
                            ->where('grupo_origen',$postalGrupoOrigen[0])
                            ->where('grupo_destino', $postalGrupoDestino[0])
                            ->get()->pluck('zona')->toArray()
                            ;

                    Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
                    Log::debug("Zona ".$zona[0]);
                    Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
                    
                    $tarifas = DhlTarifas::select('precio', 'dhl_tarifas.id', 'dhl_tarifas.servicio_id','servicios.nombre as servicios_nombre','servicios.tiempo_entrega')
                            ->join('servicios','servicios.id', '=', 'dhl_tarifas.servicio_id')
                            ->where('zona',$zona[0] )
                            ;

                    if ($request['pesoFacturado'] >70) {
                        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." pesoFacturado >70");
                        $maxPrecio = $tarifas->max('precio');
                        Log::debug(print_r($maxPrecio,true));
                        $tarifas = $tarifas->where('precio',$maxPrecio)
                                        ->get()->toArray()
                                        ;
                        
                    } else {
                        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." pesoFacturado <70");
                        $tarifas = $tarifas->where('kg', $request['pesoFacturado'])
                            ->get()->toArray()
                            ;
                    }
                    

                    Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);

                    foreach ($tarifas as $key => $tarifa) {
                        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);

                        $empresa = Empresa::where('id', $empresa_id)->get()->toArray()[0];
                        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
                        Log::debug(print_r($empresa,true));

                        $descuentoPorcentaje = $empresa['descuento']/100;
                        $costoDescuento = round($tarifa['precio'] *$descuentoPorcentaje,2);
                        
                        $subCosto = $tarifa['precio']-$costoDescuento;
                        Log::debug(print_r($subCosto,true));

                        $fscIncremento = $empresa['fsc']/100;
                        Log::debug(print_r($fscIncremento,true)); 
                        
                        $costoFsc = round( $subCosto*$fscIncremento ,2);
                        Log::debug(print_r($costoFsc,true)); 

                        $costo = round( $subCosto*(1+$fscIncremento) ,2);
                        Log::debug(print_r($costo,true));

                        if ($request['piezas']>1){
                            $costo = $costo+ $empresa['precio_mulitpieza'];
                        }

                        if ($estadoCoberturaDestino[0]['extendida'] === "SI" ) {
                            //$costo = round( $costo + $empresa['area_extendida'] ,2);
                        }

                        $servicioNombre = ($tarifa['servicio_id'] ===2) ? 'Dia Sig' : 'Terrestre' ;
                        
                        $tablaTmp = $tarifa;
                        $tablaTmp['costo'] =$costo;
                        $tablaTmp['precio'] =$costo;
                        $tablaTmp['ltds_id'] =Config('ltd.dhl.id');
                        $tablaTmp['nombre'] =Config('ltd.dhl.nombre');
                        $tablaTmp['kg_ini'] =$request['pesoFacturado'];
                        $tablaTmp['kg_fin'] =$request['pesoFacturado'];
                        $tablaTmp['kg_extra'] = 0;
                        $tablaTmp['ocurre'] = $estadoCoberturaDestino[0]['ocurre'];
                        $tablaTmp['extendida_cobertura'] = $estadoCoberturaDestino[0]['extendida'] ;
                        $tablaTmp['extendida'] = $empresa['area_extendida'];
                        $tablaTmp['seguro'] = $empresa['seguro'];
                        $tablaTmp['zona'] = $zona[0];
                        Log::debug(print_r($tablaTmp,true));  
                        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." CALCULO KG ADICIOANL DHL");
                        if ($request['pesoFacturado'] >70) { 
                            

                            $kgAdicional = $request['pesoFacturado'] -70;
                            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
                            $kgCosto = Config('ltd.dhl.kgmas70.zona')[$zona[0]];
                            $kgAdicional = ($kgAdicional* $kgCosto  );  
                            Log::info($kgAdicional);
                            $descuentoKgAdicional = $kgAdicional *$descuentoPorcentaje;
                            $precioAdicional=round($kgAdicional-$descuentoKgAdicional,2);
                            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
                            Log::info($precioAdicional);

                            $incrementoKgAdicional = round($precioAdicional *(1+$fscIncremento),2);
                            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
                            Log::info($incrementoKgAdicional);

                            $tablaTmp['costo'] = round( $tablaTmp['costo']+ Config('ltd.dhl.kgmas70.base')+$incrementoKgAdicional ,2);
                            $tablaTmp['kg_extra'] = $incrementoKgAdicional;

                            Log::debug(print_r($tablaTmp,true));
                            $tabla[] = $tablaTmp;
                        } else {
                            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);

                            $tabla[] = $tablaTmp;
                            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
                            if ($tarifa['servicio_id']===2) {
                                if ($empresa['premium10'] > 0){
                                    $tablaTmp['costo'] = round($tablaTmp['costo']+$empresa['premium10'],2);
                                    $tablaTmp['servicios_nombre'] = "10:30";
                                    $tablaTmp['servicio_id'] = "5";

                                    $tabla[] = $tablaTmp;
                                }

                                if ($empresa['premium12'] > 0){
                                    $tablaTmp['costo'] = round($tablaTmp['costo']+$empresa['premium12'],2);
                                    $tablaTmp['servicios_nombre'] = "12:00";
                                    $tablaTmp['servicio_id'] = "6";
                                    $tabla[] = $tablaTmp;
                                }
                            }
                            //fin $tarifa['servicio_id']===2
                        }
                        //fin if else $request['pesoFacturado'] >70
                    }
                    //Fin foreach

                    Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__); 
                break;
                case 5: //ZONA
                    Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." Clasificacion 5 = ".Config('tarifa.clasificacion.5') );

                        $fedexTarifas = new Fedex_tarifas();
                        $fedexTarifas->zona($request, $servicioIds,$empresa_id,$ltdId);
                        $tabla = array_merge($tabla, $fedexTarifas->getTarifa());
                    Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
                    break;
                default:
                    Log::debug("No se seleccion niguna clasificacion");
            }//fin Switch
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            $this->tabla = $tabla;
        }//fin foreach ($empresasLtd as $ltdId => $clasificacion) {

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $saldo = new Saldos();
        $this->saldo = $saldo->porEmpresa($empresa_id);

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." $canal");
        if ($canal === "API") {
            $empresa = EmpresaApi::select("tipo_pago_id")->where("id", $empresa_id)->firstOrFail();
        } else {
            $empresa = Empresa::select("tipo_pago_id")->where("id", $empresa_id)->firstOrFail();
        }
        
        
        $this->tipoPagoId = $empresa->tipo_pago_id;

    }// fin public function base ($guiaId){


    /**
     * Se obtienen los datos para armar el insert de fedex
     * 
     * @author Javier Hernandez
     * @copyright 2022-2023 XpertaMexico
     * @package App\Negocio\Guias
     * @api
     * 
     * @version 1.0.0
     * 
     * @since 1.0.0 Primera version de la funcion fedexApi
     * 
     * @throws
     *
     * @param array $parametros eseseses
     * 
     * @var int 
     * 
     * 
     * @return json Objeto con la respuesta de exito o fallo 
     */

    public function valoresCotizacion($data){
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        
        $data['costo_kg_extra']= 0;
        $data['costo_seguro'] = 0;
        $data['costo_extendida'] = 0;
        $data['sobre_peso_kg'] =0;
        $data['bSeguro'] = false;
       
        $data['peso_bascula'] = $data['peso'];
        $data['peso_dimensional'] = ($data['alto']*$data['ancho']*$data['largo'])/5000;
        
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $data['peso_facturado'] = ($data['peso_bascula'] > $data['peso_dimensional']) ? ceil($data['peso_bascula']) : ceil($data['peso_dimensional']) ;
        
        $data['pesoFacturado']=$data['peso_facturado'];

        
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $data['subPrecio'] = $data['costo_kg_extra']+$data['costo_seguro']+$data['costo_extendida'];


        return $data;
    }//private function valoresCotizacion()


    /**
     * Se obtienen los datos obtener el precio
     * 
     * @author Javier Hernandez
     * @copyright 2022-2023 XpertaMexico
     * @package App\Negocio\Guias
     * @api
     * 
     * @version 1.0.0
     * 
     * @since 1.0.0 Primera version de la funcion fedexApi
     * 
     * @throws
     *
     * @param array $data informacion de todo el flujo 
     * 
     * @var int 
     * 
     * 
     * @return json Objeto con la respuesta de exito o fallo 
     */

    public function calculoPrecio($data) {
        $data['zona'] = $this->tarifa['zona'];
        $data['costo_base'] = $this->tarifa['costo'];
        $data['costo_seguro'] = 0;
        $data['costo_kg_extra'] = 0;
        $data['costo_extendida'] = 0;

        //Calcula sobre peso
        if ($data['peso_facturado'] > $this->tarifa['kg_fin'] ) {
            
            $data['sobre_peso_kg'] = $data['peso_facturado'] - $this->tarifa['kg_fin'];
            $data['costo_kg_extra'] = $data['sobre_peso_kg'] * $this->tarifa['kg_extra'];
        }
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        //valida Seguro
        if ($data['valor_envio'] > 0) {
            $data['costo_seguro'] = ($data['valor_envio']* $this->tarifa['seguro'])/100;
            $data['bSeguro'] = true;

        }

        $data['extendida'] = $this->tarifa['extendida_cobertura'];
        //Valida area extendida
        if ( $this->tarifa['extendida_cobertura'] === "SI"){
            $data['costo_extendida'] = $this->tarifa['extendida'];
            
        }
        $data['subPrecio'] = $data['costo_base']+$data['costo_kg_extra']+$data['costo_seguro'] + $data['costo_extendida'];
        $data['precio'] = round($data['subPrecio']*1.16, 2);
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        return $data;
    } //fin calculoPrecio

    public function getMensaje ()
    {
        return $this->mensaje;
    }

    public function getTabla()
    {
        return $this->tabla;
    }

    public function getSaldo()
    {
        return $this->saldo;
    }

    public function getTipoPagoId()
    {
        return $this->tipoPagoId;
    }

}// Fin Clase