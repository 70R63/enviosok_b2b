<?php
namespace App\Http\Controllers\CRM;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use Illuminate\Http\Request;

class CrmCompanyController extends Controller
{
    public function index(Request $request)
    {
        $query=Empresa::withoutGlobalScopes()->withCount('users');
        if($request->filled('q')){$term=trim($request->q);$query->where(fn($q)=>$q->where('nombre','like',"%{$term}%")->orWhere('rfc','like',"%{$term}%")->orWhere('email','like',"%{$term}%"));}
        $empresas=$query->latest('id')->paginate(25)->withQueryString();
        return view('crm.empresas.index',compact('empresas'));
    }
    public function show(int $empresa)
    {
        $empresa=Empresa::withoutGlobalScopes()->findOrFail($empresa);
        $empresa->load('users:id,empresa_id,name,email,created_at');
        $userIds=$empresa->users->pluck('id');
        $envios=\App\Models\B2cCotizacion::with(['user:id,name,email','invoiceRequest'])->whereIn('user_id',$userIds)->latest()->paginate(15);
        $incidencias=\App\Models\B2cIncidencia::with('user:id,name,email')->whereIn('user_id',$userIds)->latest()->get();
        $adeudos=\App\Models\B2cAdeudo::whereIn('user_id',$userIds)->latest()->get();
        return view('crm.empresas.show',compact('empresa','envios','incidencias','adeudos'));
    }
}
