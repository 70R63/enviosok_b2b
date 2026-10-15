# ENFA-11 — implementación local y corrección de seis P2

Base: feature/enfa-local-provider-network, HEAD 023d3acb4cadab454d21bfa6e9136930b4039c78. Sin commit, push, despliegue, acceso a STAGE/PRD, migraciones de aplicación, comandos de caché, CANCEL, refunds ni cambios de ledger/payable. Las pruebas usan SQLite :memory:, fixtures y HTTP simulado.

## P2-1: validación temporal antes del pago

ProviderPickupPreference::normalize() valida mode ASAP|WINDOW, fecha Y-m-d y horas H:i. ASAP fuerza fecha/horas a null. WINDOW requiere inicio < fin, fecha no pasada y fin estrictamente posterior a now(config('app.timezone')). No admite una ventana que cruce medianoche. No se acepta una zona horaria del browser.

validateForPayment() centraliza la revalidación del pickup_request almacenado para provider local todavía impago. Se llama antes de mostrar confirmación, iniciar GET pago, reservar en GET pago, crear una preferencia Mercado Pago y pagar con saldo (también dentro del lock de la cotización antes de buscar/debitar saldo). Errores devuelven al checkout. Si ya tiene pago acreditado o guía, la revalidación no invalida la compra. Callbacks/recovery/fulfillment no revalidan ventanas acreditadas.

La UI sólo decide destino. La autorización de fulfillment sigue en el servicio con verificación de pago, locks y claves únicas existentes. BFCache local recarga formularios restaurados; no se hizo sesión manual de navegador ni pago real.

## Rutas y tracking público

Sólo en config('zigo_domains.portals.b2c.host'), antes de las rutas globales tenant:

| Método | URI | Nombre |
| --- | --- | --- |
| GET/HEAD | /rastreo | b2c.rastreo |
| POST | /rastreo | b2c.rastreo.buscar |
| GET/HEAD | /rastrear | b2c.rastrear |
| GET/HEAD | /tracking | b2c.tracking |

GET admite tracking_number. Las rutas tenant white-label permanecen. El tracking local resuelve bridge del consumer del host y devuelve sólo tracking_number/operator/service/status_label/timeline[{label,occurred_at}], sin modelos, contactos, direcciones, IDs internos, costos, conductor, actor, evidencia, metadata ni enlaces privados. El nacional conserva su rama histórica, incluyendo su presentación anterior de ID de cotización y CP/colonia; no se afirma una nueva política de privacidad para esa rama.

## P2-2: QR por consumer

ProviderConsumerResolver::corporate() comparte la misma resolución UUID/slug configurada con current(), sin hardcodear identidad ZIGO. Sólo un bridge cuyo consumer_tenant_id corresponda a ese consumer activo usa https://<B2C_HOST>/rastreo?tracking_number=<TRACKING>. Para otros consumers se conserva la URL autorizada anterior del detalle de su contrato. No se relaja ownership ni se cambian QR nacionales.

## P2-3: identidad pública e historia

ProviderPresentation usa branding.brand_name y, si es aceptable, tenant.name, descartando coincidencias con tenant.slug de forma case-insensitive y con trim. Fallback: Operador logístico. No se hardcodea ENFA. Los casos entregasenfa/Entregasenfa/ENTREGASENFA/espacios no se autorizan como identidad por capitalización.

Nuevas guías provider local siempre incluyen provider_identity.display_name, provider_identity.logo_path y service_name, además de branding.brand_name del consumer. El logo del provider debe ser público, de su tenant y PNG/JPEG/WEBP válido; se copia al guide-assets con hash. HTML/PDF usan esos assets históricos y la identidad del snapshot. El lector no consulta branding mutable del operador.

Snapshots históricos SIN provider_identity usan SIEMPRE Operador logístico; el campo provider antiguo no se interpreta como nombre público. No se reescribe ninguna guía histórica ni la cotización 147. La presentación de nuevas guías separa consumer, GUÍA DE ENVÍO, operador, servicio y Generado por ZIGO Platform. El nombre ENFA ENTREGAS URBANAS depende del branding público configurado, no de código.

## Esquemas de recolección (sin nuevas columnas)

Se mergea sólo pickup_request en guia_request_snapshot para tenant_provider_local. No se reemplazan payloads nacionales.

~~~json
{"pickup_request":{"mode":"WINDOW","requested_date":"2026-10-16","window_start":"09:00","window_end":"13:00","timezone":"America/Mexico_City","requested_at":"2026-10-15T12:00:00-06:00"}}
~~~

Después del pago se congela y copia a TenantOperation.metadata.pickup_request. ASAP usa requested_date/window_start/window_end null. pickup_requested_at sigue siendo creación/envío de solicitud logística, nunca cita.

ProviderPickupConfirmationService exige tenant provider activo, membership owner/admin/operator, bridge suyo y ACCEPTED. Primera confirmación sólo en READY_FOR_PICKUP. Registra PICKUP_CONFIRMED append-only y mergea TenantOperation.metadata.pickup_confirmation:

~~~json
{"mode":"WINDOW","confirmed_date":"2026-10-16","window_start":"09:00","window_end":"13:00","timezone":"America/Mexico_City"}
~~~

ASAP deja fecha/horas en null. Actor/occurred_at van en los campos del evento, no en datos públicos. Mismo payload devuelve el evento original (orden de claves JSON irrelevante); otro payload se rechaza sin sobrescribirlo. No se implementa reprogramación ni se cambia LocalShipment.status.

ACCEPTED sólo acepta la solicitud; no confirma ventana ni crea driver. REJECTED es atención comercial/operativa, sin CANCEL/refund/reversión. Dispatch conserva ACCEPTED como requisito; la entrega conserva POD obligatorio.

## P2-4: auditoría de lectores de eventos

El evento operativo mantiene status READY_FOR_PICKUP por compatibilidad del schema, pero su significado es event_code PICKUP_CONFIRMED. No existe un nuevo estado físico.

| Lector | Tratamiento |
| --- | --- |
| LocalTrackingEvent | isPhysical()/scopePhysical(): event_code=status, o CREATED/SHIPMENT_CREATED |
| LocalTrackingService::read | Sólo eventos físicos |
| LocalTrackingService::transition repetida | Busca evento físico y reordena por id; no devuelve PICKUP_CONFIRMED |
| Tenant API Hub TrackingController | Timeline exclusivamente físico |
| ApiWebhookPublisher | Ignora eventos operativos; no emite otro shipment.ready_for_pickup |
| TenantB2cController::track | Conserva event_code en su proyección de timeline |
| ShipmentStatusPresenter + tenant/tracking/_timeline | Etiqueta PICKUP_CONFIRMED como Recolección confirmada |
| Admin/customer tracking y detalle customer | Consumen el partial anterior con eventos del shipment; heredan la distinción |
| TenantDriverController / dispatch | La fecha de solicitud proviene sólo de eventos físicos READY_FOR_PICKUP |
| ProviderTrackingProjection | Ya distinguía confirmación por event_code y whitelist física; se conserva y prueba |
| Provider inbox | Ya distingue PICKUP_CONFIRMED; se conserva y prueba |
| B2C fulfillment | Consume la proyección provider, con hitos reales |
| Tracking header y customer tracking-index | Sólo usan timestamp de última actualización y estado actual; no convierten el evento en estado |
| LocalShipmentService | Escritor de SHIPMENT_CREATED; no se cambia |
| ProviderPayableService / ProviderPayableReconcile | Filtran DELIVERED/DELIVERY_FAILED; READY_FOR_PICKUP/PICKUP_CONFIRMED no introduce estado financiero; no se modifican |
| AppServiceProvider | Hooks existentes auditados, sin cambios; el publisher físico filtra el evento operativo |

Se probó empate de timestamps entre transición física y confirmación: repetir READY_FOR_PICKUP devuelve el evento físico original, no crea otro y no devuelve PICKUP_CONFIRMED. También se probó que el webhook y API físicos no duplican el hito.

## P2-5: duplicación local

resetLocalPurchaseState() sólo puede operar sobre una copia NUEVA no persistida. Limpia campos derivados de compra, guía, notificación y selección; unsetRelations evita copiar asociaciones. No copia filas relacionadas de contrato, fulfillment, recovery ni facturación. Direcciones, referencias de domicilio, paquete, contenido y opciones del usuario permanecen; pickup_request no se hereda. El original no cambia.

Campos auditados/reset de la copia local:

~~~text
payment_id
payment_status
payment_external_reference
payment_collection_id
payment_verification_status
payment_verification_source
payment_verification_error
payment_verification_attempted_at
payment_verified_at
payment_verified_amount
payment_verified_currency
payment_verified_external_reference
payment_verification_payload
guia_id
tracking_number
documento
guia_estatus
guia_provider_reference
guia_provider_request_number
guia_generation_attempts
guia_generation_started_at
guia_last_attempt_at
guia_generated_at
guia_recovered_at
guia_last_error_code
guia_last_error_message
guia_request_snapshot
guia_response_snapshot
guia_provider_shipment_id
guia_provider_status
guia_label_format
guia_request_fingerprint
guia_generated_by
guide_processing_email_sent_at
guide_generated_email_sent_at
guide_pending_email_sent_at
guide_pdf_recovered_email_sent_at
provider_confirmation_claimed_at
provider_fulfillment_attempted_at
provider
carrier
service_code
quote_source
provider_quote_reference
quote_request_fingerprint
quote_correlation_id
quote_expires_at
logistico
servicio
precio
precio_sin_seguro
provider_base_price
provider_extended_area_price
provider_subtotal
provider_total
zigo_margin_percentage
zigo_fixed_fee
zigo_margin_amount
zigo_adjustment_type
zigo_adjustment_value
zigo_adjustment_amount
zigo_discount_type
zigo_discount_value
zigo_discount_amount
zigo_final_price
zigo_profit_amount
zigo_customer_segment
zigo_pricing_rule_id
zigo_pricing_adjustment_id
zigo_client_pricing_rule_id
~~~

Todos quedan null cuando existen en el modelo copiado, salvo guia_generation_attempts=0 y los valores iniciales que establece duplicarEnvioB2c: guia_estatus=SIN_GUIA, precio=0, estatus=DIRECCION_CAPTURADA. Los emails/claims incluyen los seis campos mínimos pedidos. La prueba prepara una original pagada con guía, marcas de correo, claims, errores/intentos, contrato y fulfillment; comprueba conservación del original y notificación nueva de la copia tras su propia compra.

## P2-6 y alcance nacional

Mis envíos eager-load providerContract.fulfillment.shipment. Para provider local Descargar guía sólo aparece cuando existen fulfillment Y shipment; no depende de documento. Ver detalle mantiene la ruta del contrato. PDF conserva autorización.

PrivateCheckoutResponse sólo aplica private,no-store si la cotización es tenant_provider_local. El nuevo lock del POST checkout sólo se aplica a ese provider. El reset de guia_request_snapshot al duplicar se limita a la copia local; nacional conserva su comportamiento previo. No se modifican payloads, verificación ni generación de guía Estafeta/Xperta.

## Correo y límites

Se conserva el claim existente de correo de compra/guía y se usa identidad snapshoteada. No hay nuevo sistema de correo ni notificación independiente por aceptación/confirmación. No se modifican ledger/payable, refund, CANCEL, ni código AI/Bots.

No se realizó pago/correo real, inspección visual del PDF ni sesión BFCache de navegador. El PDF se valida como PDF y por su URL visible en streams; los dos consumidores del renderer reciben la misma URL para texto y QR, pero el test no decodifica la imagen QR.

## Inventario final

Modificados:

- app/Domain/ApiHub/ApiWebhookPublisher.php
- app/Domain/Network/Providers/Models/ProviderContract.php
- app/Domain/Network/Providers/Models/ProviderFulfillment.php
- app/Domain/Network/Providers/ProviderConsumerResolver.php
- app/Domain/Network/Providers/ProviderFulfillmentService.php
- app/Domain/Network/Providers/ProviderPresentation.php
- app/Domain/Network/Providers/ProviderTrackingProjection.php
- app/Domain/Shipping/Local/LocalGuidePdfRenderer.php
- app/Domain/Shipping/Local/LocalShipmentService.php
- app/Domain/Shipping/Local/LocalTrackingService.php
- app/Domain/Shipping/Local/Models/LocalTrackingEvent.php
- app/Http/Controllers/API/Hub/V1/TrackingController.php
- app/Http/Controllers/B2C/CotizacionPublicaController.php
- app/Http/Controllers/B2C/ProviderFulfillmentController.php
- app/Http/Controllers/Tenant/ProviderFulfillmentInboxController.php
- app/Http/Controllers/Tenant/TenantB2cController.php
- app/Http/Controllers/Tenant/TenantDriverController.php
- app/Models/B2cCotizacion.php
- app/Services/Shipping/GuideRecoveryService.php
- app/Support/Presentation/ShipmentStatusPresenter.php
- resources/views/b2c/checkout.blade.php
- resources/views/b2c/confirmar-envio.blade.php
- resources/views/b2c/mis-envios.blade.php
- resources/views/b2c/provider-fulfillment.blade.php
- resources/views/email/provider-fulfillment-confirmed.blade.php
- resources/views/tenant/admin/provider-request.blade.php
- resources/views/tenant/tracking/_timeline.blade.php
- routes/network.php
- routes/web.php
- tests/Feature/HistoricalB2cProviderNetworkTest.php
- tests/Feature/NetworkProviderAcceptanceTest.php
- tests/Feature/NetworkProviderFulfillmentTest.php
- tests/Feature/NetworkProviderTrackingTest.php

Nuevos (sin staging):

- app/Domain/Network/Providers/ProviderPickupConfirmationService.php
- app/Domain/Network/Providers/ProviderPickupPreference.php
- app/Http/Middleware/PrivateCheckoutResponse.php
- docs/ENFA-11-post-purchase-tracking-branding-pickup.md
- resources/views/b2c/partials/post-purchase-nav.blade.php
- resources/views/b2c/partials/revalidate-purchase.blade.php
- resources/views/b2c/provider-tracking-public.blade.php
- tests/Feature/EnfaPostPurchaseTest.php

## Resultados finales de pruebas

Se ejecutó cada filtro individualmente con php artisan test --filter=<nombre> --do-not-cache-result. APP_KEY efímera en el proceso; no se crea/modifica .env. Todos los tests figuran como warnings por .env ausente; permanece además una deprecación previa de metadata doc-comment. No se ocultan advertencias.

| Filtro | Resultado exacto de PHPUnit |
| --- | --- |
| EnfaPostPurchaseTest | 56 warnings (574 assertions) |
| HistoricalB2cProviderNetworkTest | 7 warnings (67 assertions) |
| NetworkProviderFulfillmentTest | 48 warnings (176 assertions) |
| NetworkProviderAcceptanceTest | 38 warnings (117 assertions) |
| NetworkProviderTrackingTest | 41 warnings (145 assertions) |
| B2cQuoteCheckoutAuthTest | 7 warnings (39 assertions) |
| NetworkLocalShippingTest | 8 warnings (40 assertions) |
| DriverLastMileFoundationTest | 2 failed, 37 warnings (394 assertions) |
| EstafetaIntegrationTest | 8 warnings (21 assertions) |
| XpertaGuideFinalTest | 24 warnings (64 assertions) |
| B2cXpertaSelectionPersistenceTest | 1 warning (12 assertions) |
| NetworkTenantB2cTest | 1 failed, 16 warnings (222 assertions) |
| NetworkProviderApiTest | 42 warnings (138 assertions) |
| ZigoApiHubCommercialTest | 8 warnings (46 assertions) |

Los seis filtros obligatorios: 197 tests, 1118 aserciones, cero fallos (código 0 cada uno). Regresión adicional solicitada: 92 tests, 457 aserciones, 1 fallo baseline. Complementarios: NetworkLocalShippingTest 8/40 sin fallos; DriverLastMileFoundationTest 39/394 con 2 fallos baseline; ZigoApiHubCommercialTest 8/46 sin fallos. Total no duplicado: 344 tests, 2055 aserciones, 3 fallos baseline y ningún fallo nuevo.

Baseline separado, sin tocar tests/vistas para ocultarlo:

- NetworkTenantB2cTest:413 espera TENANT ADMIN. Reproducido contra HEAD original en revisión previa; vuelve a fallar igual.
- DriverLastMileFoundationTest:216 espera DELIVERED y :240 espera READY FOR PICKUP en HTML traducido. Se ejecutó la clase completa desde copia temporal de HEAD 023d3acb: 39 tests, 394 aserciones, los mismos 2 fallos. Clases App/Tests cargadas desde copia base y vendor original compartido.

Los 16 casos nuevos de esta corrección se agregan a EnfaPostPurchaseTest (40 → 56). Cubren expiración el mismo día y al día siguiente en GET/saldo/Mercado Pago, compra VERIFIED vencida, variantes de slug, otro consumer, repetición física con timestamp empatado, etiquetas tenant, copia/notificación, ausencia/presencia de descarga, alcance nacional y webhook/timeline API exclusivamente físicos.

## Validación y revisión crítica final focalizada

- PHP -l: 40 PHP/Blade tocados, todos sin errores.
- route:list --json: código 0, sin cachés; las cuatro rutas B2C conservan dominio exacto y las tenant siguen presentes. Sin nuevos nombres duplicados; api. repetido tres veces es previo.
- git diff --check: código 0.
- P2-1: fin de ventana revalidado antes de iniciar pago impago y dentro del lock de saldo; acreditadas no se invalidan retroactivamente.
- P2-2: QR corporativo condicionado por consumer configurado; otros conservan URL autorizada anterior.
- P2-3: sin inferencia de identidad pública histórica ni excepciones por mayúsculas del slug.
- P2-4: lectores operativos distinguen confirmación; lectores físicos y webhooks la excluyen. No se agregan estados físicos.
- P2-5: reset sólo sobre copia local nueva, original intacto, email/claims limpiados y propia notificación probada.
- P2-6: descarga exige fulfillment + shipment, con eager loading, sin depender de documento.
- Conclusión focalizada: PASS para commit, con los 3 fallos baseline separados y los límites manuales de PDF/BFCache indicados. Sin nuevos P2 encontrados en esa revisión.
- No se hizo commit/push, staging, despliegue, migrations de aplicación ni cachés. HEAD permanece en la base indicada.

## git diff --stat final (sin contar archivos nuevos no agregados al índice)

~~~text
 app/Domain/ApiHub/ApiWebhookPublisher.php          |   2 +-
 .../Network/Providers/Models/ProviderContract.php  |   5 +
 .../Providers/Models/ProviderFulfillment.php       |   2 +-
 .../Network/Providers/ProviderConsumerResolver.php |  19 +--
 .../Providers/ProviderFulfillmentService.php       |   3 +-
 .../Network/Providers/ProviderPresentation.php     |  31 ++++-
 .../Providers/ProviderTrackingProjection.php       |  55 ++++++--
 .../Shipping/Local/LocalGuidePdfRenderer.php       |  17 ++-
 app/Domain/Shipping/Local/LocalShipmentService.php |   6 +-
 app/Domain/Shipping/Local/LocalTrackingService.php |   4 +-
 .../Shipping/Local/Models/LocalTrackingEvent.php   |  10 +-
 .../Controllers/API/Hub/V1/TrackingController.php  |   2 +-
 .../B2C/CotizacionPublicaController.php            | 153 ++++++++++++++++++---
 .../B2C/ProviderFulfillmentController.php          |   4 +
 .../Tenant/ProviderFulfillmentInboxController.php  |   8 ++
 .../Controllers/Tenant/TenantB2cController.php     |   1 +
 .../Controllers/Tenant/TenantDriverController.php  |   2 +-
 app/Models/B2cCotizacion.php                       |  42 ++++++
 app/Services/Shipping/GuideRecoveryService.php     |   2 +-
 .../Presentation/ShipmentStatusPresenter.php       |   6 +
 resources/views/b2c/checkout.blade.php             |  23 +++-
 resources/views/b2c/confirmar-envio.blade.php      |   4 +
 resources/views/b2c/mis-envios.blade.php           |  15 +-
 resources/views/b2c/provider-fulfillment.blade.php |  19 +--
 .../email/provider-fulfillment-confirmed.blade.php |   5 +-
 .../views/tenant/admin/provider-request.blade.php  |  25 +++-
 .../views/tenant/tracking/_timeline.blade.php      |   2 +-
 routes/network.php                                 |   1 +
 routes/web.php                                     |  30 ++--
 tests/Feature/HistoricalB2cProviderNetworkTest.php |   2 +-
 tests/Feature/NetworkProviderAcceptanceTest.php    |   3 +-
 tests/Feature/NetworkProviderFulfillmentTest.php   |   6 +-
 tests/Feature/NetworkProviderTrackingTest.php      |   2 +-
 33 files changed, 413 insertions(+), 98 deletions(-)
~~~

Status final: 33 archivos modificados y 8 nuevos; índice sin cambios. Inventario completo arriba.
