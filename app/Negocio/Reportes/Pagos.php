<?php

namespace App\Negocio\Reportes;

use Log;
use File;
use Carbon\Carbon;

use App\Models\Saldos\Bancos as mBancos;

use App\Models\Reportes\Pagos AS mPagos;
use App\Models\Saldos\Pagos AS mSaldoPagos;

class Pagos 
{
	private $mensaje = array();
	private $bancos = array();
    private $tabla = array();


	/**
     * Metodo Ventas el cual obtine los registros apra armar el reporte de ventas
     * 
     * @param array $parametros
     * @return void
     */

    public function tablaResumen ()
    {
        $this->tabla = mPagos::select("reporte_pagos.id","user_id", "reporte_pagos.empresa_id", "banco_id", "fecha_ini", "fecha_fin", "ruta_csv", "registros_cantidad"
            ,\DB::raw('DATE_FORMAT(    reporte_pagos.created_at, "%Y-%c-%d %H:%i") as creada')
            ,\DB::raw("IF(banco_id=0,'TODOS',bancos.nombre) AS banco_nombre ")  
            ,"users.name as user_nombre"
            ,\DB::raw("IF(reporte_pagos.empresa_id=0,'TODOS',empresas.nombre) AS empresa_nombre ")
            )
            ->joinUsers()
            ->joinBancos()
            ->joinEmpresas()
            ->get()->toArray();

    }

    /**
     * Metodo Ventas el cual obtine los registros apra armar el reporte de ventas
     * 
     * @param array $parametros
     * @return void
     */

    public function cfgBancos ()
    {
    	Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
    	$this->bancos = mBancos::where("estatus",1)
    				->orderBy("nombre", "ASC")
    				->pluck("nombre","id")
    				->toArray();
        array_unshift($this->bancos, "TODOS");
        
    	Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
    	
    }


    /**
     * 
     * 
     * @param array $parametros
     * @return void
     */

    public function creacionCsv (array $parametros)
    {
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        
        $pagos = mSaldoPagos::select("pagos.id AS pago_id","empresas.nombre AS empresa_nombre", "bancos.nombre AS banco_nombre", "users.name AS users_nombre",\DB::raw('DATE_FORMAT(pagos.created_at, "%Y-%m-%d %h:%m") as created_at'), "importe", "referencia", "fecha_deposito"

            )
                ->filtro($parametros)
                ->joinEmpresa()
                ->joinBancos()
                ->joinUsuario()
                ->get()->toArray()
                //->toSql()
                ;
        Log::debug(print_r($pagos,true));


        $parametros['fecha_ini'] = is_null($parametros['fecha_ini']) ? "1999-12-31" : $parametros['fecha_ini'];
        $parametros['fecha_fin'] = is_null($parametros['fecha_fin']) ? "1999-12-31" : $parametros['fecha_fin'];

        $parametros['user_id'] = auth()->user()->id;

            
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $carbon = Carbon::parse();
        $carbon->settings(['toStringFormat' => 'Y-m-d_hm']);
        $timestamps = Carbon::now()->timestamp;
        $nameCsv = sprintf("reportes/pagos/%s-reportepago_%s_%s-%s.csv"
                ,(string)$carbon,$parametros['empresa_id'],$parametros['banco_id'],$timestamps);

        $parametros['ruta_csv'] =$nameCsv; 
        $filename =  public_path($nameCsv);
        $handle = fopen($filename, 'w');

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$nameCsv.'"');
        header("Content-Transfer-Encoding: binary");
            
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        fputcsv($handle, [
            "ID PAGO",
            "FECHA CREACION"
            ,"USUARIO"
            ,"EMPRESA"
            ,"BANCO"
            ,"IMPORTE"
            ,"REFERENCIA"
            ,"FECHA DEPOSITO"
            

        ]);

        $contador = 0;
        foreach ($pagos as $pago) {

            fputcsv($handle, [
                $pago['pago_id']
                ,date('Y-m-d h:m',strtotime($pago['created_at']) )
                ,$pago['users_nombre']
                ,$pago['empresa_nombre']
                ,$pago['banco_nombre']
                ,$pago['importe']
                ,$pago['referencia']
                ,$pago['fecha_deposito']

            ]);
            $contador++;
        }
        fclose($handle);
        $parametros['registros_cantidad'] =$contador;

        Log::debug(print_r($parametros,true));
        mPagos::create($parametros);
        
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);

    }




    public function getMensaje ()
    {
        return $this->mensaje;
    }

    public function getBancos()
    {
        return $this->bancos;
    }

    public function getTabla()
    {
        return $this->tabla;
    }
}