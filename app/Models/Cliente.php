<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

use Log;
use App\Models\Sucursal;

class Cliente extends Model
{
    use HasFactory;

    protected $table = 'clientes';
    protected $guarded = []; 

    private $existe = false ;
    private $insertId = 0 ;


    /**
     * Agraga a la consulta los casos de negocio.
     *
     * 
    */

    protected static function boot()
    {
        parent::boot();        
        static::addGlobalScope('estatus', function (Builder $builder) {
            $builder->where('clientes.estatus', '1');

            $empresaId =  isset(auth()->user()->empresa_id)  ? auth()->user()->empresa_id : 2 ;
            $empresas = EmpresaEmpresas::where('id',$empresaId)
                ->pluck('empresa_id')->toArray();
            $builder->whereIN('empresa_id',$empresas);
        });
    }

    /**
     * Funcion para crear un objeto para insertar el registro del cliente.
     *
     * @param $request
     * @return array 
     * 
    */

    public function insertSemiManual($request){
        Log::info(__CLASS__." ".__FUNCTION__." INICIANDO ---------");

        
        if ($request['esManual'] === "SI" || $request['esManual'] === "SEMI" || $request['esManual'] === "API" ) {
            $empresa_id = $request['empresa_id'];
        } else {
            $empresa_id = auth()->user()->empresa_id;
        }
        
        
        $insert = array(
            "nombre"    => $request['nombre_d']
            ,"contacto" => $request['contacto_d']
            ,"direccion"=> $request['direccion_d']
            ,"direccion2"=>$request['direccion2_d']
            ,"cp"       => $request['cp_d']
            ,"colonia"  => $request['colonia_d']
            ,"ciudad"   => $request['ciudad_d']
            ,"entidad_federativa"=>$request['entidad_federativa_d']
            ,"celular"  => $request['celular_d']
            ,"telefono" => $request['telefono_d']
            ,"empresa_id"=>$empresa_id
            ,"no_ext"   => $request['no_ext_d']
            ,"no_int"   => $request['no_int_d']

            );

        $this->insertId = $this->create($insert)->id;
        
        
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." FINALIZANDO ---------");
        

    }    

    /**
     * Funcion para crear un objeto para insertar el registro del cliente.
     *
     * @param $request
     * @return array 
     * 
    */

    public function validaCliente($request){
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." INICIANDO ---------");

        $empresa_id = $request['empresa_id'];
        switch ($request['esManual']) {
            case "SI":
                Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." esManual = si ");
                $canal = "MNL" ;
                $empresa_id = $request['empresa_id'];
                $cliente = self::where('nombre', 'like', $request['nombre_d'])
                            ->where('empresa_id',$empresa_id)
                            ->pluck('id')
                            ->toArray();
                break;
            case "SEMI":
                Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." esManual = semi ");
                $canal = "SML" ;
                $empresa_id = $request['empresa_id'];
                $cliente = self::where('nombre', 'like', $request['nombre_d'])
                            ->where('empresa_id',$empresa_id)
                            ->pluck('id')
                            ->toArray();
            case "API":
                Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." esManual = API ");
                $canal = "API" ;
                $empresa_id = $request['empresa_id'];
                $cliente = self::where('nombre', 'like', $request['nombre_d'])
                            ->where('empresa_id',$empresa_id)
                            ->pluck('id')
                            ->toArray();
                
                break;     
            case "RETORNO":
                Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__." esManual = RETORNO ");
                $cliente = self::where("id", $request['cliente_id'] )->pluck('id')
                            ->toArray();;
                break;           
            default:
                Log::info("No se detecto el canal");
        }
        

        Log::debug(print_r($cliente,true));

        Log::info(__CLASS__." ".__FUNCTION__." FINALIZANDO ---------");
        if (empty($cliente)) {
            $this->existe = false;
        } else {
            $this->existe = true;
            $this->insertId = $cliente[0];
        }

        
    }

    public function getExiste(){
        return $this->existe;
    }


    public function getId(){
        return $this->insertId;
    }
}
