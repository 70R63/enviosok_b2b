<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\LtdController;
use App\Http\Controllers\B2C\CotizacionPublicaController;
use App\Http\Controllers\B2C\B2cInvoiceDocumentController;
use App\Http\Controllers\B2C\B2cAdeudoController;
use App\Http\Controllers\B2C\GuestGuideRecoveryController;
use App\Http\Controllers\B2C\B2cInvoiceCorrectionController;
use App\Http\Controllers\API\CPController;
use App\Http\Controllers\B2cMisEnviosController;
use App\Http\Controllers\Admin\B2cIncidenciaAdminController;
use App\Http\Controllers\CRM\CrmClientController;
use App\Http\Controllers\Web\PostalCodeLookupController;
use App\Http\Controllers\Web\LandingProspectController;
use App\Http\Controllers\Web\WaitlistController;
use App\Http\Controllers\CRM\CrmPricingController;
use App\Http\Controllers\CRM\CrmInvoiceRequestController;
use App\Http\Controllers\CRM\CrmInvoiceDocumentController;
use App\Http\Controllers\CRM\CrmGuideController;
use App\Http\Controllers\CRM\CrmGuideRecoveryController;
use App\Http\Controllers\CRM\CrmGuideExportController;
use App\Http\Controllers\CRM\CrmNotificationController;
use App\Http\Controllers\CRM\CrmPaymentExportController;
use App\Http\Controllers\CRM\CrmDebtController;
use App\Http\Controllers\CRM\CrmShippingProviderController;
use App\Http\Controllers\CRM\CrmIdentityVerificationController;
use App\Http\Controllers\CRM\CrmPublicChannelController;
use App\Http\Controllers\CRM\CrmPaymentController;
use App\Http\Controllers\CRM\CrmIncidentController;
use App\Http\Controllers\CRM\CrmCompanyController;
use App\Http\Controllers\CRM\CrmDevOpsController;
use App\Http\Controllers\DevOps\DevOpsAuthController;
use App\Http\Controllers\DevOps\XpertaIntegrationController;
use App\Models\B2cCotizacion;
use App\Http\Controllers\Onboarding\ZigoPlatformController;
use App\Http\Controllers\Webchat\HostedWebchatController;

Route::get('/ai/webchat/widget.js', [HostedWebchatController::class,'widget'])->name('ai.webchat.widget');
Route::get('/chat/{publicKey}', [HostedWebchatController::class,'show'])->where('publicKey','wc_[A-Za-z0-9_-]{43}')->name('ai.webchat.hosted');

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

Route::middleware('zigo.corporate.host')->prefix('zigo-platform')->name('zigo-platform.')->group(function (): void {
    Route::get('/', [ZigoPlatformController::class, 'landing'])->name('landing');
    Route::get('/precios', [ZigoPlatformController::class, 'pricing'])->name('pricing');
    Route::get('/comenzar', [ZigoPlatformController::class, 'start'])->name('start');
    Route::post('/comenzar', [ZigoPlatformController::class, 'storeStart'])->middleware('throttle:8,1')->name('start.store');
    Route::get('/solicitud/{token}/solucion', [ZigoPlatformController::class, 'solution'])->name('onboarding.solution');
    Route::patch('/solicitud/{token}/solucion', [ZigoPlatformController::class, 'storeSolution'])->middleware('throttle:12,1')->name('onboarding.solution.store');
    Route::get('/solicitud/{token}/plataforma', [ZigoPlatformController::class, 'platform'])->name('onboarding.platform');
    Route::patch('/solicitud/{token}/plataforma', [ZigoPlatformController::class, 'storePlatform'])->middleware('throttle:12,1')->name('onboarding.platform.store');
    Route::get('/solicitud/{token}/resumen', [ZigoPlatformController::class, 'summary'])->name('onboarding.summary');
    Route::post('/solicitud/{token}/checkout', [ZigoPlatformController::class, 'checkout'])->middleware('throttle:onboarding-checkout')->name('onboarding.checkout');
    Route::get('/solicitud/{token}/retorno/{result}', [ZigoPlatformController::class, 'returned'])->middleware('throttle:20,1')->name('onboarding.return');
});


Route::domain(config('zigo_domains.portals.b2c.host'))
    ->group(function () {
        Route::get('/', [CotizacionPublicaController::class, 'index'])
            ->name('home');

        Route::get('/limpiar-cotizacion', [
            CotizacionPublicaController::class,
            'limpiarCotizacion',
        ])->name('landing.cotizacion.limpiar');
    });

Route::resource('profile','userProfileController');

Route::get('/dashboard', function () {
    return redirect()->route('b2c.dashboard');
})->middleware(['auth'])->name('dashboard');

Route::get('/envio/recuperar', [GuestGuideRecoveryController::class, 'requestForm'])->name('guide-recovery.form');
Route::post('/envio/recuperar', [GuestGuideRecoveryController::class, 'requestLink'])->middleware('throttle:5,1')->name('guide-recovery.request');
Route::get('/envio/recuperar/{token}', [GuestGuideRecoveryController::class, 'show'])->middleware('throttle:20,1')->name('guide-recovery.show');
Route::get('/envio/recuperar/{token}/pdf', [GuestGuideRecoveryController::class, 'download'])->middleware('throttle:10,1')->name('guide-recovery.download');

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
    ->middleware(['auth', 'throttle:6,1'])
    ->name('b2c.guia.generar');

Route::get('/b2c/guia/{cotizacion}/etiqueta', [CotizacionPublicaController::class, 'descargarEtiquetaB2c'])
    ->middleware(['throttle:12,1'])
    ->name('b2c.guia.etiqueta');

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

Route::get('/b2c/envios/{cotizacion}/retomar', [CotizacionPublicaController::class, 'retomarEnvioB2c'])
    ->name('b2c.envios.retomar');

Route::get('/b2c/envios/{cotizacion}/editar', [CotizacionPublicaController::class, 'editarEnvioB2c'])
    ->name('b2c.envios.editar');

Route::put('/b2c/envios/{cotizacion}', [CotizacionPublicaController::class, 'actualizarEnvioB2c'])
    ->name('b2c.envios.actualizar');

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

Route::get(
    '/b2c/facturas/{invoiceRequest}/documentos/{format}',
    [B2cInvoiceDocumentController::class, 'download']
)
    ->whereNumber('invoiceRequest')
    ->where('format', 'pdf|xml|zip')
    ->name('b2c.facturas.documentos.download');

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

// Adeudos B2C
Route::get('/b2c/adeudos', [B2cAdeudoController::class, 'index'])
    ->name('b2c.adeudos.index');

Route::post('/b2c/adeudos/{adeudo}/saldo', [B2cAdeudoController::class, 'payWithBalance'])
    ->middleware('throttle:6,1')
    ->name('b2c.adeudos.saldo');

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
Route::get('/b2c/incidencias/{incidencia}', [CotizacionPublicaController::class, 'showIncidenciaB2c'])->name('b2c.incidencias.show');
Route::post('/b2c/incidencias/{incidencia}/comentarios', [CotizacionPublicaController::class, 'commentIncidenciaB2c'])->name('b2c.incidencias.comment');
Route::get('/b2c/incidencias/{incidencia}/evidencia', [CotizacionPublicaController::class, 'evidenceIncidenciaB2c'])->name('b2c.incidencias.evidence');

//nuevo envio -- paquete
Route::post('/b2c/paquete/{cotizacion}', [CotizacionPublicaController::class, 'guardarPaqueteB2c'])
    ->middleware('auth')
    ->name('b2c.paquete.guardar');

    Route::get('/b2c/cotizacion/{cotizacion}/opciones', [CotizacionPublicaController::class, 'opcionesB2c'])
    ->name('b2c.opciones');

});

// Admin - Incidencias B2C
Route::middleware(['zigo.portal:b2c,strict', 'auth', 'roles:sysadmin,admin,adminops,operaciones'])
    ->prefix('admin')
    ->group(function () {

        Route::get('/incidencias', [B2cIncidenciaAdminController::class, 'index'])
            ->name('admin.incidencias.index');

        Route::get('/incidencias/{incidencia}', [B2cIncidenciaAdminController::class, 'show'])
            ->name('admin.incidencias.show');

        Route::post('/incidencias/{incidencia}/responder', [B2cIncidenciaAdminController::class, 'responder'])
            ->name('admin.incidencias.responder');

        Route::get('/conciliacion-saldo/{cotizacion}', [B2cIncidenciaAdminController::class, 'showBalanceReconciliation'])
            ->name('admin.conciliacion.show');

        Route::post('/conciliacion-saldo/{cotizacion}/reversar', [B2cIncidenciaAdminController::class, 'reverseBalance'])
            ->middleware('throttle:6,1')
            ->name('admin.conciliacion.reverse');
    });

//LOGIN SOPORTE
Route::get('/soporte/login', [B2cIncidenciaAdminController::class, 'login'])
    ->middleware('zigo.portal:support')
    ->name('soporte.login');

Route::post('/soporte/login', [B2cIncidenciaAdminController::class, 'loginPost'])
    ->middleware('zigo.portal:support')
    ->name('soporte.login.post');

Route::post('/soporte/logout', [B2cIncidenciaAdminController::class, 'logoutSoporte'])
    ->middleware('zigo.portal:support')
    ->name('soporte.logout');

// Portal Soporte
Route::middleware(['zigo.portal:support', 'auth', 'roles:sysadmin,admin,adminops,operaciones,soporte'])
    ->prefix('soporte')
    ->name('soporte.')
    ->group(function () {

        Route::get('/dashboard', [B2cIncidenciaAdminController::class, 'dashboard'])
            ->name('dashboard');

        Route::get('/incidencias', [B2cIncidenciaAdminController::class, 'indexSoporte'])
            ->name('incidencias.index');

        Route::get('/incidencias/{incidencia}', [B2cIncidenciaAdminController::class, 'showSoporte'])
            ->name('incidencias.show');

        Route::patch('/incidencias/{incidencia}/estado', [B2cIncidenciaAdminController::class, 'statusSoporte'])->name('incidencias.status');
        Route::post('/incidencias/{incidencia}/seguimiento', [B2cIncidenciaAdminController::class, 'followUpSoporte'])->name('incidencias.follow-up');
        Route::get('/incidencias/{incidencia}/evidencia', [B2cIncidenciaAdminController::class, 'evidenceSoporte'])->name('incidencias.evidence');

    });

// ===============================
// PORTAL NEGOCIOS / EMPRESAS B2B
// ===============================
Route::get('/negocios/login', [B2cIncidenciaAdminController::class, 'loginNegocios'])
    ->middleware('zigo.portal:b2b')
    ->name('negocios.login');

Route::post('/negocios/login', [B2cIncidenciaAdminController::class, 'loginNegociosPost'])
    ->middleware('zigo.portal:b2b')
    ->name('negocios.login.post');

Route::post('/negocios/logout', [B2cIncidenciaAdminController::class, 'logoutNegocios'])
    ->middleware('zigo.portal:b2b')
    ->name('negocios.logout');

Route::middleware(['zigo.portal:b2b', 'auth', 'roles:sysadmin,admin,adminops,operaciones,cliente'])
    ->prefix('negocios')
    ->name('negocios.')
    ->group(function () {
        Route::get('/dashboard', function () {
            return view('negocios.dashboard');
        })->name('dashboard');

    });

Route::domain(config('zigo_domains.portals.devops.host'))
    ->middleware(['ensure.zigo.portal:devops'])
    ->group(function (): void {
        Route::get('/login', [DevOpsAuthController::class, 'showLogin'])
            ->name('devops.login');
        Route::post('/login', [DevOpsAuthController::class, 'login'])
            ->middleware('throttle:5,1')
            ->name('devops.login.store');
    });

Route::domain(config('zigo_domains.portals.devops.host'))
    ->middleware(['ensure.zigo.portal:devops', 'auth', 'roles:sysadmin,admin,soporte'])
    ->name('devops.')
    ->group(function (): void {
        Route::get('/', [CrmDevOpsController::class, 'index'])->name('index');
        Route::post('/logout', [DevOpsAuthController::class, 'logout'])->middleware('throttle:10,1')->name('logout');
        Route::get('/deployments', [CrmDevOpsController::class, 'deployments'])->name('deployments.index');
        Route::get('/deployments/create', [CrmDevOpsController::class, 'create'])->name('deployments.create');
        Route::post('/deployments', [CrmDevOpsController::class, 'store'])->middleware('throttle:devops-package-store')->name('deployments.store');
        Route::get('/deployments/{deployment}', [CrmDevOpsController::class, 'show'])->whereNumber('deployment')->name('deployments.show');
        Route::post('/deployments/{deployment}/validate', [CrmDevOpsController::class, 'validatePackage'])->whereNumber('deployment')->middleware('throttle:devops-package-validate')->name('deployments.validate');
        Route::post('/deployments/{deployment}/deploy', [CrmDevOpsController::class, 'deploy'])->whereNumber('deployment')->middleware('throttle:devops-package-deploy')->name('deployments.deploy');
        Route::post('/deployments/{deployment}/rollback', [CrmDevOpsController::class, 'rollback'])->whereNumber('deployment')->middleware('throttle:devops-package-rollback')->name('deployments.rollback');
        Route::get('/health', [CrmDevOpsController::class, 'health'])->name('health');
        Route::post('/health/run', [CrmDevOpsController::class, 'runHealth'])->middleware('throttle:devops-health-run')->name('health.run');
        Route::get('/releases', [CrmDevOpsController::class, 'releases'])->name('releases.index');
        Route::get('/comparison', [CrmDevOpsController::class, 'comparison'])->name('comparison');
        Route::get('/alerts', [CrmDevOpsController::class, 'alerts'])->name('alerts.index');
        Route::post('/alerts/{alert}/acknowledge', [CrmDevOpsController::class, 'acknowledgeAlert'])->whereNumber('alert')->middleware('throttle:10,1')->name('alerts.acknowledge');
        Route::get('/audits', [CrmDevOpsController::class, 'audits'])->name('audits.index');
        Route::get('/reports', [CrmDevOpsController::class, 'reports'])->name('reports.index');
        Route::get('/integrations/xperta-estafeta', [XpertaIntegrationController::class, 'index'])->name('integrations.xperta');
        Route::post('/integrations/xperta-estafeta/token', [XpertaIntegrationController::class, 'token'])->middleware('throttle:devops-xperta-token')->name('integrations.xperta.token');
        Route::post('/integrations/xperta-estafeta/frequency', [XpertaIntegrationController::class, 'frequency'])->middleware('throttle:devops-xperta-frequency')->name('integrations.xperta.frequency');
        Route::post('/integrations/xperta-estafeta/quote', [XpertaIntegrationController::class, 'quote'])->middleware('throttle:devops-xperta-quote')->name('integrations.xperta.quote');
    });

Route::any('/crm/devops/{path?}', function (?string $path = null) {
    $target = rtrim((string) config('zigo_domains.portals.devops.url'), '/');
    if ($path !== null && $path !== '') {
        $target .= '/' . ltrim($path, '/');
    }
    if (request()->getQueryString()) {
        $target .= '?' . request()->getQueryString();
    }

    return redirect()->away(
        $target,
        request()->isMethod('GET') || request()->isMethod('HEAD') ? 302 : 307
    );
})
    ->where('path', '.*')
    ->middleware(['ensure.zigo.portal:crm', 'auth', 'roles:sysadmin,admin,soporte'])
    ->name('crm.devops.redirect');


// ===============================
// PORTAL CRM / ADMIN GENERAL
// ===============================
Route::get('/crm/login', [B2cIncidenciaAdminController::class, 'loginCrm'])
    ->middleware('zigo.portal:crm')
    ->name('crm.login');

Route::post('/crm/login', [B2cIncidenciaAdminController::class, 'loginCrmPost'])
    ->middleware('zigo.portal:crm')
    ->name('crm.login.post');

Route::post('/crm/logout', [B2cIncidenciaAdminController::class, 'logoutCrm'])
    ->middleware('zigo.portal:crm')
    ->name('crm.logout');

Route::middleware(['ensure.zigo.portal:crm', 'auth', 'roles:sysadmin,admin'])
    ->prefix('crm')
    ->name('crm.')
    ->group(function () {
        Route::get('/dashboard', function () {
            return view('crm.dashboard');
        })->name('dashboard');

        Route::get('/empresas', [CrmCompanyController::class, 'index'])->name('empresas.index');
        Route::get('/empresas/{empresa}', [CrmCompanyController::class, 'show'])->whereNumber('empresa')->name('empresas.show');
        Route::get('/incidencias', [CrmIncidentController::class, 'index'])->name('incidencias.index');
        Route::get('/incidencias/{incidencia}', [CrmIncidentController::class, 'show'])->whereNumber('incidencia')->name('incidencias.show');
        Route::patch('/incidencias/{incidencia}/asignar', [CrmIncidentController::class, 'assign'])->name('incidencias.assign');
        Route::patch('/incidencias/{incidencia}/estado', [CrmIncidentController::class, 'status'])->name('incidencias.status');
        Route::post('/incidencias/{incidencia}/respuesta', [CrmIncidentController::class, 'response'])->name('incidencias.response');
        Route::get('/incidencias/{incidencia}/evidencia', [CrmIncidentController::class, 'evidence'])->name('incidencias.evidence');

        Route::get('/marketing/canales-publicos', [CrmPublicChannelController::class, 'index'])
            ->name('marketing.public-channels.index');

        Route::put('/marketing/canales-publicos', [CrmPublicChannelController::class, 'update'])
            ->name('marketing.public-channels.update');

        Route::get(
            '/paqueterias',
            [CrmShippingProviderController::class, 'index']
        )->name('shipping.index');

        Route::post(
            '/paqueterias/xperta/probar-token',
            [CrmShippingProviderController::class, 'testToken']
        )
            ->middleware('throttle:6,1')
            ->name('shipping.xperta.test-token');

        Route::post(
            '/paqueterias/xperta/probar-cotizacion',
            [CrmShippingProviderController::class, 'testQuote']
        )
            ->middleware('throttle:12,1')
            ->name('shipping.xperta.test-quote');

        Route::get(
            '/verificaciones',
            [CrmIdentityVerificationController::class, 'index']
        )->name('identity.index');

        Route::get(
            '/verificaciones/{verification}',
            [CrmIdentityVerificationController::class, 'show']
        )
            ->whereNumber('verification')
            ->name('identity.show');

        Route::get(
            '/verificaciones/{verification}/documentos/{document}',
            [CrmIdentityVerificationController::class, 'document']
        )
            ->whereNumber('verification')
            ->where(
                'document',
                'ine-front|ine-back|selfie'
            )
            ->name('identity.document');

        Route::post(
            '/verificaciones/{verification}/aprobar',
            [CrmIdentityVerificationController::class, 'approve']
        )
            ->whereNumber('verification')
            ->middleware('throttle:12,1')
            ->name('identity.approve');

        Route::post(
            '/verificaciones/{verification}/correccion',
            [
                CrmIdentityVerificationController::class,
                'requestCorrection',
            ]
        )
            ->whereNumber('verification')
            ->middleware('throttle:12,1')
            ->name('identity.correction');

        Route::post(
            '/verificaciones/{verification}/rechazar',
            [CrmIdentityVerificationController::class, 'reject']
        )
            ->whereNumber('verification')
            ->middleware('throttle:12,1')
            ->name('identity.reject');

        Route::get('/guias', [CrmGuideController::class, 'index'])
            ->name('guias.index');
        Route::get('/guias/export', CrmGuideExportController::class)->name('guias.export');
        Route::post('/notificaciones/{delivery}/reintentar', [CrmNotificationController::class,'retry'])->whereNumber('delivery')->name('notifications.retry');

        Route::get('/guias/{cotizacion}', [CrmGuideController::class, 'show'])
            ->whereNumber('cotizacion')
            ->name('guias.show');

        Route::post('/guias/{cotizacion}/recuperacion/crear', [CrmGuideRecoveryController::class, 'create'])->whereNumber('cotizacion')->name('guias.recovery.create');
        Route::post('/guias/{cotizacion}/recuperacion/normalizar', [CrmGuideRecoveryController::class, 'normalize'])->whereNumber('cotizacion')->name('guias.recovery.normalize');
        Route::post('/guias/{cotizacion}/recuperacion/pdf', [CrmGuideRecoveryController::class, 'pdf'])->whereNumber('cotizacion')->name('guias.recovery.pdf');
        Route::post('/guias/{cotizacion}/recuperacion/enlace', [CrmGuideRecoveryController::class, 'link'])->whereNumber('cotizacion')->name('guias.recovery.link');
        Route::post('/guias/{cotizacion}/recuperacion/recotizar', [CrmGuideRecoveryController::class, 'requote'])->whereNumber('cotizacion')->name('guias.recovery.requote');

        Route::get('/pagos', [CrmPaymentController::class, 'index'])
            ->name('pagos.index');
        Route::get('/pagos/export', CrmPaymentExportController::class)
            ->name('pagos.export');

        Route::get('/pagos/envios/{cotizacion}', [CrmPaymentController::class, 'showShipment'])
            ->whereNumber('cotizacion')
            ->name('pagos.envios.show');

        Route::get('/pagos/recargas/{recarga}', [CrmPaymentController::class, 'showRecharge'])
            ->whereNumber('recarga')
            ->name('pagos.recargas.show');

        Route::post('/guias/{cotizacion}/adeudos', [CrmGuideController::class, 'storeDebt'])
            ->whereNumber('cotizacion')
            ->middleware('throttle:12,1')
            ->name('guias.adeudos.store');

        Route::get('/adeudos', [CrmDebtController::class, 'index'])
            ->name('adeudos.index');

        Route::get('/adeudos/{adeudo}', [CrmDebtController::class, 'show'])
            ->whereNumber('adeudo')
            ->name('adeudos.show');

        Route::post('/adeudos/{adeudo}/cancelar', [CrmDebtController::class, 'cancel'])
            ->whereNumber('adeudo')
            ->middleware('throttle:12,1')
            ->name('adeudos.cancel');

        Route::post('/adeudos/{adeudo}/condonar', [CrmDebtController::class, 'waive'])
            ->whereNumber('adeudo')
            ->middleware('throttle:12,1')
            ->name('adeudos.waive');

        Route::get('/seguridad', [\App\Http\Controllers\CRM\CrmSecurityController::class, 'index'])
            ->name('seguridad.index');

        Route::get('/seguridad/usuarios', [\App\Http\Controllers\CRM\CrmSecurityController::class, 'usuarios'])
            ->name('seguridad.usuarios');

        Route::get('/seguridad/roles', [\App\Http\Controllers\CRM\CrmSecurityController::class, 'roles'])
            ->name('seguridad.roles');

        Route::get('/seguridad/permisos', [\App\Http\Controllers\CRM\CrmSecurityController::class, 'permisos'])
            ->name('seguridad.permisos');

        Route::get('/seguridad/auditoria', [\App\Http\Controllers\CRM\CrmSecurityController::class, 'auditoria'])
            ->name('seguridad.auditoria');

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

        Route::get(
            '/api-hub/billing',
            [\App\Http\Controllers\CRM\CrmApiBillingController::class, 'index']
        )->name('api-hub.billing.index');

        Route::get(
            '/api-hub/billing/{apiBillingRequest}',
            [\App\Http\Controllers\CRM\CrmApiBillingController::class, 'show']
        )
            ->whereNumber('apiBillingRequest')
            ->name('api-hub.billing.show');

        Route::post(
            '/api-hub/billing/{apiBillingRequest}/iniciar-atencion',
            [\App\Http\Controllers\CRM\CrmApiBillingController::class, 'startProcessing']
        )
            ->whereNumber('apiBillingRequest')
            ->name('api-hub.billing.start-processing');

        Route::post(
            '/api-hub/billing/{apiBillingRequest}/gestion',
            [\App\Http\Controllers\CRM\CrmApiBillingController::class, 'updateManagement']
        )
            ->whereNumber('apiBillingRequest')
            ->name('api-hub.billing.management.update');

        Route::post(
            '/api-hub/billing/{apiBillingRequest}/rechazar',
            [\App\Http\Controllers\CRM\CrmApiBillingController::class, 'reject']
        )
            ->whereNumber('apiBillingRequest')
            ->name('api-hub.billing.reject');

        Route::post(
            '/api-hub/billing/{apiBillingRequest}/cancelar',
            [\App\Http\Controllers\CRM\CrmApiBillingController::class, 'cancel']
        )
            ->whereNumber('apiBillingRequest')
            ->name('api-hub.billing.cancel');

        Route::post(
            '/api-hub/billing/{apiBillingRequest}/documentos',
            [\App\Http\Controllers\CRM\CrmApiBillingController::class, 'storeDocuments']
        )
            ->whereNumber('apiBillingRequest')
            ->name('api-hub.billing.documents.store');

        Route::get(
            '/api-hub/billing/{apiBillingRequest}/documentos/{format}',
            [\App\Http\Controllers\CRM\CrmApiBillingController::class, 'downloadDocument']
        )
            ->whereNumber('apiBillingRequest')
            ->whereIn('format', ['pdf', 'xml', 'zip'])
            ->name('api-hub.billing.documents.download');

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

        Route::get('/api-hub/{apiClient}/products', [\App\Http\Controllers\CRM\CrmApiHubController::class, 'products'])
            ->name('api-hub.products');

        Route::post('/api-hub/{apiClient}/products', [\App\Http\Controllers\CRM\CrmApiHubController::class, 'updateProducts'])
            ->name('api-hub.products.update');

        Route::get('/api-hub/{apiClient}/webhooks', [\App\Http\Controllers\CRM\CrmApiWebhookController::class, 'index'])
            ->name('api-hub.webhooks.index');

        Route::post('/api-hub/{apiClient}/webhooks', [\App\Http\Controllers\CRM\CrmApiWebhookController::class, 'store'])
            ->name('api-hub.webhooks.store');

        Route::post('/api-hub/{apiClient}/webhooks/{webhookEndpoint}/actualizar', [\App\Http\Controllers\CRM\CrmApiWebhookController::class, 'update'])
            ->whereNumber('webhookEndpoint')
            ->name('api-hub.webhooks.update');

        Route::post('/api-hub/{apiClient}/webhooks/{webhookEndpoint}/estado', [\App\Http\Controllers\CRM\CrmApiWebhookController::class, 'toggle'])
            ->whereNumber('webhookEndpoint')
            ->name('api-hub.webhooks.toggle');

        Route::post('/api-hub/{apiClient}/webhooks/{webhookEndpoint}/rotar-secreto', [\App\Http\Controllers\CRM\CrmApiWebhookController::class, 'rotateSecret'])
            ->whereNumber('webhookEndpoint')
            ->name('api-hub.webhooks.rotate-secret');

        Route::post('/api-hub/{apiClient}/webhooks/{webhookEndpoint}/entregas/{delivery}/reenviar', [\App\Http\Controllers\CRM\CrmApiWebhookController::class, 'retryDelivery'])
            ->whereNumber('webhookEndpoint')
            ->whereNumber('delivery')
            ->name('api-hub.webhooks.deliveries.retry');

        Route::post('/clientes/{cliente}/seguimiento', [CrmClientController::class, 'actualizarSeguimiento'])
            ->name('clientes.seguimiento');


        Route::get('/facturacion', [CrmInvoiceRequestController::class, 'index'])
            ->name('facturacion.index');

        Route::get('/facturacion/{invoiceRequest}', [CrmInvoiceRequestController::class, 'show'])
            ->whereNumber('invoiceRequest')
            ->name('facturacion.show');

        Route::post(
            '/facturacion/{invoiceRequest}/iniciar-atencion',
            [CrmInvoiceRequestController::class, 'iniciarAtencion']
        )
            ->whereNumber('invoiceRequest')
            ->name('facturacion.iniciar-atencion');


        Route::post(
            '/facturacion/{invoiceRequest}/gestion',
            [CrmInvoiceRequestController::class, 'guardarGestion']
        )
            ->whereNumber('invoiceRequest')
            ->name('facturacion.gestion.update');

        Route::post(
            '/facturacion/{invoiceRequest}/rechazar',
            [CrmInvoiceRequestController::class, 'rechazar']
        )
            ->whereNumber('invoiceRequest')
            ->name('facturacion.rechazar');

        Route::post(
            '/facturacion/{invoiceRequest}/cancelar',
            [CrmInvoiceRequestController::class, 'cancelar']
        )
            ->whereNumber('invoiceRequest')
            ->name('facturacion.cancelar');

        Route::post(
            '/facturacion/{invoiceRequest}/documentos',
            [CrmInvoiceRequestController::class, 'subirDocumentos']
        )
            ->whereNumber('invoiceRequest')
            ->name('facturacion.documentos.store');

        Route::get(
            '/facturacion/{invoiceRequest}/documentos/{format}',
            [CrmInvoiceDocumentController::class, 'download']
        )
            ->whereNumber('invoiceRequest')
            ->where('format', 'pdf|xml|zip')
            ->name('facturacion.documentos.download');

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
        Route::post('/pricing/concept-rules', [CrmPricingController::class, 'storeConceptRule'])
            ->name('pricing.concept-rules.store');
        Route::post('/pricing/concept-rules/{conceptRule}/toggle', [CrmPricingController::class, 'toggleConceptRule'])
            ->name('pricing.concept-rules.toggle');

        Route::post('/pricing/adjustments', [CrmPricingController::class, 'storeAdjustment'])
            ->name('pricing.adjustments.store');

        Route::post('/pricing/client-rules', [CrmPricingController::class, 'storeClientRule'])
            ->name('pricing.client-rules.store');


        Route::post('/pricing/sources', [CrmPricingController::class, 'storeSource'])
            ->name('pricing.sources.store');

        Route::post('/pricing/sources/{source}/toggle', [CrmPricingController::class, 'toggleSource'])
            ->name('pricing.sources.toggle');

        Route::post('/pricing/agreements', [CrmPricingController::class, 'storeAgreement'])
            ->name('pricing.agreements.store');

        Route::post('/pricing/agreements/{agreement}/toggle', [CrmPricingController::class, 'toggleAgreement'])
            ->name('pricing.agreements.toggle');

        Route::post('/pricing/agreement-services', [CrmPricingController::class, 'storeAgreementService'])
            ->name('pricing.agreement-services.store');

        Route::post('/pricing/agreement-services/{agreementService}/toggle', [CrmPricingController::class, 'toggleAgreementService'])
            ->name('pricing.agreement-services.toggle');

        Route::post('/pricing/rate-cards', [CrmPricingController::class, 'storeRateCard'])
            ->name('pricing.rate-cards.store');

        Route::post('/pricing/rate-cards/{rateCard}/toggle', [CrmPricingController::class, 'toggleRateCard'])
            ->name('pricing.rate-cards.toggle');

        Route::post('/pricing/rate-cards/{rateCard}/lines', [CrmPricingController::class, 'storeRateLine'])
            ->name('pricing.rate-lines.store');

        Route::post('/pricing/rate-lines/{rateLine}/toggle', [CrmPricingController::class, 'toggleRateLine'])
            ->name('pricing.rate-lines.toggle');

        Route::post(
            '/pricing/agreement-services/'
            . '{agreementService}/rate-references',
            [
                CrmPricingController::class,
                'storeRateReference',
            ]
        )
            ->name('pricing.rate-references.store');

        Route::post(
            '/pricing/rate-references/'
            . '{rateReference}/toggle',
            [
                CrmPricingController::class,
                'toggleRateReference',
            ]
        )
            ->name('pricing.rate-references.toggle');

        Route::get(
            '/pricing/rate-references/'
            . '{rateReference}/document',
            [
                CrmPricingController::class,
                'downloadRateReferenceDocument',
            ]
        )
            ->name('pricing.rate-references.document');

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
    ->middleware(['zigo.portal:b2c', 'throttle:60,1'])
    ->name('postal-code.lookup');

Route::get('/b2c/cp/colonias', [PostalCodeLookupController::class, 'colonias'])
    ->middleware(['zigo.portal:b2c', 'throttle:60,1'])
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

Route::middleware('auth')->group(function(){
    Route::get('/b2c/facturacion/{invoiceRequest}/corregir',[B2cInvoiceCorrectionController::class,'correct'])->middleware('signed')->name('b2c.invoice.correct');
    Route::post('/b2c/facturacion/{invoiceRequest}/reenviar',[B2cInvoiceCorrectionController::class,'resubmit'])->name('b2c.invoice.resubmit');
});



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

Route::domain(config('zigo_domains.portals.crm.host'))->middleware(['auth','roles:sysadmin'])->group(function(){
    Route::get('/crm/paqueterias/diagnostico', [\App\Http\Controllers\CRM\CrmShippingDiagnosticController::class, 'index'])->name('crm.shipping.diagnostics');
    Route::post('/internal/shipping/quote-probe', \App\Http\Controllers\Internal\ShippingQuoteProbeController::class)->middleware('throttle:3,1')->name('crm.shipping.quote-probe');
});
Route::domain(config('zigo_domains.portals.devops.host'))->middleware(['auth','roles:sysadmin'])->post('/internal/shipping/quote-probe', \App\Http\Controllers\Internal\ShippingQuoteProbeController::class)->middleware('throttle:3,1')->name('devops.shipping.quote-probe');
