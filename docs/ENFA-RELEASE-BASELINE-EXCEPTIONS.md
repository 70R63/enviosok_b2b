# ENFA — Release baseline exceptions

Estos fallos **no están resueltos**. Se aceptan únicamente como excepciones al gate ENFA después de comparar cada uno y comprobar independientemente los caminos críticos. No se cambió ninguna assertion existente en esta revisión ni se excluyeron suites del smoke original.

## Método y resultados

Base: archivo aislado de `git archive b8fa885e` sin .env, con autoloader propio y dependencias de composer.lock; RC: working tree conservado. Misma versión PHP/PHPUnit, configuración SQLite :memory:, filtros y APP_KEY de prueba en proceso. Se preservan XML JUnit, logs y mensajes normalizados por fallo.

Se comparan: A presencia en base; B presencia en RC; C tipo de excepción; D assertion, esperado/real y texto/estatus; E mensaje completo y stack normalizados con SHA-256. El normalizador sólo elimina raíz de checkout, separadores escapados, UUID, CSRF, fechas/horas, longitudes impresas, uniqid y el filemtime del asset zigo-design-system.css. No elimina estados, mensajes, SQL, rutas funcionales, rol ni números HTTP.

Los casos 5/7/8 sólo diferían adicionalmente en `zigo-design-system.css?v=FILEMTIME` por extracción de git archive. Ambos archivos CSS tienen SHA-256 `BFF08A495DCD5DA0DEFCE4ACF521DEA9561908E9897C4DA912F544104B07AA2B`; no se ocultó cambio de contenido. Los 21 pares de mensajes y stack normalizados tienen hashes idénticos. Fuente automatizada: [comparison.json](enfa-release-gate/comparison.json), [normalizador reproducible](../tests/Support/analyze_provider_release_gate.php).

`IDENTICAL_BASELINE_FAILURE=21`, `BASELINE_FAILURE_CHANGED=0`, `NEW_FAILURE=0`, `CRITICAL_ENFA_FAILURES=0`. Todos son PHPUnit\Framework\ExpectationFailedException; las excepciones SQL subyacentes de 3/4 son iguales. Las suites heredadas se cuentan como casos distintos, indicando su archivo fuente real.

## Tabla exacta de los 21

En **archivo** se indica la línea de assertion. Ninguno de estos archivos de test fue modificado en ENFA-01..10. **Toca cambios** se refiere a ejecución/inspección de código del frente, incluso rutas compartidas; no implica causalidad. Todos los casos de motor/seguridad se clasifican indirectos sólo tras gates funcionales positivos, nunca por ser simplemente preexistentes.

| # | Suite | Test | Archivo:assertion | Assertion/error | Esperado | Real | Categoría | Toca cambios ENFA | Toca flujo funcional ENFA | Impacto | HEAD base | RC | Clasificación |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 1 | `DriverLastMileFoundationTest` | `test_permissions_state_machine_public_tracking_append_only_and_usage_contract` | `tests/Feature/DriverLastMileFoundationTest.php:216` | `assertSee` / ExpectationFailedException | `DELIVERED` | HTTP 200, Entregado; BD DELIVERED y seis eventos ya comprobados antes de fallar | Texto público tracking | Sí: routes/web.php, DriverConsoleController, LocalTrackingService, DriverDeliveryService | Sí, motor local reutilizado | RELACIONADO INDIRECTAMENTE | FAIL: DELIVERED → missing from HTTP response | FAIL idéntico | `IDENTICAL_BASELINE_FAILURE` |
| 2 | `DriverLastMileFoundationTest` | `test_customer_pickup_is_fixed_idempotent_ignores_client_logistics_fields_and_enters_tenant_queue` | `tests/Feature/DriverLastMileFoundationTest.php:240` | `assertSee` / ExpectationFailedException | `READY FOR PICKUP` | HTTP 200, Recolección solicitada; BD READY_FOR_PICKUP y asignación ya comprobadas | Texto público pickup | Sí: routes/web.php, TenantOperationController, DriverAssignmentService | Sí, pickup/dispatch compartidos | RELACIONADO INDIRECTAMENTE | FAIL: READY FOR PICKUP → missing from HTTP response | FAIL idéntico | `IDENTICAL_BASELINE_FAILURE` |
| 3 | `NetworkBuildYourZigoTest` | `test_dashboard_and_tenant_detail_show_only_effective_modules_without_blade_directives` | `tests/Feature/NetworkBuildYourZigoTest.php:10` | `assertStatus` / ExpectationFailedException | `200` | HTTP 500; SQLite no such table: network_subscriptions | Fixture incompleta Network | Sí: routes/network.php; el controller preexistente falla antes de renderizar | No, detalle genérico de tenant | NO RELACIONADO | FAIL: 200 → 500 | FAIL idéntico | `IDENTICAL_BASELINE_FAILURE` |
| 4 | `NetworkBuildYourZigoTest` | `test_tenant_without_plan_shows_no_catalog_modules_as_contracted` | `tests/Feature/NetworkBuildYourZigoTest.php:11` | `assertStatus` / ExpectationFailedException | `200` | HTTP 500; SQLite no such table: network_subscriptions | Fixture incompleta Network | Sí: routes/network.php; controller preexistente | No, detalle genérico de tenant | NO RELACIONADO | FAIL: 200 → 500 | FAIL idéntico | `IDENTICAL_BASELINE_FAILURE` |
| 5 | `NetworkTenantB2cTest` | `test_operation_shipment_guide_and_tracking_are_tenant_isolated_and_public_tracking_is_private` | `tests/Feature/NetworkTenantB2cTest.php:413` | `assertSee` / ExpectationFailedException | `TENANT ADMIN` | HTTP 200; RASTREO OPERATIVO presente, TENANT ADMIN ausente | Texto de identidad administrativa | Sí: routes/web.php, tenant/admin/layout y renderer local | Sí, tracking/guía local compartidos | RELACIONADO INDIRECTAMENTE | FAIL: TENANT ADMIN → missing from HTTP response | FAIL idéntico | `IDENTICAL_BASELINE_FAILURE` |
| 6 | `NetworkWhiteLabelTest` | `test_preview_uses_fallback_and_only_included_modules` | `tests/Feature/NetworkWhiteLabelTest.php:12` | `assertSee` / ExpectationFailedException | `#185FA8` | HTTP 200; fallback actual #3867f5, no #185FA8 | Color fallback preview | Sí: routes/network.php; preview/branding genéricos | Indirecto, identidad visual; no contratación | RELACIONADO INDIRECTAMENTE | FAIL: #185FA8 → missing from HTTP response | FAIL idéntico | `IDENTICAL_BASELINE_FAILURE` |
| 7 | `ZigoCustomerPortalNavigationTest` | `test_known_tracking_statuses_use_canonical_labels_on_customer_admin_and_public_surfaces` | `tests/Feature/ZigoCustomerPortalTest.php:178` | `assertSee` / ExpectationFailedException | `TENANT ADMIN` | HTTP 200; falta TENANT ADMIN en rastreo operativo | Texto de identidad administrativa | Sí: routes/web.php, tenant/admin/layout | Sí, proyección tracking compartida | RELACIONADO INDIRECTAMENTE | FAIL: TENANT ADMIN → missing from HTTP response | FAIL idéntico | `IDENTICAL_BASELINE_FAILURE` |
| 8 | `ZigoCustomerPortalTest` | `test_known_tracking_statuses_use_canonical_labels_on_customer_admin_and_public_surfaces` | `tests/Feature/ZigoCustomerPortalTest.php:178` | `assertSee` / ExpectationFailedException | `TENANT ADMIN` | HTTP 200; falta TENANT ADMIN en rastreo operativo | Texto de identidad administrativa | Sí: routes/web.php, tenant/admin/layout | Sí, proyección tracking compartida | RELACIONADO INDIRECTAMENTE | FAIL: TENANT ADMIN → missing from HTTP response | FAIL idéntico | `IDENTICAL_BASELINE_FAILURE` |
| 9 | `ZigoNetworkBillingLifecycleTest` | `test_host_auth_and_two_factor_contract_are_preserved` | `tests/Feature/ZigoNetworkOnboardingOperationsTest.php:48` | `assertStatus` / ExpectationFailedException | `404` | HTTP 400 en wrong.example/network/onboarding | Contrato de rechazo de host | Sí: routes/network.php, layout Network en preparación previa | Indirecto, frontera de administración | RELACIONADO INDIRECTAMENTE | FAIL: 404 → 400 | FAIL idéntico | `IDENTICAL_BASELINE_FAILURE` |
| 10 | `ZigoNetworkBillingLifecycleTest` | `test_payment_approved_failed_is_visible_and_security_contract_is_preserved` | `tests/Feature/ZigoNetworkOperationsConsoleTest.php:53` | `assertStatus` / ExpectationFailedException | `404` | HTTP 400 en wrong.test/network/payments | Contrato de rechazo de host | Sí: routes/network.php | Indirecto, frontera Network; pago SaaS distinto | RELACIONADO INDIRECTAMENTE | FAIL: 404 → 400 | FAIL idéntico | `IDENTICAL_BASELINE_FAILURE` |
| 11 | `ZigoNetworkBillingLifecycleTest` | `test_pre_stage_network_navigation_smoke_has_no_dead_or_duplicate_links` | `tests/Feature/ZigoNetworkOperationsConsoleTest.php:69` | `assertStatus` / ExpectationFailedException | `404` | HTTP 400 en wrong.test/network/api-hub | Contrato de rechazo de host | Sí: routes/network.php, resources/views/network/layout.blade.php | Indirecto, navegación/API Hub | RELACIONADO INDIRECTAMENTE | FAIL: 404 → 400 | FAIL idéntico | `IDENTICAL_BASELINE_FAILURE` |
| 12 | `ZigoNetworkBillingLifecycleTest` | `test_reminders_are_configurable_and_idempotent` | `tests/Feature/ZigoNetworkBillingLifecycleTest.php:41` | `assertTrue` / ExpectationFailedException | `true` | notifyDue devuelve false | Notificación renovación SaaS | No, SaasSubscriptionLifecycleService no cambiado por ENFA | No, aviso de suscripción SaaS | NO RELACIONADO | FAIL: true → false | FAIL idéntico | `IDENTICAL_BASELINE_FAILURE` |
| 13 | `ZigoNetworkBillingLifecycleTest` | `test_notice_uses_server_side_order_amount_semantics_and_tenant_payment_cta` | `tests/Feature/ZigoNetworkBillingLifecycleTest.php:78` | `assertTrue` / ExpectationFailedException | `true` | notifyDue devuelve false | Notificación renovación SaaS | No, SaasSubscriptionLifecycleService no cambiado por ENFA | No, aviso/CTA renovación SaaS | NO RELACIONADO | FAIL: true → false | FAIL idéntico | `IDENTICAL_BASELINE_FAILURE` |
| 14 | `ZigoNetworkOnboardingOperationsTest` | `test_host_auth_and_two_factor_contract_are_preserved` | `tests/Feature/ZigoNetworkOnboardingOperationsTest.php:48` | `assertStatus` / ExpectationFailedException | `404` | HTTP 400 en wrong.example/network/onboarding | Contrato de rechazo de host | Sí: routes/network.php | Indirecto, frontera de administración | RELACIONADO INDIRECTAMENTE | FAIL: 404 → 400 | FAIL idéntico | `IDENTICAL_BASELINE_FAILURE` |
| 15 | `ZigoNetworkOperationsConsoleTest` | `test_host_auth_and_two_factor_contract_are_preserved` | `tests/Feature/ZigoNetworkOnboardingOperationsTest.php:48` | `assertStatus` / ExpectationFailedException | `404` | HTTP 400 en wrong.example/network/onboarding | Contrato de rechazo de host | Sí: routes/network.php | Indirecto, frontera de administración | RELACIONADO INDIRECTAMENTE | FAIL: 404 → 400 | FAIL idéntico | `IDENTICAL_BASELINE_FAILURE` |
| 16 | `ZigoNetworkOperationsConsoleTest` | `test_payment_approved_failed_is_visible_and_security_contract_is_preserved` | `tests/Feature/ZigoNetworkOperationsConsoleTest.php:53` | `assertStatus` / ExpectationFailedException | `404` | HTTP 400 en wrong.test/network/payments | Contrato de rechazo de host | Sí: routes/network.php | Indirecto, frontera Network; no cobro ENFA | RELACIONADO INDIRECTAMENTE | FAIL: 404 → 400 | FAIL idéntico | `IDENTICAL_BASELINE_FAILURE` |
| 17 | `ZigoNetworkOperationsConsoleTest` | `test_pre_stage_network_navigation_smoke_has_no_dead_or_duplicate_links` | `tests/Feature/ZigoNetworkOperationsConsoleTest.php:69` | `assertStatus` / ExpectationFailedException | `404` | HTTP 400 en wrong.test/network/api-hub | Contrato de rechazo de host | Sí: routes/network.php, resources/views/network/layout.blade.php | Indirecto, navegación/API Hub | RELACIONADO INDIRECTAMENTE | FAIL: 404 → 400 | FAIL idéntico | `IDENTICAL_BASELINE_FAILURE` |
| 18 | `ZigoSaasOnboardingEndToEndTest` | `test_unknown_visitor_reaches_operational_tenant_owner_customer_and_network` | `tests/Feature/ZigoSaasOnboardingEndToEndTest.php:42` | `assertStatus` / ExpectationFailedException | `200` | HTTP 404 en GET http://zigo.local:8000/ | Entrada pública onboarding SaaS | Sí: routes/web.php; index.blade.php no llega a renderizarse | No, adquisición/provisioning SaaS; usa tenants nuevos | NO RELACIONADO | FAIL: 200 → 404 | FAIL idéntico | `IDENTICAL_BASELINE_FAILURE` |
| 19 | `ZigoStageHostContractTest` | `test_driver_and_network_surfaces_do_not_leak_across_hosts` | `tests/Feature/ZigoStageHostContractTest.php:23` | `assertStatus` / ExpectationFailedException | `404` | HTTP 400 en network-stage.zigo-envios.com/driver/manifest.webmanifest | Contrato de rechazo de host driver | Sí: routes/web.php; TrustHosts no cambiado por ENFA | Indirecto, seguridad de superficies | RELACIONADO INDIRECTAMENTE | FAIL: 404 → 400 | FAIL idéntico | `IDENTICAL_BASELINE_FAILURE` |
| 20 | `ZigoSupportCenterTest` | `test_route_contract_enforces_surface_middleware_and_network_scope_controller` | `tests/Feature/ZigoSupportCenterTest.php:11` | `assertNotNull` / ExpectationFailedException | `route exists` | null para alias tenant.driver.support.store | Alias de ruta soporte | Sí: routes/web.php y routes/network.php; no acción ENFA | No, soporte/tickets | NO RELACIONADO | FAIL: route exists → null | FAIL idéntico | `IDENTICAL_BASELINE_FAILURE` |
| 21 | `ZigoTenantRetentionTest` | `test_pre_stage_network_navigation_smoke_has_no_dead_or_duplicate_links` | `tests/Feature/ZigoNetworkOperationsConsoleTest.php:69` | `assertStatus` / ExpectationFailedException | `404` | HTTP 400 en wrong.test/network/api-hub | Contrato de rechazo de host | Sí: routes/network.php, resources/views/network/layout.blade.php | Indirecto, navegación/API Hub | RELACIONADO INDIRECTAMENTE | FAIL: 404 → 400 | FAIL idéntico | `IDENTICAL_BASELINE_FAILURE` |

## Justificación individual y evidencia

### 1. `Tests\Feature\DriverLastMileFoundationTest::test_permissions_state_machine_public_tracking_append_only_and_usage_contract`

La assertion exige el literal DELIVERED; el motor entregó y el renderer localiza el estado. Tracking/POD/privacidad provider y guest pasan en ENFA.

Evidencia A/B: [baseline](enfa-release-gate/failure-01-base.txt) y [RC](enfa-release-gate/failure-01-rc.txt). C/D/E: tipo `PHPUnit\Framework\ExpectationFailedException` en ambos; signature esperado/real idéntica; SHA-256 normalizado de ambos `64d681167c4603c8b00d47620807a66baa1146d577b009d57c10d7a0b1b3ed6a`. No hay cambio observado atribuible a ENFA. Impacto: **RELACIONADO INDIRECTAMENTE**.

### 2. `Tests\Feature\DriverLastMileFoundationTest::test_customer_pickup_is_fixed_idempotent_ignores_client_logistics_fields_and_enters_tenant_queue`

Falla sólo READY FOR PICKUP en UI localizada. Recolección automática, dispatch idempotente y aislamiento provider pasan separadamente.

Evidencia A/B: [baseline](enfa-release-gate/failure-02-base.txt) y [RC](enfa-release-gate/failure-02-rc.txt). C/D/E: tipo `PHPUnit\Framework\ExpectationFailedException` en ambos; signature esperado/real idéntica; SHA-256 normalizado de ambos `4fffe0149372025da4ba4214309657b94ee114ac9adb5f7e8cc7e359ec398cea`. No hay cambio observado atribuible a ENFA. Impacto: **RELACIONADO INDIRECTAMENTE**.

### 3. `Tests\Feature\NetworkBuildYourZigoTest::test_dashboard_and_tenant_detail_show_only_effective_modules_without_blade_directives`

El esquema mínimo de esa suite omite subscriptions. Readiness, administración connection y settlement usan fixtures completas y pasan, incluso en host no local configurado.

Evidencia A/B: [baseline](enfa-release-gate/failure-03-base.txt) y [RC](enfa-release-gate/failure-03-rc.txt). C/D/E: tipo `PHPUnit\Framework\ExpectationFailedException` en ambos; signature esperado/real idéntica; SHA-256 normalizado de ambos `39f0a9b82079d9a07694c7235b582874933abc2656dff3142a55a1f09faecf38`. No hay cambio observado atribuible a ENFA. Impacto: **NO RELACIONADO**.

### 4. `Tests\Feature\NetworkBuildYourZigoTest::test_tenant_without_plan_shows_no_catalog_modules_as_contracted`

Misma omisión de fixture de la fila 3, sin conexión ni contratación provider. Migración/schema y administración ENFA validados aparte.

Evidencia A/B: [baseline](enfa-release-gate/failure-04-base.txt) y [RC](enfa-release-gate/failure-04-rc.txt). C/D/E: tipo `PHPUnit\Framework\ExpectationFailedException` en ambos; signature esperado/real idéntica; SHA-256 normalizado de ambos `a0d10552f6fc711f0958d6ab3b76b33175a4b502ca9c03a2785ea6a7d3b9cc4c`. No hay cambio observado atribuible a ENFA. Impacto: **NO RELACIONADO**.

### 5. `Tests\Feature\NetworkTenantB2cTest::test_operation_shipment_guide_and_tracking_are_tenant_isolated_and_public_tracking_is_private`

El fallo es el rótulo antiguo de layout; las denegaciones cross-tenant previas pasaron. Guía, tracking seguro, guest y POD provider se ejecutan completos en los gates ENFA.

Evidencia A/B: [baseline](enfa-release-gate/failure-05-base.txt) y [RC](enfa-release-gate/failure-05-rc.txt). C/D/E: tipo `PHPUnit\Framework\ExpectationFailedException` en ambos; signature esperado/real idéntica; SHA-256 normalizado de ambos `fb4bb9c0f274e74e53c74030b95c2078d26a36e99f074917eaf0b2e618245c46`. No hay cambio observado atribuible a ENFA. Impacto: **RELACIONADO INDIRECTAMENTE**.

### 6. `Tests\Feature\NetworkWhiteLabelTest::test_preview_uses_fallback_and_only_included_modules`

No se modifica fallback de preview en ENFA. Las pruebas RC verifican nombre/logo provider y consumer en guía y límites de paths.

Evidencia A/B: [baseline](enfa-release-gate/failure-06-base.txt) y [RC](enfa-release-gate/failure-06-rc.txt). C/D/E: tipo `PHPUnit\Framework\ExpectationFailedException` en ambos; signature esperado/real idéntica; SHA-256 normalizado de ambos `0471f8de6ad6e652584ce50258ddd1a0855e764ba7635a12b2cea3dd04ee38e9`. No hay cambio observado atribuible a ENFA. Impacto: **RELACIONADO INDIRECTAMENTE**.

### 7. `Tests\Feature\ZigoCustomerPortalNavigationTest::test_known_tracking_statuses_use_canonical_labels_on_customer_admin_and_public_surfaces`

La assertion está en ZigoCustomerPortalTest.php aunque se ejecuta en la subclase Navigation. Estados customer previos pasan. Timeline y privacidad provider/guest pasan separados.

Evidencia A/B: [baseline](enfa-release-gate/failure-07-base.txt) y [RC](enfa-release-gate/failure-07-rc.txt). C/D/E: tipo `PHPUnit\Framework\ExpectationFailedException` en ambos; signature esperado/real idéntica; SHA-256 normalizado de ambos `6aef5f43aa59581f7e21430966364e0b2ade971e3823cdf72108cd6ab9f0b73b`. No hay cambio observado atribuible a ENFA. Impacto: **RELACIONADO INDIRECTAMENTE**.

### 8. `Tests\Feature\ZigoCustomerPortalTest::test_known_tracking_statuses_use_canonical_labels_on_customer_admin_and_public_surfaces`

Caso original de la misma assertion que fila 7; no se oculta ni se elimina su ejecución. Gates ENFA cubren tracking/POD/ownership independientemente del encabezado antiguo.

Evidencia A/B: [baseline](enfa-release-gate/failure-08-base.txt) y [RC](enfa-release-gate/failure-08-rc.txt). C/D/E: tipo `PHPUnit\Framework\ExpectationFailedException` en ambos; signature esperado/real idéntica; SHA-256 normalizado de ambos `636ad0d679ffe116e87861751e4107a7d87e9c818ace6613b404212ca27c81c8`. No hay cambio observado atribuible a ENFA. Impacto: **RELACIONADO INDIRECTAMENTE**.

### 9. `Tests\Feature\ZigoNetworkBillingLifecycleTest::test_host_auth_and_two_factor_contract_are_preserved`

Host no confiable es rechazado por TrustHosts antes del 404 esperado. Gate adicional prueba host válido 200 y 2FA/rol en connection/settlement; no se relaja la seguridad.

Evidencia A/B: [baseline](enfa-release-gate/failure-09-base.txt) y [RC](enfa-release-gate/failure-09-rc.txt). C/D/E: tipo `PHPUnit\Framework\ExpectationFailedException` en ambos; signature esperado/real idéntica; SHA-256 normalizado de ambos `5429a2904fb20d8588126dc57d777305cd3943e39e34a7a2bf4d5881e4417572`. No hay cambio observado atribuible a ENFA. Impacto: **RELACIONADO INDIRECTAMENTE**.

### 10. `Tests\Feature\ZigoNetworkBillingLifecycleTest::test_payment_approved_failed_is_visible_and_security_contract_is_preserved`

No es payment confirmation de shipment ENFA. La vista previa de pago aprobó antes del rechazo de host. Pago provider confirmado/fulfillment y gates de acceso pasan.

Evidencia A/B: [baseline](enfa-release-gate/failure-10-base.txt) y [RC](enfa-release-gate/failure-10-rc.txt). C/D/E: tipo `PHPUnit\Framework\ExpectationFailedException` en ambos; signature esperado/real idéntica; SHA-256 normalizado de ambos `5e5df6e81cea8fb0a730adc9115825fded6b5878a41cea2455673179ddf1e87f`. No hay cambio observado atribuible a ENFA. Impacto: **RELACIONADO INDIRECTAMENTE**.

### 11. `Tests\Feature\ZigoNetworkBillingLifecycleTest::test_pre_stage_network_navigation_smoke_has_no_dead_or_duplicate_links`

Todos los GET válidos y checks de navegación anteriores llegaron a pasar. Falla el rechazo final 404 vs 400. API provider y administración segura pasan.

Evidencia A/B: [baseline](enfa-release-gate/failure-11-base.txt) y [RC](enfa-release-gate/failure-11-rc.txt). C/D/E: tipo `PHPUnit\Framework\ExpectationFailedException` en ambos; signature esperado/real idéntica; SHA-256 normalizado de ambos `49ecc9b380dd35dc14fac50edc5ce2e4c92a10264d268e23c3e9a7348ea97de8`. No hay cambio observado atribuible a ENFA. Impacto: **RELACIONADO INDIRECTAMENTE**.

### 12. `Tests\Feature\ZigoNetworkBillingLifecycleTest::test_reminders_are_configurable_and_idempotent`

La assertion precede la idempotencia del aviso; no se declara esa funcionalidad resuelta. ENFA no usa notifyDue para confirmar pagos/fulfillments; subscription vigente se valida aparte.

Evidencia A/B: [baseline](enfa-release-gate/failure-12-base.txt) y [RC](enfa-release-gate/failure-12-rc.txt). C/D/E: tipo `PHPUnit\Framework\ExpectationFailedException` en ambos; signature esperado/real idéntica; SHA-256 normalizado de ambos `39f5fb738078655f32078bcb5c148d58d813a8fe8b59c2f99f0e1dfc66c1833e`. No hay cambio observado atribuible a ENFA. Impacto: **NO RELACIONADO**.

### 13. `Tests\Feature\ZigoNetworkBillingLifecycleTest::test_notice_uses_server_side_order_amount_semantics_and_tenant_payment_cta`

Falla en la primera assertion de envío de aviso; no se afirma que el CTA posterior haya sido probado por este caso. Es independiente de ledger/settlement y payment confirmation de envíos.

Evidencia A/B: [baseline](enfa-release-gate/failure-13-base.txt) y [RC](enfa-release-gate/failure-13-rc.txt). C/D/E: tipo `PHPUnit\Framework\ExpectationFailedException` en ambos; signature esperado/real idéntica; SHA-256 normalizado de ambos `44fef980a7f5126aabd3b257d96e17c6a9a1460bd0b69c91a773a0f39abad9e6`. No hay cambio observado atribuible a ENFA. Impacto: **NO RELACIONADO**.

### 14. `Tests\Feature\ZigoNetworkOnboardingOperationsTest::test_host_auth_and_two_factor_contract_are_preserved`

Es la implementación fuente de la assertion heredada por filas 9/15. Host no confiable sigue denegado; gates nuevos cubren acceso válido/2FA/rol para ENFA.

Evidencia A/B: [baseline](enfa-release-gate/failure-14-base.txt) y [RC](enfa-release-gate/failure-14-rc.txt). C/D/E: tipo `PHPUnit\Framework\ExpectationFailedException` en ambos; signature esperado/real idéntica; SHA-256 normalizado de ambos `6c4c347d37866ef8aca84b0d10f76cc3cec5ad447919b3c5c76c563742fa0906`. No hay cambio observado atribuible a ENFA. Impacto: **RELACIONADO INDIRECTAMENTE**.

### 15. `Tests\Feature\ZigoNetworkOperationsConsoleTest::test_host_auth_and_two_factor_contract_are_preserved`

Método heredado de ZigoNetworkOnboardingOperationsTest; misma línea, excepción y respuesta que baseline. Se conserva la ejecución individual.

Evidencia A/B: [baseline](enfa-release-gate/failure-15-base.txt) y [RC](enfa-release-gate/failure-15-rc.txt). C/D/E: tipo `PHPUnit\Framework\ExpectationFailedException` en ambos; signature esperado/real idéntica; SHA-256 normalizado de ambos `3830c2404194d019a069a562756a7927afef79914d306e231d44fa8391a3703e`. No hay cambio observado atribuible a ENFA. Impacto: **RELACIONADO INDIRECTAMENTE**.

### 16. `Tests\Feature\ZigoNetworkOperationsConsoleTest::test_payment_approved_failed_is_visible_and_security_contract_is_preserved`

Implementación fuente de fila 10. La discrepancia 404/400 no abre acceso. Se validan por separado administración y confirmación de pago ENFA.

Evidencia A/B: [baseline](enfa-release-gate/failure-16-base.txt) y [RC](enfa-release-gate/failure-16-rc.txt). C/D/E: tipo `PHPUnit\Framework\ExpectationFailedException` en ambos; signature esperado/real idéntica; SHA-256 normalizado de ambos `265d40d8673c0fa18f601dc13ff330589b8e54bbf61366e5161a04e3b412446e`. No hay cambio observado atribuible a ENFA. Impacto: **RELACIONADO INDIRECTAMENTE**.

### 17. `Tests\Feature\ZigoNetworkOperationsConsoleTest::test_pre_stage_network_navigation_smoke_has_no_dead_or_duplicate_links`

Implementación fuente de filas 11/21. Checks previos sobre rutas correctas/navegación pasan; failure final permanece idéntico. API provider foundation pasa completa.

Evidencia A/B: [baseline](enfa-release-gate/failure-17-base.txt) y [RC](enfa-release-gate/failure-17-rc.txt). C/D/E: tipo `PHPUnit\Framework\ExpectationFailedException` en ambos; signature esperado/real idéntica; SHA-256 normalizado de ambos `2111d58c252a8da268c36f4774c95c78c9c297219851d52c11ae96d693273270`. No hay cambio observado atribuible a ENFA. Impacto: **RELACIONADO INDIRECTAMENTE**.

### 18. `Tests\Feature\ZigoSaasOnboardingEndToEndTest::test_unknown_visitor_reaches_operational_tenant_owner_customer_and_network`

Falla antes de crear tenant o ejecutar logística. ENFA consume tenants existentes con contexto/dominio verificado; cobertura, quote, checkout y contratación tienen gates independientes. No se afirma que el onboarding SaaS funcione.

Evidencia A/B: [baseline](enfa-release-gate/failure-18-base.txt) y [RC](enfa-release-gate/failure-18-rc.txt). C/D/E: tipo `PHPUnit\Framework\ExpectationFailedException` en ambos; signature esperado/real idéntica; SHA-256 normalizado de ambos `62e9f0d806e50924256331adc289345e1deedf18f64c8d4de5a59b76156197ea`. No hay cambio observado atribuible a ENFA. Impacto: **NO RELACIONADO**.

### 19. `Tests\Feature\ZigoStageHostContractTest::test_driver_and_network_surfaces_do_not_leak_across_hosts`

La petición al host driver configurado anterior aprobó. El host ajeno es denegado antes de 404; no hay fuga de manifest/provider. Driver assignment/ejecución y autorización provider pasan.

Evidencia A/B: [baseline](enfa-release-gate/failure-19-base.txt) y [RC](enfa-release-gate/failure-19-rc.txt). C/D/E: tipo `PHPUnit\Framework\ExpectationFailedException` en ambos; signature esperado/real idéntica; SHA-256 normalizado de ambos `746614008864fd6f3874d59443c1c89499ad6e6051c2cfb94cbe58be9acbf6bf`. No hay cambio observado atribuible a ENFA. Impacto: **RELACIONADO INDIRECTAMENTE**.

### 20. `Tests\Feature\ZigoSupportCenterTest::test_route_contract_enforces_surface_middleware_and_network_scope_controller`

La ruta existente driver.support.store no sustituye la assertion ausente: el fallo permanece. Los flujos críticos ENFA no llaman al alias faltante; inbox, driver y POD pasan sin soporte externo.

Evidencia A/B: [baseline](enfa-release-gate/failure-20-base.txt) y [RC](enfa-release-gate/failure-20-rc.txt). C/D/E: tipo `PHPUnit\Framework\ExpectationFailedException` en ambos; signature esperado/real idéntica; SHA-256 normalizado de ambos `7bb5ede5743bd80ed46d242e1c641765933bb75e19e970ce25eb6c5df559a135`. No hay cambio observado atribuible a ENFA. Impacto: **NO RELACIONADO**.

### 21. `Tests\Feature\ZigoTenantRetentionTest::test_pre_stage_network_navigation_smoke_has_no_dead_or_duplicate_links`

Método heredado de ZigoNetworkOperationsConsoleTest. Se documenta separadamente de filas 11/17; respuesta/stack equivalentes y navegación válida previa pasa.

Evidencia A/B: [baseline](enfa-release-gate/failure-21-base.txt) y [RC](enfa-release-gate/failure-21-rc.txt). C/D/E: tipo `PHPUnit\Framework\ExpectationFailedException` en ambos; signature esperado/real idéntica; SHA-256 normalizado de ambos `448e453c8fc5580139514eb8276b4f02e5de30e40e6b30109727b790f5c74538`. No hay cambio observado atribuible a ENFA. Impacto: **RELACIONADO INDIRECTAMENTE**.

## Gate funcional y seguridad que respaldan las excepciones

- [ENFA completo](enfa-release-gate/enfa-final.txt): connection/readiness, coverage, quote, pricing/snapshot/contract, pago/fulfillment/pickup, aceptación/rechazo, dispatch/driver, tracking/guest/POD, payable/settlement y foundation API.
- [Safety explícita](enfa-release-gate/safety.txt): sin conexión y conexión inactiva, quote nacional idéntica, selección Xperta y checkout HTTP 200, pago verificado guardado y recovery sin artifacts provider ni payable/outbox.
- [Seguridad no local](enfa-release-gate/security.txt): connection/settlement en host configurado 200, host no confiable 400 sin contenido provider, 2FA y rol exigidos. El 404 esperado por tests originales no se reescribe.
- [Nacional/Xperta/Estafeta/checkout](enfa-release-gate/national.txt): 92 pruebas / 444 assertions aprobadas.
- [Migraciones](enfa-release-gate/migrations.txt) y [SQL E2E/settlement](enfa-release-gate/sql-e2e.txt) en MariaDB 10.4.32 desechable, sin TRIGGER.
- [Inventario archivo↔suites↔fallos](enfa-release-gate/change-test-map.md) incluye el frente completo desde c1700cd9, no sólo ENFA-10.

## Incidencias de ejecución de esta revisión

Un intento simultáneo del smoke y suite ENFA compartió Storage::fake y produjo FilesystemIterator sobre pod/signatures durante limpieza de fixtures. Se conserva en parallel-attempt.txt/xml; el smoke final se repitió secuencialmente, sin editar código productivo ni assertions. No se omite esa evidencia ni se etiqueta como uno de los 21 fallos baseline. Los primeros dos errores de los nuevos harness fueron de preparación: mock array tipado que devolvía null, y prueba de FK que chocaba antes con UNIQUE; se corrigió sólo el harness nuevo para comprobar los contratos correctos.

Esta aprobación tiene alcance RC ENFA para configurar/probar en STAGE. No certifica onboarding/renovación SaaS, preview legacy, soporte ni corrección de tests de texto. El smoke seguirá no verde por las 21 excepciones. Antes de activar se ejecuta el runbook con hosts, storage/mail, memberships y cron reales; no se accedió a STAGE/PRD en esta revisión.
