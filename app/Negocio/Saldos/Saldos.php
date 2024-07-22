<?php
namespace App\Negocio\Saldos;

use Log;
use File;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

//modelos
use App\Models\Saldos\Saldos as mSaldo;
use App\Models\API\Sucursal;
use App\Models\Guia;


class Saldos 
{
    private $mensaje = array();
    
    
    /**
     * Metodo calcular, se busca sumar el monto del paga e actualizar o calcular el monto actualy dejar el monto anterior
     * 
     * @param array $parametros
     * @return void
     */

    public function calcular (array $inputs)
    {
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
    
        $saldo = mSaldo::where("empresa_id", $inputs["empresa_id"])->firstOrFail();
        $saldoArray = $saldo->toArray();
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $saldoArray["monto_anterior"]=$saldoArray["monto"];
        $saldoArray["monto"]=$saldoArray["monto"]+$inputs["importe"];
        
        $saldo->fill($saldoArray)->save();

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
    }


    /**
     * Se busca obtener el saldo de la empresa 
     * 
     * @param array $parametros
     * @return monto
     */

    public function porEmpresa ( $empresa_id)
    {
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        
        $saldo = mSaldo::select("monto")->where("empresa_id", $empresa_id)->firstOrFail();

        Log::info(print_r($saldo->monto,true));
        

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        return $saldo->monto;
    }


    /**
     * Se busca obtener el saldo de la empresa 
     * 
     * @param int $empresa_id
     * @param int $precio
     * @return avoid
     */

    public function menosPrecio ( $cia, $precio )
    {
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        
        Log::info($cia." ".$precio);
        $sucursal = Sucursal::select("empresa_id")->where("id", $cia)->firstOrFail();
        
        Log::debug(print_r("empresa_id ".$sucursal->empresa_id,true));

        
        $saldo = mSaldo::where("empresa_id", $sucursal->empresa_id)->firstOrFail();
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $saldoArray = $saldo->toArray();
        $saldoArray["monto_anterior"]=$saldoArray["monto"];
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $saldoArray["monto"]=$saldoArray["monto"]-$precio;
       
        Log::debug(print_r($saldoArray,true)); 
        $saldo->fill($saldoArray)->save();
        
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        
    }

    /**
     * Metodo recuperar, se busca  sumar saldo de una guia eliminada
     * 
     * @param array $parametros
     * @return void
     */

    public function recuperar ($request)
    {
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);

        $parametros=$request->all();
        $guiaId = $parametros["idGuiaForm"];
        $cia = $parametros["ciaForm"];
        $precio = $parametros["precioForm"];

        $empresaId = Sucursal::select("empresa_id")->where("id",$cia)->firstOrFail()->empresa_id;

        Guia::where("id",$guiaId)->firstOrFail()->update(["estatus"=>0]);

        $saldo = mSaldo::where("empresa_id", $empresaId)->firstOrFail();
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $saldoArray = $saldo->toArray();
        $saldoArray["monto_anterior"]=$saldoArray["monto"];
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $saldoArray["monto"]=$saldoArray["monto"]+$precio;
        
        $saldo->fill($saldoArray)->save();

        $this->mensaje[] = sprintf("La guia %s se elimino correctamente, el saldo recuperado es $%s",$guiaId,$precio);

        $this->mensaje[] = sprintf("El nuevo saldo es $%s",$saldoArray["monto"]);
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
    }


    /**
     * Valida las reglas de negocio para el saldo
     * 
     * @author Javier Hernandez
     * @copyright 2022-2023 XpertaMexico
     * @package App\Negocio\Guias
     * 
     * @version 1.0.0
     * 
     * @since 1.0.0 Primera version de la funcion fedexApi
     * 
     * @throws
     *
     * @param float saldo monto disponible que tiene una empresa
     * 
     * @var int 
     * @var float $saldo
     * 
     * 
     * @return $data Se agra informacion segun la necesidad
     */

    public function validaSaldo($saldo){
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $saldoMinimo = 90; 
        
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        Log::debug(print_r($saldoMinimo,true));

        if ($saldo < $saldoMinimo) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            $mensaje[] = sprintf("El Saldo: %s es menor al limite permitido",$saldo);
            throw ValidationException::withMessages($mensaje);
        }

        if ($saldo < 0) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            $mensaje[] = sprintf("Saldo Negativo: $%s",$saldo);
            throw ValidationException::withMessages($mensaje);
        }

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
    }




    public function getMensaje ()
    {
        return $this->mensaje;
    }
}