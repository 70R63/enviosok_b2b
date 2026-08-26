<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::table('network_subscriptions', function(Blueprint $t){$t->string('provider_subscription_id',191)->nullable()->unique('network_sub_provider_sub_uq');$t->string('provider_plan_id',191)->nullable();$t->string('provider_status',40)->nullable();$t->timestamp('next_payment_date')->nullable();$t->string('billing_frequency',12)->nullable();$t->boolean('cancel_at_period_end')->default(false);}); } public function down(): void { Schema::table('network_subscriptions', fn(Blueprint $t)=>$t->dropUnique('network_sub_provider_sub_uq')); Schema::table('network_subscriptions', fn(Blueprint $t)=>$t->dropColumn(['provider_subscription_id','provider_plan_id','provider_status','next_payment_date','billing_frequency','cancel_at_period_end'])); } };
