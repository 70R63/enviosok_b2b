# IA-02B — Agent lifecycle

## Boundary and authorization

Every public lifecycle and version-creation operation applies `AiFeatureGate`, active `AiTenantBoundary`, `AiEntitlementGate` for `AI_CORE`, and `TenantAccessService::canManageTenant`. Tenant and creator identifiers are never accepted from DTOs. Owner/admin must have an active membership in the current tenant. `AuthorizedAiLifecycleActor` is an internal, non-serializable snapshot of one successful check, not a permanent capability: services revalidate it inside the transaction, every named model transition revalidates immediately before persistence, and audit revalidates before insertion. Context changes, disabled AI, inactive tenants, revoked entitlements, suspended memberships, downgraded roles and deleted users therefore fail closed.

The supported runtime trust boundary is `HTTP/Controller/Job → authorized lifecycle service → tenant-scoped models → database`. Arbitrary in-process PHP using Reflection, container manipulation, `DB::table()` or raw SQL is privileged internal execution outside that boundary. Such access is architecturally prohibited and should be caught by review/architecture tests where possible; it is not addressed with static secrets, global flags or supposedly unforgeable PHP tokens.

## Contract lifecycle

Contract Versions transition `draft → offered → accepted|rejected|withdrawn`; a previously accepted version becomes `superseded` when a newer offer is accepted. Aggregate termination additionally permits `draft → cancelled` and `offered → withdrawn`. Offered content is frozen. Offer computes canonical SHA-256 over contractual content only. Acceptance recalculates and compares both stored and caller-expected hashes, records structured evidence, activates the contract, and moves its accepted pointer. Evidence is operational proof, not a certified electronic signature, and causes no billing.

## Agent Version lifecycle

Agent Versions transition `draft → testing → approved → published → retired`, with `testing → draft` for corrections. Testing hashes `schema_version` plus structured configuration. Approval requires structured manual-review evidence and a matching hash. Publication rechecks the hash and requires the same Agent's current accepted Contract Version and an active Contract. Publication is logical only; no deployment or runtime is created. Repeating publication of the current version is idempotent.

## Aggregate lifecycle

Agents support active/paused/resumed/retired transitions. Contracts support active/suspended/resumed/ended transitions. Suspending a Contract pauses an active Agent but resuming it does not resume the Agent. Ending a Contract or retiring an Agent uses one coordinator transaction: all work Agent Versions are retired, draft Contract Versions are cancelled, offered Contract Versions are withdrawn, both current pointers are cleared, the Contract is ended and the Agent is retired. There is no recursive public-service call or duplicate event. Reasons are trimmed, limited to 500 characters and reject control characters. Records are never physically deleted.

## Hashing

`CanonicalJsonHasher` validates through `StructuredDataGuard`, sorts associative keys recursively, preserves list order and Unicode, and encodes with `JSON_PRESERVE_ZERO_FRACTION`. Consequently `1` and `1.0` differ; `-0.0` and `0.0` are deterministic and distinct. Sparse numeric arrays are JSON objects whose keys are sorted. Invalid Unicode fails closed. The result is SHA-256, while full contractual or configuration content is never placed in audit metadata or exceptions.

## Concurrency and mutations

Within the global `Agent → AgentContract → AgentContractVersion → AgentVersion` order, multiple rows of either version table are locked by numeric `id` ascending.

All services use the global lock order `Agent → AgentContract → AgentContractVersion → AgentVersion`. Caller-provided model instances are used only to resolve scoped parent IDs; rows are reloaded after locks. Version numbers use `MAX(version_number) + 1` inside the transaction, with unique constraints as the final defense and bounded transaction attempts. SQLite tests verify behavior and constraints, but do not claim row-lock equivalence with MySQL. Only one Agent work version (`draft/testing/approved`) and one Contract work version (`draft/offered`) may exist.

There is no global/static lifecycle mutator or arbitrary callback boundary. Each model exposes `@internal` named transitions with an exact per-instance column allow-list; permission is cleared through `finally` and cannot enable another model, request, worker or coroutine. Named transitions require an active transaction, current reauthorization, matching tenant, valid persisted source state and transition-specific aggregate/hash/evidence invariants. Architecture tests limit their callers to lifecycle services. Protected bulk update, increment/decrement extras, insert/upsert/insertGetId and append-only delete/truncate paths are rejected. Draft-editable fields continue to use normal saves. `withoutGlobalScope`, `DB::table`, and raw SQL remain prohibited outside explicitly authorized, audited internal maintenance; Eloquent cannot technically intercept raw SQL.

The current pointers are same-aggregate constraints, not merely same-tenant references. MySQL uses composite `RESTRICT` foreign keys. Because SQLite cannot add a foreign key to an existing table, the SQLite migration creates equivalent insert/update constraint triggers; both variants permit `NULL` during retirement/finalization.

## Audit

The existing Network audit was not reused because it lacks tenant ownership, fail-closed behavior, lifecycle metadata, and append-only enforcement. `ai_agent_lifecycle_events` is a tenant-scoped append-only record containing the freshly reauthorized actor, verified aggregate/resource IDs and types, transition, reason, timestamp, and non-sensitive metadata. `AgentLifecycleAudit` exposes event-specific methods rather than a generic event/from/to API; each method requires the surrounding transaction and verifies the persisted target state. Architecture tests restrict the internal event append call to this audit service. Eloquent update, quiet update, delete, quiet delete, bulk delete, increment/decrement, insert/upsert and truncate are blocked. Hash checks are recorded only as `hash_verified=true`. It never stores full hashes, configuration, contractual content, prompts, secrets or IP addresses. Raw administrative SQL remains a prohibited, separately audited maintenance bypass rather than a database-level append-only guarantee.

## Deferred

INNOTECH Super Admin delegation, certified signatures, Readiness Score/Simulator, deployments, runtime, providers, channels, knowledge, conversations, usage, and billing remain outside IA-02B. MySQL row-lock and deadlock behavior still requires isolated integration validation; SQLite verifies business behavior and constraints but not production locking semantics.
