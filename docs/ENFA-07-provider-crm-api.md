# ENFA-07: frontera API de proveedores

## Infraestructura reutilizada

API Hub aporta clientes y claves Bearer con hash SHA-256, revocación, vigencia, scopes, entitlement API, cuotas, rate limiting, request IDs, medición, auditoría e idempotencia. No se crea otro emisor de claves. Sus entregas webhook almacenan también el outbox provider, con estados PR_* separados del worker general. Existen signing HMAC, secretos cifrados y protección SSRF reutilizables por un futuro adapter HTTP; todavía no se usa transporte externo.

No se encontró documentación confirmada del CRM real. No hay URLs, credenciales, payloads específicos ni llamadas al CRM. El único adapter de producción registrado es disabled; el fake vive en tests.

## Identidad y autorización

network_provider_integrations vincula un cliente API existente a un provider y una ProviderConnection LOCAL_SHIPPING. FK compuestas impiden vincular un cliente consumer al provider. Un cliente tiene una integración; usar clientes dedicados por conexión. El comando confiable network:provider-api:bind recibe el UUID del cliente y la conexión, resuelve ownership server-side y no emite secretos. Las claves y scopes se gestionan mediante API Hub existente.

Cada petición valida clave, cliente, tenant activo, entitlement API, integración y conexión vigentes, scope y bridge contractual. El navegador o CRM no puede elegir tenant ni alterar precios. TenantContext no cambia. HTTPS es obligatorio fuera de local/testing. El límite del middleware es 8 MiB; los límites PHP/proxy pueden ser menores y deben configurarse en deployment. La evidencia conserva las validaciones del dominio.

Scopes: provider:fulfillment:read, provider:fulfillment:decide, provider:dispatch:write, provider:tracking:write, provider:pod:write. No existen acciones de cancelación, borrado, refund o GPS.

## Dominio y auditoría

ProviderApiOperations coordina identidad, idempotencia y DTO. Acceptance reutiliza ProviderFulfillmentDecisionService; dispatch reutiliza DriverAssignmentService; tracking, failed y POD reutilizan ProviderDriverExecutionService y los servicios last-mile existentes. Los controllers no contienen state machines.

ProviderApiPrincipal representa al cliente externo, sin falsificar owner/admin. Las decisiones y asignaciones guardan actor_api_client_id y usuario humano nulo; auditoría y eventos distinguen CRM_API de TENANT_UI. El driver real sigue siendo el sujeto operativo y de atribución, no el actor API. No se comparten memberships.

Acceptance sigue separada de tracking. Sólo ACCEPTED es dispatchable. REJECTED no cancela ni reembolsa. La proyección consumer/guest y la guía usan el mismo shipment y mecanismo seguro de ENFA-06, independientemente del canal que produzca el evento.

## Idempotencia y concurrencia

Todos los POST requieren Idempotency-Key. API Hub guarda fingerprint ordenado, respuesta y hashes de evidencia. La clave se delimita por cliente y comando; el fingerprint incluye fulfillment. Igual key/payload devuelve la respuesta original; distinto payload devuelve 409. Ownership y permisos se verifican antes del replay.

Una transacción y lock de integración serializan los primeros inserts del cliente; los servicios existentes bloquean fulfillment/shipment y validan transición. Se conservan UNIQUE de idempotencia, decisiones y vínculos operativos. Se preserva explícitamente expires_at al escribir la respuesta para evitar el auto-update TIMESTAMP de MariaDB. No se añade cleanup automático: conservar estos registros hasta definir una política que no permita repetir comandos históricos.

## Outbox y recuperación

Después del commit de ProviderFulfillment se publica provider.fulfillment.created, sin HTTP dentro de la transacción comercial. UNIQUE integración/recurso/evento evita duplicados. El worker reconcilia fulfillments sin evento, cubriendo el crash entre commit y publicación y las integraciones habilitadas posteriormente.

network:provider-crm:deliver corre cada minuto. Reclama un lease de 120 segundos en transacción corta, llama al adapter fuera de transacción y confirma mediante el mismo lease. Reintenta con backoff exponencial de minutos, máximo seis intentos; termina PR_ATTENTION. Error persistido sanitizado, sin excepción remota ni secretos. Un adapter disabled deja PR_DISABLED sin consumir intentos. --resume=<integration UUID> reactiva esos eventos cuando hay adapter habilitado. PR_ATTENTION requiere revisión operativa, sin loop automático infinito.

Estados: PR_PENDING, PR_SENDING, PR_RETRY, PR_DELIVERED, PR_DISABLED, PR_ATTENTION. La entrega es al menos una vez: un crash después de envío y antes del ack puede repetirla. El CRM deberá deduplicar event_id. Fallos no revierten pago, fulfillment, guía ni tracking.

ProviderCrmAdapter recibe envelope e integración confiable; el resolver usa config/provider_crm.php. El futuro adapter debe validar URL/SSRF, usar secretos cifrados, timeout menor al lease, firma/autenticación confirmada y reconocimiento explícito. No registrar payloads con PII ni credenciales.

## Referencias y dispatch externo

La referencia externa es opcional, ASCII y máxima 160 caracteres. UNIQUE por integración/referencia y por integración/fulfillment, case-sensitive, evita reasociaciones. La primera referencia queda estable; cambiarla da conflicto. Inbound y ack outbound pueden establecerla.

Dispatch usa UUID de DriverProfile del provider, con membresía, actividad y disponibilidad existentes. No se interpreta un ID CRM como ID local. Correlación/sincronización de drivers queda pendiente de documentación real. No hay live GPS; coordenadas de evidencia conservan su semántica existente.

## Migración y rollback

La migración amplía tablas API Hub y actores existentes, y crea sólo integraciones y referencias. FK/UNIQUE y transacciones protegen integridad; no requiere TRIGGER ni SQL exclusivo de MySQL 8. ALTER de nullabilidad usa sintaxis común MySQL/MariaDB sin depender de DBAL allí. SQLite change() en Laravel 9 puede requerir DBAL; el runtime instalado y ejecutado es Laravel 12/PHP 8.2, no se certificó runtime Laravel 9 ni MySQL 5.7/8.

MariaDB 10.4.32 desechable valida up/down/up y concurrencia sin privilegio TRIGGER. Rollback con historial API/outbox/referencias se rechaza antes de DDL: primero exportar/migrar ese historial. No se inventa actor humano ni se borra auditoría para volver a NOT NULL.

## Información necesaria para conectar el CRM real

Solicitar contrato y sandbox oficiales: URLs y versiones; autenticación/rotación y signing; payload de creación y ack; idempotencia y referencias; errores reintentables, cuotas y SLA; catálogo/mapping de estados; identidad/correlación de drivers; formato, límites y privacidad de POD; fechas/timezones, paginación y retención. Definir responsable de aceptación/dispatch, reconciliación y atención tras agotamiento. GPS necesita un contrato independiente de frecuencia, precisión y retención; no participa aquí.

El adapter real y pruebas de interoperabilidad quedan pendientes. ENFA-07 es foundation genérica, no una integración CRM activa.
