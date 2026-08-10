# ZN-SEC-01 — Security audit and release gate

Audited branch: `feature/zigo-security-stage-release-gate`  
Baseline commit: `6fcfa70ccea1a0353eb6e529c64ad951ce3eeba2`  
Audit date: 2026-08-09

## Executive result

The application-level controls inspected are materially stronger: CSRF now covers logout, sessions regenerate after successful authentication, surface cookies remain host-only, trusted hosts are explicit outside local/test, outbound API webhooks cannot follow redirects, Stage emits noindex and security headers, POD images are re-encoded without EXIF, and orphan POD evidence has a conservative cleanup command. No business rule was changed.

The release is **NOT READY**. Dependency and build blockers remain: Composer reports high advisories in Laravel 9, Guzzle and CommonMark; npm reports one critical and nine high development/build dependencies; the frontend build cannot run from the current worktree because the Vite executable is absent; and Network has no mandatory 2FA. These require dedicated dependency/framework and 2FA work, not an unsafe blind upgrade in this gate.

## Findings

### Critical open (1)

- Frontend build dependency chain: `form-data` is critical in the full npm audit. It is dev/build-time rather than a production runtime dependency, but the release artifact is not reproducible or acceptable until the lockfile is upgraded and a clean `npm ci && npm run build` passes. Runtime-only npm audit reports one low issue (`sweetalert2`).

### High open (4)

- PHP dependencies: current lock is affected by high advisories in `laravel/framework`, `guzzlehttp/guzzle`, and `league/commonmark`. Laravel 9 has no patched 9.x range for the reported 2026 CRLF advisory, so remediation requires a controlled framework upgrade.
- Network authentication has rate limiting, host isolation, session regeneration and superadmin authorization, but no mandatory 2FA. This is a PRE-PRD blocker and remains a Stage security exception requiring explicit acceptance or implementation.

### High remediated in this change

- Removed the global CSRF exception for `logout`.
- Enabled trusted-host validation outside local/test, driven by an explicit allowlist/base-domain contract.
- Disabled redirects on the legacy API Hub outbound webhook client, closing a redirect-to-private-network SSRF path after initial DNS validation.
- Removed an assigned `APP_KEY` from `.env.example` and removed hard-coded Estafeta tracking defaults from a DTO.
- Re-encoded POD JPEG/PNG/WEBP before private storage, stripping EXIF and rejecting invalid/decompression-heavy images.

### Medium/low backlog

- Move CSP from report-only to enforcement after reviewing Stage reports; the compatibility policy currently permits inline script/style because legacy Blade surfaces rely on them.
- Normalize image attachments in Support Center as POD images are normalized; current support controls already enforce private disk, server MIME, size, generated stored name and authorized download.
- Replace abandoned Composer packages (`doctrine/annotations`, `laravelcollective/html`, `spatie/data-transfer-object`) in scoped upgrades.
- Upgrade the runtime `sweetalert2` low advisory.
- Official ZIGO Driver 192/512/maskable icons remain a Stage warning and PRD presentation blocker.
- Introduce shared Redis cache before horizontal scaling. File/database cache is acceptable only for a single Stage instance.

## Authentication and surface matrix

| Surface | Authentication | Authorization/context | Cookie/host boundary |
|---|---|---|---|
| Customer | global User password | active TenantCustomerProfile for resolved tenant | tenant host, CSRF, regenerated session |
| Tenant Admin | global User password | active tenant membership/admin middleware | tenant host, CSRF, regenerated session |
| Driver tenant-host | global User password | driver role + ACTIVE DriverProfile in resolved tenant | tenant host, CSRF |
| Driver central | global User password | server-resolved eligible workspaces; active context UUID in session | dedicated host and `_driver` host-only cookie |
| Network | global User password | `network.auth` + sysadmin/superadmin | dedicated host and `_network` host-only cookie |
| API Hub V1 | Bearer hash-only API key | key → client → tenant → subscription/entitlement/scope/quota | configured API host; no user-session authority |
| Payment Edge | no user login for webhook; server OAuth correlation for callback | signature/state/provider verification | dedicated host; no tenant session trust |

Authentication in one surface is not itself authorization in another. API keys never become User sessions and Payment Edge does not infer tenant from request input.

## Tenant-isolation result

Existing bounded-context tests cover customers, operations, shipments/guides, checkouts/payments, drivers/POD, support, SaaS orders, API clients/keys/webhooks and quota. Resource queries are tenant/customer/client constrained and the browser cannot supply authoritative `tenant_id`. Host isolation is tested separately. Full regression execution is listed in the release manifest; no claim of Stage readiness is made while the dependency blockers are open.

## Payments

Seller OAuth state is cached with TTL/one-time consumption and PKCE; tokens use encrypted model casts. Seller and platform credentials are separate. Browser returns do not approve payments. Webhooks require signature and retrieve provider state before amount, currency, seller, external reference and idempotent fulfillment checks. StageSafetyGuard rejects seller production mode in Stage and requires HTTPS, secure host-only cookies and persistent cache.

## API Hub and SSRF

V1 keys remain hash-only and one-time display; scopes, API entitlement, subscription, monthly quota, per-key rate limit, ownership and idempotency remain enforced. Webhook URLs reject credentials, non-HTTP schemes, local/private/reserved addresses and require HTTPS outside local/testing. DNS A/AAAA records are validated before each delivery. Both delivery implementations now disable HTTP redirects, preventing a public endpoint from redirecting the client to a private address. DNS rebinding risk is reduced but a future hardened egress proxy/IP pinning layer is recommended for multi-tenant production scale.

## Uploads, POD and downloads

Support attachments are private, limited to JPEG/PNG/WEBP/PDF by server MIME and size, stored under generated names, and served through authorized controllers with safe disposition and `nosniff`. POD evidence is private and authorization-scoped; images are now decoded/re-encoded, stripping EXIF. `zigo:security:cleanup-orphan-evidence` scans only POD directories, ignores files newer than 48 hours, checks proof/attempt references and defaults to report-only unless `--delete` is supplied. Guides, POD, Support and invoice documents remain controller/service-authorized; paths are never accepted directly from users.

## Sessions, CSRF, headers and errors

- Required Stage: `SESSION_SECURE_COOKIE=true`, `SESSION_DOMAIN=` and `SESSION_SAME_SITE=lax`; HttpOnly is always enabled.
- Successful logins regenerate sessions; logout invalidates session and regenerates CSRF token.
- All web state-changing routes use web CSRF. Payment/API endpoints live outside broad CSRF exceptions and use their signature/key contracts.
- Global headers: `nosniff`, `DENY`, strict-origin referrer policy, restrictive Permissions-Policy and CSP report-only. Stage adds `X-Robots-Tag: noindex, nofollow, noarchive`.
- `APP_DEBUG=false` is enforced by the Stage gate; API V1 retains its JSON error contract.

## Secrets and logging

The tracked-file scan reports no confirmed provider/API private secret after remediation. False-positive patterns were found in minified/font vendor assets and were not credentials. An assigned example APP key and legacy DTO credential defaults were removed; any key ever used outside local development must still be rotated operationally. Payment/API logs use UUIDs and safe status metadata, not authorization headers or tokens. `.env`, logs and private storage must never enter a release artifact.

## Linux / PSR-4 / TCPDF

Strict optimized autoload audit identified and corrected namespace/case mismatches for DHL labels, Estafeta DTOs/tracking classes and `RepesajePolicy`. A temporary isolated autoload build loaded `TCPDF` 6.10.1 successfully. This validates the current package, not a clean network install; the definitive release test remains a clean Composer install from the lockfile on Linux.

## Audit trails

Database ledgers/events remain the source of truth for payments, SaaS activation, Usage, API keys/usage, Support, Driver delivery/POD and webhook deliveries. Application logs are troubleshooting aids only.
