# ZN-UX-04 — Customer Shipping Journey & Checkout Foundation

## Journey

The tenant customer journey is `/app/cotizar` → service selection → `/app/envio/nuevo` → `/app/envio/evidencia` → checkout summary → pending payment. Anonymous selection retains a tenant-bound operation UUID in the encrypted session, claims that exact operation after authentication, and resumes at shipping data. A unique checkout per tenant operation makes repeated submissions idempotent.

Shipping addresses remain journey snapshots; no address book is introduced. Postal codes must equal the quoted route and are resolved with `ZigoPostalCodeService`. Package type, weight and dimensions come exclusively from `quoted_package`; the shipping form cannot turn a quoted envelope into a box.

## Commercial calculation and checkout

`CustomerCheckoutPricing` is the single calculator for shipping price plus delivery-proof surcharge. Browser totals, provider, service and proof details are never accepted as commercial truth. `TenantCustomerCheckout` freezes quote, shipping data, proof rules and amounts with independent order and payment statuses.

Order statuses: `DRAFT`, `PENDING_PAYMENT`, `PAID`, `EXPIRED`, `CANCELED`.

Payment statuses: `PENDING`, `APPROVED`, `REJECTED`, `CANCELED`, `REFUNDED` (future).

At `PENDING_PAYMENT`, the operation remains `quoted`: no Usage, shipment or guide exists. Expired checkouts have the same zero-fulfillment guarantee.

## ZN-08 Mercado Pago contract

`CustomerCheckoutFulfillmentService::approve()` is the internal idempotent orchestration entry point reserved for a provider-verified `APPROVED` event. ZN-UX-04 exposes no route or UI that invokes it. The future webhook adapter must verify signature, tenant payment connection, provider payment ID, amount, currency and checkout reference before calling it.

On a verified approval it atomically records `PAID/APPROVED`, confirms the operation, records Usage through the existing idempotency key, creates at most one shipment, freezes the checkout proof contract and enables the existing guide service. Repeated events with the same provider/reference return the existing shipment; conflicting references are rejected.

The current fulfillment adapter supports the existing ZIGO Local shipment contract. Additional carrier purchase adapters must implement the same gate before a non-local service can be sold through checkout.

## Money ownership

Tenant → ZIGO payments fund ZIGO plans, renewals, add-ons and future platform balance. Customer → tenant payments are guide sales owned by the tenant (for example RapidGo Local). ZIGO orchestrates but must not route these sales into ZIGO treasury. Each tenant will connect its own Mercado Pago account through future OAuth at `/admin/configuracion/pagos`; manual access-token entry is not the primary UX.

Driver compensation remains separate. ZIGO may calculate `SALARIED`, `PER_DELIVERY` or `HYBRID` earnings, but the tenant funds and pays its Drivers. Driver payout providers do not share the customer checkout lifecycle.

## Security and operations

All journey queries require host tenant, active customer profile and owned operation/checkout. Inactive proof options, arbitrary operation/checkout UUIDs, changed postal routes and client totals are rejected. Customer guide and pickup actions require an existing owned shipment; a customer never selects a Driver. Tenant Admin can inspect checkout, customer, order/payment status and public total from the operation view, without payment credentials.

No scheduler is required in this phase. `expires_at` is enforced when payment is opened and again by the future approval service.
