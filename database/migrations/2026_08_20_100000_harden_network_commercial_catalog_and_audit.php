<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{
  Schema::table('network_commercial_products',function(Blueprint$t):void{$t->boolean('is_public')->default(false)->after('is_active');$t->unsignedInteger('display_order')->nullable()->after('sort_order');$t->timestamp('archived_at')->nullable()->after('display_order');$t->index(['is_public','is_active','display_order'],'network_products_public_idx');});
  Schema::create('network_admin_audit_events',function(Blueprint$t):void{$t->id();$t->foreignId('actor_user_id');$t->string('action',100);$t->string('entity_type',120);$t->unsignedBigInteger('entity_id')->nullable();$t->json('before_json')->nullable();$t->json('after_json')->nullable();$t->timestamp('created_at')->useCurrent();$t->foreign('actor_user_id','network_audit_actor_fk')->references('id')->on('users');$t->index(['entity_type','entity_id'],'network_audit_entity_idx');$t->index(['actor_user_id','created_at'],'network_audit_actor_created_idx');});
 }
 public function down():void{Schema::dropIfExists('network_admin_audit_events');Schema::table('network_commercial_products',function(Blueprint$t):void{$t->dropIndex('network_products_public_idx');$t->dropColumn(['is_public','display_order','archived_at']);});}
};
