<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

use DB;

class userProfileController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */


    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $users = Auth::user();
        return view("perfil.profile",compact('users'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */
    public function show(User $user)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */
    public function edit(User $user)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, User $user)
    {
        //dd($user,$request);
       
       $this->validate(request(), [
            'old_password' => 'required',
            'password' => 'required|min:6|confirmed'
        ]);

       #VERIFICAR PASSWORD ACTUAL
        if(!Hash::check($request->old_password, auth()->user()->password)){
            $tmp = sprintf("El password actual no coincide");
            $notices = array($tmp);
            return \Redirect::route('profile.index')-> withErrors ($notices);      
        }
        else{
            $request->password = Hash::make(request('password'));
            $query = DB::table('users')
                ->where('id', $request->user_id)
                ->update(['password' => $request->password]);
            $tmp = sprintf("El usuario '%s' se actualizó correctamente", $request->name);
            $notices = array($tmp);
            return \Redirect::route('profile.index')-> withSuccess ($notices);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */
    public function destroy(User $user)
    {
        //
    }
}
