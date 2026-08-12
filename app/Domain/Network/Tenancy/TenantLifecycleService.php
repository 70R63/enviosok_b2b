<?php

namespace App\Domain\Network\Tenancy;

use App\Domain\Network\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class TenantLifecycleService
{
    public function canPermanentlyDeleteTenant(Tenant $tenant): bool
    {
        return $this->blockingHistory($tenant) === [];
    }

    public function blockingHistory(Tenant $tenant): array
    {
        $checks = [
            'approved_payments' => fn () => Schema::hasTable('platform_payment_attempts') && DB::table('platform_payment_attempts')->where('tenant_id',$tenant->id)->where('status','APPROVED')->exists(),
            'payment_events' => fn () => Schema::hasTable('platform_payment_events') && Schema::hasTable('platform_payment_attempts') && DB::table('platform_payment_events as e')->join('platform_payment_attempts as a','a.id','=','e.platform_payment_attempt_id')->where('a.tenant_id',$tenant->id)->exists(),
            'paid_orders' => fn () => Schema::hasTable('tenant_saas_orders') && DB::table('tenant_saas_orders')->where('tenant_id',$tenant->id)->whereIn('status',['PAID','ACTIVATED'])->exists(),
            'subscriptions' => fn () => Schema::hasTable('network_subscriptions') && DB::table('network_subscriptions')->where('tenant_id',$tenant->id)->exists(),
            'usage' => fn () => Schema::hasTable('network_usage_events') && DB::table('network_usage_events')->where('tenant_id',$tenant->id)->exists(),
            'operations' => fn () => Schema::hasTable('network_tenant_operations') && DB::table('network_tenant_operations')->where('tenant_id',$tenant->id)->exists(),
            'shipments' => fn () => Schema::hasTable('local_shipments') && Schema::hasTable('network_tenant_operations') && DB::table('local_shipments as s')->join('network_tenant_operations as o','o.id','=','s.tenant_operation_id')->where('o.tenant_id',$tenant->id)->exists(),
            'tickets' => fn () => Schema::hasTable('support_tickets') && DB::table('support_tickets')->where('tenant_id',$tenant->id)->exists(),
            'paid_onboarding' => fn () => Schema::hasTable('saas_onboarding_applications') && DB::table('saas_onboarding_applications')->where('tenant_id',$tenant->id)->whereNotNull('paid_at')->exists(),
        ];
        return array_keys(array_filter($checks, fn ($check) => $check()));
    }

    public function archive(Tenant $tenant, string $reason): Tenant
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason'=>'El motivo es obligatorio.']);
        }
        if (!in_array($tenant->status, ['inactive','suspended','archived'], true)) {
            throw ValidationException::withMessages(['tenant'=>'Sólo un tenant inactivo o suspendido puede archivarse.']);
        }
        $tenant->update(['status'=>'archived','archived_at'=>now(),'archive_reason'=>$reason]);
        return $tenant->fresh();
    }

    public function deleteEmpty(Tenant $tenant, string $reason): void
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason'=>'El motivo es obligatorio.']);
        }
        $blocking = $this->blockingHistory($tenant);
        if ($blocking) {
            throw ValidationException::withMessages(['tenant'=>'El tenant conserva historial ('.implode(', ',$blocking).') y sólo puede archivarse.']);
        }
        DB::transaction(function () use ($tenant): void {
            $tenant->domains()->delete();
            $tenant->branding()->delete();
            $tenant->memberships()->delete();
            $tenant->delete();
        });
    }
}
