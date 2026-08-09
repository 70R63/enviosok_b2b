<?php
namespace App\Http\Controllers\Network;
use App\Domain\Payments\Models\{TenantPaymentAttempt,TenantPaymentConnection}; use App\Http\Controllers\Controller;
final class NetworkPaymentController extends Controller{public function __invoke(){return view('network.payments',['connections'=>TenantPaymentConnection::with('tenant')->latest()->paginate(30),'counts'=>TenantPaymentAttempt::selectRaw('status, count(*) aggregate')->groupBy('status')->pluck('aggregate','status')]);}}
