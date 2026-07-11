<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\B2cIncidencia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class B2cIncidenciaAdminController extends Controller
{
    public function index(Request $request)
{
    $estatus = $request->get('estatus');
    $q = trim($request->get('q'));

    $incidencias = B2cIncidencia::with(['user', 'cotizacion'])
        ->when($estatus, function ($query) use ($estatus) {
            $query->where('estatus', $estatus);
        })
        ->latest()
        ->get();

    $cotizaciones = collect();

    if ($q) {
        $cotizaciones = \App\Models\B2cCotizacion::with('user')
            ->where(function ($query) use ($q) {

                if (is_numeric($q)) {
                    $query->where('id', $q);
                }

                $query->orWhere('tracking_number', 'like', "%{$q}%")
                    ->orWhere('payment_id', $q)
                    ->orWhere('payment_external_reference', 'like', "%{$q}%")
                    ->orWhere('payment_status', 'like', "%{$q}%")
                    ->orWhere('estatus', 'like', "%{$q}%")
                    ->orWhere('guia_estatus', 'like', "%{$q}%")
                    ->orWhereHas('user', function ($u) use ($q) {
                        $u->where('name', 'like', "%{$q}%")
                          ->orWhere('email', 'like', "%{$q}%");
                    });
            })
            ->latest()
            ->limit(10)
            ->get();
    }

    return view('admin.incidencias.index', compact(
        'incidencias',
        'estatus',
        'q',
        'cotizaciones'
    ));
}

    public function show(B2cIncidencia $incidencia)
    {
        $incidencia->load(['user', 'cotizacion']);

        return view('admin.incidencias.show', compact('incidencia'));
    }

    public function responder(Request $request, B2cIncidencia $incidencia)
    {
        $data = $request->validate([
            'estatus' => ['required', 'string'],
            'prioridad' => ['nullable', 'string'],
            'respuesta_admin' => ['required', 'string'],
        ]);

        $incidencia->update([
            'estatus' => $data['estatus'],
            'prioridad' => $data['prioridad'] ?? $incidencia->prioridad,
            'respuesta_admin' => $data['respuesta_admin'],
            'respondida_at' => now(),
            'respondida_por' => auth()->id(),
        ]);

        return redirect()
            ->route('admin.incidencias.show', $incidencia->id)
            ->with('success', 'Incidencia actualizada correctamente.');
    }

    public function dashboard()
    {
        $total = \App\Models\B2cIncidencia::count();
        $abiertas = \App\Models\B2cIncidencia::whereIn('estatus', ['NUEVA', 'ABIERTA', 'PENDIENTE'])->count();
        $proceso = \App\Models\B2cIncidencia::where('estatus', 'EN_PROCESO')->count();
        $cerradas = \App\Models\B2cIncidencia::whereIn('estatus', ['RESUELTA', 'CERRADA'])->count();

        $incidencias = \App\Models\B2cIncidencia::latest()->limit(10)->get();

        return view('soporte.dashboard', compact('total', 'abiertas', 'proceso', 'cerradas', 'incidencias'));
    }

    public function login()
{
    return view('soporte.login');
}

public function loginPost(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (!Auth::attempt($credentials)) {
            return back()->withErrors([
                'email' => 'Credenciales incorrectas.',
            ])->withInput();
        }

        $request->session()->regenerate();

        $user = Auth::user();
        $role = optional($user->roles->first())->slug;

        if (!in_array($role, ['sysadmin', 'admin', 'adminops', 'operaciones'])) {
            Auth::logout();
            return back()->withErrors([
                'email' => 'No tienes permiso para acceder al portal de soporte.',
            ]);
        }

        return redirect()->route('soporte.dashboard');
    }

    public function indexSoporte(Request $request)
    {
        $q = $request->get('q');
        $estatus = $request->get('estatus');

        $incidencias = B2cIncidencia::with('user')
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('folio', 'like', "%{$q}%")
                        ->orWhere('tracking_number', 'like', "%{$q}%")
                        ->orWhere('tipo', 'like', "%{$q}%")
                        ->orWhere('asunto', 'like', "%{$q}%")
                        ->orWhereHas('user', function ($u) use ($q) {
                            $u->where('name', 'like', "%{$q}%")
                            ->orWhere('email', 'like', "%{$q}%");
                        });
                });
            })
            ->when($estatus, fn($query) => $query->where('estatus', $estatus))
            ->latest()
            ->get();

        $cotizaciones = collect();

        return view('soporte.incidencias.index', compact('incidencias', 'cotizaciones', 'q', 'estatus'));
    }

    public function showSoporte(B2cIncidencia $incidencia)
    {
        return view('soporte.incidencias.show', compact('incidencia'));
    }

    public function logoutSoporte(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('soporte.login');
    }

    //CRM
    public function loginCrm()
    {
        return view('crm.login');
    }

    public function loginCrmPost(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (!Auth::attempt($credentials)) {
            return back()->withErrors([
                'email' => 'Credenciales incorrectas.',
            ])->withInput();
        }

        $request->session()->regenerate();

        $role = optional(Auth::user()->roles->first())->slug;

        if (!in_array($role, ['sysadmin', 'admin'])) {
            Auth::logout();

            return back()->withErrors([
                'email' => 'No tienes permiso para acceder al CRM ZIGO.',
            ]);
        }

        return redirect()->route('crm.dashboard');
    }

    public function logoutCrm(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('crm.login');
    }

    //HUB
    public function loginHub()
    {
        return view('hub.login');
    }

    public function loginHubPost(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (!Auth::attempt($credentials)) {
            return back()->withErrors(['email' => 'Credenciales incorrectas.'])->withInput();
        }

        $request->session()->regenerate();

        $role = optional(Auth::user()->roles->first())->slug;

        if (!in_array($role, ['sysadmin', 'admin', 'adminops', 'cliente'])) {
            Auth::logout();
            return back()->withErrors(['email' => 'No tienes permiso para acceder al API Hub.']);
        }

        return redirect()->route('hub.dashboard');
    }

    public function logoutHub(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('hub.login');
    }

    public function loginNegocios()
{
    return view('negocios.login');
}

    public function loginNegociosPost(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (!Auth::attempt($credentials)) {
            return back()->withErrors(['email' => 'Credenciales incorrectas.'])->withInput();
        }

        $request->session()->regenerate();

        $role = optional(Auth::user()->roles->first())->slug;

        if (!in_array($role, ['sysadmin', 'admin', 'adminops', 'operaciones', 'cliente'])) {
            Auth::logout();
            return back()->withErrors(['email' => 'No tienes permiso para acceder al portal Negocios.']);
        }

        return redirect()->route('negocios.dashboard');
    }

    public function logoutNegocios(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('negocios.login');
    }
}