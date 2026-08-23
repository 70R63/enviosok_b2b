# IA-07A — Leads & Outcome Evidence V1

## Propósito y alcance

IA-07A extiende Conversation Core para que una respuesta estructurada pueda proponer datos de prospecto y detección de resolución. El backend valida esas propuestas y persiste `Lead` y `OutcomeEvent` como entidades horizontales de AI Core. Sólo funciona a través de conversaciones `internal_test`; no publica canales.

## Entidades y relaciones

`ai_leads` conserva UUID, tenant, Agent, Agent Version, Agent Contract Version, Conversation, assistant message y Runtime Run de origen. Existe como máximo un Lead por Conversation mediante constraint tenant-aware. Candidatos posteriores sólo completan campos autorizados que faltan en ese Lead canónico; no borran ni reemplazan valores válidos existentes. `ai_outcome_events` conserva las mismas referencias, el tipo, estado, fechas, Lead opcional y evidencia mínima. Un OutcomeEvent que referencia un Lead sólo puede apuntar al Lead canónico de la misma Conversation y tenant. Todas las FKs históricas usan `RESTRICT` y las referencias AI usan claves compuestas tenant-aware cuando las tablas fuente lo permiten.

## Contract como autoridad

`AgentContractVersion.outcome_policy` es la única autoridad. El objeto V1 admite:

```json
{
  "lead": {
    "allowed_fields": ["name", "email", "phone", "company", "interest"],
    "required_fields": ["email"],
    "max_length": 191
  },
  "outcomes": ["valid_lead", "resolved_consultation"]
}
```

La versión de contrato ya es versionada, validada, hasheada e inmutable después de ser ofrecida. IA-07A no la modifica in-place ni crea configuración paralela. Claves candidatas fuera de la allowlist se rechazan y hacen fallar atómicamente TX2.

## PII, cifrado y minimización

Los datos permitidos se normalizan, limitan y guardan con el cast nativo `encrypted:array` de Laravel. La evidencia no duplica PII, transcript, prompt ni respuesta cruda del proveedor. No se registran datos personales en URLs, logs o metadata técnica. Las vistas Blade usan escape normal y nunca salida sin escapar.

## Structured output e integración

El schema existente añade `lead_candidate` y `resolved_candidate`. Son propuestas, nunca declaraciones de verificación. Retrieval, runtime y HTTP continúan fuera de transacciones. TX2 completa assistant y citas, finaliza Conversation y registra Lead/Outcome como una unidad; una violación contractual o de coherencia del aggregate revierte toda esa finalización. Antes de persistir evidencia se comprueba que Conversation, assistant message, Runtime Run, Agent, Agent Version, Contract Version y Lead forman un único aggregate coherente, además de pertenecer al mismo tenant.

## Outcomes y estados

Los tipos V1 son `valid_lead` y `resolved_consultation`, y sus únicos estados V1 son `detected` y `verified`. Un Lead válido queda `verified` sólo si los datos efectivos ya persistidos en el Lead canónico contienen todos los campos contractualmente requeridos. Una consulta resuelta queda conservadoramente `detected`, porque V1 no introduce una regla determinista de auto-verificación. La confianza del modelo no verifica outcomes.

La idempotencia se protege con un Lead único por tenant/Conversation y un Outcome único por tenant/Conversation/tipo/Runtime Run. La evidencia referencia reglas y aggregates internos, no texto completo.

## Tenant Admin, seguridad y retención

Tenant Admin agrega listado y detalle de Leads bajo el patrón existente `web`, tenant resolution/auth, subscription, owner/admin y entitlement `AI_CORE`. La UI muestra datos permitidos, estado, agente, fecha, Conversation y evidencia operacional, sin IDs técnicos, proveedor, modelo, unidades ni costos. Los modelos usan `AiTenantModel`, scope/boundary existentes y el acceso cross-tenant falla cerrado.

Los UUID tenant-scoped pueden utilizarse como identificadores opacos de routing en URLs internas del Tenant Admin. No se presentan como información visible o referencia comercial al usuario.

Los Leads contienen PII y deben seguir `AI_DATA_RETENTION_DAYS`. Esta fase documenta la obligación, pero no crea purge job ni scheduler.

## Pruebas y restricciones

Las pruebas se ejecutan en SQLite `:memory:`, con `PRAGMA foreign_keys=ON`, migraciones reales y HTTP fake con `preventStrayRequests`. Cubren migración reversible, constraints, cifrado, política contractual, tenancy, idempotencia, ambos outcomes, runtime fuera de transacción, recuperación stale y UI/XSS.

Un outcome representa valor empresarial demostrado. No se deriva del número de tokens, llamadas al modelo o costo del proveedor.

Quedan fuera: CRM ZIGO, deduplicación global, canales públicos, Webchat, WhatsApp, Human Inbox/Handoff operativo, Simulator/Readiness, Usage, Credits, Billing, cobros, wallet, planes AI, deploy y llamadas reales a OpenAI.
