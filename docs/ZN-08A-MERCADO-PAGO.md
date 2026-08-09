# ZN-08A — Mercado Pago tenant seller payments

## Ownership and provider boundary

The final customer pays the tenant seller directly through Mercado Pago Checkout Pro. ZIGO creates the preference as marketplace/integrator with the tenant OAuth access token; ZIGO is not the treasury. `marketplace_fee` is an optional server-side snapshot and defaults to zero. Tenant→ZIGO billing and Driver compensation remain separate.

`PaymentProvider` isolates checkout fulfillment from Mercado Pago HTTP. The implementation uses Authorization Code + PKCE S256, encrypted token casts, refresh locking, server-side preference amounts, signed webhook validation, provider retrieval, and idempotent fulfillment.

## Sandbox prerequisites

1. A Mercado Pago marketplace application with Authorization Code and PKCE enabled.
2. A seller test account distinct from the buyer test account.
3. HTTPS public callback exactly matching `ZIGO_MP_REDIRECT_URI`.
4. HTTPS public webhook in `ZIGO_MP_WEBHOOK_URL` pointing to `/api/payments/mercado-pago/webhook`.
5. App client id/secret and webhook secret supplied only through environment secrets.
6. Seller grants read/write/offline access; tenant owner/admin completes `/admin/configuracion/pagos`.
7. Sandbox mode and zero fee unless an explicit commercial sandbox fee is approved.
8. Queue/log monitoring and a valid tenant subscription/SHIPPING entitlement for end-to-end fulfillment.

OAuth correlation is stored for ten minutes in Laravel Cache and consumed atomically on callback. Multi-node environments therefore require a shared cache backend; the callback does not depend on a tenant hostname or a cross-host browser session.

Browser returns never approve payment. The signed notification is only a trigger: ZIGO retrieves `/v1/payments/{id}` using the seller token and validates payment id, opaque external reference, exact amount, MXN currency and `collector_id` before `CustomerCheckoutFulfillmentService::approve()`.

Real OAuth/webhook tests require a stable public HTTPS environment or an explicitly managed secure development tunnel; the repository adds no tunnel and no HTTP security bypass. Before production readiness, re-check the live Mercado Pago OAuth, Checkout Pro preference and Webhooks documentation because provider contracts can change.

Future-safe model statuses include revoked/expired/error connections and refunded payments, but this phase does not expose refunds, subscriptions, wallets, payouts or manual approval.
