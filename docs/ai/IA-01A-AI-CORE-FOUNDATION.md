# IA-01A — AI Core Foundation

## Bounded context

`App\Domain\AI` is the provider-neutral foundation for INNOTECH AI Agents. IA-01A contains feature gating, tenant execution, future runtime contracts, queue conventions, provider registration boundaries, and private knowledge-storage validation. It intentionally contains no agents, conversations, knowledge entities, channels, provider adapters, or public endpoints.

The existing `Network\Tenancy` tenant is canonical. AI must never create a parallel tenant, plan, subscription, entitlement, usage, API-key, or webhook domain.

## Dependencies

Allowed dependencies:

- `AI` to `Network\Tenancy` for `Tenant`, `TenantContext`, and active-tenant validation.
- Future AI application services to Network Catalog/Billing through explicit interfaces or existing public services.
- Laravel container, queue, configuration, and private filesystem abstractions.

Forbidden dependencies:

- AI Core to ZIGO, B2C, Shipping, CRM, Support, Driver, carrier, payment, or UI implementations.
- Provider-neutral contracts to a concrete model vendor or SDK.
- Public endpoints or provider calls while `AI_ENABLED=false`.
- Knowledge content on a public filesystem disk.

## Tenant strategy

`AiTenantBoundary` is used by request flows after the existing portal middleware has resolved `TenantContext`. It fails closed without an active tenant and verifies every AI-owned resource's `tenant_id`. It does not replace the existing HTTP tenant resolver.

Every future AI table owned by a tenant must have `tenant_id`, tenant-prefixed indexes, and queries guarded by `AiTenantBoundary`. Cross-tenant foreign references should be prevented with database constraints where practical.

## Job strategy

Future AI jobs extend `TenantAwareAiJob`. Each payload transports `tenantId`; the base job selects `AI_QUEUE_CONNECTION` and `AI_QUEUE_NAME`, applies `AI_MAX_ATTEMPTS`, and runs through `AiTenantJobExecution`. Jobs fail while AI is disabled or when the tenant is missing/inactive. `AiTenantJobExecution` is exclusively for queue workers: it clears residual context before execution and never restores a previous context because that context may belong to another job. It must not be used as a synchronous HTTP executor.

The initial database connection is supported without changing Laravel's global queue default. The `jobs` migration is supplied because the repository only had `failed_jobs`; it must be deployed through the normal reviewed migration process.

## Storage strategy

`AI_KNOWLEDGE_DISK` defaults to Laravel's private `local` disk. `KnowledgeStorageGuard` rejects the `public` disk, any disk configured with public visibility, and unknown disks. Future ingestion must call this guard before storing or reading knowledge content and must use tenant-specific prefixes.

## Provider-neutral contracts

- `ModelGateway`: structured generation request/response.
- `EmbeddingGateway`: vectorization without vendor types.
- `VectorStore`: namespace-scoped upsert and search.
- `ToolExecutor`: named tool execution with structured context.
- `TraceSink`: structured trace recording.

There are no bindings for these contracts in IA-01A. `ProviderRegistry` first checks the feature gate and then fails if no future adapter has been explicitly bound.

## Pending IA-01B / IA-02 decisions

- Typed request/response value objects and error taxonomy for model providers.
- Provider selection and credential ownership.
- Exact queue topology, worker supervision, timeouts, and retry/backoff policy.
- Private S3 configuration, encryption, tenant prefixes, retention, and deletion workflows.
- Vector-store technology and tenant namespace enforcement.
- Agent/version/contract persistence and authorization.
- Usage dimensions, traces, redaction, evaluations, and guardrails.
- Public-channel authentication and origin validation for Webchat.
- IA-01B supplies the trusted `AiJobDispatcher`; future entry points must use it rather than dispatching `TenantAwareAiJob` directly.
