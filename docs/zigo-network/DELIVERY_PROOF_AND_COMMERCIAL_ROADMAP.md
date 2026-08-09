# Delivery proof, payments and UX roadmap

## Configurable delivery proof

Each tenant owns delivery-proof options. The temporary Tenant Admin shipment form selects one; ZN-UX-01 will move this choice to customer checkout. An immutable 1:1 shipment snapshot prevents later catalog edits from changing a purchased guide. With no option, the application bootstraps `DEFAULT`: receiver name/type, photo, signature, GPS, two attempts and `ANY_PERSON_AT_ADDRESS`.

Surcharge and currency are frozen for later commercial integration but are not added to pricing or payments in ZN-07B.2.

## Payment ownership (future)

The first planned ZIGO Payments provider is Mercado Pago.

- Tenant payments to ZIGO (plans, renewals, add-ons and top-ups) settle into ZIGO's account.
- A final customer's RapidGo guide purchase belongs to RapidGo. RapidGo connects its own provider account; ZIGO orchestrates and never funds tenant operations with ZIGO money.
- RapidGo funds SALARIED, PER_DELIVERY and HYBRID Drivers. ZIGO calculates earnings, settlements, evidence and reports, and may later orchestrate payouts using RapidGo funds—not ZIGO treasury.

Tenant Driver Payouts use `TENANT` as funding source. Future V1 is manual bank transfer with reference and proof. Future V2 introduces `PayoutProvider` to move tenant funds to the Driver.

## ZN-UX-01 route contract

Public tenant: `/`, `/registro`, `/login`, `/cotizar`, `/rastreo`, `/rastreo/{tracking}`.

Authenticated customer: `/app`, `/app/cotizar`, `/app/envios`, `/app/envios/{shipment}`, `/app/saldo`, `/app/perfil`.

Tenant Admin: `/admin`, `/admin/operations`, `/admin/dispatch/pickups`, `/admin/drivers`, `/admin/users`, `/admin/plan`, `/admin/configuracion`, `/admin/configuracion/entregas`; `/admin/configuracion/pagos` is future.

Driver: `/driver`, `/driver/login`, `/driver/shipments/{shipment}`.

Network: `/network`, `/network/tenants`, `/network/plans`, `/network/local-shipping`, `/network/devops`; `/network/payments` is future.

## Customer journey

Tenant Landing → Registration/Login → Quote → Service selection → Shipment data → Evidence selection → Summary → Payment → Guide → Pickup request → Tracking.

“Mis envíos” will expose current status, timeline, guide download and automatic tracking without requiring Tenant Admin. Checkout, wallet, Mercado Pago, payout execution and customer registration remain future work. `RETURN_PENDING` and `RETURN_TO_ORIGIN` are reserved concepts only and are not in the state machine.
