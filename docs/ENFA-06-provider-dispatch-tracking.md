# ENFA-06 — Dispatch, tracking y proyección consumer

Base: `87ad7aaa feat: add provider acceptance workflow`, rama `feature/enfa-local-provider-network`. Se verificó repositorio/HEAD/árbol limpio antes de modificar. La implementación continúa siendo genérica y no realiza llamadas CRM, pagos, refunds, liquidaciones ni cancelaciones nuevas.

## Arquitectura y ownership

Se reutilizan `DriverProfile`, `DriverAssignment`, `LocalShipment`, `LocalTrackingEvent`, `LocalTrackingService`, `DeliveryEvidenceService`, `DriverDeliveryService` y `DeliveryRequirementService`. No se crea otro motor logístico, state machine, tabla, migración o guía.

`ProviderDriverExecutionService` agrega autorización e idempotencia alrededor de esos servicios; la consola driver delega las acciones de shipments `provider_contract` a esa frontera. Las acciones generales anteriores conservan su camino existente. El adaptador futuro debe resolver actor/assignment autenticados server-side, nunca desde tenant IDs o precios de un payload.

Consumer conserva compra, checkout y snapshot económico. Provider conserva operación, shipment, driver, assignment, eventos y POD. Los servicios no cambian `TenantContext` ni crean memberships.

## Dispatch y asignación

`ProviderDispatchEligibility` exige decisión ACCEPTED y bridge correspondiente al shipment/operación/provider. Pending y rejected no se asignan ni se ejecutan, incluso si hay assignments históricos previos a ENFA-05/06.

La UI manual de ENFA-05 sigue usando el endpoint/servicio existente. Owner/admin administra drivers; operator conserva lectura/decisión según permisos existentes. El servicio interno revalida membership provider activa del actor operativo (owner/admin/operator), driver ACTIVE y su membership driver activa bajo lock. No permite que un actor consumer asigne en otro tenant.

Para provider, una asignación nueva exige driver AVAILABLE. La presencia reciente no se exige manualmente (política existente de despacho manual); sí se exige para autoasignación. El driver actualmente asignado se conserva como candidato para repeat submit aunque esté BUSY. La asignación bloquea shipment/assignment y driver, revalida estado actual y reutiliza el assignment si coincide.

Autoasignación existente se protege adicionalmente para provider: driver y membership se releen bajo lock, con disponibilidad/presencia actuales. Si otro shipment ganó ese driver, devuelve null y permite retry; no sobreasigna silenciosamente. No hay autoasignación nueva al aceptar ni integración externa. La UI sigue priorizando asignación manual; los callers internos existentes pueden reutilizar autoAssign.

Se conserva la reasignación existente sólo en READY_FOR_PICKUP, dentro del mismo provider; se desactiva el assignment anterior. No se añade otro workflow de reasignación ni se cambia de provider. El driver anterior pierde autoridad incluso si su request ya había resuelto un assignment antes del cambio.

## Ejecución y estados

Máquina de `LocalTrackingService`, sin cambios: READY_FOR_PICKUP → PICKED_UP → IN_TRANSIT → OUT_FOR_DELIVERY → DELIVERED o DELIVERY_FAILED. El retorno desde DELIVERY_FAILED a OUT_FOR_DELIVERY reutiliza el límite de intentos contratado/snapshot existente.

La frontera provider bloquea shipment, assignment, perfil y membership. Revalida mismo provider, assignment vigente, actor driver correcto, perfil/membership activos y decisión ACCEPTED. El actor siempre se registra en el evento existente. Un driver ajeno, uno reasignado o un tenant incorrecto no ejecuta acciones conociendo UUIDs.

PICKED_UP/IN_TRANSIT/OUT_FOR_DELIVERY se delegan al state machine existente. Repetir la transición al estado actual devuelve el evento original; un request atrasado hacia un estado superado se rechaza por transición inválida, sin duplicar evento. DELIVERED requiere la ruta de POD, no una transición directa de ese adaptador. No se exponen acciones de cancelación.

## POD e intentos fallidos

La captura usa requisitos/configuración y renderer/formularios actuales: receptor, tipo, fotografía normalizada, firma PNG y coordenadas cuando sean requeridos. `DeliveryRequirementService` congela la opción provider actual al primer uso si no existía snapshot. El fallback existente exige evidencia completa. No se recotiza ni se aplica recargo adicional al precio congelado; los importes operativos de configuración no cambian economía consumer/provider contractual. Una modalidad de evidencia comercial adicional necesita diseño/selección explícitos en otra fase.

La foto se valida y normaliza con el servicio existente. Firma, receptor, tipo y coordenadas se validan nuevamente en la frontera de dominio, para que un futuro adaptador no dependa sólo de la validación del controller.

Entrega y POD se ejecutan en la misma transacción SQL, con UNIQUE existente por shipment. Replay devuelve el mismo POD del assignment original, sin guardar nuevos archivos ni generar otro evento/attribution. La ruta POST de la consola permite exclusivamente ese replay provider tras COMPLETED; la vista driver y los caminos generales conservan sus restricciones anteriores. No se crea guía por transición.

DELIVERY_FAILED registra el intento existente y evento durable. Replay mientras siga fallido devuelve el intento original. Tras volver legítimamente a OUT_FOR_DELIVERY, una nueva falla genera el siguiente intento, hasta el límite existente. No implica rejected, cancelación o refund. Motivos/notas/foto/coordenadas de fallo permanecen internos en esta proyección.

`DriverDeliveryService` conserva su attribution y contabilidad driver existentes si hay una política configurada. ENFA-06 no añade liquidación, payout, settlement ni cambios al contrato económico.

## Proyección consumer y guest

El acceso parte de `ProviderContract`, valida consumer efectivo y ownership de comprador/sesión mediante `ProviderContractSelectionService`, y resuelve bridge/operación/shipment persistidos. Recovery usa el token ENFA-04 y su consumer, expiración, revocación y límite de accesos. No hay endpoint público por shipment PK/UUID ni provider ID de navegador.

`ProviderTrackingProjection` es un DTO por lista permitida sobre un bridge ya autorizado: label, timeline (label + timestamp), receptor/fecha de POD y disponibilidad de foto/firma. Además valida consumer y shipment/provider/operación. Un adaptador futuro debe conservar la autorización de comprador o bearer antes de invocarlo; conocer el tenant no reemplaza ownership de compra.

| Condición | Mensaje cliente |
| --- | --- |
| Pending | Solicitud enviada al operador logístico |
| Accepted + READY_FOR_PICKUP | Recolección confirmada |
| PICKED_UP | Paquete recolectado |
| IN_TRANSIT | En tránsito |
| OUT_FOR_DELIVERY | En reparto |
| DELIVERED | Entregado |
| DELIVERY_FAILED | No fue posible completar la entrega |
| Rejected | Estamos revisando tu envío |

El timeline sólo deriva etiquetas de eventos conocidos; nunca usa description/metadata, actor/driver IDs ni notas. Añade la confirmación de aceptación desde su registro durable; cuando los timestamps tienen la misma precisión de segundo, la aceptación precede al pickup físico. El rechazo técnico y su motivo no aparecen.

El cliente sigue viendo branding consumer, operador visible, folio y guía originales, direcciones congeladas y su precio comercial. No ve costo provider, margen, reglas o IDs internos. La bandeja provider muestra avance operativo sin introducir cancelación ni mostrar economía consumer.

POD sólo se proyecta para un shipment DELIVERED y ACCEPTED, con tenant/shipment/assignment/driver consistentes. Archivos se sirven desde storage privado mediante contrato autorizado y kind photo/signature; no se acepta ruta arbitraria ni UUID de evidencia. Referencias de archivo se comprueban contra el formato de rutas POD existente. Respuestas private/no-store/no-referrer y nosniff. No se exponen receiver_notes, coordenadas ni paths.

Las rutas guest reutilizan `/envio/recuperar/{token}`; los enlaces de guía/evidencia del mismo origen necesitan ese bearer para conceder acceso read-only. No se imprime como texto, no se incorpora al PDF/QR, no se coloca en links externos y se usa no-referrer. No hay polling automático que consuma el presupuesto del token. El límite/TTL actuales permanecen; cada GET autorizado de recovery consume el acceso según ENFA-04.

## Ubicación y notificaciones

La presencia actual es last_seen_at y availability_status, sin latitud/longitud ni stream de posición. POD/failed attempts pueden almacenar coordenadas puntuales de captura. La navegación driver existente usa la dirección; no constituye GPS en vivo. ENFA-06 no muestra mapa ni posición real-time, ni proyecta esas coordenadas privadas al cliente.

No hay correos nuevos por transición/entrega. Se conserva confirmación y recuperación ENFA-04. Notificaciones adicionales requieren política y outbox/idempotencia; se difieren para evitar spam o duplicados.

## Concurrencia, compatibilidad y rollback

Asignación/transición usan transacciones con locks y reintentos acotados de deadlock. POD/fallo no reejecutan automáticamente callbacks que escriben archivos; un retry del request es seguro por estado/locks/UNIQUE y reutiliza la evidencia ya confirmada. Los archivos privados y SQL no forman una transacción distribuida: un crash entre almacenamiento y commit puede dejar archivos huérfanos, aunque no dos POD efectivos; recuperación/limpieza de huérfanos requiere trabajo posterior. La limpieza por excepción del servicio existente se conserva.

No hay migración nueva ni dependencia de TRIGGER o sintaxis MySQL 8. Se usan APIs comunes de Laravel 9; runtime ejecutado localmente PHP 8.2/Laravel 12. MySQL 5.7/8 y runtime Laravel 9 no ejecutados. SQL real validado en MariaDB 10.4.32 desechable, usuario sin TRIGGER.

El runner SQL conserva guard de datadir temporal/puerto/@@datadir y agrega dispatch, auto-dispatch, tracking, driver-conflict, pod y failed. Usa dos procesos, barrera de lock y espera real; checks de una ejecución, economía congelada y contexto intacto. Archivos SQL de evidencia quedan bajo ese datadir temporal, separados de storage de aplicación. También mantiene modos ENFA-03/04/05 y rollback de sus migraciones con vínculos persistidos. Ese rollback no se aplica a datos de aplicación y las advertencias de backup/auditoría ENFA-04/05 siguen vigentes.

## Inventario

Validación final: ENFA-01 27/124, ENFA-02 33/69, ENFA-03 50/120, ENFA-04 48/176, ENFA-05 38/116 y ENFA-06 41/145 (tests/aserciones), todos pasan. Regresión amplia: 442 tests / 2292 aserciones; 439 pasan, cero errores y sólo tres fallos de texto preexistentes. `TENANT ADMIN` en NetworkTenantB2cTest y `DELIVERED`/`READY FOR PICKUP` en DriverLastMileFoundationTest se reproducen en una exportación aislada de HEAD `87ad7aaa`. La deprecación PHPUnit doc-comment preexistente permanece. JUnit local: `storage/logs/enfa06-regression.xml`.

MariaDB 10.4.32: pasan dispatch, auto-dispatch, tracking repetido, conflicto de drivers, POD e intento fallido concurrentes; también selección, fulfillment y accept/accept + accept/reject de fases anteriores. Se verifica lock/espera real, unicidad, actor/contexto, economía y rollback existente sin grant TRIGGER. No se ejecutaron migraciones contra la DB de aplicación o ambientes externos. `git diff --check` y checks de whitespace para archivos nuevos pasan.

Nuevos: `app/Domain/Network/Providers/ProviderDriverExecutionService.php`, `app/Domain/Network/Providers/ProviderTrackingProjection.php`, `tests/Feature/NetworkProviderTrackingTest.php`, `tests/Support/ProviderTrackingFixture.php`, `docs/ENFA-06-provider-dispatch-tracking.md`.

Modificados: `app/Domain/Network/Providers/ProviderDispatchEligibility.php`, `app/Domain/Shipping/LastMile/DriverAssignmentService.php`, `app/Domain/Shipping/LastMile/DriverDispatchService.php`, `app/Http/Controllers/Tenant/DriverConsoleController.php`, `app/Http/Controllers/B2C/ProviderFulfillmentController.php`, `app/Http/Controllers/B2C/GuestGuideRecoveryController.php`, `resources/views/b2c/provider-fulfillment.blade.php`, `resources/views/tenant/admin/provider-request.blade.php`, `routes/web.php`, `tests/Support/provider_contract_concurrency.php`.

## Preparación ENFA-07

Adaptador CRM/API autenticado que resuelva actor/tenant/assignment y delegue a los mismos servicios; definir scopes, actor externo, idempotency keys de eventos externos y request/event audit. No mapear IDs externos a tenant/assignment sin ownership server-side. Mantener estados acceptance/tracking separados y economía inmutable. Definir outbox/notificaciones y manejo de huérfanos. Si se requiere GPS real, definir fuente/consentimiento/retención/privacidad; las coordenadas POD no son un stream. Revisar shipments/assignments históricos ya iniciados sin acceptance antes del rollout: quedan bloqueados para nueva ejecución hasta decisión explícita, sin modificar automáticamente tracking o asignaciones.
