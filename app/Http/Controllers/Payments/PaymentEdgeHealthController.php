<?php
namespace App\Http\Controllers\Payments;
use App\Http\Controllers\Controller;
final class PaymentEdgeHealthController extends Controller
{
    public function __invoke(){return response()->json(['service'=>'zigo-payments','status'=>'ok']);}
}
