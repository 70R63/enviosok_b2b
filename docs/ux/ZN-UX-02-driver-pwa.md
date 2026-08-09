# ZN-UX-02 — ZIGO Driver PWA & Central Driver Experience

## Ownership and commercial architecture

ZIGO Driver is owned by ZIGO Platform. Tenant identity is operational context only. The central shell, installed application name and app icon remain ZIGO. Supported product directions are private tenant drivers, independently contracted tenant drivers, and Driver Standalone for ERP/TMS customers purchasing Driver App + Dispatch Console + API Hub/Webhooks. Driver Marketplace remains future.

Commercial packaging:

1. ZIGO CRM + Driver.
2. ZIGO Driver Standalone + Dispatch Console + API Hub/Webhooks.

## Hosts and transition

`ZIGO_DRIVER_URL` configures the central origin and `ZIGO_DRIVER_HOST` is an optional host fallback. Local default is `http://driver.zigo.local:8000`; production can set `https://driver.zigo-envios.com` without code changes. The central surface is `/driver/*`. Existing verified tenant hosts continue exposing the same `/driver/*` paths.

Central sessions use a host-only `_driver` cookie suffix. Tenant-host routing continues through `tenant.resolve`. Central routing never resolves tenant from hostname.

## Central context and switching

The authenticated global User is the root of trust. `DriverWorkspaceService` builds the allowed list from:

`User → active driver membership → ACTIVE DriverProfile → active tenant → operational subscription → DRIVER entitlement`.

The browser never submits or stores a trusted `tenant_id`. The selector submits a profile UUID, which is compared with constant-time equality against a newly generated authorized list. The session stores only `zigo_driver.active_profile_uuid`. One workspace is selected automatically; multiple workspaces show a selector. Missing, stale or manipulated context is removed. Logout clears context and invalidates the session.

## PWA and icons

The central manifest uses name/short name `ZIGO Driver`, `standalone`, `portrait-primary`, `/driver/` start/scope, ZIGO Navy `#0B2445`, and canvas `#F1F5FA`.

No approved corporate 192×192 or 512×512 raster app icons were found in the repository. The manifest deliberately omits `icons`; no synthetic logo or tenant asset is used. Installability will remain incomplete in browsers that require icons until Brand supplies approved ZIGO 192, 512 and maskable assets. Expected future paths: `public/images/pwa/zigo-driver-192.png`, `zigo-driver-512.png`, and `zigo-driver-maskable-512.png`.

## Safe service worker and offline behavior

Registration occurs only on the central Driver host and only when the browser reports HTTPS. There is no HTTP security bypass. The worker scope is `/driver/` and its explicit cache allowlist contains only the shared design-system CSS and neutral offline page.

It never stores authenticated HTML, shipment details, PII, addresses, phones, POD, media, signatures, GPS, earnings, evidence, API responses, or mutation responses. Navigation requests are network-only with a neutral offline fallback; protected responses additionally return `private, no-store`. POST/PUT/PATCH/DELETE are ignored. There is no background sync or offline delivery.

Critical forms are blocked client-side while offline with: “Necesitas conexión para completar esta acción.”

## Install UX and HTTPS

The install button is revealed only after the real `beforeinstallprompt` event and uses the browser prompt. When unavailable, web operation remains usable; profile retains install guidance without simulating a native prompt. Local HTTPS and production certificate activation belong to environment/DevOps validation.

## Contact, messaging and privacy

Recipient calling uses `tel:` only for an active assignment and delivery-compatible status. The number is placed in the link but not rendered as text. Completed delivery history contains tracking, terminal status, date and the driver's own earning only—never recipient PII. Masked calling is future.

Controlled message templates are documented in Support: “Estoy en camino”, “Llegué al domicilio”, “No encuentro el acceso”, and “¿Puede salir a recibir?”. Channel selection (in-app/SMS/provider-approved WhatsApp) remains future; there is no chat backend.

## Security checklist

- Central Driver has an isolated host-only session cookie.
- Tenant selection is derived from server-side authorization, not request tenant IDs.
- Every protected response is `private, no-store` and `noindex`.
- Assignments remain constrained by tenant, driver profile, ACTIVE assignment and allowed shipment state.
- Evidence routes continue using the existing assignment checks.
- PII is absent from completed lists and cannot be reopened through active-assignment routes.
- Service worker uses an explicit public allowlist and never caches authenticated responses.
- Logout removes active workspace before invalidating the session.

## Future only

Marketplace, payout provider, push, messaging provider, route optimization, offline operations, background GPS, native apps, wallet, automatic payouts and AI remain unimplemented.
