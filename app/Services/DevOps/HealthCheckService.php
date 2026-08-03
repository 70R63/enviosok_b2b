<?php

namespace App\Services\DevOps;

use App\Models\ZigoHealthCheck;
use App\Services\ZigoDomainResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use App\Services\Shipping\Estafeta\Data\EstafetaOperationRequest;
use App\Services\Shipping\Estafeta\EstafetaConfigurationValidator;
use App\Services\Shipping\Estafeta\EstafetaGateway;
use App\Services\Shipping\ProviderStrategyResolver;
use App\Services\Shipping\UnifiedShippingQuoteService;
use App\Services\Shipping\Data\UnifiedQuoteRequest;

class HealthCheckService
{
    private const CHECKS = [
        'home' => ['/', 'ZIGO'],
        'postal_lookup' => ['/postal-code/lookup/64000', '64000'],
        'colonias' => ['/b2c/cp/colonias?cp=64000', '64000'],
        'crm_login' => ['/crm/login', 'CRM'],
        'negocios_login' => ['/negocios/login', 'ZIGO'],
        'soporte_login' => ['/soporte/login', 'Soporte'],
    ];

    public function __construct(private ZigoDomainResolver $domains, private EstafetaConfigurationValidator $estafetaConfig, private EstafetaGateway $estafeta,private ProviderStrategyResolver $shippingStrategies,private UnifiedShippingQuoteService $unifiedQuotes) {}

    public function run(string $environment, ?int $userId): Collection
    {
        $baseUrl = $this->baseUrl($environment);

        $checks = collect(self::CHECKS)->map(function (array $definition, string $key) use ($baseUrl, $environment, $userId): ZigoHealthCheck {
            [$path, $expected] = $definition; $started = hrtime(true); $httpStatus = null;
            $portal=match($key){'crm_login'=>'crm','negocios_login'=>'b2b','soporte_login'=>'support',default=>null};$target=$portal?rtrim((string)$this->domains->baseUrl($portal),'/'):$baseUrl;
            if($portal&&$target==='')return ZigoHealthCheck::create(['environment'=>$environment,'check_key'=>$key,'status'=>'skipped','http_status'=>null,'duration_ms'=>0,'message'=>'Portal no configurado','checked_by_user_id'=>$userId,'checked_at'=>now()]);
            try {
                $response = Http::acceptJson()->timeout(10)->connectTimeout(5)->withHeaders(['User-Agent' => 'ZIGO-Internal-HealthCheck/1.0'])->get($target . $path);
                $duration = (int) round((hrtime(true) - $started) / 1000000); $httpStatus = $response->status();
                $hasExpected = str_contains($response->body(), $expected);
                $status = $response->successful() && $hasExpected ? 'success' : ($response->successful() ? 'warning' : 'failed');
                $message = $response->successful() ? ($hasExpected ? 'Respuesta válida y contenido mínimo confirmado.' : 'HTTP válido, pero no se encontró el contenido mínimo esperado.') : 'El endpoint respondió con estado no exitoso.';
            } catch (\Throwable $exception) {
                $duration = (int) round((hrtime(true) - $started) / 1000000); $status = 'failed'; $message = 'No fue posible completar la solicitud de health check.';
            }

            return ZigoHealthCheck::create(['environment' => $environment, 'check_key' => $key, 'status' => $status, 'http_status' => $httpStatus, 'duration_ms' => $duration, 'message' => $message, 'checked_by_user_id' => $userId, 'checked_at' => now()]);
        });
        $checks->push($this->estafetaCheck($environment,$userId,'estafeta_contract',function():array{$c=(array)config('zigo_estafeta.contracts');$ok=collect($c)->every(fn($v)=>$v==='confirmed');return[$ok,$ok?'Contratos Estafeta confirmados.':'Contratos Estafeta pendientes: '.implode(', ',array_keys(array_filter($c,fn($v)=>$v!=='confirmed'))).'.'];}));
        $active=(bool)config('zigo_estafeta.enabled',false)&&($environment==='stage'||(bool)config('zigo_estafeta.devops_prd_active_checks',false));
        $checks->push($this->estafetaCheck($environment,$userId,'estafeta_auth',function()use($active):array{if(!$active)return[true,'Check activo deshabilitado por política PRD.','warning'];$this->estafeta->authenticate();return[true,'Autenticación Estafeta válida; token oculto.'];}));
        $checks->push($this->estafetaCheck($environment,$userId,'estafeta_coverage_64000',function()use($active):array{if(!$active)return[true,'Check activo deshabilitado por política PRD.','warning'];$r=$this->estafeta->checkCoverage(EstafetaOperationRequest::fromArray(['origin_postal_code'=>'64000','destination_postal_code'=>'64000']));return[$r->success,$r->success?'Cobertura técnica 64000 validada.':'Cobertura técnica no disponible.'];}));
        $checks->push($this->estafetaCheck($environment,$userId,'estafeta_quote_64000',function()use($active):array{if(!$active)return[true,'Check activo deshabilitado por configuración.','warning'];$r=$this->estafeta->quote(EstafetaOperationRequest::fromArray(['origin_postal_code'=>'64000','destination_postal_code'=>'64000','weight'=>1,'length'=>20,'width'=>20,'height'=>20,'package_type'=>'box']));return[$r->success,$r->success?'Cotización técnica 64000 validada.':'Cotización técnica no disponible.'];}));
        $checks->push($this->estafetaCheck($environment,$userId,'shipping_strategy_estafeta',function()use($environment):array{$r=$this->shippingStrategies->resolve('quote','estafeta',$environment);return[$r['strategy']!=='unavailable','Estrategia quote Estafeta: '.$r['strategy'].'.'];}));
        $shippingActive=$environment==='stage'&&(bool)config('services.shipping.stage_active_checks',false);
        $checks->push($this->estafetaCheck($environment,$userId,'shipping_quote_estafeta_stage',function()use($shippingActive):array{if(!$shippingActive)return[true,'Check activo deshabilitado por configuración.','warning'];$r=$this->unifiedQuotes->quote(new UnifiedQuoteRequest('estafeta','64000','64000',1,20,20,20,'box'),'stage',true);return[$r->success,$r->success?'Cotización Xperta/Estafeta Stage válida.':'Cotización Stage no disponible.'];}));
        return $checks;
    }

    private function estafetaCheck(string $environment,?int $userId,string $key,callable $callback):ZigoHealthCheck
    {
        $started=hrtime(true);$status='failed';$message='No fue posible completar el check Estafeta.';
        try{$result=$callback();$status=$result[2]??($result[0]?'success':'failed');$message=$result[1];}catch(\Throwable $e){}
        return ZigoHealthCheck::create(['environment'=>$environment,'check_key'=>$key,'status'=>$status,'http_status'=>null,'duration_ms'=>(int)round((hrtime(true)-$started)/1000000),'message'=>$message,'checked_by_user_id'=>$userId,'checked_at'=>now()]);
    }

    public function baseUrl(string $environment): string
    {
        if ($environment === 'stage') { $url = (string) config('app.url'); }
        elseif ($environment === 'production') { $url = (string) $this->domains->baseUrl('b2c'); }
        else { throw new \InvalidArgumentException('Ambiente inválido.'); }

        $parts = parse_url($url); $host = strtolower((string) ($parts['host'] ?? ''));
        if (!in_array($parts['scheme'] ?? null, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) { throw new \RuntimeException('La URL base configurada no es segura.'); }
        if ($environment === 'production' && ($parts['scheme'] ?? null) !== 'https') { throw new \RuntimeException('Producción requiere HTTPS.'); }
        return rtrim($url, '/');
    }
}
