<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
        $this->renderable(function (ValidationException $e, $request) {
            if (! $request->is('api/hub/v1/*')) return null;
            return response()->json(['error'=>['code'=>'VALIDATION_ERROR','message'=>'La solicitud contiene datos inválidos.','details'=>$e->errors()],'meta'=>['request_id'=>$request->attributes->get('zigo_request_id')]],422);
        });
        $this->renderable(function (ModelNotFoundException $e, $request) {
            if (! $request->is('api/hub/v1/*')) return null;
            return response()->json(['error'=>['code'=>'RESOURCE_NOT_FOUND','message'=>'El recurso no existe o no está autorizado.'],'meta'=>['request_id'=>$request->attributes->get('zigo_request_id')]],404);
        });
    }
}
