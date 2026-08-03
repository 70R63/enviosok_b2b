# Estrategia de proveedores de envío

El carrier comercial y el proveedor HTTP son conceptos distintos. Una opción mostrada como Estafeta puede provenir de HTTP Xperta, del motor contractual local o del flujo legacy local.

## Matriz confirmada

| Operación | Estrategia | Proveedor HTTP | Estado |
|---|---|---|---|
| auth Estafeta | estafeta_direct | Estafeta | confirmado |
| coverage directa | unavailable | ninguno | contrato desconocido |
| quote directa | unavailable | ninguno | contrato desconocido |
| coverage Xperta | xperta_estafeta | Xperta | condicionada a frequency habilitada |
| quote Xperta | xperta_estafeta | Xperta | confirmado |
| quote legacy | contractual_local / legacy_estafeta | ninguno | confirmado local |
| shipment Xperta | no habilitado en esta fase | Xperta | contrato existente, flag apagado |
| tracking | legacy_estafeta | Estafeta | confirmado legacy |

Xperta cotiza con LTD `estafeta`, servicios `terrestre` y `diasig`. El costo proveedor normalizado se conserva en `provider_base_price`; margen, seguro, descuentos e IVA comerciales permanecen fuera del provider.

## Diagnóstico y Stage

```bash
php artisan zigo:shipping-contract-audit --provider=all --environment=stage --include-legacy
php artisan zigo:b2c-quote-flow-map --no-external-call --json
php artisan zigo:shipping-strategy --environment=stage --operation=all --carrier=estafeta
php artisan zigo:shipping-probe --environment=stage --carrier=estafeta --operation=quote --cp-origin=64000 --cp-destination=64000 --weight=1 --length=20 --width=20 --height=20 --package-type=box --confirm=SHIPPING-STAGE --record-contract
```

Sin confirmación o con `--dry-run`, el probe no ejecuta tráfico. Production está bloqueado. El recorder registra estructuras y nombres, nunca secretos o payloads completos.

## Variables y rollback

- `XPERTA_ENABLED`
- `XPERTA_ENVIRONMENT`
- `XPERTA_BASE_URL` y credenciales Xperta existentes
- `ZIGO_SHIPPING_CONTRACT_RECORDER_ENABLED=false`
- `ZIGO_SHIPPING_CONTRACT_RECORDER_ALLOW_PRODUCTION=false`
- `ZIGO_B2C_UNIFIED_QUOTE_ENABLED=false`
- `ZIGO_DEVOPS_SHIPPING_STAGE_ACTIVE_CHECKS=false`

Rollback: apagar `ZIGO_B2C_UNIFIED_QUOTE_ENABLED` y los active checks. El flujo B2C principal no fue reemplazado.

Para conectar B2C deben pasar el probe Stage, pruebas de fallback explícito, comparación contractual y validación de que `provider_base_price` nunca se trate como precio final.
