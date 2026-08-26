<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::table('network_subscriptions',function(Blueprint $t){$t->unsignedBigInteger('pending_plan_id')->nullable();$t->timestamp('pending_effective_at')->nullable();$t->index(['tenant_id','pending_effective_at'],'net_sub_pending_ix');}); }
 public function down(): void { Schema::table('network_subscriptions',function(Blueprint $t){$t->dropIndex('net_sub_pending_ix');$t->dropColumn(['pending_plan_id','pending_effective_at']);}); }
};
