<?php
namespace App\Http\Controllers\CRM;
use App\Http\Controllers\Controller;
use App\Models\B2cCotizacion;
use App\Services\Shipping\B2cXpertaGuideFlowService;
use App\Services\Shipping\GuideRecoveryService;
use App\Services\Shipping\Xperta\XpertaGuidePdfService;
use App\Services\Shipping\Xperta\XpertaGuideResponseNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class CrmGuideRecoveryController extends Controller
{
    public function create(B2cCotizacion $cotizacion,B2cXpertaGuideFlowService $flow,GuideRecoveryService $recovery){ if($cotizacion->guia_id||$cotizacion->tracking_number)abort(409);$result=$flow->generateAfterConfirmedPayment($cotizacion);$recovery->audit($result,'RETRY_CREATE','SUCCESS');$email=strtolower(trim((string)($result->remitente_email?:$result->destinatario_email?:$result->user?->email)));if($email!=='')$recovery->guideAvailable($result,$email);return back()->with('success','Reintento procesado.'); }
    public function normalize(B2cCotizacion $cotizacion,XpertaGuideResponseNormalizer $normalizer,GuideRecoveryService $recovery)
    {
        $n=$normalizer->normalize((array)$cotizacion->guia_response_snapshot);if(!$normalizer->isRecoverable($n))throw new RuntimeException('El snapshot no contiene una guía recuperable.');
        if(!$cotizacion->guia_id&&!$cotizacion->tracking_number)$cotizacion->forceFill(['guia_id'=>$n['waybill'],'tracking_number'=>$n['tracking'],'guia_provider_reference'=>$n['provider_reference'],'guia_provider_request_number'=>ctype_digit((string)$n['provider_request_number'])?(int)$n['provider_request_number']:null,'guia_provider_status'=>'GENERADA','guia_estatus'=>'GENERADA_SIN_DOCUMENTO','estatus'=>'GUIA_GENERADA','guia_recovered_at'=>now()])->save();
        $recovery->audit($cotizacion,'NORMALIZE_SNAPSHOT','SUCCESS');return back()->with('success','Snapshot normalizado.');
    }
    public function pdf(B2cCotizacion $cotizacion, XpertaGuidePdfService $pdf, GuideRecoveryService $recovery)
    {
        $case = $recovery->syncCase($cotizacion);
        if (!$case || $case->classification !== GuideRecoveryService::DOCUMENT_MISSING) abort(409);

        try {
            $result = $pdf->fetch($cotizacion);
            $recovery->audit($result, 'FETCH_PDF', 'SUCCESS');
            $case->forceFill(['status' => 'RESOLVED'])->save();
            $email = strtolower(trim((string) ($result->remitente_email ?: $result->destinatario_email ?: $result->user?->email)));
            if ($email !== '') {
                try {
                    $recovery->guideAvailable($result, $email);
                } catch (Throwable $mailException) {
                    return redirect()->route('crm.guias.index')->with('error', 'PDF recuperado, pero no fue posible enviar el correo de guía disponible.');
                }
            }

            return redirect()->route('crm.guias.index')->with('success', 'PDF de la guía recuperado correctamente.');
        } catch (Throwable $exception) {
            $recovery->audit($cotizacion->fresh(), 'FETCH_PDF', 'FAILURE', $pdf->diagnostics($exception));

            return redirect()->route('crm.guias.index')->with('error', 'No fue posible recuperar el PDF. La guía permanece registrada y puede reintentarse.');
        }
    }
    public function link(B2cCotizacion $cotizacion,GuideRecoveryService $recovery)
    {
        $email=strtolower(trim((string)($cotizacion->remitente_email?:$cotizacion->destinatario_email?:$cotizacion->user?->email)));
        if($email==='') return redirect()->route('crm.guias.index')->with('error','La cotización no tiene un correo de cliente válido.');
        try {
            $recovery->issue($cotizacion,$email,$cotizacion->documento?'generated':'pending');
            return redirect()->route('crm.guias.index')->with('success','Enlace de recuperación enviado correctamente.');
        } catch (Throwable $exception) {
            Log::warning('No fue posible emitir el enlace CRM de recuperación', ['cotizacion_id'=>$cotizacion->id,'exception'=>get_class($exception),'error_code'=>$exception->getMessage()]);
            return redirect()->route('crm.guias.index')->with('error','No fue posible enviar el enlace de recuperación. Inténtalo nuevamente.');
        }
    }
    public function requote(Request $request,B2cCotizacion $cotizacion,GuideRecoveryService $recovery){$data=$request->validate(['nuevo_total'=>'required|numeric|min:0.01']);$case=$recovery->syncCase($cotizacion);if(!$case||$case->classification!==GuideRecoveryService::QUOTE_EXPIRED)abort(409);$case->update(['requote_total'=>$data['nuevo_total'],'requote_reviewed_at'=>now(),'requote_reviewed_by'=>auth()->id()]);$recovery->audit($cotizacion,'ADMIN_REQUOTE','SUCCESS',['paid'=>(float)$case->original_paid_amount,'new_total'=>(float)$data['nuevo_total'],'difference'=>round((float)$data['nuevo_total']-(float)$case->original_paid_amount,2)]);return back()->with('success','Recotización registrada para revisión; no se generó ningún cargo.');}
}
