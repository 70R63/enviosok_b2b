<?php

namespace App\Http\Controllers\Roles;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\EmpresaEmpresas;
use App\Models\Roles\Roles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use App\Models\Empresa;

//USO GENERAL
use Log;
use Redirect;
use Exception;
use DB;


class UsersController extends Controller
{


    public function index()
    {
        $empresas = EmpresaEmpresas::where('id',auth()->user()->empresa_id)
                ->pluck('empresa_id')->toArray();

        $users = User::whereIN('users.empresa_id',$empresas)
            ->orderBy('id','asc')->get();
        
        return view('usuario.index', compact("users") );
    }

    public function create(Request $request)
    {
        if($request->ajax()){
            $roles = Roles::where('id', $request->role_id)->first();
            $permisos = $roles->permisos;

            return $permisos;  
        }

        $resultset = DB::table('users_roles')
                ->where('user_id', auth()->user()->id )
                ->get();

        $roles = Roles::where('id','>',$resultset[0]->roles_id)->get();
        

        $pluckEmpresa = Empresa::pluck('nombre','id');

        return view('usuario.crear',compact('roles','pluckEmpresa'));
    }

 
    public function store(Request $request)
    {   
        try {
            $request->validate([
                'name' => 'required|max:40',
                'email' => 'required|unique:users|email|max:50',
                'password' => 'required|between:8,12|confirmed',
                'password_confirmation' => 'required'
            ]);

                $user = new User;
                $user->name = $request->name;
                $user->email = $request->email;
                $user->password = Hash::make($request->password);
                $user->empresa_id = $request->empresa_id;
                $user->save();

                if($request->role != null){
                    $user->roles()->attach($request->role);
                    $user->save();
                }

                if($request->permisos != null){            
                    foreach ($request->permisos as $permiso) {
                        $user->permisos()->attach($permiso);
                        $user->save();
                    }
                }
                $tmp = sprintf("El usuario '%s' se creó correctamente", $user->name);
                $notices = array($tmp);
            } 
            catch (ModelNotFoundException $e) {
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
                return \Redirect::route('users.index')-> withSuccess ($notices);;

    }

    public function show(User $user)
    {
        return view('usuario.mostrar',['users' => $user]);
    }

    public function edit(User $user)
    {

        $roles = Roles::get();
        $userRole = $user->roles->first();
 
        if($userRole != null){
            $rolePermisos = $userRole->allRolesPermisos;
        }else{
            $rolePermisos = null;
        }
        $userPermisos = $user->permisos;

        return view('usuario.editar', [
            'users'=>$user,
            'roles'=>$roles,
            'userRole'=>$userRole,
            'rolPermisos'=>$rolePermisos,
            'usersPermisos'=>$userPermisos
            ]);  
    }

    public function update(Request $request, User $user)
    {  
        try {                 
                $request->validate([
                    'name' => 'required|max:40',
                    'email' => 'required|email|max:50',
                    'password' => 'confirmed',
                ]);
                $user->name = $request->name;
                $user->email = $request->email;
                if($request->password != null){
                    $user->password = Hash::make($request->password);
                }
                $user->save();
                $user->roles()->detach();
                $user->permisos()->detach();

                if($request->role != null){
                    $user->roles()->attach($request->role);
                    $user->save();
                }
                if($request->permisos != null){            
                    foreach ($request->permisos as $permiso) {
                        $user->permisos()->attach($permiso);
                        $user->save();
                    }
                }
                $tmp = sprintf("El usuario '%s' se actualizó correctamente", $user->name);
                $notices = array($tmp);
            } 
            catch (ModelNotFoundException $e) {
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
        return \Redirect::route('users.index')-> withSuccess ($notices);
    }

    public function destroy(User $user)
    {
        $user->roles()->detach();
        $user->permisos()->detach();
        $user->delete();
        $tmp = sprintf("El usuario '%s' se eliminó correctamente", $user->name);
        $notices = array($tmp);
        return \Redirect::route('users.index')-> withSuccess ($notices);;
    }
}
