<?php

namespace App\Http\Controllers\Roles;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;  
use App\Models\Roles\Roles;
use App\Models\Roles\Permisos;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

//USO GENERAL
use Log;
use Redirect;
use Exception;

class RolesController extends Controller
{
    public function index()
    {
        $roles = Roles::orderBy('id','asc')->get();
        return view('roles.index',['roles' => $roles]);
    }

    public function create()
    {
        $roles = Roles::orderBy('id','asc')->get();
        return view('roles.crear',['roles' => $roles]);
    }

    public function store(Request $request)
    {
        //dd($request);
        try {
                $request->validate([
                    'name' => 'required|unique:roles|max:20',
                    'slug' => 'required|max:20',
                    'roles_permisos' => 'required',
                ]);

                $rol = new Roles;
                $rol->name = $request->name;
                $rol->slug = $request->slug;
                $rol->save();

                foreach($request->roles_permisos as $item){
                    $items = new Permisos();
                    $items->name = ucfirst($item);
                    $items->slug = strtolower(str_replace("", "-", $item));
                    $items->save();
                    $rol->permisos()->attach($items->id);
                    $rol->save();           
                }

                $tmp = sprintf("El rol '%s' se creo correctamente", $rol->name);
                $notices = array($tmp);

        } catch (ModelNotFoundException $e) {
                Log::info(__CLASS__." ".__FUNCTION__);
                Log::info("ModelNotFoundException");
                return Redirect::back()
                    ->withErrors(array($e->getMessage() ))
                    ->withInput();
             } catch (QueryException $e) { 
                Log::info(__CLASS__." ".__FUNCTION__." QueryException");
                Log::debug($e->getMessage());
                    return Redirect::back()
                        ->withErrors(array($e->getMessage() ))
                        ->withInput(); 
                } catch (Exception $e) {
                    Log::info(__CLASS__." ".__FUNCTION__);
                    return Redirect::back()
                        ->with('dangers',array($e->getMessage() ))
                        ->withInput();               
            }
        return \Redirect::route('roles.index') -> withSuccess ($notices);
    }

    public function show($id)
    { 
        try {
            Log::info(__CLASS__." ".__FUNCTION__);    
            $roles = Roles::where('id', '=',$id)
            ->first();
            Log::debug("show ".$roles);

            return view('roles.mostrar',['roles'=>$roles]); 

        } catch (Exception $e) {
            $roles = array();
            return view('roles.mostrar'
                )->with('notices',array($e->getMessage() ));
        }
    }

 
    public function edit($id)
    {

       try {
            Log::info(__CLASS__." ".__FUNCTION__);    
            $roles = Roles::where('id', '=',$id)->first();
            Log::debug("show ".$roles);
            return view('roles.editar',['roles'=>$roles]); 

        } catch (Exception $e) {
            $roles = array();
            return view('roles.editar'
                )->with('notices',array($e->getMessage() ));
        }
    }

    public function update(Request $request, Roles $role)
    {

        try {
           $request->validate([
                'name' => 'required|max:20',
                'slug' => 'required|max:20',
            ]);
     
           
                $role->name = $request->name;
                $role->slug = $request->slug;
                $role->save();

                $role->permisos()->delete();
                $role->permisos()->detach();
              
                foreach ($request->roles_permisos as $permission) {
                    $permissions = new Permisos();
                    $permissions->name = $permission;
                    $permissions->slug = strtolower(str_replace(" ", "-", $permission));
                    $permissions->save();
                    $role->permisos()->attach($permissions->id);
                    $role->save();
                }    
                $tmp = sprintf("Actualizacion correcta del Rol '%s'", $request->name);
                $notices = array($tmp);                
        } catch (ModelNotFoundException $e) {
                Log::info(__CLASS__." ".__FUNCTION__);
                Log::info("ModelNotFoundException");
                return Redirect::back()
                    ->withErrors(array($e->getMessage() ))
                    ->withInput();
             } catch (QueryException $e) { 
                Log::info(__CLASS__." ".__FUNCTION__." QueryException");
                Log::debug($e->getMessage());
                    return Redirect::back()
                        ->withErrors(array($e->getMessage() ))
                        ->withInput(); 
                } catch (Exception $e) {
                    Log::info(__CLASS__." ".__FUNCTION__);
                    return Redirect::back()
                        ->with('dangers',array($e->getMessage() ))
                        ->withInput();               
            }
        return \Redirect::route('roles.index',['roles'=>$request]) -> withSuccess ($notices);
    }

    public function destroy($id)
    {

        $item = Roles::findOrFail($id);
        $item->permisos()->delete();
        $item->delete();
        $item->permisos()->detach();
        $tmp = sprintf("El Rol se eliminó correctamente",);
        $notices = array($tmp); 
        return \Redirect::route('roles.index')-> withSuccess ($notices);
    }
}
