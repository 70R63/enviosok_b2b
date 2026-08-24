# IA-08C — ZIGO Logistics Actions V1

## Inventory and delegation

Action: `zigo.quote_shipment`

Delegates to: `App\Domain\Network\Channels\B2C\TenantB2cQuoteService`, which reuses `PackageValidator`, `LocalPricingEngine`, `RouteDistanceProvider`, `ZigoPostalCodeService`, and persists `LocalShippingQuoteSnapshot`.

Action: `zigo.track_shipment`

Delegates to: `App\Domain\Shipping\Local\LocalTrackingService::read`, backed by tenant-owned `LocalShipment` and append-only `LocalTrackingEvent` records. Statuses are the existing `LocalShipment::STATUSES`; no AI status enum exists.

Action: `zigo.create_shipment_guide`

Delegates to: `App\Domain\Network\Channels\B2C\TenantB2cQuoteService::finalize`, `App\Domain\Network\Channels\B2C\TenantOperationService`, and `App\Domain\Shipping\Local\LocalShipmentService`. `App\Domain\Shipping\Local\LocalGuideService` is not called by the Action: it renders/downloads the PDF later from the immutable `guide_snapshot` created by `LocalShipmentService`.

## Boundaries and authority

Handlers live under `App\Domain\Shipping\AI\Actions`; AI Core only owns the horizontal Action contract. `quote()` may create preliminary options from postal code and settlement for display. Guide input supplies the full structured street/exterior/interior address, but no price, carrier, provider, margin or cost fields. Before any operation confirmation or shipment side effect, the handler verifies both postal codes against the selected preliminary option and delegates full-address recalculation to `TenantB2cQuoteService::finalize()`. The final snapshot must preserve service, currency and commercial total previously presented for confirmation; otherwise creation stops and a new Action/confirmation is required. The operation and shipment pricing snapshot are then bound to the final snapshot UUID. A guide can never reuse the price of a different route. `LocalShipmentService` preserves the immutable commercial/package/address snapshots and its unique `tenant_operation_id` prevents a second shipment for the same operation.

Registry availability requires the existing `EntitlementService`: quote and guide require `SHIPPING`, tracking requires `TRACKING`. The pinned Agent Contract must also contain the exact Action key. Tenant identity always comes from `ActionExecutionContext`.

## Confirmation, failures and privacy

Guide creation is WRITE/REQUIRED. Pass 1 only reserves `awaiting_confirmation`; the server revalidates registry, entitlement and Contract before claiming. The handler then revalidates tenant, quote membership, option membership, expiry and service state outside the Action transaction. A failed or ambiguous provider-side execution is not automatically retried because the ActionRun leaves the confirmable state. Existing Human Handoff handles unresolved final responses.

ActionRun input/output remain encrypted. UI shows only postal-code route, friendly service, commercial total and a masked recipient. It never displays raw payloads, internal costs, provider credentials or database IDs. Dynamic content is Blade-escaped.

## Feature flags and test strategy

The vertical uses published/active tenant services plus existing `SHIPPING`/`TRACKING` entitlements. It does not bypass Xperta/Estafeta flags because handlers never call either provider; V1 targets the real ZIGO Local vertical. Tests cover origin, destination and combined route mismatch inside one tenant, successful same-price finalization, changed-price rejection, final snapshot binding and double confirmation. They use SQLite `:memory:`, `Http::preventStrayRequests`, existing service boundaries and no real provider/OpenAI calls.

Acceptance exercises the registered production handlers through Conversation Pass 1 → Action → Pass 2. Quote runs `TenantB2cQuoteService` with postal infrastructure replaced at the boundary and proves the 179 MXN server snapshot overrides model text. Guide proves `awaiting_confirmation`, zero shipments before the real Tenant Admin confirmation endpoint, one immutable shipment after confirmation, Q1/O2 and cross-tenant rejection, and one call across double confirmation. A simulated connection-loss after the create boundary produces `action_failed`, no automatic retry, and an IA-08A Human Handoff through Pass 2. Tracking reads the tenant-owned shipment through `LocalTrackingService::read` and also reaches Pass 2/Handoff.

Tenant Admin edits `allowed_actions` only on a Draft Contract using trusted checkboxes populated from Registry intersected with tenant entitlement. `UpdateAgentContractActionsService` repeats that validation under locks, rejects unknown or no-longer-entitled keys, and never mutates accepted Contract versions. Quote and guide inputs receive application validation before invoking their real services; extra price/provider/service authority fields remain rejected by the closed Action schema.

## Capability and scope

All three capabilities have real reusable services. Tracking read normalization was moved into the existing `LocalTrackingService` so API/UI/AI do not grow a parallel tracking engine. No migration, AI quote/shipment/guide tables, billing changes, public Webchat, WhatsApp or provider configuration were added. Availability remains limited to Tenant Admin `internal_test`.
