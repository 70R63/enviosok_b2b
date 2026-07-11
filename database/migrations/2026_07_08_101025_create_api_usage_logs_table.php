<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
{
    Schema::create('api_usage_logs', function (Blueprint $table) {
        $table->id();
        $table->foreignId('api_client_id')->nullable()->constrained('api_clients')->nullOnDelete();
        $table->foreignId('api_key_id')->nullable()->constrained('api_keys')->nullOnDelete();
        $table->string('endpoint');
        $table->string('method', 10);
        $table->integer('status_code')->nullable();
        $table->integer('response_time_ms')->nullable();
        $table->string('ip')->nullable();
        $table->text('error_message')->nullable();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('api_usage_logs');
    }
};
