<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('network_two_factor_authentications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique('ntfa_user_unique')->constrained('users', indexName: 'ntfa_user_fk')->cascadeOnDelete();
            $table->text('secret');
            $table->json('recovery_code_hashes')->nullable();
            $table->unsignedBigInteger('last_used_timestep')->nullable();
            $table->timestamp('enabled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('network_two_factor_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users', indexName: 'ntfe_user_fk')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users', indexName: 'ntfe_actor_fk')->nullOnDelete();
            $table->string('event', 48);
            $table->string('ip_hash', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at'], 'ntfe_user_created_idx');
            $table->index(['event', 'created_at'], 'ntfe_event_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_two_factor_events');
        Schema::dropIfExists('network_two_factor_authentications');
    }
};
