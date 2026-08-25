<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{
  Schema::table('network_subscriptions',fn(Blueprint$t)=>$t->unique(['tenant_id','id'],'net_sub_tenant_id_uq'));
  Schema::table('network_entitlements',fn(Blueprint$t)=>$t->unique(['tenant_id','subscription_id','id'],'net_ent_owner_id_uq'));
  Schema::create('network_plan_module_capacities',function(Blueprint$t):void{$t->id();$t->unsignedBigInteger('plan_id');$t->unsignedBigInteger('module_id');$t->string('capability_code',50);$t->unsignedInteger('quantity');$t->timestamps();$t->unique(['plan_id','module_id','capability_code'],'net_plan_mod_cap_uq');$t->foreign(['plan_id','module_id'],'net_plan_mod_cap_rel_fk')->references(['plan_id','module_id'])->on('network_plan_modules')->cascadeOnDelete();});
  Schema::create('network_entitlement_capacities',function(Blueprint$t):void{$t->id();$t->unsignedBigInteger('tenant_id');$t->unsignedBigInteger('subscription_id');$t->unsignedBigInteger('entitlement_id');$t->string('capability_code',50);$t->unsignedInteger('quantity');$t->enum('source',['addon','override']);$t->string('source_key',100);$t->boolean('is_enabled')->default(true);$t->timestamps();$t->unique(['entitlement_id','capability_code','source','source_key'],'net_ent_cap_source_uq');$t->index(['tenant_id','subscription_id','capability_code'],'net_ent_cap_lookup_idx');$t->foreign(['tenant_id','subscription_id'],'net_ent_cap_sub_fk')->references(['tenant_id','id'])->on('network_subscriptions')->cascadeOnDelete();$t->foreign(['tenant_id','subscription_id','entitlement_id'],'net_ent_cap_ent_fk')->references(['tenant_id','subscription_id','id'])->on('network_entitlements')->cascadeOnDelete();});
 }
 public function down():void{Schema::dropIfExists('network_entitlement_capacities');Schema::dropIfExists('network_plan_module_capacities');Schema::table('network_entitlements',fn(Blueprint$t)=>$t->dropUnique('net_ent_owner_id_uq'));Schema::table('network_subscriptions',fn(Blueprint$t)=>$t->dropUnique('net_sub_tenant_id_uq'));}
};
