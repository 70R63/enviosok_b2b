<?php
namespace App\Domain\Network\Tenancy;
use App\Domain\Network\Tenancy\Models\Tenant;
final class TenantContext
{
 private ?Tenant $tenant=null;
 public function set(Tenant $tenant):void{$this->tenant=$tenant;}
 public function current():?Tenant{return $this->tenant;}
 public function tenant():?Tenant{return $this->tenant;}
 public function id():?int{return $this->tenant?->getKey();}
 public function hasTenant():bool{return $this->tenant!==null;}
 public function clear():void{$this->tenant=null;}
}
