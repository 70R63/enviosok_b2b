<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;
return new class extends Migration{
 public function up():void{
  Schema::create('zigo_notification_deliveries',function(Blueprint $t){$t->id();$t->string('event_key',191)->unique();$t->string('event_type',60);$t->string('notifiable_type',191);$t->unsignedBigInteger('notifiable_id');$t->json('recipients')->nullable();$t->string('status',20)->default('PENDING');$t->unsignedSmallInteger('attempts')->default(0);$t->timestamp('sent_at')->nullable();$t->timestamp('failed_at')->nullable();$t->string('last_error',500)->nullable();$t->timestamps();$t->index(['notifiable_type','notifiable_id']);});
  Schema::create('zigo_guide_export_audits',function(Blueprint $t){$t->id();$t->unsignedBigInteger('user_id');$t->string('scope',20);$t->json('filters')->nullable();$t->unsignedInteger('record_count');$t->string('filename',191);$t->timestamps();});
  Schema::create('b2c_invoice_request_events',function(Blueprint $t){$t->id();$t->foreignId('invoice_request_id')->constrained('b2c_invoice_requests')->cascadeOnDelete();$t->unsignedBigInteger('user_id')->nullable();$t->string('event_type',40);$t->string('previous_status',30)->nullable();$t->string('new_status',30);$t->string('public_reason',2000)->nullable();$t->text('internal_note')->nullable();$t->json('replaced_files')->nullable();$t->timestamps();});
  Schema::table('b2c_invoice_requests',function(Blueprint $t){$t->string('rejection_category',60)->nullable();$t->timestamp('resubmitted_at')->nullable();});
  Schema::table('b2c_fiscal_profiles',fn(Blueprint $t)=>$t->string('constancia_path',500)->nullable());
 }
 public function down():void{Schema::table('b2c_fiscal_profiles',fn(Blueprint $t)=>$t->dropColumn('constancia_path'));Schema::table('b2c_invoice_requests',fn(Blueprint $t)=>$t->dropColumn(['rejection_category','resubmitted_at']));Schema::dropIfExists('b2c_invoice_request_events');Schema::dropIfExists('zigo_guide_export_audits');Schema::dropIfExists('zigo_notification_deliveries');}
};
