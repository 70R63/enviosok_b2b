<?php
namespace Tests\Support;
use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;
final class WhiteLabelTestSchema
{
 public static function create():void{Schema::dropIfExists('network_tenant_brandings');Schema::dropIfExists('network_tenant_domains');Schema::create('network_tenant_domains',function(Blueprint$t){$t->id();$t->unsignedBigInteger('tenant_id');$t->string('domain')->unique();$t->string('type');$t->string('environment');$t->boolean('is_primary')->default(false);$t->string('status');$t->timestamp('verified_at')->nullable();$t->timestamps();});Schema::create('network_tenant_brandings',function(Blueprint$t){$t->id();$t->unsignedBigInteger('tenant_id')->unique();$t->string('brand_name')->nullable();$t->string('logo_path')->nullable();$t->string('primary_color',7)->nullable();$t->string('secondary_color',7)->nullable();$t->string('accent_color',7)->nullable();$t->string('favicon_path')->nullable();$t->string('support_email')->nullable();$t->string('support_phone')->nullable();$t->timestamps();});}
}
