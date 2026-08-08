# ZIGO postal catalog

## Architecture and source of truth

`zigo_postal_codes` is the operational source of truth for B2C, B2B, ZIGO Local,
White Label and API Hub. All runtime lookups go through `ZigoPostalCodeService`.
The `sepomex` table and SEPOMEX-formatted files are import sources only; runtime
fallback to that legacy table is intentionally prohibited to prevent inconsistent
answers between products.

The catalog stores one row per settlement, not one row per postal code. Its
`codigo_postal` indexes are non-unique because one postal code can contain several
settlements. The synchronization identity is `codigo_postal + estado + municipio +
asentamiento`.

API Hub may sell Postal Code Lookup as a metered product. Internal B2C, B2B,
Shipping, ZIGO Local and White Label usage consumes the same domain capability as
internal infrastructure and must not create an extra billable API call for a tenant.

## Controlled synchronization

The command never downloads, truncates, deactivates or deletes records. It validates
five-digit postal codes and required geographic fields, preserves UTF-8, processes
transactional batches, and reports processed, inserted, updated and invalid rows.
Re-running the same source updates the same settlement identities without duplication.

From a populated legacy source table:

```bash
php artisan zigo:postal-codes:sync --from=sepomex --batch=500
```

From a reviewed UTF-8 CSV/TXT source (recommended for LOCAL/STAGE/PRD promotion):

```bash
php artisan zigo:postal-codes:sync --from=file --file=storage/app/imports/sepomex.csv --batch=500
```

Accepted canonical headers are `codigo_postal,estado,municipio,ciudad,asentamiento,
tipo_asentamiento,zona,cobertura_estafeta,activo`. SEPOMEX headers `d_codigo,
d_estado,d_mnpio,d_ciudad,d_asenta,d_tipo_asenta,d_zona` are mapped only at import.
Comma, pipe, tab and semicolon delimiters are auto-detected, or can be specified with
`--delimiter`.

Review the source checksum and row/error totals in every environment. An error count
returns a non-zero exit code, while valid rows in completed batches remain committed.
Never load a full application backup to bootstrap this catalog.

## Repository datasets

The repository contains populated SEPOMEX SQL dumps in `sepomex_backup.sql` and
`resources/db/sepomex.sql`; `siteground_full_backup.sql` also contains SEPOMEX data.
`database/backups/db06qjqr4v7ufp.sql` contains populated `sepomex` and
`zigo_postal_codes` tables. Do not restore the full backups. Extract/review only the
postal dataset into a controlled UTF-8 CSV before using the file workflow, or use an
already-populated isolated `sepomex` table as the import source.

## Hosts and acceptance

With `ZIGO_SUBDOMAIN_ROUTING_ENABLED=true`, portal middleware enforces the API host
configured by `ZIGO_API_URL` for `/api/hub/cp/{codigoPostal}` and the B2C host
configured by `ZIGO_B2C_URL` for `/postal-code/lookup/{codigoPostal}` and
`/b2c/cp/colonias?cp=`. Do not call the web endpoints through the API host or relax
their middleware. After synchronization, verify `64000` through both web routes, the
service, and a ZIGO Local zone assignment.
