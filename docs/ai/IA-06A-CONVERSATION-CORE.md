# IA-06A — Conversation Core y consola interna

IA-06A agrega conversaciones multi-tenant de prueba fijadas a un Agent Version Draft o Testing. IA-06A soporta únicamente el canal `internal_test`; los canales externos no forman parte de esta fase. Una conversación puede estar `open`, `handoff_requested` o `closed`; handoff solicitado no implica asignación ni atención humana.

Cada turno persiste primero el mensaje de usuario cifrado y un assistant `pending`. La transacción se libera antes de invocar el `ModelGateway`; una segunda transacción completa o falla el placeholder y vincula el Runtime Run y exclusivamente los Knowledge Chunks citados. No existen retries automáticos. Los errores guardan sólo un código seguro.

Antes de reservar un turno nuevo, un assistant `pending` se considera abandonado al alcanzar o superar `AI_CONVERSATION_STALE_TURN_SECONDS` (120 segundos por defecto) y se marca `failed` bajo el lock de la conversación. Un pending con una antigüedad menor a la lease continúa bloqueando envíos paralelos. La recuperación no repite la ejecución anterior ni controla el timeout HTTP del proveedor.

El contexto multi-turn contiene como máximo seis mensajes `completed`, con tope configurable de 6,000 caracteres. Viaja como datos no confiables en `input.history`, separado de `instructions`; retrieval continúa usando principalmente el mensaje actual. No se persiste el prompt compilado.

El contenido usa el cast cifrado nativo de Laravel y sólo se lee mediante modelos tenant-scoped y servicios autorizados. Runtime Run mantiene observabilidad técnica, pero la consola no muestra proveedor, modelo, tokens, costos, IDs técnicos, prompts ni instrucciones. `AI_DATA_RETENTION_DAYS` documenta la ventana de retención (90 días por defecto); IA-06A no implementa purga automática.
