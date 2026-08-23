<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class AiHumanHandoffFoundationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::statement('PRAGMA foreign_keys=ON');
        Schema::create('network_tenants', fn (Blueprint $t) => $t->id());
        Schema::create('users', fn (Blueprint $t) => $t->id());
        Schema::create('ai_runtime_runs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->unique(['tenant_id', 'id']);
        });
        Schema::create('ai_conversations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->boolean('turn_in_progress')->default(false);
            $t->unsignedInteger('next_sequence')->default(1);
            $t->string('status');
            $t->unique(['tenant_id', 'id']);
        });
        Schema::create('ai_conversation_messages', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('conversation_id');
            $t->unique(['tenant_id', 'id']);
        });
        $this->migration()->up();
    }

    public function test_migration_up_down_up_and_conversation_aware_constraints(): void
    {
        $this->assertTrue(Schema::hasTable('ai_human_handoffs'));
        $this->assertTrue(Schema::hasColumn('ai_conversations', 'active_handoff_id'));
        $this->assertNotEmpty(DB::select("PRAGMA foreign_key_list('ai_human_handoffs')"));
        $this->migration()->down();
        $this->assertFalse(Schema::hasTable('ai_human_handoffs'));
        $this->migration()->up();
        $this->assertTrue(Schema::hasTable('ai_human_handoffs'));
    }

    public function test_requested_message_and_active_pointer_reject_incoherent_tenant_or_conversation_and_allow_history(): void
    {
        DB::table('network_tenants')->insert([['id' => 1], ['id' => 2]]);
        DB::table('users')->insert(['id' => 1]);
        DB::table('ai_runtime_runs')->insert([['id' => 1, 'tenant_id' => 1], ['id' => 2, 'tenant_id' => 2]]);
        DB::table('ai_conversations')->insert([['id' => 1, 'tenant_id' => 1, 'status' => 'handoff_requested'], ['id' => 2, 'tenant_id' => 1, 'status' => 'handoff_requested'], ['id' => 3, 'tenant_id' => 2, 'status' => 'handoff_requested']]);
        DB::table('ai_conversation_messages')->insert([['id' => 1, 'tenant_id' => 1, 'conversation_id' => 1], ['id' => 2, 'tenant_id' => 1, 'conversation_id' => 2], ['id' => 3, 'tenant_id' => 2, 'conversation_id' => 3]]);
        DB::table('ai_human_handoffs')->insert($this->row(1, 1, 1));
        DB::table('ai_human_handoffs')->insert($this->row(2, 1, 1, 'released'));
        $this->assertSame(2, DB::table('ai_human_handoffs')->count());
        foreach ([$this->row(3, 1, 2), array_replace($this->row(4, 2, 2), ['tenant_id' => 2, 'runtime_run_id' => 2])] as $invalid) {
            try {
                DB::table('ai_human_handoffs')->insert($invalid);
                $this->fail('Incoherent handoff must fail.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
        DB::table('ai_conversations')->where('id', 1)->update(['active_handoff_id' => 1]);
        try {
            DB::table('ai_conversations')->where('id', 2)->update(['active_handoff_id' => 1]);
            $this->fail('Active pointer from another conversation must fail.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame(1, (int) DB::table('ai_conversations')->where('id', 1)->value('active_handoff_id'));
    }

    private function row(int $id, int $conversation, int $message, string $status = 'requested'): array
    {
        return ['id' => $id, 'uuid' => sprintf('00000000-0000-0000-0000-%012d', $id), 'tenant_id' => 1, 'conversation_id' => $conversation, 'requested_by_message_id' => $message, 'runtime_run_id' => 1, 'status' => $status, 'requested_at' => now(), 'reason_code' => 'model_requested', 'created_at' => now(), 'updated_at' => now()];
    }

    private function migration(): object
    {
        return require base_path('database/migrations/2026_08_30_100000_create_ai_human_handoffs.php');
    }
}
