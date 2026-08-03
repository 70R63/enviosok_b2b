<?php
namespace App\Console\Commands;
use App\Services\Shipping\Estafeta\{EstafetaConfigurationValidator,EstafetaTokenManager};
use Illuminate\Console\Command;
class EstafetaTokenStatusCommand extends Command
{
    protected $signature = 'zigo:estafeta-token-status {--refresh} {--invalidate} {--confirm=}';
    protected $description = 'Muestra el estado seguro del token Estafeta.';
    public function handle(EstafetaConfigurationValidator $validator, EstafetaTokenManager $tokens): int
    {
        $validation = $validator->validate(); $production = $validation['environment'] === 'production';
        if ($this->option('refresh') && $production && $this->option('confirm') !== 'ESTAFETA-PRD') { $this->error('Refresh PRD bloqueado: use --confirm=ESTAFETA-PRD.'); return self::FAILURE; }
        if ($this->option('invalidate')) $tokens->invalidate();
        if ($this->option('refresh')) $tokens->token(true);
        $status = $tokens->status();
        $this->table(['Campo', 'Estado'], [['Configuración válida', $validation['valid'] ? 'sí' : 'no'], ['Token cacheado', $status['cached'] ? 'sí' : 'no'], ['Tiempo restante (s)', $status['remaining_seconds']], ['Ambiente', $status['environment']]]);
        return $validation['valid'] ? self::SUCCESS : self::FAILURE;
    }
}
