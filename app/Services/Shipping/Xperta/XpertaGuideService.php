<?php

namespace App\Services\Shipping\Xperta;

use App\Models\B2cCotizacion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use RuntimeException;

class XpertaGuideService
{
    public function __construct(
        private XpertaApiClient $client,
        private XpertaTokenService $tokenService
    ) {
    }

    public function buildPayload(B2cCotizacion $cotizacion, bool $includeToken = true): array
    {
        $this->validateCotizacion($cotizacion);
        [$length, $width, $height] = $this->parseDimensions($cotizacion);
        $weight = $this->numericString(
            $cotizacion->peso_facturable ?: $cotizacion->peso_real ?: $cotizacion->peso
        );
        $insured = (bool) $cotizacion->requiere_seguro_envio;
        $insurance = $insured ? round((float) $cotizacion->valor_declarado, 2) : null;
        $senderEmail = trim((string) $cotizacion->remitente_email);
        $destinationEmail = trim((string) $cotizacion->destinatario_email);
        $name = $senderEmail !== ''
            ? $senderEmail
            : (trim((string) (Auth::user()?->email)) ?: trim((string) config('services.xperta.email')));
        $reference = trim((string) $cotizacion->referencia);

        return [
            'empresa_id' => $this->guideEmpresaId(),
            'token' => $includeToken ? $this->tokenService->encodedToken() : '***TOKEN_BASE64***',
            'name' => $name,
            'labelDefinition' => [
                'wayBillDocument' => [
                    'content' => trim((string) $cotizacion->contenido),
                    'aditionalInfo' => $reference,
                ],
                'itemDescription' => [
                    'parcelId' => 4,
                    'weight' => $weight,
                    'height' => $height,
                    'length' => $length,
                    'width' => $width,
                ],
                'serviceConfiguration' => [
                    'quantityOfLabels' => 1,
                    'originZipCodeForRouting' => (string) $cotizacion->cp_origen,
                    'isInsurance' => $insured,
                    'insurance' => $insurance,
                    'isReturnDocument' => false,
                    'effectiveDate' => now()->format('Ymd'),
                ],
                'location' => [
                    'origin' => $this->origin($cotizacion, $reference),
                    'destination' => [
                        'isDeliveryToPUDO' => false,
                        'homeAddress' => $this->destination($cotizacion, $destinationEmail, $reference),
                    ],
                    'notified' => [
                        'residence' => $this->notified(
                            $cotizacion,
                            $destinationEmail !== '' ? $destinationEmail : $name
                        ),
                    ],
                ],
            ],
        ];
    }

    public function create(B2cCotizacion $cotizacion, ?string $service = null): array
    {
        return $this->createWithMeta($cotizacion, $service)['data'];
    }

    public function createWithMeta(B2cCotizacion $cotizacion, ?string $service = null): array
    {
        if (!config('services.xperta.enabled', false)
            || !config('services.xperta.guide_enabled', false)
            || !config('zigo_b2c_xperta.guide_enabled', false)) {
            throw new RuntimeException('La generación de guía Xperta está desactivada.');
        }
        $service = $this->normalizeService($service ?: (string) $cotizacion->servicio);
        return $this->client->sendWithMeta(
            'POST', $this->resolvedPath($cotizacion, $service), $this->buildPayload($cotizacion),
            $this->providerHeaders(), true
        );
    }

    public function resolvedPath(B2cCotizacion $cotizacion, ?string $service = null): string
    {
        $service = $this->normalizeService($service ?: (string) $cotizacion->servicio);
        return $this->client->resolvePath(
            (string) config('services.xperta.guide_path', '/api/v1/empresas/{empresa}/ltds/{ltd}/servicios/{service}/guia'),
            ['empresa' => config('services.xperta.empresa'), 'ltd' => config('services.xperta.ltd', 'estafeta'), 'service' => $service]
        );
    }

    private function origin(B2cCotizacion $q, string $reference): array
    {
        return [
            'contact' => $this->contact((string) $q->remitente_nombre, (string) $q->remitente_telefono, (string) $q->remitente_email),
            'address' => $this->address($q, true, $reference, true),
        ];
    }

    private function destination(B2cCotizacion $q, string $email, string $reference): array
    {
        return [
            'contact' => $this->contact((string) $q->destinatario_nombre, (string) $q->destinatario_telefono, $email),
            'address' => $this->address($q, false, $reference, true),
        ];
    }

    private function notified(B2cCotizacion $q, string $email): array
    {
        return [
            'contact' => $this->contact((string) $q->destinatario_nombre, (string) $q->destinatario_telefono, $email),
            'address' => $this->address($q, false, null, false),
        ];
    }

    private function contact(string $name, string $phone, string $email): array
    {
        return [
            'corporateName' => trim($name), 'contactName' => trim($name),
            'cellPhone' => preg_replace('/\D/', '', $phone), 'email' => trim($email), 'taxPayerCode' => '',
        ];
    }

    private function address(B2cCotizacion $q, bool $origin, ?string $reference, bool $complete): array
    {
        $prefix = $origin ? 'remitente' : 'destinatario';
        $suffix = $origin ? 'origen' : 'destino';
        $address = [
            'bUsedCode' => false, 'roadTypeAbbName' => 'Calle',
            'roadName' => (string) $q->{$prefix . '_direccion'},
            'settlementTypeAbbName' => 'Col', 'settlementName' => (string) $q->{'colonia_' . $suffix},
            'zipCode' => (string) $q->{'cp_' . $suffix}, 'countryName' => 'MEX',
        ];
        if ($complete) {
            $address['ciudad'] = (string) $q->{'ciudad_' . $suffix};
            $address['entidad'] = (string) $q->{'estado_' . $suffix};
        }
        $address['addressReference'] = $reference;
        $address['externalNum'] = (string) $q->{$prefix . '_num_ext'};
        if ($complete) {
            $address['indoorInformation'] = (string) ($q->{$prefix . '_num_int'} ?? '');
        }
        return $address;
    }

    private function guideEmpresaId(): ?int
    {
        $value = config('services.xperta.guide_empresa_id');
        if ($value === null || trim((string) $value) === '') return null;
        if (!preg_match('/^\d+$/', trim((string) $value))) {
            throw new RuntimeException('XPERTA_GUIDE_EMPRESA_ID debe ser numérico o vacío.');
        }
        return (int) $value;
    }

    private function numericString(mixed $value): string
    {
        $number = round((float) $value, 2);
        if ($number <= 0) throw new RuntimeException('El peso y las dimensiones deben ser mayores que cero.');
        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }

    private function validateCotizacion(B2cCotizacion $q): void
    {
        if (!$q->hasCompleteShippingAddresses()) throw new RuntimeException('La cotización no tiene direcciones completas.');
        if (!$q->hasCompletePackageData()) throw new RuntimeException('La cotización no tiene datos completos del paquete.');
        foreach (['ciudad_origen', 'estado_origen', 'ciudad_destino', 'estado_destino'] as $field) {
            if (trim((string) $q->{$field}) === '') throw new RuntimeException('Falta completar el campo ' . $field . '.');
        }
    }

    private function parseDimensions(B2cCotizacion $q): array
    {
        if (strtolower((string) $q->tipo_envio) === 'sobre') return ['1', '1', '1'];
        $parts = array_values(array_filter(preg_split('/x|\*|,|;|\s+/', strtolower((string) $q->medidas)), fn ($v) => $v !== ''));
        if (count($parts) !== 3) throw new RuntimeException('Las dimensiones no tienen formato largo x ancho x alto.');
        foreach ($parts as $dimension) {
            if (!preg_match('/^[1-9][0-9]{0,2}$/', $dimension)) {
                throw new RuntimeException('Las dimensiones de caja deben ser enteros positivos de máximo tres dígitos.');
            }
        }
        return $parts;
    }

    private function normalizeService(string $service): string
    {
        $normalized = mb_strtolower(trim(Str::ascii($service)));
        return match (true) {
            str_contains($normalized, 'dia sig'), str_contains($normalized, 'siguiente'), $normalized === 'diasig' => 'diasig',
            str_contains($normalized, 'terrestre'), $normalized === 'terrestre' => 'terrestre',
            default => throw new RuntimeException('Servicio Xperta no reconocido: ' . $service),
        };
    }

    private function providerHeaders(): array
    {
        return ['Corporativo' => (string) config('services.xperta.corporativo'),
            'x-api-key' => (string) config('services.xperta.api_key'),
            'Content-Type' => 'application/json', 'Accept' => 'application/json'];
    }
}
