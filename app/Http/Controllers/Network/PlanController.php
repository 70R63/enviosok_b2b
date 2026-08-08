<?php
namespace App\Http\Controllers\Network;
use App\Domain\Network\Catalog\Models\Plan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Network\StorePlanRequest;
class PlanController extends Controller
{
    public function index() { return view('network.plans.index',['plans'=>Plan::withCount('modules')->latest()->paginate(20)]); }
    public function create() { return view('network.plans.create'); }
    public function store(StorePlanRequest $request)
    {
        $plan=Plan::create($request->validated());
        return redirect()->route('network.plans.show',$plan)->with('success','Plan creado correctamente.');
    }
    public function show(Plan $plan) { $plan->load('modules'); return view('network.plans.show',compact('plan')); }
}
