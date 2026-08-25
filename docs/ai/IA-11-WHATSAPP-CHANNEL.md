# IA-11 — WhatsApp Conversational Channel V1

WhatsApp is a brand-neutral channel adapter over the existing Conversation Core, LIVE Runtime, Knowledge, Action Core, Handoff, and Lead/Outcome services. It does not introduce a parallel conversation or execution engine. The Meta WhatsApp Cloud API adapter is infrastructure behind a small provider contract; its Graph host and version are server configuration, never tenant-provided URLs.

## Onboarding, credentials, and webhook security

V1 uses manual Meta Cloud onboarding. Tenant owner/admin supplies Phone Number ID, optional business/display metadata, Access Token and App Secret. Secrets are encrypted at rest and write-only; a random Verify Token is shown only when created or rotated. The opaque webhook key contains no tenant, Agent, Version, or credential authority.

GET verification compares the decrypted Verify Token with timing-safe comparison. POST validates `X-Hub-Signature-256` over the raw request body using the encrypted App Secret before JSON parsing. The strict parser accepts only configured phone-number metadata, provider IDs, text, interactive reply IDs, and bounded delivery status data. Unknown, duplicate, unsupported, or disabled-channel events are safely acknowledged without Runtime work.

Authenticated inbound messages first create a durable encrypted receipt unique by Channel and provider message ID, then dispatch a tenant-aware queue job and return a fast acknowledgment. A provider redelivery re-dispatches an existing `received` receipt, closing the insert-before-dispatch crash window. Jobs claim `received → processing` under a row lock, so duplicate jobs cannot repeat Runtime. Terminal `failed` receipts are not automatically retried. Contact lookup uses SHA-256 while raw contact/profile values are encrypted. No transcript is duplicated outside Conversation Core.

## Conversation and execution

The first inbound contact resolves `Agent.current_published_version_id`, requires the exact published AgentVersion and ContractVersion, and creates a normal WhatsApp Conversation. It remains pinned to that Version/Contract; a later publication affects only new conversations. Execution is always server-side LIVE. Knowledge, READ/WRITE Actions, Lead/Outcome and stale-turn protection remain owned by their existing cores.

The customer service window defaults to 24 hours and is renewed only by an authenticated inbound visitor event using server processing time. Free-form assistant and human delivery is blocked outside that window. Before a Runtime turn is reserved, a short transaction locks Agent → Channel → Session, locks and rereads the current entitlement authority, and then uses the Conversation Core reservation. Disable or entitlement revocation before this point blocks Runtime; after reservation, the accepted turn may complete without holding locks during model/provider I/O. Normal turn contention returns the receipt to `received` and schedules a delayed retry before any Runtime or Action effect. V1 is text-only. Media, voice, files, location and business-initiated template outreach are unsupported.

WRITE Actions remain `awaiting_confirmation`. WhatsApp sends the same trusted neutral confirmation summary used by Webchat plus opaque Confirm/Cancel reply identifiers. The stored authority is a token hash tied to Channel, contact session, Conversation and ActionRun with a short TTL. Confirmation locks Channel → Session → delivery evidence before the existing Conversation → ActionRun claim. The model, browser, text such as “sí”, and provider payload cannot supply commercial authority. Double delivery/callback remains single-effect.

## Delivery, handoff, and privacy

Outbound records use a deterministic idempotency key per logical Message/kind. The provider contract accepts bounded text/confirmation DTOs; an infrastructure resolver obtains only the current Phone Number ID and Access Token. Connect and request timeouts are server-controlled, and no HTTP retry is configured. A provider timeout becomes `ambiguous` and is not blindly retried; V1 recovery is an explicit manual operational decision. Status callbacks are scoped through the authenticated Channel and its Session/tenant. `sent → delivered → read` is monotonic; stale transitions and `failed` after `delivered`/`read` are ignored. Raw provider bodies, credentials, phone values, message text, commercial snapshots and model output are not logged. Encrypted credential columns are also hidden from model array/JSON serialization.

HumanHandoff remains IA-08A. While human control is active, visitor messages enter the same Conversation but do not invoke AI; an authorized human reply is delivered through the same WhatsApp session if Channel, entitlement and service window remain valid. Disable blocks new Runtime, confirmation and human delivery. AI_CORE is the current entitlement authority; no parallel entitlement or billing system is introduced.

## Product boundary and V1 limitations

This transport is conversational infrastructure that may be reused later, but IA-11 contains no campaign manager, broadcast, segmentation, marketing journey, newsletter, promotion blast, bulk messaging, remarketing, marketing automation, template manager, Meta Embedded Signup, Billing, Usage, Marketplace, media, voice, or arbitrary public send API. There is no visible product-owner branding and no vertical logic in the WhatsApp core.
