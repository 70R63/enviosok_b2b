<?php

namespace App\Http\Middleware;

use App\Models\ApiClientProduct;
use App\Models\ApiProduct;
use App\Models\ApiUsageLog;
use Closure;
use Illuminate\Http\Request;

class EnsureApiProductAccess
{
    public function handle(
        Request $request,
        Closure $next,
        string $productCode
    ) {
        $apiClient = $request->attributes->get(
            'api_client'
        );

        if (!$apiClient) {
            return response()->json([
                'success' => false,
                'message' => 'No fue posible identificar al cliente API.',
            ], 401);
        }

        $product = ApiProduct::query()
            ->where(
                'code',
                strtoupper(trim($productCode))
            )
            ->where('active', true)
            ->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'El producto API solicitado no está disponible.',
            ], 503);
        }

        $request->attributes->set(
            'api_product',
            $product
        );

        $clientProduct = ApiClientProduct::query()
            ->where(
                'api_client_id',
                $apiClient->id
            )
            ->where(
                'api_product_id',
                $product->id
            )
            ->first();

        if (!$clientProduct || !$clientProduct->active) {
            return response()->json([
                'success' => false,
                'message' => 'El cliente no tiene habilitado este producto API.',
                'product' => $product->code,
            ], 403);
        }

        $request->attributes->set(
            'api_client_product',
            $clientProduct
        );

        if ($clientProduct->monthly_limit !== null) {
            $currentUsage = ApiUsageLog::query()
                ->where(
                    'api_client_id',
                    $apiClient->id
                )
                ->where(
                    'api_product_id',
                    $product->id
                )
                ->whereBetween('created_at', [
                    now()->startOfMonth(),
                    now()->endOfMonth(),
                ])
                ->count();

            if (
                $currentUsage
                >= $clientProduct->monthly_limit
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Límite mensual del producto excedido.',
                    'product' => $product->code,
                    'monthly_limit' =>
                        $clientProduct->monthly_limit,
                    'current_usage' => $currentUsage,
                ], 429);
            }
        }

        return $next($request);
    }
}
