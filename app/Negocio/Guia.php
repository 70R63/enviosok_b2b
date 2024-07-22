<?php

namespace App\Negocio;

use Log;

class Guia 
{
    private $tarifa = array();
    
    /**
     * Metodo para poder mandar a 0 los valors que ya no se deben de duplicar en el insert de las guias, por el tema de reports 
     * 
     * @param array $insert
     * @return array body
     */

    static public function costosEnCero ($insert)
    {
        
        $insert['peso'] = 0; 
        $insert['seguro'] = 0; 
        $insert['valor_envio'] = 0; 
        $insert['precio'] = 0; 
        $insert['costo_base'] = 0; 
        $insert['costo_kg_extra'] = 0; 
        $insert['costo_kg_extra'] = 0; 
        
        return $insert;
    }

}