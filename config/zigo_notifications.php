<?php
$emails=array_values(array_unique(array_filter(array_map(fn($v)=>strtolower(trim($v)),explode(',',(string)env('ZIGO_CRM_ADMIN_NOTIFICATION_EMAILS',''))),fn($v)=>filter_var($v,FILTER_VALIDATE_EMAIL))));
return ['enabled'=>(bool)env('ZIGO_CRM_ADMIN_NOTIFICATION_ENABLED',true),'emails'=>$emails,'invoice_request'=>(bool)env('ZIGO_CRM_NOTIFY_INVOICE_REQUEST',true),'incident'=>(bool)env('ZIGO_CRM_NOTIFY_INCIDENT',true),'prospect'=>(bool)env('ZIGO_CRM_NOTIFY_PROSPECT',true),'export_limit'=>(int)env('ZIGO_CRM_GUIDES_EXPORT_LIMIT',25000)];
