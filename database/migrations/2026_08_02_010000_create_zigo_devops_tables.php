<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zigo_deployments', function (Blueprint $table): void {
            $table->id();
            $table->enum('environment', ['stage', 'production']);
            $table->string('package_name');
            $table->string('branch')->nullable();
            $table->string('commit_hash', 64)->nullable();
            $table->string('package_sha256', 64);
            $table->enum('status', ['uploaded', 'validated', 'deploying', 'success', 'failed', 'rolled_back'])->default('uploaded');
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('backup_path')->nullable();
            $table->boolean('rollback_available')->default(false);
            $table->text('summary')->nullable();
            $table->timestamps();
            $table->index(['environment', 'status', 'created_at']);
        });

        Schema::create('zigo_deployment_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('deployment_id')->constrained('zigo_deployments')->cascadeOnDelete();
            $table->string('relative_path');
            $table->boolean('existed_before');
            $table->string('backup_fingerprint', 64)->nullable();
            $table->string('deployed_fingerprint', 64)->nullable();
            $table->timestamps();
            $table->unique(['deployment_id', 'relative_path']);
        });

        Schema::create('zigo_deployment_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('deployment_id')->constrained('zigo_deployments')->cascadeOnDelete();
            $table->string('level', 20);
            $table->string('step', 80);
            $table->text('message');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['deployment_id', 'created_at']);
        });

        Schema::create('zigo_health_checks', function (Blueprint $table): void {
            $table->id();
            $table->enum('environment', ['stage', 'production']);
            $table->string('check_key', 80);
            $table->enum('status', ['success', 'warning', 'failed']);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('message', 500)->nullable();
            $table->foreignId('checked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at');
            $table->timestamps();
            $table->index(['environment', 'check_key', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zigo_health_checks');
        Schema::dropIfExists('zigo_deployment_logs');
        Schema::dropIfExists('zigo_deployment_files');
        Schema::dropIfExists('zigo_deployments');
    }
};
