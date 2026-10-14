# ENFA-04: fulfillment provider y recolección

## Confirmación comercial existente

Seleccionar una oferta no crea operación, shipment ni pickup. CHECKOUT_COMPLETO
representa direcciones capturadas, no compra confirmada. El límite comercial real
es PaymentVerificationService:

- verifyPayload: pago aprobado, referencia/moneda/importe verificados server-side;
- markBalancePaymentVerified: movimiento COMPRA_GUIA/APLICADO de saldo prepago.

ProviderPaymentObserver observa la persistencia VERIFIED/payment_verified_at,
exclusivamente para tenant_provider_local, y ejecuta después del commit mediante
DB::afterCommit. No se modifican MercadoPagoPaymentClient, PaymentVerificationService,
webhook Mercado Pago ni servicios internos Xperta. Un rollback de pago no ejecuta
fulfillment. Un fallo operativo posterior no revierte un pago real ya aprobado.

El servicio vuelve a leer/bloquear la cotización y usa isEligibleForGuide existente
para verificar la evidencia, incluido el movimiento de saldo y adeudos agregados.
No basta payment_status=approved ni un precio enviado por el navegador.

## Ownership y bridge durable

network_provider_fulfillments vincula ProviderContract/ProviderQuoteSnapshot con
TenantOperation y LocalShipment. Incluye UUID, consumer/provider, confirmación
comercial, pickup_requested_at y timestamps. Es inmutable en Eloquent, incluidos
métodos sin eventos y escrituras masivas mediante ImmutableProviderBuilder.

FK compuestas garantizan:

- contrato corresponde al snapshot;
- snapshot corresponde al consumer/provider;
- operación pertenece al provider;
- shipment corresponde a esa misma operación y provider.

UNIQUE por contrato, operación, shipment y UUID evita vínculos equivalentes.
Campos de identidad y fechas de confirmación son NOT NULL. No hay triggers ni
dependencia del permiso TRIGGER. Se agregan índices únicos de referencia en las
cuatro tablas existentes. b2c_cotizaciones incorpora referencias opcionales por
dirección y timestamps de intento/claim de notificación; no se copian servicios,
cobertura ni se crea otro tenant. Estos campos de recuperación no mutan economía.

Consumer conserva cliente, cotización, pago, venta, IVA y margen. Provider conserva
operación, shipment, servicio y eventos locales. La operación no recibe un perfil
cliente consumer ni creator user consumer; sólo los contactos necesarios del envío.
No se conceden memberships ni se cambia TenantContext.

## Algoritmo y transacción

1. Bloquear B2cCotizacion, ProviderContract y ProviderQuoteSnapshot; validar identidad,
   quote_source, capability, fingerprint y aceptación dentro de la vigencia original.
   Bloquear tenants por ID y autoridad de suscripción antes de lecturas de pago.
2. Exigir pago verificado y acreditado, moneda y precio contractual congelados.
3. Devolver el bridge existente. Una revocación posterior no elimina ni reemplaza
   un envío ya creado. Si hay operación previa sin bridge, exigir reparación.
4. Bloquear conexión, grant y servicio. Revalidar conexión
   vigente, tenants activos, servicio publicado/autorizado, cobertura provider y
   límites de paquete. No recalcular tarifa ni margen.
5. Validar direcciones completas actuales del checkout, nunca consultar después
   direcciones mutables de perfil. Congelar nombre/contacto, teléfono, email,
   calle, exterior/interior, colonia, CP, municipio, estado, país y referencias.
   B2C no tiene coordenadas de checkout persistidas; no se inventan ni geocodifican.
6. Congelar paquete contractual, peso, dimensiones en cm, contenido y valor declarado.
7. Con la autoridad provider ya bloqueada, exigir suscripción operativa y
   entitlement LOCAL_SHIPPING. Confirmar mediante TenantOperationService, reutilizando
   el registro de consumo de Network con tenant-operation:<UUID>.
8. Reutilizar LocalShipmentService con TenantOperation del mismo provider.
9. Reutilizar LocalTrackingService para CREATED -> READY_FOR_PICKUP, crear bridge
   y registrar folio/guía en B2C. Todo lo operativo y el consumo son atómicos.

La operación usa channel=b2c, source_type=provider_contract, source_id del contrato,
status=confirmed y provider=ZIGO_LOCAL. ZIGO_LOCAL es la clave técnica existente del
motor local, no identifica un tenant ni determina branding. El servicio/tenant se
derivan de la autorización contractual. No significa aceptación por el provider.

La expiración posterior de la oferta aceptada no destruye el contrato ni cambia
importes. Cambios de tarifa/margen no lo reprician. Cambios críticos de autorización,
cobertura, servicio, paquete o capacidad operativa antes del primer fulfillment
impiden crear logística y dejan la compra pendiente de revisión.

## Pickup automático y separación de estados

Una compra local confirmada implica una única solicitud de recolección en origen,
sin otra compra ni portal provider. READY_FOR_PICKUP y su evento local representan
esa solicitud. pickup_requested_at permite identificarla durablemente.
No hay una tabla paralela de tracking ni estados ACCEPTED/REJECTED en la línea
logística. No se asigna driver, provisionan opciones POD/GPS ni despacha al exterior.
No se agrega ni cambia cancelación existente.

## Idempotencia, fallos y recuperación

El lock de cotización serializa confirmaciones concurrentes; UNIQUE protege el
bridge y LocalShipment conserva UNIQUE(tenant_operation_id). La transacción reintenta
hasta cinco veces ante deadlocks. Reintentos devuelven el mismo fulfillment y no
duplican eventos READY_FOR_PICKUP ni consumo.

Observer y recuperación aíslan fallos por compra. Logs contienen quote ID y clase
de excepción, sin direcciones, secretos, costos ni margen. Una compra PAGADA sin
bridge es el backlog durable; UI informa pendiente y que no debe pagarse otra vez.

Comando interno:

```text
php artisan network:provider-fulfillment:retry --limit=100
php artisan network:provider-fulfillment:retry --quote=<B2C-ID>
```

El barrido incluye compras verificadas sin bridge y fulfillments con correo pendiente.
Se agenda cada minuto con withoutOverlapping(10) mediante el scheduler existente.
Debe estar activo el cron habitual de Laravel; no se inició aquí ningún scheduler.
provider_fulfillment_attempted_at ordena lotes por el intento más antiguo, evitando
que compras bloqueadas impidan recuperar otras. --quote permite recuperación puntual.
No cobra ni hace dispatch externo. El backlog persiste aunque muera el observer.
Crashes durante fulfillment revierten toda su transacción; después del commit se
reutiliza el bridge y se procesa sólo la notificación pendiente.

## Economía, guía y UI

ProviderQuoteSnapshot sigue siendo el contrato económico de ENFA-03: costo provider
neto, subtotal comercial neto, IVA, total cliente bruto y margen neto comparable.
No se recalculan ni mutan. pricing_snapshot operativo lleva únicamente costo provider
neto, moneda y copia de tarifa; no lleva precio consumidor, IVA de venta ni margen.

Se reutiliza LocalGuidePdfRenderer. guía conserva marca consumer, nombre del servicio
y display provider obtenidos de Tenant/branding. El logo consumer se difiere para
no debilitar el aislamiento de paths del renderer. PDF contiene sólo información
logística. No se guarda en una URL de storage pública; se entrega dinámicamente
mediante endpoint autorizado y Cache-Control: private, no-store.

GET /b2c/envio-local/{contract:uuid} muestra folio, provider visible, servicio, pickup,
destino, estado local y descarga. Se verifica consumer efectivo y ownership
usuario/sesión mediante el mismo guard de ENFA-03. El QR apunta a ese resumen
protegido; no simula tracking público. Seguimiento detallado queda para ENFA-05.
No expone provider/connection/operation IDs, costo ni margen y no incluye Cancelar.
Un checkout local ya pagado redirige al resumen sin modificar las direcciones.
Nacional conserva sus endpoints, vistas y comportamiento.

## Confirmación por correo

Se reutilizan Laravel Mail, GuideRecoveryService::guideAvailable y el timestamp
guide_generated_email_sent_at de B2C. Sólo la rama local usa
ProviderFulfillmentConfirmedMail y una plantilla con origen, destino, folio y
provider. El webhook genérico recibe esa misma rama y no emite correo/enlace de
recuperación nacional para una guía local.

La URL usa dominio consumer verificado, con fallback al portal B2C configurado,
nunca el host del webhook. Debe configurarse correctamente ese dominio/portal.
Clientes con cuenta acceden por UUID contractual y su autorización original.
Guests reciben un token aleatorio de 80 caracteres mediante la infraestructura
B2cGuideRecoveryToken existente: sólo SHA-256 se persiste; TTL y máximo de intentos
se reutilizan. El token sólo permite resumen/PDF en el consumer efectivo correcto,
sin reclamar cotización, cambiar sesión/TenantContext, memberships o crear logística.
Los endpoints de recuperación existentes delegan únicamente la rama provider local.
El formulario existente permite recuperar acceso usando folio público y correo del
comprador, restringido al consumer actual. Nunca usa una PK adivinada para locales.
Se conservan throttle, expiración, revocación, auditoría y límites de intentos.
En la rama local, resolve bloquea el token e incrementa intentos atómicamente,
asignando explícitamente expires_at original para impedir expiración implícita
por ON UPDATE en TIMESTAMP legado de MySQL/MariaDB. Validado con ese tipo real
en la instancia MariaDB desechable; la rama nacional de resolve no cambia.

El QR del PDF contiene sólo UUID y requiere ownership; no contiene bearer token.
La respuesta guest usa private/no-store y Referrer-Policy: no-referrer. No se crean
URLs públicas de storage. Los enlaces de correo requieren el dominio consumer
verificado o un portal correctamente configurado; en otro consumer son rechazados.

El claim de correo usa provider_confirmation_claimed_at como lease de diez minutos.
El timestamp guide_generated_email_sent_at se escribe después del envío. Un crash
antes de enviar puede recuperarse al expirar el lease. Un fallo explícito libera
el claim y revoca el token no entregado; no revierte compra ni fulfillment.
No se promete exactly-once SMTP: si el servidor acepta el correo y el proceso muere
antes de guardar sent_at, un reintento puede duplicar ese correo. Resolver ese límite
requiere idempotencia del transporte/outbox y queda documentado para ENFA-05.

## Migración, rollback y compatibilidad

2026_10_13_120000_create_network_provider_fulfillments.php usa Schema/Blueprint,
InnoDB, FK/UNIQUE, strings, DATETIME y timestamps, sin CHECK ni DDL de triggers.
Construcciones compatibles con Laravel 9 y MySQL 5.7/8/MariaDB 10.4. El runtime
ejecutado es Laravel 12/PHP 8.2.12; no se ejecutó en un runtime Laravel 9/MySQL real.

Rollback elimina bridge, índices adicionales y columnas nuevas de referencias/intento/claim; conserva
operaciones/shipments/eventos/consumo y snapshots de direcciones. Con datos reales
requiere respaldo y reparación/restauración de vínculos antes de reactivar recovery.
El guard FULFILLMENT_LINK_REQUIRES_REPAIR evita duplicar operaciones tras ese rollback.
No se aplicó ninguna migración fuera de pruebas locales aisladas.

## Pruebas y ENFA-05

NetworkProviderFulfillmentTest cubre confirmación, pago real verificado/saldo,
after-commit/rollback, ownership, snapshots, referencias, perfil mutable, revocación,
expiración aceptada, conservación económica, FK/UNIQUE, reintentos, fallo parcial,
autorización cross-tenant/sesión, privacidad HTML/PDF/email, no CRM/driver/POD/GPS,
notificación idempotente y rollback de migración.

provider_contract_concurrency.php conserva modo ENFA-03 y agrega modo fulfillment:

```text
php tests/Support/provider_contract_concurrency.php 43318 <datadir-temporal-enfa03-sql> fulfillment
```

Sólo acepta instancia desechable bajo temp, puerto >=30000, y verifica @@datadir
antes de escribir. Usa DB aleatoria y usuario sin TRIGGER; no usa DB de .env.
Dos workers verifican espera real del row lock, un bridge, operación, shipment,
pickup y consumo, precio congelado y up/down/up/down en MariaDB 10.4.32.

ENFA-05 deberá definir aceptación/rechazo provider como workflow separado del
tracking, permisos de administración operativa, dispatch/CRM con autenticación,
idempotencia y aislamiento, asignación driver, tracking público seguro/proyección
consumer, políticas POD y notificaciones/outbox. El bridge
provee identidad durable para correlacionar todo ello. No se implementan aquí
CRM, GPS, webhooks provider, dispatch externo, liquidación ni publicidad.

## Resultados de validación

- ENFA-04: 48 pruebas, 176 assertions, sin errores/fallos.
- Regresión combinada: 312 pruebas, 1787 assertions, 309 pasan. Incluye ENFA-01,
  ENFA-02, ENFA-03, ENFA-04, Network local/B2C, cotización/checkout B2C, pricing,
  Xperta/strategy, UI, recuperación de guías y certificación de pagos con fakes.
- Tres fallos preexistentes, reproducidos en exportación aislada de 1d2f4113:
  NetworkTenantB2cTest por TENANT ADMIN y DriverLastMileFoundationTest por DELIVERED
  y READY FOR PICKUP en vistas traducidas. Ninguno de esos tests/plantillas se modifica.
  Una deprecación previa de PHPUnit por metadata doc-comment en TenantLogisticsEngineTest.
- MariaDB 10.4.32: concurrencia real ENFA-03 y ENFA-04, locks, idempotencia,
  ownership y rollback up/down/up/down correctos con usuario sin TRIGGER.
- Sintaxis PHP y git diff --check correctos. Sólo pruebas locales aisladas;
  ningún acceso STAGE/PRD, envío real de correos o llamada externa CRM/provider.

Comando de regresión, con APP_ENV=testing, DB_CONNECTION=sqlite,
DB_DATABASE=:memory: y APP_KEY de prueba:

```text
php vendor/phpunit/phpunit/phpunit --filter 'Network(ProviderFulfillment|ProviderContractSelection|TenantProviderQuoting|ProviderConnection|LocalShipping|TenantB2c)Test|B2c(XpertaQuoteUi|QuoteCheckoutAuth|XpertaStageQuoteFlow|XpertaSelectionPersistence|CommercialBreakdownSnapshot|ResponsiveUi|LandingMobileCssContract)Test|ShippingStrategyIntegrationTest|ZigoCommercialPricingEngineTest|Xperta(PostmanContract|ForbiddenDiagnostic)Test|TenantLogisticsEngineTest|FinalGuideRecoveryWiringTest|GuestGuideRecoveryClosureTest|StagePaymentCertificationTest|DriverLastMileFoundationTest|ZigoDriverPwaTest' --display-phpunit-deprecations
```

## Revisión final e inventario

Los nueve modificados y doce nuevos iniciales se compararon íntegramente contra
1d2f4113: todos correspondían a ENFA-04. No hubo cambios ajenos ni descartes.
Se añadieron dos modificaciones ENFA-04: scheduler y recuperación guest existentes.
La autoridad de suscripción se bloquea antes de consultas con vista consistente;
la revalidación comprueba estado/vigencia en filas bloqueadas de tenants, conexión
y servicio para evitar aceptar versiones antiguas bajo REPEATABLE READ.

| Estado | Archivo |
| --- | --- |
| Modificado | app/Console/Kernel.php |
| Modificado | app/Domain/Shipping/Local/LocalGuidePdfRenderer.php |
| Modificado | app/Domain/Shipping/Local/LocalShipmentService.php |
| Modificado | app/Http/Controllers/B2C/CotizacionPublicaController.php |
| Modificado | app/Http/Controllers/B2C/GuestGuideRecoveryController.php |
| Modificado | app/Providers/AppServiceProvider.php |
| Modificado | app/Services/Shipping/GuideRecoveryService.php |
| Modificado | resources/views/b2c/checkout.blade.php |
| Modificado | resources/views/b2c/pago-success.blade.php |
| Modificado | routes/web.php |
| Modificado | tests/Support/provider_contract_concurrency.php |
| Nuevo | app/Console/Commands/RetryProviderFulfillment.php |
| Nuevo | app/Domain/Network/Providers/Models/ProviderFulfillment.php |
| Nuevo | app/Domain/Network/Providers/ProviderFulfillmentService.php |
| Nuevo | app/Domain/Network/Providers/ProviderPaymentObserver.php |
| Nuevo | app/Http/Controllers/B2C/ProviderFulfillmentController.php |
| Nuevo | app/Mail/ProviderFulfillmentConfirmedMail.php |
| Nuevo | database/migrations/2026_10_13_120000_create_network_provider_fulfillments.php |
| Nuevo | docs/ENFA-04-provider-fulfillment.md |
| Nuevo | resources/views/b2c/provider-fulfillment.blade.php |
| Nuevo | resources/views/email/provider-fulfillment-confirmed.blade.php |
| Nuevo | tests/Feature/NetworkProviderFulfillmentTest.php |
| Nuevo | tests/Support/ProviderFulfillmentFixture.php |
