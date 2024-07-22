<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Configuracion para LTDs
    |--------------------------------------------------------------------------
    |
    | LTD.ID valor para decalro en la tabla ltd
    |
    |
    |
    |El valor servicio es el Id delcaro en la tabla servicios
    |en caso de poner un valor mal el tipo de servicio puede causar una guia con valores distintos
    |1) = Terrestre, 2) Siguientes dia, 3) dos dias, los valores debran ajustarse para cada LTD 
    |
    |
    */

    'general' =>[
        0   => 'TODOS'
        ,1   => 'FEDEX'
        ,2  => 'ESTAFETA_MEXICANA'
        ,3  => 'REDPACK'
        ,4  => 'DHL'
        ,6  => 'UPS'
    ]
    ,'estafeta'      => env('WSDL_ESTAFETA', 'Laravel'),
    'estafeta_tracking' => env('WSDL_ESTAFETA_TRACKING_DEV', 'tracking'),
    'fedex' => [
        'id'            => "1"
        ,'nombre'=>"FEDEX"
        ,'base_uri'     => env('FEDEX_BASEURI')
        ,'client_id'    => env('FEDEX_CLIENT_ID')
        ,'client_secret'=> env('FEDEX_CLIENT_SECRET')
        ,'servicio' => [
            '1'     => 'FEDEX_EXPRESS_SAVER'
            ,'2'    => 'STANDARD_OVERNIGHT'
            ,'3'    => 'STANDARD_OVERNIGHT'
        ]
        ,'cred'     => [
            'accountNumber' => env('FEDEX_ACCOUNTNUMBER')
        ]
        ,'rastreoEstatus' => [
            'IN'    => '1'
            ,'HL'     => '2'
            ,'PU'     => '2'
	        ,'SE'     => '2'
	        ,'DE' => '3'
            ,'IT'    => '3'
            ,'DY'    => '3'
            ,'DL'    => '4'
            ,'OC'    => '1'
            ,'HP'    => '2'
            ,'OW'    => '3'
	    ,'CD'   => '3'
	    ,'OD'   => '3'
	    ,'DU'   => '3'
	    ,'RL'   => '3'
        ]   
    ]
    ,'estafeta' =>[
        'id'    => "2"
        ,'nombre'=>"ESTAFETA"
        ,'base_uri'  =>  env('ESTAFETA_BASEURI')
        ,'token_uri'    =>  env('ESTAFETA_TOKEN_URI')
        ,'api_key'  =>  env('ESTAFETA_APIKEY')
        ,'secret'   => env('ESTAFETA_SECRET')
        ,'servicio' => [
            '1'     => '70'
            ,'2'    => '60'
            ,'3'    => 'D0'
        ]
        ,'cred'     => [
            'suscriberId' => env('ESTAFETA_SUSCRIBERID')
            ,'customerNumber' => env('ESTAFETA_CUSTOMERNUMBER')
            ,'salesOrganization'=>env('ESTAFETA_SALESORGANIZATION')
        ]
        ,'rastreoEstatus' => [
            'ON_TRANSIT' => '3'
            ,'DELIVERED'=> '4'
        ]
        ,'rastreo' => [
            'suscriberId' => env('ESTAFETA_SUSCRIBERID_TRACKING')
            ,'login'=>env('ESTAFETA_LOGIN_TRACKING')
            ,'pswd'=>env('ESTAFETA_PSWD_TRACKING')
            ,'api_key'  =>  env('ESTAFETA_APIKEY_TRACKING') 
            ,'secret'   => env('ESTAFETA_SECRET_TRACKING')
            ,'base_uri'  =>  env('ESTAFETA_BASEURI_TRACKING')
            ,'servicio'  =>  env('ESTAFETA_SERVICIO_TRACKING')
        ]
    ]
    ,'redpack' =>[
        'id'    => "3"
        ,'nombre'=>"REDPACK"
        ,'base_uri_token'  =>  env('REDPACK_BASEURI_TOKEN')
        ,'client_id'  =>  env('REDPACK_CLIENT_ID')
        ,'client_secret'   => env('REDPACK_CLIENT_SECRET')
        ,'user'  =>  env('REDPACK_USER')
        ,'pass'   => env('REDPACK_PASS')
        ,'uri_documentation' => env('REDPACK_URI_DOCUMENTATION')
        ,'idClient' => env('REDPACK_IDCLIENT')
        ,'servicio' => [
            '1'     => '2'
            ,'2'    => '1'
            ,'3'    => '1'
        ]
        ,'rastreoEstatus' => [
            '93' => '1'
	    ,'10' => '1'
	    ,'2' => '2'
	    ,'6' => '2'
	    ,'3'=> '3'
	    ,'8' => '3'
	    ,'42' => '3'
	    ,'43' => '3'
	    ,'48' => '3'
	    ,'1004'=>'3'
	    ,'1015'=>'3'
	    ,'1' => '4'
	    ,'5' => '3'
	    ,'200'=>'3'
	    ,'2002'=>'3'
	    ,'2006'=>'3'
	    ,'125' =>'3'
            ,'2007'=>'6'
	    ,'0' => '1'
            
        ]
        ,'rastreo' => [
            'uri'  =>  env('REDPACK_TRACKINGBYNUMBER_URI')
            ,'servicio'  =>  env('REDPACK_TRACKINGBYNUMBER')
        ]
    ]
    ,'dhl' =>[
        'id'    => "4"
        ,'nombre'=>"DHL"
        ,'base_uri'  =>  env('DHL_BASEURI')
        ,'token_uri'    =>  env('ESTAFETA_TOKEN_URI')
        ,'api_key'  =>  env('DHL_APIKEY')
        ,'secret'   => env('DHL_SECRET')
        ,'servicio' => [
            '1'     => 'G'
            ,'2'    => 'N'
            ,'5'    => '0'
            ,'6'    => '1'
        ]
        ,'rastreoEstatus' => [
            'SA' => '1'
            ,'PU'=> '2'
            ,'PL' =>  '2'
            ,'DF' =>  '2'
            ,'AF' =>  '3'
            ,'AR' =>  '3'
            ,'WC' =>  '3'
    	    ,'OK' =>  '4'
    	    ,'OH' => '3'
    	    ,'CA' => '3'
    	    ,'CC' => '3'
    	    ,'FD' => '3'
    	    ,'NH' => '3'
    	    ,'MD' => '3'
    	    ,'AD' => '3'
    	    ,'BA' => '3'
    	    ,'RD' => '3'
    	    ,'RT' => '5'
    	    ,'CM' => '3'
	        ,'RR' => '3'
	        ,'CS' => '9'
            ,'ND' => '3'
        ]
        ,'shipment' => [
            'uri' => env('DHL_URI_SHIPMENT')
        ]
        ,'kgmas70'  => [
            'base'  => 320
            ,'zona'=> [
                'A'  => '37.14'
                ,'B'  => '42.86'
                ,'C'  => '48.68'
                ,'D'  => '51.4'
                ,'E'  => '120.2'
                ,'F'  => '125.44'
                ,'G'  => '134.4'
                ,'H'  => '141.38'
            ]
        ]
    ]

]; 
