# ZN-SEC-02 dependency, 2FA and build contract

## Dependency remediation

The former npm lock resolved Axios 1.6.7 through `form-data` 4.0.0 and an old Vite/Sass/PostCSS toolchain. The final lock resolves Axios 1.19.0, `form-data` 4.0.6, Vite 6.4.3, Laravel Vite Plugin 1.3.0, Tailwind 3.4.19 and Sass 1.89.2. Tailwind is direct and pinned to major 3 because the existing PostCSS contract is Tailwind 3. `vite.config.mjs` makes the Vite configuration explicitly ESM. A clean `npm ci`, `npm audit` and `npm run build` is mandatory; global Vite is never used.

Release builders use Node 20.19.x and npm 10.x. Node 18.18–18.x remains accepted by this repository for transitional local builds and was used for the Windows isolated validation. Node 21/22 is not part of this release contract.

Composer now targets PHP 8.2 and the minimum secure supported framework line, Laravel 12 (`12.65.0` in the lock). The controlled compatibility update also locks Guzzle 7.15.3, CommonMark 2.9.1, Sanctum 4.3.3 and PHPUnit 11.5.56. `laravelcollective/html` is replaced by the namespace-compatible maintained fork `rdx/laravelcollective-html`. TCPDF remains lock-built at 6.10.1. Composer reports no security advisories. `doctrine/annotations` and `spatie/data-transfer-object` are abandoned transitive packages and remain a non-release-blocking maintenance backlog; neither currently has a security advisory.

## Mandatory Network TOTP

Only the ZIGO Network surface requires this factor. A valid sysadmin password creates a short-lived pending-login correlation and leaves the user unauthenticated. An unenrolled sysadmin must enroll TOTP and verify the first code; an enrolled sysadmin must pass a TOTP or one-time recovery code. Only then is the user authenticated, the session ID regenerated, and `network.2fa_user_id` recorded. Logout invalidates both authentication and second-factor state.

TOTP secrets use Laravel encrypted casts. Recovery codes are displayed exactly once and stored only as password hashes. A TOTP timestep and recovery code cannot be replayed. Challenge and enrollment endpoints are throttled. Audit events contain event type, actors and a keyed-free IP hash, never secrets or codes.

A sysadmin cannot reset their own factor. Another fully authenticated sysadmin may reset it and an audit event is appended. If the installation has only one sysadmin and both the authenticator and recovery codes are lost, recovery is an operational database procedure performed during a controlled maintenance window by an authorized operator: verify identity out of band, back up the database, delete only that user's `network_two_factor_authentications` row, record the incident externally, and require immediate enrollment. There is no web bypass.

Migration: `2026_08_18_100000_create_network_two_factor_tables.php`. Run it through the normal reviewed Stage migration process; this task does not migrate Stage.

## Reproducible release build

Build from a clean commit. Do not copy `.env`, Windows `vendor`, `node_modules`, generated temporary builds, or private storage.

```bash
composer validate --no-check-publish
composer install --prefer-dist --no-interaction --no-dev --optimize-autoloader
composer dump-autoload -o --strict-psr --no-scripts
composer audit --locked
composer check-platform-reqs --no-dev
php -r "require 'vendor/autoload.php'; exit(class_exists('TCPDF') ? 0 : 1);"
npm ci
npm audit --audit-level=high
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

The repository still tracks historical vendor files. Do not mass-delete them in this phase. Transitional deployment must ignore the checked-in vendor content and replace it atomically with the output of `composer install` from `composer.lock`. Migration away from tracked vendor should be a separate reviewed change that first proves every current deployment path builds vendor from the lock.

## Linux certification

PSR-4, path casing, framework boot, caches, dependency locks and clean installs were validated from an isolated Windows export. WSL Ubuntu 24.04 exists on the audit machine but has no Linux PHP, Composer or Node runtime installed, so an actual Linux execution remains required. Certify on the Stage-compatible Linux builder with PHP 8.2+, required extensions, Node 20.19.x and npm 10.x by running the commands above plus the isolated regression suite. Until that run passes, `LINUX READY` and `READY TO DEPLOY STAGE` remain `NO`.
