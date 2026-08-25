# IA-12 — Usage, plans and limits V1

## Autoridad de capacidad

`AI_CORE` continúa siendo el entitlement booleano de Agentes IA. `AiCapacityService` es la autoridad server-side para `MAX_AGENTS`, `MAX_WEBCHAT_CHANNELS`, `MAX_WHATSAPP_CHANNELS`, `MONTHLY_RUNTIME_UNITS`, `MONTHLY_ACTION_RUNS` y `MONTHLY_CONVERSATIONS`. La feature global, Tenant activo, Subscription vigente y entitlement `AI_CORE` habilitado son requisitos acumulativos; filas de capacidad huérfanas no conceden acceso.

La fórmula efectiva por capacidad es:

```text
baseline = override canónico habilitado, si existe
           de lo contrario capacidad plan+módulo, si existe
           de lo contrario default legacy de config/ai.php

effective = baseline + suma(add-ons habilitados)
```

La base del plan vive exclusivamente en `network_plan_module_capacities`. `network_entitlement_capacities` sólo admite `addon` y `override`; no crea una segunda base. Cada add-on usa una `source_key` estable y puede deshabilitarse conservando historial. El único override provisionable usa `source_key=override`, reemplaza la base y también puede deshabilitarse. La combinación plan/módulo debe existir en `network_plan_modules`, y las FKs compuestas impiden mezclar Tenant, Subscription y Entitlement de agregados distintos.

Para un `AI_CORE` histórico sin filas nuevas, los defaults son: 1 Agent, 1 Webchat, 0 WhatsApp, 1000 Runtime Runs LIVE, 100 Action Runs y 250 Conversations por bucket mensual. No se modifica ni elimina un Agent preexistente. Todos los estados no terminales (`draft`, `active`, `paused`) consumen slot; `retired` es terminal y no consume.

## Usage y período mensual

Se reutiliza `network_usage_events`, con métricas separadas `ai_runtime_units`, `ai_action_runs` y `ai_conversations`. Los tokens del proveedor, observabilidad, Leads, Outcomes y Handoff no forman parte de esta autoridad comercial.

`AiUsagePeriodResolver` deriva buckets mensuales anclados en `Subscription.current_period_start`, incluso si la Subscription es anual o se extiende sobre la misma fila. Usa el timezone de la aplicación y aritmética `addMonthsNoOverflow`; cada intervalo es half-open `[start, end)` y su final nunca supera `current_period_end`. Por ello el evento exacto en la frontera pertenece sólo al bucket siguiente y el uso del mes anterior no se arrastra tras una extensión/renovación.

Una unidad Runtime corresponde a un `RuntimeRun` LIVE persistido y aceptado. `MeteredRuntimeRunService` centraliza tanto el Runtime normal como `post_action_synthesis`. Para una Action que requiere síntesis, la reserva idempotente `post_action_runtime:{action_run_id}` se crea antes de reclamar o ejecutar el side effect; la reserva se vincula al Run y no puede ser robada por otro request. Si no hay cuota, la Action permanece recuperable y el handler no se invoca. Si el handler falla después de reservar, la unidad permanece consumida y el Run reservado termina en `failed` auditable, sin llamar al proveedor. Un retry del mismo ActionRun reutiliza la reserva. Simulator, `internal_test` y consola interna usan `AiExecutionMode::Simulation` y no consumen.

Una unidad Action se reserva al reclamar el `ActionRun` para ejecución. Un `requested` o `awaiting_confirmation` aún no reclamado no consume; una vez `executing`, la unidad permanece aunque el handler o la validación de salida falle. Reintentar el mismo `ActionRun` mantiene una sola unidad.

Una unidad Conversation se reserva únicamente al crear una conversación Webchat o WhatsApp real. Mensajes, polling, handoff, reintentos y webhooks duplicados sobre la misma conversación no agregan unidades.

## Idempotencia, enforcement y concurrencia

Las claves lógicas son `runtime_run:{id}`, `action_run:{id}` y `conversation:{id}`. El índice UNIQUE existente `(tenant_id, metric, idempotency_key)` es la autoridad final frente a redelivery y carreras; el chequeo previo sólo optimiza el camino idempotente.

Agent creation y creación inicial de canales se rechazan antes de persistir por encima del límite. Editar, rotar credenciales/keys, deshabilitar o volver a habilitar el mismo canal no crea otro slot. Los canales configurados, habilitados o no, cuentan porque reservan una integración del Tenant.

El orden canónico para flujos IA-12 es Tenant, current Subscription, entitlement/capacidades, agregado AI necesario y Usage Event. Esto incluye `authorizeTurn` de WhatsApp: el lookup por webhook es no bloqueante; luego se bloquea Tenant, autoridad, Agent, Channel y Session, revalidando ownership, habilitación y ventana. En canales públicos puede hacerse un lookup inicial sin lock sólo para resolver el Tenant; dentro de la transacción se bloquea y revalida en el orden canónico. Las llamadas externas se ejecutan después de confirmar la reserva, fuera de la transacción. SQLite `:memory:` valida reglas, rollback, FKs e idempotencia secuencial, pero no reproduce locks de InnoDB; la garantía concurrente se basa en el lock de Tenant común y el UNIQUE de base de datos.

## Tenancy, workspaces y UI

Toda capacidad y todo usage se resuelve desde el Tenant autorizado/`TenantContext`; no se acepta `tenant_id` del cliente o modelo como autoridad. Un Tenant AI-only usa el workspace de Agentes IA y no obtiene rutas operacionales logísticas. Un Tenant con módulos operacionales más `AI_CORE` conserva esos módulos y suma IA en el mismo Tenant, usuarios y Tenant Admin. Sin `AI_CORE`, con entitlement deshabilitado, Subscription expirada, Tenant inactivo o feature global apagada, IA queda no operativa.

Tenant Admin muestra capacidad y uso reales, estado “Límite alcanzado” y el mensaje neutral “Amplía tu plan para agregar más capacidad.” No muestra precios, checkout ni identificadores técnicos.

## Separación de Billing

IA-12 representa y aplica capacidad operacional. No cobra, factura, renueva dinero, almacena tarjetas, ofrece refunds, publica catálogo, crea checkout, integra Mercado Pago ni da de alta públicamente tenants AI-only. IA-13/IA-14 podrán provisionar base y add-ons usando esta autoridad sin crear otro Tenant, Subscription o `AI_CORE`. La autoridad horizontal no depende de ZIGO; las Actions logísticas continúan como adapters verticales separados.
