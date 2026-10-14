# ENFA-05 — Provider inbox y aceptación

Base revisada: `5d43c35b`, rama `feature/enfa-local-provider-network`. Árbol limpio antes de empezar. No hay cambios de identidad tenant, pagos, pricing, contratos económicos ni integración externa.

## Arquitectura y lifecycle

El pago verificado persistido sigue disparando ENFA-04: contrato → fulfillment inmutable → operación provider → shipment provider → solicitud automática de pickup y guía. Cotizar, seleccionar o entrar al checkout siguen sin crear logística.

La nueva entidad `ProviderFulfillmentDecision` registra una decisión definitiva, separada del bridge, del contrato económico y del tracking. Ausencia de decisión = `PENDING_PROVIDER`; transiciones permitidas: pending → `ACCEPTED` o pending → `REJECTED`. La misma decisión repetida devuelve el registro original (UUID, actor, fecha, motivo y canal). La decisión contraria genera un error de validación y no modifica nada. No hay reapertura.

`REJECTED` requiere atención comercial: no cancela compra ni shipment, no devuelve dinero, no borra operación, no modifica snapshots, precios, IVA o margen. La proyección consumer permite que el checkout y el enlace guest conozcan ese estado sin comunicar el motivo interno. Reasignación y atención manual automatizada quedan pendientes.

`READY_FOR_PICKUP` conserva la semántica ENFA-04 de pickup solicitado. No garantiza aceptación del operador. La aceptación es otro eje; sólo un fulfillment aceptado entra al dispatch provider existente. No se altera el motor de tracking.

## Ownership y autorización

Consumer conserva cliente, venta, checkout y economía. Provider conserva fulfillment, operación, shipment y futuros driver/tracking/POD. La bandeja filtra explícitamente `provider_tenant_id` desde `TenantContext`. Las rutas están dentro del grupo web/tenant autenticado, con workspace operacional y entitlement `LOCAL_SHIPPING`.

La UI y el servicio verifican tenant activo y membership activa `owner`, `admin` u `operator`. Decidir revalida tenant y membership mediante lecturas con bloqueo dentro de la transacción; nunca usa tenant, actor, servicio ni precios del formulario. UUID ajeno devuelve 404, permisos insuficientes 403. No se crean memberships ni se cambia TenantContext.

El servicio de dominio recibe tenant y actor de un adaptador autenticado. `TENANT_UI` es el canal del controller; `INTERNAL` permite reutilización interna con la misma autorización. `API`/`CRM` no están habilitados: una futura integración deberá resolver una identidad/autorización válida sin confiar en IDs del payload. No hay endpoints API externos, credenciales, llamadas CRM ni webhooks en esta fase.

## Persistencia, auditoría e idempotencia

Tabla `network_provider_fulfillment_decisions`: PK interno, UUID público no predecible, fulfillment FK NOT NULL + UNIQUE, estado string(24), actor FK NOT NULL, canal string(24), motivo nullable string(500), fecha de decisión DATETIME NOT NULL y timestamps. Índice estado/fecha para auditoría. FKs sin cascade preservan referencias e impiden borrar un fulfillment o actor referenciados.

El modelo reutiliza `ImmutableProviderBuilder`: update/delete, incluso quiet/bulk/upsert, están prohibidos en aplicación. El servicio valida estados/canales y motivo opcional de una línea, acotado a 500 caracteres; Blade escapa el motivo para vista interna y nunca lo proyecta al cliente. Los estados no dependen de CHECK ni TRIGGER. Un acceso SQL directo privilegiado puede eludir la inmutabilidad de aplicación, como en ENFA-03/04; no hay privilegio TRIGGER obligatorio.

La transacción bloquea tenant, membership, fulfillment y decisión existente. UNIQUE por fulfillment es la última defensa contra duplicados. Hay hasta cinco intentos de transacción ante deadlock. Dos actores concurrentes se serializan: el primero que adquiere el bloqueo y confirma decide; el segundo obtiene el mismo registro o conflicto, sin sobrescribir actor/fecha. La prueba SQL fuerza el primer lock para comprobar ambos resultados. El bloqueo tenant serializa decisiones de ese provider; puede optimizarse si el volumen lo exige, manteniendo revalidación de permisos y orden de locks.

Crash antes del commit deja pending; después del commit un retry devuelve la decisión original. No hay segundo efecto remoto ni email en la transacción.

## Bandeja y UI

Rutas bajo `/admin/solicitudes-provider`:

- GET listado y detalle por UUID fulfillment; filtro pending/accepted/rejected.
- POST `/{fulfillment}/accept` y `/{fulfillment}/reject`, CSRF web.

Muestra folio aleatorio shipment, fecha, servicio, marca consumer congelada, contactos/direcciones congeladas, referencias y paquete. La decisión y el estado logístico son visibles por separado. No muestra total cliente, costo, margen, reglas, tenant/connection IDs ni UUID contractual. Las respuestas son privadas/no-store.

Las operaciones originadas en `provider_contract` se consultan desde esta bandeja: no aparecen en el listado genérico con referencia contractual y el detalle genérico redirige al detalle seguro. No se modifican las capacidades generales de cancelación; la bandeja no las expone ni las consume.

Consumer autenticado/guest usa la vista y recuperación ENFA-04:

| Decisión | Texto cliente |
| --- | --- |
| PENDING_PROVIDER | Solicitud enviada al operador logístico |
| ACCEPTED | Recolección confirmada |
| REJECTED | Estamos revisando tu envío |

El motivo interno nunca aparece. Tras rechazo se confirma la compra sin prometer la recolección por ese operador. Guía/PDF y el enlace guest siguen disponibles y conservan su autorización, expiración, branding y privacidad. No se incorporan cancelación, mapa ni tracking nuevo.

## Dispatch y correo

`ProviderDispatchEligibility` protege la cola, candidatos manuales/automáticos y servicios de asignación existentes. Envíos cuyo origen no sea `provider_contract` conservan la elegibilidad previa; provider pending/rejected no se despacha. Si la nueva tabla falta durante rollout/rollback, la elegibilidad provider falla de forma cerrada.

Aceptar no asigna automáticamente. Owner/admin con entitlement `DRIVER` puede usar el formulario mínimo de asignación ya existente desde el detalle; reutiliza `TenantDriverController` y `DriverAssignmentService`, mismos drivers, pertenencia tenant e idempotencia. Operator puede decidir pero no gana permisos de administración de drivers. Sin DRIVER no se ofrece el enlace de despacho.

No hay nuevo correo por aceptación/rechazo. Se conserva el correo de confirmación ENFA-04 y recuperación. Se difiere la notificación de aceptación hasta definir outbox/entrega idempotente y política de comunicaciones; rechazo nunca dispara correo alarmante.

## Compatibilidad y rollback

APIs usadas disponibles en Laravel 9: Schema, FK/UNIQUE, transacción con reintentos y `lockForUpdate`. Referencias: [Laravel 9 Query Builder](https://laravel.com/framework/docs/9.x/queries), [FK MariaDB/InnoDB](https://mariadb.com/docs/server/architecture/server-constraints/foreign-key-constraints). No enum SQL, triggers, JSON relacional ni sintaxis exclusiva de MySQL 8. DATETIME evita depender de rango/autoupdate de TIMESTAMP para la decisión.

Runtime local instalado: PHP 8.2, Laravel 12; compatibilidad de APIs con Laravel 9 revisada, no ejecutada sobre runtime 9. MariaDB 10.4.32 probado realmente; MySQL 5.7/8 no ejecutados.

Down elimina sólo la tabla de decisiones; no elimina fulfillment, operación, shipment, checkout o economía. La secuencia de rollback es ENFA-05 antes de ENFA-04 por FK. **Un rollback de esquema con decisiones reales elimina esa auditoría**: exportar/respaldar primero; re-up no reconstruye decisiones y vuelve a pending. No usar rollback de esquema como mecanismo de reapertura. La prueba disposable verifica down/up/down con registros persistidos y conservación logística.

## Validación

`NetworkProviderAcceptanceTest` cubre ownership, memberships, manipulación, máquina de estados, repeat submit, actor/fecha/canal, motivo y XSS, privacidad, snapshots/economía, ausencia de correo/CRM/refund/cancelación, contextos, guest PDF, CSRF, dispatch bloqueado y asignación manual real idempotente, FK/UNIQUE/inmutabilidad y rollback.

`tests/Support/provider_contract_concurrency.php` admite `decision-accept` y `decision-conflict`: dos procesos y dos usuarios reales, barrera de lock y comprobación de espera. Sólo permite un servidor con puerto >=30000 y datadir temporal `enfa03-sql-*` verificado contra `@@datadir`; crea una DB desechable y usuario sin TRIGGER. Conserva los modos de selección/fulfillment y recuperación guest en SQL real.

La regresión incluye ENFA-01/02/03/04/05, Network Local Shipping/Tenant B2C/Admin/Auth, Driver Last Mile/PWA, guest recovery, checkout/pago B2C, pricing y nacional/Xperta. Los tres fallos de texto `TENANT ADMIN`, `DELIVERED`, `READY FOR PICKUP` se reproducen en una exportación aislada de HEAD `5d43c35b`, sin cambiar el working tree; no se corrigen aquí.

Resultados finales locales: ENFA-01 27/124, ENFA-02 33/69, ENFA-03 50/120, ENFA-04 48/176 y ENFA-05 38/116 (tests/aserciones), todos pasan. Regresión amplia: 401 tests, 2147 aserciones; 398 pasan y únicamente los tres fallos preexistentes anteriores. Una deprecación PHPUnit por metadata doc-comment preexistente. MariaDB 10.4.32: carreras accept/accept y accept/reject con dos usuarios, selección y fulfillment concurrentes, recovery guest TIMESTAMP legado, FK y rollback pasan sin grant TRIGGER. `git diff --check` pasa. No migraciones aplicadas a DB de aplicación ni ambientes externos.

## Inventario

Nuevos:

- `app/Domain/Network/Providers/Models/ProviderFulfillmentDecision.php`
- `app/Domain/Network/Providers/ProviderFulfillmentDecisionService.php`
- `app/Domain/Network/Providers/ProviderDispatchEligibility.php`
- `app/Http/Controllers/Tenant/ProviderFulfillmentInboxController.php`
- `database/migrations/2026_10_13_130000_create_network_provider_fulfillment_decisions.php`
- `resources/views/tenant/admin/provider-inbox.blade.php`
- `resources/views/tenant/admin/provider-request.blade.php`
- `tests/Feature/NetworkProviderAcceptanceTest.php`
- `tests/Support/ProviderAcceptanceFixture.php`
- `docs/ENFA-05-provider-acceptance.md`

Modificados:

- `app/Domain/Network/Providers/Models/ProviderFulfillment.php`
- `app/Domain/Shipping/LastMile/DriverAssignmentService.php`
- `app/Domain/Shipping/LastMile/DriverDispatchService.php`
- `app/Http/Controllers/B2C/ProviderFulfillmentController.php`
- `app/Http/Controllers/Tenant/TenantDriverController.php`
- `app/Http/Controllers/Tenant/TenantOperationController.php`
- `resources/views/b2c/provider-fulfillment.blade.php`
- `resources/views/tenant/admin/layout.blade.php`
- `routes/network.php`
- `tests/Support/provider_contract_concurrency.php`

## Preparación ENFA-06

Agregar adaptador autenticado API/CRM al mismo servicio de decisión, identidad del actor/canal y política de autorización explícita; no exponer escrituras económicas. Definir atención comercial a rechazos/reasignación sin convertirlos en cancelación/refund implícitos. Definir outbox para notificaciones/eventos, visibilidad central consumer para equipo comercial y capacidad/dispatch operativo externo. Reutilizar Driver/LocalTracking/POD existentes. Decidir política para fulfillments antiguos: inician pending, no aceptación retroactiva automática; detener nuevo dispatch provider hasta decisión explícita. Revisar antes del rollout si existen asignaciones/ejecuciones antiguas ya iniciadas: esta migración no las revoca ni modifica tracking.
