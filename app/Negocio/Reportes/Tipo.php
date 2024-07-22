<?php

namespace App\Negocio\Reportes;

use Log;
use File;
use Carbon\Carbon;

use App\Models\API\Reportes_ventas;
use App\Models\API\Reportes;
use App\Models\API\Reportes_repesajes;

class Tipo 
{
    
    
    /**
     * Metodo Ventas el cual obtine los registros apra armar el reporte de ventas
     * 
     * @param array $parametros
     * @return void
     */

    public function ventas (array $parametros)
    {
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $reporteVentas = Reportes_ventas::filtro( $parametros )
                ->where("reportes_ventas.estatus",1)
                ->get()->toArray()
                
            ;

        //Log::debug();
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
     
        $ltdLeyenda = Config("ltd.general")[$parametros['ltdId']];


        $carbon = Carbon::parse();
        $carbon->settings(['toStringFormat' => 'Y-m-d']);

        $fechaIni = empty($parametros['fecha_ini']) ? "0000-00-00" : Carbon::parse($parametros['fecha_ini'])->format('Y-m-d');

        $fechaFin = empty($parametros['fecha_fin']) ? "0000-00-00" : Carbon::parse($parametros['fecha_fin'])->format('Y-m-d');

        $nameCsv = sprintf("csv/%s-cliente_%s-ltd_%s-servicio_%s-de_%s-a_%s.csv"
            ,(string)$carbon,$parametros['clienteIdCombo'],$parametros['ltdId'],$parametros['servicio_id'], $fechaIni, $fechaFin );

        $filename =  public_path($nameCsv);
        $handle = fopen($filename, 'w');

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$nameCsv.'"');
        header("Content-Transfer-Encoding: binary");

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        fputcsv($handle, [
            "ID GUIA",
            "EMPRESA_ID"
            ,"USUARIO"
            ,"LTD_ID"
            ,"NO SOLICITUD"
            ,"TRACKINGNUMBER"
            ,"SERVICIO_ID"
            ,"CLIENTE EXPERTA"
            ,"CREACION"
            ,"FECHA ENVIO"
            ,"LARGO"
            ,"ANCHO"
            ,"ALTO"
            ,"PESO FACTURADO"
            ,"PESO DIMENSIONAL"
            ,"PESO BASCUAL"
            ,"CIUDAD ORIGEN"
            ,"ESTADO ORIGEN"
            ,"CP ORIGEN"
            ,"CONTACTO REMITENTE"
            ,"CIUDAD DESTINO"
            ,"ESTADO DESTINO"
            ,"CP DESTINO"
            ,"CONTACTO DESTINO"
            ,"REFERENCIA"
            ,"NOTAS"
            ,"ZONA"
            ,"KGS EXTRA"
            ,"COSTO BASE"
            ,"COSTO KGS EXTRA"
            ,"COSTO A.E."
            ,"COSTO EXCESO DIMENSION Y/0 VOL IRREGULAR"
            ,"COSTO SERVICIO PREMIUM"
            ,"COSTO MULTIPIEZA"
            ,"COSTO SEGURO"
            ,"SUBTOTAL"
            ,"TOTAL"
            ,"ESTATUS RASTREO"


        ]);

        $contador = 0;
        foreach ($reporteVentas as $venta) {

            $subtotal = $venta['costo_base']+$venta['costo_kg_extra']+$venta['costo_extendida']+$venta['seguro']+$venta['servicio_premium']
                +$venta['multipieza'];

            fputcsv($handle, [
                $venta['id'],
                $venta['clilente_xperta'],
                $venta['usuario']
                ,$venta['ltd_nombre']
                ,$venta['numero_solicitud']
                ,sprintf("'%s",trim($venta['tracking_number']))
                ,$venta['servicio_nombre']
                ,$venta['clilente_xperta']
                ,$venta['created_at']
                ,""
                ,$venta['largo']
                ,$venta['ancho']
                ,$venta['alto']
                ,$venta['peso_facturado']
                ,$venta['peso_dimensional']
                ,$venta['peso_bascula']
                ,$venta['ciudad_origen']
                ,$venta['entidad_federativa_origen']
                ,$venta['cp_origen']
                ,$venta['contacto_origen']
                ,$venta['ciudad_origen']
                ,$venta['entidad_federativa_destino']
                ,$venta['cp_destino']
                ,$venta['contacto_destino']
                ,""
                ,""
                ,$venta['zona']
                ,$venta['sobre_peso_kg']
                ,$venta['costo_base']
                ,$venta['costo_kg_extra']
                ,$venta['costo_extendida']
                ,0
                ,$venta['servicio_premium']
                ,$venta['multipieza']
                ,$venta['seguro']
                ,$subtotal
                ,$venta['precio']
                ,$venta['rastreo_nombre']

            ]);
            $contador++;
        }
        fclose($handle);

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $this->insertarReporte($parametros, $fechaIni, $fechaFin, $nameCsv, $contador);
        
    }// fin ventas


    public function repesaje (array $parametros)
    {

        $reporteRepesajes = Reportes_repesajes::filtro( $parametros )
                ->get()->toArray()
                
            ;
 
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        Log::debug(print_r($reporteRepesajes,true));


        $ltdLeyenda = Config("ltd.general")[$parametros['ltdId']];


        $carbon = Carbon::parse();
        
        $carbon->settings(['toStringFormat' => 'Y-m-d']);

        $fechaIni = empty($parametros['fecha_ini']) ? "0000-00-00" : Carbon::parse($parametros['fecha_ini'])->format('Y-m-d');

        $fechaFin = empty($parametros['fecha_fin']) ? "0000-00-00" : Carbon::parse($parametros['fecha_fin'])->format('Y-m-d');

        $nameCsv = sprintf("csv/%s-cliente_%s-ltd_%s-servicio_%s-de_%s-a_%s.csv"
            ,(string)$carbon,$parametros['clienteIdCombo'],$parametros['ltdId'],$parametros['servicio_id'], $fechaIni, $fechaFin );

        $filename =  public_path($nameCsv);
        $handle = fopen($filename, 'w');

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        fputcsv($handle, [
            
            "GUIA_ID"
            ,"CLIENTE XPERTA"
            ,"TRACKINGNUMBER"
            ,"FECHA RECOLECCION"
            ,"SERVICIO"
            ,"LARGO"
            ,"ANCHO"
            ,"ALTO"
            ,"PESO DIMENSIONAL"
            ,"PESO BASCULA"
            ,"PESO FACTURADO"
            ,"LARGO"
            ,"ANCHO"
            ,"ALTO"
            ,"PESO DIMENSIONAL"
            ,"PESO BASCULA"
            ,"KGS EXTRA"
            ,"COSTO BASE"
            ,"COSTO KGS EXTRA"
            ,"COSTO A.E."
            ,"COSTO EXCESO DIMENSION Y/0 VOL IRREGULAR"
            ,"COSTO SERVICIO PREMIUM"
            ,"COSTO MULTIPIEZA"
            ,"COSTO SEGURO"
            ,"SUBTOTAL"
            ,"TOTAL"
            ,"ESTATUS RASTREO"


        ]);

        $contador = 0;
       
        foreach ($reporteRepesajes as $repesaje) {
            $subtotal = $repesaje['costo_base']+$repesaje['costo_kg_extra']+$repesaje['costo_extendida']+$repesaje['seguro'];

            fputcsv($handle, [
                $repesaje['id']
                ,$repesaje['cliente_xperta']
                ,$repesaje['tracking_number']
                ,$repesaje['pickup_fecha']
                ,$repesaje['servicio_nombre']
                ,$repesaje['largo']
                ,$repesaje['ancho']
                ,$repesaje['alto']
                ,$repesaje['peso_dimensional']
                ,$repesaje['peso_bascula']
                ,$repesaje['peso_facturado']
                ,$repesaje['largo_rastreo']
                ,$repesaje['ancho_rastreo']
                ,$repesaje['alto_rastreo']
                ,"0"
                ,"0"
                ,$repesaje['sobre_peso_kg']
                ,$repesaje['costo_base']
                ,$repesaje['costo_kg_extra']
                ,$repesaje['costo_extendida']
                ,"0"
                ,"0"
                ,"0"
                ,$repesaje['seguro']
                ,$subtotal
                ,$repesaje['precio']
                ,$repesaje['rastreo_nombre']

                

            ]);
            $contador++;
        }
        
        fclose($handle);
        header('Content-Type: text/csv');
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        //$this->insertarReporte($parametros, $fechaIni, $fechaFin, $nameCsv, $contador);
        
    }// repesaje



    private function insertarReporte($parametros, $fechaIni, $fechaFin, $nameCsv, $contador)
    {
        Reportes::create(
            array('cia' => $parametros['clienteIdCombo']
                ,'ltd_id' => $parametros['ltdId']
                ,'servicio_id'=>$parametros['servicio_id']
                ,'fecha_ini' => $fechaIni
                ,'fecha_fin' => $fechaFin
                ,'ruta_csv' => sprintf("public/%s",$nameCsv)
                ,'registros_cantidad' => $contador
                ,'tipo' => $parametros['tipo']
            )
        );
    } //fin insertarReporte
}