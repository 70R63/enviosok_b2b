<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\B2cAdeudo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CrmDebtController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->get('search', ''));
        $status = strtoupper(trim((string) (
            $request->get('estatus') ?: 'TODOS'
        )));

        $query = B2cAdeudo::query()
            ->with([
                'user:id,name,email',
                'cotizacion:id,user_id,guia_id,tracking_number,logistico,servicio',
                'creator:id,name,email',
            ]);

        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query
                    ->where('tracking_number', 'like', '%' . $search . '%')
                    ->orWhere(
                        'referencia_xperta',
                        'like',
                        '%' . $search . '%'
                    )
                    ->orWhere('concepto', 'like', '%' . $search . '%');

                if (ctype_digit($search)) {
                    $query
                        ->orWhere('id', (int) $search)
                        ->orWhere('cotizacion_id', (int) $search);
                }

                $query->orWhereHas('user', function ($userQuery) use ($search) {
                    $userQuery
                        ->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
                });
            });
        }

        if ($status !== 'TODOS') {
            $query->where('estatus', $status);
        }

        $adeudos = $query
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        $pendingTotal = B2cAdeudo::query()
            ->whereIn('estatus', [
                B2cAdeudo::STATUS_PENDING,
                B2cAdeudo::STATUS_PAYMENT_STARTED,
            ])
            ->sum('monto');

        $pendingCount = B2cAdeudo::query()
            ->whereIn('estatus', [
                B2cAdeudo::STATUS_PENDING,
                B2cAdeudo::STATUS_PAYMENT_STARTED,
            ])
            ->count();

        return view('crm.adeudos.index', compact(
            'adeudos',
            'search',
            'status',
            'pendingTotal',
            'pendingCount'
        ));
    }

    public function show(B2cAdeudo $adeudo)
    {
        $adeudo->load([
            'user:id,name,email',
            'cotizacion',
            'creator:id,name,email',
            'cancelledBy:id,name,email',
        ]);

        return view('crm.adeudos.show', compact('adeudo'));
    }

    public function cancel(
        Request $request,
        B2cAdeudo $adeudo
    ) {
        return $this->closeDebt(
            $request,
            $adeudo,
            B2cAdeudo::STATUS_CANCELLED,
            'Adeudo cancelado correctamente.'
        );
    }

    public function waive(
        Request $request,
        B2cAdeudo $adeudo
    ) {
        return $this->closeDebt(
            $request,
            $adeudo,
            B2cAdeudo::STATUS_WAIVED,
            'Adeudo condonado correctamente.'
        );
    }

    private function closeDebt(
        Request $request,
        B2cAdeudo $adeudo,
        string $status,
        string $message
    ) {
        $data = $request->validate([
            'motivo' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        try {
            DB::transaction(function () use (
                $adeudo,
                $data,
                $status
            ) {
                $locked = B2cAdeudo::query()
                    ->whereKey($adeudo->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (!$locked->isPending()) {
                    throw new \DomainException(
                        'Solo los adeudos pendientes pueden cerrarse.'
                    );
                }

                $locked->forceFill([
                    'estatus' => $status,
                    'cancel_reason' => trim($data['motivo']),
                    'cancelled_by' => auth()->id(),
                    'cancelled_at' => now(),
                ])->save();
            });
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('crm.adeudos.show', $adeudo)
            ->with('success', $message);
    }
}
