<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\LtdController;
use App\Http\Controllers\B2C\CotizacionPublicaController;
use App\Http\Controllers\API\CPController;
use App\Http\Controllers\B2cMisEnviosController;
use App\Http\Controllers\Admin\B2cIncidenciaAdminController;
use App\Http\Controllers\CRM\CrmClientController;
use App\Http\Controllers\Web\PostalCodeLookupController;
use App\Http\Controllers\Web\LandingProspectController;
use App\Http\Controllers\Web\WaitlistController;
use App\Http\Controllers\CRM\CrmPricingController;
use App\Models\B2cCotizacion;

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


Route::get('/', [CotizacionPublicaController::class, 'index'])->name('home');

Route::get('/limpiar-cotizacion', [CotizacionPublicaController::class, 'limpiarCotizacion'])
    ->name('landing.cotizacion.limpiar');

Route::resource('profile','userProfileController');

Route::get('/dashboard', function () {
    return redirect()->route('b2c.dashboard');
})->middleware(['auth'])->name('dashboard');

Route::post('/b2c/cotizacion/{cotizacion}/seleccionar', [CotizacionPublicaController::class, 'seleccionar'])
    ->name('b2c.seleccionar');

Route::post('/b2c/cotizacion/{cotizacion}/seleccionar-nuevo', [CotizacionPublicaController::class, 'seleccionarNuevoEnvio'])
    ->name('b2c.seleccionar.nuevo');

Route::get(
    '/b2c/confirmar/{cotizacion}',
    [CotizacionPublicaController::class, 'confirmarEnvio']
)
    ->middleware('auth')
    ->name('b2c.confirmar');

Route::post(
    '/b2c/confirmar/{cotizacion}',
    [CotizacionPublicaController::class, 'procesarConfirmacion']
)
    ->middleware('auth')
    ->name('b2c.confirmar.procesar');
			
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

    //Cotizador b2c logueado en zigo
    Route::post('/b2c/cotizador-rapido', [
        CotizacionPublicaController::class,
        'cotizadorRapidoB2c'
    ])->name('b2c.cotizador-rapido');

    Route::get(
    '/b2c/cotizador-rapido/limpiar',
    [
        CotizacionPublicaController::class,
        'limpiarCotizadorRapidoB2c'
    ]
    )->name('b2c.cotizador-rapido.limpiar');

    Route::get('/b2c/mis-envios', [CotizacionPublicaController::class, 'misEnviosB2c'])
        ->name('b2c.mis-envios');

//Ver Detalle
Route::get('/b2c/envios/{cotizacion}', [CotizacionPublicaController::class, 'detalleEnvioB2c'])
    ->middleware('auth')
    ->name('b2c.envios.detalle');

Route::post('/b2c/envios/{cotizacion}/duplicar', [CotizacionPublicaController::class, 'duplicarEnvioB2c'])
    ->name('b2c.envios.duplicar');

Route::post('/b2c/envios/{cotizacion}/eliminar', [CotizacionPublicaController::class, 'eliminarCotizacionB2c'])
    ->name('b2c.envios.eliminar');

//Facturar
Route::post(
    '/b2c/envios/{cotizacion}/facturar',
    [
        CotizacionPublicaController::class,
        'solicitarFacturaB2c',
    ]
)
    ->name('b2c.envios.facturar');

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

Route::post(
    '/b2c/configuracion/datos-fiscales',
    [
        CotizacionPublicaController::class,
        'guardarDatosFiscalesB2c',
    ]
)
    ->middleware('auth')
    ->name(
        'b2c.configuracion.fiscal.guardar'
    );

//Incidencias
Route::get('/b2c/incidencias', [CotizacionPublicaController::class, 'incidenciasB2c'])
    ->name('b2c.incidencias');

Route::post('/b2c/incidencias', [CotizacionPublicaController::class, 'guardarIncidenciaB2c'])
    ->name('b2c.incidencias.guardar');

//nuevo envio -- paquete
Route::post('/b2c/paquete/{cotizacion}', [CotizacionPublicaController::class, 'guardarPaqueteB2c'])
    ->middleware('auth')
    ->name('b2c.paquete.guardar');

    Route::get('/b2c/cotizacion/{cotizacion}/opciones', [CotizacionPublicaController::class, 'opcionesB2c'])
    ->name('b2c.opciones');

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

//LOGIN SOPORTE
Route::get('/soporte/login', [B2cIncidenciaAdminController::class, 'login'])
    ->name('soporte.login');

Route::post('/soporte/login', [B2cIncidenciaAdminController::class, 'loginPost'])
    ->name('soporte.login.post');

Route::post('/soporte/logout', [B2cIncidenciaAdminController::class, 'logoutSoporte'])
            ->name('soporte.logout');

// Portal Soporte
Route::middleware(['auth', 'roles:sysadmin,admin,adminops,operaciones'])
    ->prefix('soporte')
    ->name('soporte.')
    ->group(function () {

        Route::get('/dashboard', [B2cIncidenciaAdminController::class, 'dashboard'])
            ->name('dashboard');

        Route::get('/incidencias', [B2cIncidenciaAdminController::class, 'indexSoporte'])
            ->name('incidencias.index');

        Route::get('/incidencias/{incidencia}', [B2cIncidenciaAdminController::class, 'showSoporte'])
            ->name('incidencias.show');

        Route::post('/incidencias/{incidencia}/responder', [B2cIncidenciaAdminController::class, 'responder'])
            ->name('incidencias.responder');

    });

// ===============================
// PORTAL NEGOCIOS / EMPRESAS B2B
// ===============================
Route::get('/negocios/login', [B2cIncidenciaAdminController::class, 'loginNegocios'])
    ->name('negocios.login');

Route::post('/negocios/login', [B2cIncidenciaAdminController::class, 'loginNegociosPost'])
    ->name('negocios.login.post');

Route::post('/negocios/logout', [B2cIncidenciaAdminController::class, 'logoutNegocios'])
    ->name('negocios.logout');

Route::middleware(['auth', 'roles:sysadmin,admin,adminops,operaciones,cliente'])
    ->prefix('negocios')
    ->name('negocios.')
    ->group(function () {
        Route::get('/dashboard', function () {
            return view('negocios.dashboard');
        })->name('dashboard');
    });


// ===============================
// PORTAL CRM / ADMIN GENERAL
// ===============================
Route::get('/crm/login', [B2cIncidenciaAdminController::class, 'loginCrm'])
    ->name('crm.login');

Route::post('/crm/login', [B2cIncidenciaAdminController::class, 'loginCrmPost'])
    ->name('crm.login.post');

Route::post('/crm/logout', [B2cIncidenciaAdminController::class, 'logoutCrm'])
    ->name('crm.logout');

Route::middleware(['auth', 'roles:sysadmin,admin'])
    ->prefix('crm')
    ->name('crm.')
    ->group(function () {
        Route::get('/dashboard', function () {
            return view('crm.dashboard');
        })->name('dashboard');

        Route::get('/seguridad', [\App\Http\Controllers\CRM\CrmSecurityController::class, 'index'])
            ->name('seguridad.index');

        Route::get('/seguridad/usuarios', [\App\Http\Controllers\CRM\CrmSecurityController::class, 'usuarios'])
            ->name('seguridad.usuarios');

        Route::get('/seguridad/roles', [\App\Http\Controllers\CRM\CrmSecurityController::class, 'roles'])
            ->name('seguridad.roles');

        Route::get('/seguridad/permisos', [\App\Http\Controllers\CRM\CrmSecurityController::class, 'permisos'])
            ->name('seguridad.permisos');

        Route::get('/seguridad/usuarios/crear', [\App\Http\Controllers\CRM\CrmSecurityController::class, 'crearUsuario'])
            ->name('seguridad.usuarios.crear');

        Route::post('/seguridad/usuarios', [\App\Http\Controllers\CRM\CrmSecurityController::class, 'guardarUsuario'])
            ->name('seguridad.usuarios.guardar');

        Route::get('/seguridad/usuarios/{user}/editar', [\App\Http\Controllers\CRM\CrmSecurityController::class, 'editarUsuario'])
            ->name('seguridad.usuarios.editar');

        Route::post('/seguridad/usuarios/{user}/actualizar', [\App\Http\Controllers\CRM\CrmSecurityController::class, 'actualizarUsuario'])
            ->name('seguridad.usuarios.actualizar');

        Route::post('/seguridad/usuarios/{user}/eliminar', [\App\Http\Controllers\CRM\CrmSecurityController::class, 'eliminarUsuario'])
            ->name('seguridad.usuarios.eliminar');

        Route::get('/seguridad/roles/crear', [\App\Http\Controllers\CRM\CrmSecurityController::class, 'crearRol'])
            ->name('seguridad.roles.crear');

        Route::post('/seguridad/roles', [\App\Http\Controllers\CRM\CrmSecurityController::class, 'guardarRol'])
            ->name('seguridad.roles.guardar');

        Route::get('/seguridad/roles/{id}/editar', [\App\Http\Controllers\CRM\CrmSecurityController::class, 'editarRol'])
            ->name('seguridad.roles.editar');

        Route::post('/seguridad/roles/{id}/actualizar', [\App\Http\Controllers\CRM\CrmSecurityController::class, 'actualizarRol'])
            ->name('seguridad.roles.actualizar');

        Route::post('/seguridad/roles/{id}/eliminar', [\App\Http\Controllers\CRM\CrmSecurityController::class, 'eliminarRol'])
            ->name('seguridad.roles.eliminar');

        Route::get('/seguridad/roles/{id}/permisos', [\App\Http\Controllers\CRM\CrmSecurityController::class, 'permisosRol'])
            ->name('seguridad.roles.permisos');

        Route::post('/seguridad/roles/{id}/permisos', [\App\Http\Controllers\CRM\CrmSecurityController::class, 'guardarPermisosRol'])
            ->name('seguridad.roles.permisos.guardar');

        Route::get('/seguridad/permisos/crear', [\App\Http\Controllers\CRM\CrmSecurityController::class, 'crearPermiso'])
            ->name('seguridad.permisos.crear');

        Route::post('/seguridad/permisos', [\App\Http\Controllers\CRM\CrmSecurityController::class, 'guardarPermiso'])
            ->name('seguridad.permisos.guardar');

        Route::get('/seguridad/permisos/{id}/editar', [\App\Http\Controllers\CRM\CrmSecurityController::class, 'editarPermiso'])
            ->name('seguridad.permisos.editar');

        Route::post('/seguridad/permisos/{id}/actualizar', [\App\Http\Controllers\CRM\CrmSecurityController::class, 'actualizarPermiso'])
            ->name('seguridad.permisos.actualizar');

        Route::post('/seguridad/permisos/{id}/eliminar', [\App\Http\Controllers\CRM\CrmSecurityController::class, 'eliminarPermiso'])
            ->name('seguridad.permisos.eliminar');

        Route::get('/api-hub', [\App\Http\Controllers\CRM\CrmApiHubController::class, 'index'])
            ->name('api-hub.index');

        Route::resource('clientes', \App\Http\Controllers\CRM\CrmClientController::class)
            ->except(['show'])
            ->names('clientes')
            ->parameters(['clientes' => 'cliente']);
        
        Route::get('/api-hub/{apiClient}', [\App\Http\Controllers\CRM\CrmApiHubController::class, 'show'])
            ->name('api-hub.show');

        Route::post('/api-hub/{apiClient}/plan', [\App\Http\Controllers\CRM\CrmApiHubController::class, 'updatePlan'])
            ->name('api-hub.update-plan');

        Route::post('/api-hub/{apiClient}/toggle-active', [\App\Http\Controllers\CRM\CrmApiHubController::class, 'toggleActive'])
            ->name('api-hub.toggle-active');

        Route::post('/api-hub/{apiClient}/keys', [\App\Http\Controllers\CRM\CrmApiHubController::class, 'createApiKey'])
            ->name('api-hub.keys.create');

        Route::post('/api-hub/{apiClient}/keys/{apiKey}/toggle', [\App\Http\Controllers\CRM\CrmApiHubController::class, 'toggleApiKey'])
            ->name('api-hub.keys.toggle');

        Route::post('/clientes/{cliente}/seguimiento', [CrmClientController::class, 'actualizarSeguimiento'])
            ->name('clientes.seguimiento');
            
        Route::get('/pricing', [CrmPricingController::class, 'index'])
            ->name('pricing.index');

        Route::post('/pricing/simulate', [CrmPricingController::class, 'simulate'])
            ->name('pricing.simulate');

        Route::post('/pricing/rules/{rule}/toggle', [CrmPricingController::class, 'toggleRule'])
            ->name('pricing.rules.toggle');

        Route::post('/pricing/adjustments/{adjustment}/toggle', [CrmPricingController::class, 'toggleAdjustment'])
            ->name('pricing.adjustments.toggle');

        Route::post('/pricing/client-rules/{clientRule}/toggle', [CrmPricingController::class, 'toggleClientRule'])
            ->name('pricing.client-rules.toggle');

        Route::post('/pricing/rules', [CrmPricingController::class, 'storeRule'])
            ->name('pricing.rules.store');

        Route::post('/pricing/adjustments', [CrmPricingController::class, 'storeAdjustment'])
            ->name('pricing.adjustments.store');

        Route::post('/pricing/client-rules', [CrmPricingController::class, 'storeClientRule'])
            ->name('pricing.client-rules.store');

    });


// ===============================
// API HUB
// ===============================
Route::get('/hub/login', [B2cIncidenciaAdminController::class, 'loginHub'])
    ->name('hub.login');

Route::post('/hub/login', [B2cIncidenciaAdminController::class, 'loginHubPost'])
    ->name('hub.login.post');

Route::post('/hub/logout', [B2cIncidenciaAdminController::class, 'logoutHub'])
    ->name('hub.logout');

Route::middleware(['auth', 'roles:sysadmin,admin,adminops,cliente'])
    ->prefix('hub')
    ->name('hub.')
    ->group(function () {
        Route::get('/dashboard', function () {
            return view('hub.dashboard');
        })->name('dashboard');
    });
// ===============================   


// ===============================
// RUTAS PÚBLICAS / UTILIDADES B2C
// ===============================
Route::get('/postal-code/lookup/{codigoPostal}', [PostalCodeLookupController::class, 'show'])
    ->middleware('throttle:60,1')
    ->name('postal-code.lookup');

Route::get('/b2c/cp/colonias', [PostalCodeLookupController::class, 'colonias'])
    ->middleware('throttle:60,1')
    ->name('b2c.cp.colonias');

Route::view('/nosotros', 'public.nosotros')->name('public.nosotros');
Route::view('/paqueteria', 'public.paqueteria')->name('public.paqueteria');
Route::view('/faqs', 'public.faqs')->name('public.faqs');

Route::view('/aviso-privacidad', 'legal.aviso-privacidad')
    ->name('legal.aviso-privacidad');

Route::view('/terminos-condiciones', 'legal.terminos')
    ->name('legal.terminos');

Route::view('/politica-envios', 'legal.politica-envios')
    ->name('legal.politica-envios');

Route::view('/api-hub', 'web.api-hub')
    ->name('web.api-hub');

Route::view('/soporte', 'web.soporte')
    ->name('web.soporte');

Route::view('/api-hub', 'public.api-hub')->name('public.api-hub');

Route::view('/soporte', 'public.soporte')->name('public.soporte');

Route::get('/b2c/checkout/{cotizacion}', [CotizacionPublicaController::class, 'checkout'])
    ->name('b2c.checkout');

Route::post('/b2c/checkout/{cotizacion}', [CotizacionPublicaController::class, 'procesarCheckout'])
    ->name('b2c.checkout.procesar');

//LANDING PARA PROXIMAMENTE 
Route::get('/', [WaitlistController::class, 'index'])
    ->name('home');

Route::get('/proximamente', [WaitlistController::class, 'index'])
    ->name('waitlist.index');

Route::post('/proximamente/registro', [WaitlistController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('waitlist.store');

Route::get('/portal-zigo', function () {
    return view('index');
})->name('portal.original');

// ===============================
// LANDING / PROSPECTOS
// ===============================
Route::get('/soluciones', [LandingProspectController::class, 'empresas'])
    ->name('landing.empresas');

Route::post('/soluciones/solicitud', [LandingProspectController::class, 'storeB2B'])
    ->name('landing.empresas.store');

// aquí siguen las  rutas públicas: index, login, registro, cotización pública, etc.

Route::match(['GET', 'POST'], '/b2c/prepago/webhook', [B2cMisEnviosController::class, 'recargaWebhook'])
    ->name('b2c.prepago.webhook');

//Cotizar b2c
Route::get('/b2c/cotizar', function () {
    return redirect()->to(url('/') . '#cotizar');
})->name('b2c.cotizar.get');

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
