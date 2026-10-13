# ENFA-03 — Durable quote selection and contract snapshot

## Arquitectura y elección de persistencia

Se revisaron b2c_cotizaciones, network_tenant_operations,
local_shipping_quote_snapshots y tenant_customer_checkouts. Las dos últimas
estructuras representan cotización/checkout de un solo tenant; el checkout
tenant además depende de operación/perfil del storefront. No representan
simultáneamente consumidor, provider, autorización Network y compra B2C existente.
Usarlas directamente introduciría ownership incorrecto o efectos operativos.

Se agregan ProviderQuoteSnapshot y ProviderContract dentro de Domain/Network/Providers:

- network_provider_quote_snapshots: oferta económica inmutable, UUID aleatorio,
  referencias FK a B2C, consumer/provider, ProviderConnection, servicio, tarifa
  local y regla comercial; capability, fuente, fingerprint, binding de sesión,
  paquete/origen/destino/servicio/tarifa/regla histórica, costo neto, subtotal
  comercial neto, tasa IVA, IVA, total bruto, utilidad neta, moneda y vigencia.
- network_provider_contracts: aceptación durable de una oferta, UUID propio,
  cotización B2C, oferta, selected_by_user_id nullable, selected_at y timestamps.
  El contrato referencia el snapshot completo, sin copiarlo a otra entidad mutable.

No se almacenan transacciones en ProviderConnection ni se duplican servicios,
cobertura, drivers o empresas. Las relaciones vivas tienen FK; los JSON son
copias históricas de datos/condiciones de la oferta, no autorización en JSON.

B2cCotizacion tiene relación providerContract por b2c_cotizacion_id. Se reutiliza
provider_quote_reference para el UUID contractual y los campos económicos
existentes para continuar el checkout. No se agrega una FK circular a la tabla B2C.

## Lifecycle e identidad

1. TenantProviderQuoteSource/LocalProviderQuoteEvaluator evalúan sin escrituras,
   conservando el comportamiento ENFA-02 respecto de operaciones y shipments.
2. El controlador agrega la publicación mediante ProviderQuotePublisher. Sólo
   publica ofertas para cotizaciones persistidas del actor/sesión efectivos,
   consumer resuelto server-side y esquema disponible. Una oferta publicada es
   un snapshot comercial; no es una operación logística.
3. Cada oferta tiene UUID v4 aleatorio persistido. No se selecciona mediante
   logistico, nombre visible, service_code o combinación de textos. Los POST
   locales aceptan únicamente provider_snapshot_uuid como identidad.
4. El precio mostrado se toma de la misma oferta persistida que se seleccionará.
   Una falla de publicación conserva opciones nacionales y deja la opción local
   como Sólo cotización; no habilita una selección por strings como fallback.
5. Al aceptar, ProviderContractSelectionService valida la oferta y crea un único
   contrato junto con la actualización de B2cCotizacion en una transacción.
6. Ya seleccionada, la opción se lee del snapshot congelado, sin consultar los
   cotizadores nacional/local ni emitir nuevas ofertas. La UI muestra Continuar
   al checkout existente. Esto no es una nueva selección de una oferta vencida.

La expiración es 30 minutos como máximo, acotada por el fin de vigencia de la
conexión, servicio y tarifa. Inicio inclusivo y expiración exclusiva. Se comprueba
de nuevo después de obtener los locks. Un POST sobre una oferta vencida rechaza
la selección con una validación genérica que exige recotización, sin crear contrato.
Un contrato aceptado no cambia retroactivamente por vencimiento posterior de su
oferta: conserva precio y puede continuar el checkout comercial existente.

Cambiar de oferta tras aceptar un contrato, o reemplazar una selección nacional
ya efectuada por una local, requiere una cotización nueva. No hay sustitución,
cancelación o múltiples contratos efectivos sobre la misma cotización.

## Ownership, autorización y revalidación

ProviderConsumerResolver reutiliza TenantContext o el mapeo de host verified a
tenant active, sin llamar a TenantDomainResolver ni cambiar TenantContext.
ProviderQuoteOwnership verifica el dueño autenticado de B2C o la cotización
LANDING_PUBLICA de la sesión. pending_checkout_id por sí solo no acredita una
oferta no seleccionada. Para retomar un contrato guest por pending_checkout_id,
también se verifica el nonce original del snapshot.

El binding guarda SHA256 de un nonce aleatorio de 64 caracteres mantenido sólo
en la sesión del servidor. Sobrevive a la regeneración normal de sesión al login.
No se entrega ese nonce al cliente. La oferta también queda vinculada a un
fingerprint del paquete, pesos, dimensiones, seguro y CP/colonias de ambos extremos.

Al seleccionar se validan:

- Existencia de UUID, consumer efectivo, cotización B2C, dueño/binding y fingerprint.
- Fuente tenant_provider_local, capability LOCAL_SHIPPING y vigencia.
- Consumer/provider activos, conexión vigente y correspondiente al snapshot.
- Grant específico del servicio, servicio activo/publicado/vigente y misma estrategia.
- Cobertura vigente de ambos CP y límites de paquete/servicio mediante
  LocalProviderEligibility, compartido con la evaluación ENFA-02.
- Tarifa histórica todavía activa, del provider/servicio correctos, vigente y
  con package_type compatible. Sin calcular un precio nuevo.
- Cotización sin pago/guía/otra selección previa para crear una aceptación nueva.

Provider, servicio y precios del navegador son ignorados. Un UUID de otro
consumer, cotización, usuario o sesión no autoriza selección. Tampoco habilita
acceso administrativo o memberships del provider. El checkout seleccionado
comprueba además el consumer y ownership antes de continuar.

Los cambios de importe de tarifa o margen no reemplazan la oferta: el snapshot
cotizado se honra dentro de su vigencia mientras las condiciones operativas
críticas permanezcan válidas. Desactivar autorización/cobertura/servicio/tarifa
rechaza una aceptación nueva, conservando la oferta histórica sin modificarla.

## Snapshot económico y fiscalidad

Se reutilizan las convenciones del checkout local existente y el desglose B2C:
subtotal_amount/commercial_subtotal neto, tax_amount/vat IVA y
total_amount/customer_total bruto. La tasa se obtiene de
zigo_commercial_pricing.vat_rate y se congela con la oferta; no se consulta de
nuevo en el POST o después de la selección. BigDecimal y HALF_UP evitan mezclar
redondeos de importes netos con brutos.

LocalPricingEngine trabaja con amount de la tarifa local. Se conserva la
convención neta del dominio local, cuyo checkout aplica IVA al subtotal; se
registra expresamente provider_cost_basis=net. No se inventa una factura o IVA
del provider. provider_subtotal/provider_base_price contienen costo neto;
provider_total permanece null porque no se recibió un total fiscal bruto provider.

Ejemplo de fixtures (no configuración productiva):

| Importe | Valor | Base |
| --- | ---: | --- |
| Provider cost | 100.00 | Neto |
| Subtotal cliente | 125.00 | Neto, después de regla comercial |
| IVA cliente | 20.00 | IVA de la oferta, tasa 0.1600 |
| Total cliente | 145.00 | Bruto |
| Utilidad comparable | 25.00 | 125 netos menos 100 netos |

precio, precio_sin_seguro y zigo_final_price guardan el total bruto de mensajería,
como en el flujo comercial B2C actual. zigo_profit_amount conserva utilidad neta.
No se obtiene utilidad restando costo neto de total bruto. No hay protección
local añadida: el toggle de seguro se deshabilita y el modelo bloquea introducirla
después de aceptar. Valor declarado/direcciones pueden completarse sin cambiar
importes ni condiciones cotizadas de paquete/cobertura.

La tarifa y regla comercial se copian íntegramente al snapshot y tienen referencias
FK separadas. Cambios posteriores no alteran esas copias. No se usan ajustes o
descuentos nacionales. ZigoProviderQuoteObservationService lee para selección
local únicamente la proyección comercial pública del contrato; el flujo nacional
de observaciones continúa igual. Checkout y billing existente pueden leer ese
desglose sin exponer costo, margen o IDs internos.

## Inmutabilidad e idempotencia

UNIQUE en contrato.b2c_cotizacion_id y contrato.quote_snapshot_id impiden dos
aceptaciones efectivas. La FK compuesta oferta ID/B2C impide asociar un contrato
a una cotización diferente de la oferta. Otra FK compuesta verifica el consumer,
provider y capability de ProviderConnection; otra verifica el dueño del servicio.

La transacción bloquea B2cCotizacion, tenants en orden de ID, conexión, grant,
servicio, cobertura, paquete y tarifa. Reintenta hasta cinco veces ante deadlocks.
Dos POST del mismo UUID vigente obtienen el mismo contrato. Dos ofertas distintas
que compiten por la misma cotización no reemplazan la aceptación ganadora.
La creación del contrato y los campos de checkout son atómicos; una falla en
actualizar B2C revierte también el contrato.

Los modelos rechazan cambios de ofertas y contratos, incluso saveQuietly y
el borrado sin eventos. ImmutableProviderBuilder prohíbe update/delete/forceDelete,
increment/decrement y upsert masivos. Los contratos aceptados y sus snapshots no
se eliminan; la FK protege además el snapshot ante una aceptación concurrente
con su limpieza. Una oferta expirada sigue siendo inmutable; sólo una oferta no
aceptada puede eliminarse individualmente. No se recalculan importes al expirar.
B2cCotizacion bloquea desde el modelo cambios posteriores a identidad, economía,
paquete, CP, colonias y seguro de la selección local. Los datos de cliente/estado
comercial no modifican el contrato económico.

Límite explícito: DB::table, toBase, SQL directo y administradores de base de datos
no pasan por estos controles Eloquent. No deben usarse para mutar estas entidades.
Sin triggers, la base garantiza estructura/referencias/unicidad, pero no la
inmutabilidad de los importes frente a escrituras SQL externas a la aplicación.
No se capturan errores SQL para ocultar permisos faltantes.

Ofertas sin seleccionar se pueden eliminar y se eliminan por CASCADE al borrar
su cotización. Así, ofertar un provider local no bloquea la limpieza existente de
una cotización nacional. Un contrato seleccionado restringe borrar su cotización;
se conserva el historial comercial. No se añade acción de cancelación.

## Migración y compatibilidad

2026_10_13_110000_create_network_provider_contract_snapshots.php crea dos tablas
InnoDB, índices/FK/UNIQUE y campos económicos NOT NULL. No crea triggers y no
requiere privilegio TRIGGER; tampoco depende de CHECK para la seguridad funcional.
Usa APIs disponibles en Laravel 9; el runtime probado es Laravel 12/PHP 8.2.12.
El esquema utiliza construcciones disponibles en MariaDB 10.4 y MySQL 5.7/8
(JSON exige MySQL >=5.7.8). Se conserva el aislamiento, ownership, transacciones
y UNIQUE de idempotencia. No se instala una defensa opcional ni se ignoran errores.

La collation ASCII binaria de capability sólo se aplica a MySQL/MariaDB y coincide
con Network; en SQLite se conserva su comparación binaria nativa. expires_at y
selected_at usan DATETIME para evitar defaults implícitos heredados de TIMESTAMP
en MariaDB/MySQL con modo estricto. created_at/updated_at son timestamps nullable.

Rollback: contratos, ofertas, índice
compuesto nuevo de ProviderConnection. No borra tenants, servicios, tarifas ni
B2cCotizacion. Como todo rollback que elimina tablas transaccionales, hacerlo con
contratos reales requiere respaldo y plan de datos: B2C conserva sus valores y
referencias, pero los contratos eliminados dejarían de estar disponibles.
No se aplicó la migración a STAGE/PRD.
Si una instalación ya aplicó la versión anterior, editar este archivo no elimina
los triggers existentes: seguirán activos hasta una intervención DBA explícita.
No se realiza aquí ninguna alteración sobre instalaciones externas.

## UI y límites operativos

La selección utiliza el UUID en los formularios existentes de landing, opciones
B2C y cotizador parcial. No se exponen consumer/provider/connection/service IDs,
costo ni margen. El link Continuar para contratos aceptados evita reseleccionar
una oferta vencida. La selección nacional por strings conserva su pipeline.

No hay LocalShipment, TenantOperation, driver assignment, tracking, GPS, POD,
guía final, CRM, liquidación o memberships nuevas. El endpoint nacional de guía
rechaza explícitamente cotizaciones tenant_provider_local para impedir que un
nombre visible coincidente las envíe accidentalmente a Estafeta.
No se modifica Mercado Pago, Xperta internamente, IA/Bots, TodoAutopartes,
owner activation ni cancelación.

## Preparación exacta ENFA-04

ENFA-04 recibirá ProviderContract UUID y snapshot inmutable con ambas partes,
autorización, servicio, tarifa, paquete y economía. Debe crear un vínculo idempotente
entre contrato y ejecución física provider, usando provider_tenant_id explícito.
Debe decidir requisitos de pago/aceptación y revalidación operativa antes de ejecutar.

El origen/destino de la oferta es de alcance postal (CP/colonia y datos disponibles
al cotizar). Las direcciones completas capturadas después viven en el checkout B2C;
ENFA-04 debe validarlas y crear su snapshot de fulfilment separado, sin reescribir
la oferta o el contrato económico. No hay todavía snapshot físico del envío.

Quedan pendientes también políticas de archivo/retención de ofertas expiradas,
integración de seguro/distancia/otras monedas y reglas de conciliación/fiscalidad
provider cuando haya una factura real. Esta fase no introduce endpoints de esas
capacidades ni ejecuta operaciones externas.

## Pruebas y reproducción

NetworkProviderContractSelectionTest cubre selección válida/expirada, scopes
consumer/usuario/sesión/B2C, manipulación del request, revocaciones, idempotencia,
competencia de ofertas, FK/UNIQUE, conservación económica/fiscal, HTML público,
cero ejecución física, TenantContext, rollback, inmutabilidad Eloquent, selección nacional,
login/checkout comercial, continuidad de aceptación y rollback transaccional.

El script opt-in tests/Support/provider_contract_concurrency.php comprueba SQL
y concurrencia real con dos procesos PHP. Sólo acepta un puerto >=30000 y un
datadir bajo el directorio temporal con marcador enfa03-sql-. Antes de cualquier
escritura compara @@datadir con el directorio esperado, crea una base aleatoria y
utiliza fixtures y un usuario temporal con permisos explícitos sin TRIGGER. Las credenciales sólo se guardan en el datadir desechable para los workers. No toma conexiones de .env. Ejemplo, con instancia desechable
previamente inicializada y bind 127.0.0.1:

```powershell
php tests/Support/provider_contract_concurrency.php 43318 <datadir-temporal-enfa03-sql>
```

Verificado en MariaDB 10.4.32: ambos procesos devolvieron el mismo contrato y
se midió la espera del segundo proceso por el row lock; precio congelado, cero
operaciones e up/down/up/down pasaron con un usuario sin permiso TRIGGER. El proceso SQL se detuvo.
No se usó el servidor XAMPP habitual ni STAGE/PRD. Falta ejecución real en MySQL
y un runtime Laravel 9; SQLite no demuestra compatibilidad de esos motores.

El fallo conocido de NetworkTenantB2cTest por el texto TENANT ADMIN en tracking
se reprodujo en el commit base 102ea68a restaurando temporalmente los archivos
rastreados modificados y recuperándolos después. No se cambió ese test o su dominio.

Resultados finales:

- Pruebas ENFA-03: 50 pruebas / 120 assertions, OK.
- Corrida combinada: 202 pruebas / 956 assertions; 201 pasaron y sólo persiste
  el fallo preexistente TENANT ADMIN. Incluye ENFA-01, ENFA-02, B2C quote/checkout,
  Network local shipping, pricing, Xperta/strategy/contratos y UI responsive.
- Deprecación preexistente de PHPUnit por metadata en doc-comment de
  TenantLogisticsEngineTest::test_distance_tiers_and_overage.
- Validación MariaDB y concurrencia real: OK, con dos procesos, una contratación,
  lock verificado, usuario sin TRIGGER, ausencia de operaciones e up/down/up/down.
- php -l y git diff --check: sin errores. Pint aplicado a archivos nuevos y
  archivos pequeños del dominio provider, sin reformatear el controlador nacional.

Comando de regresión:

```powershell
php vendor/phpunit/phpunit/phpunit --filter 'Network(ProviderContractSelection|TenantProviderQuoting|ProviderConnection|LocalShipping|TenantB2c)Test|B2c(XpertaQuoteUi|QuoteCheckoutAuth|XpertaStageQuoteFlow|XpertaSelectionPersistence|CommercialBreakdownSnapshot|ResponsiveUi|LandingMobileCssContract)Test|ShippingStrategyIntegrationTest|ZigoCommercialPricingEngineTest|Xperta(PostmanContract|ForbiddenDiagnostic)Test|TenantLogisticsEngineTest' --display-phpunit-deprecations
```

Los procesos de pruebas usan APP_ENV=testing, SQLite :memory: y APP_KEY de prueba
o la conexión explícita a la instancia temporal verificada. No se editó .env.
