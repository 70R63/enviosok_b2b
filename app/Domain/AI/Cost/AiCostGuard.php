<?php

namespace App\Domain\AI\Cost;

use App\Domain\AI\Cost\Models\{AiCostBudget,AiCostLedger,AiCostReservation,AiProviderRate};
use App\Domain\AI\Runtime\Models\RuntimeRun;
use App\Domain\Network\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class AiCostGuard
{
    public function reserve(Tenant $tenant, RuntimeRun $run): void
    {
        if ($run->execution_mode?->value !== 'live' || ! Schema::hasTable('ai_cost_reservations')) return;
        $this->assertRunTenant($tenant, $run);
        DB::transaction(function () use ($tenant, $run): void {
            $period = now()->format('Y-m');
            $rate = $this->rate($run->provider_code, $run->model_code, now());
            if (! $rate) throw new RuntimeException('AI_PROVIDER_RATE_MISSING');
            $estimate = $this->estimate($rate);
            $key = 'runtime_run:'.$run->id;
            $existing = AiCostReservation::where('runtime_run_id', $run->id)->lockForUpdate()->first();
            if ($existing) return;
            $tenantBudget = AiCostBudget::where('tenant_id', $tenant->id)->where('period', $period)->lockForUpdate()->first();
            $globalBudget = AiCostBudget::whereNull('tenant_id')->where('period', $period)->lockForUpdate()->first();
            if (! $tenantBudget || $tenantBudget->budget_microusd === null) throw new RuntimeException('AI_COST_BUDGET_EXCEEDED');
            $tenantUsed = (int) AiCostLedger::where('tenant_id', $tenant->id)->where('billing_period', $period)->sum('actual_cost_microusd');
            $tenantReserved = (int) AiCostReservation::where('tenant_id', $tenant->id)->where('billing_period', $period)->where('status', 'reserved')->sum('reserved_microusd');
            if ($tenantUsed + $tenantReserved + $estimate > (int) $tenantBudget->budget_microusd) throw new RuntimeException('AI_COST_BUDGET_EXCEEDED');
            if ($globalBudget) {
                $globalUsed = (int) AiCostLedger::where('billing_period', $period)->sum('actual_cost_microusd');
                $globalReserved = (int) AiCostReservation::where('billing_period', $period)->where('status', 'reserved')->sum('reserved_microusd');
                if ($globalBudget->budget_microusd === null || $globalUsed + $globalReserved + $estimate > (int) $globalBudget->budget_microusd) throw new RuntimeException('AI_GLOBAL_COST_BUDGET_EXCEEDED');
            }
            AiCostReservation::create(['tenant_id'=>$tenant->id,'runtime_run_id'=>$run->id,'provider'=>$run->provider_code,'model'=>$run->model_code,'billing_period'=>$period,'reserved_microusd'=>$estimate,'status'=>'reserved','idempotency_key'=>$key,'rate_snapshot'=>$rate]);
        });
    }

    public function record(Tenant $tenant, RuntimeRun $run, array $usage): AiCostLedger
    {
        $this->assertRunTenant($tenant, $run);
        foreach (['input_tokens', 'cached_input_tokens', 'output_tokens', 'total_tokens'] as $field) {
            if (! is_int($usage[$field] ?? 0) || ($usage[$field] ?? 0) < 0) throw new RuntimeException('AI_USAGE_INVALID');
        }
        return DB::transaction(function () use ($tenant, $run, $usage): AiCostLedger {
            $key = 'runtime_run:'.$run->id;
            if ($existing = AiCostLedger::where('tenant_id',$tenant->id)->where('idempotency_key',$key)->first()) return $existing;
            $reservation = Schema::hasTable('ai_cost_reservations') ? AiCostReservation::where('runtime_run_id',$run->id)->lockForUpdate()->first() : null;
            $rate = $reservation?->rate_snapshot ?? $this->rate($run->provider_code, $run->model_code, $run->created_at ?? now());
            if (! $rate) throw new RuntimeException('AI_PROVIDER_RATE_MISSING');
            $actual = $this->cost((int)($usage['input_tokens']??0),(int)($usage['cached_input_tokens']??0),(int)($usage['output_tokens']??0),$rate);
            $reserved = (int)($reservation?->reserved_microusd ?? 0);
            $ledger = AiCostLedger::create(['tenant_id'=>$tenant->id,'agent_id'=>$run->agent_id,'runtime_run_id'=>$run->id,'provider'=>$run->provider_code,'model'=>$run->model_code,'input_tokens'=>(int)($usage['input_tokens']??0),'cached_input_tokens'=>(int)($usage['cached_input_tokens']??0),'output_tokens'=>(int)($usage['output_tokens']??0),'total_tokens'=>(int)($usage['total_tokens']??0),'actual_cost_microusd'=>$actual,'reserved_microusd'=>$reserved,'released_microusd'=>max(0,$reserved-$actual),'rate_snapshot'=>$rate,'cost_usd'=>0,'billing_period'=>now()->format('Y-m'),'idempotency_key'=>$key]);
            if ($reservation) $reservation->update(['status'=>'settled','actual_cost_microusd'=>$actual,'released_microusd'=>max(0,$reserved-$actual)]);
            return $ledger;
        });
    }

    public function release(RuntimeRun $run): void
    { if (! Schema::hasTable('ai_cost_reservations')) return; AiCostReservation::where('runtime_run_id',$run->id)->where('status','reserved')->update(['status'=>'released','released_microusd'=>DB::raw('reserved_microusd'),'updated_at'=>now()]); }

    private function rate(?string $provider, ?string $model, $at): ?array
    { $r=AiProviderRate::where('provider',$provider)->where('model',$model)->where('enabled',true)->where('effective_from','<=',$at)->latest('effective_from')->first();if(!$r)return null;return ['input'=>(int)$r->input_microusd_per_million,'cached_input'=>(int)$r->cached_input_microusd_per_million,'output'=>(int)$r->output_microusd_per_million]; }
    private function estimate(array $rate): int { return $this->cost(1000,0,600,$rate); }
    private function cost(int $input,int $cached,int $output,array $rate): int
    {
        foreach ([$input, $cached, $output, ...array_values($rate)] as $value) if ($value < 0) throw new RuntimeException('AI_COST_VALUE_INVALID');
        if ($cached > $input) throw new RuntimeException('AI_USAGE_INVALID');
        $parts = [($input - $cached) * $rate['input'], $cached * $rate['cached_input'], $output * $rate['output']];
        foreach ($parts as $part) if (! is_int($part) || $part < 0) throw new RuntimeException('AI_COST_OVERFLOW');
        $sum = array_sum($parts); if (! is_int($sum) || $sum > PHP_INT_MAX - 999999) throw new RuntimeException('AI_COST_OVERFLOW');
        return intdiv($sum + 999999, 1000000);
    }

    private function assertRunTenant(Tenant $tenant, RuntimeRun $run): void
    {
        if ((int) $run->tenant_id !== (int) $tenant->id) throw new RuntimeException('AI_RUNTIME_TENANT_MISMATCH');
    }
}
