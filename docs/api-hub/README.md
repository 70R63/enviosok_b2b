# ZIGO API Hub V1

## Legacy inventory

Legacy permanece sin cambios: `GET /api/hub/cp/{cp}`, `GET /api/hub/ping`, Billing API, `X-ZIGO-API-KEY`, `ValidateZigoApiKey`, `ApiClient`, `ApiKey`, `ApiUsageLog`, productos y webhooks CRM. V1 no modifica hashes, headers, respuestas ni tablas legacy.

V1 usa `Authorization: Bearer`, host dedicado y modelos `tenant_api_*`. Un API Client pertenece a un Tenant y nunca representa a un customer final.

## Ejemplo

```bash
curl "${ZIGO_API_URL}/api/hub/v1/postal-codes/64000" \
  -H "Authorization: Bearer ${ZIGO_API_KEY}" \
  -H "X-ZIGO-Request-Id: erp-postal-0001"
```

Los POST de quote, shipment y pickup requieren `Idempotency-Key`. Una repetición idéntica devuelve el resultado almacenado y no consume otra unidad; un payload distinto responde `409 IDEMPOTENCY_CONFLICT`.

## Medición

Sólo respuestas autenticadas 2xx consumen una unidad. No consumen 401, 403, 5xx ni replay idempotente. El lookup postal interno utilizado por UI y servicios no pasa por este middleware y no consume cuota.

## Webhook signing

Headers: `X-ZIGO-Event-Id`, `X-ZIGO-Timestamp`, `X-ZIGO-Signature`. Manifest: `{event_id}.{unix_timestamp}.{raw_json}`. Signature: `sha256=` + HMAC-SHA256 hexadecimal con el secreto del endpoint. El receptor debe validar firma con comparación constante, timestamp y event ID contra replay.

El payload no incluye direcciones, teléfonos, POD, firma, GPS, Driver ni earnings. Los fallos outbound quedan en PENDING/RETRY/EXHAUSTED y nunca revierten una transición logística.

## Stage

`api-stage.zigo-envios.com` usa el mismo Laravel `/public`. Configurar `ZIGO_API_ENABLED=true`, `ZIGO_API_HOST=api-stage.zigo-envios.com`, `ZIGO_API_URL=https://api-stage.zigo-envios.com` y HTTPS. Producción futura: `api.zigo-envios.com`.

No hay fecha de deprecación legacy. La migración será explícita y controlada.
