<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Http\Requests\CRM\DeployDeploymentRequest;
use App\Http\Requests\CRM\StoreDeploymentRequest;
use App\Models\ZigoDeployment;
use App\Models\ZigoDeploymentLog;
use App\Models\ZigoHealthCheck;
use App\Services\DevOps\DeploymentExecutor;
use App\Services\DevOps\HealthCheckService;
use App\Services\DevOps\PackageManifestValidator;
use App\Services\DevOps\{ReleaseService,EnvironmentComparisonService,PlatformStatusService,IntegrationStatusService,PromotionEligibilityService,DevOpsReportService,DeploymentTimelineViewModel,DevOpsAlertService,DevOpsAuditService};
use App\Models\{ZigoDevOpsAlert,ZigoDevOpsAudit,ZigoDevOpsRelease};
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class CrmDevOpsController extends Controller
{
    public function index(ReleaseService $releases,EnvironmentComparisonService $comparison,PlatformStatusService $platform,IntegrationStatusService $integrations,PromotionEligibilityService $promotion,DevOpsReportService $reports): View
    {
        $this->authorizeRead();
        $latest = ZigoDeployment::with('requester')->latest()->first();
        $environments = collect(['local','stage', 'production'])->mapWithKeys(fn (string $environment): array => [$environment => ZigoDeployment::with('requester')->where('environment', $environment)->latest()->first()]);
        $activeReleases=collect(['stage','production'])->mapWithKeys(fn($e)=>[$e=>$releases->active($e)]);return view('crm.devops.index', ['latest'=>$latest,'environments'=>$environments,'releases'=>$activeReleases,'comparison'=>$comparison->compare(),'platform'=>$platform->current('stage'),'integrations'=>$integrations->current('stage'),'promotion'=>$promotion->evaluate(),'metrics'=>$reports->metrics(),'openAlerts'=>ZigoDevOpsAlert::where('status','open')->latest()->limit(10)->get(),'canDeploy'=>$this->isSysadmin()]);
    }

    public function deployments(): View
    {
        $this->authorizeRead();
        return view('crm.devops.deployments.index', [
            'deployments' => ZigoDeployment::with('requester')->latest()->paginate(20),
            'canDeploy' => $this->isSysadmin(),
            'historyView' => request()->query('view') === 'history',
        ]);
    }

    public function create(): View
    {
        $this->authorizeSysadmin();
        return view('crm.devops.deployments.create');
    }

    public function store(StoreDeploymentRequest $request,DevOpsAuditService $audits): RedirectResponse
    {
        $file = $request->file('package');
        $deployment = ZigoDeployment::create(['environment' => $request->validated('environment'), 'package_name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 'package_sha256' => hash_file('sha256', $file->getRealPath()), 'status' => 'uploaded', 'requested_by_user_id' => $request->user()->id]);
        $stored = Storage::disk('local')->putFileAs('zigo-devops/packages', $file, $deployment->id . '.zip');
        if ($stored === false) {
            $deployment->delete();
            return back()->withInput()->withErrors(['package' => 'No fue posible guardar el paquete en almacenamiento privado.']);
        }
        $this->log($deployment, 'info', 'upload', 'Paquete registrado; contenido pendiente de validación.');
        $audits->record('upload','success',$deployment->environment,$deployment->id);
        return redirect('/deployments/' . $deployment->id)
            ->with('success', 'Paquete registrado. Valídalo antes de desplegar.');
    }

    public function show(ZigoDeployment $deployment,DeploymentTimelineViewModel $timeline): View
    {
        $this->authorizeRead();
        $deployment->load(['requester', 'approver', 'files', 'logs' => fn ($query) => $query->latest('created_at')]);
        $manifest = $this->summary($deployment);
        return view('crm.devops.deployments.show', ['deployment' => $deployment, 'manifest' => $manifest, 'timeline'=>$timeline->build($deployment),'canDeploy' => $this->isSysadmin()]);
    }

    public function validatePackage(ZigoDeployment $deployment, PackageManifestValidator $validator, DeploymentExecutor $executor,DevOpsAuditService $audits): RedirectResponse
    {
        $this->authorizeSysadmin(); abort_unless(in_array($deployment->status, ['uploaded', 'failed'], true), 422);
        $manifest = $validator->validate($executor->packagePath($deployment), $deployment->environment);
        $deployment->update(['package_name' => $manifest['package_name'], 'branch' => $manifest['branch'], 'commit_hash' => $manifest['commit_hash'], 'package_sha256' => $manifest['package_sha256'], 'status' => 'validated', 'summary' => json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'finished_at' => null]);
        $this->log($deployment, 'info', 'validate', 'ZIP, manifiesto, rutas y fingerprints validados.');
        $audits->record('validate','success',$deployment->environment,$deployment->id);
        return back()->with('success', 'Paquete validado correctamente.');
    }

    public function deploy(DeployDeploymentRequest $request, ZigoDeployment $deployment, DeploymentExecutor $executor): RedirectResponse
    {
        $this->authorizeSysadmin();
        if ($deployment->environment === 'production') {
            abort_unless(config('zigo_devops.allow_production'), 403, 'Producción está deshabilitada.');
            $request->validate(['production_confirmation' => ['required', Rule::in(['DESPLEGAR-PRD'])]]);
        }

        abort_unless($deployment->status === 'validated', 422);

        $updated = ZigoDeployment::query()
            ->whereKey($deployment->id)
            ->where('status', 'validated')
            ->update([
                'status' => 'deploying',
                'started_at' => now(),
                'finished_at' => null,
            ]);

        if ($updated !== 1) {
            return back()->withErrors([
                'deployment' => 'El despliegue ya fue iniciado o cambió de estado.',
            ]);
        }

        $deployment->refresh();

        try {
            $executor->deploy($deployment);
            return back()->with('success', 'Despliegue completado.');
        } catch (Throwable $exception) {
            return back()->with('error', 'El despliegue no pudo completarse. Consulta los logs sanitizados.');
        }
    }

    public function rollback(ZigoDeployment $deployment, DeploymentExecutor $executor): RedirectResponse
    {
        $this->authorizeSysadmin();
        try {
            $executor->rollback($deployment);
            return back()->with('success', 'Rollback de archivos completado.');
        } catch (Throwable $exception) {
            return back()->with('error', 'El rollback no pudo completarse. Consulta los logs sanitizados.');
        }
    }

    public function health(): View
    {
        $this->authorizeRead();
        $checks = ZigoHealthCheck::with('checkedBy')->latest('checked_at')->limit(100)->get()->groupBy('environment');
        return view('crm.devops.health', ['checks' => $checks, 'canRunHealth' => $this->isSysadmin() || auth()->user()->hasRol('soporte')]);
    }

    public function runHealth(Request $request, HealthCheckService $service,DevOpsAuditService $audits,DevOpsAlertService $alerts): RedirectResponse
    {
        abort_unless($this->isSysadmin() || auth()->user()?->hasRol('soporte'), 403);
        $data = $request->validate(['environment' => ['required', Rule::in(['stage', 'production'])]]);
        try {
            $results=$service->run($data['environment'], $request->user()->id);$failed=$results->where('status','failed');$audits->record('health',$failed->isEmpty()?'success':'failed',$data['environment']);if($failed->isNotEmpty())$alerts->create('health_failed','critical','Health checks fallidos','Uno o más health checks reportaron fallo.',$data['environment'],null,['failed_keys'=>$failed->pluck('check_key')->all()]);
            return back()->with('success', 'Health checks completados.');
        } catch (Throwable $exception) {
            return back()->with('error', 'No fue posible ejecutar los health checks con la configuración actual.');
        }
    }

    public function releases(ReleaseService $service):View{$this->authorizeRead();return view('crm.devops.enterprise-table',['title'=>'Releases','headers'=>['Ambiente','Estado','Commit','Branch','SHA','Fecha'],'rows'=>$service->history()->limit(100)->get()->map(fn($r)=>[$r->environment,$r->status,$r->commit_hash?:'—',$r->branch?:'—',$r->package_sha256,$r->deployed_at])]);}
    public function comparison(EnvironmentComparisonService $service):View{$this->authorizeRead();$c=$service->compare();return view('crm.devops.comparison',compact('c'));}
    public function alerts():View{$this->authorizeRead();return view('crm.devops.alerts',['alerts'=>ZigoDevOpsAlert::with('deployment')->latest()->paginate(30),'canAcknowledge'=>$this->isSysadmin()]);}
    public function acknowledgeAlert(ZigoDevOpsAlert $alert,DevOpsAlertService $service,DevOpsAuditService $audits):RedirectResponse{$this->authorizeSysadmin();$service->acknowledge($alert,auth()->id());$audits->record('acknowledge_alert','success',$alert->environment,$alert->deployment_id);return back()->with('success','Alerta reconocida.');}
    public function audits():View{$this->authorizeRead();return view('crm.devops.enterprise-table',['title'=>'Auditoría','headers'=>['Fecha','Acción','Ambiente','Resultado','Deployment'],'rows'=>ZigoDevOpsAudit::latest()->limit(200)->get()->map(fn($a)=>[$a->created_at,$a->action,$a->environment?:'—',$a->result,$a->deployment_id?:'—'])]);}
    public function reports(Request $request,DevOpsReportService $service):View{$this->authorizeRead();$filters=$request->only(['environment','status','user_id','from','to','branch','commit']);return view('crm.devops.reports',['metrics'=>$service->metrics($filters),'filters'=>$filters]);}

    private function authorizeRead(): void { abort_unless(auth()->user()?->hasRol('sysadmin') || auth()->user()?->hasRol('admin') || auth()->user()?->hasRol('soporte'), 403); }
    private function authorizeSysadmin(): void { abort_unless($this->isSysadmin(), 403); }
    private function isSysadmin(): bool { return auth()->user()?->hasRol('sysadmin') === true; }
    private function summary(ZigoDeployment $deployment): array { $decoded = json_decode((string) $deployment->summary, true); return is_array($decoded) ? $decoded : []; }
    private function log(ZigoDeployment $deployment, string $level, string $step, string $message): void { ZigoDeploymentLog::create(['deployment_id' => $deployment->id, 'level' => $level, 'step' => $step, 'message' => substr(strip_tags($message), 0, 2000)]); }
}
