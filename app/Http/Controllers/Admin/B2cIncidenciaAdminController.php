<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\B2cCotizacion;
use App\Models\B2cIncidencia;
use App\Services\Billing\B2cBalanceReversalService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Services\IncidentWorkflowService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

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

    public function showBalanceReconciliation(
        B2cCotizacion $cotizacion,
        B2cBalanceReversalService $reversalService
    ) {
        $cotizacion->load([
            'user',
            'saldoReversals.adminUser',
            'saldoReversals.purchaseMovement',
            'saldoReversals.reversalMovement',
        ]);

        $preview = $reversalService->preview(
            $cotizacion
        );

        return view(
            'admin.conciliacion.show',
            compact(
                'cotizacion',
                'preview'
            )
        );
    }

    public function reverseBalance(
        Request $request,
        B2cCotizacion $cotizacion,
        B2cBalanceReversalService $reversalService
    ) {
        $data = $request->validate([
            'reason' => [
                'required',
                'string',
                'min:20',
                'max:1000',
            ],
            'provider_confirmed' => [
                'accepted',
            ],
        ]);

        try {
            $reversal = $reversalService->reverse(
                $cotizacion,
                (int) auth()->id(),
                $data['reason'],
                $request->boolean(
                    'provider_confirmed'
                )
            );
        } catch (DomainException $exception) {
            return back()
                ->withInput()
                ->with(
                    'error',
                    $exception->getMessage()
                );
        }

        return redirect()
            ->route(
                'admin.conciliacion.show',
                $cotizacion->id
            )
            ->with(
                'success',
                'Saldo devuelto correctamente. '
                . 'Reverso #' . $reversal->id . '.'
            );
    }

    public function dashboard()
    {
        $base=$this->supportScope();
        $total=(clone $base)->count();$abiertas=(clone $base)->whereIn('estatus',['ABIERTA','EN_REVISION','ASIGNADA'])->count();
        $proceso=(clone $base)->where('estatus','EN_PROCESO')->count();$cerradas=(clone $base)->whereIn('estatus',['RESUELTA','CERRADA'])->count();
        $incidencias=(clone $base)->latest()->limit(10)->get();

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

        if (!in_array($role, ['sysadmin', 'admin', 'adminops', 'operaciones', 'soporte'])) {
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

        $incidencias = $this->supportScope()->with(['user:id,name,email','assignee:id,name,email'])
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
            ->when($request->filled('prioridad'),fn($query)=>$query->where('prioridad',$request->prioridad))
            ->latest()->paginate(25)->withQueryString();

        $cotizaciones = collect();

        return view('soporte.incidencias.index', compact('incidencias', 'cotizaciones', 'q', 'estatus'));
    }

    public function showSoporte(B2cIncidencia $incidencia)
    {
        $this->authorizeSupportIncident($incidencia);
        $incidencia->load(['user:id,name,email','cotizacion','events.user:id,name,email']);
        return view('soporte.incidencias.show', compact('incidencia'));
    }

    public function statusSoporte(Request $request,B2cIncidencia $incidencia,IncidentWorkflowService $flow)
    {
        $this->authorizeSupportIncident($incidencia);
        $data=$request->validate(['status'=>['required',Rule::in(['EN_PROCESO','RESUELTA'])]]);
        try{$flow->status($incidencia,$data['status'],auth()->id(),'SOPORTE');}catch(\DomainException $e){return back()->withErrors(['status'=>$e->getMessage()]);}
        return back()->with('success','Estatus actualizado.');
    }

    public function followUpSoporte(Request $request,B2cIncidencia $incidencia,IncidentWorkflowService $flow)
    {
        $this->authorizeSupportIncident($incidencia);
        $data=$request->validate(['public_response'=>['nullable','string','max:5000'],'internal_note'=>['nullable','string','max:5000'],'solution'=>['nullable','boolean']]);
        if(empty($data['public_response'])&&empty($data['internal_note']))return back()->withErrors(['public_response'=>'Captura una respuesta o nota.']);
        try{$flow->message($incidencia,auth()->id(),'SOPORTE',$data['public_response']??null,$data['internal_note']??null,$request->boolean('solution'));}catch(\DomainException $e){return back()->withErrors(['public_response'=>$e->getMessage()]);}
        return back()->with('success','Seguimiento registrado.');
    }

    public function evidenceSoporte(B2cIncidencia $incidencia)
    {
        $this->authorizeSupportIncident($incidencia);
        abort_unless($incidencia->evidencia&&Storage::disk('public')->exists($incidencia->evidencia),404);
        return Storage::disk('public')->download($incidencia->evidencia,basename($incidencia->evidencia));
    }

    private function supportScope()
    {
        $query=B2cIncidencia::query();
        if(!auth()->user()->hasRol('sysadmin,admin'))$query->where('assigned_to',auth()->id());
        return $query;
    }

    private function authorizeSupportIncident(B2cIncidencia $incidencia): void
    {
        if(!auth()->user()->hasRol('sysadmin,admin')&&(int)$incidencia->assigned_to!==(int)auth()->id())abort(403);
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
