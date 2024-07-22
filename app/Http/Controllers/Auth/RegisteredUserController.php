<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Catalogo;
use App\Models\CatalogoElemento;
use App\Models\Roles\Roles;
use App\Models\User;
use App\Providers\RouteServiceProvider;
//use App\Services\DomicilioService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class RegisteredUserController extends Controller
{
    protected $domicilio;
    public function __construct()
    {
        //$this->domicilio = new DomicilioService();
    }

    /**
     * Display the registration view.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $cat = Catalogo::whereCodigo('tiposVialidad')->first();
        $tiposVialidad = CatalogoElemento::whereCatalogoId($cat->id)->orderBy('nombre')->get();
        return view('auth.register',compact('tiposVialidad'));
    }

    /**
     * Handle an incoming registration request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'apellido_paterno' => ['required', 'string', 'max:255'],
            'rfc' => ['required', 'string', 'max:13'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', Rules\Password::defaults()],
            'cp' => ['required','numeric', 'max:99999'],
            'estado' => ['required','string'],
            'municipio_alcaldia' => ['required','string', 'max:255'],
            'colonia' => ['required','string', 'max:255'],
            'calle' => ['required','string', 'max:255'],
            'no_exterior' => ['required','numeric','max:99999'],
            'tipo_vialidad_id' => ['required'],
            'ine_anverso' => ['required','image','max:5120'],
            'ine_reverso' => ['required','image','max:5120'],
            'selfie' => ['required','image','max:5120'],
        ]);

        $user = User::create([
            'name' => $request->name,
            'apellido_paterno' => $request->apellido_paterno,
            'apellido_materno' => @$request->apellido_materno,
            'rfc' => $request->rfc,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $rolCliente = Roles::whereSlug('cliente')->first();
        $user->roles()->sync([ $rolCliente->id ]);
        $this->domicilio->guardarDomicilio($request,$user);

        foreach ($request->allFiles() as $k=>$file){
            $file->store('documentos/'.$user->id.'/'.$k);
        }

        event(new Registered($user));
        Auth::login($user);
        return redirect(RouteServiceProvider::HOME)->with('success',["Te has registrado correctamente. Te notificaremos por email cuando tu información sea validada."]);
    }
}
