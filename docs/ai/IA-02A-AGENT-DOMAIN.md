# IA-02A — Agent Domain

## Aggregate

`Agent` is the aggregate root. It owns one canonical `AgentContract`, sequential `AgentContractVersion` records, and sequential `AgentVersion` records. IA-02A creates only draft version 1 of each versioned branch. Acceptance, offering, approval, publication, retirement, deployments, and published pointers remain out of scope for IA-02B.

## Tables

- `ai_agents`: tenant-owned identity, type, lifecycle status, and creator.
- `ai_agent_contracts`: one canonical contract aggregate per tenant/agent.
- `ai_agent_contract_versions`: immutable-intent structured commercial and operational scope by version number.
- `ai_agent_versions`: structured agent configuration by version number, optionally linked to a contract version during draft.

All tables use bigint IDs compatible with `network_tenants` and `users`. Tenant/code, tenant/agent, and per-aggregate version uniqueness are database-enforced. Composite foreign keys enforce tenant consistency for agent, contract, and contract-version references where portable. All tenant, creator, aggregate, and version foreign keys restrict physical deletion: normal lifecycle uses archive/retire/end states, while any future physical purge must be explicit, authorized, and audited. The invariant that an Agent Version's Contract Version belongs to the same agent is enforced transactionally by the model/service because the normalized contract-version table derives agent through its contract; duplicating `agent_id` there was avoided.

## States

Backed PHP enums are stored as strings. Creation uses only `draft`:

- Agent: draft, active, paused, retired.
- Agent Version: draft, testing, approved, published, retired.
- Agent Contract: draft, active, suspended, ended.
- Agent Contract Version: draft, offered, accepted, superseded.
- Agent type: sales, customer_service, booking, quote, custom_operational.

Transitions are intentionally absent until IA-02B.

## Tenant isolation

Every tenant-owned AI model extends `AiTenantModel`. `AiTenantScope` requires an active `AiTenantBoundary` for every normal query and adds the canonical `tenant_id` predicate. Creation derives tenant ID from that boundary; `tenant_id` is not fillable and a conflicting forced value is rejected. Updates cannot change tenant ID.

Identity and structural foreign keys are immutable after creation. `AiTenantBuilder` rejects bulk updates to those columns and rejects Eloquent bulk insert/upsert operations because they bypass model events and trusted tenant derivation. Editable fields and lifecycle states remain updateable. Raw `DB::table` and SQL writes cannot be intercepted by Eloquent and are prohibited outside explicitly authorized and audited internal maintenance processes.

The explicit platform bypass is Eloquent `withoutGlobalScope(AiTenantScope::class)`. It is reserved for future audited internal platform operations and must not be used by tenant endpoints. IA-02A does not use it in application services.

Relations target other scoped AI models, so relationship queries also fail closed and remain tenant-limited. Model invariants reject cross-tenant agents/contracts and reject an Agent Version linked to a Contract Version for another agent.

## Structured configuration

`AgentConfigurationData` requires `schema_version` and structured sections: identity, goals, behavior, guardrails, qualification, handoff, capabilities, and metadata. Identity, goals, and behavior cannot be empty. Data accepts only arrays, finite scalar values, and null; objects, resources, closures, non-finite floats, secrets, credentials, and monolithic prompt keys are rejected recursively after key normalization. It is future compiler input, not a monolithic provider prompt.

## Structured contract

`AgentContractDraftData` requires a non-empty job-to-be-done, list-shaped objectives/capabilities/channels/actions, and structured policies for handoff, outcome, capacity, SLA, privacy, and pricing. Sensitive fields are rejected recursively. Pricing is descriptive only and does not trigger billing.

## Creation and authorization

`CreateAgentDraftService` applies the AI feature gate, active tenant boundary, `AI_CORE` entitlement, and existing `TenantAccessService`. Only active owner/admin memberships may create. `created_by_user_id` comes from that validated actor. Tenant ID is never part of the input DTO. A single database transaction creates Agent, Contract, Contract Version 1, and Agent Version 1 and links both version branches.

## Dependencies

Allowed: AI Core tenancy/entitlement components, Network Tenant/Membership/Billing public services, `User`, Eloquent, and database transactions.

Forbidden: ZIGO verticals, B2C, CRM, Support, Shipping, Payments, providers, credentials, HTTP clients, prompt compilers, routes, controllers, and UI.

## Pending IA-02B

- transition policies and authorization for offering/acceptance/approval/publication;
- concurrency-safe allocation of version numbers beyond version 1;
- immutable accepted/published snapshots and checksums;
- publication pointers and deployment lifecycle;
- contract acceptance identity/evidence and audit trail;
- readiness validation before approval/publication.
