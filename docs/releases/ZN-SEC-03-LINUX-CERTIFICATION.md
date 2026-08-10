# ZN-SEC-03 Linux certification

Certification date: 2026-08-09 (America/Mexico_City)

## Certified environment

- Ubuntu 24.04.2 LTS on WSL2, kernel 5.15.167.4
- ext4 candidate: `/home/jreyes/zigo-linux-cert`
- PHP 8.3.6
- Composer 2.7.1
- Node 20.19.6
- npm 10.8.2
- Laravel 12.65.0

The candidate was copied from the working tree, including uncommitted ZN-SEC changes. The copy excluded `.git`, `.env`, `vendor`, `node_modules`, generated caches/build output, logs, and private storage content. Dependencies and frontend assets were regenerated from `composer.lock` and `package-lock.json`.

## Results

- `composer install`: PASS
- `composer validate`: PASS
- `composer audit`: 0 advisories (Critical 0, High 0); two abandoned-package notices remain (`doctrine/annotations`, `spatie/data-transfer-object`)
- `composer dump-autoload -o --strict-psr`: PASS
- `composer check-platform-reqs --no-dev`: PASS
- TCPDF autoload: PASS
- Mercado Pago legacy runtime classes after classmap normalization: PASS
- `npm ci`: PASS
- `npm audit`: 0 vulnerabilities (Critical 0, High 0)
- `npm run build`: PASS
- Artisan version/about/route list/view cache/route cache: PASS; generated caches cleared afterward
- Security/surface regression: 117 passed, 0 failed, 809 assertions
- Full suite: 345 passed, 0 failed, 0 errors, 0 risky, 2351 assertions

Tests use SQLite `:memory:` through `phpunit.xml`. No normal MySQL database was used.

## Legacy normalization

- Generic Breeze authentication fixtures now create ZIGO's required `empresa_id`.
- Password reset tests assert the ZIGO notification contract rather than Laravel's generic notification.
- Checkout and example fixtures now represent tenant-domain middleware and real portal hosts.
- Laravel/PHPUnit host rejection expects the hardened pre-router HTTP 400 behavior; middleware was not weakened.
- PHPUnit doc-comment metadata was converted to PHPUnit 11/12-compatible test names and attributes without retiring coverage.
- Linux absolute paths such as `/tmp/file.csv` are recognized by the postal catalog command.

## Linux-only findings

1. Unix absolute paths were treated as project-relative by the postal import command; fixed in source.
2. NTFS-to-WSL copy modes appeared as `777`; the certified tree was normalized and the deployment model below is mandatory.

No filename/namespace casing, line-ending, locale, timezone, shell-assumption, or frontend-build blocker remains. WSL also reports an invalid user-level `.wslconfig`; it is external to this repository and did not block WSL2 execution.

## Stage runtime contract

Required PHP/runtime extensions:

- Core/platform: `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `iconv`, `json`, `libxml`, `mbstring`, `openssl`, `pcre`, `session`, `tokenizer`, `xml`, `xmlreader`, `xmlwriter`
- Database: `PDO`, `pdo_mysql`; `pdo_sqlite` is required for the isolated test suite
- TCPDF, images and POD: `gd`
- Archives: `zip`
- Recommended runtime support used by the certified build: `intl`, `SimpleXML`

SiteGround Stage must provide PHP 8.2+ with this extension set, Node 20.19.x/npm 10.x for server-side asset builds if builds are performed there, and writable Laravel runtime directories. No SiteGround connection or deployment was performed.

Permission model: release code directories `0755`, files `0644`; `storage/` and `bootstrap/cache/` owned by the deploy user and grouped to the PHP/web process, directories `2775`, files `0664`. The release parent must allow traversal by the web process. Never use `0777`.

## Gate

- ZN-SEC-03 LINUX READY: YES
- ZN-SEC-03 FULL REGRESSION READY: YES
- ZN-SEC-03 SECURITY READY: YES
- ZN-SEC-03 RELEASE REPRODUCIBLE: YES
- ZN-SEC-03 READY TO DEPLOY STAGE: YES

No deploy, Stage migration, Git staging, commit, or push was performed.
