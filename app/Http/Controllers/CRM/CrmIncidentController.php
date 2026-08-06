<?php
namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\B2cIncidencia;
use App\Models\User;
use App\Services\IncidentWorkflowService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CrmIncidentController extends Controller
{
    public function index(Request $request)
    {
        $query=B2cIncidencia::with(['user:id,name,email','cotizacion:id,tracking_number','assignee:id,name,email']);
        foreach(['tipo','prioridad'] as $field)if($request->filled($field))$query->where($field,$request->$field);
        if($request->filled('estatus'))$query->where('estatus',$request->estatus);
        if($request->filled('responsable'))$query->where('assigned_to',$request->responsable);
        if($request->filled('q')){$term=trim($request->q);$query->where(function($q)use($term){$q->where('folio','like',"%{$term}%")->orWhere('tracking_number','like',"%{$term}%")->orWhereHas('user',fn($u)=>$u->where('name','like',"%{$term}%")->orWhere('email','like',"%{$term}%"));});}
        $incidencias=$query->latest()->paginate(25)->withQueryString();
        $responsables=$this->supportUsers();
        return view('crm.incidencias.index',compact('incidencias','responsables'));
    }

    public function show(B2cIncidencia $incidencia)
    {
        $incidencia->load(['user:id,name,email','cotizacion','assignee:id,name,email','events.user:id,name,email']);
        $responsables=$this->supportUsers();
        return view('crm.incidencias.show',compact('incidencia','responsables'));
    }

    public function assign(Request $request,B2cIncidencia $incidencia,IncidentWorkflowService $flow)
    {
        $data=$request->validate(['assigned_to'=>['required','integer','exists:users,id'],'priority'=>['required',Rule::in(['BAJA','MEDIA','ALTA','CRITICA'])]]);
        $flow->assign($incidencia,(int)$data['assigned_to'],$data['priority'],auth()->id());
        return back()->with('success','Incidencia asignada.');
    }

    public function status(Request $request,B2cIncidencia $incidencia,IncidentWorkflowService $flow)
    {
        $data=$request->validate(['status'=>['required',Rule::in(['ABIERTA','EN_REVISION','ASIGNADA','EN_PROCESO','RESUELTA','CERRADA'])]]);
        try{$flow->status($incidencia,$data['status'],auth()->id(),'CRM');}catch(DomainException $e){return back()->withErrors(['status'=>$e->getMessage()]);}
        return back()->with('success','Estatus actualizado.');
    }

    public function response(Request $request,B2cIncidencia $incidencia,IncidentWorkflowService $flow)
    {
        $data=$request->validate(['public_response'=>['nullable','string','max:5000'],'internal_note'=>['nullable','string','max:5000'],'solution'=>['nullable','boolean']]);
        if(empty($data['public_response'])&&empty($data['internal_note']))return back()->withErrors(['public_response'=>'Captura una respuesta o nota.']);
        try{$flow->message($incidencia,auth()->id(),'CRM',$data['public_response']??null,$data['internal_note']??null,$request->boolean('solution'));}catch(DomainException $e){return back()->withErrors(['public_response'=>$e->getMessage()]);}
        return back()->with('success','Seguimiento registrado.');
    }

    public function evidence(B2cIncidencia $incidencia)
    {
        abort_unless($incidencia->evidencia&&Storage::disk('public')->exists($incidencia->evidencia),404);
        return Storage::disk('public')->download($incidencia->evidencia,basename($incidencia->evidencia));
    }

    private function supportUsers()
    {
        return User::whereHas('roles',fn($q)=>$q->whereIn('slug',['soporte','adminops','operaciones']))->orderBy('name')->get(['users.id','users.name','users.email']);
    }
}
