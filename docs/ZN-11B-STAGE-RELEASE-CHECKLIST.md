# ZN-11B — Checklist de liberación a Stage

Este procedimiento es para Stage. Nunca copie valores secretos a este documento, tickets o logs.

## Pre-deploy

- Confirmar respaldo de base de datos y ventana de mantenimiento.
- Confirmar que las migraciones ZN-11B `100000`, `100100`, `100200`, `100300` y `100400` están incluidas y pendientes en el orden esperado.
- Validar los nombres de configuración, sin imprimir sus valores: `APP_ENV`, `APP_URL`, `APP_KEY`, `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
- Configurar el host corporativo, Network y Payments conforme al mapa de hosts de Stage.
- Configurar `ZIGO_TENANT_DOMAIN`, `ZIGO_ONBOARDING_RESERVATION_MINUTES`, `ZIGO_ONBOARDING_TAX_RATE`, `ZIGO_ONBOARDING_MANAGED_SUBDOMAINS_VERIFIED` y `ZIGO_ONBOARDING_TENANT_SCHEME`.
- Confirmar `QUEUE_CONNECTION`. `sync` es operable, pero el reconciliador debe quedar disponible aunque no exista worker permanente.
- Confirmar correo con `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_ENCRYPTION`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` y `MAIL_FROM_NAME`.
- Confirmar Mercado Pago Platform con `ZIGO_MP_PLATFORM_ENABLED`, `ZIGO_MP_PLATFORM_ACCESS_TOKEN`, `ZIGO_MP_PLATFORM_ACCOUNT_ID`, `ZIGO_MP_PLATFORM_WEBHOOK_SECRET` y `ZIGO_MP_PLATFORM_WEBHOOK_URL`.
- Confirmar que la URL de webhook apunta por HTTPS al host Payments y a `POST /payments/mercado-pago/webhook`.
- Confirmar wildcard DNS y certificado TLS para `*.zigo-envios.com` (o el dominio configurado de Stage), además de los hosts corporativo, Network y Payments.
- Verificar que el catálogo público contiene una oferta activa/publicable con precios server-side válidos.
- Verificar que el cron/operación puede ejecutar `php artisan zigo:onboarding:reconcile --paid --failed --limit=50` con exclusión mutua.

## Deploy

1. Activar modo mantenimiento.
2. Publicar el código de la versión aprobada.
3. Ejecutar Composer sólo si `composer.lock` cambió.
4. Publicar assets sólo si los artefactos públicos cambiaron.
5. Ejecutar `php artisan migrate --force`.
6. Ejecutar `php artisan optimize:clear`.
7. Reconstruir únicamente las cachés usadas por la política del servidor (`config:cache`, `route:cache` y/o `view:cache`).
8. Desactivar modo mantenimiento.
9. Confirmar que el reconciliador puede ejecutarse manualmente antes de habilitar tráfico comercial.

## Smoke post-deploy

- `GET /zigo-platform` responde 200 en el host corporativo.
- `GET /zigo-platform/precios` responde 200 y no inventa precios.
- `GET /zigo-platform/comenzar` responde 200.
- El endpoint/health check del host Payments responde y el webhook no acepta firmas inválidas.
- `/network/onboarding` responde para sysadmin con 2FA y no en hosts incorrectos.
- Un tenant ya existente conserva admin, portal y branding.
- La consulta logística de código postal `64000` funciona con el mecanismo de smoke existente, sin consumir un carrier real si Stage dispone de fake.

## E2E sandbox

1. Crear una empresa y `purchase_key` nuevos desde el host corporativo.
2. Seleccionar una oferta publicable y reservar un subdominio nuevo.
3. Confirmar que antes del pago no existen Tenant, Empresa, User, Membership, Subscription, Entitlement, Branding ni TenantDomain.
4. Completar un pago sandbox de Mercado Pago Platform.
5. Confirmar webhook verificado, payment attempt `APPROVED` y onboarding `PAID` antes del provisioning.
6. Confirmar onboarding `ACTIVE`, un único tenant y dominio primario verificado.
7. Confirmar recepción única de la activación, establecimiento de password y acceso al host correcto.
8. Completar `/admin/setup` y abrir `/admin`.
9. Registrar un cliente final en el tenant, iniciar sesión, cotizar y abrir el flujo de nuevo envío sin ejecutar pago Customer→Tenant real.
10. Confirmar en Network el payment `APPROVED`, onboarding `ACTIVE` y timeline sanitizado.

## Rollback y recuperación

- Rollback de código: volver a la versión anterior compatible y limpiar/reconstruir cachés. No manipular estados de pago para simular reversión.
- Las migraciones ZN-11B son aditivas. No ejecutar `migrate:rollback` automáticamente si ya existen solicitudes, intentos o pagos; evaluar compatibilidad y respaldo antes de cualquier reversión de esquema.
- Nunca borrar ni revertir datos de un payment attempt `APPROVED`, `paid_at` o su evento verificado.
- Si el pago quedó aprobado y provisioning falló, conservar `APPROVED`/`paid_at`, corregir la causa y ejecutar `php artisan zigo:onboarding:reconcile --uuid=<UUID>` o el retry autorizado de Network.
- `ACTIVE` debe permanecer no-op ante reconcile. No volver a cobrar para resolver un `FAILED` con pago confirmado.
- Documentar UUID, correlation key y código sanitizado de fallo; nunca adjuntar tokens, contraseñas, secretos HMAC ni payloads completos del provider.
