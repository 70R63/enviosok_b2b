<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $isSqlite = Schema::getConnection()->getDriverName() === 'sqlite';
        // Targets and their composite unique keys must precede the pointer FKs.
        Schema::table('ai_agent_contract_versions', function (Blueprint $table): void {
            $table->char('content_hash', 64)->nullable();
            $table->timestamp('offered_at')->nullable();
            $table->unsignedBigInteger('offered_by_user_id')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->unsignedBigInteger('accepted_by_user_id')->nullable();
            $table->char('accepted_content_hash', 64)->nullable();
            $table->json('acceptance_evidence')->nullable();
            $table->timestamp('terminal_at')->nullable();
            $table->unsignedBigInteger('terminal_by_user_id')->nullable();
            $table->text('terminal_reason')->nullable();
            $table->index(['tenant_id', 'offered_at'], 'ai_contract_versions_offered_ix');
            $table->index(['tenant_id', 'accepted_at'], 'ai_contract_versions_accepted_ix');
            $table->unique(['tenant_id', 'agent_contract_id', 'id'], 'ai_contract_versions_owner_id_uq');
            $table->foreign('offered_by_user_id', 'ai_contract_versions_offered_by_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('accepted_by_user_id', 'ai_contract_versions_accepted_by_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('terminal_by_user_id', 'ai_contract_versions_terminal_by_fk')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::table('ai_agent_versions', function (Blueprint $table): void {
            $table->char('configuration_hash', 64)->nullable();
            $table->timestamp('testing_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->json('approval_evidence')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('published_by_user_id')->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->unsignedBigInteger('retired_by_user_id')->nullable();
            $table->unique(['tenant_id', 'id'], 'ai_agent_versions_tenant_id_uq');
            $table->unique(['tenant_id', 'agent_id', 'id'], 'ai_agent_versions_owner_id_uq');
            $table->index(['tenant_id', 'testing_at'], 'ai_agent_versions_testing_ix');
            $table->index(['tenant_id', 'published_at'], 'ai_agent_versions_published_ix');
            $table->foreign('approved_by_user_id', 'ai_agent_versions_approved_by_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('published_by_user_id', 'ai_agent_versions_published_by_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('retired_by_user_id', 'ai_agent_versions_retired_by_fk')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::table('ai_agents', function (Blueprint $table) use ($isSqlite): void {
            $table->unsignedBigInteger('current_published_version_id')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->index(['tenant_id', 'current_published_version_id'], 'ai_agents_current_version_ix');
            $table->index(['tenant_id', 'activated_at'], 'ai_agents_activated_ix');
            if (! $isSqlite) {
                $table->foreign(['tenant_id', 'id', 'current_published_version_id'], 'ai_agents_current_version_fk')
                    ->references(['tenant_id', 'agent_id', 'id'])->on('ai_agent_versions')->restrictOnDelete();
            }
        });

        Schema::table('ai_agent_contracts', function (Blueprint $table) use ($isSqlite): void {
            $table->unsignedBigInteger('current_accepted_version_id')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->index(['tenant_id', 'current_accepted_version_id'], 'ai_contracts_current_version_ix');
            $table->index(['tenant_id', 'activated_at'], 'ai_contracts_activated_ix');
            if (! $isSqlite) {
                $table->foreign(['tenant_id', 'id', 'current_accepted_version_id'], 'ai_contracts_current_version_fk')
                    ->references(['tenant_id', 'agent_contract_id', 'id'])->on('ai_agent_contract_versions')->restrictOnDelete();
            }
        });

        if ($isSqlite) {
            $this->createSqlitePointerGuards();
        }

        Schema::create('ai_agent_lifecycle_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('network_tenants')->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('aggregate_type', 160);
            $table->unsignedBigInteger('aggregate_id');
            $table->string('resource_type', 160);
            $table->unsignedBigInteger('resource_id');
            $table->string('event', 80);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->index(['tenant_id', 'aggregate_type', 'aggregate_id'], 'ai_lifecycle_aggregate_ix');
            $table->index(['tenant_id', 'event', 'occurred_at'], 'ai_lifecycle_event_time_ix');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_agent_lifecycle_events');

        $isSqlite = Schema::getConnection()->getDriverName() === 'sqlite';
        if ($isSqlite) {
            DB::unprepared('DROP TRIGGER IF EXISTS ai_agents_pointer_insert_guard');
            DB::unprepared('DROP TRIGGER IF EXISTS ai_agents_pointer_update_guard');
            DB::unprepared('DROP TRIGGER IF EXISTS ai_contracts_pointer_insert_guard');
            DB::unprepared('DROP TRIGGER IF EXISTS ai_contracts_pointer_update_guard');
        } else {
            Schema::table('ai_agents', fn (Blueprint $table) => $table->dropForeign('ai_agents_current_version_fk'));
            Schema::table('ai_agent_contracts', fn (Blueprint $table) => $table->dropForeign('ai_contracts_current_version_fk'));
        }
        if (! $isSqlite) {
            Schema::table('ai_agent_versions', function (Blueprint $table): void {
                $table->dropForeign('ai_agent_versions_approved_by_fk');
                $table->dropForeign('ai_agent_versions_published_by_fk');
                $table->dropForeign('ai_agent_versions_retired_by_fk');
            });
            Schema::table('ai_agent_contract_versions', function (Blueprint $table): void {
                $table->dropForeign('ai_contract_versions_offered_by_fk');
                $table->dropForeign('ai_contract_versions_accepted_by_fk');
                $table->dropForeign('ai_contract_versions_terminal_by_fk');
            });
        } elseif (version_compare(app()->version(), '12.0.0', '>=')) {
            // Laravel 12 uses SQLite's native DROP COLUMN. Remove these constraints
            // first so SQLite never rebuilds a table with references to removed columns.
            Schema::table('ai_agent_versions', function (Blueprint $table): void {
                $table->dropForeign(['approved_by_user_id']);
                $table->dropForeign(['published_by_user_id']);
                $table->dropForeign(['retired_by_user_id']);
            });
            Schema::table('ai_agent_contract_versions', function (Blueprint $table): void {
                $table->dropForeign(['offered_by_user_id']);
                $table->dropForeign(['accepted_by_user_id']);
                $table->dropForeign(['terminal_by_user_id']);
            });
        }

        Schema::table('ai_agents', function (Blueprint $table): void {
            $table->dropIndex('ai_agents_current_version_ix');
            $table->dropIndex('ai_agents_activated_ix');
            $table->dropColumn(['current_published_version_id', 'activated_at', 'paused_at', 'retired_at']);
        });
        Schema::table('ai_agent_contracts', function (Blueprint $table): void {
            $table->dropIndex('ai_contracts_current_version_ix');
            $table->dropIndex('ai_contracts_activated_ix');
            $table->dropColumn(['current_accepted_version_id', 'activated_at', 'suspended_at', 'ended_at']);
        });
        Schema::table('ai_agent_contract_versions', function (Blueprint $table): void {
            $table->dropIndex('ai_contract_versions_offered_ix');
            $table->dropIndex('ai_contract_versions_accepted_ix');
            $table->dropUnique('ai_contract_versions_owner_id_uq');
            $table->dropColumn(['content_hash', 'offered_at', 'offered_by_user_id', 'accepted_at', 'accepted_by_user_id', 'accepted_content_hash', 'acceptance_evidence', 'terminal_at', 'terminal_by_user_id', 'terminal_reason']);
        });
        Schema::table('ai_agent_versions', function (Blueprint $table): void {
            $table->dropUnique('ai_agent_versions_tenant_id_uq');
            $table->dropUnique('ai_agent_versions_owner_id_uq');
            $table->dropIndex('ai_agent_versions_testing_ix');
            $table->dropIndex('ai_agent_versions_published_ix');
            $table->dropColumn(['configuration_hash', 'testing_at', 'approved_at', 'approved_by_user_id', 'approval_evidence', 'published_at', 'published_by_user_id', 'retired_at', 'retired_by_user_id']);
        });
    }

    /** SQLite cannot add a foreign key to an existing table without rebuilding it. */
    private function createSqlitePointerGuards(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TRIGGER ai_agents_pointer_insert_guard
BEFORE INSERT ON ai_agents
WHEN NEW.current_published_version_id IS NOT NULL
 AND NOT EXISTS (
    SELECT 1 FROM ai_agent_versions
    WHERE tenant_id = NEW.tenant_id AND agent_id = NEW.id AND id = NEW.current_published_version_id
 )
BEGIN SELECT RAISE(ABORT, 'invalid agent published version pointer'); END
SQL);
        DB::unprepared(<<<'SQL'
CREATE TRIGGER ai_agents_pointer_update_guard
BEFORE UPDATE OF tenant_id, id, current_published_version_id ON ai_agents
WHEN NEW.current_published_version_id IS NOT NULL
 AND NOT EXISTS (
    SELECT 1 FROM ai_agent_versions
    WHERE tenant_id = NEW.tenant_id AND agent_id = NEW.id AND id = NEW.current_published_version_id
 )
BEGIN SELECT RAISE(ABORT, 'invalid agent published version pointer'); END
SQL);
        DB::unprepared(<<<'SQL'
CREATE TRIGGER ai_contracts_pointer_insert_guard
BEFORE INSERT ON ai_agent_contracts
WHEN NEW.current_accepted_version_id IS NOT NULL
 AND NOT EXISTS (
    SELECT 1 FROM ai_agent_contract_versions
    WHERE tenant_id = NEW.tenant_id AND agent_contract_id = NEW.id AND id = NEW.current_accepted_version_id
 )
BEGIN SELECT RAISE(ABORT, 'invalid contract accepted version pointer'); END
SQL);
        DB::unprepared(<<<'SQL'
CREATE TRIGGER ai_contracts_pointer_update_guard
BEFORE UPDATE OF tenant_id, id, current_accepted_version_id ON ai_agent_contracts
WHEN NEW.current_accepted_version_id IS NOT NULL
 AND NOT EXISTS (
    SELECT 1 FROM ai_agent_contract_versions
    WHERE tenant_id = NEW.tenant_id AND agent_contract_id = NEW.id AND id = NEW.current_accepted_version_id
 )
BEGIN SELECT RAISE(ABORT, 'invalid contract accepted version pointer'); END
SQL);
    }
};
