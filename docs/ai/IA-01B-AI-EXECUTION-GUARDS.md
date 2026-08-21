# IA-01B — AI Execution Guards

## Secure dispatch flow

All future AI entry points must call `AiJobDispatcher::dispatch()` with a factory. The dispatcher checks `AI_ENABLED`, requires the tenant already resolved in `AiTenantBoundary`, verifies AI entitlement, passes the trusted tenant primary key to the factory, verifies the returned `TenantAwareAiJob`, rejects tenant mismatch, and only then sends the job through Laravel's bus dispatcher.

Controllers must never accept a browser-provided tenant ID to construct an AI job and must never call Laravel `dispatch()` directly for AI work. Until a narrower application service exists, only trusted internal services may provide AI job factories.

## AI_CORE entitlement

`AiEntitlementGate` uses the existing `SubscriptionService::activeForTenant()` and the entitlement snapshot returned by `EntitlementService::forSubscription()`. Access requires an active tenant, an active/trial/past-due/grace subscription in its valid period, and an enabled `AI_CORE` entitlement on that exact subscription. Plan fallback is deliberately not accepted.

No AI-specific tenant, subscription, entitlement, feature-flag, or usage table is introduced.

## Queue policy

AI jobs use their own configurable connection and queue without changing Laravel's global queue default. Defaults and bounds are:

- attempts: `AI_MAX_ATTEMPTS=3`, accepted range 1–10;
- timeout: `AI_JOB_TIMEOUT=120`, accepted range 1–900 seconds;
- backoff: `AI_JOB_BACKOFF_SECONDS=10,30,60`, each value 1–3600 seconds;
- connection: `AI_QUEUE_CONNECTION=database`;
- queue: `AI_QUEUE_NAME=ai`.

Invalid numeric, backoff, connection, or queue values fall back to these safe defaults. `RetryableAiJobException` and `PermanentAiJobException` provide the minimal future error distinction; no provider retry behavior exists yet. Job execution and Laravel's `failed()` callback both clear `TenantContext` without logging payloads.

## Tenant knowledge storage

`TenantKnowledgeStorage` derives the active tenant from `AiTenantBoundary` and uses the immutable database primary key. Every key is placed below:

`tenants/{tenantKey}/ai/knowledge/`

Callers provide only a normalized relative key. Empty paths, absolute paths, drive paths, backslashes, control characters, empty segments, `.` and `..` are rejected. The selected disk is checked by `KnowledgeStorageGuard`; public or unknown disks fail closed. Private local and private S3-compatible disks are supported without assuming all S3 storage is public.

## Dependencies

Allowed:

- AI to Network Tenancy and Network Billing public services.
- AI jobs to Laravel bus/queue contracts.
- tenant knowledge storage to Laravel filesystem contracts.

Forbidden:

- browser or controller supplied tenant IDs;
- direct AI job dispatch outside `AiJobDispatcher`;
- plan-only entitlement fallback;
- public storage, `public_path`, or cross-tenant prefixes;
- dependencies on ZIGO, B2C, CRM, Support, Shipping, Payments, providers, SDKs, or HTTP clients.

## Pending IA-02 decisions

- application-level job factories for concrete AI workflows;
- provider adapters, credentials, request DTOs, and provider-specific retry classification;
- worker supervision and operational queue monitoring;
- storage encryption, retention deletion, and S3 lifecycle policies;
- Agent, Agent Version, Agent Contract, authorization, traces, and usage accounting.
