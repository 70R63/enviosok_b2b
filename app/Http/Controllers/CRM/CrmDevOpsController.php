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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class CrmDevOpsController extends Controller
{
    public function index(): View
    {
        $this->authorizeRead();
        $latest = ZigoDeployment::with('requester')->latest()->first();
        $environments = collect(['stage', 'production'])->mapWithKeys(fn (string $environment): array => [$environment => ZigoDeployment::with('requester')->where('environment', $environment)->latest()->first()]);
        return view('crm.devops.index', ['latest' => $latest, 'environments' => $environments, 'canDeploy' => $this->isSysadmin()]);
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

    public function store(StoreDeploymentRequest $request): RedirectResponse
    {
        $file = $request->file('package');
        $deployment = ZigoDeployment::create(['environment' => $request->validated('environment'), 'package_name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 'package_sha256' => hash_file('sha256', $file->getRealPath()), 'status' => 'uploaded', 'requested_by_user_id' => $request->user()->id]);
        $stored = Storage::disk('local')->putFileAs('zigo-devops/packages', $file, $deployment->id . '.zip');
        if ($stored === false) {
            $deployment->delete();
            return back()->withInput()->withErrors(['package' => 'No fue posible guardar el paquete en almacenamiento privado.']);
        }
        $this->log($deployment, 'info', 'upload', 'Paquete registrado; contenido pendiente de validación.');
        return redirect('/deployments/' . $deployment->id)
            ->with('success', 'Paquete registrado. Valídalo antes de desplegar.');
    }

    public function show(ZigoDeployment $deployment): View
    {
        $this->authorizeRead();
        $deployment->load(['requester', 'approver', 'files', 'logs' => fn ($query) => $query->latest('created_at')]);
        $manifest = $this->summary($deployment);
        return view('crm.devops.deployments.show', ['deployment' => $deployment, 'manifest' => $manifest, 'canDeploy' => $this->isSysadmin()]);
    }

    public function validatePackage(ZigoDeployment $deployment, PackageManifestValidator $validator, DeploymentExecutor $executor): RedirectResponse
    {
        $this->authorizeSysadmin(); abort_unless(in_array($deployment->status, ['uploaded', 'failed'], true), 422);
        $manifest = $validator->validate($executor->packagePath($deployment), $deployment->environment);
        $deployment->update(['package_name' => $manifest['package_name'], 'branch' => $manifest['branch'], 'commit_hash' => $manifest['commit_hash'], 'package_sha256' => $manifest['package_sha256'], 'status' => 'validated', 'summary' => json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'finished_at' => null]);
        $this->log($deployment, 'info', 'validate', 'ZIP, manifiesto, rutas y fingerprints validados.');
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

    public function runHealth(Request $request, HealthCheckService $service): RedirectResponse
    {
        abort_unless($this->isSysadmin() || auth()->user()?->hasRol('soporte'), 403);
        $data = $request->validate(['environment' => ['required', Rule::in(['stage', 'production'])]]);
        try {
            $service->run($data['environment'], $request->user()->id);
            return back()->with('success', 'Health checks completados.');
        } catch (Throwable $exception) {
            return back()->with('error', 'No fue posible ejecutar los health checks con la configuración actual.');
        }
    }

    private function authorizeRead(): void { abort_unless(auth()->user()?->hasRol('sysadmin') || auth()->user()?->hasRol('admin') || auth()->user()?->hasRol('soporte'), 403); }
    private function authorizeSysadmin(): void { abort_unless($this->isSysadmin(), 403); }
    private function isSysadmin(): bool { return auth()->user()?->hasRol('sysadmin') === true; }
    private function summary(ZigoDeployment $deployment): array { $decoded = json_decode((string) $deployment->summary, true); return is_array($decoded) ? $decoded : []; }
    private function log(ZigoDeployment $deployment, string $level, string $step, string $message): void { ZigoDeploymentLog::create(['deployment_id' => $deployment->id, 'level' => $level, 'step' => $step, 'message' => substr(strip_tags($message), 0, 2000)]); }
}
