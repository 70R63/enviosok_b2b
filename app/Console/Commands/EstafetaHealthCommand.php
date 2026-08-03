<?php
namespace App\Console\Commands;
use App\Services\Shipping\Estafeta\Data\EstafetaOperationRequest;
use App\Services\Shipping\Estafeta\{EstafetaConfigurationValidator,EstafetaGateway};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
class EstafetaHealthCommand extends Command
{
    protected $signature = 'zigo:estafeta-health {--environment=stage} {--check=all} {--cp-origin=64000} {--cp-destination=64000} {--weight=1} {--length=20} {--width=20} {--height=20} {--package-type=box} {--dry-run} {--confirm-stage=}';
    protected $description = 'Diagnóstico no transaccional y sanitizado de Estafeta.';
    public function handle(EstafetaConfigurationValidator $validator, EstafetaGateway $gateway): int
    {
        $environment = strtolower((string) $this->option('environment')); $check = strtolower((string) $this->option('check'));
        if ($environment !== 'stage' || !in_array($check, ['config','contract','auth','coverage','quote','all'], true)) { $this->error('Esta fase solo permite Stage y checks conocidos.'); return self::INVALID; }
        config(['zigo_estafeta.environment' => $environment]); $steps = []; $wanted = $check === 'all' ? ['config','contract','auth','coverage','quote'] : [$check];
        foreach ($wanted as $step) { $started = hrtime(true); $status = 'success'; $message = 'OK';
            try {
                if ($step === 'config') { $result = $validator->validate(); $status = $result['valid'] ? 'success' : 'failed'; $message = $result['valid'] ? 'Configuración válida.' : 'Configuración incompleta: ' . implode(', ', $result['missing']); }
                elseif ($step === 'contract') { $contracts=(array)config('zigo_estafeta.contracts');$status=collect($contracts)->every(fn($v)=>$v==='confirmed')?'success':'failed';$message='auth='.($contracts['auth']??'unknown').', coverage='.($contracts['coverage']??'unknown').', quote='.($contracts['quote']??'unknown'); }
                elseif ($this->option('confirm-stage') !== 'ESTAFETA-STAGE') { $status='skipped';$message='Requiere --confirm-stage=ESTAFETA-STAGE.'; }
                elseif ($this->option('dry-run')) { $status = 'skipped'; $message = 'Omitido por --dry-run.'; }
                elseif ($step === 'auth') { $gateway->authenticate(); $message = 'Autenticación válida; token no mostrado.'; }
                else { $request = EstafetaOperationRequest::fromArray(['origin_postal_code' => $this->option('cp-origin'), 'destination_postal_code' => $this->option('cp-destination')]); $result = $step === 'coverage' ? $gateway->checkCoverage($request) : $gateway->quote($request); $status = $result->success ? 'success' : 'failed'; $message = $result->classification; }
            } catch (\Throwable $e) { $status = 'failed'; $message = 'No fue posible completar el paso de forma segura.'; }
            $steps[] = ['step' => $step, 'status' => $status, 'duration_ms' => (int) round((hrtime(true)-$started)/1000000), 'message' => $message];
        }
        $ready = collect($steps)->whereIn('step', ['config','coverage','quote'])->every(fn ($s) => in_array($s['status'], ['success','skipped'], true)) && collect($steps)->where('status','failed')->isEmpty();
        $ready = $ready && collect($steps)->where('step','contract')->where('status','success')->isNotEmpty();
        $report = ['generated_at' => now()->toIso8601String(), 'environment' => $environment, 'dry_run' => (bool) $this->option('dry-run'), 'steps' => $steps, 'READY_FOR_B2C_QUOTES' => $ready];
        Storage::disk('local')->put('private/estafeta-health/health-' . now()->format('Ymd-His') . '.json', json_encode($report, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
        $this->table(['Paso','Estado','ms','Mensaje'], array_map(fn ($s) => array_values($s), $steps)); $this->line('READY_FOR_B2C_QUOTES=' . ($ready ? 'true' : 'false'));
        return $ready ? self::SUCCESS : self::FAILURE;
    }
}
