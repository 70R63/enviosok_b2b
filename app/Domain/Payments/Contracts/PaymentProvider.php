<?php
namespace App\Domain\Payments\Contracts;
use App\Domain\Network\Channels\B2C\Models\TenantCustomerCheckout; use App\Domain\Payments\Models\{TenantPaymentAttempt,TenantPaymentConnection}; use Illuminate\Http\Request;
interface PaymentProvider{
 public function authorizationUrl(string $state,string $challenge):string; public function exchangeAuthorizationCode(string $code,string $verifier):array; public function storeConnectionTokens(TenantPaymentConnection $connection,array $payload):void; public function refreshConnectionIfNeeded(TenantPaymentConnection $connection):TenantPaymentConnection;
 public function createCheckout(TenantPaymentConnection $connection,TenantPaymentAttempt $attempt,TenantCustomerCheckout $checkout):array; public function retrievePayment(TenantPaymentConnection $connection,string $paymentId):array; public function validateWebhook(Request $request,string $dataId):bool;
}
