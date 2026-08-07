<?php
namespace App\Services\Shipping\Xperta;

use App\Models\B2cCotizacion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class XpertaGuidePdfService
{
    public function __construct(private XpertaApiClient $client, private XpertaTokenService $tokens) {}

    public function fetch(B2cCotizacion $quote): B2cCotizacion
    {
        $identifier = trim((string) ($quote->guia_id ?: $quote->tracking_number));
        if ($identifier === '') throw new RuntimeException('PDF_IDENTIFIER_MISSING');
        if ($quote->documento && Storage::disk('local')->exists($quote->documento)) return $quote;
        $service = $this->service((string) ($quote->service_code ?: $quote->servicio));
        $path = $this->client->resolvePath((string) config('services.xperta.guide_pdf_path'), [
            'empresa'=>config('services.xperta.empresa'),'ltd'=>config('services.xperta.ltd'),'service'=>$service,
        ]);
        try {
            $result = $this->client->sendWithMeta('POST', $path, [
                'token'=>$this->tokens->encodedToken(), 'wayBill'=>$identifier,
            ], $this->headers());
            if (($result['http_status'] ?? 0) !== 200 || data_get($result, 'data.success') !== true) throw new RuntimeException('XPERTA_PDF_RESPONSE_REJECTED');
            $encoded = $this->pdfValue((array) $result['data']);
            $binary = base64_decode((string) preg_replace('#^data:application/pdf;base64,#i', '', $encoded), true);
            $max = max(1024, (int) config('services.xperta.guide_pdf_max_bytes', 10485760));
            if ($binary === false || !str_starts_with($binary, '%PDF-') || strlen($binary) < 9 || strlen($binary) > $max) throw new RuntimeException('XPERTA_PDF_INVALID');
            $target = 'private/b2c/labels/'.$quote->id.'/'.Str::uuid().'.pdf';
            if (!Storage::disk('local')->put($target, $binary)) throw new RuntimeException('XPERTA_PDF_STORAGE_FAILED');
            $quote->forceFill(['documento'=>$target,'guia_label_format'=>'PDF','guia_recovered_at'=>now(),'guia_estatus'=>'GENERADA','guia_last_error_code'=>null,'guia_last_error_message'=>null])->save();
            Log::info('Operación Xperta completada', $this->context($quote, $result));
            return $quote->refresh();
        } catch (Throwable $e) {
            Log::warning('Operación Xperta fallida', $this->context($quote, [], $e));
            throw $e;
        }
    }
    private function pdfValue(array $r): string { foreach (['data.data','data.pdf','data.pdfBase64','data.document','pdf','pdfBase64','document'] as $k) { $v=data_get($r,$k); if(is_string($v)&&trim($v)!=='')return trim($v); } throw new RuntimeException('XPERTA_PDF_MISSING'); }
    private function service(string $v): string { $v=strtolower(Str::ascii(trim($v))); if($v==='terrestre'||str_contains($v,'terrestre'))return 'terrestre'; if($v==='diasig'||str_contains($v,'dia sig')||str_contains($v,'siguiente'))return 'diasig'; throw new RuntimeException('XPERTA_PDF_SERVICE_INVALID'); }
    private function headers(): array { return ['Corporativo'=>(string)config('services.xperta.corporativo'),'x-api-key'=>(string)config('services.xperta.api_key'),'Accept'=>'application/json','Content-Type'=>'application/json']; }
    private function context(B2cCotizacion $q,array $r=[],?Throwable $e=null): array { $m=$e instanceof XpertaProviderException?$e->diagnosticMetadata:[]; return ['cotizacion_id'=>$q->id,'operation'=>'fetch_pdf','http_status'=>$r['http_status']??($m['http_status']??null),'provider_code'=>$e instanceof XpertaProviderException?$e->errorCode:($e?class_basename($e):null),'provider_message'=>$e?mb_substr(preg_replace('/(token|api.?key|password|secret)\s*[:=]\s*[^\s,;]+/i','$1=[REDACTED]',$e->getMessage()),0,300):null,'correlation_id'=>$r['correlation_id']??($m['correlation_id']??null),'request_number'=>$q->guia_provider_request_number,'attempts'=>(int)$q->guia_generation_attempts]; }
}
