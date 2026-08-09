<?php
namespace App\Domain\Network\Commerce\Contracts;use App\Domain\Network\Commerce\Models\{PlatformPaymentAttempt,TenantSaasOrder};use Illuminate\Http\Request;
interface PlatformPaymentProvider{public function createCheckout(PlatformPaymentAttempt$a,TenantSaasOrder$o):array;public function retrievePayment(string$id):array;public function validateWebhook(Request$r,string$id):bool;public function accountId():string;}
