<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;

final class XpertaConfigCheckCommand extends Command
{
    protected $signature = 'zigo:xperta-config-check';
    protected $description = 'Muestra configuración operativa Xperta redactada; no ejecuta solicitudes.';

    public function handle(): int
    {
        $environment = strtolower(trim((string) config('services.xperta.environment')));
        $host = strtolower((string) parse_url((string) config('services.xperta.base_url'), PHP_URL_HOST));
        $empresa = trim((string) config('services.xperta.empresa'));
        $corporativo = trim((string) config('services.xperta.corporativo'));
        $services = array_values(array_filter(array_map('trim', (array) config('services.xperta.services', []))));
        $rows = [
            ['enabled', config('services.xperta.enabled', false) ? 'true' : 'false'],
            ['environment', $environment ?: 'missing'],
            ['base_host', $host ?: 'missing'],
            ['empresa', $empresa ?: 'missing'],
            ['corporativo', $corporativo ?: 'missing'],
            ['ltd', trim((string) config('services.xperta.ltd')) ?: 'missing'],
            ['services', $services ? implode(', ', $services) : 'missing'],
            ['token_path', $this->path('token_path')],
            ['frequency_path', $this->path('frequency_path')],
            ['quote_path', $this->path('quote_path')],
            ['guide_path', $this->path('guide_path')],
            ['email_present', filled(config('services.xperta.email')) ? 'true' : 'false'],
            ['password_present', filled(config('services.xperta.password')) ? 'true' : 'false'],
            ['api_key_present', filled(config('services.xperta.api_key')) ? 'true' : 'false'],
        ];
        $this->table(['Campo', 'Estado redactado'], $rows);
        if ($environment === 'production' && preg_match('/(^|[.\-])(dev|qa|stage|staging|sandbox)([.\-]|$)/i', $host)) $this->warn('El environment es production, pero el hostname parece no productivo.');
        if ($empresa === '') $this->warn('XPERTA_EMPRESA está vacío.');
        if ($corporativo === '') $this->warn('XPERTA_CORPORATIVO está vacío.');
        if ($services === []) $this->warn('No hay servicios Xperta configurados.');
        if (!filled(config('services.xperta.api_key'))) $this->warn('La API key requerida no está configurada.');
        return self::SUCCESS;
    }

    private function path(string $key): string
    {
        $value = trim((string) config('services.xperta.' . $key));
        if ($value === '') return 'missing';
        return (string) (parse_url($value, PHP_URL_PATH) ?: $value);
    }
}
