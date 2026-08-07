<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;
return new class extends Migration{public function up():void{Schema::create('zigo_payment_export_audits',function(Blueprint $t){$t->id();$t->unsignedBigInteger('user_id');$t->string('tab',20);$t->string('scope',20);$t->json('filters')->nullable();$t->unsignedInteger('record_count');$t->string('filename',191);$t->timestamps();$t->index(['user_id','tab','scope']);});}public function down():void{Schema::dropIfExists('zigo_payment_export_audits');}};
