# IA-08A — Human Handoff Foundation V1

## Objetivo y alcance

IA-08A completa el ciclo interno `AI → handoff requested → human takes → human responds → release to AI`. Sigue limitado al canal `internal_test`; no publica Webchat, WhatsApp ni routing externo.

## Aggregate y lifecycle

`HumanHandoff` pertenece al AI Core y conserva tenant, Conversation, assistant message y Runtime Run de solicitud, estado, asignación y fechas. Sus estados V1 son `requested`, `active`, `released` y `closed`. Conversation utiliza `open`, `handoff_requested`, `human_active` y `closed`.

`needs_handoff=true` crea la solicitud dentro de TX2 únicamente cuando Conversation, assistant completado y Runtime Run completado concuerdan en tenant, Agent y Agent Version, y tanto el mensaje como el run conservan la decisión validada de handoff. `active_handoff_id` en Conversation representa el único ciclo activo y una FK compuesta garantiza que el Handoff pertenezca al mismo tenant y Conversation. Al liberar se limpia el puntero, conservando el histórico y permitiendo un ciclo posterior.

## Takeover, ownership y mensajes

Takeover bloquea Conversation y Handoff, revalida owner/admin con `AI_CORE` y asigna sólo una vez. Sólo el usuario asignado puede enviar mensajes humanos, liberar o cerrar durante atención activa. Los mensajes usan el role `human`, el contador secuencial único de Conversation y el cast cifrado existente de `ConversationMessage`. No crean Runtime Runs, Leads ni Outcomes.

## Transacciones y aislamiento

Request forma parte de TX2. Take, mensaje humano, release y close usan transacciones cortas y el orden canónico de locks `Conversation → HumanHandoff`; después de bloquear revalidan puntero, estado y ownership. Así, take/message/release/close se serializan sobre Conversation sin inversión de locks. Ninguna acción humana llama al provider. Mientras Conversation está `human_active`, el flujo AI falla antes de reservar turno o efectuar HTTP. Release devuelve Conversation a `open` sin ejecutar IA automáticamente.

## Seguridad, tenancy y UI

Las FKs son tenant-aware y `requested_by_message_id` es además Conversation-aware. El acceso HTTP reutiliza Tenant Admin, owner/admin y `AI_CORE`; cross-tenant falla cerrado. La UI ofrece Inbox/Handoffs, takeover, transcript diferenciado, respuesta humana, release y close. Los UUID sólo funcionan como route keys internos. Blade escapa mensajes y no muestra provider, modelo, tokens, costos ni IDs técnicos.

## Retención, pruebas y exclusiones

Handoffs no duplican transcript ni PII y siguen la retención de Conversation. Las pruebas usan SQLite `:memory:`, migraciones reales, foreign keys y HTTP fake con stray requests bloqueadas; cubren constraints, historia, takeover, ownership, cifrado, secuencia, bloqueo AI, release, segundo ciclo y cierre.

Quedan fuera: Actions/Tools, tracking, creación de guías, canales públicos, WhatsApp, Webchat, Telegram, Messenger, CRM ZIGO, equipos/routing/RBAC avanzado, SLA humano, Simulator, Evaluations, Readiness, Usage, Credits, Billing, Vision, deploy e IA-09.
