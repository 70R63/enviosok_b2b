<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CrmClient;
use Illuminate\Http\Request;

class WaitlistController extends Controller
{
    public function index()
    {
        return view('public.proximamente');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'segment' => ['required', 'in:persona,emprendedor,ecommerce,empresa,api'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $clientType = match ($data['segment']) {
            'persona', 'emprendedor' => 'b2c',
            'ecommerce', 'empresa' => 'b2b',
            'api' => 'api',
            default => 'b2c',
        };

        $segmentLabel = match ($data['segment']) {
            'persona' => 'Persona',
            'emprendedor' => 'Emprendedor',
            'ecommerce' => 'Ecommerce',
            'empresa' => 'Empresa',
            'api' => 'API / Integrador',
            default => 'No especificado',
        };

        CrmClient::create([
            'client_type' => $clientType,
            'commercial_status' => 'prospecto',
            'name' => $data['name'],
            'company_name' => $segmentLabel,
            'contact_name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'source' => 'landing_waitlist',
            'lead_status' => 'nuevo',
            'lead_priority' => 'media',
            'reviewed_at' => null,
            'active' => true,
            'notes' => trim(
                "Registro anticipado ZIGO\n" .
                "Segmento: {$segmentLabel}\n" .
                "Teléfono/WhatsApp: {$data['phone']}\n" .
                "Mensaje: " . ($data['message'] ?? 'Sin mensaje')
            ),
        ]);

        return redirect()
            ->route('waitlist.index')
            ->with('success', 'Gracias. Te avisaremos cuando ZIGO esté listo para que seas de los primeros en probarlo.');
    }
}