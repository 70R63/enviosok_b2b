# Estafeta PRD Runbook

## Arquitectura

`EstafetaGateway` es la única fachada nueva. Delega autenticación, cobertura, cotización, guía, rastreo y cancelación en clientes especializados; `EstafetaHttpClient` aplica TLS, timeouts, correlación, clasificación, retries transitorios y observabilidad. `EstafetaTokenManager` usa caché y lock separados por ambiente. `LegacyEstafetaAdapter` permite migrar firmas basadas en arrays sin eliminar las clases históricas.

## Variables y preparación

Configure exclusivamente las variables `ZIGO_ESTAFETA_*` documentadas en `.env.example`. En Stage: URLs y credenciales de Stage, `ENVIRONMENT=stage`, TLS activo y mock desactivado. En PRD: URLs HTTPS verificadas, `ENVIRONMENT=production`, mock desactivado y checks activos DevOps desactivados inicialmente. No copie valores desde pantallas ni argumentos de comandos.

Ejecute `php artisan zigo:estafeta-audit`, luego `php artisan zigo:estafeta-health --environment=stage --check=all --dry-run`. Tras aprobación operativa ejecute por separado config, auth, cobertura y cotización controlada. El health nunca crea ni cancela guías. Para PRD se exige `--confirm=ESTAFETA-PRD`; la activación de auth/cobertura DevOps requiere `ZIGO_DEVOPS_ESTAFETA_PRD_ACTIVE_CHECKS=true`.

## Token

Use `php artisan zigo:estafeta-token-status`. `--invalidate` elimina solo la entrada de caché del ambiente. `--refresh` renueva; en PRD exige confirmación. El token no se persiste en base de datos ni se imprime.

## Errores comunes

- `configuration`: variable ausente, URL inválida, HTTP en PRD o mock en producción.
- `authentication_error`: revisar rotación y permisos sin registrar credenciales.
- `validation_error`/`business_error`: corregir contrato; no se reintentan.
- `rate_limit`/`provider_error`/`timeout`/`network_error`: revisar eventos correlacionados y política de retry.

## Rotación y rollback

Rotación: cargar secretos por el mecanismo seguro del ambiente, limpiar caché de configuración, invalidar el token, validar config/auth y conservar evidencia sanitizada. Rollback: desactivar `ZIGO_ESTAFETA_ENABLED`, restaurar el selector legacy existente y limpiar config; no revertir datos ni borrar tablas. Las clases legacy permanecen disponibles en esta fase.

## Evidencia y datos prohibidos

Conservar JSON privados de audit/health, salida sanitizada, correlation IDs, duración y estados. Nunca registrar token, password, API key, secretos, payload completo, nombre, teléfono, email, domicilio completo ni documentos/etiquetas. Un pase PRD requiere audit sin bloqueos, Stage validado, contrato de endpoints confirmado por Estafeta, credenciales rotadas y aprobación operativa.
# EST-PRD-02 Stage

El contrato OAuth está confirmado por `Estafeta::__construct`: formulario URL encoded con `grant_type=client_credentials`, `client_id`, `client_secret` y `scope`; respuesta con `access_token` y `expires_in`.

Cobertura y cotización del legacy son consultas locales, no contratos HTTP Estafeta. Permanecen en `unknown` y bloquean la activación del gateway hasta obtener evidencia contractual real. No configure URLs ni cambie esos indicadores por inferencia.

Generar plantilla redactada: `php artisan zigo:estafeta-config-template --environment=stage`.

Diagnóstico seguro: `php artisan zigo:estafeta-health --environment=stage --check=all --cp-origin=64000 --cp-destination=64000 --weight=1 --length=20 --width=20 --height=20 --package-type=box --confirm-stage=ESTAFETA-STAGE`.

Esta fase no habilita guías ni permite PRD.

## EST-PRD-03.1 — probe único Xperta/Estafeta PRD

El probe PRD permanece apagado con `ZIGO_SHIPPING_PRD_QUOTE_PROBE_ENABLED=false`. No modifica `ZIGO_B2C_UNIFIED_QUOTE_ENABLED`, no usa fallback legacy, no persiste cotizaciones comerciales y no permite `--record-contract`.

Solo acepta `carrier=estafeta`, `operation=quote`, confirmación `SHIPPING-PRD` y el paquete canónico 64000→64000, 1 kg, 20×20×20 cm, tipo box. Ejecuta exclusivamente la estrategia `xperta_estafeta`, un solo servicio y sin retries funcionales.

```bash
php artisan zigo:shipping-probe --environment=production --carrier=estafeta --operation=quote --cp-origin=64000 --cp-destination=64000 --weight=1 --length=20 --width=20 --height=20 --package-type=box --confirm=SHIPPING-PRD
```

Antes de una ventana autorizada, habilitar temporalmente solo `ZIGO_SHIPPING_PRD_QUOTE_PROBE_ENABLED=true`, regenerar config cache y volver a `false` inmediatamente después. Nunca combinar con `--record-contract`.

## EST-PRD-03.3 — validación aislada del token Xperta

El chequeo de token está apagado con `ZIGO_XPERTA_PRD_TOKEN_CHECK_ENABLED=false`. Sin `--execute`, el comando solo valida y genera evidencia redactada; no abre tráfico. La ejecución activa llama exactamente una vez al endpoint configurado en `XPERTA_TOKEN_PATH`, con el mismo payload y headers del flujo Xperta vigente. Solicita un token nuevo sin consultar, borrar ni reemplazar la caché compartida.

```bash
php artisan zigo:xperta-token-check --environment=production --confirm=XPERTA-TOKEN-PRD --execute
```

Durante una ventana autorizada, habilite temporalmente la bandera, regenere la caché de configuración y vuelva a deshabilitarla al concluir. El reporte privado en `storage/app/private/xperta-token-checks/` conserva solo host, path, empresa, estado HTTP, duración, presencia/longitud/fingerprint del token y si se envió `x-api-key`; nunca conserva el token, email, password, API key, headers, request o response completos. Este comando no ejecuta cotización, cobertura, guía, tracking, cancelación ni fallback.

### Contrato HTTP confirmado por Postman

El login usa `POST /api/v1/{corporativo}/login`: `email`, `password` y `minutos` viajan exclusivamente como query parameters; los headers son `x-api-key`, `Corporativo`, `minutos` y `Accept: application/json`. No existe JSON body. La respuesta válida exige `success=true` y `message.token`; `message.expires_at`, cuando existe, determina el TTL de caché con margen preventivo.

Frecuencia usa `POST /api/v1/empresas/{corporativo}/ltds/{ltd}/frecuencia/{origin}/{destination}` y body JSON con solo `token`. Cotización usa `POST /api/v1/empresas/{corporativo}/ltds/{ltd}/servicios/{service}/cotizaciones` y conserva exactamente `token`, `peso`, `largo`, `ancho`, `alto`, `cp`, `cp_d` y `valor_declarado`. Ambas operaciones envían `Corporativo`, `x-api-key`, `Content-Type: application/json` y `Accept: application/json`.
