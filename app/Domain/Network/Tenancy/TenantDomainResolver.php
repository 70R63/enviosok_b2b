<?php
namespace App\Domain\Network\Tenancy;
use App\Domain\Network\Tenancy\Models\TenantDomain;
final class TenantDomainResolver
{
 public function __construct(private TenantContext $context){}
 public function resolve(string $host):?TenantDomain{$this->context->clear();$domain=TenantDomain::with('tenant')->where('domain',$this->normalize($host))->where('status','verified')->first();if(!$domain||$domain->tenant?->status!=='active')return null;$this->context->set($domain->tenant);return $domain;}
 public function normalize(string $host):string{return TenantDomain::normalize($host);}
}
