<?php
namespace App\Http\Middleware;use Closure;use Illuminate\Http\Request;
final class RequireTenantApiScope{public function handle(Request$r,Closure$next,string$scope){if(!in_array($scope,$r->attributes->get('api_scopes',[]),true))return response()->json(['error'=>['code'=>'INSUFFICIENT_SCOPE','message'=>'La API key no autoriza esta operación.'],'meta'=>['request_id'=>$r->attributes->get('zigo_request_id')]],403);return$next($r);}}
