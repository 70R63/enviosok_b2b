<?php
namespace App\Negocio\Saldos;


use Log;

//modelos
use App\Models\Guia;
use App\Models\Saldos\Ajustes as mAjustes;
use App\Models\API\Sucursal;

//Negocio
use App\Negocio\Saldos\Saldos as nSaldos;

class Ajustes 
{
    private $mensaje = array();
    private $guia = null;
    private $tabla = array();
    
 
    /**
     * Metodo detalleGuia, se busca  sumar saldo de una guia eliminada
     * 
     * @param array $parametros
     * @return void
     */

    public function detalleGuia ($guiaId)
    {
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        Log::debug(print_r($guiaId  ,true));

        $this->guia = Guia::select("guias.id AS guias_id", "cia", "tracking_number","pickup_fecha","ltds.nombre as ltd_nombre", "empresas.nombre AS empresa_nombre")
                ->where("guias.id",$guiaId)
                ->joinSucursalAjuste()
                ->joinEmpresaAjuste()
                ->joinLtdAjuste()
                ->firstOrFail()
                ;
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
    }

    /**
     * Metodo detalleGuia, se busca  sumar saldo de una guia eliminada
     * 
     * @param array $parametros
     * @return void
     */

    public function insertar ($inputs)
    {
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        
        $inputs['user_id'] = auth()->user()->id;
        $sucursal = Sucursal::select("empresa_id")
                    ->where("id",$inputs["cia"])
                    ->firstOrFail();
        
        $inputs['empresa_id'] = $sucursal->empresa_id;
        if ($inputs['importe']>=0) {
            $inputs['nota_de']= "credito";
        }else{
            $inputs['nota_de']= "debito";
        }
        Log::debug(print_r($inputs ,true));
        $nSaldos = new nSaldos();
        $nSaldos->calcular($inputs);
        $idAjuste = mAjustes::create($inputs)->id;
        
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
    }

    /**
     * Metodo detalleGuia, se busca  sumar saldo de una guia eliminada
     * 
     * @param array $parametros
     * @return void
     */

    public function tabla ()
    {
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        
        $this->tabla = mAjustes::select("ajustes.id","ajustes.created_at","name","empresas.nombre","factura_id","fecha_deposito", "importe", "comentarios", "nota_de"
            ,"guia_id", "guias.tracking_number", "guias.pickup_fecha", "guias.ltd_id"
            ,"ltds.nombre AS ltd_nombre"
            )
            ->joinUsuario()
            ->joinEmpresa()
            ->joinGuias()
            ->joinLtds()
            ->where('ajustes.created_at', '>', now()->subDays(30)->endOfDay())
            ->orderBy("ajustes.id","desc")
            ->get()->toArray();
        
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
    }


    public function getMensaje ()
    {
        return $this->mensaje;
    }

    public function getGuia ()
    {
        return $this->guia;
    }

    public function getTabla()
    {
        return $this->tabla;
    }
}