# IA-14 — Public AI Self-Service Onboarding

IA-14 adds a small public, product-facing entry point for **Agentes IA**.
It reuses the existing SaaS onboarding, tenant, subscription, payment and
provisioning aggregates; it does not create a second tenancy or payment
system.

## Public routes

- `GET /agentes-ia` — neutral product landing.
- `GET /agentes-ia/precios` — active AI base offer from the commercial catalog.
- `GET /agentes-ia/comenzar` and `POST /agentes-ia/comenzar` — contact and
  company capture, subdomain reservation and commercial snapshot creation.
- The resulting opaque onboarding token is used for summary, checkout and
  return routes. Internal tenant, subscription and entitlement identifiers are
  not exposed.

## Catalog and onboarding reuse

The base offer is selected by active catalog metadata (`ai_product=true` and
`ai_kind=base`), never by a price or hard-coded product ID. A technical
`AI_AGENTS` plan is created/reused and linked to `AI_CORE` only when the
customer starts the AI flow. `SaasOnboardingApplication` stores the frozen
commercial snapshot and the normal `PlatformPaymentService` starts checkout.
Verified payment processing remains the authority for provisioning.

The existing `SaasTenantProvisioningService` creates the single Tenant, owner,
subscription and entitlement. When the snapshot is an AI product,
`AiCommercialActivationService` applies the catalog capacities through the
IA-12 capacity authority. Replays are safe because the onboarding purchase key,
order and activation services are idempotent.

## AI-only and combined tenants

An AI-only tenant uses the same Tenant Admin and receives only AI capabilities
authorized by `AI_CORE`; it does not acquire logistics modules. A tenant that
already has logistics keeps that subscription and can add AI to the same
Tenant. The first-agent experience continues through the existing Launchpad
and IA-12 limits remain authoritative.

## Security and scope

The public flow validates the opaque application token, uses the existing
subdomain reservation and CSRF/throttle middleware, and never accepts tenant,
subscription or entitlement IDs as authority. Checkout still validates the
Mercado Pago redirect returned by the existing payment service; tests use fakes
and make no provider requests.

Stage catalog prices are provisional and configurable in the commercial
catalog; they are not the final commercial tariff. IA-14 does not implement
Marketing, a public logistics funnel, a new provider, billing APIs, or a new
Marketplace. Product-facing branding is neutral: **Agentes IA** / **AI Agents**.

## Verification

The focused tests cover public route availability, catalog selection,
AI-only separation, combined tenants, provisioning idempotency, AI_CORE and
capacity application, tenancy boundaries and neutral branding. The relevant
AI, SaaS onboarding, payment and Tenant Admin regression suites remain the
required verification set; all external HTTP calls are prevented in tests.
