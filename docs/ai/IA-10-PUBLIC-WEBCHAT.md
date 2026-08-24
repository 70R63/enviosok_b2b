# IA-10 — Public Webchat V1

Public Webchat is a horizontal channel adapter over the existing AI Conversation Core. It does not introduce a Runtime, Action executor, Knowledge engine, Lead/Outcome recorder, or Handoff lifecycle. The channel belongs to an Agent and survives publication of later versions.

## Publication and conversation binding

A new session resolves `Agent.current_published_version_id` on the server and requires that exact version to be `published` with an exact ContractVersion binding. The Conversation is pinned to that version. Publishing V2 affects new sessions only; an existing session continues its historical V1 when that immutable version is retired. There is no Draft, latest-version, Simulator, or browser-selected fallback.

## Channel and public identity

`ai_webchat_channels` stores an opaque, random public key, enable state, text-only branding, a validated hex color, and canonical allowed origins. The key identifies a channel and is not an administrator credential. Rotation prevents the old key from starting new sessions; existing sessions remain tied to the channel row until expiry, unless the channel is disabled. Disable blocks starts and messages immediately.

Tenant Admin exposes Agent → Channels → Webchat with display name, welcome message, launcher label, color, allowed HTTPS origins, hosted preview, embed snippet, enable/disable, and key rotation. Enabling requires a published AgentVersion. The existing AI_CORE entitlement and tenant owner/admin authorization are reused; no billing system is added.

## Public sessions and privacy

The server generates 256 random bits for each session bearer token. Only its SHA-256 hash is persisted. The bearer travels in the `Authorization` header, never a polling URL. It is scoped to one Channel and one existing Conversation, expires after the configured TTL (24 hours by default), and is never logged. The session table stores no transcript or visitor PII. Conversation message content remains encrypted by Conversation Core. The widget keeps the bearer in `sessionStorage`, not a third-party cookie or an administrator session. A `(session, client_message_id)` receipt is terminal once completed or failed: repeating a completed ID returns its recorded response, repeating a failed ID returns the same safe failure without calling Runtime, a model, or a handler again, and a genuine retry requires a new client ID.

## Origin, CORS, and abuse controls

Embed origins are canonical scheme/host/port values. Production accepts HTTPS only, rejects paths, userinfo, unsafe schemes, null/unlisted origins, and spoofed suffixes. Local/test permits explicit HTTP localhost. First-party hosted chat is authorized only when its canonical Origin matches `app.url`; a browser flag cannot enable hosted mode. Webchat is isolated by an early, path-specific CORS boundary: it handles Webchat preflight before Laravel's legacy global CORS authority and removes or replaces legacy headers on normal Webchat responses. It emits the exact allowed Channel Origin, never `*`, and is a no-op for every non-Webchat path. The broader legacy CORS configuration remains unchanged for other modules.

Named Laravel limiters cover session creation, messages, confirmation attempts, and polling using a hash of Channel, IP, and (where present) session token. Defaults are server configuration. Messages must be valid, non-empty UTF-8 and no larger than 8,000 bytes.

## Live Conversation and Actions

The Webchat Conversation uses `AiExecutionMode::Live`. Each message uses Conversation Core reservation/sequencing and a client UUID backed by a unique, content-free receipt for durable idempotency. Model calls remain outside database transactions. Knowledge retrieval, citations, Lead/Outcome candidates, and HumanHandoff use their existing services.

READ Actions pass through the trusted Registry, current tenant entitlement, pinned Contract allowlist, schema validation, ActionExecutor, handler, result validation, and Pass 2. WRITE Actions stop at `awaiting_confirmation`. The public confirmation endpoint accepts only a session bearer and ActionRun UUID; it verifies the ActionRun belongs to that session's Conversation. It does not accept price, provider, route, result, or commercial authority. Its linearization point is the short transaction ordered as Channel → Session → Conversation → ActionRun: enabled state, TTL/closure, tenant, Agent, and Conversation ownership are revalidated before ActionRun changes atomically to `executing`. Disable or expiry committed before that claim rejects confirmation; a claim committed first may complete afterward. No handler, provider, or model call is inside that transaction. Existing Action idempotency makes retry/double confirmation single-effect. Confirmation summaries cross a trusted Action presenter boundary and expose only a bounded neutral `title` plus allowlisted `label`/`value` fields. The Webchat core contains no vertical presentation branches. The guide presenter resolves its authoritative server-side quote snapshot and exposes only service, origin, destination, final total, and currency; it never exposes raw input, provider cost, margin, credentials, or internal identifiers. The browser cannot submit or modify this summary as execution authority.

When Runtime requests Handoff, IA-08A creates the operational HumanHandoff and blocks AI while human control is active. Human replies use the existing inbox/service and are returned through sequence-based polling for the same session only.

## Widget and hosted preview

The vanilla JavaScript widget uses Shadow DOM, namespaced styles, DOM APIs and `textContent`; tenant, visitor, model, and human strings are never assigned to `innerHTML`. It includes keyboard-focusable controls, labels, a responsive panel, message composer, and WRITE confirmation button. The snippet contains only the cacheable widget URL and public channel key. Hosted preview uses the same widget and API. Hosted rendering and API start share the public availability resolver: enabled Channel, coherent tenant/Agent, current published Version with an exact Contract binding, and current AI_CORE entitlement. A rotated key cannot open a hosted page or start a session; an existing bearer may continue through the retired-key alias until TTL unless the Channel is disabled.

## Limits and exclusions

V1 is Webchat only. It has polling rather than WebSockets/SSE, no attachments, voice, visitor accounts, transcript export, WhatsApp, Telegram, Messenger, Instagram, Billing, commercial Usage/Credits, Marketplace, customer arbitrary code, or Generic HTTP Actions. Provider and MySQL concurrency are exercised through fakes and SQLite in tests; production provider calls occur only through the configured existing gateway.
