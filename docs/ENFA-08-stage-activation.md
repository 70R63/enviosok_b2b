# ENFA-08: administración y activación en STAGE

## Alcance y ownership

Network administra únicamente ProviderConnection y sus servicios autorizados. Se reutilizan network_provider_connections y network_provider_connection_services; no hay migración ENFA-08 ni copia de catálogo, cobertura, tarifas o drivers. La administración vive en /network/provider-connections y en el menú Relaciones provider.

La política actual de Network es global para sysadmin, con autenticación y segundo factor. Tenant admins/owners/operators no pueden autoautorizar relaciones cross-tenant. Los UUID de tenants se resuelven server-side; cada ID de servicio se valida contra el provider persistido. La identidad de una conexión existente es inmutable desde esta interfaz. Consumer y provider deben ser distintos. Los cambios se auditan con NetworkAdminAuditService (actor, fecha, estado/vigencia y servicios); nunca se guardan secretos o economía en esa auditoría.

Guardar inactiva permite configuración incompleta. Guardar activa evalúa readiness en una transacción, con locks de tenants/conexión/servicios y FK/UNIQUE existentes. Cualquier FAIL revierte el cambio y los grants. Los WARN no bloquean. No se crean operaciones al configurar ni al diagnosticar. Desactivar o quitar un servicio puede impedir nuevas selecciones y fulfillments todavía pendientes; no modifica contratos aceptados, economía ni shipments existentes. No es cancelación.

## Readiness y criterios

ProviderNetworkReadinessService es read-only y devuelve ready más checks con code, status y message. Diagnostica la configuración actual, no una cotización de un paquete particular ni disponibilidad permanente.

FAIL bloquea activación: tenants inexistentes/inactivos/iguales; relación incorrecta/inexistente; vigencia no actual o inconsistente; capability distinta de LOCAL_SHIPPING; ningún servicio autorizado; ownership incorrecto; servicios inactivos, no publicados o vencidos; moneda/estrategia no soportada; ausencia de cobertura vigente en origen o destino; reglas de paquete ausentes/inválidas; tarifa operacional inaplicable; pricing comercial explícito ausente; ningún miembro operativo; suscripción/entitlement LOCAL_SHIPPING ausentes; infraestructura requerida o scheduler de aplicación incompletos; integración externa activa sin requisitos locales.

Se verifican todos los servicios autorizados y los tipos caja/sobre configurados en sus reglas. Reglas específicas prevalecen sobre reglas generales del provider, como en el cotizador. Las tarifas se filtran por provider, servicio, paquete, actividad y vigencia y se evalúan con LocalPricingEngine. Pricing comercial usa LocalProviderCommercialPricing y la clave de esa pareja, segmento anonymous y plan vacío, sin fallback ESTAFETA. No se muestran importes, costos o márgenes en readiness.

Cobertura informa zonas activas, CP activos/vigentes con formato de cinco dígitos y servicios con origen/destino cubiertos. Un CP o tarifa de otro tenant no cuenta. No se asume región geográfica, ni se importa cobertura durante preflight.

WARN permite preparar la relación pero requiere acción antes de la prueba operativa: drivers activos/disponibles pendientes, entitlement DRIVER pendiente, cron/ejecución del host no comprobable y contrato remoto/scopes de escritura aún no certificados. Se permite preparar relación antes de incorporar drivers; el dominio conserva sus validaciones de dispatch. Un miembro owner/admin/operator activo con usuario existente es FAIL si falta: la consola interna necesita un actor autorizado.

PASS acredita cada condición local comprobada. No acredita pago futuro, cobertura de cualquier CP/paquete, entrega real, cron externo, SMTP, storage/PDF o interoperabilidad CRM. Las fases anteriores siguen revalidando autorización y elegibilidad en quote/selección/fulfillment/dispatch.

## Modo interno y CRM

Ausencia de ProviderIntegration, integración inactiva o adapter disabled expresa INTERNAL / TENANT_UI; no se agrega columna redundante. Es válido para cotización, compra, inbox, aceptación, dispatch, tracking y POD. No se requieren claves API ni entitlement API en modo interno.

Sin integración no se publica evento CRM. Con adapter disabled, ENFA-07 conserva una entrega PR_DISABLED, sin intentos, next_retry ni error, fuera de la selección de reintentos. No hay HTTP, loop infinito ni error visible. Conservar ese evento permite habilitación futura explícita, no representa un fallo de fulfillment.

Una integración activa con adapter distinto de disabled requiere registro de adapter habilitado, cliente activo de este provider, vigencia, clave no revocada/no vencida con scope read y entitlement API. Adapter desconocido es FAIL, no se interpreta silenciosamente como modo interno. Readiness no contacta el CRM ni imprime secretos. La configuración remota y scopes de escritura se deben certificar con el contrato real antes de uso.

## Comando read-only

Identificadores explícitos evitan ambigüedad entre slug y UUID:

```text
php artisan network:provider-network:check --consumer=slug:<consumer-slug> --provider=uuid:<provider-uuid>
php artisan network:provider-network:check --consumer=slug:<consumer-slug> --provider=slug:<provider-slug> --for-activation
```

Sin --for-activation, conexión inactiva da FAIL. Con el flag se evalúa como candidata; no se activa ni se omiten otros checks. Exit 0 significa sin FAIL (puede haber WARN); exit 1 indica FAIL o diagnóstico no disponible. Opciones inválidas/errores se reportan sin echo de entradas, SQL ni secretos. Usarlo en el servidor autorizado con su configuración existente; no exponerlo como endpoint público.

## Procedimiento genérico STAGE, sin SQL manual

Estas instrucciones son para el responsable autorizado del ambiente. En esta implementación no se accedió a STAGE/PRD ni se desplegó.

1. Desplegar código revisado siguiendo el proceso del proyecto; respaldar y aplicar migraciones pendientes con el comando Laravel del entorno. ENFA-08 no agrega migraciones. Revisar los guards de rollback con historial de fases anteriores, sin borrar auditoría para volver atrás.
2. En Network → Empresas identificar el consumer existente por nombre/slug/UUID; no crear un duplicado.
3. Identificar del mismo modo el provider existente; no copiarlo ni crear una segunda empresa.
4. Comprobar ambos tenants activos. En Network comprobar suscripción operacional del provider y entitlement LOCAL_SHIPPING. Verificar un usuario provider activo con membership owner/admin/operator; no conceder membresía provider al usuario consumer.
5. Entrar como usuario autorizado al dominio del provider → /admin/operacion. Crear/editar servicio en su administrador existente, activo y publicado, moneda MXN y estrategia FLAT o PACKAGE_FLAT soportada por cotizador cross-tenant. No cambiar TenantContext manualmente. Validar vigencia si ya existe.
6. En cobertura del mismo portal cargar sólo CP oficiales aprobados por el provider, mediante selección/importador CSV existente. Asegurar zonas activas referenciadas por origen/destino del servicio. No cargar estos CP bajo el consumer. Mantener ceros iniciales.
7. Configurar tarifa operacional real, activa/vigente y aplicable al servicio/tipo de paquete. No inventar montos ni usar base_cost/base_price como sustituto de regla local.
8. Configurar reglas de paquete del provider con peso y dimensiones reales para caja y/o sobre. No duplicarlas bajo consumer.
9. En Network → Relaciones provider → Crear seleccionar consumer/provider y consultar catálogo. Copiar la clave carrier calculada que muestra la UI. Con permiso comercial existente entrar al CRM comercial → Pricing → Márgenes/Profits; crear la regla base con esa clave completa (reemplazar el carrier prellenado), segmento anonymous, plan vacío y paquete all o cada tipo autorizado. Configurar sólo margen/fee/min_price aprobados. No usar regla ESTAFETA, no crear automáticamente precio. La administración usa los modelos existentes.
10. Guardar la relación LOCAL_SHIPPING inactiva, con vigencia correspondiente.
11. Seleccionar servicios del catálogo del provider y guardar; no copiar servicios. Corregir todos los FAIL del readiness visible. Cada servicio autorizado debe estar preparado.
12. Dejar CRM sin ProviderIntegration o con adapter disabled. Operación INTERNAL / TENANT_UI no requiere conectar API externa ni introducir secretos nuevos.
13. Ejecutar el comando con --for-activation y los identificadores reales elegidos; comprobar exit 0 y atender WARN de operación.
14. En la relación seleccionar Activa y guardar. El servidor repetirá readiness y rechazará errores críticos. Ejecutar preflight sin flag y comprobar exit 0.
15. Desde el consumer cotizar un origen/destino realmente cubiertos y un paquete permitido. Verificar opción del provider y precio comercial correcto, sin costos/margen en HTML.
16. Cotizar CP no cubiertos y paquete fuera de límites: la opción debe omitirse, conservando opciones nacionales disponibles.
17. Seleccionar, completar checkout y realizar el pago/saldo de prueba autorizado por el proceso STAGE. Antes de confirmación persistida no debe existir logística; después debe existir un único fulfillment/operación/shipment provider, con economía congelada.
18. Entrar al portal provider → /admin/solicitudes-provider y localizar la solicitud por referencia pública.
19. Aceptar; verificar proyección consumer de recolección confirmada. Repetir el submit no debe duplicar decisión. Rechazo requiere atención, no cancelación/refund.
20. Configurar entitlement DRIVER y driver real del provider, membership activa y disponibilidad según infraestructura actual; asignar desde el inbox/dispatch existente. No usar driver consumer.
21. Con el driver autorizado ejecutar PICKED_UP → IN_TRANSIT → OUT_FOR_DELIVERY y verificar timeline seguro consumer.
22. Registrar POD conforme a las opciones vigentes del servicio, receptor y evidencia permitida. Verificar DELIVERED, evidencia privada y economía intacta. DELIVERY_FAILED usa el intento existente, sin cancelación ni refund.
23. Probar recuperación guest propia y descarga de la misma guía; token/UUID ajenos deben denegarse. Verificar privacidad de costo, margen, IDs y razón interna de rechazo.
24. Comprobar que cron del host ejecuta php artisan schedule:run cada minuto, con usuario/directorio/cache/storage adecuados; observar network:provider-fulfillment:retry y network:provider-crm:deliver. Ejecutar recovery controlado sólo sobre el entorno autorizado; confirmar idempotencia y ausencia de PR_RETRY/PR_ATTENTION por CRM disabled. Verificar SMTP y permisos PDF/evidencia por el proceso operativo existente.

## Ejemplo ENFA (sólo nombres, sin IDs ni secretos)

En Network seleccionar el tenant ZIGO ya existente como consumer y el tenant ENFA ya existente como provider. ENFA configura servicio, CP oficiales, tarifa, paquetes y miembros en su consola. El responsable comercial configura la regla de la pareja usando la clave carrier mostrada por Network. Guardar relación inactiva, autorizar servicio, dejar CRM disabled, ejecutar preflight candidato, activar y repetir los pasos de compra/inbox/driver/tracking/POD/guest. Usar en los comandos los slugs/UUID encontrados realmente; este documento no los presupone. No hay branding ni región hardcodeados en código.

## Validación y límites

La suite nueva cubre permisos, ownership, fechas, activación, pricing separado, diagnóstico sin secretos, CLI read-only y CRM quieto. Se ejecutó la regresión ENFA-01..07, Network, B2C/pago, guest, shipping, drivers/POD, pricing y nacional/Xperta. Los fallos ajenos se contrastaron con una copia aislada del HEAD base.

Resultado final: 46 pruebas nuevas / 172 assertions, sin errores ni fallos. Suite ampliada: 551 pruebas / 2750 assertions, con siete fallos preexistentes y una deprecación PHPUnit. Base 4cf32004: 505 pruebas / 2578 assertions con los mismos siete fallos. Son tres expectativas de texto TENANT ADMIN, DELIVERED y READY FOR PICKUP, y cuatro casos de host/seguridad Network que esperan 404 y reciben 400. No se modificaron esos módulos.

MariaDB 10.4.32 desechable valida queries, activación repetida sin duplicados, exclusión de tarifas ajenas, rollback de activación fallida y diagnóstico sin operaciones/outbox/context switching. No se requiere TRIGGER ni SQL MySQL 8-only. Se usan APIs compatibles con Laravel 9; runtime ejecutado Laravel 12/PHP 8.2, no se certificó ejecución Laravel 9/MySQL 5.7/8.

No se implementó landing/logo/publicidad. La identidad visual existente del tenant y la presentación de opciones/guía son los puntos para una fase posterior; activación no publica banners. No se toca Mercado Pago, Xperta interno, bots, cancelación, refund ni liquidación.
