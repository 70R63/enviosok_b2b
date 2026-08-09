# ZN-UX-03 — White-label Customer Portal

## Identity decision

`User` remains the global authentication identity. `TenantMembership` remains restricted to staff and Driver roles. Customer access is represented by `TenantCustomerProfile`, unique by tenant and user, so one identity may legitimately be a customer of multiple tenants without receiving administrative access. The legacy required `users.empresa_id` column remains populated with its neutral compatibility value for newly created global identities; it is not used for tenant authorization.

`TenantOperation.customer_profile_id` is nullable to preserve historical and admin-created operations. Customer-created/claimed quotes are linked to an active profile, and portal shipment queries require both matching tenant and matching customer profile.

## Journey and commercial gaps

Public and authenticated quotes reuse `TenantB2cQuoteService`; quoting does not record Usage. A selected option is validated against the quote options stored server-side in session, then persisted on the draft operation. Anonymous selection stores only tenant and operation UUID as pending context and resumes after customer authentication.

Checkout, payment and confirmation remain future work. Evidence options show only active tenant configuration. A surcharge is displayed as a foundation line item, never added to an invented final total until the commercial pricing contract is implemented.

## Authentication and reset

The existing Laravel web guard and password broker are reused. The generic `/login` controller delegates to the tenant customer login only when `TenantDomainResolver` resolves a verified domain for an active tenant. Invalid credentials and users without an active profile receive the same generic failure. Existing `/forgot-password` and reset endpoints remain the password-reset foundation; no custom token system was introduced.

## Privacy and isolation

Tenant resolution comes from the verified hostname. Customer authorization never accepts a tenant ID from input. Public tracking returns status and timestamps only; it excludes addresses, names, phones, provider costs and internal event descriptions. Customer shipment detail is authorized through tenant operation ownership and excludes Driver details, earnings, internal pricing and POD evidence.

## Routes

Public: `/`, `/registro`, `/login`, `/logout`, `/cotizar`, `/rastreo`, `/rastreo/{tracking}`. Legacy `/tracking` and `/tracking/{tracking}` remain available.

Authenticated: `/app`, `/app/cotizar`, `/app/envios`, `/app/envios/{shipment}`, `/app/perfil`. `/app/saldo` is intentionally not implemented because no customer wallet contract exists.
