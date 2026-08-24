<?php

use App\Http\Controllers\API\ApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Payments\MercadoPagoWebhookController as TenantMercadoPagoWebhookController;
use App\Http\Controllers\Payments\MercadoPagoPlatformWebhookController;

Route::domain(config('zigo_surfaces.payments.host'))->group(function (): void {
    Route::post('/payments/mercado-pago/webhook', TenantMercadoPagoWebhookController::class)
        ->withoutMiddleware('throttle:api')->middleware('payments.edge.headers')
        ->name('payments.mercado-pago.webhook');
    Route::post('/payments/mercado-pago/platform/webhook', MercadoPagoPlatformWebhookController::class)
        ->withoutMiddleware('throttle:api')->middleware('payments.edge.headers')
        ->name('payments.mercado-pago.platform.webhook');
});

use App\Http\Controllers\API\LoginController  as AuthController;
use App\Http\Controllers\API\GuiaController;
use App\Http\Controllers\API\CotizacionController;
use App\Http\Controllers\API\EmpresaLtdController;
use App\Http\Controllers\API\DireccionController;
use App\Http\Controllers\API\CPController;
use App\Http\Controllers\API\ClienteController;
use App\Http\Controllers\API\ReportesController;
use App\Http\Controllers\API\Reportes\RepesajeController;
use App\Http\Controllers\API\Saldos\PagosController;
use App\Http\Controllers\API\Saldos\SaldosController;
use App\Http\Controllers\API\Reportes\PagosController as ReportesPagoController;
use App\Http\Controllers\API\Ltd\FedexController;
use App\Http\Controllers\API\Ltd\EstafetaController;

use App\Http\Controllers\API\Hub\PostalCodeController;
use App\Http\Controllers\API\Hub\BillingInvoiceController;
use App\Http\Controllers\API\Payments\MercadoPagoWebhookController;
use App\Http\Middleware\ValidateZigoApiKey;
use App\Http\Controllers\API\Hub\V1\{PostalController as V1PostalController,QuoteController as V1QuoteController,ShipmentController as V1ShipmentController,TrackingController as V1TrackingController};

use App\Http\Controllers\API\DEV\GuiaController as DevGuiaController ;
use App\Http\Controllers\Webchat\PublicWebchatController;

Route::prefix('ai/webchat/{publicKey}')->where(['publicKey'=>'wc_[A-Za-z0-9_-]{43}'])->group(function ():void {
    Route::options('/sessions', [PublicWebchatController::class,'options']);
    Route::options('/messages', [PublicWebchatController::class,'options']);
    Route::options('/actions/{actionRun}/confirm', [PublicWebchatController::class,'actionOptions']);
    Route::post('/sessions', [PublicWebchatController::class,'start'])->middleware('throttle:ai-webchat-start')->name('ai.webchat.sessions.start');
    Route::post('/messages', [PublicWebchatController::class,'message'])->middleware('throttle:ai-webchat-message')->name('ai.webchat.messages.store');
    Route::get('/messages', [PublicWebchatController::class,'history'])->middleware('throttle:ai-webchat-poll')->name('ai.webchat.messages.index');
    Route::post('/actions/{actionRun}/confirm', [PublicWebchatController::class,'confirm'])->middleware('throttle:ai-webchat-confirm')->name('ai.webchat.actions.confirm');
});


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('/register',[AuthController::class,'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::post(
    '/webhooks/mercadopago',
    MercadoPagoWebhookController::class
)
    ->middleware('throttle:120,1')
    ->name('api.webhooks.mercadopago');

Route::post('domicilio', [ApiController::class,'domicilio'])->name('api.domicilio');
Route::post('getColonias', [ApiController::class,'getColonias'])->name('api.getColonias');

Route::middleware('auth:sanctum')->get('/ping', function (Request $request) {

    return response()->json([
            'status' => true,
            'message' => "Ping successfully!",
        ], 200);
});

//SADEMIO
Route::name('api')->group(function () {
    Route::name('sademio.')->group(function () {
        Route::group(['prefix'=>'SADEMIO'], function(){
            Route::post('/login', [AuthController::class, 'login'])->name('login');
        });
    });
});

//Route::domain('local.xpertamexico.com')->group(function () {
    Route::middleware(['throttle:100,1','validaToken'])->group(function(){
        Route::post('logout', [AuthController::class, 'logout']);



        Route::controller(GuiaController::class)->group(function(){
            Route::get('ltds', 'creacion');
            Route::post('fedex', 'fedex');
            Route::post('estafeta', 'estafeta');
            Route::post('dev/estafeta', 'estafeta');
            Route::get('rastreoTabla', 'rastreoTabla');
        });


        Route::name('api.')->group(function () {
            //MENU FEDEX

            Route::name('enviosperros.')->group(function () {
                Route::group(['prefix'=>'enviosperros'], function(){
                    Route::name('fedex.')->group(function () {
                        Route::group(['prefix'=>'fedex'], function(){

                            Route::get('/greeting', function () {
                                return 'Hello World';
                            })->name("greeting");

                            Route::controller(FedexController::class)->group(function(){
                                Route::post('terrestre', 'terrestre')->name("terrestre");

                            });

                            Route::controller(FedexController::class)->group(function(){
                                Route::post('diasig', 'diasig')->name("diasig");

                            });

                            Route::controller(FedexController::class)->group(function(){
                                Route::get('cotizacion/{servicio}', 'cotizacion')->name("cotizacion");

                            });

                        });
                    });
                });
            });


            //SADEMIO
             Route::name('sademio.')->group(function () {
                Route::group(['prefix'=>'SADEMIO'], function(){

                    Route::name('estafeta.')->group(function () {
                        Route::group(['prefix'=>'estafeta'], function(){

                            Route::get('/greeting', function () {
                                return 'Hello World';
                            })->name("greeting");

                            Route::controller(EstafetaController::class)->group(function(){
                                Route::post('{servicios}', 'creacion')->name("creacion");
                            });
                        });
                    });
                });
            });

        });// FIN api.

    });
//});



//MIDDLEWARE PARA AJAX DESDE WEB
Route::middleware(['throttle:100,1','auth'])->group(function () {
    Route::name('api.')->group(function () {
        //Carga los metodos basicos index, store, update , etc
        Route::apiResource('cotizaciones', CotizacionController::class);

        Route::controller(CotizacionController::class)->group(function(){
            Route::get('cp', 'cp');
        });

        Route::apiResource('empresaltd', EmpresaLtdController::class);

        Route::controller(GuiaController::class)->group(function(){
            Route::get('guiasTabla', 'guiasTabla');
            Route::post('rastreoActualizar', 'rastreoActualizar');
        });

        Route::controller(DireccionController::class)->prefix('direccion')->group(function(){
            Route::get('{cliente}', 'index')->name("direcciones.tipo");

        });

        Route::controller(CPController::class)->group(function(){
            Route::get('cp/colonias', 'colonias')->name("cp.colonias");
        });

        Route::controller(ClienteController::class)->group(function(){
            Route::get('clientes', 'clientes')->name("clientes");
        });

        //MENU REPORTES
        Route::group(['prefix'=>'reportes','as'=>'reportes.'], function(){
            Route::controller(ReportesController::class)->group(function(){
                Route::get('ventas', 'reportes')->name("ventas");
                Route::post('ventas', 'creacion')->name("creacion");
            });

            Route::group(['prefix'=>'repesajes','as'=>'repesajes.'], function(){
                Route::controller(RepesajeController::class)->group(function(){
                    Route::get('repesajes', 'reportes')->name("repensajes");
                    Route::post('repesajes', 'creacion')->name("creacion");
                });
            });

            Route::group(['prefix'=>'pagos','as'=>'pagos.'], function(){
                Route::controller(ReportesPagoController::class)->group(function(){
                    Route::get('index', 'index')->name("index");
                    Route::post('creacion', 'creacion')->name("creacion");
                });
            });
        });

        //MENU SALDOS
        Route::group(['prefix'=>'saldos','as'=>'saldos.'], function(){
            Route::controller(PagosController::class)->group(function(){
                Route::get('pagos/resumen', 'tablaPagosResumen')->name("pagos_resumen");

            });

            Route::controller(PagosController::class)->group(function(){
                Route::get('pagos/{empresa_id}', 'tablaPagos')->name("pagos");

            });

            Route::controller(SaldosController::class)->group(function(){
                Route::get('empresas', 'porEmpresa')->name("empresas");

            });

        });






    });
});
//Fin Middileware

//ejecucion
 Route::controller(GuiaController::class)->group(function(){
    Route::get('rastreoActualizar', 'rastreoActualizarAutomatico')->name("rastreoConsola");
});

//AMBIENTE DEV TEMPORAL

Route::name('api.dev.')->group(function () {
    Route::group(['prefix'=>'dev/'], function(){
        Route::name('enviosperros.')->group(function () {
            Route::group(['prefix'=>'enviosperros'], function(){
                Route::post('/login', [AuthController::class, 'login'])->name('login');
            });
        });

        Route::name('sademio.')->group(function () {
            Route::group(['prefix'=>'SADEMIO'], function(){
                Route::post('/login', [AuthController::class, 'login'])->name('login');
            });
        });
    });
});


Route::middleware(['throttle:10,1','validaToken'])->group(function(){
    Route::controller(DevGuiaController::class)->group(function(){
        Route::post('dev/estafeta', 'estafeta');
    });

    Route::name('api.dev.')->group(function () {

        Route::group(['prefix'=>'dev/'], function(){

            Route::name('enviosperros.')->group(function () {
                Route::group(['prefix'=>'enviosperros'], function(){

                    Route::name('fedex.')->group(function () {
                        Route::group(['prefix'=>'fedex'], function(){

                            Route::get('/greeting', function () {
                                return 'Hello World';
                            })->name("greeting");

                            Route::controller(FedexController::class)->group(function(){
                                Route::post('terrestre', 'terrestreDEV')->name("terrestreDEV");

                            });

                            Route::controller(FedexController::class)->group(function(){
                                Route::post('diasig', 'diasigDEV')->name("diasigDEV");

                            });

                            Route::controller(FedexController::class)->group(function(){
                                Route::get('cotizacion/{servicio}', 'cotizacionDEV')->name("cotizacionDEV");

                            });

                        });
                    });
                });
            });

            //SADEMIO
             Route::name('sademio.')->group(function () {
                Route::group(['prefix'=>'SADEMIO'], function(){

                    Route::name('estafeta.')->group(function () {
                        Route::group(['prefix'=>'estafeta'], function(){

                            Route::get('/greeting', function () {
                                return 'Hello World';
                            })->name("greeting");

                            Route::controller(EstafetaController::class)->group(function(){
                                Route::post('{servicio}', 'creacionDEV')->name("creacionDEV");
                            });
                        });
                    });
                });
            });


        });// FIN api.
    });
});



//AMBINTE DEV.OPERANDOEXPERTAMENTE.COM

Route::name('api.v1.')->group(function () {
    Route::group(['prefix'=>'v1/'], function(){

        Route::group(['prefix'=>'{empresa}'], function(){
            Route::post('/login', [AuthController::class, 'login'])->name('login');
        });

    });
});


Route::middleware(['throttle:50,1','AccesosApi'])->group(function(){
    Route::name('api.v1.')->group(function () {
    Route::group(['prefix'=>'v1/'], function(){

        Route::group(['prefix'=>'empresas/{empresa}'
            , 'as'=>'empresas.']
            , function(){


            Route::get('/greeting', function () {
                                return 'Hello World';
                            })->name("greeting");

            Route::group(['prefix'=>'ltds/{ltds}/servicios/{servicios}'
                            ,'as' => 'ltds.servicios.' ]
                        ,function(){

                 Route::controller(CotizacionController::class)->group(function(){
                    Route::get('cotizaciones', 'cotizaciones')->name('cotizaciones');
                });


            });


            Route::group(['prefix'=>'ltds/estafeta', 'as'=>'ltds.estafeta.'], function(){
                Route::group(['prefix'=>'servicios/{servicios}', 'as'=>'servicios.'], function(){

                    Route::controller(EstafetaController::class)->group(function(){
                        Route::post('guia/{formatoImpresion?}', 'creacion')->name("guia");
                    });
                });
            });


            Route::group(['prefix'=>'ltds/fedex', 'as'=>'ltds.fedex.'], function(){
                Route::group(['prefix'=>'servicios/{servicios}', 'as'=>'servicios.'], function(){

                    Route::controller(FedexController::class)->group(function(){
                        Route::post('guia/{formatoImpresion?}', 'creacion')->name("guias");


                    });
                });
            });


        });

    });
    });
});

Route::middleware(['zigo.portal:api', 'zigo.api'])->prefix('hub')->group(function () {
    Route::get('/ping', function (Request $request) {
        return response()->json([
            'success' => true,
            'message' => 'ZIGO API funcionando correctamente',
            'client' => $request->attributes->get('api_client')->name,
            'environment' => $request->attributes->get('api_key')->environment,
            'timestamp' => now()->toDateTimeString(),
        ]);
    });

    Route::get('/cp/{codigoPostal}', [PostalCodeController::class, 'show'])
        ->middleware('zigo.product:POSTAL_CODES');

    Route::middleware('zigo.product:BILLING')
        ->prefix('v1/billing')
        ->name('api.hub.billing.')
        ->group(function () {
            Route::post(
                '/invoices',
                [BillingInvoiceController::class, 'store']
            )->name('invoices.store');

            Route::get(
                '/invoices/{externalId}',
                [BillingInvoiceController::class, 'show']
            )->where(
                'externalId',
                '[A-Za-z0-9][A-Za-z0-9._:-]*'
            )->name('invoices.show');

            Route::get(
                '/invoices/{externalId}/documents/{format}',
                [BillingInvoiceController::class, 'document']
            )
                ->where(
                    'externalId',
                    '[A-Za-z0-9][A-Za-z0-9._:-]*'
                )
                ->whereIn('format', ['pdf', 'xml', 'zip'])
                ->name('invoices.documents');
        });
});

Route::domain(config('zigo_api_hub.host'))->prefix('hub/v1')->name('api.hub.v1.')
    ->middleware(['zigo.api.v1.request','zigo.api.v1.auth','zigo.api.v1.meter'])
    ->group(function (): void {
        Route::get('/postal-codes/{postalCode}', [V1PostalController::class, 'show'])->middleware('zigo.api.v1.scope:postal:read')->where('postalCode','[0-9]{5}')->name('postal.show');
        Route::post('/quotes', [V1QuoteController::class, 'store'])->middleware('zigo.api.v1.scope:quotes:write')->name('quotes.store');
        Route::post('/shipments', [V1ShipmentController::class, 'store'])->middleware('zigo.api.v1.scope:shipments:write')->name('shipments.store');
        Route::get('/shipments/{shipment}', [V1ShipmentController::class, 'show'])->middleware('zigo.api.v1.scope:shipments:read')->name('shipments.show');
        Route::get('/shipments/{shipment}/guide', [V1ShipmentController::class, 'guide'])->middleware('zigo.api.v1.scope:guides:read')->name('shipments.guide');
        Route::get('/tracking/{tracking}', [V1TrackingController::class, 'show'])->middleware('zigo.api.v1.scope:tracking:read')->name('tracking.show');
        Route::post('/shipments/{shipment}/pickup', [V1ShipmentController::class, 'pickup'])->middleware('zigo.api.v1.scope:pickups:write')->name('shipments.pickup');
    });

if (app()->environment('local') || app()->environment('testing')) {
    Route::post('/hub/testing/webhook-receiver', function (Request $request) {
        return response()->json([
            'received' => true,
            'event' => $request->header('X-ZIGO-Event'),
            'delivery_id' =>
                $request->header('X-ZIGO-Delivery-ID'),
            'signature_present' =>
                $request->hasHeader('X-ZIGO-Signature'),
        ]);
    })->name('api.hub.testing.webhook-receiver');
}
