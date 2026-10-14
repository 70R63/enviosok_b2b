# ENFA release — gates funcionales

**ENFA E2E: PASS**. 377 casos / 1336 assertions, cero failures/errors/skips, una deprecación PHPUnit preexistente. El conjunto dedicado incluye todos los casos provider (no se excluyeron los 21 del smoke, que se ejecutó aparte completo). No hay cobro/HTTP real ni acceso a STAGE: confirmación de pago y mail/storage son controlados.

| Gate | Comportamiento | Evidencia ejecutada | Resultado |
|---|---|---|---|
| A | Ruta cubierta → provider aparece | `Tests\Feature\NetworkTenantProviderQuotingTest::test_both_postal_codes_covered_return_option` | PASS |
| B | Origen/destino fuera → provider omitido | `Tests\Feature\NetworkTenantProviderQuotingTest::test_uncovered_origin_or_destination_preserves_national_options` | PASS |
| C | Nacional continúa | `Tests\Feature\NetworkTenantProviderQuotingTest::test_national_options_are_unchanged_without_connections` | PASS |
| D | Selección provider durable | `Tests\Feature\NetworkProviderContractSelectionTest::test_valid_local_offer_can_be_selected_and_attached_to_checkout` | PASS |
| E | Pago confirmado, frontera after commit | `Tests\Feature\NetworkProviderFulfillmentTest::test_real_verified_payment_boundary_runs_only_after_commit` | PASS |
| F | Fulfillment sin segunda compra | `Tests\Feature\NetworkProviderFulfillmentTest::test_pending_review_can_retry_without_a_second_purchase` | PASS |
| G | Guía y logo consumer | `Tests\Feature\NetworkProviderReleaseCandidateTest::test_guide_uses_consumer_logo_and_customer_provider_identity` | PASS |
| H | Pickup automático READY_FOR_PICKUP | `Tests\Feature\NetworkProviderReleaseCandidateTest::test_integrated_release_lifecycle_without_crm` | PASS |
| I | Acceptance → IN_SERVICE idempotente | `Tests\Feature\NetworkProviderSettlementTest::test_accepted_is_in_service_and_repeated_acceptance_idempotent` | PASS |
| J | Dispatch/driver mismo provider | `Tests\Feature\NetworkProviderTrackingTest::test_accepted_same_provider_assignment_and_duplicate_are_safe` | PASS |
| K | Tracking completo | `Tests\Feature\NetworkProviderReleaseCandidateTest::test_integrated_release_lifecycle_without_crm` | PASS |
| L | Delivered | `Tests\Feature\NetworkProviderSettlementTest::test_delivered_is_payable_not_settled_and_projection_repeat_is_idempotent` | PASS |
| M | POD durable | `Tests\Feature\NetworkProviderTrackingTest::test_repeated_pod_reuses_original_files_and_event` | PASS |
| N | Payable | `Tests\Feature\NetworkProviderSettlementTest::test_fulfillment_creates_one_pending_payable_and_duplicate_events_do_not_duplicate` | PASS |
| O | Settlement parcial | `Tests\Feature\NetworkProviderSettlementTest::test_partial_then_full_settlement_preserves_economy_and_audit` | PASS |
| P | Settlement completo SETTLED | `Tests\Feature\NetworkProviderReleaseCandidateTest::test_integrated_release_lifecycle_without_crm` | PASS |
| Q | Guest recovery + tracking/POD/guía | `Tests\Feature\NetworkProviderTrackingTest::test_guest_own_tracking_pod_and_original_guide_work_without_token_in_pdf` | PASS |
| R | Provider rejected → REVIEW | `Tests\Feature\NetworkProviderReleaseCandidateTest::test_rejected_is_review_without_deleting_obligation_or_checkout`, `Tests\Feature\NetworkProviderSettlementTest::test_rejected_is_review_without_deleting_obligation_or_checkout` | PASS |
| S | Delivery failed → REVIEW | `Tests\Feature\NetworkProviderReleaseCandidateTest::test_delivery_failed_is_review_without_zeroing_cost_refund_or_cancellation`, `Tests\Feature\NetworkProviderSettlementTest::test_delivery_failed_is_review_without_zeroing_cost_refund_or_cancellation` | PASS |
| T | Sin CRM → operación interna completa | `Tests\Feature\NetworkProviderNetworkAdminTest::test_internal_fulfillment_without_integration_never_creates_crm_outbox` | PASS |

## Feature safety: PASS

`NetworkProviderReleaseGateSafetyTest` ejecuta los dos escenarios obligatorios: relación ausente y relación inactiva, manteniendo catálogo/cobertura/pricing provider completamente configurados. Verifica cero opciones locales, opción nacional sin alteración, selección nacional Xperta con referencia/precio esperado, checkout HTTP 200 y sin branding provider; guarda pago VERIFIED mediante eventos reales del modelo y ejecuta los tres recovery commands. Snapshot, contract, fulfillment, shipment/operación provider, payable/eventos/settlement y outbox siguen en cero. Contexto consumer intacto y cero HTTP externo. Dos casos / 58 assertions, sin fallos.

Xperta/Estafeta/checkout: 92 casos / 444 assertions aprobados en suite dedicada sin cambiar implementación. Los transports nacionales se sustituyen por fixtures/mocks donde las suites existentes lo requieren.

## Seguridad focal: PASS

`NetworkProviderReleaseGateSecurityTest`: dos casos / 11 assertions en environment staging local de fixtures. Host configurado válido devuelve 200 en connection/settlement; host no confiable devuelve 400 sin contenido provider; faltan 2FA o rol → redirect/403. Son pruebas de las propiedades de acceso, no una sustitución del 404 que esperan las assertions originales.

## Migration gate: PASS

MariaDB 10.4.32 desechable y user sin TRIGGER ni ALL PRIVILEGES. Esquema padre vacío equivalente de fixtures y migraciones reales (Network/local shipping/driver/API y las seis ENFA). Down de las seis en orden inverso vacío, up de las seis en orden cronológico, once tablas proveedor, FK/índices inspeccionados y FK compuesta probada con ownership incorrecto. Quote cubierta visible y no cubierta omitida. No triggers, INSERT de tenants ni IDs de empresas en las migraciones. Un E2E SQL adicional de settlement parcial concurrente pasa hasta SETTLED y rechaza rollback poblado. No certifica migrate completo de tablas legacy ni configuración real del hosting.

## Reproducción

Ver logs/XML en este directorio. Los procesos que usan `Storage::fake` del mismo workspace se ejecutan secuencialmente. El smoke conserva exactamente el filtro original; no se añade --exclude ni se edita ninguna assertion previa. Los scripts SQL exigen port >30000 y validan @@datadir temporal antes de crear una base nueva. No usan la conexión .env.
