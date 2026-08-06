<?php

namespace App\Http\Controllers\DevOps;

use App\Http\Controllers\Controller;
use App\Models\ZigoProviderApiEvent;
use App\Services\DevOps\XpertaStageIntegrationTester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class XpertaIntegrationController extends Controller
{
    public function index(XpertaStageIntegrationTester $tester): View
    {
        $this->featureEnabled($tester);
        $this->readable();
        $history = ZigoProviderApiEvent::query()->where('provider','xperta')->whereIn('operation',['devops_token','devops_frequency','devops_quote'])->latest()->limit(50)->get();
        return view('crm.devops.integrations.xperta', ['configuration'=>$tester->configuration(),'history'=>$history,'canExecute'=>$this->sysadmin()]);
    }
    public function token(XpertaStageIntegrationTester $tester): RedirectResponse { $this->featureEnabled($tester); $this->executable(); return back()->with('probe_result',$tester->token()); }
    public function frequency(Request $request, XpertaStageIntegrationTester $tester): RedirectResponse
    {
        $this->featureEnabled($tester); $this->executable(); $data=$request->validate(['origin'=>['required','regex:/^\d{5}$/'],'destination'=>['required','regex:/^\d{5}$/']]);
        return back()->with('probe_result',$tester->frequency($data['origin'],$data['destination']));
    }
    public function quote(Request $request, XpertaStageIntegrationTester $tester): RedirectResponse
    {
        $this->featureEnabled($tester); $this->executable(); $data=$request->validate(['origin'=>['required','regex:/^\d{5}$/'],'destination'=>['required','regex:/^\d{5}$/'],'weight'=>['required','numeric','gt:0','max:1000'],'length'=>['required','numeric','gt:0','max:500'],'width'=>['required','numeric','gt:0','max:500'],'height'=>['required','numeric','gt:0','max:500'],'service'=>['required',Rule::in(['terrestre','diasig'])],'declared_value'=>['required','numeric','min:0','max:1000000']]);
        return back()->with('probe_result',$tester->quote($data));
    }
    private function readable():void { abort_unless($this->sysadmin()||auth()->user()?->hasRol('admin')||auth()->user()?->hasRol('soporte'),403); }
    private function executable():void { abort_unless($this->sysadmin(),403); }
    private function featureEnabled(XpertaStageIntegrationTester $tester):void { abort_unless((bool) ($tester->configuration()['enabled'] ?? false),404); }
    private function sysadmin():bool { return auth()->user()?->hasRol('sysadmin')===true; }
}
