# ENFA-10 — Release candidate

Estado: **READY FOR STAGE WITH BASELINE EXCEPTIONS** según el release gate final; 21 excepciones idénticas documentadas, cero fallos nuevos/críticos ENFA y gates funcionales/safety/migraciones aprobados. No se ha accedido a STAGE/PRD. No hay commit ni deploy ENFA-10.

## A. Commits y auditoría integrada

Rama `feature/enfa-local-provider-network`, base verificada limpia `b8fa885e98160e0d93ea5d384ff12d31cdd8268c`.

| Fase | Commit | Función |
|---|---|---|
| ENFA-01 | 551ac883 | ProviderConnection y permisos de servicios |
| ENFA-02 | 102ea68a | Agregación local con nacional |
| ENFA-03 | 1d2f4113 | Oferta durable y contratación |
| ENFA-04 | 5d43c35b | Fulfillment y recolección |
| ENFA-05 | 87ad7aaa | Aceptación/rechazo |
| ENFA-06 | 6f816561 | Dispatch y proyección tracking/POD |
| ENFA-07 | 4cf32004 | Frontera API/CRM opcional |
| ENFA-08 | 11ca1850 | Administración y readiness |
| ENFA-09 | b8fa885e | Payable y settlement manual |
| ENFA-10 | Sin commit, cambios locales | Hardening, branding, publicación y paquete STAGE |

Se revisaron documentación, superficie de cambios del frente, modelos, servicios, rutas, schedules, migraciones y pruebas. No se encontró un segundo motor provider de tracking/dispatch/finanzas. Los adaptadores UI/API llaman los mismos servicios. Los servicios nacionales existentes son independientes. `ZIGO_LOCAL` es un discriminador técnico heredado del motor local, no una búsqueda de tenant por nombre; se conserva para reutilizar ese motor.

Contradicciones encontradas: el logo consumer se dejaba explícitamente null en el snapshot y el renderer validaba contra el propietario operativo provider. Se habilita snapshot de logo consumer y se valida el propietario a través del bridge persistido. La vista customer mostraba un segundo estado del shipment que podía confundir con aceptación; se retira para usar la proyección segura. No había consentimiento explícito para publicar socios: se agrega configuración vacía por defecto, sin migración.

## B. Arquitectura final

Connection autoriza/configura; QuoteSnapshot conserva oferta/economía; Contract registra selección; Fulfillment enlaza contratación con una operación y shipment propiedad provider. Pago verificado es prerrequisito. El fulfillment crea automáticamente READY_FOR_PICKUP en el origen, sin otra compra/formulario/portal/correo manual del cliente. Decision es append-only y separada de tracking. Dispatch y estados/POD reutilizan el motor local. Payable/eventos/settlement son un ledger separado; entregar no ejecuta pagos.

No se cambia TenantContext para simular otro tenant. TenantContext se establece en fixtures para representar al consumer; el dominio recibe provider explícito. No se incorporan cancelación, refund, CRM real, GPS live, payout automático ni modificaciones internas de Xperta/Estafeta/Mercado Pago.

## C. Migraciones

Orden obligatorio, después de las dependencias Network, shipping local, drivers/POD y API Hub ya presentes:

| Archivo (database/migrations/) | Tablas/efecto | Reversión |
|---|---|---|
| 2026_10_13_100000_create_network_provider_connections.php | connections, connection_services; índice compuesto del catálogo | Hijos antes de padres; conserva catálogo |
| 2026_10_13_110000_create_network_provider_contract_snapshots.php | quote_snapshots, contracts | Requiere analizar contratos/historial antes de down |
| 2026_10_13_120000_create_network_provider_fulfillments.php | fulfillments, enlaces operativos/índices | No usar down como rollback funcional de compras |
| 2026_10_13_130000_create_network_provider_fulfillment_decisions.php | decisions | Preservar auditoría de decisiones |
| 2026_10_13_140000_add_provider_api_boundary.php | integrations, external_references; extensiones API/auditoría/outbox | Bloquea down con historial API |
| 2026_10_13_150000_create_provider_payable_ledger.php | payables, settlement_entries, payable_events | Bloquea down con cualquier historial financiero |

ENFA-10 no agrega migraciones. FK compuestas mantienen ownership, UNIQUE evita duplicados; importes DECIMAL. No triggers ni datos ENFA/ZIGO precargados. DDL MariaDB tiene commits implícitos: ante fallo inspeccionar estado antes de repetir. Revisar `migrate:status`, backup restaurable, `migrate --pretend`, luego `migrate --force` sólo en ventana autorizada. No ejecutar rollback genérico en un esquema con operaciones. Ver runbook y documentos ENFA-01/03/07/09 para constraints y límites del ORM frente a SQL directo.

## D. Scheduler y recovery

| Command | Frecuencia | Propósito | Criticidad | Si cron se detiene |
|---|---|---|---|---|
| network:provider-fulfillment:retry --limit=100 | Cada minuto, mutex 10 min | Recuperar compra verificada sin fulfillment | Alta | Compra puede quedar preparando; no repetir compra |
| network:provider-crm:deliver --limit=100 | Cada minuto, mutex 10 min | Entregar outbox opcional | Baja en INTERNAL | No afecta operación interna; disabled no crea retry infinito |
| network:provider-payables:reconcile --limit=100 | Cada minuto, mutex 10 min | Recuperar obligación/eventos financieros | Alta financiera | Proyección financiera puede retrasarse; no alterar tracking |
| network:provider-network:check --consumer=slug:CONSUMER --provider=slug:PROVIDER [--for-activation] | Manual | Preflight read-only PASS/WARN/FAIL | Obligatoria antes de activar | No se ejecuta diagnóstico automáticamente |
| network:provider-api:bind {client_uuid} {connection_id} | Manual | Vincular cliente API opcional | No necesaria INTERNAL | No afecta TENANT_UI |

Los tres jobs están registrados una vez en Kernel; invocaciones idempotentes y comandos separados. Un fallo no introduce dependencia de CRM entre jobs; pueden retrasar el siguiente si el proceso del scheduler se bloquea. Cron del host NO está verificado por readiness: es WARN, debe demostrarse. Configurar `schedule:run` cada minuto con directorio, usuario y PHP correctos; capturar exit/logs y alertar ausencia de ejecución. No limpiar locks hasta comprobar que no existe proceso activo. No duplicar con crons individuales además de scheduler.

## E. Rutas

- Network (sysadmin, host y 2FA existentes): GET listado/create/edit y POST create/update `/network/provider-connections`; GET listado/detalle y POST registro `/network/provider-settlements`.
- Tenant provider: GET `/admin/solicitudes-provider`, GET detalle UUID, POST `/{fulfillment}/accept` y `/reject`. Reutiliza driver console/dispatch/POD existentes.
- Customer: GET `/b2c/envio-local/{contract:uuid}`, `/guia`, `/evidencia/{photo|signature}`. UUID no sustituye autorización de compra y consumer.
- Guest: recuperación existente `/envio/recuperar/{token}` y descarga; nueva evidencia `/envio/recuperar/{token}/evidencia/{kind}`. Token limitado al consumer, nunca incrustado en PDF.
- API Hub: prefijo provider bajo V1, GET fulfillments/listado/detalle y POST acciones accept/reject/assign/tracking/delivery-failed/pod según `routes/api.php`. Scopes y frontera explícita, sin endpoints financieros. La fuente exacta de rutas/acciones es el inventario adjunto; ENFA-10 no agrega rutas.

## F. Permisos

Consumer: ownership autenticado o sesión de compra; guest mediante recuperación válida y host consumer. Provider: tenant activo, workspace operativo, subscription vigente, LOCAL_SHIPPING. Inbox/decisiones: membership activa owner/admin/operator. Dispatch: owner/admin y DRIVER; conductor: membership driver activa del mismo tenant, perfil ACTIVE y disponibilidad conforme al motor. API sólo si se habilita deliberadamente con entitlement API, integration/key y scopes. Settlement y Connection sólo sysadmin Network, con host/auth/2FA/CSRF existentes. Ser operador provider no concede finanzas Network.

## G. Configuración y env

El frente introduce `config/provider_crm.php`: único adapter incorporado `disabled`, lease 120 segundos, máximo 6 intentos. No hay variables env nuevas obligatorias ni secretos ENFA. Usa infraestructura existente APP_KEY, DB, mail, filesystem, host/contexto, scheduler y tasa IVA comercial existente; no se editó .env. INTERNAL/TENANT_UI no requiere ProviderIntegration ni API key ni endpoint CRM.

ENFA-10: `config/provider_network.php`, `public_partner_uuids = []`. Publicación explícita por UUID provider (configuración de servidor, no enviado como texto al cliente), adicional a conexión vigente del consumer, tenants activos y al menos un servicio autorizado/publicado. Sin contexto consumer verificado no publica socios. No se reutiliza published del servicio como consentimiento de marketing del tenant. No afecta eligibility, precio ni ranking. Tras cambios regenerar config cache por procedimiento autorizado.

## H. Tablas y ownership

Nuevas del frente: `network_provider_connections`, `network_provider_connection_services`, `network_provider_quote_snapshots`, `network_provider_contracts`, `network_provider_fulfillments`, `network_provider_fulfillment_decisions`, `network_provider_integrations`, `network_provider_external_references`, `network_provider_payables`, `network_provider_settlement_entries`, `network_provider_payable_events`.

Se reutilizan network_tenants/domains/brandings/memberships, subscription/entitlement/usage/audit, b2c_cotizaciones y recuperación, zigo_pricing_rules, local_shipping_services/zones/postal_codes/package_rules/pricing_rules, network_tenant_operations, local_shipments/tracking, driver assignments/proofs y API Hub/outbox. No se copian cobertura/tarifas provider al consumer.

## I. Feature safety y E2E

Sin conexión activa: cero opciones locales, opciones nacionales por el mismo pipeline previo. Pruebas explícitas `test_national_options_are_unchanged_without_connections`, ausencia/inactividad/vigencia y nuevo `test_uncovered_origin_or_destination_preserves_national_options` (ambos extremos por separado).

`NetworkProviderReleaseCandidateTest` integra fixture consumer/provider, connection, servicio, cobertura de ambos CP, package rule, tarifa y pricing comercial; publicación/selección/contract, pago VERIFIED controlado, fulfillment automático, READY_FOR_PICKUP, aceptación, driver, PICKED_UP/IN_TRANSIT/OUT_FOR_DELIVERY, DELIVERED+POD, PAYABLE, parcial/final, SETTLED. Readiness se ejecuta en cada fixture y no exige CRM ni settlement automático. Es prueba de dominio integrada: el pago se persiste como confirmación controlada; no simula un cobro externo ni reemplaza las pruebas HTTP de checkout/pago existentes.

Tarifas cambian después de contratar para probar economía congelada. Pruebas existentes cubren ownership, consumidores ajenos, drivers ajenos, replays, duplicados, errores de pago, guest y scopes. Rechazo conserva compra/READY_FOR_PICKUP, no dispatch, payable REVIEW y mensaje seguro. DELIVERY_FAILED conserva compra, auditoría y deuda, pasa REVIEW; no hay cancelación/refund. Sin HTTP externo (`preventStrayRequests`, Mail fake, filesystem fake); CRM disabled probado por suites API/administración y ausencia de operaciones HTTP en el E2E.

## J. Rollback

Primero desactivar conexión y retirar publicación. Conservar contratos, compras, guías, seguimiento, pruebas y ledger. Desactivar corta ofertas nuevas y puede impedir fulfillment pendiente: conciliar esos casos con soporte antes de revertir código. No elimina operaciones ya creadas ni libera obligaciones. Reversión de código sólo a versión compatible con historial persistido. No ejecutar down con historial ni borrar deuda. Restaurar backup requiere análisis de operaciones/pagos ocurridos después del backup.

## K. Observabilidad

Inspeccionar logs Laravel de publicación/cotización/fulfillment, recovery_required, outbox y reconciliación; registrar exit no cero de comandos. Correlacionar UUID públicos en atención y IDs sólo en herramientas internas autorizadas. No registrar payloads, tokens, direcciones, motivos privados ni secretos. Revisar compras verificadas sin bridge, bridges sin payable, REVIEW sin responsable y antigüedad de outbox/recovery. Alertas/cron del hosting requieren configuración operativa; readiness no demuestra su ejecución.

## L. Límites y backlog

- Cotización CP soporta MXN, FLAT/PACKAGE_FLAT; no distancia inventada ni seguro local implícito.
- REVIEW requiere resolución operativa/financiera acordada; no hay ajuste/reversión fiscal automatizada.
- SETTLED liquida base neta contractual; no certifica IVA/factura provider ni transferencia bancaria.
- POD tiene coordenadas puntuales si se capturan; no GPS live ni mapa de ruta continuo.
- Logo nuevo consumer se conserva en guía; guías históricas sin logo siguen usando nombre. Branding público actual puede cambiar sin alterar economía.
- Publicar socio no garantiza cobertura; el cotizador decide disponibilidad.
- Publicidad/banners administrables: backlog separado. Nunca vincular con activation, eligibility, price o ranking.
- PDF fixture renderizado con Windows.Data.Pdf e inspeccionado visualmente: etiqueta legible, logo consumer embebido, origen/destino, código de barras/QR y operador sin desbordamiento. Poppler no está instalado; el renderer Windows fue la alternativa local. Validar también los logos y nombres reales de STAGE durante el smoke.

## M. Validación y fallos preexistentes

Consultar `ENFA-10-validation.md` y logs adjuntos para resultados exactos. Base comparada: archivo aislado de HEAD b8fa885e, mismas dependencias, mismo filtro, SQLite :memory:, sin .env. Los resultados de documentos anteriores no sustituyen esta comparación.

MariaDB: instancia desechable 10.4.32, bind loopback y datadir temporal validado antes de crear bases; usuario de pruebas sin TRIGGER. Readiness, POD y settlement concurrente/reconciliación/rollback financiero probados. Bootstrap inicial tuvo corrupción Aria en tablas de privilegios; se aisló una segunda instancia y se registran sólo ejecuciones aprobadas como evidencia, sin ocultar el incidente. No se certifica un migrate completo de toda la aplicación sobre copia STAGE.

## N. Requisitos STAGE / decisión

## Release gate final — revisión de baseline

**READY FOR STAGE WITH BASELINE EXCEPTIONS**

| Gate | Resultado |
|---|---|
| Known baseline failures | 21 |
| New failures introduced by ENFA | 0 |
| Baseline failures materially changed | 0 |
| Critical ENFA failures | 0 |
| ENFA E2E | PASS — 377 casos / 1336 assertions |
| Feature safety | PASS — ausente/inactiva; 2 casos / 58 assertions |
| Migration gate | PASS — MariaDB 10.4.32 desechable |
| Smoke RC | 716 total; 695 passed; 21 fallos; 0 errores/skipped |
| Smoke HEAD base b8fa885e | 705 total; 684 passed; 21 fallos; 0 errores/skipped |

Una deprecación PHPUnit preexistente permanece en ambas ejecuciones. Se mantuvo íntegro el filtro original del smoke. Los cuatro casos agregados por esta revisión son exclusivamente verificaciones de safety y seguridad; no hubo modificación productiva ni de assertions existentes. Los totales de gates focales son subconjuntos, no se suman al total del smoke.

La comparación por caso confirma presencia, tipo de excepción, assertion, esperado/real y mensaje/stack completo normalizado con SHA-256 idéntico. No basta coincidencia de nombres: ver [tabla exacta de 21 y justificación individual](ENFA-RELEASE-BASELINE-EXCEPTIONS.md), [A–T funcionales](enfa-release-gate/functional-gates.md), [archivo↔suites↔fallos](enfa-release-gate/change-test-map.md), [comparación JSON](enfa-release-gate/comparison.json) y [smoke final](enfa-release-gate/rc-final.txt).

Esta decisión sustituye la clasificación conservadora inicial de ENFA-10, basada sólo en coincidencia de casos. Se aceptan los 21 como excepciones ENFA documentadas, **sin declararlos resueltos**. No autoriza omitir controles del hosting: backup/restauración, migraciones del esquema real, cron, storage/mail, hosts válidos, memberships/entitlements y configuración comercial siguen siendo pasos del runbook antes de activar. No se accedió a STAGE/PRD, no se editó .env y no hubo commit/push/deploy.