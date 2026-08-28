<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') DB::statement('PRAGMA foreign_keys=ON');

        Schema::table('ai_runtime_runs', function (Blueprint $t): void {
            $t->char('conversation_turn_token_hash', 64)->nullable()->after('agent_version_id');
            $t->unique(['tenant_id','agent_id','agent_version_id','id','conversation_turn_token_hash'], 'ai_runtime_turn_identity_uq');
        });
        if (Schema::hasTable('ai_action_runs')) Schema::table('ai_action_runs',function(Blueprint$t):void{$t->char('conversation_turn_token_hash',64)->nullable()->after('runtime_run_id');$t->timestamp('execution_expires_at')->nullable()->after('started_at');$t->index(['tenant_id','conversation_id','conversation_turn_token_hash'],'ai_actions_turn_ix');});

        Schema::table('ai_conversation_messages', function (Blueprint $t): void {
            $t->unsignedBigInteger('agent_id')->nullable()->after('conversation_id');
            $t->unsignedBigInteger('agent_version_id')->nullable()->after('agent_id');
            $t->char('turn_token_hash', 64)->nullable()->after('agent_version_id');
        });
        if (DB::table('ai_conversation_messages as m')->leftJoin('ai_conversations as c','c.id','=','m.conversation_id')->whereNull('c.id')->exists()) throw new \RuntimeException('AI turn integrity backfill found an orphan conversation message.');
        DB::table('ai_conversation_messages as m')->orderBy('m.id')->select('m.id', 'm.conversation_id')->get()->each(function ($message): void {
            $conversation = DB::table('ai_conversations')->where('id', $message->conversation_id)->first(['agent_id', 'agent_version_id']);
            if ($conversation) DB::table('ai_conversation_messages')->where('id', $message->id)->update(['agent_id' => $conversation->agent_id, 'agent_version_id' => $conversation->agent_version_id]);
        });
        $linked=DB::table('ai_conversation_messages')->whereNotNull('runtime_run_id')->orderBy('id')->get(['id','tenant_id','conversation_id','agent_id','agent_version_id','runtime_run_id','turn_token_hash']);
        if($linked->groupBy('runtime_run_id')->contains(fn($messages)=>$messages->count()>1))throw new \RuntimeException('AI turn integrity backfill found a Runtime Run linked to multiple messages.');
        $linked->each(function($message):void{$run=DB::table('ai_runtime_runs')->where('id',$message->runtime_run_id)->first(['tenant_id','agent_id','agent_version_id','conversation_turn_token_hash']);if(!$run||(int)$run->tenant_id!==(int)$message->tenant_id||(int)$run->agent_id!==(int)$message->agent_id||(int)$run->agent_version_id!==(int)$message->agent_version_id)throw new \RuntimeException('AI turn integrity backfill found an incompatible Runtime Run association.');$hash=$message->turn_token_hash?:$run->conversation_turn_token_hash?:hash('sha256',random_bytes(32));if(($message->turn_token_hash&&!hash_equals($message->turn_token_hash,$hash))||($run->conversation_turn_token_hash&&!hash_equals($run->conversation_turn_token_hash,$hash)))throw new \RuntimeException('AI turn integrity backfill found conflicting turn ownership.');DB::table('ai_conversation_messages')->where('id',$message->id)->update(['turn_token_hash'=>$hash]);DB::table('ai_runtime_runs')->where('id',$message->runtime_run_id)->update(['conversation_turn_token_hash'=>$hash]);});
        Schema::table('ai_conversation_messages', function (Blueprint $t): void {
            $t->unique(['tenant_id','conversation_id','id','turn_token_hash'], 'ai_msg_turn_identity_uq');
            $t->foreign(['tenant_id','conversation_id','agent_id','agent_version_id'], 'ai_msg_conv_identity_fk')->references(['tenant_id','id','agent_id','agent_version_id'])->on('ai_conversations')->restrictOnDelete();
            $t->foreign(['tenant_id','agent_id','agent_version_id','runtime_run_id','turn_token_hash'], 'ai_msg_runtime_identity_fk')->references(['tenant_id','agent_id','agent_version_id','id','conversation_turn_token_hash'])->on('ai_runtime_runs')->restrictOnDelete();
        });

        Schema::table('ai_conversations', function (Blueprint $t): void {
            $t->char('active_turn_token_hash', 64)->nullable()->after('turn_in_progress');
            $t->unsignedBigInteger('active_turn_assistant_message_id')->nullable()->after('active_turn_token_hash');
            $t->timestamp('active_turn_started_at')->nullable()->after('active_turn_assistant_message_id');
            $t->timestamp('active_turn_heartbeat_at')->nullable()->after('active_turn_started_at');
            $t->timestamp('active_turn_expires_at')->nullable()->after('active_turn_heartbeat_at');
            $t->index(['tenant_id','turn_in_progress','active_turn_expires_at'], 'ai_conv_turn_recovery_ix');
            $t->foreign(['tenant_id','id','active_turn_assistant_message_id','active_turn_token_hash'], 'ai_conv_active_turn_fk')->references(['tenant_id','conversation_id','id','turn_token_hash'])->on('ai_conversation_messages')->restrictOnDelete();
        });

        if(Schema::hasTable('ai_action_runs'))DB::table('ai_action_runs')->orderBy('id')->get(['id','source_message_id'])->each(function($action):void{$message=DB::table('ai_conversation_messages')->where('id',$action->source_message_id)->first(['turn_token_hash']);if(!$message)throw new \RuntimeException('AI Action turn backfill found an orphan source message.');DB::table('ai_action_runs')->where('id',$action->id)->update(['conversation_turn_token_hash'=>$message->turn_token_hash]);});

        $lease = min(3600, max(60, (int) config('ai.conversation_turn_lease_seconds', 120)));
        DB::table('ai_conversations')->where('turn_in_progress', true)->orderBy('id')->get(['id'])->each(function ($conversation) use ($lease): void {
            $pending = DB::table('ai_conversation_messages')->where('conversation_id', $conversation->id)->where('role', 'assistant')->where('status', 'pending')->orderByDesc('sequence')->first(['id']);
            if (! $pending) return;
            $existing=DB::table('ai_conversation_messages')->where('id',$pending->id)->value('turn_token_hash');$hash = is_string($existing)?$existing:hash('sha256', random_bytes(32));
            $now = now();
            DB::table('ai_conversation_messages')->where('id', $pending->id)->update(['turn_token_hash' => $hash]);
            DB::table('ai_conversations')->where('id', $conversation->id)->update([
                'active_turn_token_hash' => $hash,
                'active_turn_assistant_message_id' => $pending->id,
                'active_turn_started_at' => $now,
                'active_turn_heartbeat_at' => $now,
                'active_turn_expires_at' => $now->copy()->addSeconds($lease),
            ]);
        });
        $this->createIntegrityTriggers();
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') DB::statement('PRAGMA foreign_keys=ON');
        $this->dropIntegrityTriggers();
        Schema::table('ai_conversations', function (Blueprint $t): void {
            $t->dropForeign(DB::getDriverName() === 'sqlite' ? ['tenant_id','id','active_turn_assistant_message_id','active_turn_token_hash'] : 'ai_conv_active_turn_fk');
            $t->dropIndex('ai_conv_turn_recovery_ix');
            $t->dropColumn(['active_turn_token_hash','active_turn_assistant_message_id','active_turn_started_at','active_turn_heartbeat_at','active_turn_expires_at']);
        });
        Schema::table('ai_conversation_messages', function (Blueprint $t): void {
            $t->dropForeign(DB::getDriverName() === 'sqlite' ? ['tenant_id','agent_id','agent_version_id','runtime_run_id','turn_token_hash'] : 'ai_msg_runtime_identity_fk');
            $t->dropForeign(DB::getDriverName() === 'sqlite' ? ['tenant_id','conversation_id','agent_id','agent_version_id'] : 'ai_msg_conv_identity_fk');
            $t->dropUnique('ai_msg_turn_identity_uq');
            $t->dropColumn(['agent_id','agent_version_id','turn_token_hash']);
        });
        Schema::table('ai_runtime_runs', function (Blueprint $t): void {
            $t->dropUnique('ai_runtime_turn_identity_uq');
            $t->dropColumn('conversation_turn_token_hash');
        });
        if(Schema::hasTable('ai_action_runs'))Schema::table('ai_action_runs',function(Blueprint$t):void{$t->dropIndex('ai_actions_turn_ix');$t->dropColumn(['conversation_turn_token_hash','execution_expires_at']);});
    }

    private function createIntegrityTriggers():void
    {
        if(DB::getDriverName()==='sqlite'){
            DB::unprepared("CREATE TRIGGER ai_msg_turn_insert_guard BEFORE INSERT ON ai_conversation_messages WHEN NEW.runtime_run_id IS NOT NULL AND NEW.turn_token_hash IS NULL BEGIN SELECT RAISE(ABORT,'AI message Runtime Run requires turn ownership'); END");
            DB::unprepared("CREATE TRIGGER ai_msg_turn_update_guard BEFORE UPDATE ON ai_conversation_messages WHEN NEW.runtime_run_id IS NOT NULL AND NEW.turn_token_hash IS NULL BEGIN SELECT RAISE(ABORT,'AI message Runtime Run requires turn ownership'); END");
            DB::unprepared("CREATE TRIGGER ai_msg_pending_insert_guard BEFORE INSERT ON ai_conversation_messages WHEN NEW.role = 'assistant' AND NEW.status = 'pending' AND NEW.turn_token_hash IS NULL BEGIN SELECT RAISE(ABORT,'AI pending assistant requires turn ownership'); END");
            DB::unprepared("CREATE TRIGGER ai_msg_pending_update_guard BEFORE UPDATE ON ai_conversation_messages WHEN NEW.role = 'assistant' AND NEW.status = 'pending' AND NEW.turn_token_hash IS NULL BEGIN SELECT RAISE(ABORT,'AI pending assistant requires turn ownership'); END");
            if(Schema::hasTable('ai_action_runs')){DB::unprepared("CREATE TRIGGER ai_action_turn_insert_guard BEFORE INSERT ON ai_action_runs WHEN NEW.status IN ('executing','reconciliation_required') AND (NEW.conversation_turn_token_hash IS NULL OR NEW.execution_expires_at IS NULL) BEGIN SELECT RAISE(ABORT,'AI Action execution requires turn ownership'); END");DB::unprepared("CREATE TRIGGER ai_action_turn_update_guard BEFORE UPDATE ON ai_action_runs WHEN NEW.status IN ('executing','reconciliation_required') AND (NEW.conversation_turn_token_hash IS NULL OR NEW.execution_expires_at IS NULL) BEGIN SELECT RAISE(ABORT,'AI Action execution requires turn ownership'); END");}
            return;
        }
        DB::unprepared("CREATE TRIGGER ai_msg_turn_insert_guard BEFORE INSERT ON ai_conversation_messages FOR EACH ROW BEGIN IF NEW.runtime_run_id IS NOT NULL AND NEW.turn_token_hash IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='AI message Runtime Run requires turn ownership'; END IF; END");
        DB::unprepared("CREATE TRIGGER ai_msg_turn_update_guard BEFORE UPDATE ON ai_conversation_messages FOR EACH ROW BEGIN IF NEW.runtime_run_id IS NOT NULL AND NEW.turn_token_hash IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='AI message Runtime Run requires turn ownership'; END IF; END");
        DB::unprepared("CREATE TRIGGER ai_msg_pending_insert_guard BEFORE INSERT ON ai_conversation_messages FOR EACH ROW BEGIN IF NEW.role = 'assistant' AND NEW.status = 'pending' AND NEW.turn_token_hash IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='AI pending assistant requires turn ownership'; END IF; END");
        DB::unprepared("CREATE TRIGGER ai_msg_pending_update_guard BEFORE UPDATE ON ai_conversation_messages FOR EACH ROW BEGIN IF NEW.role = 'assistant' AND NEW.status = 'pending' AND NEW.turn_token_hash IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='AI pending assistant requires turn ownership'; END IF; END");
        if(Schema::hasTable('ai_action_runs')){DB::unprepared("CREATE TRIGGER ai_action_turn_insert_guard BEFORE INSERT ON ai_action_runs FOR EACH ROW BEGIN IF NEW.status IN ('executing','reconciliation_required') AND (NEW.conversation_turn_token_hash IS NULL OR NEW.execution_expires_at IS NULL) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='AI Action execution requires turn ownership'; END IF; END");DB::unprepared("CREATE TRIGGER ai_action_turn_update_guard BEFORE UPDATE ON ai_action_runs FOR EACH ROW BEGIN IF NEW.status IN ('executing','reconciliation_required') AND (NEW.conversation_turn_token_hash IS NULL OR NEW.execution_expires_at IS NULL) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='AI Action execution requires turn ownership'; END IF; END");}
    }

    private function dropIntegrityTriggers():void
    {
        foreach(['ai_msg_turn_insert_guard','ai_msg_turn_update_guard','ai_msg_pending_insert_guard','ai_msg_pending_update_guard','ai_action_turn_insert_guard','ai_action_turn_update_guard']as$name)DB::unprepared('DROP TRIGGER IF EXISTS '.$name);
    }
};
