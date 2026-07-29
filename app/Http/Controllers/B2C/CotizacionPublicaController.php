<?php

namespace App\Http\Controllers\B2C;

use App\Http\Controllers\Controller;
use App\Models\B2cSaldo;
use App\Models\B2cMovimientoSaldo;
use App\Models\B2cCotizacion;
use App\Models\User;
use App\Models\Roles\Roles;
use App\Models\B2cFiscalProfile;
use App\Models\B2cDireccion;
use App\Models\B2cIdentityVerification;
use App\Models\B2cIncidencia;
use App\Models\B2cInvoiceRequest;
use App\Services\ZigoPricingService;
use App\Services\ZigoProviderRateService;
use App\Exceptions\Identity\IdentityVerificationRequiredException;
use App\Exceptions\Payments\PaymentVerificationException;
use App\Services\Identity\IdentityGuideAccessService;
use App\Services\Payments\PaymentVerificationService;
use App\Services\Shipping\EstafetaGuideService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
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
            $pesoFinal = ceil(max($pesoReal, $pesoVolumetrico));
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

    public function cotizadorRapidoB2c(Request $request)
    {
        $data = $request->validate([
            'cp_origen' => ['required', 'string', 'max:120'],
            'colonia_origen' => ['nullable', 'string', 'max:255'],
            'ciudad_origen' => ['nullable', 'string', 'max:100'],
            'estado_origen' => ['nullable', 'string', 'max:100'],

            'cp_destino' => ['required', 'string', 'max:120'],
            'colonia_destino' => ['nullable', 'string', 'max:255'],
            'ciudad_destino' => ['nullable', 'string', 'max:100'],
            'estado_destino' => ['nullable', 'string', 'max:100'],

            'tipo_envio' => ['required', 'in:caja,sobre'],
            'peso' => ['nullable', 'numeric', 'min:0.1'],
            'medidas' => ['nullable', 'string', 'max:50'],
        ]);

        $tipoEnvio = strtolower($data['tipo_envio']);

        $cpOrigen = substr(
            preg_replace('/\D/', '', $data['cp_origen']),
            0,
            5
        );

        $cpDestino = substr(
            preg_replace('/\D/', '', $data['cp_destino']),
            0,
            5
        );

        if (strlen($cpOrigen) !== 5) {
            return back()
                ->withErrors([
                    'cp_origen' => 'Selecciona un código postal de origen válido.',
                ])
                ->withInput();
        }

        if (strlen($cpDestino) !== 5) {
            return back()
                ->withErrors([
                    'cp_destino' => 'Selecciona un código postal de destino válido.',
                ])
                ->withInput();
        }

        if ($tipoEnvio === 'sobre') {
            $pesoFinal = 1;
            $medidasFinal = null;
        } else {
            if (empty($data['peso'])) {
                return back()
                    ->withErrors([
                        'peso' => 'El peso es obligatorio para envíos en caja.',
                    ])
                    ->withInput();
            }

            if (empty($data['medidas'])) {
                return back()
                    ->withErrors([
                        'medidas' => 'Las medidas son obligatorias para envíos en caja.',
                    ])
                    ->withInput();
            }

            $medidasCapturadas = strtolower(
                preg_replace('/\s+/', '', $data['medidas'])
            );

            $patronMedidas = '/^(\d+(?:\.\d+)?)x(\d+(?:\.\d+)?)x(\d+(?:\.\d+)?)$/';

            if (!preg_match($patronMedidas, $medidasCapturadas, $coincidencias)) {
                return back()
                    ->withErrors([
                        'medidas' => 'Captura las medidas con el formato largo x ancho x alto. Ejemplo: 20x20x20.',
                    ])
                    ->withInput();
            }

            $largo = (float) $coincidencias[1];
            $ancho = (float) $coincidencias[2];
            $alto = (float) $coincidencias[3];

            if ($largo <= 0 || $ancho <= 0 || $alto <= 0) {
                return back()
                    ->withErrors([
                        'medidas' => 'Las dimensiones deben ser mayores a cero.',
                    ])
                    ->withInput();
            }

            $pesoReal = (float) $data['peso'];

            $pesoVolumetrico = ($largo * $ancho * $alto) / 5000;

            $pesoFinal = (int) ceil(
                max($pesoReal, $pesoVolumetrico)
            );

            $medidasFinal = $medidasCapturadas;
        }

        $ubicacionOrigen = $this->resolverUbicacionPostal(
            $cpOrigen,
            $data['colonia_origen'] ?? null
        );

        $ubicacionDestino = $this->resolverUbicacionPostal(
            $cpDestino,
            $data['colonia_destino'] ?? null
        );

        $cotizacion = B2cCotizacion::create([
            'user_id' => auth()->id(),

            'cp_origen' => $cpOrigen,
            'colonia_origen' => $data['colonia_origen']
                ?? $ubicacionOrigen['colonia'],
            'ciudad_origen' => $data['ciudad_origen']
                ?? $ubicacionOrigen['ciudad'],
            'estado_origen' => $data['estado_origen']
                ?? $ubicacionOrigen['estado'],

            'cp_destino' => $cpDestino,
            'colonia_destino' => $data['colonia_destino']
                ?? $ubicacionDestino['colonia'],
            'ciudad_destino' => $data['ciudad_destino']
                ?? $ubicacionDestino['ciudad'],
            'estado_destino' => $data['estado_destino']
                ?? $ubicacionDestino['estado'],

            'tipo_envio' => $tipoEnvio,
            'peso' => $pesoFinal,
            'medidas' => $medidasFinal,

            'estatus' => 'COTIZADA',
            'referencia' => 'B2C_LOGUEADO',
        ]);

        session([
            'b2c_cotizacion_actual_id' => $cotizacion->id,
        ]);

        return redirect()->route(
            'b2c.opciones',
            $cotizacion->id
        );
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

    public function seleccionarNuevoEnvio(
        Request $request,
        B2cCotizacion $cotizacion
    ) {
        $data = $request->validate([
            'logistico' => [
                'required',
                'string',
                'max:100',
            ],
            'servicio' => [
                'required',
                'string',
                'max:100',
            ],
        ]);

        if ($cotizacion->user_id !== auth()->id()) {
            abort(403);
        }

        // Estafeta permite hasta 70.999 kg facturables.
        $pesoFacturable = (float) (
            $cotizacion->peso_facturable
            ?: $cotizacion->peso
        );

        if (
            strcasecmp(
                trim($data['logistico']),
                'Estafeta'
            ) === 0
            &&
            $pesoFacturable > 67.999
        ) {
            return back()->with(
                'error',
                'El peso facturable de '
                . number_format($pesoFacturable, 2)
                . ' kg supera el máximo permitido por Estafeta '
                . '(70.99 kg). Reduce el peso o las dimensiones '
                . 'del paquete.'
            );
        }

        $option = $this->findSelectedOption(
            $cotizacion,
            $data['logistico'],
            $data['servicio']
        );

        if (!$option) {
            return back()->with(
                'error',
                'La opción seleccionada no es válida.'
            );
        }

        $this->applyPricingToCotizacion(
            $cotizacion,
            $option
        );

        $cotizacion->refresh();

        if (
            $cotizacion->referencia ===
            'B2C_NUEVO_ENVIO'
        ) {
            return redirect()->route(
                'b2c.confirmar',
                $cotizacion->id
            );
        }

        return redirect()->route(
            'b2c.checkout',
            $cotizacion->id
        );
    }

public function checkout(B2cCotizacion $cotizacion)
{
    $ubicacionOrigen = $this->resolverUbicacionPostal(
        $cotizacion->cp_origen,
        $cotizacion->colonia_origen
    );

    $ubicacionDestino = $this->resolverUbicacionPostal(
        $cotizacion->cp_destino,
        $cotizacion->colonia_destino
    );

    $updates = [];

    if (empty($cotizacion->ciudad_origen) && !empty($ubicacionOrigen['ciudad'])) {
        $updates['ciudad_origen'] = $ubicacionOrigen['ciudad'];
    }

    if (empty($cotizacion->estado_origen) && !empty($ubicacionOrigen['estado'])) {
        $updates['estado_origen'] = $ubicacionOrigen['estado'];
    }

    if (empty($cotizacion->ciudad_destino) && !empty($ubicacionDestino['ciudad'])) {
        $updates['ciudad_destino'] = $ubicacionDestino['ciudad'];
    }

    if (empty($cotizacion->estado_destino) && !empty($ubicacionDestino['estado'])) {
        $updates['estado_destino'] = $ubicacionDestino['estado'];
    }

    if (!empty($updates)) {
        $cotizacion->update($updates);
        $cotizacion->refresh();
    }

    $isPublicCheckout =
        $cotizacion->referencia === 'LANDING_PUBLICA'
        && empty($cotizacion->user_id);

    if (
        !$isPublicCheckout
        && $cotizacion->user_id
        && (
            !auth()->check()
            || $cotizacion->user_id !== auth()->id()
        )
    ) {
        abort(403);
    }

    $saldo = null;

    $direccionesOrigen = collect();
    $direccionesDestino = collect();

    if (
        auth()->check()
        && !$isPublicCheckout
        && $cotizacion->user_id === auth()->id()
    ) {
        $saldo = B2cSaldo::firstOrCreate(
            [
                'user_id' => auth()->id(),
            ],
            [
                'saldo' => 0,
            ]
        );

        /*
        * Solo mostrar direcciones del mismo CP.
        * Cambiar el CP después de cotizar alteraría
        * cobertura, servicio y precio.
        */
        $cpOrigen = substr(
            preg_replace(
                '/\D/',
                '',
                (string) $cotizacion->cp_origen
            ),
            0,
            5
        );

        $cpDestino = substr(
            preg_replace(
                '/\D/',
                '',
                (string) $cotizacion->cp_destino
            ),
            0,
            5
        );

        $direccionesOrigen = B2cDireccion::where(
            'user_id',
            auth()->id()
        )
            ->where('activo', true)
            ->where('tipo', 'ORIGEN')
            ->where('cp', $cpOrigen)
            ->orderByDesc('favorita')
            ->orderByDesc('principal')
            ->latest()
            ->get();

        $direccionesDestino = B2cDireccion::where(
            'user_id',
            auth()->id()
        )
            ->where('activo', true)
            ->where('tipo', 'DESTINO')
            ->where('cp', $cpDestino)
            ->orderByDesc('favorita')
            ->orderByDesc('principal')
            ->latest()
            ->get();
    }

    return view(
        'b2c.checkout',
        compact(
            'cotizacion',
            'saldo',
            'isPublicCheckout',
            'direccionesOrigen',
            'direccionesDestino'
        )
    );
}

public function confirmarEnvio(
    B2cCotizacion $cotizacion,
    IdentityGuideAccessService $identityGuideAccessService
) {
    if ($cotizacion->user_id !== auth()->id()) {
        abort(403);
    }

    if (
        !$cotizacion->logistico ||
        !$cotizacion->servicio ||
        (float) $cotizacion->precio <= 0
    ) {
        return redirect()
            ->route('b2c.opciones', $cotizacion->id)
            ->with(
                'error',
                'Primero selecciona una paquetería válida.'
            );
    }

    $tieneDirecciones =
        !empty($cotizacion->remitente_nombre) &&
        !empty($cotizacion->remitente_telefono) &&
        !empty($cotizacion->remitente_direccion) &&
        !empty($cotizacion->remitente_num_ext) &&
        !empty($cotizacion->destinatario_nombre) &&
        !empty($cotizacion->destinatario_telefono) &&
        !empty($cotizacion->destinatario_direccion) &&
        !empty($cotizacion->destinatario_num_ext);

    if (!$tieneDirecciones) {
        return redirect()
            ->route('b2c.checkout', $cotizacion->id)
            ->with(
                'error',
                'Completa los datos del remitente y destinatario.'
            );
    }

    if (
        empty($cotizacion->contenido) ||
        empty($cotizacion->tipo_envio) ||
        (float) $cotizacion->peso <= 0
    ) {
        return redirect()
            ->route('b2c.paquete', $cotizacion->id)
            ->with(
                'error',
                'Completa la información del paquete.'
            );
    }

    $saldo = B2cSaldo::firstOrCreate(
        ['user_id' => auth()->id()],
        ['saldo' => 0]
    );

    $identityAccess =
        $identityGuideAccessService->evaluate(
            (int) auth()->id(),
            (int) $cotizacion->id
        );

    return view(
        'b2c.confirmar-envio',
        compact(
            'cotizacion',
            'saldo',
            'identityAccess'
        )
    );
}

public function procesarConfirmacion(
    Request $request,
    B2cCotizacion $cotizacion,
    IdentityGuideAccessService $identityGuideAccessService,
    PaymentVerificationService $verificationService
) {
    if ($cotizacion->user_id !== auth()->id()) {
        abort(403);
    }

    try {
        $identityGuideAccessService->assertCanProceed(
            (int) auth()->id(),
            (int) $cotizacion->id
        );
    } catch (
        IdentityVerificationRequiredException $exception
    ) {
        return redirect()
            ->to(
                route('b2c.configuracion')
                . '#identidad'
            )
            ->with(
                'identity_error',
                $exception->getMessage()
            );
    }

    $data = $request->validate([
        'metodo_pago' => [
            'required',
            'in:mercado_pago,saldo',
        ],
    ]);

    if (
        !$cotizacion->logistico ||
        !$cotizacion->servicio ||
        (float) $cotizacion->precio <= 0
    ) {
        return redirect()
            ->route('b2c.opciones', $cotizacion->id)
            ->with(
                'error',
                'La cotización no tiene una paquetería válida.'
            );
    }

    $tieneDirecciones =
        !empty($cotizacion->remitente_nombre) &&
        !empty($cotizacion->remitente_telefono) &&
        !empty($cotizacion->remitente_direccion) &&
        !empty($cotizacion->remitente_num_ext) &&
        !empty($cotizacion->destinatario_nombre) &&
        !empty($cotizacion->destinatario_telefono) &&
        !empty($cotizacion->destinatario_direccion) &&
        !empty($cotizacion->destinatario_num_ext);

    if (!$tieneDirecciones) {
        return redirect()
            ->route('b2c.checkout', $cotizacion->id)
            ->with(
                'error',
                'Completa los datos del envío antes de pagar.'
            );
    }

    $cotizacion->update([
        'estatus' => 'CHECKOUT_COMPLETO',
    ]);

    if ($data['metodo_pago'] === 'saldo') {
        return $this->pagarConSaldo(
            $cotizacion->fresh(),
            $verificationService,
            $identityGuideAccessService
        );
    }

    return redirect()->route(
        'b2c.pago',
        $cotizacion->id
    );
}

public function procesarCheckout(
    Request $request,
    B2cCotizacion $cotizacion
) {
    $isPublicCheckout =
        $cotizacion->referencia === 'LANDING_PUBLICA'
        && empty($cotizacion->user_id);

    /*
     * Una cotización autenticada solamente puede ser
     * procesada por su propietario.
     */
    if (!$isPublicCheckout) {
        if (
            !auth()->check()
            || $cotizacion->user_id !== auth()->id()
        ) {
            abort(403);
        }
    }

    $data = $request->validate([
        'remitente_nombre' => [
            'required',
            'string',
            'max:255',
        ],

        'remitente_telefono' => [
            'required',
            'string',
            'max:30',
        ],

        'remitente_email' => [
            'required',
            'email',
            'max:255',
        ],

        'remitente_direccion' => [
            'required',
            'string',
            'max:255',
        ],

        'remitente_num_ext' => [
            'required',
            'string',
            'max:50',
        ],

        'remitente_num_int' => [
            'nullable',
            'string',
            'max:50',
        ],

        'ciudad_origen' => [
            'nullable',
            'string',
            'max:100',
        ],

        'estado_origen' => [
            'nullable',
            'string',
            'max:100',
        ],

        'destinatario_nombre' => [
            'required',
            'string',
            'max:255',
        ],

        'destinatario_telefono' => [
            'required',
            'string',
            'max:30',
        ],

        'destinatario_email' => [
            'nullable',
            'email',
            'max:255',
        ],

        'destinatario_direccion' => [
            'required',
            'string',
            'max:255',
        ],

        'destinatario_num_ext' => [
            'required',
            'string',
            'max:50',
        ],

        'destinatario_num_int' => [
            'nullable',
            'string',
            'max:50',
        ],

        'ciudad_destino' => [
            'nullable',
            'string',
            'max:100',
        ],

        'estado_destino' => [
            'nullable',
            'string',
            'max:100',
        ],

        'contenido' => [
            'required',
            'string',
            'max:255',
        ],

        'valor_declarado' => [
            'nullable',
            'numeric',
            'min:0',
        ],

        'requiere_seguro_envio' => [
            'nullable',
            'boolean',
        ],

        'direccion_origen_id' => [
            'nullable',
            'integer',
            'exists:b2c_direcciones,id',
        ],

        'direccion_destino_id' => [
            'nullable',
            'integer',
            'exists:b2c_direcciones,id',
        ],

        'guardar_origen' => [
            'nullable',
            'boolean',
        ],

        'guardar_destino' => [
            'nullable',
            'boolean',
        ],

        'alias_origen' => [
            'nullable',
            'string',
            'max:100',
        ],

        'alias_destino' => [
            'nullable',
            'string',
            'max:100',
        ],
    ]);

    $normalizarCp = static function (
        ?string $valor
    ): string {
        return substr(
            preg_replace(
                '/\D/',
                '',
                (string) $valor
            ),
            0,
            5
        );
    };

    $cpOrigen = $normalizarCp(
        $cotizacion->cp_origen
    );

    $cpDestino = $normalizarCp(
        $cotizacion->cp_destino
    );

    /*
     * Verificar que las direcciones seleccionadas sean
     * propiedad del usuario, estén activas y correspondan
     * al tipo y CP de la cotización.
     */
    $direccionOrigenId =
        $data['direccion_origen_id']
        ?? null;

    $direccionDestinoId =
        $data['direccion_destino_id']
        ?? null;

    if (
        !$isPublicCheckout
        && $direccionOrigenId
    ) {
        $direccionOrigenValida =
            B2cDireccion::where(
                'id',
                $direccionOrigenId
            )
                ->where(
                    'user_id',
                    auth()->id()
                )
                ->where('tipo', 'ORIGEN')
                ->where('activo', true)
                ->where('cp', $cpOrigen)
                ->exists();

        if (!$direccionOrigenValida) {
            return back()
                ->withInput()
                ->with(
                    'error',
                    'La dirección de origen seleccionada '
                    . 'no es válida para este envío.'
                );
        }
    }

    if (
        !$isPublicCheckout
        && $direccionDestinoId
    ) {
        $direccionDestinoValida =
            B2cDireccion::where(
                'id',
                $direccionDestinoId
            )
                ->where(
                    'user_id',
                    auth()->id()
                )
                ->where('tipo', 'DESTINO')
                ->where('activo', true)
                ->where('cp', $cpDestino)
                ->exists();

        if (!$direccionDestinoValida) {
            return back()
                ->withInput()
                ->with(
                    'error',
                    'La dirección de destino seleccionada '
                    . 'no es válida para este envío.'
                );
        }
    }

    $ubicacionOrigen =
        $this->resolverUbicacionPostal(
            $cpOrigen,
            $cotizacion->colonia_origen
        );

    $ubicacionDestino =
        $this->resolverUbicacionPostal(
            $cpDestino,
            $cotizacion->colonia_destino
        );

    $data['ciudad_origen'] =
        $data['ciudad_origen']
        ?: (
            $ubicacionOrigen['ciudad']
            ?? $cotizacion->ciudad_origen
        );

    $data['estado_origen'] =
        $data['estado_origen']
        ?: (
            $ubicacionOrigen['estado']
            ?? $cotizacion->estado_origen
        );

    $data['ciudad_destino'] =
        $data['ciudad_destino']
        ?: (
            $ubicacionDestino['ciudad']
            ?? $cotizacion->ciudad_destino
        );

    $data['estado_destino'] =
        $data['estado_destino']
        ?: (
            $ubicacionDestino['estado']
            ?? $cotizacion->estado_destino
        );

    if (
        !$data['ciudad_origen']
        || !$data['estado_origen']
        || !$data['ciudad_destino']
        || !$data['estado_destino']
    ) {
        return back()
            ->withInput()
            ->with(
                'error',
                'No fue posible completar ciudad y estado '
                . 'con los códigos postales seleccionados.'
            );
    }

    /*
     * Protección del envío.
     */
    $requiereSeguro = (bool) (
        $data['requiere_seguro_envio']
        ?? false
    );

    $valorDeclarado = round(
        (float) (
            $data['valor_declarado']
            ?? 0
        ),
        2
    );

    if (
        $requiereSeguro
        && $valorDeclarado <= 0
    ) {
        return back()
            ->withInput()
            ->with(
                'error',
                'Para proteger tu envío, captura un '
                . 'valor declarado mayor a cero.'
            );
    }

    $precioSinSeguro = round(
        (float) (
            $cotizacion->precio_sin_seguro
            ?: $cotizacion->precio
        ),
        2
    );

    $seguroPorcentaje = 2.00;
    $seguroIvaPorcentaje = 16.00;

    $seguroMonto = 0.00;

    if ($requiereSeguro) {
        $seguroBase = round(
            $valorDeclarado
            * ($seguroPorcentaje / 100),
            2
        );

        $seguroIva = round(
            $seguroBase
            * ($seguroIvaPorcentaje / 100),
            2
        );

        $seguroMonto = round(
            $seguroBase + $seguroIva,
            2
        );
    }

    $precioFinal = round(
        $precioSinSeguro + $seguroMonto,
        2
    );

    /*
     * Datos exclusivos del formulario de direcciones.
     * No pertenecen a b2c_cotizaciones.
     */
    $guardarOrigen = (bool) (
        $data['guardar_origen']
        ?? false
    );

    $guardarDestino = (bool) (
        $data['guardar_destino']
        ?? false
    );

    $aliasOrigen = trim(
        (string) (
            $data['alias_origen']
            ?? ''
        )
    );

    $aliasDestino = trim(
        (string) (
            $data['alias_destino']
            ?? ''
        )
    );

    $aliasOrigen =
        $aliasOrigen !== ''
            ? $aliasOrigen
            : 'Origen';

    $aliasDestino =
        $aliasDestino !== ''
            ? $aliasDestino
            : 'Destino';

    $datosCotizacion = $data;

    unset(
        $datosCotizacion['direccion_origen_id'],
        $datosCotizacion['direccion_destino_id'],
        $datosCotizacion['guardar_origen'],
        $datosCotizacion['guardar_destino'],
        $datosCotizacion['alias_origen'],
        $datosCotizacion['alias_destino']
    );

    $userId = auth()->id();

    DB::transaction(
        function () use (
            $cotizacion,
            $datosCotizacion,
            $valorDeclarado,
            $requiereSeguro,
            $seguroPorcentaje,
            $seguroIvaPorcentaje,
            $seguroMonto,
            $precioSinSeguro,
            $precioFinal,
            $isPublicCheckout,
            $userId,
            $direccionOrigenId,
            $direccionDestinoId,
            $guardarOrigen,
            $guardarDestino,
            $aliasOrigen,
            $aliasDestino,
            $data,
            $cpOrigen,
            $cpDestino
        ) {
            $cotizacion->update([
                ...$datosCotizacion,

                'valor_declarado' =>
                    $valorDeclarado,

                'requiere_seguro_envio' =>
                    $requiereSeguro,

                'seguro_porcentaje' =>
                    $seguroPorcentaje,

                'seguro_iva_porcentaje' =>
                    $seguroIvaPorcentaje,

                'seguro_monto' =>
                    $seguroMonto,

                'precio_sin_seguro' =>
                    $precioSinSeguro,

                'precio' =>
                    $precioFinal,

                'estatus' =>
                    'CHECKOUT_COMPLETO',
            ]);

            /*
             * El checkout público no administra
             * direcciones guardadas.
             */
            if (
                $isPublicCheckout
                || !$userId
            ) {
                return;
            }

            /*
             * Solo crear una dirección cuando:
             * - no se seleccionó una existente;
             * - el usuario marcó Guardar.
             */
            if (
                !$direccionOrigenId
                && $guardarOrigen
            ) {
                B2cDireccion::firstOrCreate(
                    [
                        'user_id' => $userId,
                        'tipo' => 'ORIGEN',
                        'calle' =>
                            $data['remitente_direccion'],
                        'num_ext' =>
                            $data['remitente_num_ext'],
                        'cp' => $cpOrigen,
                    ],
                    [
                        'alias' => $aliasOrigen,
                        'nombre' =>
                            $data['remitente_nombre'],
                        'email' =>
                            $data['remitente_email'],
                        'telefono' =>
                            $data['remitente_telefono'],
                        'num_int' =>
                            $data['remitente_num_int']
                            ?? null,
                        'colonia' =>
                            $cotizacion->colonia_origen,
                        'ciudad' =>
                            $data['ciudad_origen'],
                        'estado' =>
                            $data['estado_origen'],
                        'principal' => false,
                        'activo' => true,
                        'favorita' => false,
                    ]
                );
            }

            if (
                !$direccionDestinoId
                && $guardarDestino
            ) {
                B2cDireccion::firstOrCreate(
                    [
                        'user_id' => $userId,
                        'tipo' => 'DESTINO',
                        'calle' =>
                            $data['destinatario_direccion'],
                        'num_ext' =>
                            $data['destinatario_num_ext'],
                        'cp' => $cpDestino,
                    ],
                    [
                        'alias' => $aliasDestino,
                        'nombre' =>
                            $data['destinatario_nombre'],
                        'email' =>
                            $data['destinatario_email']
                            ?? null,
                        'telefono' =>
                            $data['destinatario_telefono'],
                        'num_int' =>
                            $data['destinatario_num_int']
                            ?? null,
                        'colonia' =>
                            $cotizacion->colonia_destino,
                        'ciudad' =>
                            $data['ciudad_destino'],
                        'estado' =>
                            $data['estado_destino'],
                        'principal' => false,
                        'activo' => true,
                        'favorita' => false,
                    ]
                );
            }
        }
    );

    if ($isPublicCheckout) {
        return redirect()->route(
            'b2c.pago',
            $cotizacion->id
        );
    }

    return redirect()->route(
        'b2c.confirmar',
        $cotizacion->id
    );
}

public function pago(
    B2cCotizacion $cotizacion,
    IdentityGuideAccessService $identityGuideAccessService
) {
    $hasOwner = !empty($cotizacion->user_id);

    if (
        $hasOwner
        && (
            !auth()->check()
            || (int) $cotizacion->user_id
                !== (int) auth()->id()
        )
    ) {
        abort(403);
    }

    if ($cotizacion->hasGeneratedGuide()) {
        return redirect()->route(
            'b2c.pago.success',
            $cotizacion->id
        );
    }

    if ($cotizacion->hasAccreditedPayment()) {
        return redirect()->route(
            'b2c.pago.success',
            $cotizacion->id
        );
    }

    if (
        !$cotizacion->logistico
        || !$cotizacion->servicio
        || (float) $cotizacion->precio <= 0
    ) {
        return redirect()
            ->route('b2c.opciones', $cotizacion->id)
            ->with(
                'error',
                'Primero selecciona una paquetería válida.'
            );
    }

    $accessToken =
        config('services.mercadopago.access_token');

    if (empty($accessToken)) {
        abort(
            500,
            'Falta configurar '
            . 'MERCADOPAGO_ACCESS_TOKEN en .env'
        );
    }

    if ($hasOwner) {
        try {
            DB::transaction(
                function () use (
                    $cotizacion,
                    $identityGuideAccessService
                ) {
                    $identityGuideAccessService
                        ->assertCanProceed(
                            (int) $cotizacion->user_id,
                            (int) $cotizacion->id,
                            true
                        );

                    $lockedCotizacion =
                        B2cCotizacion::query()
                            ->whereKey($cotizacion->id)
                            ->lockForUpdate()
                            ->firstOrFail();

                    if (
                        !$lockedCotizacion->hasGeneratedGuide()
                        && !$lockedCotizacion
                            ->hasAccreditedPayment()
                    ) {
                        $lockedCotizacion->forceFill([
                            'estatus' => 'PAGO_INICIADO',
                        ])->save();
                    }
                }
            );
        } catch (
            IdentityVerificationRequiredException $exception
        ) {
            return redirect()
                ->to(
                    route('b2c.configuracion')
                    . '#identidad'
                )
                ->with(
                    'identity_error',
                    $exception->getMessage()
                );
        }
    }

    SDK::setAccessToken($accessToken);

    $item = new Item();
    $item->title =
        'Guía de envío '
        . $cotizacion->logistico
        . ' - '
        . $cotizacion->servicio;
    $item->quantity = 1;
    $item->unit_price =
        (float) $cotizacion->precio;
    $item->currency_id = 'MXN';

    $preference = new Preference();
    $preference->items = [$item];
    $preference->external_reference =
        'B2C-' . $cotizacion->id;

    $baseUrl = rtrim(config('app.url'), '/');

    $preference->back_urls = [
        'success' =>
            $baseUrl
            . '/b2c/pago/'
            . $cotizacion->id
            . '/success',
        'failure' =>
            $baseUrl
            . '/b2c/pago/'
            . $cotizacion->id
            . '/failure',
        'pending' =>
            $baseUrl
            . '/b2c/pago/'
            . $cotizacion->id
            . '/pending',
    ];

    $preference->auto_return = 'approved';
    $preference->notification_url =
        $baseUrl . '/api/webhooks/mercadopago';

    try {
        $preference->save();
    } catch (\Throwable $exception) {
        if ($hasOwner) {
            B2cCotizacion::query()
                ->whereKey($cotizacion->id)
                ->where('estatus', 'PAGO_INICIADO')
                ->update([
                    'estatus' => 'CHECKOUT_COMPLETO',
                ]);
        }

        \Log::error(
            'Mercado Pago no pudo crear preferencia',
            [
                'cotizacion_id' => $cotizacion->id,
                'error' => $exception->getMessage(),
            ]
        );

        return redirect()
            ->route(
                'b2c.confirmar',
                $cotizacion->id
            )
            ->with(
                'error',
                'No fue posible iniciar el pago. '
                . 'Intenta nuevamente.'
            );
    }

    if (
        !$preference->id
        || !$preference->init_point
    ) {
        if ($hasOwner) {
            B2cCotizacion::query()
                ->whereKey($cotizacion->id)
                ->where('estatus', 'PAGO_INICIADO')
                ->update([
                    'estatus' => 'CHECKOUT_COMPLETO',
                ]);
        }

        \Log::error(
            'Mercado Pago no creó preferencia',
            [
                'cotizacion_id' => $cotizacion->id,
                'precio' => $cotizacion->precio,
                'error' => $preference->error ?? null,
            ]
        );

        return redirect()
            ->route(
                'b2c.confirmar',
                $cotizacion->id
            )
            ->with(
                'error',
                'Mercado Pago no pudo generar '
                . 'la preferencia de pago.'
            );
    }

    if (!$hasOwner) {
        $cotizacion->update([
            'estatus' => 'PAGO_INICIADO',
        ]);
    }

    return redirect($preference->init_point);
}

public function pagoSuccess(
    Request $request,
    B2cCotizacion $cotizacion,
    PaymentVerificationService $verificationService
) {
    $cotizacion = $this->verifyMercadoPagoCallback(
        $request,
        $cotizacion,
        $verificationService,
        'RETURN_SUCCESS'
    );

    session()->forget([
        'cotizacion_publica_id',
        'login_required',
    ]);

    if (
        in_array(
            $cotizacion->estatus,
            ['PAGADA', 'GUIA_GENERADA'],
            true
        )
    ) {
        return view('b2c.pago-success', compact('cotizacion'));
    }

    if ($cotizacion->estatus === 'PAGO_RECHAZADO') {
        return view('b2c.pago-failure', compact('cotizacion'));
    }

    return view('b2c.pago-pending', compact('cotizacion'));
}

public function pagoFailure(
    Request $request,
    B2cCotizacion $cotizacion,
    PaymentVerificationService $verificationService
) {
    $cotizacion = $this->verifyMercadoPagoCallback(
        $request,
        $cotizacion,
        $verificationService,
        'RETURN_FAILURE'
    );

    if (
        in_array(
            $cotizacion->estatus,
            ['PAGADA', 'GUIA_GENERADA'],
            true
        )
    ) {
        return view('b2c.pago-success', compact('cotizacion'));
    }

    if ($cotizacion->estatus === 'PAGO_PENDIENTE') {
        return view('b2c.pago-pending', compact('cotizacion'));
    }

    return view('b2c.pago-failure', compact('cotizacion'));
}

public function pagoPending(
    Request $request,
    B2cCotizacion $cotizacion,
    PaymentVerificationService $verificationService
) {
    $cotizacion = $this->verifyMercadoPagoCallback(
        $request,
        $cotizacion,
        $verificationService,
        'RETURN_PENDING'
    );

    if (
        in_array(
            $cotizacion->estatus,
            ['PAGADA', 'GUIA_GENERADA'],
            true
        )
    ) {
        return view('b2c.pago-success', compact('cotizacion'));
    }

    if ($cotizacion->estatus === 'PAGO_RECHAZADO') {
        return view('b2c.pago-failure', compact('cotizacion'));
    }

    return view('b2c.pago-pending', compact('cotizacion'));
}

private function verifyMercadoPagoCallback(
    Request $request,
    B2cCotizacion $cotizacion,
    PaymentVerificationService $verificationService,
    string $source
): B2cCotizacion {
    if ($cotizacion->payment_status === 'saldo_prepago') {
        return $cotizacion;
    }

    $paymentId = trim((string) (
        $request->get('payment_id')
        ?: $request->get('collection_id')
        ?: $cotizacion->payment_id
        ?: $cotizacion->payment_collection_id
        ?: ''
    ));

    if ($paymentId === '') {
        if ($cotizacion->estatus !== 'GUIA_GENERADA') {
            $cotizacion->update([
                'estatus' => 'PAGO_EN_VERIFICACION',
                'payment_verification_status' => 'PENDING',
                'payment_verification_source' => $source,
                'payment_verification_error' =>
                    'Falta el identificador del pago.',
                'payment_verification_attempted_at' => now(),
            ]);
        }

        return $cotizacion->refresh();
    }

    try {
        return $verificationService->verifyByPaymentId(
            $cotizacion,
            $paymentId,
            $source
        );
    } catch (PaymentVerificationException $exception) {
        \Log::warning('Pago Mercado Pago pendiente de verificación', [
            'cotizacion_id' => $cotizacion->id,
            'payment_id' => $paymentId,
            'verification_code' =>
                $exception->verificationCode(),
            'error' => $exception->getMessage(),
        ]);

        if ($cotizacion->estatus !== 'GUIA_GENERADA') {
            $cotizacion->update([
                'estatus' => 'PAGO_EN_VERIFICACION',
                'payment_verification_status' => 'PENDING',
                'payment_verification_source' => $source,
                'payment_verification_error' =>
                    $exception->verificationCode()
                    . ': '
                    . $exception->getMessage(),
                'payment_verification_attempted_at' => now(),
            ]);
        }

        return $cotizacion->refresh();
    }
}

public function generarGuia(
    B2cCotizacion $cotizacion,
    EstafetaGuideService $guideService,
    IdentityGuideAccessService $identityGuideAccessService
) {
    if ($cotizacion->user_id !== auth()->id()) {
        abort(403);
    }

    /*
     * Un pago ya acreditado siempre conserva el derecho
     * a generar o reintentar su guia. La identidad se
     * valida antes de cobrar nuevos envios, no despues
     * de haber descontado saldo o aprobado el pago.
     */
    if (
        !$cotizacion->hasGeneratedGuide()
        && !$cotizacion->hasAccreditedPayment()
    ) {
        try {
            $identityGuideAccessService
                ->assertCanGenerate(
                    (int) auth()->id(),
                    (int) $cotizacion->id
                );
        } catch (
            IdentityVerificationRequiredException $exception
        ) {
            return redirect()
                ->to(
                    route('b2c.configuracion')
                    . '#identidad'
                )
                ->with(
                    'identity_error',
                    $exception->getMessage()
                );
        }
    }

    if (
        strcasecmp(
            trim((string) $cotizacion->logistico),
            'Estafeta'
        ) !== 0
    ) {
        return back()->with(
            'error',
            'La generación de guía todavía no está disponible '
            . 'para el logístico seleccionado: '
            . $cotizacion->logistico
        );
    }

    try {
        $cotizacion = $guideService->generate(
            $cotizacion,
            $this->buildEstafetaPayload($cotizacion)
        );
    } catch (\RuntimeException $exception) {
        return back()->with(
            'error',
            $exception->getMessage()
        );
    }

    $pdfUrl = $cotizacion->documento
        ? asset(
            'storage/'
            . basename($cotizacion->documento)
        )
        : null;

    $message = $cotizacion->documento
        ? 'Guía disponible correctamente. Tracking: '
            . $cotizacion->tracking_number
        : 'La guía fue generada y el tracking quedó registrado. '
            . 'El documento requiere revisión operativa.';

    return redirect()
        ->route(
            'b2c.pago.success',
            $cotizacion->id
        )
        ->with('success', $message)
        ->with('pdf_url', $pdfUrl);
}

private function buildEstafetaPayload(B2cCotizacion $cotizacion): array
{
    $cpOrigen = substr($cotizacion->cp_origen, 0, 5);
    $cpDestino = substr($cotizacion->cp_destino, 0, 5);
    /*
     * Referencia visible en la etiqueta.
     * No enviar marcadores internos como
     * B2C_NUEVO_ENVIO o B2C_LOGUEADO.
     */
    $referenciaEtiqueta =
        'ZIGO-' . $cotizacion->id;

    [$largo, $ancho, $alto] = array_pad(
        array_map(
            'trim',
            explode(
                'x',
                strtolower(
                    $cotizacion->medidas
                    ?? '20x20x20'
                )
            )
        ),
        3,
        20
    );

    $requiereSeguro = (bool) $cotizacion->requiere_seguro_envio;
    $valorDeclarado = $requiereSeguro ? (float) $cotizacion->valor_declarado : 0.00;

    return [
        'user_id' => (int) env('B2C_USER_ID', 9),
        'empresa_id' => (int) env('B2C_EMPRESA_ID', 1),
        'ltd_id' => 2,
        'servicio_id' => (int) env('B2C_ESTAFETA_SERVICIO_ID', 1),
        'esManual' => 'API',
        'canal' => 'API',
        'valor_declarado' => $valorDeclarado,
        'valor_envio' => $valorDeclarado,
        'seguro' => $requiereSeguro ? 1 : 0,
        'bSeguro' => $requiereSeguro ? 'true' : 'false',
        'formatoImpresion' => 'FILE_PDF',

        'labelDefinition' => [
            'wayBillDocument' => [
                'content' => $cotizacion->contenido ?? 'Paquete',
                'aditionalInfo' => $referenciaEtiqueta ?: 'SIN REFERENCIA',
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
                        'addressReference' => $referenciaEtiqueta ?: 'SIN REFERENCIA',
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
                            'addressReference' => $referenciaEtiqueta ?: 'SIN REFERENCIA',
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

    $cotizacionActual = null;

    $cotizacionActualId = session(
        'b2c_cotizacion_actual_id'
    );

    if ($cotizacionActualId) {
        $cotizacionActual = B2cCotizacion::where(
            'id',
            $cotizacionActualId
        )
            ->where('user_id', $userId)
            ->first();

        if (!$cotizacionActual) {
            session()->forget(
                'b2c_cotizacion_actual_id'
            );
        }
    }

    $totalCotizaciones = B2cCotizacion::where('user_id', $userId)->count();

    $totalPagadas = B2cCotizacion::where(
        'user_id',
        $userId
    )
        ->where(function ($query) {
            $query
                ->whereIn(
                    'estatus',
                    [
                        'PAGADA',
                        'GUIA_GENERADA',
                    ]
                )
                ->orWhereIn(
                    'payment_status',
                    [
                        'approved',
                        'saldo_prepago',
                    ]
                );
        })
        ->count();

    $totalGuias = B2cCotizacion::where('user_id', $userId)
        ->whereNotNull('tracking_number')
        ->count();

    $totalErrores = B2cCotizacion::where(
        'user_id',
        $userId
    )
        ->where(function ($query) {
            $query
                ->where(
                    'estatus',
                    'like',
                    '%ERROR%'
                )
                ->orWhere(
                    'guia_estatus',
                    'like',
                    'ERROR%'
                );
        })
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
        'ultimosEnvios',
        'cotizacionActual'
    ));
}

public function misEnviosB2c()
{
    $envios = B2cCotizacion::where('user_id', auth()->id())
        ->latest()
        ->get();

    return view('b2c.mis-envios', compact('envios'));
}

public function detalleEnvioB2c(
    B2cCotizacion $cotizacion
) {
    $userId = (int) auth()->id();

    if (
        (int) $cotizacion->user_id
        !== $userId
    ) {
        abort(403);
    }

    $fiscalProfile =
        B2cFiscalProfile::where(
            'user_id',
            $userId
        )
            ->where('activo', true)
            ->first();

    $invoiceRequest =
        B2cInvoiceRequest::where(
            'cotizacion_id',
            $cotizacion->id
        )
            ->where('user_id', $userId)
            ->first();

    $paymentStatus = strtolower(
        trim(
            (string) $cotizacion
                ->payment_status
        )
    );

    $shipmentStatus = strtoupper(
        trim(
            (string) $cotizacion->estatus
        )
    );

    $canInvoice =
        in_array(
            $paymentStatus,
            [
                'approved',
                'saldo_prepago',
            ],
            true
        )
        || in_array(
            $shipmentStatus,
            [
                'PAGADA',
                'GUIA_GENERADA',
                'ERROR_GENERACION_GUIA',
            ],
            true
        );

    $regimenesFiscales = config(
        'b2c_fiscal.regimenes',
        []
    );

    $usosCfdi = config(
        'b2c_fiscal.usos_cfdi',
        []
    );

    return view(
        'b2c.detalle-envio',
        compact(
            'cotizacion',
            'fiscalProfile',
            'invoiceRequest',
            'canInvoice',
            'regimenesFiscales',
            'usosCfdi'
        )
    );
}

public function solicitarFacturaB2c(
    B2cCotizacion $cotizacion
) {
    $userId = (int) auth()->id();

    if (
        (int) $cotizacion->user_id
        !== $userId
    ) {
        abort(403);
    }

    $paymentStatus = strtolower(
        trim(
            (string) $cotizacion
                ->payment_status
        )
    );

    $shipmentStatus = strtoupper(
        trim(
            (string) $cotizacion->estatus
        )
    );

    $canInvoice =
        in_array(
            $paymentStatus,
            [
                'approved',
                'saldo_prepago',
            ],
            true
        )
        || in_array(
            $shipmentStatus,
            [
                'PAGADA',
                'GUIA_GENERADA',
                'ERROR_GENERACION_GUIA',
            ],
            true
        );

    if (!$canInvoice) {
        return redirect()
            ->route(
                'b2c.envios.detalle',
                [
                    'cotizacion' =>
                        $cotizacion->id,

                    'origen' => 'pagos',
                ]
            )
            ->with(
                'error',
                'Este pago no está aprobado y no puede facturarse.'
            );
    }

    $fiscalProfile =
        B2cFiscalProfile::where(
            'user_id',
            $userId
        )
            ->where('activo', true)
            ->first();

    if (
        !$fiscalProfile
        || !$fiscalProfile->estaCompleto()
    ) {
        return redirect()
            ->route('b2c.configuracion')
            ->with(
                'error',
                'Primero registra tus datos fiscales.'
            );
    }

    if (
        !$fiscalProfile->usoCfdiEsCompatible()
    ) {
        return redirect()
            ->route('b2c.configuracion')
            ->with(
                'error',
                'Actualiza tus datos fiscales: el uso de CFDI no es compatible con el régimen fiscal.'
            );
    }

    $paymentMethod =
        $paymentStatus === 'saldo_prepago'
            ? 'SALDO_PREPAGO'
            : 'MERCADO_PAGO';

    $invoiceRequest =
        B2cInvoiceRequest::firstOrCreate(
            [
                'cotizacion_id' =>
                    $cotizacion->id,
            ],
            [
                'user_id' => $userId,

                'fiscal_profile_id' =>
                    $fiscalProfile->id,

                'payment_reference' =>
                    $cotizacion
                        ->payment_external_reference
                    ?: $cotizacion->payment_id,

                'payment_status' =>
                    $cotizacion->payment_status,

                'payment_method' =>
                    $paymentMethod,

                'metodo_pago' => 'PUE',

                'forma_pago' => null,

                'monto' =>
                    (float) (
                        $cotizacion->precio
                        ?? 0
                    ),

                'razon_social' =>
                    $fiscalProfile
                        ->razon_social,

                'rfc' =>
                    $fiscalProfile->rfc,

                'codigo_postal_fiscal' =>
                    $fiscalProfile
                        ->codigo_postal_fiscal,

                'direccion_fiscal' =>
                    $fiscalProfile
                        ->direccion_fiscal,

                'regimen_fiscal' =>
                    $fiscalProfile
                        ->regimen_fiscal,

                'uso_cfdi' =>
                    $fiscalProfile
                        ->uso_cfdi,

                'email_facturacion' =>
                    $fiscalProfile
                        ->email_facturacion,

                'status' => 'SOLICITADA',

                'solicitada_at' => now(),
            ]
        );

    $message =
        $invoiceRequest->wasRecentlyCreated
            ? 'Solicitud de factura registrada correctamente.'
            : 'Este pago ya tiene una solicitud de factura registrada.';

    return redirect()
        ->route(
            'b2c.envios.detalle',
            [
                'cotizacion' =>
                    $cotizacion->id,

                'origen' => 'pagos',
            ]
        )
        ->with('success', $message);
}

public function misPagosB2c()
{
    $pagos = B2cCotizacion::where(
        'user_id',
        auth()->id()
    )
        ->with('invoiceRequest')
        ->where(function ($query) {
            $query
                ->whereNotNull(
                    'payment_id'
                )
                ->orWhereNotNull(
                    'payment_status'
                )
                ->orWhereNotNull(
                    'payment_external_reference'
                );
        })
        ->latest()
        ->get();

    return view(
        'b2c.mis-pagos',
        compact('pagos')
    );
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

    $cotizacionEdicion = null;

    return view(
        'b2c.nuevo-envio',
        compact(
            'direccionesOrigen',
            'direccionesDestino',
            'cotizacionEdicion'
        )
    );
}

public function retomarEnvioB2c(
    B2cCotizacion $cotizacion
) {
    if ($cotizacion->user_id !== auth()->id()) {
        abort(403);
    }

    $status = strtoupper(
        trim((string) $cotizacion->estatus)
    );

    $guideStatus = strtoupper(
        trim((string) $cotizacion->guia_estatus)
    );

    if ($cotizacion->hasGeneratedGuide()) {
        return redirect()
            ->route('b2c.envios.detalle', $cotizacion->id)
            ->with(
                'success',
                'La guía de este envío ya fue generada.'
            );
    }

    if (
        $cotizacion->hasAccreditedPayment()
        || in_array($status, [
            'PAGADA',
            'ERROR_GENERACION_GUIA',
        ], true)
    ) {
        return redirect()
            ->route('b2c.envios.detalle', $cotizacion->id)
            ->with(
                'success',
                'El pago está acreditado. Continúa con la generación o recuperación de la guía.'
            );
    }

    if ($guideStatus === 'GENERANDO') {
        return redirect()
            ->route('b2c.envios.detalle', $cotizacion->id)
            ->with(
                'success',
                'La generación de la guía está en curso.'
            );
    }

    if ($status === 'DIRECCION_CAPTURADA') {
        return redirect()->route(
            'b2c.paquete',
            $cotizacion->id
        );
    }

    if ($status === 'PAQUETE_CAPTURADO') {
        return redirect()->route(
            'b2c.opciones',
            $cotizacion->id
        );
    }

    if ($status === 'COTIZADA') {
        if ($cotizacion->hasQuotablePackageData()) {
            return redirect()->route(
                'b2c.opciones',
                $cotizacion->id
            );
        }

        if ($cotizacion->hasCompleteShippingAddresses()) {
            return redirect()->route(
                'b2c.paquete',
                $cotizacion->id
            );
        }

        return redirect()->route(
            'b2c.envios.editar',
            $cotizacion->id
        );
    }

    if ($status === 'SELECCIONADA') {
        if (
            $cotizacion->hasCompleteShippingAddresses()
            && $cotizacion->hasCompletePackageData()
        ) {
            return redirect()->route(
                'b2c.confirmar',
                $cotizacion->id
            );
        }

        return redirect()->route(
            'b2c.checkout',
            $cotizacion->id
        );
    }

    if (in_array($status, [
        'CHECKOUT_COMPLETO',
        'PAGO_RECHAZADO',
    ], true)) {
        return redirect()->route(
            'b2c.confirmar',
            $cotizacion->id
        );
    }

    if (in_array($status, [
        'PAGO_INICIADO',
        'PAGO_PENDIENTE',
        'PAGO_EN_VERIFICACION',
        'PAGO_VERIFICACION_FALLIDA',
    ], true)) {
        return redirect()
            ->route('b2c.envios.detalle', $cotizacion->id)
            ->with(
                'error',
                'El pago todavía requiere confirmación. Revisa su estado antes de iniciar otro cobro.'
            );
    }

    if (!$cotizacion->hasCompleteShippingAddresses()) {
        return redirect()->route(
            'b2c.envios.editar',
            $cotizacion->id
        );
    }

    if (!$cotizacion->hasCompletePackageData()) {
        return redirect()->route(
            'b2c.paquete',
            $cotizacion->id
        );
    }

    if (
        !$cotizacion->logistico
        || !$cotizacion->servicio
        || (float) $cotizacion->precio <= 0
    ) {
        return redirect()->route(
            'b2c.opciones',
            $cotizacion->id
        );
    }

    return redirect()->route(
        'b2c.confirmar',
        $cotizacion->id
    );
}

public function editarEnvioB2c(
    B2cCotizacion $cotizacion
) {
    if ($cotizacion->user_id !== auth()->id()) {
        abort(403);
    }

    if (!$cotizacion->canEditShipment()) {
        return redirect()
            ->route('b2c.envios.detalle', $cotizacion->id)
            ->with(
                'error',
                'Este envío ya no permite editar direcciones porque tiene un pago o una guía en proceso.'
            );
    }

    $direccionesOrigen = B2cDireccion::where(
        'user_id',
        auth()->id()
    )
        ->where('activo', true)
        ->where('tipo', 'ORIGEN')
        ->orderByDesc('favorita')
        ->latest()
        ->get();

    $direccionesDestino = B2cDireccion::where(
        'user_id',
        auth()->id()
    )
        ->where('activo', true)
        ->where('tipo', 'DESTINO')
        ->orderByDesc('favorita')
        ->latest()
        ->get();

    $cotizacionEdicion = $cotizacion;

    return view(
        'b2c.nuevo-envio',
        compact(
            'direccionesOrigen',
            'direccionesDestino',
            'cotizacionEdicion'
        )
    );
}

public function actualizarEnvioB2c(
    Request $request,
    B2cCotizacion $cotizacion
) {
    if ($cotizacion->user_id !== auth()->id()) {
        abort(403);
    }

    if (!$cotizacion->canEditShipment()) {
        return redirect()
            ->route('b2c.envios.detalle', $cotizacion->id)
            ->with(
                'error',
                'Este envío ya no permite modificaciones.'
            );
    }

    $data = $request->validate([
        'remitente_nombre' => ['required', 'string', 'max:255'],
        'remitente_email' => ['nullable', 'email', 'max:255'],
        'remitente_telefono' => ['required', 'string', 'max:30'],
        'remitente_direccion' => ['required', 'string', 'max:255'],
        'remitente_num_ext' => ['required', 'string', 'max:50'],
        'remitente_num_int' => ['nullable', 'string', 'max:50'],
        'cp_origen' => ['required', 'string', 'max:120'],
        'colonia_origen' => ['required', 'string', 'max:255'],
        'ciudad_origen' => ['required', 'string', 'max:100'],
        'estado_origen' => ['required', 'string', 'max:100'],

        'destinatario_nombre' => ['required', 'string', 'max:255'],
        'destinatario_email' => ['nullable', 'email', 'max:255'],
        'destinatario_telefono' => ['required', 'string', 'max:30'],
        'destinatario_direccion' => ['required', 'string', 'max:255'],
        'destinatario_num_ext' => ['required', 'string', 'max:50'],
        'destinatario_num_int' => ['nullable', 'string', 'max:50'],
        'cp_destino' => ['required', 'string', 'max:120'],
        'colonia_destino' => ['required', 'string', 'max:255'],
        'ciudad_destino' => ['required', 'string', 'max:100'],
        'estado_destino' => ['required', 'string', 'max:100'],
    ]);

    $cpOrigen = substr(
        preg_replace('/\D/', '', $data['cp_origen']),
        0,
        5
    );

    $cpDestino = substr(
        preg_replace('/\D/', '', $data['cp_destino']),
        0,
        5
    );

    if (strlen($cpOrigen) !== 5) {
        return back()
            ->withErrors([
                'cp_origen' =>
                    'Selecciona un código postal de origen válido.',
            ])
            ->withInput();
    }

    if (strlen($cpDestino) !== 5) {
        return back()
            ->withErrors([
                'cp_destino' =>
                    'Selecciona un código postal de destino válido.',
            ])
            ->withInput();
    }

    $ubicacionOrigen = $this->resolverUbicacionPostal(
        $cpOrigen,
        $data['colonia_origen']
    );

    $ubicacionDestino = $this->resolverUbicacionPostal(
        $cpDestino,
        $data['colonia_destino']
    );

    try {
        DB::transaction(function () use (
            $cotizacion,
            $data,
            $cpOrigen,
            $cpDestino,
            $ubicacionOrigen,
            $ubicacionDestino
        ) {
            $locked = B2cCotizacion::query()
                ->whereKey($cotizacion->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!$locked->canEditShipment()) {
                throw new \DomainException(
                    'El envío cambió de estado y ya no permite edición.'
                );
            }

            $locked->forceFill([
                'cp_origen' => $cpOrigen,
                'colonia_origen' => $data['colonia_origen'],
                'ciudad_origen' => $data['ciudad_origen']
                    ?: $ubicacionOrigen['ciudad'],
                'estado_origen' => $data['estado_origen']
                    ?: $ubicacionOrigen['estado'],
                'cp_destino' => $cpDestino,
                'colonia_destino' => $data['colonia_destino'],
                'ciudad_destino' => $data['ciudad_destino']
                    ?: $ubicacionDestino['ciudad'],
                'estado_destino' => $data['estado_destino']
                    ?: $ubicacionDestino['estado'],

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

                'logistico' => null,
                'servicio' => null,
                'precio' => null,
                'precio_sin_seguro' => null,
                'provider_base_price' => null,
                'zigo_margin_percentage' => null,
                'zigo_fixed_fee' => null,
                'zigo_margin_amount' => null,
                'zigo_adjustment_type' => null,
                'zigo_adjustment_value' => null,
                'zigo_adjustment_amount' => null,
                'zigo_discount_type' => null,
                'zigo_discount_value' => null,
                'zigo_discount_amount' => null,
                'zigo_final_price' => null,
                'zigo_profit_amount' => null,
                'zigo_customer_segment' => null,
                'zigo_pricing_rule_id' => null,
                'zigo_pricing_adjustment_id' => null,
                'zigo_client_pricing_rule_id' => null,

                'payment_id' => null,
                'payment_status' => null,
                'payment_external_reference' => null,
                'payment_collection_id' => null,
                'payment_verification_status' => null,
                'payment_verification_source' => null,
                'payment_verification_error' => null,
                'payment_verification_attempted_at' => null,
                'payment_verified_at' => null,
                'payment_verified_amount' => null,
                'payment_verified_currency' => null,
                'payment_verified_external_reference' => null,
                'payment_verification_payload' => null,

                'guia_id' => null,
                'tracking_number' => null,
                'documento' => null,
                'guia_estatus' => 'SIN_GUIA',
                'guia_provider_reference' => null,
                'guia_provider_request_number' => null,
                'guia_generation_attempts' => 0,
                'guia_generation_started_at' => null,
                'guia_last_attempt_at' => null,
                'guia_generated_at' => null,
                'guia_recovered_at' => null,
                'guia_last_error_code' => null,
                'guia_last_error_message' => null,
                'guia_request_snapshot' => null,
                'guia_response_snapshot' => null,

                'estatus' => 'DIRECCION_CAPTURADA',
            ])->save();
        });
    } catch (\DomainException $exception) {
        return redirect()
            ->route('b2c.envios.detalle', $cotizacion->id)
            ->with('error', $exception->getMessage());
    }

    return redirect()
        ->route('b2c.paquete', $cotizacion->id)
        ->with(
            'success',
            'Direcciones actualizadas. Revisa el paquete y vuelve a cotizar el servicio.'
        );
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
        'referencia' => 'B2C_NUEVO_ENVIO',

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

public function pagarConSaldo(
    B2cCotizacion $cotizacion,
    PaymentVerificationService $verificationService,
    IdentityGuideAccessService $identityGuideAccessService
) {
    if ($cotizacion->user_id !== auth()->id()) {
        abort(403);
    }

    if ($cotizacion->estatus !== 'CHECKOUT_COMPLETO') {
        return redirect()
            ->route(
                'b2c.checkout',
                $cotizacion->id
            )
            ->with(
                'error',
                'Completa los datos del envío antes '
                . 'de pagar con saldo.'
            );
    }

    try {
        DB::transaction(
            function () use (
                $cotizacion,
                $verificationService,
                $identityGuideAccessService
            ) {
                $identityGuideAccessService
                    ->assertCanProceed(
                        (int) $cotizacion->user_id,
                        (int) $cotizacion->id,
                        true
                    );

                $lockedCotizacion =
                    B2cCotizacion::query()
                        ->whereKey($cotizacion->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                $reference =
                    'COTIZACION-'
                    . $lockedCotizacion->id;

                $existingMovement =
                    B2cMovimientoSaldo::query()
                        ->where(
                            'user_id',
                            auth()->id()
                        )
                        ->where(
                            'tipo',
                            'COMPRA_GUIA'
                        )
                        ->where(
                            'referencia',
                            $reference
                        )
                        ->where(
                            'estatus',
                            'APLICADO'
                        )
                        ->lockForUpdate()
                        ->first();

                if ($existingMovement) {
                    $lockedCotizacion->forceFill([
                        'estatus' => 'PAGADA',
                        'payment_status' =>
                            'saldo_prepago',
                        'payment_external_reference' =>
                            'SALDO-'
                            . $lockedCotizacion->id,
                    ])->save();

                    $verificationService
                        ->markBalancePaymentVerified(
                            $lockedCotizacion
                        );

                    return;
                }

                $saldo =
                    B2cSaldo::query()
                        ->where(
                            'user_id',
                            auth()->id()
                        )
                        ->lockForUpdate()
                        ->first();

                if (!$saldo) {
                    $saldo = B2cSaldo::create([
                        'user_id' => auth()->id(),
                        'saldo' => 0,
                    ]);
                }

                $total =
                    (float) $lockedCotizacion->precio;

                $saldoAnterior =
                    (float) $saldo->saldo;

                if ($saldoAnterior < $total) {
                    throw new \DomainException(
                        'Saldo insuficiente. '
                        . 'Recarga saldo o paga '
                        . 'con Mercado Pago.'
                    );
                }

                $saldoNuevo =
                    $saldoAnterior - $total;

                $saldo->update([
                    'saldo' => $saldoNuevo,
                ]);

                B2cMovimientoSaldo::create([
                    'user_id' => auth()->id(),
                    'tipo' => 'COMPRA_GUIA',
                    'monto' => $total,
                    'saldo_anterior' =>
                        $saldoAnterior,
                    'saldo_nuevo' =>
                        $saldoNuevo,
                    'referencia' => $reference,
                    'estatus' => 'APLICADO',
                ]);

                $lockedCotizacion->forceFill([
                    'estatus' => 'PAGADA',
                    'payment_status' =>
                        'saldo_prepago',
                    'payment_external_reference' =>
                        'SALDO-'
                        . $lockedCotizacion->id,
                ])->save();

                $verificationService
                    ->markBalancePaymentVerified(
                        $lockedCotizacion
                    );
            }
        );
    } catch (
        IdentityVerificationRequiredException $exception
    ) {
        return redirect()
            ->to(
                route('b2c.configuracion')
                . '#identidad'
            )
            ->with(
                'identity_error',
                $exception->getMessage()
            );
    } catch (\DomainException $exception) {
        return back()->with(
            'error',
            $exception->getMessage()
        );
    }

    session()->forget(
        'b2c_metodo_pago_' . $cotizacion->id
    );

    return redirect()
        ->route(
            'b2c.pago.success',
            $cotizacion->id
        )
        ->with(
            'success',
            'Guía pagada con saldo prepago.'
        );
}

public function configuracionB2c()
{
    $userId = auth()->id();

    $identity =
        B2cIdentityVerification::firstOrCreate(
            [
                'user_id' => $userId,
            ],
            [
                'status' => 'SIN_VERIFICAR',
            ]
        );

    $fiscalProfile =
        B2cFiscalProfile::where(
            'user_id',
            $userId
        )->first();

    $regimenesFiscales = config(
        'b2c_fiscal.regimenes',
        []
    );

    $usosCfdi = config(
        'b2c_fiscal.usos_cfdi',
        []
    );

    $usosCfdiPorRegimen = config(
        'b2c_fiscal.usos_cfdi_por_regimen',
        []
    );

    return view(
        'b2c.configuracion',
        compact(
            'identity',
            'fiscalProfile',
            'regimenesFiscales',
            'usosCfdi',
            'usosCfdiPorRegimen'
        )
    );
}

public function guardarDatosFiscalesB2c(
    Request $request
) {
    $regimenesFiscales = config(
        'b2c_fiscal.regimenes',
        []
    );

    $usosCfdi = config(
        'b2c_fiscal.usos_cfdi',
        []
    );

    $usosCfdiPorRegimen = config(
        'b2c_fiscal.usos_cfdi_por_regimen',
        []
    );

    $request->merge([
        'rfc' => strtoupper(
            preg_replace(
                '/\s+/',
                '',
                (string) $request->input('rfc')
            )
        ),

        'razon_social' => trim(
            (string) $request->input(
                'razon_social'
            )
        ),

        'codigo_postal_fiscal' =>
            preg_replace(
                '/\D/',
                '',
                (string) $request->input(
                    'codigo_postal_fiscal'
                )
            ),

        'regimen_fiscal' => trim(
            (string) $request->input(
                'regimen_fiscal'
            )
        ),

        'uso_cfdi' => strtoupper(
            trim(
                (string) $request->input(
                    'uso_cfdi'
                )
            )
        ),

        'email_facturacion' => strtolower(
            trim(
                (string) $request->input(
                    'email_facturacion'
                )
            )
        ),
    ]);

    $data = $request->validate(
        [
            'razon_social' => [
                'required',
                'string',
                'max:255',
            ],

            'rfc' => [
                'required',
                'string',
                'min:12',
                'max:13',
                'regex:/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/',
            ],

            'codigo_postal_fiscal' => [
                'required',
                'digits:5',
            ],

            'direccion_fiscal' => [
                'nullable',
                'string',
                'max:500',
            ],

            'regimen_fiscal' => [
                'required',
                Rule::in(
                    array_keys(
                        $regimenesFiscales
                    )
                ),
            ],

            'uso_cfdi' => [
                'bail',
                'required',
                Rule::in(
                    array_keys($usosCfdi)
                ),
                function (
                    $attribute,
                    $value,
                    $fail
                ) use (
                    $request,
                    $usosCfdiPorRegimen
                ) {
                    $regimenFiscal =
                        (string) $request->input(
                            'regimen_fiscal'
                        );

                    $usosPermitidos =
                        $usosCfdiPorRegimen[
                            $regimenFiscal
                        ] ?? [];

                    if (
                        $regimenFiscal !== ''
                        && ! in_array(
                            (string) $value,
                            $usosPermitidos,
                            true
                        )
                    ) {
                        $fail(
                            'El uso de CFDI seleccionado no es compatible con el régimen fiscal.'
                        );
                    }
                },
            ],

            'email_facturacion' => [
                'required',
                'email',
                'max:255',
            ],
        ],
        [
            'razon_social.required' =>
                'Captura el nombre o razón social.',

            'rfc.required' =>
                'Captura el RFC.',

            'rfc.regex' =>
                'El formato del RFC no es válido.',

            'codigo_postal_fiscal.required' =>
                'Captura el código postal fiscal.',

            'codigo_postal_fiscal.digits' =>
                'El código postal fiscal debe contener 5 dígitos.',

            'regimen_fiscal.required' =>
                'Selecciona el régimen fiscal.',

            'regimen_fiscal.in' =>
                'El régimen fiscal seleccionado no es válido.',

            'uso_cfdi.required' =>
                'Selecciona el uso de CFDI.',

            'uso_cfdi.in' =>
                'El uso de CFDI seleccionado no es válido.',

            'email_facturacion.required' =>
                'Captura el correo para recibir facturas.',

            'email_facturacion.email' =>
                'El correo para facturas no es válido.',
        ]
    );

    $data['user_id'] = auth()->id();
    $data['activo'] = true;

    B2cFiscalProfile::updateOrCreate(
        [
            'user_id' => auth()->id(),
        ],
        $data
    );

    return redirect()
        ->route('b2c.configuracion')
        ->with(
            'success',
            'Datos fiscales guardados correctamente.'
        );
}

public function guardarIdentidadB2c(Request $request)
{
    $request->validate([
        'ine_front' => [
            'required',
            'image',
            'mimes:jpg,jpeg,png,webp',
            'max:5120',
        ],
        'ine_back' => [
            'required',
            'image',
            'mimes:jpg,jpeg,png,webp',
            'max:5120',
        ],
        'selfie_with_ine' => [
            'required',
            'image',
            'mimes:jpg,jpeg,png,webp',
            'max:5120',
        ],
    ]);

    $userId = (int) auth()->id();

    $identity =
        B2cIdentityVerification::firstOrCreate(
            [
                'user_id' => $userId,
            ],
            [
                'status' =>
                    B2cIdentityVerification::STATUS_UNVERIFIED,
            ]
        );

    if (
        $identity->status
        === B2cIdentityVerification::STATUS_APPROVED
    ) {
        return redirect()
            ->route('b2c.configuracion')
            ->with(
                'error',
                'Tu identidad ya está aprobada. '
                . 'No es necesario reemplazar los documentos.'
            );
    }

    $directory =
        'b2c/identity/'
        . $userId
        . '/'
        . now()->format('Ymd_His');

    $newPaths = [];

    try {
        foreach (
            [
                'ine_front',
                'ine_back',
                'selfie_with_ine',
            ] as $field
        ) {
            $newPaths[$field] =
                $request->file($field)
                    ->store($directory, 'local');
        }

        $oldPaths = [
            $identity->ine_front,
            $identity->ine_back,
            $identity->selfie_with_ine,
        ];

        $oldDisk =
            in_array(
                $identity->document_disk,
                ['local', 'public'],
                true
            )
                ? $identity->document_disk
                : 'public';

        DB::transaction(
            function () use (
                $identity,
                $userId,
                $newPaths
            ) {
                $fromStatus = $identity->status;

                $identity->update([
                    'ine_front' =>
                        $newPaths['ine_front'],
                    'ine_back' =>
                        $newPaths['ine_back'],
                    'selfie_with_ine' =>
                        $newPaths['selfie_with_ine'],
                    'document_disk' => 'local',
                    'status' =>
                        B2cIdentityVerification::STATUS_PENDING,
                    'submitted_at' => now(),
                    'comments' => null,
                    'correction_documents' => null,
                    'reviewed_at' => null,
                    'reviewed_by' => null,
                ]);

                \App\Models\B2cIdentityVerificationEvent::create([
                    'identity_verification_id' =>
                        $identity->id,
                    'user_id' => $userId,
                    'event_type' => 'SUBMITTED',
                    'from_status' => $fromStatus,
                    'to_status' =>
                        B2cIdentityVerification::STATUS_PENDING,
                    'comments' => null,
                    'metadata' => [
                        'documents' => [
                            'ine_front',
                            'ine_back',
                            'selfie_with_ine',
                        ],
                    ],
                    'performed_by' => $userId,
                ]);
            }
        );

        foreach ($oldPaths as $oldPath) {
            if (
                filled($oldPath)
                && $oldPath !== $newPaths['ine_front']
                && $oldPath !== $newPaths['ine_back']
                && $oldPath !== $newPaths['selfie_with_ine']
            ) {
                \Illuminate\Support\Facades\Storage::disk(
                    $oldDisk
                )->delete($oldPath);
            }
        }
    } catch (\Throwable $exception) {
        foreach ($newPaths as $newPath) {
            \Illuminate\Support\Facades\Storage::disk(
                'local'
            )->delete($newPath);
        }

        throw $exception;
    }

    return redirect()
        ->to(
            route('b2c.configuracion')
            . '#identidad'
        )
        ->with(
            'identity_success',
            'Documentos enviados correctamente. '
            . 'Tu identidad quedó pendiente de revisión.'
        );
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

public function guardarPaqueteB2c(
    Request $request,
    B2cCotizacion $cotizacion
) {
    if ($cotizacion->user_id !== auth()->id()) {
        abort(403);
    }

    if (
        $cotizacion->guia_id ||
        $cotizacion->estatus === 'GUIA_GENERADA'
    ) {
        return back()->with(
            'error',
            'No es posible modificar un envío que ya tiene guía.'
        );
    }

    $data = $request->validate([
        'tipo_envio' => [
            'required',
            'in:caja,sobre',
        ],

        'peso' => [
            'nullable',
            'required_if:tipo_envio,caja',
            'numeric',
            'min:0.1',
        ],

        'largo' => [
            'nullable',
            'required_if:tipo_envio,caja',
            'numeric',
            'min:1',
        ],

        'ancho' => [
            'nullable',
            'required_if:tipo_envio,caja',
            'numeric',
            'min:1',
        ],

        'alto' => [
            'nullable',
            'required_if:tipo_envio,caja',
            'numeric',
            'min:1',
        ],

        'contenido' => [
            'required',
            'string',
            'max:255',
        ],

        'valor_declarado' => [
            'nullable',
            'numeric',
            'min:0',
        ],

        'requiere_seguro_envio' => [
            'nullable',
            'boolean',
        ],

        'acepta_no_prohibidos' => [
            'accepted',
        ],
    ]);

    $tipoEnvio = strtolower(
        trim($data['tipo_envio'])
    );

    /*
     * Cálculo del peso facturable.
     */
    if ($tipoEnvio === 'sobre') {
        $pesoReal = 1.00;
        $pesoVolumetrico = 0.00;
        $pesoFacturable = 1.00;
        $medidas = null;
    } else {
        $pesoReal = round(
            (float) $data['peso'],
            2
        );

        $largo = (float) $data['largo'];
        $ancho = (float) $data['ancho'];
        $alto = (float) $data['alto'];

        $pesoVolumetrico = round(
            ($largo * $ancho * $alto) / 5000,
            2
        );

        $pesoFacturable = (float) ceil(
            max(
                $pesoReal,
                $pesoVolumetrico
            )
        );

        $formatearMedida = static function (
            float $valor
        ): string {
            return rtrim(
                rtrim(
                    number_format(
                        $valor,
                        2,
                        '.',
                        ''
                    ),
                    '0'
                ),
                '.'
            );
        };

        $medidas =
            $formatearMedida($largo)
            . 'x'
            . $formatearMedida($ancho)
            . 'x'
            . $formatearMedida($alto);
    }

    /*
     * Cálculo de protección.
     */
    $valorDeclarado = round(
        (float) ($data['valor_declarado'] ?? 0),
        2
    );

    $requiereSeguro = (bool) (
        $data['requiere_seguro_envio'] ?? false
    );

    if (
        $requiereSeguro &&
        $valorDeclarado <= 0
    ) {
        return back()
            ->withInput()
            ->with(
                'error',
                'Para proteger tu envío debes capturar '
                . 'un valor declarado mayor a cero.'
            );
    }

    $seguroPorcentaje = 2.00;
    $seguroIvaPorcentaje = 16.00;

    $seguroBase = 0.00;
    $seguroIva = 0.00;
    $seguroMonto = 0.00;

    if ($requiereSeguro) {
        $seguroBase = round(
            $valorDeclarado
            * ($seguroPorcentaje / 100),
            2
        );

        $seguroIva = round(
            $seguroBase
            * ($seguroIvaPorcentaje / 100),
            2
        );

        $seguroMonto = round(
            $seguroBase + $seguroIva,
            2
        );
    }

    $cotizacion->update([
        'tipo_envio' => $tipoEnvio,

        /*
         * peso conserva el valor utilizado para cotizar.
         */
        'peso' => $pesoFacturable,
        'peso_real' => $pesoReal,
        'peso_volumetrico' => $pesoVolumetrico,
        'peso_facturable' => $pesoFacturable,

        'medidas' => $medidas,
        'contenido' => $data['contenido'],

        'valor_declarado' => $valorDeclarado,
        'requiere_seguro_envio' => $requiereSeguro,
        'seguro_porcentaje' => $seguroPorcentaje,
        'seguro_iva_porcentaje' => $seguroIvaPorcentaje,
        'seguro_monto' => $seguroMonto,

        /*
         * Se debe seleccionar nuevamente el servicio
         * y calcular su precio.
         */
        'logistico' => null,
        'servicio' => null,
        'precio' => null,
        'precio_sin_seguro' => null,

        'estatus' => 'PAQUETE_CAPTURADO',
    ]);

    return redirect()->route(
        'b2c.opciones',
        $cotizacion->id
    );
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

    private function applyPricingToCotizacion(
        B2cCotizacion $cotizacion,
        array $option
    ): void {
        $pricing = $option['pricing'];

        /*
        * Precio final de la mensajería después de aplicar
        * margen, ajuste y descuento ZIGO.
        */
        $precioEnvio = round(
            (float) $pricing['final_price'],
            2
        );

        /*
        * En Nuevo envío la protección ya fue seleccionada
        * en la pantalla Paquete.
        *
        * En Cotizador rápido todavía se selecciona en Checkout,
        * por lo que aquí normalmente será cero.
        */
        $proteccionTotal = 0.00;

        if (
            (bool) $cotizacion->requiere_seguro_envio &&
            (float) $cotizacion->seguro_monto > 0
        ) {
            $proteccionTotal = round(
                (float) $cotizacion->seguro_monto,
                2
            );
        }

        /*
        * Total que pagará el cliente.
        */
        $precioTotal = round(
            $precioEnvio + $proteccionTotal,
            2
        );

        $cotizacion->update([
            'logistico' => $option['logistico'],
            'servicio' => $option['servicio'],

            /*
            * Desglose comercial.
            */
            'precio_sin_seguro' => $precioEnvio,

            /*
            * precio es el total que paga el cliente:
            * mensajería + protección.
            */
            'precio' => $precioTotal,

            /*
            * Auditoría de pricing ZIGO.
            * Estos campos corresponden al precio de mensajería,
            * sin incorporar la protección.
            */
            'provider_base_price' =>
                $pricing['base_price'],

            'zigo_margin_percentage' =>
                $pricing['margin_percentage'],

            'zigo_fixed_fee' =>
                $pricing['fixed_fee'],

            'zigo_margin_amount' =>
                $pricing['margin_amount'],

            'zigo_adjustment_type' =>
                $pricing['adjustment_type'],

            'zigo_adjustment_value' =>
                $pricing['adjustment_value'],

            'zigo_adjustment_amount' =>
                $pricing['adjustment_amount'],

            'zigo_discount_type' =>
                $pricing['discount_type'],

            'zigo_discount_value' =>
                $pricing['discount_value'],

            'zigo_discount_amount' =>
                $pricing['discount_amount'],

            /*
            * Mantener zigo_final_price como precio final
            * exclusivo de mensajería.
            */
            'zigo_final_price' =>
                $precioEnvio,

            'zigo_profit_amount' =>
                $pricing['profit_amount'],

            'zigo_customer_segment' =>
                $pricing['customer_segment'],

            'zigo_pricing_rule_id' =>
                $pricing['pricing_rule_id'],

            'zigo_pricing_adjustment_id' =>
                $pricing['adjustment_id'],

            'zigo_client_pricing_rule_id' =>
                $pricing['client_pricing_rule_id'],

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
        $cp = substr(preg_replace('/\D/', '', (string) $cp), 0, 5);

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

        if (!$row) {
            return [
                'ciudad' => null,
                'estado' => null,
                'colonia' => $colonia,
            ];
        }

        return [
            'ciudad' => $row->D_mnpio
                ?? $row->d_mnpio
                ?? $row->d_ciudad
                ?? $row->municipio
                ?? null,

            'estado' => $row->d_estado
                ?? $row->estado
                ?? null,

            'colonia' => $row->d_asenta
                ?? $row->colonia
                ?? $colonia,
        ];
    }

    public function limpiarCotizadorRapidoB2c()
    {
        session()->forget(
            'b2c_cotizacion_actual_id'
        );

        return redirect()->to(
            route('b2c.dashboard') . '#cotizador'
        );
    }
}
