<?php

namespace App\Http\Controllers\B2C;

use App\Http\Controllers\Controller;
use App\Negocio\Guias\EstafetaCreacion;
use App\Models\B2cSaldo;
use App\Models\B2cMovimientoSaldo;
use App\Models\B2cCotizacion;
use App\Models\User;
use App\Models\Roles\Roles;
use App\Models\Guia;
use App\Models\B2cDireccion;
use App\Models\B2cIdentityVerification;
use App\Models\B2cIncidencia;
use App\Services\ZigoPricingService;
use App\Services\ZigoProviderRateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;
use Illuminate\Support\Facades\DB;
use MercadoPago\SDK;
use MercadoPago\Preference;
use MercadoPago\Item;
use Carbon\Carbon;

class CotizacionPublicaController extends Controller
{

    public function cotizar(Request $request)
    {
        $data = $request->validate([
            'cp_origen' => ['required', 'string', 'max:120'],
            'colonia_origen' => ['nullable', 'string', 'max:255'],
            'cp_destino' => ['required', 'string', 'max:120'],
            'colonia_destino' => ['nullable', 'string', 'max:255'],
            'tipo_envio' => ['required', 'in:caja,sobre'],
            'peso' => ['required', 'numeric', 'min:0.1'],
            'medidas' => ['nullable', 'string', 'max:50'],
            'ciudad_origen' => ['nullable', 'string', 'max:100'],
            'estado_origen' => ['nullable', 'string', 'max:100'],
            'ciudad_destino' => ['nullable', 'string', 'max:100'],
            'estado_destino' => ['nullable', 'string', 'max:100'],
        ]);

        $tipoEnvio = strtolower($data['tipo_envio']);
        $pesoReal = (float) str_replace(',', '.', $data['peso']);

        $cpOrigen = substr(preg_replace('/\D/', '', $data['cp_origen']), 0, 5);
        $cpDestino = substr(preg_replace('/\D/', '', $data['cp_destino']), 0, 5);

        if (strlen($cpOrigen) !== 5 || strlen($cpDestino) !== 5) {
            return redirect()
                ->to(url('/') . '#cotizar')
                ->withInput()
                ->with('error', 'Debes seleccionar un código postal válido de origen y destino.');
        }

        if ($tipoEnvio === 'sobre') {
            $pesoFinal = 1.00;
            $medidasFinal = null;
        } else {
            $medidasFinal = $data['medidas'] ?? null;

            if (!$medidasFinal) {
                return redirect()
                    ->to(url('/') . '#cotizar')
                    ->withInput()
                    ->with('error', 'Para cotizar una caja debes capturar largo, alto y ancho.');
            }

            $partes = preg_split('/x|\*|,|;|\s+/', strtolower($medidasFinal));
            $partes = array_values(array_filter($partes, fn ($value) => $value !== ''));

            if (count($partes) < 3) {
                return redirect()
                    ->to(url('/') . '#cotizar')
                    ->withInput()
                    ->with('error', 'Las dimensiones de la caja no son válidas.');
            }

            $largo = max((float) $partes[0], 1);
            $alto = max((float) $partes[1], 1);
            $ancho = max((float) $partes[2], 1);

            $pesoVolumetrico = round(($largo * $alto * $ancho) / 5000, 2);
            $pesoFinal = max($pesoReal, $pesoVolumetrico);
        }

        $cpOrigen = substr($data['cp_origen'], 0, 5);
        $cpDestino = substr($data['cp_destino'], 0, 5);

        $ubicacionOrigen = $this->resolverUbicacionPostal($cpOrigen, $data['colonia_origen'] ?? null);
        $ubicacionDestino = $this->resolverUbicacionPostal($cpDestino, $data['colonia_destino'] ?? null);

        $cotizacion = B2cCotizacion::create([
            'user_id' => null,
            'cp_origen' => $cpOrigen,
            'colonia_origen' => $data['colonia_origen'] ?? $ubicacionOrigen['colonia'],
            'cp_destino' => $cpDestino,
            'colonia_destino' => $data['colonia_destino'] ?? $ubicacionDestino['colonia'],
            'tipo_envio' => $tipoEnvio,
            'peso' => $pesoFinal,
            'medidas' => $medidasFinal,
            'estatus' => 'COTIZADA',
            'referencia' => 'LANDING_PUBLICA',
            'ciudad_origen' => $data['ciudad_origen'] ?? $ubicacionOrigen['ciudad'],
            'estado_origen' => $data['estado_origen'] ?? $ubicacionOrigen['estado'],
            'ciudad_destino' => $data['ciudad_destino'] ?? $ubicacionDestino['ciudad'],
            'estado_destino' => $data['estado_destino'] ?? $ubicacionDestino['estado'],
        ]);

        session([
            'cotizacion_publica_id' => $cotizacion->id,
        ]);

        return redirect()->to(url('/') . '#cotizar');
    }

    public function seleccionar(Request $request, B2cCotizacion $cotizacion)
    {
        $data = $request->validate([
            'logistico' => ['required', 'string', 'max:100'],
            'servicio' => ['required', 'string', 'max:100'],
        ]);

        $tipoEnvio = strtolower($cotizacion->tipo_envio ?? 'caja');

        $requiereLogin = false;

        if ($cotizacion->referencia === 'LANDING_PUBLICA') {
            if ($tipoEnvio === 'caja') {
                $requiereLogin = true;
            }

            if ($tipoEnvio === 'sobre') {
                $cotizacion->update([
                    'peso' => 1.00,
                    'medidas' => null,
                ]);

                $cotizacion->refresh();
            }
        }

        if ($requiereLogin) {
            return redirect()
                ->to(url('/') . '#cotizar')
                ->with(
                    'login_required',
                    'Para continuar con envíos tipo caja necesitas iniciar sesión o crear una cuenta.'
                );
        }

        $option = $this->findSelectedOption($cotizacion, $data['logistico'], $data['servicio']);

        if (!$option) {
            return back()->with('error', 'La opción seleccionada no es válida.');
        }

        $this->applyPricingToCotizacion($cotizacion, $option);

        return redirect()->route('b2c.checkout', $cotizacion->id);
    }

    public function seleccionarNuevoEnvio(Request $request, B2cCotizacion $cotizacion)
    {
        $data = $request->validate([
            'logistico' => ['required', 'string', 'max:100'],
            'servicio' => ['required', 'string', 'max:100'],
            'metodo_pago' => ['nullable', 'string'],
        ]);

        if ($cotizacion->user_id !== auth()->id()) {
            abort(403);
        }

        $option = $this->findSelectedOption($cotizacion, $data['logistico'], $data['servicio']);

        if (!$option) {
            return back()->with('error', 'La opción seleccionada no es válida.');
        }

        $this->applyPricingToCotizacion($cotizacion, $option);

        $cotizacion->refresh();

        if (($data['metodo_pago'] ?? null) === 'saldo') {
            return $this->pagarConSaldo($cotizacion);
        }

        return redirect()->route('b2c.pago', $cotizacion->id);
    }

public function checkout(B2cCotizacion $cotizacion)
{
    if (
        $cotizacion->user_id !== null
        && auth()->check()
        && $cotizacion->user_id !== auth()->id()
    ) {
        abort(403);
    }

    if (
        $cotizacion->user_id !== null
        && !auth()->check()
    ) {
        return redirect()->route('login');
    }

    return view('b2c.checkout', compact('cotizacion'));
}

public function procesarCheckout(Request $request, B2cCotizacion $cotizacion)
{
    $data = $request->validate([
        'remitente_nombre' => ['required', 'string', 'max:255'],
        'remitente_telefono' => ['required', 'string', 'max:30'],
        'remitente_email' => ['required', 'email', 'max:255'],
        'remitente_direccion' => ['required', 'string', 'max:255'],

        'remitente_num_ext' => 'required|string|max:50',
        'remitente_num_int' => 'nullable|string|max:50',
        'ciudad_origen' => 'required|string|max:100',
        'estado_origen' => 'required|string|max:100',

        'destinatario_num_ext' => 'required|string|max:50',
        'destinatario_num_int' => 'nullable|string|max:50',
        'ciudad_destino' => 'required|string|max:100',
        'estado_destino' => 'required|string|max:100',

        'destinatario_nombre' => ['required', 'string', 'max:255'],
        'destinatario_telefono' => ['required', 'string', 'max:30'],
        'destinatario_email' => ['nullable', 'email', 'max:255'],
        'destinatario_direccion' => ['required', 'string', 'max:255'],

        'contenido' => ['required', 'string', 'max:255'],
        'valor_declarado' => ['nullable', 'numeric', 'min:0'],
        'referencia' => ['nullable', 'string', 'max:255'],
    ]);

    $cotizacion->update([
        ...$data,
        'valor_declarado' => $data['valor_declarado'] ?? 0,
        'estatus' => 'CHECKOUT_COMPLETO',
    ]);

    return redirect()
        ->route('b2c.pago', $cotizacion->id);
}

public function pago(B2cCotizacion $cotizacion)
{
    $accessToken = env('MERCADOPAGO_ACCESS_TOKEN');

    if (empty($accessToken)) {
        abort(500, 'Falta configurar MERCADOPAGO_ACCESS_TOKEN en .env');
    }

    if (!$cotizacion->logistico || !$cotizacion->servicio || (float) $cotizacion->precio <= 0) {
        return redirect()
            ->route('b2c.opciones', $cotizacion->id)
            ->with('error', 'Primero selecciona una paquetería válida.');
    }

    SDK::setAccessToken($accessToken);

    $item = new Item();
    $item->title = 'Guía de envío ' . $cotizacion->logistico . ' - ' . $cotizacion->servicio;
    $item->quantity = 1;
    $item->unit_price = (float) $cotizacion->precio;
    $item->currency_id = 'MXN';

    $preference = new Preference();
    $preference->items = [$item];
    $preference->external_reference = 'B2C-' . $cotizacion->id;

    $baseUrl = rtrim(config('app.url'), '/');

    $preference->back_urls = [
        'success' => $baseUrl . '/b2c/pago/' . $cotizacion->id . '/success',
        'failure' => $baseUrl . '/b2c/pago/' . $cotizacion->id . '/failure',
        'pending' => $baseUrl . '/b2c/pago/' . $cotizacion->id . '/pending',
    ];

    $preference->auto_return = 'approved';

    $preference->save();

    if (!$preference->id || !$preference->init_point) {
        \Log::error('Mercado Pago no creó preferencia', [
            'cotizacion_id' => $cotizacion->id,
            'precio' => $cotizacion->precio,
            'error' => $preference->error ?? null,
        ]);

        abort(500, 'Mercado Pago no pudo generar la preferencia de pago.');
    }

    $cotizacion->update([
        'estatus' => 'PAGO_INICIADO',
    ]);

    return redirect($preference->init_point);
}

public function pagoSuccess(Request $request, B2cCotizacion $cotizacion)
{
    if ($cotizacion->estatus !== 'GUIA_GENERADA') {
        $cotizacion->update([
            'estatus' => 'PAGADA',
            'payment_id' => $request->get('payment_id') ?? $cotizacion->payment_id,
            'payment_status' => $request->get('status') ?? $request->get('collection_status') ?? $cotizacion->payment_status,
            'payment_external_reference' => $request->get('external_reference') ?? $cotizacion->payment_external_reference,
            'payment_collection_id' => $request->get('collection_id') ?? $cotizacion->payment_collection_id,
        ]);
    }

    session()->forget([
        'cotizacion_publica_id',
        'login_required',
    ]);

    $cotizacion->refresh();

    return view('b2c.pago-success', compact('cotizacion'));
}

public function pagoFailure(Request $request, B2cCotizacion $cotizacion)
{
    $cotizacion->update([
        'estatus' => 'PAGO_RECHAZADO',
        'payment_id' => $request->get('payment_id'),
        'payment_status' => $request->get('status'),
        'payment_external_reference' => $request->get('external_reference'),
        'payment_collection_id' => $request->get('collection_id'),
    ]);

    return view('b2c.pago-failure', compact('cotizacion'));
}

public function pagoPending(Request $request, B2cCotizacion $cotizacion)
{
    $cotizacion->update([
        'estatus' => 'PAGO_PENDIENTE',
        'payment_id' => $request->get('payment_id'),
        'payment_status' => $request->get('status'),
        'payment_external_reference' => $request->get('external_reference'),
        'payment_collection_id' => $request->get('collection_id'),
    ]);

    return view('b2c.pago-pending', compact('cotizacion'));
}

public function generarGuia(B2cCotizacion $cotizacion)
{
    if ($cotizacion->estatus !== 'PAGADA') {
        abort(403, 'La cotización aún no está pagada.');
    }

    if ($cotizacion->logistico !== 'Estafeta') {
        return 'Por ahora conectaremos primero Estafeta. Logístico actual: ' . $cotizacion->logistico;
    }

    $payload = $this->buildEstafetaPayload($cotizacion);

try {
    $generador = new EstafetaCreacion();
    $generador->parseoApi($payload);
} catch (\Throwable $e) {
    \Log::error('Error generando guía B2C', [
        'cotizacion_id' => $cotizacion->id,
        'error' => $e->getMessage(),
    ]);

    $cotizacion->update([
        'guia_estatus' => 'ERROR_PROVEEDOR',
        'estatus' => 'ERROR_GENERACION_GUIA',
    ]);

    return back()->with('error', 'No fue posible generar la guía. Error proveedor: ' . $e->getMessage());
}

    $guia = Guia::withoutGlobalScopes()->orderBy('id', 'desc')->first();

    $tracking = $guia->tracking_number ?? null;

    if (!$tracking || str_contains($tracking, 'Exception') || str_contains($tracking, 'SAXParseException')) {
        $cotizacion->update([
            'guia_id' => $guia->id ?? null,
            'tracking_number' => null,
            'documento' => null,
            'guia_estatus' => 'ERROR_PROVEEDOR',
            'estatus' => 'ERROR_GENERACION_GUIA',
        ]);

        return back()->with('error', 'Estafeta rechazó el payload: ' . ($tracking ?? 'Sin detalle'));
    }

    $cotizacion->update([
        'guia_id' => $guia->id ?? null,
        'tracking_number' => $guia->tracking_number ?? null,
        'documento' => $guia->documento ?? null,
        'guia_estatus' => 'GENERADA',
        'estatus' => 'GUIA_GENERADA',
    ]);

    $pdfUrl = asset('storage/' . basename($cotizacion->documento));

    return redirect()
        ->route('b2c.pago.success', $cotizacion->id)
        ->with('success', 'Guía generada correctamente. Tracking: ' . ($guia->tracking_number ?? 'N/A'))
        ->with('pdf_url', $pdfUrl);
    }

private function buildEstafetaPayload(B2cCotizacion $cotizacion): array
{
    $cpOrigen = substr($cotizacion->cp_origen, 0, 5);
    $cpDestino = substr($cotizacion->cp_destino, 0, 5);

    [$alto, $largo, $ancho] = array_pad(
        array_map('trim', explode('x', strtolower($cotizacion->medidas ?? '20x20x20'))),
        3,
        20
    );

    return [
        'user_id' => (int) env('B2C_USER_ID', 9),
        'empresa_id' => (int) env('B2C_EMPRESA_ID', 1),
        'ltd_id' => 2,
        'servicio_id' => (int) env('B2C_ESTAFETA_SERVICIO_ID', 1),
        'esManual' => 'API',
        'canal' => 'API',
        'formatoImpresion' => 'FILE_PDF',

        'labelDefinition' => [
            'wayBillDocument' => [
                'content' => $cotizacion->contenido ?? 'Paquete',
                'aditionalInfo' => $cotizacion->referencia ?: 'SIN REFERENCIA',
            ],
            'itemDescription' => [
                'parcelId' => 4,
                'weight' => (string) $cotizacion->peso,
                'height' => (string) $alto,
                'length' => (string) $largo,
                'width' => (string) $ancho,
            ],
            'serviceConfiguration' => [
                'quantityOfLabels' => 1,
                'originZipCodeForRouting' => $cpOrigen,
                'isInsurance' => false,
                'insurance' => null,
                'isReturnDocument' => false,
                'effectiveDate' => Carbon::now()->format('Ymd'),
            ],
            'location' => [
                'origin' => [
                    'contact' => [
                        'corporateName' => $cotizacion->remitente_nombre,
                        'contactName' => $cotizacion->remitente_nombre,
                        'cellPhone' => $cotizacion->remitente_telefono,
                        'email' => filter_var($cotizacion->remitente_email, FILTER_VALIDATE_EMAIL)
                            ? $cotizacion->remitente_email
                            : 'no-reply@enviosok.com',
                        'taxPayerCode' => 'XAXX010101000',
                    ],
                    'address' => [
                        'bUsedCode' => false,
                        'roadTypeAbbName' => 'Calle',
                        'roadName' => $cotizacion->remitente_direccion,
                        'settlementTypeAbbName' => 'Col',
                        'settlementName' => substr($cotizacion->colonia_origen ?: 'CENTRO', 0, 35),
                        'zipCode' => $cpOrigen,
                        'countryName' => 'MEX',
                        'ciudad' => $cotizacion->ciudad_origen,
                        'entidad' => $cotizacion->estado_origen,
                        'addressReference' => $cotizacion->referencia ?: 'SIN REFERENCIA',
                        'externalNum' => $cotizacion->remitente_num_ext,
                        'indoorInformation' => $cotizacion->remitente_num_int ?? 'SN',
                    ],
                ],
                'destination' => [
                    'isDeliveryToPUDO' => false,
                    'homeAddress' => [
                        'contact' => [
                            'corporateName' => $cotizacion->destinatario_nombre,
                            'contactName' => $cotizacion->destinatario_nombre,
                            'cellPhone' => $cotizacion->destinatario_telefono,
                            'email' => filter_var($cotizacion->destinatario_email, FILTER_VALIDATE_EMAIL)
                                ? $cotizacion->destinatario_email
                                : 'no-reply@enviosok.com',
                            'taxPayerCode' => 'XAXX010101000',
                        ],
                        'address' => [
                            'bUsedCode' => false,
                            'roadTypeAbbName' => 'Calle',
                            'roadName' => $cotizacion->destinatario_direccion,
                            'settlementTypeAbbName' => 'Col',
                            'settlementName' => substr($cotizacion->colonia_destino ?: 'CENTRO', 0, 35),
                            'zipCode' => $cpDestino,
                            'countryName' => 'MEX',
                            'ciudad' => $cotizacion->ciudad_destino,
                            'entidad' => $cotizacion->estado_destino,
                            'addressReference' => $cotizacion->referencia ?: 'SIN REFERENCIA',
                            'externalNum' => $cotizacion->destinatario_num_ext,
                            'indoorInformation' => $cotizacion->destinatario_num_int ?? 'SN',
                        ],
                    ],
                ],
            ],
        ],
    ];
}

public function rastreoPublico()
{
    return view('b2c.rastreo');
}

public function buscarRastreoPublico(Request $request)
{
    $request->validate([
        'tracking_number' => ['required', 'string', 'max:100'],
    ]);

    $tracking = trim($request->tracking_number);

    $cotizacion = B2cCotizacion::where('tracking_number', $tracking)
        ->orWhere('documento', $tracking)
        ->first();

    return view('b2c.rastreo', [
        'tracking' => $tracking,
        'cotizacion' => $cotizacion,
    ]);
}

public function registroB2c()
{
    return view('b2c.register');
}

public function guardarRegistroB2c(Request $request)
{
    $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'apellido_paterno' => ['required', 'string', 'max:255'],
        'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
        'password' => ['required', 'confirmed', Rules\Password::defaults()],
    ]);

    $user = User::create([
        'name' => $request->name,
        'apellido_paterno' => $request->apellido_paterno,
        'email' => $request->email,
        'password' => Hash::make($request->password),
        'empresa_id' => 1,
    ]);

    $rolCliente = Roles::where('slug', 'cliente')->first();

      if ($rolCliente) {
         \DB::table('users_roles')->insertOrIgnore([
        'user_id' => $user->id,
        'roles_id' => $rolCliente->id,
    ]);
}

    Auth::login($user);

    return redirect()
        ->route('b2c.dashboard')
        ->with('success', 'Cuenta creada correctamente.');
}

public function dashboardB2c()
{
    $userId = auth()->id();

    $totalCotizaciones = B2cCotizacion::where('user_id', $userId)->count();

    $totalPagadas = B2cCotizacion::where('user_id', $userId)
        ->where('estatus', 'PAGADA')
        ->count();

    $totalGuias = B2cCotizacion::where('user_id', $userId)
        ->whereNotNull('tracking_number')
        ->count();

    $totalErrores = B2cCotizacion::where('user_id', $userId)
        ->where('estatus', 'like', '%ERROR%')
        ->count();

    $ultimosEnvios = B2cCotizacion::where('user_id', $userId)
        ->latest()
        ->take(5)
        ->get();

    return view('b2c.dashboard', compact(
        'totalCotizaciones',
        'totalPagadas',
        'totalGuias',
        'totalErrores',
        'ultimosEnvios'
    ));
}

public function misEnviosB2c()
{
    $envios = B2cCotizacion::where('user_id', auth()->id())
        ->latest()
        ->get();

    return view('b2c.mis-envios', compact('envios'));
}

public function detalleEnvioB2c(B2cCotizacion $cotizacion)
{
    if ($cotizacion->user_id !== auth()->id()) {
        abort(403);
    }

    return view('b2c.detalle-envio', compact('cotizacion'));
}

public function misPagosB2c()
{
    $pagos = B2cCotizacion::where('user_id', auth()->id())
        ->whereNotNull('payment_id')
        ->latest()
        ->get();

    return view('b2c.mis-pagos', compact('pagos'));
}

public function nuevoEnvioB2c()
{
    $direccionesOrigen = B2cDireccion::where('user_id', auth()->id())
        ->where('activo', true)
        ->where('tipo', 'ORIGEN')
        ->orderByDesc('favorita')
        ->latest()
        ->get();

    $direccionesDestino = B2cDireccion::where('user_id', auth()->id())
        ->where('activo', true)
        ->where('tipo', 'DESTINO')
        ->orderByDesc('favorita')
        ->latest()
        ->get();

    return view('b2c.nuevo-envio', compact('direccionesOrigen', 'direccionesDestino'));
}

public function guardarNuevoEnvioB2c(Request $request)
{
    $data = $request->validate([
        'remitente_nombre' => ['required', 'string', 'max:255'],
        'remitente_empresa' => ['nullable', 'string', 'max:255'],
        'remitente_email' => ['nullable', 'email', 'max:255'],
        'remitente_telefono' => ['required', 'string', 'max:30'],
        'remitente_direccion' => ['required', 'string', 'max:255'],
        'remitente_num_ext' => ['required', 'string', 'max:50'],
        'remitente_num_int' => ['nullable', 'string', 'max:50'],
        'remitente_referencias' => ['nullable', 'string', 'max:255'],

        'cp_origen' => ['required', 'string', 'max:120'],
        'colonia_origen' => ['required', 'string', 'max:255'],
        'ciudad_origen' => ['required', 'string', 'max:100'],
        'estado_origen' => ['required', 'string', 'max:100'],

        'destinatario_nombre' => ['required', 'string', 'max:255'],
        'destinatario_empresa' => ['nullable', 'string', 'max:255'],
        'destinatario_email' => ['nullable', 'email', 'max:255'],
        'destinatario_telefono' => ['required', 'string', 'max:30'],
        'destinatario_direccion' => ['required', 'string', 'max:255'],
        'destinatario_num_ext' => ['required', 'string', 'max:50'],
        'destinatario_num_int' => ['nullable', 'string', 'max:50'],
        'destinatario_referencias' => ['nullable', 'string', 'max:255'],

        'cp_destino' => ['required', 'string', 'max:120'],
        'colonia_destino' => ['required', 'string', 'max:255'],
        'ciudad_destino' => ['required', 'string', 'max:100'],
        'estado_destino' => ['required', 'string', 'max:100'],

        'guardar_origen' => ['nullable'],
        'guardar_destino' => ['nullable'],
        'alias_origen' => ['nullable', 'string', 'max:100'],
        'alias_destino' => ['nullable', 'string', 'max:100'],
    ]);

    $cotizacion = B2cCotizacion::create([
        'user_id' => auth()->id(),

        'cp_origen' => $data['cp_origen'],
        'colonia_origen' => $data['colonia_origen'],
        'ciudad_origen' => $data['ciudad_origen'],
        'estado_origen' => $data['estado_origen'],

        'cp_destino' => $data['cp_destino'],
        'colonia_destino' => $data['colonia_destino'],
        'ciudad_destino' => $data['ciudad_destino'],
        'estado_destino' => $data['estado_destino'],

        'remitente_nombre' => $data['remitente_nombre'],
        'remitente_email' => $data['remitente_email'] ?? null,
        'remitente_telefono' => $data['remitente_telefono'],
        'remitente_direccion' => $data['remitente_direccion'],
        'remitente_num_ext' => $data['remitente_num_ext'],
        'remitente_num_int' => $data['remitente_num_int'] ?? null,

        'destinatario_nombre' => $data['destinatario_nombre'],
        'destinatario_email' => $data['destinatario_email'] ?? null,
        'destinatario_telefono' => $data['destinatario_telefono'],
        'destinatario_direccion' => $data['destinatario_direccion'],
        'destinatario_num_ext' => $data['destinatario_num_ext'],
        'destinatario_num_int' => $data['destinatario_num_int'] ?? null,

        'tipo_envio' => 'caja',
        'peso' => 1,
        'medidas' => '20x20x20',
        'estatus' => 'DIRECCION_CAPTURADA',
    ]);

    if ($request->has('guardar_origen')) {
    B2cDireccion::create([
        'user_id' => auth()->id(),
        'tipo' => 'ORIGEN',
        'alias' => $data['alias_origen'] ?? 'Origen',
        'nombre' => $data['remitente_nombre'],
        'empresa' => $data['remitente_empresa'] ?? null,
        'email' => $data['remitente_email'] ?? null,
        'telefono' => $data['remitente_telefono'],
        'calle' => $data['remitente_direccion'],
        'num_ext' => $data['remitente_num_ext'],
        'num_int' => $data['remitente_num_int'] ?? null,
        'referencias' => $data['remitente_referencias'] ?? null,
        'cp' => $data['cp_origen'],
        'colonia' => $data['colonia_origen'],
        'ciudad' => $data['ciudad_origen'],
        'estado' => $data['estado_origen'],
        'activo' => true,
        'favorita' => false,
    ]);
}

if ($request->has('guardar_destino')) {
    B2cDireccion::create([
        'user_id' => auth()->id(),
        'tipo' => 'DESTINO',
        'alias' => $data['alias_destino'] ?? 'Destino',
        'nombre' => $data['destinatario_nombre'],
        'empresa' => $data['destinatario_empresa'] ?? null,
        'email' => $data['destinatario_email'] ?? null,
        'telefono' => $data['destinatario_telefono'],
        'calle' => $data['destinatario_direccion'],
        'num_ext' => $data['destinatario_num_ext'],
        'num_int' => $data['destinatario_num_int'] ?? null,
        'referencias' => $data['destinatario_referencias'] ?? null,
        'cp' => $data['cp_destino'],
        'colonia' => $data['colonia_destino'],
        'ciudad' => $data['ciudad_destino'],
        'estado' => $data['estado_destino'],
        'activo' => true,
        'favorita' => false,
    ]);
}

    return redirect()->route('b2c.paquete', $cotizacion->id);
}

public function paqueteB2c(B2cCotizacion $cotizacion)
{
    if ($cotizacion->user_id !== auth()->id()) {
        abort(403);
    }

    return view('b2c.paquete', compact('cotizacion'));
}

public function misDireccionesB2c()
{
    $direcciones = B2cDireccion::where('user_id', auth()->id())
        ->where('activo', true)
        ->orderByDesc('favorita')
        ->latest()
        ->get();

    return view('b2c.mis-direcciones', compact('direcciones'));
}

public function guardarDireccionB2c(Request $request)
{
    $data = $request->validate([
        'tipo' => ['required', 'in:ORIGEN,DESTINO'],
        'alias' => ['nullable', 'string', 'max:100'],
        'nombre' => ['required', 'string', 'max:255'],
        'empresa' => ['nullable', 'string', 'max:255'],
        'email' => ['nullable', 'email', 'max:255'],
        'telefono' => ['required', 'string', 'max:30'],
        'calle' => ['required', 'string', 'max:255'],
        'num_ext' => ['required', 'string', 'max:50'],
        'num_int' => ['nullable', 'string', 'max:50'],
        'referencias' => ['nullable', 'string', 'max:255'],
        'cp' => ['required', 'string', 'max:10'],
        'colonia' => ['required', 'string', 'max:255'],
        'ciudad' => ['required', 'string', 'max:100'],
        'estado' => ['required', 'string', 'max:100'],
    ]);

    B2cDireccion::create([
        ...$data,
        'user_id' => auth()->id(),
        'favorita' => $request->has('favorita'),
        'activo' => true,
    ]);

    return redirect()
        ->route('b2c.mis-direcciones')
        ->with('success', 'Dirección guardada correctamente.');
}

public function eliminarDireccionB2c(B2cDireccion $direccion)
{
    if ($direccion->user_id !== auth()->id()) {
        abort(403);
    }

    $direccion->update([
        'activo' => false,
    ]);

    return redirect()
        ->route('b2c.mis-direcciones')
        ->with('success', 'Dirección eliminada correctamente.');
}

public function pagarConSaldo(B2cCotizacion $cotizacion)
{
    if ($cotizacion->user_id !== auth()->id()) {
        abort(403);
    }

    $total = (float) $cotizacion->precio;

    $saldo = B2cSaldo::firstOrCreate(
        ['user_id' => auth()->id()],
        ['saldo' => 0]
    );

    if ($saldo->saldo < $total) {
        return back()->with('error', 'Saldo insuficiente. Recarga saldo o paga con Mercado Pago.');
    }

    DB::transaction(function () use ($saldo, $cotizacion, $total) {
        $saldoAnterior = $saldo->saldo;
        $saldoNuevo = $saldoAnterior - $total;

        $saldo->update([
            'saldo' => $saldoNuevo,
        ]);

        B2cMovimientoSaldo::create([
            'user_id' => auth()->id(),
            'tipo' => 'COMPRA_GUIA',
            'monto' => $total,
            'saldo_anterior' => $saldoAnterior,
            'saldo_nuevo' => $saldoNuevo,
            'referencia' => 'COTIZACION-' . $cotizacion->id,
            'estatus' => 'APLICADO',
        ]);

        $cotizacion->update([
            'estatus' => 'PAGADA',
            'payment_status' => 'saldo_prepago',
            'payment_external_reference' => 'SALDO-' . $cotizacion->id,
        ]);
    });

    return redirect()
        ->route('b2c.pago.success', $cotizacion->id)
        ->with('success', 'Guía pagada con saldo prepago.');
}

public function configuracionB2c()
{
    $identity = B2cIdentityVerification::firstOrCreate(
        ['user_id' => auth()->id()],
        ['status' => 'SIN_VERIFICAR']
    );

    return view('b2c.configuracion', compact('identity'));
}

public function guardarIdentidadB2c(Request $request)
{
    $data = $request->validate([
        'ine_front' => ['required', 'image', 'max:5120'],
        'ine_back' => ['required', 'image', 'max:5120'],
        'selfie_with_ine' => ['required', 'image', 'max:5120'],
    ]);

    $identity = B2cIdentityVerification::firstOrCreate(
        ['user_id' => auth()->id()],
        ['status' => 'SIN_VERIFICAR']
    );

    $identity->update([
        'ine_front' => $request->file('ine_front')->store('b2c/identity', 'public'),
        'ine_back' => $request->file('ine_back')->store('b2c/identity', 'public'),
        'selfie_with_ine' => $request->file('selfie_with_ine')->store('b2c/identity', 'public'),
        'status' => 'EN_REVISION',
        'comments' => null,
    ]);

    return redirect()
        ->route('b2c.configuracion')
        ->with('success', 'Documentos enviados correctamente. Tu identidad quedó en revisión.');
}

public function incidenciasB2c()
{
    $incidencias = B2cIncidencia::where('user_id', auth()->id())
        ->latest()
        ->get();

    return view('b2c.incidencias', compact('incidencias'));
}

public function guardarIncidenciaB2c(Request $request)
{
    $data = $request->validate([
        'cotizacion_id' => ['nullable', 'exists:b2c_cotizaciones,id'],
        'tracking_number' => ['nullable', 'string', 'max:100'],
        'tipo' => ['required', 'string', 'max:100'],
        'asunto' => ['required', 'string', 'max:150'],
        'descripcion' => ['required', 'string', 'max:2000'],
        'evidencia' => ['nullable', 'file', 'max:5120'],
    ]);

    $folio = 'INC-' . str_pad((B2cIncidencia::max('id') ?? 0) + 1, 5, '0', STR_PAD_LEFT);

    $evidencia = null;

    if ($request->hasFile('evidencia')) {
        $evidencia = $request->file('evidencia')->store('b2c/incidencias', 'public');
    }

    B2cIncidencia::create([
        'folio' => $folio,
        'user_id' => auth()->id(),
        'cotizacion_id' => $data['cotizacion_id'] ?? null,
        'tracking_number' => $data['tracking_number'] ?? null,
        'tipo' => $data['tipo'],
        'asunto' => $data['asunto'],
        'descripcion' => $data['descripcion'],
        'evidencia' => $evidencia,
        'estatus' => 'ABIERTA',
        'prioridad' => 'MEDIA',
    ]);

    return redirect()
        ->route('b2c.incidencias')
        ->with('success', 'Incidencia registrada correctamente.');
}

public function guardarPaqueteB2c(\Illuminate\Http\Request $request, \App\Models\B2cCotizacion $cotizacion)
{
    if ($cotizacion->user_id !== auth()->id()) {
        abort(403);
    }

    $data = $request->validate([
        'tipo_envio' => ['required', 'string', 'max:30'],
        'peso' => ['required', 'numeric', 'min:0.1'],
        'largo' => ['required', 'numeric', 'min:1'],
        'ancho' => ['required', 'numeric', 'min:1'],
        'alto' => ['required', 'numeric', 'min:1'],
        'contenido' => ['required', 'string', 'max:255'],
        'valor_declarado' => ['nullable', 'numeric', 'min:0'],
        'acepta_no_prohibidos' => ['accepted'],
    ]);

    $cotizacion->update([
        'tipo_envio' => $data['tipo_envio'],
        'peso' => $data['peso'],
        'medidas' => $data['largo'].'x'.$data['ancho'].'x'.$data['alto'],
        'contenido' => $data['contenido'],
        'valor_declarado' => $data['valor_declarado'] ?? 0,
    ]);

    return redirect()->route('b2c.opciones', $cotizacion->id);
}

public function opcionesB2c(B2cCotizacion $cotizacion)
{
    if ($cotizacion->user_id !== auth()->id()) {
        abort(403);
    }

    $opciones = $this->getAvailableOptions($cotizacion);

    $saldo = B2cSaldo::firstOrCreate(
        ['user_id' => auth()->id()],
        ['saldo' => 0]
    );

    return view('b2c.opciones', compact('cotizacion', 'opciones', 'saldo'));

}

    public function duplicarEnvioB2c(B2cCotizacion $cotizacion)
    {
        if ($cotizacion->user_id !== auth()->id()) {
            abort(403);
        }

        $nueva = $cotizacion->replicate();

        $nueva->estatus = 'DIRECCION_CAPTURADA';
        $nueva->guia_estatus = 'SIN_GUIA';
        $nueva->tracking_number = null;
        $nueva->documento = null;
        $nueva->guia_id = null;
        $nueva->payment_id = null;
        $nueva->payment_status = null;
        $nueva->payment_external_reference = null;
        $nueva->payment_collection_id = null;
        $nueva->logistico = null;
        $nueva->servicio = null;
        $nueva->precio = 0;

        $nueva->save();

        return redirect()
            ->route('b2c.paquete', $nueva->id)
            ->with('success', 'Envío duplicado correctamente. Revisa el paquete antes de cotizar.');
    }

    public function eliminarCotizacionB2c(B2cCotizacion $cotizacion)
    {
        if ($cotizacion->user_id !== auth()->id()) {
            abort(403);
        }

        if ($cotizacion->tracking_number || $cotizacion->guia_estatus === 'GENERADA') {
            return back()->with('error', 'No puedes eliminar una guía ya generada.');
        }

        $cotizacion->delete();

        return redirect()
            ->route('b2c.mis-envios')
            ->with('success', 'Cotización eliminada correctamente.');
    }

    private function customerSegmentForCotizacion(B2cCotizacion $cotizacion): string
    {
        if ($cotizacion->referencia === 'LANDING_PUBLICA' && empty($cotizacion->user_id)) {
            return 'anonymous';
        }

        if (!auth()->check()) {
            return 'anonymous';
        }

        return 'b2c';
    }

    private function packageTypeForCotizacion(B2cCotizacion $cotizacion): string
    {
        $tipo = strtolower($cotizacion->tipo_envio ?? 'sobre');

        if (str_contains($tipo, 'caja') || str_contains($tipo, 'paquete')) {
            return 'caja';
        }

        return 'sobre';
    }

    private function buildPricedOption(B2cCotizacion $cotizacion, array $option): array
    {
        $pricing = app(ZigoPricingService::class)->calculate([
            'carrier' => strtoupper($option['logistico']),
            'customer_segment' => $this->customerSegmentForCotizacion($cotizacion),
            'package_type' => $this->packageTypeForCotizacion($cotizacion),
            'base_price' => $option['base_price'],
            'user_id' => auth()->check() ? auth()->id() : null,
        ]);

        return array_merge($option, [
            'precio_base' => $pricing['base_price'],
            'precio' => $pricing['final_price'],
            'pricing' => $pricing,
        ]);
    }

    private function getAvailableOptions(B2cCotizacion $cotizacion): array
    {
        $baseOptions = app(ZigoProviderRateService::class)
            ->getOptionsForCotizacion($cotizacion);

        return array_map(
            fn ($option) => $this->buildPricedOption($cotizacion, $option),
            $baseOptions
        );
    }

    private function findSelectedOption(B2cCotizacion $cotizacion, string $logistico, string $servicio): ?array
    {
        foreach ($this->getAvailableOptions($cotizacion) as $option) {
            if (
                strtolower($option['logistico']) === strtolower($logistico)
                && strtolower($option['servicio']) === strtolower($servicio)
            ) {
                return $option;
            }
        }

        return null;
    }

    private function applyPricingToCotizacion(B2cCotizacion $cotizacion, array $option): void
    {
        $pricing = $option['pricing'];

        $cotizacion->update([
            'logistico' => $option['logistico'],
            'servicio' => $option['servicio'],

            // Campo existente: debe guardar el precio final que paga el cliente.
            'precio' => $pricing['final_price'],

            // Auditoría pricing ZIGO.
            'provider_base_price' => $pricing['base_price'],
            'zigo_margin_percentage' => $pricing['margin_percentage'],
            'zigo_fixed_fee' => $pricing['fixed_fee'],
            'zigo_margin_amount' => $pricing['margin_amount'],

            'zigo_adjustment_type' => $pricing['adjustment_type'],
            'zigo_adjustment_value' => $pricing['adjustment_value'],
            'zigo_adjustment_amount' => $pricing['adjustment_amount'],

            'zigo_discount_type' => $pricing['discount_type'],
            'zigo_discount_value' => $pricing['discount_value'],
            'zigo_discount_amount' => $pricing['discount_amount'],

            'zigo_final_price' => $pricing['final_price'],
            'zigo_profit_amount' => $pricing['profit_amount'],
            'zigo_customer_segment' => $pricing['customer_segment'],

            'zigo_pricing_rule_id' => $pricing['pricing_rule_id'],
            'zigo_pricing_adjustment_id' => $pricing['adjustment_id'],
            'zigo_client_pricing_rule_id' => $pricing['client_pricing_rule_id'],

            'estatus' => 'SELECCIONADA',
        ]);
    }

    public function index()
    {
        $cotizacion = null;
        $opciones = [];

        if (session()->has('cotizacion_publica_id')) {
            $cotizacion = B2cCotizacion::find(session('cotizacion_publica_id'));

            if ($cotizacion) {
                $opciones = $this->getAvailableOptions($cotizacion);
            }
        }

        return view('index', [
            'cotizacion_publica' => $cotizacion,
            'cotizacion_id' => $cotizacion?->id,
            'opciones' => $opciones,
        ]);
    }

    public function limpiarCotizacion()
    {
        session()->forget('cotizacion_publica_id');

        return redirect()->to(url('/') . '#cotizar');
    }

    public function getPublicOptionsForLanding(B2cCotizacion $cotizacion): array
    {
        return $this->getAvailableOptions($cotizacion);
    }

    private function resolverUbicacionPostal(?string $cp, ?string $colonia = null): array
    {
        $cp = substr((string) $cp, 0, 5);

        if (!$cp) {
            return [
                'ciudad' => null,
                'estado' => null,
                'colonia' => $colonia,
            ];
        }

        $query = DB::table('sepomex')
            ->where('d_codigo', $cp);

        if ($colonia) {
            $query->where(function ($q) use ($colonia) {
                $q->where('d_asenta', $colonia)
                ->orWhere('d_asenta', 'like', '%' . $colonia . '%');
            });
        }

        $row = $query->first();

        if (!$row) {
            $row = DB::table('sepomex')
                ->where('d_codigo', $cp)
                ->first();
        }

        return [
            'ciudad' => $row->D_mnpio ?? $row->d_mnpio ?? $row->d_ciudad ?? null,
            'estado' => $row->d_estado ?? null,
            'colonia' => $row->d_asenta ?? $colonia,
        ];
    }
}