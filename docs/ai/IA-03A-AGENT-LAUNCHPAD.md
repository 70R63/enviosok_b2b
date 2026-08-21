# IA-03A — Managed Agent Launchpad V1

## Propósito comercial

El Launchpad permite que un owner o admin describa una necesidad de negocio y reciba una propuesta inicial configurable de agente. El flujo visible es: Mis agentes → Crear agente → necesidad → información del negocio → recomendación → revisión → crear borrador → detalle del Agent Draft.

## Intake y recomendación

El intake acepta objetivo, descripción del problema y negocio, clientes, resultados, canales deseados, horarios, handoff, fuentes de conocimiento, integraciones, idioma y nombre opcional. El DTO aplica una allowlist exacta y rechaza cualquier clave desconocida o ID interno, además de HTML activo, caracteres de control, secretos, credenciales, tokens y claves relacionadas con prompts.

Handoff no admite texto operativo libre. Se expresa mediante los códigos `when_requested`, `unknown_or_low_confidence`, `sensitive_actions` y `always_available`; `rules_v1` los transforma en triggers y acciones controlados por la plataforma. Las descripciones del usuario nunca se copian a guardrails, capacidades, acciones o políticas ejecutables.

`rules_v1` produce un esquema provider-neutral con tipo, nombre, trabajo, objetivos, capacidades permitidas/prohibidas, canales, conocimiento, acciones, políticas operativas, configuración compatible con `AgentConfigurationData`, explicación, supuestos, faltantes, riesgos, alternativas y confianza. Para objetivos no definidos clasifica texto normalizado en español o inglés y reduce la confianza ante ambigüedad.

Webchat es el canal inicial. WhatsApp se conserva como solicitado y pendiente; API es una integración pendiente. Siempre existe handoff humano. La propuesta nunca habilita pagos, cambios críticos sin confirmación, asesoría regulada, precios inventados, integraciones no disponibles ni publicación automática.

## Seguridad y conversión

La sesión extiende `AiTenantModel`: el tenant se deriva exclusivamente de `TenantContext`, el binding queda tenant-scoped y las referencias usan `RESTRICT`. Desde su creación son inmutables UUID, objetivo, intake, recomendación, advisor, versión, confianza, creador y tenant. La única transición permitida es `Recommended → Converted`; posteriormente también quedan fijados status, Agent y auditoría de conversión. La infraestructura append-only bloquea escrituras normales, quiet, bulk, incrementos, borrados, truncate, insert y upsert externos.

La frontera HTTP `AiLaunchpadHttpGate` reutiliza los gates AI existentes y traduce condiciones conocidas sin revelar mensajes internos: feature o contexto inválido producen 404; falta de suscripción/entitlement o rol produce 403. Las seis rutas mantienen además los middleware tenant existentes. La autorización y los 404 cross-tenant se verifican mediante requests HTTP con hosts resueltos por `tenant.resolve`.

La conversión bloquea la sesión en transacción, valida nuevamente el esquema persistido y delega en `CreateAgentDraftService` de IA-02. Crea Agent, Contract y ambas versiones en Draft. La sesión queda Converted con referencia inmutable; reintentos devuelven el mismo Agent.

## Límites de V1

No hay providers, llamadas HTTP salientes, SDKs, prompts monolíticos, publicación, aceptación de contrato, canales funcionales, carga de conocimiento, pagos, simulador, conversaciones ni runtime. Un adapter LLM futuro podrá implementar `AgentLaunchpadAdvisor` y registrarse en el pequeño registry sin cambiar controllers, sesiones ni contratos.

SQLite `:memory:` valida idempotencia secuencial, rollback, constraints y repetición HTTP, pero no demuestra concurrencia real de dos conexiones. La prueba de `lockForUpdate` bajo concurrencia MySQL queda como validación futura; la migración sólo se revisa estáticamente para MySQL en esta fase.
