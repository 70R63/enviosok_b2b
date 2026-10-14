# Contrato provider API v1

Host y habilitación se resuelven con config/zigo_api_hub.php. Base de rutas: /api/hub/v1/provider. Usar HTTPS y Authorization: Bearer <clave provisionada por API Hub>. No incluir tenant IDs. Las respuestas incluyen únicamente información operativa permitida; pickup/delivery contienen PII necesaria y requieren almacenamiento protegido por el integrador.

| Método/ruta relativa | Scope | Payload |
| --- | --- | --- |
| GET /fulfillments?page=1 | provider:fulfillment:read | ninguno; hasta 30 registros por página |
| GET /fulfillments/{uuid} | provider:fulfillment:read | ninguno |
| POST /fulfillments/{uuid}/accept | provider:fulfillment:decide | external_reference opcional |
| POST /fulfillments/{uuid}/reject | provider:fulfillment:decide | reason opcional, hasta 500 caracteres; external_reference opcional |
| POST /fulfillments/{uuid}/dispatch | provider:dispatch:write | driver_uuid local del provider; external_reference opcional |
| POST /fulfillments/{uuid}/tracking | provider:tracking:write | status canónico; external_reference opcional |
| POST /fulfillments/{uuid}/failed | provider:tracking:write | reason y datos de intento admitidos por dominio |
| POST /fulfillments/{uuid}/pod | provider:pod:write | receptor y evidencia admitidos por dominio |

Todos los POST llevan Idempotency-Key ASCII de 8 a 160 caracteres (letras, dígitos, punto, guion, guion bajo o dos puntos; primer carácter alfanumérico). Campos no admitidos producen 422. No se aceptan precios, tenant IDs o service IDs.

Respuesta de detalle/comando: {"data":{"fulfillment_uuid":"UUID","reference":"referencia pública","external_reference":null,"acceptance":"ACCEPTED","status":"READY_FOR_PICKUP","service":{"code":"código","name":"nombre"},"pickup":{},"delivery":{},"package":{},"driver_uuid":null}}. Listado: data es un array. No se serializan modelos ni PK, costos, margen o reglas comerciales. El UUID de driver es identidad operativa, no PK interna.

Acceptance: PENDING_PROVIDER -> ACCEPTED o REJECTED. La decisión es terminal e idempotente; no es tracking. Estados canónicos de tracking: READY_FOR_PICKUP, PICKED_UP, IN_TRANSIT, OUT_FOR_DELIVERY, DELIVERED, DELIVERY_FAILED. El dominio impone transiciones y asignación activa; un string arbitrario no se guarda. DELIVERED con evidencia se registra por /pod; fallo por /failed, sin cancelación/refund. El adapter futuro traducirá estados CRM a estos estados.

POD acepta multipart photo validada por el servicio existente, received_by_name, receiver_type, receiver_notes y latitude/longitude/accuracy_meters cuando corresponda. Signature es data:image/png;base64,..., validada y limitada por dominio; no una URL remota. Failed acepta reason, notes, coordenadas y photo según validaciones existentes. Consultar las reglas actuales de DeliveryEvidenceService para obligatoriedad y catálogo de evidencia: esta frontera no las reemplaza. La evidencia sigue privada y el consumer usa su ruta autorizada existente.

Idempotencia: mismo comando/key/payload (incluidos bytes de archivos) devuelve respuesta original y X-ZIGO-Idempotent-Replay: true. Payload distinto con la misma clave da 409. Usar clave nueva para cada comando lógico; conservarla en todos sus reintentos. Un replay no cambia actor ni genera auditoría/eventos adicionales.

Errores seguros: 401 autenticación, 403 autorización/scopes, 404 recurso ajeno o inexistente, 409 conflicto, 413 tamaño, 422 comando/transición inválidos, 426 HTTPS requerido, 429 cuota/rate limit, 503 indisponibilidad. No se devuelve detalle SQL ni razón técnica interna. La infraestructura API Hub puede envolver sus errores de autenticación con su contrato existente.

Outbound envelope contiene event_id estable, event_type provider.fulfillment.created y snapshot operativo seguro. ProviderCrmAdapter::deliver recibe ese envelope y ProviderIntegration server-side y devuelve acknowledgement con external_reference opcional. No se define todavía endpoint HTTP externo ni payload específico CRM. Se requiere deduplicación remota por event_id antes de habilitar un adapter real.

No hay endpoints cancel, void, refund, delete shipment, GPS ni credenciales CRM reales. Las acciones de dominio son reutilizables por portal y este adapter sin cambiar la proyección consumer/guest.
