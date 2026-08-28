<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MONEY = [
        'ai_provider_rates' => ['input_microusd_per_million', 'cached_input_microusd_per_million', 'output_microusd_per_million'],
        'ai_cost_budgets' => ['budget_microusd'],
        'ai_cost_ledger' => ['actual_cost_microusd', 'reserved_microusd', 'released_microusd'],
        'ai_cost_reservations' => ['reserved_microusd', 'actual_cost_microusd', 'released_microusd'],
    ];
    private const LEGACY_MONEY = [
        'ai_provider_rates' => ['input_cost_per_million', 'cached_input_cost_per_million', 'output_cost_per_million'],
        'ai_cost_budgets' => ['budget_usd'],
        'ai_cost_ledger' => ['cost_usd'],
    ];

    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') DB::statement('PRAGMA foreign_keys=ON');
        $this->audit();

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE ai_cost_ledger DROP FOREIGN KEY ai_cost_ledger_agent_id_foreign');
            DB::statement('ALTER TABLE ai_cost_ledger DROP FOREIGN KEY ai_cost_ledger_conversation_id_foreign');
            DB::statement('ALTER TABLE ai_cost_ledger DROP FOREIGN KEY ai_cost_ledger_runtime_run_id_foreign');
            foreach (self::MONEY as $table => $columns) foreach ($columns as $column) {
                $nullable = in_array($column, ['budget_microusd', 'actual_cost_microusd'], true) ? ' NULL' : ' NOT NULL DEFAULT 0';
                DB::statement("ALTER TABLE {$table} MODIFY {$column} BIGINT{$nullable}");
            }
        }

        Schema::table('ai_cost_ledger', function (Blueprint $t): void {
            $t->foreign(['tenant_id', 'agent_id'], 'ai_cost_ledger_tenant_agent_fk')->references(['tenant_id', 'id'])->on('ai_agents')->restrictOnDelete();
            $t->foreign(['tenant_id', 'conversation_id'], 'ai_cost_ledger_tenant_conv_fk')->references(['tenant_id', 'id'])->on('ai_conversations')->restrictOnDelete();
            $t->foreign(['tenant_id', 'runtime_run_id'], 'ai_cost_ledger_tenant_run_fk')->references(['tenant_id', 'id'])->on('ai_runtime_runs')->restrictOnDelete();
        });
        Schema::table('ai_cost_reservations', function (Blueprint $t): void {
            $t->foreign(['tenant_id', 'runtime_run_id'], 'ai_cost_res_tenant_run_fk')->references(['tenant_id', 'id'])->on('ai_runtime_runs')->restrictOnDelete();
        });
        Schema::table('network_subscriptions', function (Blueprint $t): void {
            $t->foreign('pending_plan_id', 'net_sub_pending_plan_fk')->references('id')->on('network_plans')->restrictOnDelete();
        });
        $this->createGuards();
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') DB::statement('PRAGMA foreign_keys=ON');
        $this->dropGuards();
        Schema::table('network_subscriptions', fn (Blueprint $t) => $t->dropForeign('net_sub_pending_plan_fk'));
        Schema::table('ai_cost_reservations', fn (Blueprint $t) => $t->dropForeign('ai_cost_res_tenant_run_fk'));
        Schema::table('ai_cost_ledger', function (Blueprint $t): void {
            $t->dropForeign('ai_cost_ledger_tenant_agent_fk');
            $t->dropForeign('ai_cost_ledger_tenant_conv_fk');
            $t->dropForeign('ai_cost_ledger_tenant_run_fk');
        });
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE ai_cost_ledger ADD CONSTRAINT ai_cost_ledger_agent_id_foreign FOREIGN KEY (agent_id) REFERENCES ai_agents(id) ON DELETE SET NULL');
            DB::statement('ALTER TABLE ai_cost_ledger ADD CONSTRAINT ai_cost_ledger_conversation_id_foreign FOREIGN KEY (conversation_id) REFERENCES ai_conversations(id) ON DELETE SET NULL');
            DB::statement('ALTER TABLE ai_cost_ledger ADD CONSTRAINT ai_cost_ledger_runtime_run_id_foreign FOREIGN KEY (runtime_run_id) REFERENCES ai_runtime_runs(id) ON DELETE SET NULL');
            foreach (self::MONEY as $table => $columns) foreach ($columns as $column) {
                $nullable = in_array($column, ['budget_microusd', 'actual_cost_microusd'], true) ? ' NULL' : ' NOT NULL DEFAULT 0';
                DB::statement("ALTER TABLE {$table} MODIFY {$column} BIGINT UNSIGNED{$nullable}");
            }
        }
    }

    private function audit(): void
    {
        foreach ($this->guardedColumns() as $table => $columns) foreach ($columns as $column) {
            if (DB::table($table)->where($column, '<', 0)->exists()) throw new \RuntimeException("AI commercial integrity found negative {$table}.{$column}.");
        }
        foreach (['agent_id' => 'ai_agents', 'conversation_id' => 'ai_conversations', 'runtime_run_id' => 'ai_runtime_runs'] as $column => $parent) {
            if (DB::table('ai_cost_ledger as l')->join("{$parent} as p", 'p.id', '=', "l.{$column}")->whereNotNull("l.{$column}")->whereColumn('p.tenant_id', '!=', 'l.tenant_id')->exists())
                throw new \RuntimeException("AI commercial integrity found cross-tenant ledger {$column}.");
        }
        if (DB::table('ai_cost_reservations as r')->join('ai_runtime_runs as x', 'x.id', '=', 'r.runtime_run_id')->whereColumn('x.tenant_id', '!=', 'r.tenant_id')->exists()) throw new \RuntimeException('AI commercial integrity found cross-tenant reservation.');
        if (DB::table('network_subscriptions as s')->leftJoin('network_plans as p', 'p.id', '=', 's.pending_plan_id')->whereNotNull('s.pending_plan_id')->whereNull('p.id')->exists()) throw new \RuntimeException('AI commercial integrity found orphan pending plan.');
        if (DB::table('network_subscriptions')->where(fn ($q) => $q->whereNull('pending_plan_id')->whereNotNull('pending_effective_at'))->orWhere(fn ($q) => $q->whereNotNull('pending_plan_id')->whereNull('pending_effective_at'))->exists()) throw new \RuntimeException('AI commercial integrity found incoherent pending schedule.');
        if (DB::table('network_subscriptions')->whereNotNull('pending_plan_id')->whereColumn('pending_plan_id', 'plan_id')->exists()) throw new \RuntimeException('AI commercial integrity found current plan scheduled as pending.');
    }

    private function createGuards(): void
    {
        $driver = DB::getDriverName();
        foreach ($this->guardedColumns() as $table => $columns) {
            $condition = implode(' OR ', array_map(fn ($c) => "NEW.{$c} < 0", $columns));
            foreach (['insert' => 'INSERT', 'update' => 'UPDATE'] as $suffix => $event) {
                $name = substr("ai_nonnegative_{$table}_{$suffix}", 0, 63);
                if ($driver === 'sqlite') DB::unprepared("CREATE TRIGGER {$name} BEFORE {$event} ON {$table} WHEN {$condition} BEGIN SELECT RAISE(ABORT, 'AI monetary values must be nonnegative'); END");
                else DB::unprepared("CREATE TRIGGER {$name} BEFORE {$event} ON {$table} FOR EACH ROW BEGIN IF {$condition} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='AI monetary values must be nonnegative'; END IF; END");
            }
        }
        $condition = '(NEW.pending_plan_id IS NULL AND NEW.pending_effective_at IS NOT NULL) OR (NEW.pending_plan_id IS NOT NULL AND NEW.pending_effective_at IS NULL) OR (NEW.pending_plan_id IS NOT NULL AND NEW.pending_plan_id = NEW.plan_id)';
        foreach (['insert' => 'INSERT', 'update' => 'UPDATE'] as $suffix => $event) {
            $name = "net_sub_pending_guard_{$suffix}";
            if ($driver === 'sqlite') DB::unprepared("CREATE TRIGGER {$name} BEFORE {$event} ON network_subscriptions WHEN {$condition} BEGIN SELECT RAISE(ABORT, 'Pending plan schedule is incoherent'); END");
            else DB::unprepared("CREATE TRIGGER {$name} BEFORE {$event} ON network_subscriptions FOR EACH ROW BEGIN IF {$condition} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Pending plan schedule is incoherent'; END IF; END");
        }
    }

    private function dropGuards(): void
    {
        foreach (array_keys(self::MONEY) as $table) foreach (['insert', 'update'] as $suffix) DB::unprepared('DROP TRIGGER IF EXISTS '.substr("ai_nonnegative_{$table}_{$suffix}", 0, 63));
        foreach (['insert', 'update'] as $suffix) DB::unprepared("DROP TRIGGER IF EXISTS net_sub_pending_guard_{$suffix}");
    }

    private function guardedColumns(): array
    {
        $columns = self::MONEY;
        foreach (self::LEGACY_MONEY as $table => $legacy) $columns[$table] = [...($columns[$table] ?? []), ...$legacy];
        return $columns;
    }
};
