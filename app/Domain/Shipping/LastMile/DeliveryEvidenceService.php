<?php

namespace App\Domain\Shipping\LastMile;

use App\Domain\Shipping\LastMile\Models\DriverAssignment;
use App\Domain\Shipping\LastMile\Models\LocalDeliveryFailedAttempt;
use App\Domain\Shipping\LastMile\Models\LocalDeliveryProof;
use App\Domain\Shipping\Local\LocalTrackingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class DeliveryEvidenceService
{
    public function __construct(private DriverDeliveryService $delivery, private LocalTrackingService $tracking, private DeliveryRequirementService $requirements) {}

    public function deliver(DriverAssignment $assignment, int $userId, array $data, ?UploadedFile $photo, ?string $signatureBinary): LocalDeliveryProof
    {
        $photoPath = $photo ? $this->storeUpload($photo, 'pod/photos') : null;
        $signaturePath = $signatureBinary ? 'pod/signatures/'.Str::uuid().'.png' : null;
        if ($signaturePath) Storage::disk('local')->put($signaturePath, $signatureBinary);

        try {
            return DB::transaction(function () use ($assignment, $userId, $data, $photoPath, $signaturePath): LocalDeliveryProof {
                $locked = DriverAssignment::with(['shipment', 'driverProfile'])->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
                abort_unless($locked->status === 'ACTIVE' && $locked->shipment->status === 'OUT_FOR_DELIVERY' && (int) $locked->driverProfile->user_id === $userId, 404);
                $proof = LocalDeliveryProof::create([
                    'tenant_id' => $locked->tenant_id, 'local_shipment_id' => $locked->local_shipment_id,
                    'driver_assignment_id' => $locked->id, 'driver_profile_id' => $locked->driver_profile_id,
                    'received_by_name' => $data['received_by_name'] ?? '', 'receiver_type' => $data['receiver_type'] ?? '',
                    'receiver_notes' => $data['receiver_notes'] ?? null, 'photo_path' => $photoPath,
                    'signature_path' => $signaturePath, 'latitude' => $data['latitude'] ?? null, 'longitude' => $data['longitude'] ?? null,
                    'accuracy_meters' => $data['accuracy_meters'] ?? null, 'captured_at' => now(), 'created_at' => now(),
                ]);
                $this->delivery->deliver($locked, $userId, $proof->id);

                return $proof;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete(array_filter([$photoPath, $signaturePath]));
            throw $exception;
        }
    }

    public function fail(DriverAssignment $assignment, int $userId, array $data, ?UploadedFile $photo): LocalDeliveryFailedAttempt
    {
        $photoPath = $photo ? $this->storeUpload($photo, 'pod/failed') : null;
        try {
            return DB::transaction(function () use ($assignment, $userId, $data, $photoPath): LocalDeliveryFailedAttempt {
                $locked = DriverAssignment::with(['shipment', 'driverProfile'])->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
                abort_unless($locked->status === 'ACTIVE' && $locked->shipment->status === 'OUT_FOR_DELIVERY' && (int) $locked->driverProfile->user_id === $userId, 404);
                $attemptNumber = LocalDeliveryFailedAttempt::where('local_shipment_id', $locked->local_shipment_id)->lockForUpdate()->count() + 1;
                if ($attemptNumber > $this->requirements->forShipment($locked->shipment)->max_delivery_attempts) throw \Illuminate\Validation\ValidationException::withMessages(['attempt' => 'Límite de intentos alcanzado.']);
                $attempt = LocalDeliveryFailedAttempt::create([
                    'tenant_id' => $locked->tenant_id, 'local_shipment_id' => $locked->local_shipment_id,
                    'driver_assignment_id' => $locked->id, 'driver_profile_id' => $locked->driver_profile_id,
                    'attempt_number' => $attemptNumber, 'reason' => $data['reason'], 'notes' => $data['notes'] ?? null, 'photo_path' => $photoPath,
                    'latitude' => $data['latitude'] ?? null, 'longitude' => $data['longitude'] ?? null,
                    'accuracy_meters' => $data['accuracy_meters'] ?? null, 'occurred_at' => now(), 'created_at' => now(),
                ]);
                $this->tracking->transition($locked->shipment, 'DELIVERY_FAILED', $userId, 'Intento de entrega fallido: '.$attempt->reason);

                return $attempt;
            });
        } catch (\Throwable $exception) {
            if ($photoPath) Storage::disk('local')->delete($photoPath);
            throw $exception;
        }
    }

    private function storeUpload(UploadedFile $file, string $directory): string
    {
        $mime = $file->getMimeType();
        $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
        $dimensions = @getimagesize($file->getRealPath());
        if ($extension === null || $dimensions === false || ($dimensions[0] * $dimensions[1]) > 25000000) {
            throw ValidationException::withMessages(['photo' => 'La evidencia debe ser una imagen JPEG, PNG o WEBP válida.']);
        }

        $image = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if ($image === false) {
            throw ValidationException::withMessages(['photo' => 'No fue posible procesar la imagen de evidencia.']);
        }

        ob_start();
        if ($mime === 'image/jpeg') imagejpeg($image, null, 88);
        elseif ($mime === 'image/png') imagepng($image, null, 6);
        else imagewebp($image, null, 88);
        $normalized = ob_get_clean();
        imagedestroy($image);
        if (! is_string($normalized) || $normalized === '') {
            throw ValidationException::withMessages(['photo' => 'No fue posible normalizar la imagen de evidencia.']);
        }

        $path = $directory.'/'.Str::uuid().'.'.$extension;
        Storage::disk('local')->put($path, $normalized);
        return $path;
    }
}
