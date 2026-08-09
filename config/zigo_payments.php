<?php
return ['default'=>'mercado_pago','platform'=>[
 'enabled'=>env('ZIGO_MP_PLATFORM_ENABLED',false),'access_token'=>env('ZIGO_MP_PLATFORM_ACCESS_TOKEN'),'account_id'=>env('ZIGO_MP_PLATFORM_ACCOUNT_ID'),'webhook_secret'=>env('ZIGO_MP_PLATFORM_WEBHOOK_SECRET'),'webhook_url'=>env('ZIGO_MP_PLATFORM_WEBHOOK_URL'),
],'providers'=>['mercado_pago'=>[
 'enabled'=>env('ZIGO_MP_ENABLED',false),'environment'=>env('ZIGO_MP_ENVIRONMENT','sandbox'),
 'client_id'=>env('ZIGO_MP_CLIENT_ID'),'client_secret'=>env('ZIGO_MP_CLIENT_SECRET'),'redirect_uri'=>env('ZIGO_MP_REDIRECT_URI'),'webhook_secret'=>env('ZIGO_MP_WEBHOOK_SECRET'),
 'api_url'=>env('ZIGO_MP_API_URL','https://api.mercadopago.com'),'authorization_url'=>env('ZIGO_MP_AUTHORIZATION_URL','https://auth.mercadopago.com/authorization'),'webhook_url'=>env('ZIGO_MP_WEBHOOK_URL'),
 'marketplace_fee_enabled'=>env('ZIGO_MP_MARKETPLACE_FEE_ENABLED',false),'marketplace_fee_amount'=>env('ZIGO_MP_MARKETPLACE_FEE_AMOUNT','0.00'),
 'oauth_cache_store'=>env('ZIGO_MP_OAUTH_CACHE_STORE'),
 'connect_timeout'=>5,'timeout'=>15,'refresh_before_minutes'=>30,'webhook_tolerance_seconds'=>300,
]]];
