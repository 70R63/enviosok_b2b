<?php
namespace App\Http\Middleware;use Closure;use Illuminate\Http\Request;use Illuminate\Support\Str;
final class ApiV1RequestId{public function handle(Request$r,Closure$next){$candidate=$r->header('X-ZIGO-Request-Id');$id=is_string($candidate)&&preg_match('/^[A-Za-z0-9_-]{8,64}$/',$candidate)?$candidate:(string)Str::uuid();$r->attributes->set('zigo_request_id',$id);$response=$next($r);$response->headers->set('X-ZIGO-Request-Id',$id);return$response;}}
