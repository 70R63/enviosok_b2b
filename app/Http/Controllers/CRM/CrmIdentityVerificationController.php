<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\B2cCotizacion;
use App\Models\B2cIdentityVerification;
use App\Models\B2cIdentityVerificationEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CrmIdentityVerificationController extends Controller
{
    public function index(Request $request)
    {
        $status = strtoupper(
            trim((string) $request->query('status', 'TODOS'))
        );

        $search = trim(
            (string) $request->query('search', '')
        );

        $allowedStatuses = [
            'TODOS',
            B2cIdentityVerification::STATUS_UNVERIFIED,
            B2cIdentityVerification::STATUS_PENDING,
            B2cIdentityVerification::STATUS_CORRECTION,
            B2cIdentityVerification::STATUS_APPROVED,
            B2cIdentityVerification::STATUS_REJECTED,
        ];

        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'TODOS';
        }

        $query = B2cIdentityVerification::query()
            ->with([
                'user',
                'reviewer',
            ])
            ->latest('updated_at');

        if ($status !== 'TODOS') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->whereHas(
                'user',
                function ($userQuery) use ($search) {
                    $userQuery
                        ->where('name', 'like', '%' . $search . '%')
                        ->orWhere(
                            'apellido_paterno',
                            'like',
                            '%' . $search . '%'
                        )
                        ->orWhere(
                            'apellido_materno',
                            'like',
                            '%' . $search . '%'
                        )
                        ->orWhere(
                            'email',
                            'like',
                            '%' . $search . '%'
                        );
                }
            );
        }

        $verifications = $query
            ->paginate(20)
            ->withQueryString();

        $guideCounts = $this->generatedGuideCounts(
            $verifications
                ->getCollection()
                ->pluck('user_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->all()
        );

        $statusCounts = B2cIdentityVerification::query()
            ->select(
                'status',
                DB::raw('COUNT(*) AS total')
            )
            ->groupBy('status')
            ->pluck('total', 'status');

        return view(
            'crm.identity.index',
            compact(
                'verifications',
                'guideCounts',
                'statusCounts',
                'status',
                'search'
            )
        );
    }

    public function show(
        B2cIdentityVerification $verification
    ) {
        $verification->load([
            'user',
            'reviewer',
            'events.actor',
        ]);

        $generatedGuides = $this->generatedGuideCounts([
            (int) $verification->user_id,
        ])->get(
            (int) $verification->user_id,
            0
        );

        return view(
            'crm.identity.show',
            compact(
                'verification',
                'generatedGuides'
            )
        );
    }

    public function document(
        B2cIdentityVerification $verification,
        string $document
    ) {
        $fields = [
            'ine-front' => 'ine_front',
            'ine-back' => 'ine_back',
            'selfie' => 'selfie_with_ine',
        ];

        abort_unless(isset($fields[$document]), 404);

        $field = $fields[$document];
        $path = trim((string) $verification->{$field});

        abort_if(
            $path === ''
            || str_contains($path, '..'),
            404
        );

        $disk = $this->resolveDocumentDisk(
            $verification,
            $path
        );

        abort_unless($disk, 404);

        $extension = pathinfo(
            $path,
            PATHINFO_EXTENSION
        );

        $filename = sprintf(
            'verificacion-%d-%s.%s',
            $verification->id,
            $document,
            $extension ?: 'jpg'
        );

        return Storage::disk($disk)->response(
            $path,
            $filename,
            [
                'Cache-Control' =>
                    'private, no-store, max-age=0',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Disposition' =>
                    'inline; filename="' . $filename . '"',
            ]
        );
    }

    public function approve(
        Request $request,
        B2cIdentityVerification $verification
    ) {
        $data = $request->validate([
            'comments' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $this->assertReviewable($verification);

        $this->transition(
            $verification,
            B2cIdentityVerification::STATUS_APPROVED,
            'APPROVED',
            trim((string) ($data['comments'] ?? '')),
            null
        );

        return redirect()
            ->route(
                'crm.identity.show',
                $verification
            )
            ->with(
                'success',
                'La identidad fue aprobada correctamente.'
            );
    }

    public function requestCorrection(
        Request $request,
        B2cIdentityVerification $verification
    ) {
        $data = $request->validate([
            'documents' => [
                'required',
                'array',
                'min:1',
            ],
            'documents.*' => [
                'required',
                Rule::in([
                    'ine_front',
                    'ine_back',
                    'selfie_with_ine',
                ]),
            ],
            'comments' => [
                'required',
                'string',
                'min:10',
                'max:2000',
            ],
        ]);

        $this->assertReviewable($verification);

        $documents = array_values(
            array_unique($data['documents'])
        );

        $this->transition(
            $verification,
            B2cIdentityVerification::STATUS_CORRECTION,
            'CORRECTION_REQUESTED',
            trim($data['comments']),
            $documents
        );

        return redirect()
            ->route(
                'crm.identity.show',
                $verification
            )
            ->with(
                'success',
                'La corrección fue solicitada al usuario.'
            );
    }

    public function reject(
        Request $request,
        B2cIdentityVerification $verification
    ) {
        $data = $request->validate([
            'comments' => [
                'required',
                'string',
                'min:10',
                'max:2000',
            ],
        ]);

        if (
            $verification->status
            === B2cIdentityVerification::STATUS_APPROVED
        ) {
            throw ValidationException::withMessages([
                'status' =>
                    'Una identidad aprobada no puede rechazarse '
                    . 'desde esta operación.',
            ]);
        }

        $this->transition(
            $verification,
            B2cIdentityVerification::STATUS_REJECTED,
            'REJECTED',
            trim($data['comments']),
            null
        );

        return redirect()
            ->route(
                'crm.identity.show',
                $verification
            )
            ->with(
                'success',
                'La identidad fue rechazada.'
            );
    }

    private function transition(
        B2cIdentityVerification $verification,
        string $newStatus,
        string $eventType,
        string $comments,
        ?array $correctionDocuments
    ): void {
        DB::transaction(
            function () use (
                $verification,
                $newStatus,
                $eventType,
                $comments,
                $correctionDocuments
            ) {
                $verification->refresh();

                $fromStatus = $verification->status;

                $verification->update([
                    'status' => $newStatus,
                    'comments' =>
                        $comments !== ''
                            ? $comments
                            : null,
                    'correction_documents' =>
                        $correctionDocuments,
                    'reviewed_at' => now(),
                    'reviewed_by' => auth()->id(),
                ]);

                B2cIdentityVerificationEvent::create([
                    'identity_verification_id' =>
                        $verification->id,
                    'user_id' =>
                        $verification->user_id,
                    'event_type' => $eventType,
                    'from_status' => $fromStatus,
                    'to_status' => $newStatus,
                    'comments' =>
                        $comments !== ''
                            ? $comments
                            : null,
                    'metadata' =>
                        $correctionDocuments
                            ? [
                                'documents' =>
                                    $correctionDocuments,
                            ]
                            : null,
                    'performed_by' => auth()->id(),
                ]);
            }
        );
    }

    private function assertReviewable(
        B2cIdentityVerification $verification
    ): void {
        if (!$verification->hasCompleteDocuments()) {
            throw ValidationException::withMessages([
                'documents' =>
                    'La solicitud no tiene los tres documentos.',
            ]);
        }

        if (!$verification->isPendingReview()) {
            throw ValidationException::withMessages([
                'status' =>
                    'La solicitud ya no está pendiente de revisión.',
            ]);
        }
    }

    private function resolveDocumentDisk(
        B2cIdentityVerification $verification,
        string $path
    ): ?string {
        $preferred = trim(
            (string) $verification->document_disk
        );

        foreach (
            array_unique(
                array_filter([
                    $preferred,
                    'local',
                    'public',
                ])
            ) as $disk
        ) {
            if (
                in_array($disk, ['local', 'public'], true)
                && Storage::disk($disk)->exists($path)
            ) {
                return $disk;
            }
        }

        return null;
    }

    private function generatedGuideCounts(
        array $userIds
    ) {
        if (empty($userIds)) {
            return collect();
        }

        return B2cCotizacion::query()
            ->select(
                'user_id',
                DB::raw('COUNT(*) AS total')
            )
            ->whereIn('user_id', $userIds)
            ->where(function ($query) {
                $query
                    ->whereNotNull('tracking_number')
                    ->orWhere(
                        'guia_estatus',
                        'GENERADA'
                    )
                    ->orWhere(
                        'estatus',
                        'GUIA_GENERADA'
                    );
            })
            ->groupBy('user_id')
            ->pluck('total', 'user_id');
    }
}