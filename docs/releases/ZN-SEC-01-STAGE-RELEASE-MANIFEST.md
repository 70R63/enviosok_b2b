# ZN-SEC-01 — Stage release manifest and runbook

## Release candidate

- Branch: `feature/zigo-security-stage-release-gate`
- Audited base SHA: `6fcfa70ccea1a0353eb6e529c64ad951ce3eeba2`
- Target: SiteGround Stage, Linux, HTTPS
- Final release SHA/tag: fill only after reviewed changes are committed; deployment must use that immutable SHA.

## Stage host contract

| Host | Surface |
|---|---|
| `stage.zigo-envios.com` | Existing ZIGO original/B2C; smoke only, do not replace |
| `rapidgo-stage.zigo-envios.com` | fictitious RapidGo Local demo tenant/customer/admin |
| `driver-stage.zigo-envios.com` | central ZIGO Driver PWA |
| `network-stage.zigo-envios.com` | internal Network |
| `payments-stage.zigo-envios.com` | Mercado Pago callback/webhook edge |
| `api-stage.zigo-envios.com` | API Hub V1 |

All hosts point to the same release and Laravel `/public`. `ZIGO_TRUSTED_BASE_DOMAINS=zigo-envios.com` enables the intended subdomains; a stricter exact list may be supplied with `ZIGO_TRUSTED_HOSTS`. `rapidgo-stage` must be associated with the demo tenant through TenantDomain by an operator; no database mutation is part of this change.

## Required Stage environment

```dotenv
APP_ENV=staging
APP_DEBUG=false
APP_URL=https://stage.zigo-envios.com
APP_KEY=<stage-only-generated-key>
ZIGO_SUBDOMAIN_ROUTING_ENABLED=true
ZIGO_TRUSTED_BASE_DOMAINS=zigo-envios.com
ZIGO_CSP_REPORT_ONLY=true

SESSION_DRIVER=file
SESSION_DOMAIN=
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
CACHE_DRIVER=file
MAIL_MAILER=log

ZIGO_DRIVER_HOST=driver-stage.zigo-envios.com
ZIGO_DRIVER_URL=https://driver-stage.zigo-envios.com
ZIGO_NETWORK_HOST=network-stage.zigo-envios.com
ZIGO_NETWORK_URL=https://network-stage.zigo-envios.com
ZIGO_PAYMENTS_HOST=payments-stage.zigo-envios.com
ZIGO_PAYMENTS_URL=https://payments-stage.zigo-envios.com
ZIGO_API_ENABLED=true
ZIGO_API_HOST=api-stage.zigo-envios.com
ZIGO_API_URL=https://api-stage.zigo-envios.com

ZIGO_MP_ENABLED=true
ZIGO_MP_ENVIRONMENT=sandbox
ZIGO_MP_CLIENT_ID=<stage-secret>
ZIGO_MP_CLIENT_SECRET=<stage-secret>
ZIGO_MP_REDIRECT_URI=https://payments-stage.zigo-envios.com/payments/mercado-pago/oauth/callback
ZIGO_MP_WEBHOOK_URL=https://payments-stage.zigo-envios.com/api/payments/mercado-pago/webhook
ZIGO_MP_WEBHOOK_SECRET=<stage-secret>
ZIGO_MP_MARKETPLACE_FEE_ENABLED=false
ZIGO_MP_MARKETPLACE_FEE_AMOUNT=0.00
ZIGO_MP_OAUTH_CACHE_STORE=file

ZIGO_MP_PLATFORM_ENABLED=true
ZIGO_MP_PLATFORM_ACCESS_TOKEN=<zigo-stage-platform-secret>
ZIGO_MP_PLATFORM_ACCOUNT_ID=<stage-account-id>
ZIGO_MP_PLATFORM_WEBHOOK_SECRET=<stage-secret>
ZIGO_MP_PLATFORM_WEBHOOK_URL=https://payments-stage.zigo-envios.com/api/payments/mercado-pago/webhook
```

Single-instance Stage may use file cache/sessions with writable shared `storage`; multi-instance deployment requires Redis/database-backed shared cache/session before OAuth traffic. Do not use array cache. Keep seller test and buyer test accounts different. Do not put secrets in Git.

## SiteGround requirements

- PHP 8.2 CLI/FPM consistently (project constraint permits 8.0.2+, but audited runtime is 8.2.12).
- Extensions: ctype, curl, dom/libxml, fileinfo, filter, gd, hash, iconv, json, mbstring, openssl, PDO MySQL, session, tokenizer and zip. GD is mandatory for POD metadata stripping.
- Composer 2 and Node 20 LTS for the dependency-remediation/build track (local Node 18 is not the target build runtime).
- `memory_limit` at least 256M; `upload_max_filesize` and `post_max_size` at least the configured support/POD limits plus form overhead; `max_execution_time` at least 60 seconds for controlled HTTP operations.
- Document root points to `<release>/public`; `storage` and `bootstrap/cache` writable by PHP, source/vendor not web-writable.
- HTTPS certificate covers all six Stage hosts. Force HTTPS at the edge; forward protocol correctly to Laravel.
- Stage mail uses `log`/safe sink until explicitly approved. Stage must not send demo mail to real recipients.

## Reproducible build policy

`composer.lock` and `package-lock.json` are sources of truth. Never copy Windows `vendor`, local `.env`, `node_modules`, private `storage`, or logs. Although 7,986 vendor files are currently tracked historically, the release build must run Composer from the lockfile in a clean release directory. A dedicated follow-up must remove tracked vendor safely; do not mix that massive change into this security patch.

Safe local cleanup after reviewing the four known Composer diffs (operator command, not executed here):

```powershell
git diff -- vendor/composer/autoload_classmap.php vendor/composer/autoload_static.php vendor/composer/installed.json vendor/composer/installed.php
git restore --worktree -- vendor/composer/autoload_classmap.php vendor/composer/autoload_static.php vendor/composer/installed.json vendor/composer/installed.php
composer install --dry-run --no-interaction
php -r "require 'vendor/autoload.php'; exit(class_exists('TCPDF') ? 0 : 1);"
git status --short
```

Do not restore until confirming `composer.lock` contains `tecnickcom/tcpdf` 6.10.1. For the release builder:

```bash
git clone --no-local <repository> zigo-stage-release
cd zigo-stage-release
git checkout --detach <RELEASE_SHA>
git status --porcelain   # must be empty
composer validate --strict --no-check-publish
composer audit --locked
composer install --no-dev --prefer-dist --optimize-autoloader --classmap-authoritative --no-interaction
php -r "require 'vendor/autoload.php'; exit(class_exists('TCPDF') ? 0 : 1);"
npm ci
npm audit
npm run build
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Current audits/build fail; these commands become the acceptance test after dependency remediation.

## Migration plan

The accumulated bounded-context migrations apply in filename order:

1. `2026_08_14_100000_create_tenant_payment_foundation.php`
2. `2026_08_15_100000_create_zigo_saas_billing_foundation.php`
3. `2026_08_16_100000_create_zigo_support_center.php`
4. `2026_08_17_100000_create_tenant_api_hub_v1.php`

Earlier unapplied migrations shown by `php artisan migrate:status` must also run in global filename order. This security change adds no migration.

Pre-deploy:

```bash
php artisan migrate:status
php artisan migrate --pretend --force
```

Create and validate a backup before migration:

```bash
umask 077
mysqldump --single-transaction --routines --triggers -h "$DB_HOST" -u "$DB_USERNAME" -p "$DB_DATABASE" > "zigo_stage_$(date +%Y%m%d_%H%M%S).sql"
test -s zigo_stage_*.sql
gzip -t zigo_stage_*.sql.gz  # only if compressed
```

Restore is an operator-approved destructive action: create an empty recovery database, import the backup there first, compare table counts and application smoke results, then schedule the actual restore if required. Never test restore over the active Stage database.

## Deploy / rollback

1. Record release SHA, environment checklist and DB backup checksum.
2. Deploy immutable code into a new release directory.
3. Install dependencies/build assets as above.
4. Link Stage `.env` and persistent `storage`; never copy local data.
5. Run `php artisan migrate --force` only after successful pretend/backup.
6. Run `config:cache`, `route:cache`, `view:cache`; atomically switch document-root symlink/release pointer.
7. Run smoke matrix below.

Rollback code by switching to the previous immutable release and clearing/rebuilding caches. Database rollback uses a reviewed migration-down plan only when data loss is impossible; otherwise restore the validated backup in a maintenance window. Payment/webhook events received during rollback must be reconciled by provider IDs before reopening traffic.

## Cron matrix

| Command | Frequency | Purpose | Failure impact |
|---|---:|---|---|
| `php artisan schedule:run` | every minute | executes Laravel schedule | umbrella requirement |
| `rastreo:automatico` | hourly (scheduler) | carrier tracking refresh | delayed tracking |
| `api-hub:webhooks:deliver --limit=100` | every minute (scheduler) | legacy API webhook delivery | delayed partner events |
| `zigo:security:cleanup-orphan-evidence --delete` | daily 03:20 | delete unreferenced POD files older than guard window | storage growth only |
| `zigo:api-v1-deliver-webhooks` | review before enabling separately | V1 alternate command exists but is not scheduled | possible V1 delivery gap; consolidate commands before Stage API E2E |

SiteGround needs only the single `schedule:run` cron if its CLI execution is reliable. Capture output/exit status and alert operationally; do not run overlapping manual webhook jobs.

## Host and smoke matrix

- ZIGO original: `https://stage.zigo-envios.com/`, existing quote and CP 64000; verify no visual replacement.
- RapidGo demo: `/`, `/registro`, `/login`, `/cotizar`, `/app`, `/admin/login`; display/communications must call it demo, not client/partner.
- Driver: `/driver/login`, `/driver/manifest.webmanifest`, `/driver/service-worker.js`, `/driver/`; verify manifest scope/start `/driver/`, offline page neutral and no authenticated response in Cache Storage.
- Network: `/network/login`, `/network`, `/network/topology`, `/network/payments`, `/network/api-hub`, `/network/support`; all other Stage hosts must 404 these routes.
- Payments: `/payments/health`; callback `/payments/mercado-pago/oauth/callback`; POST `/api/payments/mercado-pago/webhook`; unsigned webhook must be 401 and GET must be 405.
- API: invalid Bearer on `/api/hub/v1/postal-codes/64000` returns 401 JSON; V1 route must 404 on non-API hosts; legacy `/api/hub/cp/{cp}` remains compatible.
- Mandatory postal checks: `/postal-code/lookup/64000`, `/b2c/cp/colonias?cp=64000`, CP 64000 in quick quote and new shipment.

## Post-deploy E2E

- Customer shipping: demo registration → quote → proof → Mercado Pago sandbox → signed webhook → PAID → exactly one Usage/shipment/guide → pickup → Driver attempts/POD → delivered/tracking.
- SaaS: Marketplace order → ZIGO sandbox platform account → verified webhook → activation once → entitlement/allowance.
- Support: Customer↔tenant, tenant↔Network and Driver operational/platform tickets; internal notes and attachments remain private.
- API Hub: API entitlement → client/key → postal/quote/shipment/guide/pickup/tracking → signed minimal webhook; repeat idempotency keys and verify quota once.
- Payments: APPROVED, PENDING, REJECTED and duplicate webhook using different seller/buyer test accounts; never production money.

## Gate checklist

- [ ] Composer audit has zero critical/high advisories or approved documented compensating controls.
- [ ] Full npm audit has zero critical/high and clean `npm ci && npm run build` passes.
- [ ] Controlled Laravel upgrade remediates the unpatched Laravel 9 advisory.
- [ ] Network mandatory 2FA implemented or explicit Stage-only risk acceptance with PRE-PRD blocker.
- [ ] Clean Linux build passes strict PSR-4 and TCPDF guide smoke.
- [ ] Worktree/release tree contains no local vendor diffs.
- [ ] Stage env passes StageSafetyGuard; secrets provisioned externally.
- [ ] Backup restore drill validated in a separate database.
- [ ] All automated regression and smoke matrices pass.
- [ ] Official PWA icons tracked as PRD blocker (not a Stage web blocker).
