<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\LtdController;
use App\Http\Controllers\B2C\CotizacionPublicaController;
use App\Http\Controllers\API\CPController;
use App\Http\Controllers\B2cMisEnviosController;
use App\Http\Controllers\Admin\B2cIncidenciaAdminController;



/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/


Route::get('/', function () {
    return view('index');
});

Route::resource('profile','userProfileController');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth'])->name('dashboard');

Route::post('/b2c/cotizacion/{cotizacion}/seleccionar', [CotizacionPublicaController::class, 'seleccionar'])
    ->name('b2c.seleccionar');
	
Route::get('/b2c/cp/colonias', [CPController::class, 'colonias'])
    ->name('b2c.cp.colonias');
		
/*provisional pruebaa */
Route::get('/b2c/checkout/{cotizacion}', [CotizacionPublicaController::class, 'checkout'])
    ->name('b2c.checkout');
	
Route::post('/b2c/checkout/{cotizacion}', [CotizacionPublicaController::class, 'procesarCheckout'])
    ->name('b2c.checkout.procesar');

/*provisional pago */
Route::get('/b2c/pago/{cotizacion}', [CotizacionPublicaController::class, 'pago'])
    ->name('b2c.pago');
	
Route::get('/b2c/pago/{cotizacion}/success', [CotizacionPublicaController::class, 'pagoSuccess'])
    ->name('b2c.pago.success');

Route::get('/b2c/pago/{cotizacion}/failure', [CotizacionPublicaController::class, 'pagoFailure'])
    ->name('b2c.pago.failure');

Route::get('/b2c/pago/{cotizacion}/pending', [CotizacionPublicaController::class, 'pagoPending'])
    ->name('b2c.pago.pending');
	
Route::post('/b2c/guia/{cotizacion}/generar', [CotizacionPublicaController::class, 'generarGuia'])
    ->name('b2c.guia.generar');

//Opciones de Rastreo
Route::get('/rastreo', [CotizacionPublicaController::class, 'rastreoPublico'])
    ->name('b2c.rastreo');

Route::post('/rastreo', [CotizacionPublicaController::class, 'buscarRastreoPublico'])
    ->name('b2c.rastreo.buscar');

//Registro b2c
Route::get('/b2c/register', [CotizacionPublicaController::class, 'registroB2c'])
    ->name('b2c.register');

Route::post('/b2c/register', [CotizacionPublicaController::class, 'guardarRegistroB2c'])
    ->name('b2c.register.store');

// Dashboard B2C
Route::get('/b2c/dashboard', [CotizacionPublicaController::class, 'dashboardB2c'])
    ->middleware('auth')
    ->name('b2c.dashboard');

//Mis Envios 
Route::middleware(['auth'])->group(function () {

    Route::get('/b2c/mis-envios', [CotizacionPublicaController::class, 'misEnviosB2c'])
        ->name('b2c.mis-envios');

//Ver Detalle
Route::get('/b2c/envios/{cotizacion}', [CotizacionPublicaController::class, 'detalleEnvioB2c'])
    ->middleware('auth')
    ->name('b2c.envios.detalle');

//Mis Pagos
Route::get('/b2c/mis-pagos', [CotizacionPublicaController::class, 'misPagosB2c'])
    ->middleware('auth')
    ->name('b2c.mis-pagos');

//Nuevo envio
Route::get('/b2c/nuevo-envio', [CotizacionPublicaController::class, 'nuevoEnvioB2c'])
    ->name('b2c.nuevo-envio');

Route::post('/b2c/nuevo-envio', [CotizacionPublicaController::class, 'guardarNuevoEnvioB2c'])
    ->name('b2c.nuevo-envio.guardar');

Route::get('/b2c/paquete/{cotizacion}', [CotizacionPublicaController::class, 'paqueteB2c'])
    ->middleware('auth')
    ->name('b2c.paquete');

//Direcciones
Route::get('/b2c/mis-direcciones', [CotizacionPublicaController::class, 'misDireccionesB2c'])
    ->middleware('auth')
    ->name('b2c.mis-direcciones');

Route::post('/b2c/mis-direcciones', [CotizacionPublicaController::class, 'guardarDireccionB2c'])
    ->middleware('auth')
    ->name('b2c.mis-direcciones.guardar');

// Direcciones -- eliminar
Route::post('/b2c/mis-direcciones/{direccion}/eliminar', [CotizacionPublicaController::class, 'eliminarDireccionB2c'])
    ->name('b2c.mis-direcciones.eliminar');

// Saldo / Prepago
Route::get('/b2c/prepago', [B2cMisEnviosController::class, 'prepago'])
    ->name('b2c.prepago');

Route::post('/b2c/prepago/recargar', [B2cMisEnviosController::class, 'crearRecarga'])
    ->name('b2c.prepago.recargar');

Route::get('/b2c/prepago/{recarga}/success', [B2cMisEnviosController::class, 'recargaSuccess'])
    ->name('b2c.prepago.success');

Route::get('/b2c/prepago/{recarga}/failure', [B2cMisEnviosController::class, 'recargaFailure'])
    ->name('b2c.prepago.failure');

Route::get('/b2c/prepago/{recarga}/pending', [B2cMisEnviosController::class, 'recargaPending'])
    ->name('b2c.prepago.pending');

Route::post('/b2c/pago/{cotizacion}/saldo', [CotizacionPublicaController::class, 'pagarConSaldo'])
    ->name('b2c.pago.saldo');

//Configuracion
Route::get('/b2c/configuracion', [CotizacionPublicaController::class, 'configuracionB2c'])
    ->name('b2c.configuracion');

Route::post('/b2c/configuracion/identidad', [CotizacionPublicaController::class, 'guardarIdentidadB2c'])
    ->name('b2c.configuracion.identidad.guardar');

//Incidencias
Route::get('/b2c/incidencias', [CotizacionPublicaController::class, 'incidenciasB2c'])
    ->name('b2c.incidencias');

Route::post('/b2c/incidencias', [CotizacionPublicaController::class, 'guardarIncidenciaB2c'])
    ->name('b2c.incidencias.guardar');

});

// Admin - Incidencias B2C
Route::middleware(['auth', 'roles:sysadmin,admin,adminops,operaciones'])
    ->prefix('admin')
    ->group(function () {

        Route::get('/incidencias', [B2cIncidenciaAdminController::class, 'index'])
            ->name('admin.incidencias.index');

        Route::get('/incidencias/{incidencia}', [B2cIncidenciaAdminController::class, 'show'])
            ->name('admin.incidencias.show');

        Route::post('/incidencias/{incidencia}/responder', [B2cIncidenciaAdminController::class, 'responder'])
            ->name('admin.incidencias.responder');
    });

Route::match(['GET', 'POST'], '/b2c/prepago/webhook', [B2cMisEnviosController::class, 'recargaWebhook'])
    ->name('b2c.prepago.webhook');

//Cotizar b2c
Route::post('/b2c/cotizar', [CotizacionPublicaController::class, 'cotizar'])
    ->name('b2c.cotizar');


/*
|Los roles definidos son 
|- sysadmin
|- admin
|- contraloria
|- auditoria
|- comercial
|- adminops
|- operaciones
|- cliente 
|- usuario
*/
//Menu SysAdmin
Route::resource('mensajerias','CfgLtdController')
    ->middleware(['roles:sysadmin,admin']);
Route::resource('guiaretorno','GuiaRetornoController')
    ->middleware(['roles:sysadmin,admin']);

//Menu Clientes
Route::resource('empresas','EmpresaController')
    ->middleware(['roles:sysadmin,admin,contraloria,comercial,adminops,operaciones']);
Route::resource('tarifas','TarifaController')
    ->middleware(['roles:sysadmin,admin,contraloria,comercial,adminops,operaciones']);

//Menu Direcciones
Route::resource('clientes','ClienteController')
    ->middleware(['roles:sysadmin,admin,comercial,adminops,operaciones,cliente']);
Route::resource('sucursales','SucursalController')
    ->middleware(['roles:sysadmin,admin,comercial,adminops,operaciones,cliente']);

//Menu Proveedores
Route::resource('ltds','LtdController')
    ->middleware(['roles:sysadmin,admin,auditoria,comercial']);
Route::resource('coberturas','CoberturasController')
    ->middleware(['roles:sysadmin,admin,auditoria,comercial']);

//Menu Usuario
Route::resource('users','Roles\UsersController')
    ->middleware(['roles:sysadmin,admin,comercial,adminops,cliente']);

//Menu guia
Route::resource('guia','GuiaController')
    ->middleware(['roles:sysadmin,admin,adminops,operaciones,cliente,usuario']);
Route::resource('cotizaciones','CotizadorController')
    ->middleware(['roles:sysadmin,admin,adminops,operaciones,cliente,usuario']);
Route::resource('rastreos','RastreosController')
    ->middleware(['roles:sysadmin,admin,adminops,operaciones,cliente,usuario']);

Route::group(['as'=>'guias.'  ,'prefix'=>'guias'],function(){
    Route::resource('masivas','Guias\MasivasController')
        ->middleware(['roles:sysadmin,admin,contraloria,adminops,operaciones,cliente,auditoria,usuario']); 
});


//Menu Roles
Route::resource('roles','Roles\RolesController')
    ->middleware(['roles:sysadmin,admin']);

//Menu Reportes
Route::resource('reportes/ventas','ReportesController')
    ->middleware(['roles:sysadmin,admin,contraloria,adminops,operaciones,auditoria,cliente']);

Route::resource('reportes/repesajes','Reportes\RepesajeController')
    ->middleware(['roles:sysadmin,admin,contraloria,adminops,operaciones,auditoria,cliente']);

Route::group(['as'=>'reportes.'  ,'prefix'=>'reportes'],function(){
    Route::resource('pagado','Reportes\PagosController')
        ->middleware(['roles:sysadmin,admin,contraloria,adminops,operaciones,auditoria']); 
});

//Menu Saldos
Route::resource('saldos/pagos','Saldos\PagosController')
    ->middleware(['roles:sysadmin,admin,contraloria,adminops,operaciones']);

Route::resource('saldos/ajustes','Saldos\AjustesController')
    ->middleware(['roles:admin,contraloria,adminops,operaciones']);

Route::resource('saldos/externas','Saldos\GuiasExternasController')
    ->middleware(['roles:admin,contraloria,adminops,operaciones']);


require __DIR__.'/auth.php';
