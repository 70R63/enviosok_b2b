<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('ai_provider_rates', function(Blueprint $t){$t->unsignedBigInteger('input_microusd_per_million')->default(0);$t->unsignedBigInteger('cached_input_microusd_per_million')->default(0);$t->unsignedBigInteger('output_microusd_per_million')->default(0);});
  Schema::table('ai_cost_budgets', function(Blueprint $t){$t->unsignedBigInteger('budget_microusd')->nullable();});
  Schema::table('ai_cost_ledger', function(Blueprint $t){$t->unsignedBigInteger('actual_cost_microusd')->nullable();$t->unsignedBigInteger('released_microusd')->default(0);$t->unsignedBigInteger('reserved_microusd')->default(0);$t->json('rate_snapshot')->nullable();});
  Schema::create('ai_cost_reservations', function(Blueprint $t){$t->id();$t->unsignedBigInteger('tenant_id');$t->unsignedBigInteger('runtime_run_id')->unique();$t->string('provider',40);$t->string('model',120);$t->string('billing_period',20);$t->unsignedBigInteger('reserved_microusd');$t->unsignedBigInteger('actual_cost_microusd')->nullable();$t->unsignedBigInteger('released_microusd')->default(0);$t->string('status',20)->default('reserved');$t->string('idempotency_key',160)->unique();$t->json('rate_snapshot')->nullable();$t->timestamps();});
 }
 public function down(): void {Schema::dropIfExists('ai_cost_reservations');Schema::table('ai_cost_ledger',function(Blueprint $t){$t->dropColumn(['actual_cost_microusd','released_microusd','reserved_microusd','rate_snapshot']);});Schema::table('ai_cost_budgets',fn(Blueprint $t)=>$t->dropColumn('budget_microusd'));Schema::table('ai_provider_rates',function(Blueprint $t){$t->dropColumn(['input_microusd_per_million','cached_input_microusd_per_million','output_microusd_per_million']);});}
};
