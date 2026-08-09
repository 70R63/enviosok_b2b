<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Shipping\LastMile\Models\DriverAssignment;
use App\Domain\Shipping\LastMile\Models\DriverCompensationPolicy;
use App\Domain\Shipping\LastMile\Models\DriverDeliveryAttribution;
use App\Domain\Shipping\LastMile\Models\DriverEarningEntry;
use App\Domain\Shipping\LastMile\DriverPresenceService;
use App\Domain\Shipping\LastMile\DeliveryEvidenceService;
use App\Domain\Shipping\LastMile\DeliveryRequirementService;
use App\Domain\Shipping\LastMile\Models\LocalDeliveryFailedAttempt;
use App\Domain\Shipping\LastMile\Models\LocalDeliveryProof;
use App\Domain\Shipping\Local\LocalTrackingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class DriverConsoleController extends Controller
{
    public function index(Request $request)
    {
        return view('tenant.driver.dashboard', $this->viewData($request));
    }

    public function deliveries(Request $request)
    {
        return view('tenant.driver.deliveries', $this->viewData($request));
    }

    public function earnings(Request $request)
    {
        return view('tenant.driver.earnings', $this->viewData($request));
    }

    public function profile(Request $request)
    {
        return view('tenant.driver.profile', $this->viewData($request));
    }

    public function support(Request $request)
    {
        return view('tenant.driver.support', $this->viewData($request));
    }

    private function viewData(Request $request): array
    {
        $profile = $request->attributes->get('driver_profile');
        $assignments = DriverAssignment::with('shipment')->where('tenant_id', $profile->tenant_id)->where('driver_profile_id', $profile->id)->where('status', 'ACTIVE')
            ->whereHas('shipment', fn ($query) => $query->whereIn('status', ['READY_FOR_PICKUP', 'PICKED_UP', 'IN_TRANSIT', 'OUT_FOR_DELIVERY', 'DELIVERY_FAILED']))->get();
        $tenant = $profile->tenant()->with('branding')->firstOrFail();
        $delivered = DriverDeliveryAttribution::where('tenant_id', $profile->tenant_id)->where('driver_profile_id', $profile->id)->count();
        $earnings = DriverEarningEntry::where('tenant_id', $profile->tenant_id)->where('driver_profile_id', $profile->id)->where('entry_type', 'DELIVERY_EARNING')->sum('amount');
        $completedDeliveries = DriverDeliveryAttribution::with('shipment')
            ->where('tenant_id', $profile->tenant_id)
            ->where('driver_profile_id', $profile->id)
            ->latest('delivered_at')
            ->limit(10)
            ->get();
        $completedEarnings = DriverEarningEntry::where('tenant_id', $profile->tenant_id)
            ->where('driver_profile_id', $profile->id)
            ->where('entry_type', 'DELIVERY_EARNING')
            ->whereIn('local_shipment_id', $completedDeliveries->pluck('local_shipment_id'))
            ->get()
            ->keyBy('local_shipment_id');
        $policy = DriverCompensationPolicy::where('tenant_id', $profile->tenant_id)
            ->where('driver_profile_id', $profile->id)
            ->first();

        return compact('tenant', 'profile', 'assignments', 'delivered', 'earnings', 'policy', 'completedDeliveries', 'completedEarnings');
    }

    public function show(Request $request, string $shipment)
    {
        $assignment = $this->assignment($request, $shipment);
        $profile = $request->attributes->get('driver_profile');

        return view('tenant.driver.shipment', ['tenant' => $profile->tenant()->with('branding')->firstOrFail(), 'profile' => $profile, 'assignment' => $assignment, 'attemptsRemaining' => app(DeliveryRequirementService::class)->attemptsRemaining($assignment->shipment)]);
    }

    public function transition(Request $request, string $shipment, LocalTrackingService $tracking, DeliveryRequirementService $requirements)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['PICKED_UP', 'IN_TRANSIT', 'OUT_FOR_DELIVERY'])]]);
        $assignment = $this->assignment($request, $shipment);
        if ($data['status'] === 'OUT_FOR_DELIVERY' && $assignment->shipment->status === 'DELIVERY_FAILED' && $requirements->attemptsRemaining($assignment->shipment) < 1) {
            throw ValidationException::withMessages(['status' => 'Límite de intentos alcanzado.']);
        }
        try {
            $tracking->transition($assignment->shipment, $data['status'], auth()->id());
        } catch (\DomainException $exception) {
            throw ValidationException::withMessages(['status' => $exception->getMessage()]);
        }

        return back()->with('success', 'Estado actualizado.');
    }

    public function proofForm(Request $request, string $shipment, DeliveryRequirementService $requirements)
    {
        $assignment = $this->assignment($request, $shipment);
        abort_unless($assignment->shipment->status === 'OUT_FOR_DELIVERY', 404);
        return view('tenant.driver.proof', ['tenant' => $assignment->driverProfile->tenant()->with('branding')->firstOrFail(), 'assignment' => $assignment, 'requirement' => $requirements->forShipment($assignment->shipment)]);
    }

    public function storeProof(Request $request, string $shipment, DeliveryEvidenceService $evidence, DeliveryRequirementService $requirements)
    {
        $assignment = $this->assignment($request, $shipment);
        $requirement = $requirements->forShipment($assignment->shipment);
        $data = $request->validate($requirements->proofRules($requirement), [
            'received_by_name.required' => 'Escribe el nombre de quien recibe.',
            'receiver_type.required' => 'Selecciona el tipo de receptor.',
            'receiver_type.in' => 'El tipo de receptor seleccionado no es válido.',
            'photo.required' => 'Selecciona una fotografía de evidencia.',
            'photo.image' => 'La fotografía debe ser una imagen válida.',
            'photo.mimes' => 'La fotografía debe ser JPEG, PNG o WEBP.',
            'photo.max' => 'La fotografía no debe exceder 5 MB.',
            'signature.required' => 'Captura la firma de quien recibe.',
            'latitude.required' => 'Captura la ubicación GPS para finalizar.',
            'longitude.required' => 'Captura la ubicación GPS para finalizar.',
            'accuracy_meters.required' => 'No se recibió la precisión de la ubicación GPS.',
        ]);
        if ($request->file('photo')) $this->validateImageContent($request->file('photo'));
        $signature = ! empty($data['signature']) ? $this->signatureBinary($data['signature']) : null;
        $requirements->assertEvidence($requirement, $data, $request->file('photo'), $signature);
        $evidence->deliver($assignment, auth()->id(), $data, $request->file('photo'), $signature);
        return redirect()->route('tenant.driver.dashboard')->with('success', 'Entrega completada correctamente.');
    }

    public function failureForm(Request $request, string $shipment, DeliveryRequirementService $requirements)
    {
        $assignment = $this->assignment($request, $shipment);
        abort_unless($assignment->shipment->status === 'OUT_FOR_DELIVERY', 404);
        $requirement = $requirements->forShipment($assignment->shipment);
        return view('tenant.driver.failure', ['tenant' => $assignment->driverProfile->tenant()->with('branding')->firstOrFail(), 'assignment' => $assignment, 'requirement' => $requirement, 'attemptNumber' => $assignment->shipment->failedDeliveryAttempts()->count() + 1]);
    }

    public function storeFailure(Request $request, string $shipment, DeliveryEvidenceService $evidence)
    {
        $assignment = $this->assignment($request, $shipment);
        $data = $request->validate([
            'reason' => ['required', Rule::in(LocalDeliveryFailedAttempt::REASONS)], 'notes' => ['nullable', 'required_if:reason,OTHER', 'string', 'max:500'],
            'photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'], 'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['nullable', 'numeric', 'min:0', 'max:10000'],
        ], [
            'reason.required' => 'Selecciona la razón del intento fallido.',
            'reason.in' => 'La razón seleccionada no es válida.',
            'notes.required_if' => 'Describe lo ocurrido cuando seleccionas Otro.',
            'photo.image' => 'La fotografía debe ser una imagen válida.',
            'photo.mimes' => 'La fotografía debe ser JPEG, PNG o WEBP.',
            'photo.max' => 'La fotografía no debe exceder 5 MB.',
        ]);
        if ($request->file('photo')) $this->validateImageContent($request->file('photo'));
        $evidence->fail($assignment, auth()->id(), $data, $request->file('photo'));
        return redirect()->route('tenant.driver.dashboard')->with('success', 'Intento de entrega registrado.');
    }

    private function signatureBinary(string $payload): string
    {
        if (! str_starts_with($payload, 'data:image/png;base64,')) throw ValidationException::withMessages(['signature' => 'La firma no es válida.']);
        $binary = base64_decode(substr($payload, 22), true);
        $image = $binary === false ? false : @getimagesizefromstring($binary);
        if ($binary === false || strlen($binary) > 512000 || ! $image || ($image['mime'] ?? null) !== 'image/png' || $image[0] < 100 || $image[1] < 40) throw ValidationException::withMessages(['signature' => 'La firma no es válida.']);
        return $binary;
    }

    private function validateImageContent(\Illuminate\Http\UploadedFile $file): void
    {
        $image = @getimagesize($file->getRealPath());
        if (! $image || ! in_array($image['mime'] ?? null, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw ValidationException::withMessages(['photo' => 'La fotografía no es una imagen permitida.']);
        }
    }

    public function availability(Request $request, DriverPresenceService $presence)
    {
        $data = $request->validate(['availability_status' => ['required', Rule::in(['AVAILABLE', 'UNAVAILABLE'])]]);
        $profile = $request->attributes->get('driver_profile');
        $presence->setAvailability($profile, $data['availability_status']);

        return back()->with('success', 'Disponibilidad actualizada.');
    }

    private function assignment(Request $request, string $shipment): DriverAssignment
    {
        $profile = $request->attributes->get('driver_profile');

        return DriverAssignment::with('shipment')->where('tenant_id', $profile->tenant_id)->where('driver_profile_id', $profile->id)->where('status', 'ACTIVE')->whereHas('shipment', fn ($query) => $query->where('uuid', $shipment)->whereIn('status', ['READY_FOR_PICKUP', 'PICKED_UP', 'IN_TRANSIT', 'OUT_FOR_DELIVERY', 'DELIVERY_FAILED']))->firstOrFail();
    }
}
