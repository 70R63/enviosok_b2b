<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LtdController;


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
    return view('auth.login');
});

Route::resource('profile','userProfileController');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth'])->name('dashboard');

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
