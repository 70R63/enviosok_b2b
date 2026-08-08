<?php
namespace App\Http\Controllers\Network;
use App\Domain\Network\Catalog\Models\Module;
use App\Http\Controllers\Controller;
use App\Http\Requests\Network\StoreModuleRequest;
class ModuleController extends Controller
{
    public function index() { return view('network.modules.index',['modules'=>Module::orderBy('sort_order')->orderBy('name')->paginate(30)]); }
    public function create() { return view('network.modules.create'); }
    public function store(StoreModuleRequest $request)
    {
        $data=$request->validated(); $data['is_active']=$request->boolean('is_active'); Module::create($data);
        return redirect()->route('network.modules.index')->with('success','Módulo creado correctamente.');
    }
}
