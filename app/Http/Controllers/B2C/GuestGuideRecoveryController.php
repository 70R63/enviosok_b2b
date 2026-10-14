<?php
namespace App\Http\Controllers\B2C;
use App\Http\Controllers\Controller;
use App\Models\B2cCotizacion;
use App\Services\Shipping\GuideRecoveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class GuestGuideRecoveryController extends Controller
{
    public function requestForm(){ return view('b2c.guide-recovery-request'); }
    public function requestLink(Request $request,GuideRecoveryService $recovery)
    {
        $data=$request->validate(['correo'=>'required|email|max:255','folio'=>'required|string|max:191']);
        $email=Str::lower(trim($data['correo'])); $folio=trim($data['folio']);
        $consumer = app(\App\Domain\Network\Providers\ProviderConsumerResolver::class)->current();
        if ($consumer && \Illuminate\Support\Facades\Schema::hasTable('network_provider_fulfillments')) {
            $local = B2cCotizacion::where('provider', 'tenant_provider_local')->where('tracking_number', $folio)
                ->whereHas('providerContract.snapshot', fn ($q) => $q->where('consumer_tenant_id', $consumer->id))
                ->where(fn ($q) => $q->whereRaw('LOWER(remitente_email) = ?', [$email])->orWhereHas('user', fn ($q) => $q->whereRaw('LOWER(email) = ?', [$email])))->first();
            if ($local) {
                $recovery->issue($local, $email, 'generated');
                return back()->with('status', 'Si los datos coinciden, recibirás un enlace.');
            }
        }
        $quote=B2cCotizacion::query()->where(function($q)use($folio){$q->where('referencia',$folio);if(ctype_digit($folio))$q->orWhereKey((int)$folio);})->where(function($q)use($email){$q->whereRaw('LOWER(remitente_email) = ?',[$email])->orWhereRaw('LOWER(destinatario_email) = ?',[$email])->orWhereHas('user',fn($u)=>$u->whereRaw('LOWER(email) = ?',[$email]));})->first();
        // Local recovery uses its public folio and effective consumer, never a guessed PK.
        if ($quote?->provider === 'tenant_provider_local') $quote = null;
        if($quote && $quote->hasAccreditedPayment())$recovery->issue($quote,$email,$quote->documento?'generated':'pending');
        return back()->with('status','Si los datos coinciden, recibirás un enlace.');
    }
    public function show(string $token,GuideRecoveryService $recovery)
    {
        $access=$recovery->resolve($token); if(!$access)abort(404);
        $cotizacion=$access->cotizacion;
        if ($cotizacion->provider === 'tenant_provider_local') return app(ProviderFulfillmentController::class)->recovered($access, $token);
        $documentAvailable=(bool)($cotizacion->documento && Storage::disk('local')->exists($cotizacion->documento));
        $classification=$recovery->classification($cotizacion);
        $publicStatus=match($classification){
            GuideRecoveryService::DOCUMENT_MISSING=>'Tu guía fue generada. Estamos preparando tu documento.',
            GuideRecoveryService::QUOTE_EXPIRED,GuideRecoveryService::CREATION_FAILED,GuideRecoveryService::MAX_ATTEMPTS=>'Estamos recuperando tu guía.',
            default=>$documentAvailable?'Tu guía está lista.':(($cotizacion->guia_id||$cotizacion->tracking_number||strtoupper((string)$cotizacion->guia_estatus)==='GENERADA')?'Tu guía fue generada. Estamos preparando tu documento.':'Estamos recuperando tu guía.'),
        };
        return view('b2c.guide-recovery',compact('cotizacion','token','documentAvailable','publicStatus'))->with('recoveryToken',$token);
    }
    public function download(string $token,GuideRecoveryService $recovery)
    {
        $access=$recovery->resolve($token); if(!$access)abort(404); $q=$access->cotizacion;
        if ($q->provider === 'tenant_provider_local') return app(ProviderFulfillmentController::class)->recovered($access, $token, true);
        if(!$q->documento || !Storage::disk('local')->exists($q->documento))abort(404);
        $recovery->audit($q,'DOWNLOAD_PDF','SUCCESS');
        return Storage::disk('local')->download($q->documento,'guia-'.$q->referencia.'.pdf',['Content-Type'=>'application/pdf']);
    }
}
