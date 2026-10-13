# ENFA-02 — Cross-tenant local provider quoting

## Arquitectura

`CotizacionPublicaController::getAvailableOptions()` delega a
`B2cQuoteOptionsService`. El agregador consulta `ZigoProviderRateService`
sin modificar su implementación y aplica el mismo `buildPricedOption()` del
controlador a las opciones nacionales. Después agrega las opciones de
`TenantProviderQuoteSource`. La fuente local utiliza `ProviderConnectionService`,
`LocalProviderQuoteEvaluator` y `LocalProviderCommercialPricing`.

No se modifica B2cXpertaQuoteFlowService, ProviderStrategyResolver,
ZigoProviderRateService, Xperta/Estafeta, Mercado Pago ni cancelación.
No se agregan rutas ni tablas. No se crean o precargan tenants o conexiones.

## Consumer y ownership

El consumer se obtiene del TenantContext existente. Si el request corporativo
no tiene contexto, se busca su host normalizado en network_tenant_domains,
únicamente con dominio verified y tenant active. Esta lectura no llama al
TenantDomainResolver ni cambia TenantContext. Sin contexto o mapeo verificado,
la fuente local devuelve vacío; el cotizador nacional continúa normalmente.
No se acepta tenant_id/consumer_tenant_id/provider_tenant_id del navegador.

El provider se obtiene de la relación persistida, nunca de nombres visibles.
Servicios, zonas, CP, reglas de paquete y tarifas conservan su tenant propietario.
No se copian datos al consumer ni se crean tablas de cobertura.

## Algoritmo de elegibilidad

1. Conexión activa y vigente para LOCAL_SHIPPING; consumer y provider activos.
2. Lista explícita de servicios autorizados mediante ProviderConnectionService.
3. Servicio del provider activo, publicado y vigente.
4. Zona de origen del servicio activa y del provider; CP origen con fila del
   mismo provider, active y vigente. Repetir para la zona/CP destino del servicio.
5. Paquete caja/sobre con peso positivo y dimensiones positivas para caja.
   Regla activa del mismo provider y del servicio; alternativamente regla general
   del provider con service_id nulo. Se prefiere la específica. Sin regla se omite.
6. PackageValidator existente comprueba peso/dimensiones; también se comprueban
   los límites propios del servicio. Nunca se toma una regla de otro tenant.
7. Cargar únicamente tarifas del mismo provider y servicio, activas, vigentes,
   con intervalo válido y package_type compatible. Se reutiliza LocalPricingEngine.
   Los extremos de vigencia se evalúan con inicio inclusivo y vencimiento exclusivo.
8. Aplicar configuración comercial explícita de ese consumer/provider.
9. Normalizar y agregar la opción. Cualquier condición fallida omite la opción.

El cotizador por CP admite FLAT y PACKAGE_FLAT. BASE_PLUS_OVERAGE y
DISTANCE_TIERS_OVERAGE se omiten porque no existe distancia vial confiable
sin direcciones completas. No se inventan distancias ni se recibe distancia del
navegador. Monedas diferentes a MXN se omiten porque el contrato de las vistas
actuales usa MXN; no se inventa conversión. Solicitudes con seguro también se
omiten para la fuente local, ya que no hay un contrato de seguro local configurado.

## Pricing comercial

Se revisaron ZigoCommercialQuoteService y ZigoPricingService: el flujo nacional
mantiene su comportamiento. La fuente local utiliza explícitamente la tabla
genérica zigo_pricing_rules sin recurrir a su default ESTAFETA, a tarifas de
contrato nacionales, ajustes globales ni descuentos de clientes sin scope local.

La clave configurada en carrier para la relación se obtiene mediante:

```php
app(LocalProviderCommercialPricing::class)->carrierKey($consumer, $provider);
// LOCAL_ + primeros 40 caracteres SHA256(consumer.uuid + '|' + provider.uuid),
// en mayúsculas. Clave estable por relación direccional, no por nombre visible.
```

La regla debe tener esa carrier exacta, active=true, plan=null,
customer_segment anonymous o b2c según el request y package_type caja/sobre/all.
Se prefiere el tipo exacto y luego id para resolución determinista. Reglas para
un consumer no afectan otro consumer conectado al mismo provider.

El importe de LocalPricingEngine (amount de local_shipping_pricing_rules y
eventuales componentes soportados) es provider cost. No se usa el base_price
del catálogo como precio comercial ni como fallback a una tarifa inexistente.
El precio cliente es costo + porcentaje de margen + cuota fija, sujeto al mínimo
de la regla. Se usa BigDecimal y redondeo monetario HALF_UP. Margen o cuota
negativos y costo no positivo ocultan la opción.

Sin regla explícita aplicable: no se publica la opción. No hay precios o márgenes
hardcodeados, fallback ESTAFETA, ajustes/descuentos nacionales, suma automática
de IVA ni otros cargos. Esta fase reproduce la fórmula de margen simple del
pricing actual; el tratamiento fiscal/desglose definitivo deberá definirse antes
de contratar. Las fixtures de pruebas no son configuración comercial productiva.

## Normalización y privacidad

Contrato interno compatible con las claves usadas por las vistas:

- provider=tenant_provider, provider_source=tenant_provider_local,
  capability=LOCAL_SHIPPING y carrier con identidad comercial estable.
- option_key: hash de consumer UUID/provider UUID/service ID; distingue servicios
  con igual code/nombre. Es identidad efímera de opción, no token de contratación.
- logistico y servicio: nombres visibles; service_code: código del provider.
- base_price/provider_cost/precio_base: costo operativo interno.
- precio/commercial_price: precio cliente; currency; entrega/SLA.
- rate_source=local_shipping_pricing_rules y metadatos visuales compatibles.
- _tenant_provider: IDs internos de consumer/provider/service/tarifa/regla
  comercial, estrategia y margen. No se serializa al navegador.

Las vistas renderizan solamente nombres, plazo y precio comercial. No se envían
IDs internos, costo ni margen en HTML, inputs o data attributes. No hay endpoint
JSON nuevo. Un futuro endpoint deberá usar una lista explícita de campos públicos
y nunca serializar el array interno completo.

## Aislamiento y errores

La fuente nacional puede fallar y dejar su mensaje rate_error existente; la
evaluación local continúa. Un error de pricing de una opción nacional no evita
evaluar las demás ni la fuente local. Sin conexiones, las opciones nacionales
válidas conservan la misma forma y el mismo pipeline de pricing.

La fuente local aísla errores de consulta inicial, por provider y por servicio.
Un provider defectuoso no elimina las opciones de otro provider ni las nacionales.
Falta de cobertura/regla, límites o tarifa no aplicable son simplemente ausencia
de opción. Logs nuevos contienen sólo clase de excepción e IDs internos cuando
son útiles, nunca mensajes completos, payloads, direcciones, CP o secretos.
Los logs internos nacionales existentes permanecen intactos.

## Ausencia de efectos operativos

No se utiliza TenantB2cQuoteService::quote(), pues crea snapshots y operaciones.
Se reutilizan únicamente PackageValidator y LocalPricingEngine, ambos evaluadores.
La fuente local sólo lee tablas: no crea TenantOperation, LocalShipment,
LocalShippingQuoteSnapshot, guía, pedido provider, driver, tracking, GPS ni POD.
No altera TenantContext, memberships ni datos del provider.

Los efectos del cotizador nacional ya existentes (por ejemplo metadata de
B2cCotizacion del flujo Xperta) siguen perteneciendo a su pipeline original.
La prueba local verifica cero INSERT/UPDATE/DELETE/REPLACE durante evaluación y
tablas operativas/snapshots vacías después de cotizar.

## Preparación ENFA-03

La UI muestra las opciones locales como Sólo cotización, con selección desactivada.
findSelectedOption también las descarta server-side, impidiendo que nombres visibles
entren al checkout/guía nacional. No existe contratación durable en esta fase.
Los botones y capacidades generales de cancelación existentes no se modifican.

ENFA-03 debe definir identidad/token firmado y snapshot durable consumer/provider,
revalidación de autorización/tarifa/paquete/cobertura, expiración e idempotencia,
precio de venta/costo/margen/fiscalidad y el flujo de contratación independiente
de la selección nacional por nombres. También debe decidir cómo obtener distancia
con direcciones completas y el contrato de seguro/monedas adicionales.

## Validación

NetworkTenantProviderQuotingTest cubre los 23 requisitos mínimos, además de
vigencias, PACKAGE_FLAT, scope comercial por consumer, resolución verificada del
host, exclusiones seguras, cero escrituras y bloqueo server-side de selección.
Pruebas locales SQLite :memory:; no se accede a STAGE/PRD ni se aplica una migración
externa. Los tests Xperta usan fixtures/mocks HTTP; no se realizan probes reales.

Se detectó un fallo preexistente en NetworkTenantB2cTest:
test_operation_shipment_guide_and_tracking_are_tenant_isolated_and_public_tracking_is_private
espera el texto TENANT ADMIN en una vista de tracking. Se reprodujo con los cuatro
archivos modificados restaurados temporalmente al commit base 551ac883 y después
se recuperaron los cambios ENFA-02. No se modifica ese test ni su dominio.

Resultado final (PHP 8.2.12 / PHPUnit 11.5.56):

- Nuevas pruebas ENFA-02: 33 pruebas, 69 assertions, OK.
- Corrida combinada final: 152 pruebas, 836 assertions; 151 pasaron y sólo
  falló el test preexistente de tracking anterior. Incluye NetworkProviderConnection,
  NetworkLocalShipping, NetworkTenantB2c, cotizador público, selección Xperta,
  flujo/contrato Xperta, strategy/provider quoting, pricing comercial, UI responsive
  y motor logístico local.
- Un aviso preexistente de PHPUnit: metadata en doc-comment de
  TenantLogisticsEngineTest::test_distance_tiers_and_overage.
- Syntax checks de los seis archivos PHP involucrados: OK. Pint aplicado
  únicamente a los cinco archivos PHP nuevos. git diff --check: OK.

Comando de regresión final:

```powershell
php vendor/phpunit/phpunit/phpunit --filter 'Network(TenantProviderQuoting|ProviderConnection|LocalShipping|TenantB2c)Test|B2c(XpertaQuoteUi|QuoteCheckoutAuth|XpertaStageQuoteFlow|XpertaSelectionPersistence|CommercialBreakdownSnapshot|ResponsiveUi|LandingMobileCssContract)Test|ShippingStrategyIntegrationTest|ZigoCommercialPricingEngineTest|Xperta(PostmanContract|ForbiddenDiagnostic)Test|TenantLogisticsEngineTest' --display-phpunit-deprecations
```

APP_ENV=testing, DB_CONNECTION=sqlite, DB_DATABASE=:memory: y una APP_KEY de
prueba se definieron sólo para los procesos de pruebas, sin editar .env.
