# ZN-09 — ZIGO Support Center

## Arquitectura y ownership

Support Center usa la identidad global `User` y un solo agregado `SupportTicket`. El `scope` define ownership:

- `TENANT_OPERATIONAL`: el tenant atiende logística de sus customers y Drivers.
- `ZIGO_PLATFORM`: ZIGO atiende acceso, aplicación, configuración, pagos SaaS y módulos.

Customer y Driver sólo leen tickets donde son requester. Tenant staff sólo gestiona tickets operativos del tenant y consulta solicitudes plataforma del mismo tenant. Network sólo consulta `ZIGO_PLATFORM`; nunca funciona como inbox logístico global.

## Conversación, auditoría y privacidad

Mensajes y eventos son append-only. `PUBLIC` es visible al requester; `INTERNAL` sólo se entrega en superficies staff autorizadas. Los eventos `CREATED`, `ASSIGNED`, `STATUS_CHANGED`, `PRIORITY_CHANGED`, `RESOLVED` y `CLOSED` permiten reconstruir cambios importantes. `first_response_at` y `resolved_at` permiten métricas SLA sin prometer tiempos comerciales.

Los adjuntos viven en storage privado con nombre aleatorio. Sólo JPEG, PNG, WEBP y PDF, con descarga autorizada y `nosniff`; no hay URL pública directa. La retención/purga queda pendiente de una política formal.

## Notificaciones y API futuras

Se publican eventos internos `SupportTicketCreated`, `SupportTicketReplied`, `SupportTicketAssigned` y `SupportTicketResolved`. Futuras integraciones podrán entregar email, notificaciones ZIGO, push o WhatsApp sin acoplar el dominio.

API Hub podrá exponer en una fase posterior `ticket.created`, `ticket.updated` y `ticket.resolved`. No existe API externa, email ingestion, chat realtime ni WebSocket en ZN-09.

## Comercialización futura

La capability puede evolucionar a BASIC SUPPORT, ADVANCED SUPPORT y MULTI-AGENT SUPPORT. Esta fase no crea entitlement ni cobra soporte.

## Riesgos pendientes

- Definir retención y purga legal de adjuntos.
- Diseñar equipos/colas si la asignación individual deja de ser suficiente.
- Definir permisos Network support separados de sysadmin antes de delegar operación.
- Definir política SLA y escalamiento antes de mostrar compromisos de respuesta.
