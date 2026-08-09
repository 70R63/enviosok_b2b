# ZN-08A.1 — Stage / Mercado Pago Sandbox Runbook

## A. Stage host architecture

All hosts point to the same Laravel release and the same `/public` document root. Code is not copied per tenant.

| Surface | Stage | Future production | Resolution |
|---|---|---|---|
| RapidGo tenant | `rapidgo-stage.zigo-envios.com` | `rapidgo.zigo-envios.com` | verified `TenantDomain` |
| ZIGO Driver | `driver-stage.zigo-envios.com` | `driver.zigo-envios.com` | `ZIGO_DRIVER_URL/HOST` |
| ZIGO Network | `network-stage.zigo-envios.com` | `network.zigo-envios.com` | `ZIGO_NETWORK_URL/HOST` |
| Payment edge | `payments-stage.zigo-envios.com` | `payments.zigo-envios.com` | `ZIGO_PAYMENTS_URL/HOST` |

`ZIGO_SUBDOMAIN_ROUTING_ENABLED=true` activates Network host enforcement. Driver and Payments routes are domain-bound independently. Unknown tenant domains remain 404.

## B. Exact Stage URLs

- OAuth start: `GET https://rapidgo-stage.zigo-envios.com/admin/configuracion/pagos/mercado-pago/conectar`
- OAuth callback: `GET https://payments-stage.zigo-envios.com/payments/mercado-pago/oauth/callback`
- Webhook: `POST https://payments-stage.zigo-envios.com/api/payments/mercado-pago/webhook`
- Edge health: `GET https://payments-stage.zigo-envios.com/payments/health`
- Customer return success: `GET https://rapidgo-stage.zigo-envios.com/app/checkout/{checkout}/pago/retorno/success`
- Customer return pending: `GET https://rapidgo-stage.zigo-envios.com/app/checkout/{checkout}/pago/retorno/pending`
- Customer return failure: `GET https://rapidgo-stage.zigo-envios.com/app/checkout/{checkout}/pago/retorno/failure`

The callback and webhook return 404 on another Host. The webhook is POST-only, API middleware means no web CSRF or customer session, and a valid signature only triggers seller-side provider verification. Browser returns never approve a checkout.

## C. OAuth correlation and cache

The start stores only a SHA-256 lookup key server-side with tenant id, initiating user id, PKCE verifier, return URL and a ten-minute TTL. The callback consumes it with `pull`, revalidates an active owner/admin membership and exchanges the code using the saved verifier. It does not require the RapidGo cookie.

Use `ZIGO_MP_OAUTH_CACHE_STORE=file` only for a single Stage instance with persistent shared release storage and permissions. Do not use `array`. For multiple instances use Redis or another shared Laravel cache and set the store name accordingly. Deploys must not erase live OAuth correlations; schedule them outside connection attempts or use shared cache.

## D. Stage environment matrix

```dotenv
APP_ENV=staging
APP_DEBUG=false
APP_URL=https://rapidgo-stage.zigo-envios.com

ZIGO_SUBDOMAIN_ROUTING_ENABLED=true
ZIGO_DRIVER_HOST=driver-stage.zigo-envios.com
ZIGO_DRIVER_URL=https://driver-stage.zigo-envios.com
ZIGO_NETWORK_HOST=network-stage.zigo-envios.com
ZIGO_NETWORK_URL=https://network-stage.zigo-envios.com
ZIGO_PAYMENTS_HOST=payments-stage.zigo-envios.com
ZIGO_PAYMENTS_URL=https://payments-stage.zigo-envios.com

SESSION_DRIVER=file
SESSION_COOKIE=zigo_stage_session
SESSION_DOMAIN=
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
CACHE_DRIVER=file
ZIGO_MP_OAUTH_CACHE_STORE=file

ZIGO_MP_ENABLED=true
ZIGO_MP_ENVIRONMENT=sandbox
ZIGO_MP_CLIENT_ID=<stage-secret-store>
ZIGO_MP_CLIENT_SECRET=<stage-secret-store>
ZIGO_MP_REDIRECT_URI=https://payments-stage.zigo-envios.com/payments/mercado-pago/oauth/callback
ZIGO_MP_WEBHOOK_SECRET=<stage-secret-store>
ZIGO_MP_WEBHOOK_URL=https://payments-stage.zigo-envios.com/api/payments/mercado-pago/webhook
ZIGO_MP_MARKETPLACE_FEE_ENABLED=false
ZIGO_MP_MARKETPLACE_FEE_AMOUNT=0.00
```

Do not set `SESSION_DOMAIN=.zigo-envios.com`. Customer/Admin intentionally coexist on the individual tenant host. Driver, Network and Payments receive suffix-specific, host-only cookies. Keep HTTPS-only, HttpOnly and SameSite Lax.

## E. PWA and tenant preparation

Driver smoke URLs:

- `https://driver-stage.zigo-envios.com/driver/manifest.webmanifest`
- `https://driver-stage.zigo-envios.com/driver/service-worker.js`
- `https://driver-stage.zigo-envios.com/driver/offline`
- start/scope: `/driver/`

The service worker is served only by the configured Driver domain, has `Service-Worker-Allowed: /driver/`, caches only the design-system CSS and neutral offline page, and never caches authenticated pages/PII. Official 192/512/maskable corporate icons remain a release prerequisite; do not invent them.

In Network, associate `rapidgo-stage.zigo-envios.com` with the RapidGo pilot as an environment-specific verified primary `TenantDomain`. Confirm uniqueness before changing primary status. This is an operator DB/application action, not part of code deployment.

## F. SiteGround readiness checklist

1. Create all four subdomains with the same release `/public` document root.
2. Issue and verify SSL certificates covering every exact host; wildcard is acceptable only if correctly installed with full chain.
3. Confirm the repository-supported PHP version and required extensions: PDO MySQL, OpenSSL, cURL, mbstring, tokenizer, XML, fileinfo, BCMath and GD as applicable.
4. Place Stage environment values outside Git; verify `APP_KEY` is stable and backed up because it encrypts OAuth tokens.
5. Keep `APP_DEBUG=false`; block public access to `.env`, Git, storage and vendor metadata.
6. Grant the PHP user write access only to `storage/` and `bootstrap/cache/`.
7. Configure persistent cache/session storage. For one instance file is acceptable only on persistent shared paths; for horizontal scale use Redis/database as supported.
8. Run Composer from the committed lock file. Default Stage release: `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction`; retain dev dependencies only in a separate controlled test runner.
9. Run config/view cache and route cache only after `php artisan route:cache` succeeds with the deployed Laravel version. This repository contains a closure health route, so route cache must be tested and skipped if unsafe.
10. Review migration status and back up the database before operator-approved migration. Never use `migrate:fresh`.
11. Scheduler is not required by ZN-08A.1 itself; preserve any existing project scheduler.
12. Keep the previous release and environment symlink available for code rollback.

## G. DevOps promotion contract

**Source:** exact reviewed commit SHA or immutable tag from `feature/zigo-payments-stage-sandbox`. **Target:** STAGE only.

Pre-deploy: clean tracked tree; expected Composer lock hash; PHP/extensions; database backup; `migrate:status`; all env names present; writable paths; SSL/DNS and cache persistence verified.

Deploy: place code in a new release directory; Composer from lock; link persistent `.env` and storage; run the approved migrations once; build safe caches; atomically switch release symlink.

Post-deploy smoke: RapidGo `/`, `/login`, `/cotizar`, `/admin/login`; Driver `/driver/login` and PWA resources; Network `/network/login`; Payments `/payments/health`; webhook GET=405 and unsigned POST=401; authenticated OAuth start redirects only to Mercado Pago.

Rollback: switch to the previous code release. Database rollback is not automatic: prefer forward-compatible migrations and a reviewed forward fix. Use `migrate:rollback --step=1` only when the exact migration is proven reversible and no new production data would be lost.

## H. Mercado Pago operator checklist

1. In **Tus integraciones**, create/select the Stage Checkout Pro marketplace integration.
2. Select Mexico and keep Stage/test credentials separate from production.
3. Enable OAuth Authorization Code and PKCE; copy App/Client ID to `ZIGO_MP_CLIENT_ID` and Client Secret to `ZIGO_MP_CLIENT_SECRET`.
4. Register the exact `ZIGO_MP_REDIRECT_URI` above—no wildcard, query string or trailing alternative.
5. In Webhooks, configure the exact HTTPS test URL from `ZIGO_MP_WEBHOOK_URL`, select the **Payments** event and save.
6. Reveal/copy the generated webhook secret once into `ZIGO_MP_WEBHOOK_SECRET`; never into tickets, logs or Git.
7. Create/use distinct Mexican test accounts: one Seller for RapidGo authorization and one Buyer for checkout. Do not use the integrator account as buyer.
8. Keep marketplace fee disabled/zero for the first test.
9. Use an incognito browser for the buyer purchase. Use current Mercado Pago test cards/status data from the operator console/docs, not copied static credentials.

## I. Approved, rejected and pending E2E

Approved path: RapidGo owner → Payments → OAuth with test Seller → CONNECTED. Separate Buyer → landing → registration/login → quote → service → addresses/package → proof → summary → Checkout Pro → approved test payment → processing return → signed webhook → provider retrieval → checkout PAID → operation confirmed → exactly one Usage → exactly one shipment/guide → pickup available.

Record before/after counts for checkout, attempt, payment event, Usage and shipment. Replay the same signed notification using the Mercado Pago simulator/retry mechanism: counts for Usage, shipment and guide must remain unchanged.

Rejected path: use the current official rejected-payment test data. Expected attempt REJECTED, checkout remains eligible for retry, and zero new Usage/shipment/guide.

Pending path: use the current official pending-payment test data. Expected attempt/checkout pending and zero new Usage/shipment/guide. Do not test refunds in this phase.

## J. Observability and security gate

Tenant Admin shows connection and checkout payment status. Network `/network/payments` shows non-secret aggregate/connection metadata. Logs use attempt UUID, tenant id, checkout UUID and provider event key; never bearer tokens, refresh tokens, client secret, webhook secret, Authorization headers or complete payment payloads.

Release gate: debug off; no test/manual-paid route; tenant web CSRF intact; webhook POST/API and signature mandatory; provider verification mandatory; one-time state; PKCE; encrypted tokens with stable `APP_KEY`; owner/admin OAuth authorization; exact edge Host; tenant/customer isolation; throttled OAuth/preference/returns; host-only cookies; persistent non-array OAuth cache; HTTPS valid.

## K. Commands — do not execute automatically

Local PowerShell:

```powershell
php artisan route:list --name=mercado-pago
php artisan route:list --path=payments
php artisan config:show zigo_surfaces
php artisan config:show zigo_payments
$env:DB_CONNECTION='sqlite'; $env:DB_DATABASE=':memory:'; php artisan test --filter='(ZigoStageHostContractTest|ZigoMercadoPagoProviderTest)'
php artisan view:cache
git diff --check
git status --short
```

Operator SSH/SiteGround (replace release paths and PHP binary; review before execution):

```bash
cd /path/to/release
git rev-parse HEAD
git status --porcelain
php -v
composer validate --no-check-publish
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan about
php artisan migrate:status
php artisan config:clear
php artisan config:cache
php artisan view:cache
php artisan route:list --name=mercado-pago
# Only after backup and explicit migration approval:
php artisan migrate --force
curl -fsS https://payments-stage.zigo-envios.com/payments/health
curl -i https://payments-stage.zigo-envios.com/api/payments/mercado-pago/webhook
```

Expected final curl: health 200; webhook GET 405. An unsigned webhook POST must return 401.
