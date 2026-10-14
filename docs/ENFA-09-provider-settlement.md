# ENFA-09: obligación provider y liquidación manual

## Fuente económica y fiscalidad

La venta pertenece al consumer. El provider no cobra al cliente mediante esta fase. ProviderContract referencia ProviderQuoteSnapshot: costo provider neto, subtotal comercial neto, IVA cliente, total cliente bruto y utilidad comercial neta. El payable copia únicamente la base contractual provider y moneda; los importes comerciales se consultan desde el mismo snapshot inmutable, sin volver a tarifas, márgenes o impuestos actuales.

provider_net_amount = provider_cost congelado. provider_tax_amount y provider_gross_amount permanecen null: ENFA-03 no contiene información fiscal suficiente del provider. No se calcula IVA provider ni se equipara impuesto cliente a impuesto de compra. Utilidad comparable se lee de profit_amount neto, nunca total bruto menos costo neto.

SETTLED significa que la base neta contractual se registró como liquidada. No certifica impuestos, factura provider o total fiscal bruto. Un pago bancario que incluya otros conceptos exige separar y conciliar la asignación neta antes de registrarla; esta fase no determina impuestos, retenciones ni saldos fiscales. Soporte fiscal futuro deberá añadirse como historial asociado, sin editar la obligación inicial.

## Persistencia y ownership

- network_provider_payables / ProviderPayable: UUID público aleatorio, FK compuesta al bridge de fulfillment con contrato/snapshot/consumer/provider, UNIQUE por fulfillment y contrato, neto DECIMAL(12,2), impuesto/bruto nullable, moneda y fecha. La conexión y cotización B2C se localizan por el snapshot existente; no se duplican servicio, tarifa, margen o cliente.
- network_provider_settlement_entries / ProviderSettlementEntry: UUID, payable/ownership/moneda, importe DECIMAL(12,2), referencia, key/fingerprint, paid_at, actor, nota acotada y created_at. FK compuesta exige mismo payable/consumer/provider/moneda.
- network_provider_payable_events / ProviderPayableEvent: historial financiero append-only, estado anterior/nuevo, acción, fuente única, actor nullable, canal, fechas y vínculo a settlement cuando corresponde.

Se reutiliza el patrón DriverEarningEntry y la protección ImmutableProviderBuilder de ENFA-03: save/update/delete/bulk update/forceDelete/upsert no pueden editar historial existente mediante modelos. No hay columna mutable de estado económico; el estado es el último evento financiero. FK impiden eliminar el bridge/actor con historia y UNIQUE evita duplicados. No hay TRIGGER obligatorio. SQL directo queda fuera de las garantías del ORM y debe reservarse a procedimientos operativos controlados.

## Lifecycle separado de logística

| Fuente durable | Estado financiero |
| --- | --- |
| Fulfillment comercial confirmado | PENDING |
| Provider ACCEPTED | IN_SERVICE |
| Provider REJECTED | REVIEW |
| Tracking DELIVERED | PAYABLE |
| Tracking DELIVERY_FAILED | REVIEW |
| Pago parcial manual | PAYABLE, con pendiente menor |
| Base neta totalmente registrada | SETTLED |

Rejected/failed no eliminan deuda, no la convierten en cero y no cancelan compra/shipment ni generan refund. Una entrega posterior a un intento fallido puede producir PAYABLE, conservando ambos eventos. Rechazo sigue REVIEW. No se liquida automáticamente al entregar. PENDING, IN_SERVICE y REVIEW no admiten settlement desde esta UI; la resolución administrativa de REVIEW y ajustes/reversiones quedan pendientes. Los modelos no usan enums SQL y permiten futuras entidades/eventos de ajuste sin reescribir entradas pasadas.

## Integración y recuperación

Los observers de fulfillment, decisiones y LocalTrackingEvent delegan a ProviderPayableService después del commit. El servicio consulta fuentes persistidas, usa transacción y locks de bridge/payable, crea una raíz como máximo y deduplica cada fuente. No introduce lógica financiera en controllers operativos, API o tracking, ni cambia TenantContext.

Un fallo financiero se registra con mensaje sanitizado y no revierte una compra/logística ya confirmada. Existe una ventana de consistencia eventual tras el commit: un crash puede impedir el callback. network:provider-payables:reconcile --limit=100, registrado cada minuto con withoutOverlapping, recupera bridges sin payable y fuentes decision/tracking sin evento financiero. Las filas ya sincronizadas no ocupan el límite. El command devuelve error si alguna fuente no pudo procesarse; monitorizar ese resultado/log.

Al recuperar una fuente antigua no se retrocede el estado final: sync reproyecta el estado vigente y, si hace falta, añade RECONCILED. Un settlement completo permanece SETTLED. Un crash durante registro manual revierte entry, evento y auditoría juntos, o deja todos confirmados; el retry reusa el resultado.

## Registro manual y precisión

ProviderSettlementService autoriza sólo sysadmin Network según política existente. UI /network/provider-settlements hereda auth, segundo factor, host Network y CSRF. Se filtra por provider y estado, se consulta referencia/servicio/fechas/entrega, neto provider, venta neta/IVA/bruto, utilidad, liquidado, pendiente e historial. No es una vista pública ni un permiso tenant admin.

El administrador registra un pago ya realizado y conciliado fuera del sistema: amount como string decimal positivo de hasta dos decimales, currency exacta, payment_reference ASCII hasta 128 caracteres, paid_at no futura, key de 8..128 caracteres y nota opcional de hasta 500, una línea. No hay campos bancarios/credenciales; no pegarlos en notas o referencia. paid_at se normaliza a UTC; el formulario interpreta la zona de aplicación y el historial identifica UTC. No se realiza transferencia, HTTP de pago, payout o Mercado Pago provider.

La suma se hace con BigDecimal sobre importes decimales string, también en SQLite; no se usa float ni SUM de punto flotante para el saldo. remaining = neto contractual - entradas. Se rechaza moneda distinta, exceso o importe no positivo. Varias entradas permiten pago parcial; llegar a cero genera SETTLED. No hay conversión de moneda.

Keys y referencias se normalizan a mayúsculas para comportamiento consistente SQLite/MariaDB. Mismo key/payload normalizado devuelve entry original; payload distinto da 409. Misma referencia y mismo payload también reusa la entrada, incluso con key nueva. UNIQUE referencia se delimita por consumer/provider/moneda y evita reutilizar el mismo pago para otra obligación. Para un pago bancario por lote, usar referencias de asignación únicas aprobadas; no se implementó conciliación bancaria por lotes.

La transacción bloquea tenants en orden, bridge y payable; calcula saldo después de los locks y revalida estado. Se permiten retries limitados por deadlock, sin efectos externos en el callback. Dos administradores no pueden registrar sobrepago, duplicar key/referencia ni producir más de un payable.

## Auditoría y aislamiento

Cada settlement conserva actor real, referencia, importe, moneda, paid_at y created_at, evento de estado anterior/nuevo y NetworkAdminAuditService. Replays no cambian el actor original ni añaden registros. Creación/reconciliación automática usa SYSTEM; decisiones y tracking conservan canal TENANT_UI/CRM_API y actor humano cuando existe. source_reference permite localizar la decisión UUID o evento tracking que identifica al actor API, sin inventar usuario humano.

Network sysadmin es global según política actual; no se introdujo un rol financiero nuevo. Usuarios provider/consumer/guest sin ese rol no tienen acceso, incluso con UUID conocido. Provider API no posee endpoint financiero ni capacidad de marcar SETTLED. No se agregó payable a serializers o proyecciones B2C/guest ni a la guía. Vista de saldo provider se difiere: antes requiere política independiente que oculte venta/margen consumer y otros providers.

## Readiness y migración

ENFA-08 agrega settlement.infrastructure: tres tablas y reconciliación registrada. Modo manual es válido, sin banco, credenciales, CRM o payout automático. La nueva migración 2026_10_13_150000_create_provider_payable_ledger.php usa FK/UNIQUE/índices, DECIMAL y DATETIME, Laravel APIs compatibles con 9 y SQL compatible con MariaDB 10.4, sin MySQL 8-only ni privilegio TRIGGER.

Rollback vacío elimina hijos antes de root y después el índice adicional del bridge. Cualquier payable/evento/settlement persistido bloquea down antes de DDL; exportar/migrar historial antes de una reversión de esquema. No borrar deuda para facilitar rollback. No hay backfill DML durante migración: ejecutar reconciliación explícitamente después de deploy para fulfillments históricos.

## Procedimiento antes de STAGE/PRD

1. Revisar código y migración; respaldo y plan de rollback con historial financiero. No se accedió ni desplegó a esos ambientes durante esta implementación.
2. Aplicar migraciones por el proceso autorizado y ejecutar preflight ENFA-08; comprobar settlement.infrastructure PASS.
3. Ejecutar network:provider-payables:reconcile --limit=100 hasta sincronizar fuentes históricas. Verificar conteos por contratos/fulfillments, netos/monedas contra snapshots y unicidad. No recalcular tasas actuales.
4. Confirmar cron schedule:run cada minuto, permisos de cache/mutex y monitorización de recovery_required/exit distinto de cero. CRM disabled no necesita activación externa.
5. En un flujo de prueba autorizado: quote/selección sin obligación; confirmación/fulfillment con PENDING; aceptación IN_SERVICE; entrega con POD PAYABLE; rechazo/fallo REVIEW.
6. En Network revisar neto contractual, venta neta/IVA/bruto y utilidad. Conciliar un pago externo aprobado; registrar asignación parcial y luego completar. No usar este command o formulario para ejecutar pagos reales automáticamente.
7. Repetir submit/referencia y probar dos administradores, sobrepago y moneda distinta. Verificar una entrada efectiva por intención, saldo preciso, actor y auditoría.
8. Verificar B2C/guest/provider sin permisos financieros, guía y API sin información o acciones nuevas de liquidación.
9. Antes de operar a escala acordar responsables financieros, resolución REVIEW, soporte fiscal provider y proceso de ajustes append-only. Certificar runtime Laravel 9/MySQL cuando sea el stack objetivo: la ejecución local disponible es Laravel 12/PHP 8.2 y MariaDB 10.4.32.

Se difieren export CSV/XLSX, vista provider, automatización bancaria, ajustes complejos y reconciliación de lotes. No se implementan cancelación, refund, CRM real, publicidad o liquidación automática.

## Evidencia local

41 pruebas nuevas / 152 assertions aprobadas. Regresión ampliada: 592 pruebas / 2902 assertions, sin errores y con los mismos siete fallos preexistentes reproducidos en HEAD base 11ca1850 (551 pruebas / 2750 assertions): tres textos TENANT ADMIN, DELIVERED, READY FOR PICKUP y cuatro expectativas de host Network 404 frente a 400. Una deprecación PHPUnit preexistente.

MariaDB 10.4.32 desechable: cuatro carreras financieras (crear payable, submit idéntico, parciales simultáneos, sobrepago), cuatro carreras API/outbox y readiness ENFA-08 aprobadas. Se validó up/down/up vacío y rechazo de rollback poblado con usuario sin privilegio TRIGGER. No se ejecutaron migraciones en el ambiente local configurado por .env ni en ambientes externos.
