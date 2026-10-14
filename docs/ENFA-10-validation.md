# ENFA-10 — Evidencia de validación

## Entorno y límites

PHP 8.2.12 / Laravel 12 (composer.lock) / PHPUnit 11.5.56. SQLite :memory: con fixtures controlados. Mail y storage falsos, HTTP externo impedido en E2E provider. APP_KEY de pruebas sólo en proceso. No se editó .env; no STAGE/PRD, CRM real, cobro, refund ni payout. Sin commit/push/deploy.

## Resultados de la implementación ENFA-10 (histórico)

| Comprobación | Resultado |
|---|---|
| Estado inicial | Rama esperada, HEAD b8fa885e98160e0d93ea5d384ff12d31cdd8268c, árbol limpio |
| ENFA-10 integrada | 6 pruebas / 53 assertions aprobadas: ciclo completo, reject, failed delivery, publicación, branding seguro y logo consumer PDF |
| ENFA-01..10 provider | 373 pruebas / 1267 assertions, cero errores/fallos; 1 deprecación preexistente |
| Nueva fuera de cobertura | Origen y destino fuera individualmente conservan opciones nacionales en agregador |
| Regresión ampliada | 712 pruebas / 4031 assertions / 21 fallos / 1 deprecación |
| HEAD base mismo filtro | 705 pruebas / 3976 assertions / mismos 21 fallos / misma deprecación |
| Comparación de nombres de casos fallidos | Compare-Object sin diferencias |
| PDF visual | Windows.Data.Pdf, una página fixture con logo consumer, direcciones, folio, QR y operador legibles |

Filtro de regresión:

    php vendor/phpunit/phpunit/phpunit --filter 'Network|LocalShipping|Driver|Tracking|Pod|POD|ApiHub|B2c|B2C|Guest|GuideRecovery|Pricing|Xperta|Estafeta|TenantLogistics' --colors=never

Filtro provider:

    php vendor/phpunit/phpunit/phpunit --filter 'NetworkProvider|NetworkTenantProvider' --colors=never

La prueba integrada parte de catálogo/cobertura/tarifas reales de fixture y usa servicios de dominio; pago VERIFIED es una confirmación controlada persistida. Las suites existentes verifican además HTTP checkout, payment, inbox, permisos, API, guest y nacional. No se afirma que sea un browser E2E con un gateway de pago.

## MariaDB 10.4.32 desechable

Inicialización con mysql_install_db.exe, proceso mysqld con --no-defaults, bind 127.0.0.1 y datadir único bajo TEMP. El runner `tests/Support/provider_contract_concurrency.php` exige marcador/datadir temporal y verifica @@datadir antes de crear bases; crea usuario restringido sin TRIGGER. No usa conexión .env. La primera instancia tuvo corrupción Aria de privilegios, y el primer GRANT en la segunda también falló; las siguientes ejecuciones pasaron. Es una limitación reproducible del bootstrap local registrada, no un resultado de aplicación aprobado.

Ejecuciones aprobadas en la segunda instancia:

- `network-readiness`: migraciones fixtures, candidato inactivo, activación repetida, exclusión de tarifa ajena, rollback de activación fallida y ausencia de efectos operativos/CRM/context switching.
- `pod`: concurrencia de entrega, un POD y evento efectivos, economía congelada, ownership y rollback operativo.
- `failed`: concurrencia de fallo, auditoría/estado esperado, economía congelada y rollback operativo.
- `payable-create`: carrera crea una obligación.
- `settlement-repeat`: submit idéntico produce una entrada.
- `settlement-partials`: quote→contract→pago fixture→fulfillment→accept→driver→tracking→POD→PAYABLE, parciales simultáneos completan SETTLED.
- `settlement-overpay`: rechaza sobrepago concurrente.
- Modos financieros verifican migración ledger up/down/up vacío y bloqueo down poblado, reconciliación idempotente, neto 100.00 y venta 145.00 congelados.

No se ejecutó migrate completo de todo el producto ni se afirma certificación de la configuración de hosting. Las dependencias previas se crean mediante fixtures y migraciones reales de shipping/provider. Instancias locales se detienen al finalizar, sin borrar evidencia financiera del ambiente de aplicación.

## Fallos reportados inicialmente (ver comparación final individual)

La lista debajo coincide en base y cambios finales. Las suites Network de documentos ENFA-08/09 usaban un filtro menor y reportaban siete; esta ejecución incorpora más clases. No se corrigen aquí módulos fuera de alcance. Algunos casos se descubren bajo alias de clase y por eso dos nombres comparten implementación.

- `Tests\Feature\DriverLastMileFoundationTest::test_permissions_state_machine_public_tracking_append_only_and_usage_contract`
- `Tests\Feature\DriverLastMileFoundationTest::test_customer_pickup_is_fixed_idempotent_ignores_client_logistics_fields_and_enters_tenant_queue`
- `Tests\Feature\NetworkBuildYourZigoTest::test_dashboard_and_tenant_detail_show_only_effective_modules_without_blade_directives`
- `Tests\Feature\NetworkBuildYourZigoTest::test_tenant_without_plan_shows_no_catalog_modules_as_contracted`
- `Tests\Feature\NetworkTenantB2cTest::test_operation_shipment_guide_and_tracking_are_tenant_isolated_and_public_tracking_is_private`
- `Tests\Feature\NetworkWhiteLabelTest::test_preview_uses_fallback_and_only_included_modules`
- `Tests\Feature\ZigoCustomerPortalNavigationTest::test_known_tracking_statuses_use_canonical_labels_on_customer_admin_and_public_surfaces`
- `Tests\Feature\ZigoCustomerPortalTest::test_known_tracking_statuses_use_canonical_labels_on_customer_admin_and_public_surfaces`
- `Tests\Feature\ZigoNetworkBillingLifecycleTest::test_host_auth_and_two_factor_contract_are_preserved`
- `Tests\Feature\ZigoNetworkBillingLifecycleTest::test_payment_approved_failed_is_visible_and_security_contract_is_preserved`
- `Tests\Feature\ZigoNetworkBillingLifecycleTest::test_pre_stage_network_navigation_smoke_has_no_dead_or_duplicate_links`
- `Tests\Feature\ZigoNetworkBillingLifecycleTest::test_reminders_are_configurable_and_idempotent`
- `Tests\Feature\ZigoNetworkBillingLifecycleTest::test_notice_uses_server_side_order_amount_semantics_and_tenant_payment_cta`
- `Tests\Feature\ZigoNetworkOnboardingOperationsTest::test_host_auth_and_two_factor_contract_are_preserved`
- `Tests\Feature\ZigoNetworkOperationsConsoleTest::test_host_auth_and_two_factor_contract_are_preserved`
- `Tests\Feature\ZigoNetworkOperationsConsoleTest::test_payment_approved_failed_is_visible_and_security_contract_is_preserved`
- `Tests\Feature\ZigoNetworkOperationsConsoleTest::test_pre_stage_network_navigation_smoke_has_no_dead_or_duplicate_links`
- `Tests\Feature\ZigoSaasOnboardingEndToEndTest::test_unknown_visitor_reaches_operational_tenant_owner_customer_and_network`
- `Tests\Feature\ZigoStageHostContractTest::test_driver_and_network_surfaces_do_not_leak_across_hosts`
- `Tests\Feature\ZigoSupportCenterTest::test_route_contract_enforces_surface_middleware_and_network_scope_controller`
- `Tests\Feature\ZigoTenantRetentionTest::test_pre_stage_network_navigation_smoke_has_no_dead_or_duplicate_links`

La clasificación inicial resumía textos/branding, host, avisos y soporte. El gate final identifica además dos errores 500 por fixture sin network_subscriptions y precisa cada assertion en el documento de excepciones. Ver logs completos para expected/actual y líneas exactas. La única expectativa afectada por ENFA-10 fue “Listo para recolectar” del resumen provider: se actualizó a “Recolección solicitada” para usar el timeline y eliminar el estado duplicado/confuso.

## Evidencia adjunta

- `enfa10-evidence/baseline.txt`: ejecución aislada HEAD base.
- `enfa10-evidence/regression.txt`: regresión ampliada final.
- `enfa10-evidence/provider.txt`: suite provider completa.
- Verificación adicional de checkout/selección/E2E tras corregir compilación Blade: 63 pruebas / 212 assertions, sin fallos ni errores.
- `enfa10-evidence/front-files.txt`: inventario de archivos ENFA-01..09.
- `enfa10-evidence/front-surface.diff`: cambios previos de rutas/config/scheduler.

El diff completo ENFA-10 para revisión se entrega como archivo temporal `enfa10-evidence/review.diff`; no es un commit ni incluye su propio contenido. `git diff --check` y status final se registran al cierre.

## Inventario final ENFA-10

Archivos modificados:

- `app/Domain/Network/Providers/LocalProviderQuoteEvaluator.php`
- `app/Domain/Network/Providers/Models/ProviderQuoteSnapshot.php`
- `app/Domain/Shipping/Local/LocalGuidePdfRenderer.php`
- `app/Domain/Shipping/Local/LocalShipmentService.php`
- `resources/views/b2c/checkout.blade.php`
- `resources/views/b2c/opciones.blade.php`
- `resources/views/b2c/partials/cotizador.blade.php`
- `resources/views/b2c/provider-fulfillment.blade.php`
- `resources/views/index.blade.php`
- `tests/Feature/NetworkProviderFulfillmentTest.php`
- `tests/Feature/NetworkTenantProviderQuotingTest.php`

Archivos creados (además de review.diff generado al cierre):

- `app/Domain/Network/Providers/ProviderPresentation.php`
- `config/provider_network.php`
- `docs/ENFA-10-release-candidate.md`
- `docs/ENFA-10-validation.md`
- `docs/ENFA-STAGE-RUNBOOK.md`
- `docs/enfa10-evidence/baseline.txt`
- `docs/enfa10-evidence/checkout.txt`
- `docs/enfa10-evidence/front-files.txt`
- `docs/enfa10-evidence/front-surface.diff`
- `docs/enfa10-evidence/provider.txt`
- `docs/enfa10-evidence/regression.txt`
- `tests/Feature/NetworkProviderReleaseCandidateTest.php`

Cierre: git diff --check aprobado. Working tree con cambios ENFA-10 sin staged, sin commit/push/deploy. HEAD conserva b8fa885e. Instancias MariaDB desechables detenidas.



## Release gate final — revisión de baseline

**READY FOR STAGE WITH BASELINE EXCEPTIONS**

| Gate | Resultado |
|---|---|
| Known baseline failures | 21 |
| New failures introduced by ENFA | 0 |
| Baseline failures materially changed | 0 |
| Critical ENFA failures | 0 |
| ENFA E2E | PASS — 377 casos / 1336 assertions |
| Feature safety | PASS — ausente/inactiva; 2 casos / 58 assertions |
| Migration gate | PASS — MariaDB 10.4.32 desechable |
| Smoke RC | 716 total; 695 passed; 21 fallos; 0 errores/skipped |
| Smoke HEAD base b8fa885e | 705 total; 684 passed; 21 fallos; 0 errores/skipped |

Una deprecación PHPUnit preexistente permanece en ambas ejecuciones. Se mantuvo íntegro el filtro original del smoke. Los cuatro casos agregados por esta revisión son exclusivamente verificaciones de safety y seguridad; no hubo modificación productiva ni de assertions existentes. Los totales de gates focales son subconjuntos, no se suman al total del smoke.

La comparación por caso confirma presencia, tipo de excepción, assertion, esperado/real y mensaje/stack completo normalizado con SHA-256 idéntico. No basta coincidencia de nombres: ver [tabla exacta de 21 y justificación individual](ENFA-RELEASE-BASELINE-EXCEPTIONS.md), [A–T funcionales](enfa-release-gate/functional-gates.md), [archivo↔suites↔fallos](enfa-release-gate/change-test-map.md), [comparación JSON](enfa-release-gate/comparison.json) y [smoke final](enfa-release-gate/rc-final.txt).

Esta decisión sustituye la clasificación conservadora inicial de ENFA-10, basada sólo en coincidencia de casos. Se aceptan los 21 como excepciones ENFA documentadas, **sin declararlos resueltos**. No autoriza omitir controles del hosting: backup/restauración, migraciones del esquema real, cron, storage/mail, hosts válidos, memberships/entitlements y configuración comercial siguen siendo pasos del runbook antes de activar. No se accedió a STAGE/PRD, no se editó .env y no hubo commit/push/deploy.