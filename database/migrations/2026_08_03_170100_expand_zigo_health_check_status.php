<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Support\Facades\DB;
return new class extends Migration {public function up():void{if(DB::getDriverName()==='mysql')DB::statement("ALTER TABLE zigo_health_checks MODIFY status ENUM('success','warning','failed','skipped','unknown') NOT NULL");}public function down():void{if(DB::getDriverName()==='mysql')DB::statement("ALTER TABLE zigo_health_checks MODIFY status ENUM('success','warning','failed') NOT NULL");}};
