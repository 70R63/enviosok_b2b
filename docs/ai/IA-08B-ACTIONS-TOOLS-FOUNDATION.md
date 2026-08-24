# IA-08B — Agent Actions / Tools Foundation

## Objetivo y alcance

IA-08B incorpora al AI Core horizontal un motor síncrono de Actions para `internal_test`. El modelo sólo propone una solicitud estructurada (`action_key` y argumentos); el backend autoriza con el Contract Version pinneado, resuelve una definición trusted en el Registry y ejecuta su handler. La aplicación productiva inicia con el Registry vacío: las Actions reales se instalan posteriormente mediante código confiable.

## Arquitectura

El flujo READ es Runtime Pass 1 → policy/Registry/schema → reserva de ActionRun → handler fuera de transacción → validación de resultado → Runtime Pass 2 → respuesta assistant final. Pass 2 recibe `action_result` como datos no confiables, nunca como instrucciones, y no admite otra Action. V1 permite una sola Action por turno, sin chaining ni ejecución paralela.

Cada Action Definition fija key, nombre, descripción, schemas de entrada/salida, efecto `read|write`, política `none|required` y handler. La key usa formato opaco `[a-z0-9_.-]`; no representa una clase, URL, binding o comando. Registry y Contract son condiciones acumulativas: la Action debe existir en código y estar en `AgentContractVersion.allowed_actions`, que conserva la inmutabilidad contractual existente.

El contexto de ejecución se deriva server-side desde tenant, Conversation, Agent, AgentVersion, ContractVersion, source message y RuntimeRun. Ninguno de esos identificadores ni la confirmación se aceptan con autoridad desde el modelo. Los schemas strict rechazan campos adicionales, tipos incorrectos y `confirmed`; los límites son `AI_ACTION_MAX_INPUT_BYTES` (16 KiB) y `AI_ACTION_MAX_OUTPUT_BYTES` (32 KiB), con defaults sin requerir `.env`.

## ActionRun, seguridad e idempotencia

ActionRun conserva evidencia operacional: aggregate, action key/effect, estado, fingerprint server-side, input/output cifrados, timestamps de solicitud/confirmación/ejecución y error seguro. Los estados V1 producidos son `requested`, `awaiting_confirmation`, `executing`, `succeeded` y `failed`; las solicitudes desconocidas o no autorizadas fallan antes de crear evidencia ejecutable. Las identidades tenant-aware y Conversation-aware, junto con guards de servicio, impiden combinaciones same-tenant incoherentes. Las FKs usan `RESTRICT`.

La unicidad `(tenant_id, idempotency_key)` evita duplicar el mismo intento lógico (Conversation, source turn, RuntimeRun y Action key); un turno posterior obtiene otra identidad. Antes de reservar, el mensaje `user` que originó Pass 1 se enlaza una sola vez con ese RuntimeRun y el guard exige `source_message.runtime_run_id === runtime_run.id`, además de tenant, Conversation, Agent y AgentVersion coherentes. Así, un RuntimeRun de otra Conversation o de otro turno de la misma Conversation no puede originar el ActionRun. `action_key`, effect, ContractVersion e input son inmutables tras la reserva.

La reserva ocurre en una transacción breve. El handler siempre se ejecuta con `DB::transactionLevel() === 0`. Una segunda transacción finaliza `succeeded` con output cifrado validado o `failed` con un código seguro. No se persisten stack traces, credenciales, HTTP raw, prompts o transcript. No existe Generic HTTP Action, resolución dinámica, `eval` ni ejecución de comandos.

## READ, WRITE y confirmación

READ puede ejecutarse automáticamente cuando Definition y Contract lo autorizan. WRITE siempre usa `confirmation=required`: Pass 1 crea `awaiting_confirmation` y no llama al handler. Owner/Admin con AI_CORE confirma por una ruta Tenant Admin interna; el ActionRun se bloquea y reserva `executing` antes de liberar la transacción. Sólo entonces se llama al handler fuera de TX. Una confirmación repetida obtiene conflicto de dominio y no repite el efecto.

La respuesta post-action reutiliza ModelGateway, Responses adapter, structured-output guard y RuntimeRun. Un fallo entrega al Runtime sólo estado/error seguro. Si la síntesis final solicita handoff, se reutiliza HumanHandoff de IA-08A. ActionRun no es Lead, Outcome, Usage ni Billing; IA-07A continúa evaluando únicamente la respuesta Runtime final.

No existe auto-retry de WRITE: una reserva ambigua debe fallar cerrada y requerir revisión humana. IA-08B no incorpora queues, workers, scheduler ni acciones asíncronas.

## Tenant Admin y privacidad

La UI muestra Actions instaladas que el Contract permite, solicitudes, estado, nombre amigable, fecha y confirmación. No muestra clases, URLs, payload JSON, UUID como texto, IDs de DB, provider/model/tokens/costos o secretos. UUID tenant-scoped sólo se usa como route key opaco. Inputs, outputs y ConversationMessage permanecen cifrados y todo contenido dinámico se escapa con Blade.

Las rutas están bajo tenant resolution, autenticación, suscripción, Tenant Admin/Owner, AI_CORE y throttle. Cross-tenant falla cerrado/404. El modelo, viewers y tenants distintos no pueden confirmar.

## Pruebas y límites V1

`AiActionFoundationTest` cubre Registry, Definition, migración up/down/up, FKs, cifrado, idempotencia, ejecución fuera de TX, READ y confirmación WRITE. `AiActionRuntimeTest` cubre structured request strict, una sola Action, rechazo de auto-confirmación y la frontera result-as-data. La regresión conserva Runtime, Conversations, Leads/Outcomes y Human Handoff.

Fuera de IA-08B: Actions ZIGO reales, cotización, tracking, creación de guía, URLs arbitrarias, código de tenant, marketplace, action chaining, parallel actions, canales externos, WhatsApp, Webchat público, CRM, Usage, Credits, Billing, background jobs y auto-retry de WRITE.
