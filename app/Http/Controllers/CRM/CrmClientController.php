<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\CrmClient;
use Illuminate\Http\Request;

class CrmClientController extends Controller
{
    public function index(Request $request)
    {
        $query = CrmClient::query()
            ->orderByDesc('id');

        if ($request->filled('client_type')) {
            $query->where('client_type', $request->client_type);
        }

        if ($request->filled('commercial_status')) {
            $query->where('commercial_status', $request->commercial_status);
        }

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhere('contact_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $clients = $query->paginate(15)->withQueryString();

        return view('crm.clientes.index', compact('clients'));
    }

    public function create()
    {
        return view('crm.clientes.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'client_type' => ['required', 'in:b2c,b2b,api,mixto'],
            'commercial_status' => ['required', 'in:prospecto,activo,suspendido,perdido'],
            'name' => ['required', 'string', 'max:191'],
            'company_name' => ['nullable', 'string', 'max:191'],
            'contact_name' => ['nullable', 'string', 'max:191'],
            'email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:50'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'source' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['active'] = $request->boolean('active', true);
        $data['source'] = $data['source'] ?? 'manual';

        CrmClient::create($data);

        return redirect()
            ->route('crm.clientes.index')
            ->with('success', 'Cliente creado correctamente.');
    }

    public function edit(CrmClient $cliente)
    {
        return view('crm.clientes.edit', compact('cliente'));
    }

    public function update(Request $request, CrmClient $cliente)
    {
        $data = $request->validate([
            'client_type' => ['required', 'in:b2c,b2b,api,mixto'],
            'commercial_status' => ['required', 'in:prospecto,activo,suspendido,perdido'],
            'name' => ['required', 'string', 'max:191'],
            'company_name' => ['nullable', 'string', 'max:191'],
            'contact_name' => ['nullable', 'string', 'max:191'],
            'email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:50'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'source' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['active'] = $request->boolean('active');

        $cliente->update($data);

        return redirect()
            ->route('crm.clientes.index')
            ->with('success', 'Cliente actualizado correctamente.');
    }

    public function destroy(CrmClient $cliente)
    {
        $cliente->update([
            'active' => false,
            'commercial_status' => 'suspendido',
        ]);

        return redirect()
            ->route('crm.clientes.index')
            ->with('success', 'Cliente suspendido correctamente.');
    }

    public function actualizarSeguimiento(Request $request, CrmClient $cliente)
    {
        $data = $request->validate([
            'lead_status' => ['required', 'in:nuevo,sin_revisar,contactado,cita_agendada,en_negociacion,convertido,descartado'],
            'lead_priority' => ['required', 'in:baja,media,alta,urgente'],
            'next_follow_up_at' => ['nullable', 'date'],
            'internal_notes' => ['nullable', 'string'],
        ]);

        $updateData = [
            'lead_status' => $data['lead_status'],
            'lead_priority' => $data['lead_priority'],
            'next_follow_up_at' => $data['next_follow_up_at'] ?? null,
            'internal_notes' => $data['internal_notes'] ?? null,
            'reviewed_at' => $cliente->reviewed_at ?? now(),
        ];

        if (in_array($data['lead_status'], [
            'contactado',
            'cita_agendada',
            'en_negociacion',
            'convertido',
            'descartado',
        ])) {
            $updateData['last_contact_at'] = now();
        }

        if ($data['lead_status'] === 'convertido') {
            $updateData['commercial_status'] = 'activo';
            $updateData['active'] = true;
        }

        if ($data['lead_status'] === 'descartado') {
            $updateData['commercial_status'] = 'perdido';
            $updateData['active'] = false;
        }

        $cliente->update($updateData);

        return redirect()
            ->route('crm.clientes.edit', $cliente)
            ->with('success', 'Seguimiento comercial actualizado correctamente.');
    }
}