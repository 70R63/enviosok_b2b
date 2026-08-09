<?php

namespace App\Http\Controllers;

use App\Domain\Network\Tenancy\TenantContext;
use App\Domain\Shipping\LastMile\Models\LocalDeliveryFailedAttempt;
use App\Domain\Shipping\LastMile\Models\LocalDeliveryProof;
use Illuminate\Support\Facades\Storage;

final class DeliveryEvidenceController extends Controller
{
    public function tenant(string $proof, string $kind, TenantContext $context)
    {
        $item = LocalDeliveryProof::where('tenant_id', $context->id())->where('uuid', $proof)->firstOrFail();
        return $this->file($item, $kind);
    }

    public function network(string $proof, string $kind)
    {
        return $this->file(LocalDeliveryProof::where('uuid', $proof)->firstOrFail(), $kind);
    }

    public function tenantFailed(string $attempt, TenantContext $context)
    {
        $item = LocalDeliveryFailedAttempt::where('tenant_id', $context->id())->where('uuid', $attempt)->firstOrFail();
        abort_unless($item->photo_path, 404);
        return response()->file(Storage::disk('local')->path($item->photo_path));
    }

    private function file(LocalDeliveryProof $proof, string $kind)
    {
        abort_unless(in_array($kind, ['photo', 'signature'], true), 404);
        $path = $kind === 'photo' ? $proof->photo_path : $proof->signature_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        return response()->file(Storage::disk('local')->path($path));
    }
}
