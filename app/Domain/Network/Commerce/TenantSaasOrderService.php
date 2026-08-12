<?php

namespace App\Domain\Network\Commerce;

use App\Domain\Network\Commerce\Models\NetworkCommercialProduct;
use App\Domain\Network\Commerce\Models\TenantSaasOrder;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class TenantSaasOrderService
{
    public function create(Tenant $tenant, User $user, NetworkCommercialProduct $product, string $key): TenantSaasOrder
    {
        abort_unless($product->is_active, 404);

        try {
            return DB::transaction(function () use ($tenant, $user, $product, $key): TenantSaasOrder {
                $existing = TenantSaasOrder::where('tenant_id', $tenant->id)->where('purchase_key', $key)->first();
                if ($existing) {
                    return $existing;
                }
                $snapshot = ['code'=>$product->code,'name'=>$product->name,'description'=>$product->description,'type'=>$product->type,'billing_type'=>$product->billing_type,'plan_id'=>$product->plan_id,'module_id'=>$product->module_id,'included_operations'=>$product->included_operations,'metadata'=>$product->metadata??[]];
                return TenantSaasOrder::create(['tenant_id'=>$tenant->id,'commercial_product_id'=>$product->id,'created_by_user_id'=>$user->id,'purchase_key'=>$key,'status'=>'PENDING_PAYMENT','payment_status'=>'PENDING','quantity'=>1,'unit_amount'=>$product->price,'subtotal'=>$product->price,'tax_amount'=>0,'total_amount'=>$product->price,'currency'=>$product->currency,'purchase_snapshot'=>$snapshot,'expires_at'=>now()->addHour()]);
            });
        } catch (QueryException $exception) {
            // The unique (tenant_id, purchase_key) constraint is the concurrency authority.
            $winner = TenantSaasOrder::where('tenant_id', $tenant->id)->where('purchase_key', $key)->first();
            if ($winner) {
                return $winner;
            }
            throw $exception;
        }
    }
}
