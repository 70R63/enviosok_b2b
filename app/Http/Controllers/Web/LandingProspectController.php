<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CrmClient;
use Illuminate\Http\Request;

class LandingProspectController extends Controller
{
    public function empresas()
    {
        return view('landing.empresas');
    }

    public function storeB2B(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'company_name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191'],
            'phone' => ['required', 'string', 'max:50'],
            'city' => ['nullable', 'string', 'max:100'],
            'monthly_shipments' => ['nullable', 'string', 'max:100'],
            'interest' => ['nullable', 'string', 'max:191'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        CrmClient::create([
            'client_type' => 'b2b',
            'commercial_status' => 'prospecto',
            'lead_status' => 'nuevo',
            'lead_priority' => 'media',
            'reviewed_at' => null,
            'name' => $data['name'],
            'company_name' => $data['company_name'],
            'contact_name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'source' => 'landing_b2b',
            'notes' => trim(
                "Ciudad/Estado: " . ($data['city'] ?? '-') . "\n" .
                "Volumen mensual: " . ($data['monthly_shipments'] ?? '-') . "\n" .
                "Interés: " . ($data['interest'] ?? '-') . "\n" .
                "Mensaje: " . ($data['message'] ?? '-')
            ),
            'active' => true,
        ]);

        return redirect()
            ->route('landing.empresas')
            ->with('success', 'Solicitud enviada correctamente. Un asesor ZIGO te contactará a la brevedad.');
    }
}