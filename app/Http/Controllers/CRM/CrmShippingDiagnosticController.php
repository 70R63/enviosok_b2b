<?php
namespace App\Http\Controllers\CRM;
use App\Http\Controllers\Controller;use App\Services\Shipping\ProviderStrategyResolver;use App\Services\Shipping\Diagnostics\XpertaContractInspector;use Illuminate\Http\Request;
final class CrmShippingDiagnosticController extends Controller {public function index(ProviderStrategyResolver $resolver,XpertaContractInspector $inspector){$strategies=[];foreach(['auth','coverage','quote','shipment','tracking','cancellation'] as $op)$strategies[$op]=$resolver->resolve($op,'estafeta',app()->environment('production')?'production':'stage');$xperta=$inspector->inspect();$ready=$strategies['quote']['strategy']==='xperta_estafeta';return view('crm.shipping.diagnostics',compact('strategies','xperta','ready'));}}
