<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys=ON');
        }
        Schema::table('ai_conversation_messages', fn (Blueprint $t) => $t->unique(['tenant_id', 'conversation_id', 'id'], 'ai_msg_handoff_identity_uq'));
        Schema::create('ai_human_handoffs', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid');
            $t->foreignId('tenant_id');
            $t->unsignedBigInteger('conversation_id');
            $t->unsignedBigInteger('requested_by_message_id')->nullable();
            $t->unsignedBigInteger('runtime_run_id')->nullable();
            $t->string('status', 16);
            $t->unsignedBigInteger('assigned_user_id')->nullable();
            $t->timestamp('requested_at');
            $t->timestamp('assigned_at')->nullable();
            $t->timestamp('released_at')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->string('reason_code', 32)->nullable();
            $t->string('safe_reason', 120)->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'uuid'], 'ai_handoff_tenant_uuid_uq');
            $t->unique(['tenant_id', 'id'], 'ai_handoff_tenant_id_uq');
            $t->unique(['tenant_id', 'conversation_id', 'id'], 'ai_handoff_conversation_identity_uq');
            $t->index(['tenant_id', 'status', 'requested_at'], 'ai_handoff_queue_ix');
            $t->foreign('tenant_id', 'ai_handoff_tenant_fk')->references('id')->on('network_tenants')->restrictOnDelete();
            $t->foreign(['tenant_id', 'conversation_id'], 'ai_handoff_conversation_fk')->references(['tenant_id', 'id'])->on('ai_conversations')->restrictOnDelete();
            $t->foreign(['tenant_id', 'conversation_id', 'requested_by_message_id'], 'ai_handoff_message_fk')->references(['tenant_id', 'conversation_id', 'id'])->on('ai_conversation_messages')->restrictOnDelete();
            $t->foreign(['tenant_id', 'runtime_run_id'], 'ai_handoff_run_fk')->references(['tenant_id', 'id'])->on('ai_runtime_runs')->restrictOnDelete();
            $t->foreign('assigned_user_id', 'ai_handoff_assignee_fk')->references('id')->on('users')->restrictOnDelete();
        });
        Schema::table('ai_conversations', function (Blueprint $t): void {
            $t->unsignedBigInteger('active_handoff_id')->nullable()->after('turn_in_progress');
            $t->foreign(['tenant_id', 'id', 'active_handoff_id'], 'ai_conv_active_handoff_fk')->references(['tenant_id', 'conversation_id', 'id'])->on('ai_human_handoffs')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys=ON');
        }
        Schema::table('ai_conversations', function (Blueprint $t): void {
            $t->dropForeign(DB::getDriverName() === 'sqlite' ? ['tenant_id', 'id', 'active_handoff_id'] : 'ai_conv_active_handoff_fk');
            $t->dropColumn('active_handoff_id');
        });
        Schema::dropIfExists('ai_human_handoffs');
        Schema::table('ai_conversation_messages', fn (Blueprint $t) => $t->dropUnique('ai_msg_handoff_identity_uq'));
    }
};
