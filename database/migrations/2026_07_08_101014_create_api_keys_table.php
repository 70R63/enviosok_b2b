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
    Schema::create('api_keys', function (Blueprint $table) {
        $table->id();
        $table->foreignId('api_client_id')->constrained('api_clients')->cascadeOnDelete();
        $table->string('name');
        $table->string('key_hash')->unique();
        $table->string('key_prefix', 20)->index();
        $table->string('environment')->default('sandbox');
        $table->boolean('active')->default(true);
        $table->timestamp('last_used_at')->nullable();
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
        Schema::dropIfExists('api_keys');
    }
};
