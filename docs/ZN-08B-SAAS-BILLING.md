# ZN-08B — ZIGO SaaS Billing

## Separación financiera

ZN-08B cubre exclusivamente **Tenant → ZIGO**. La venta de guías **Customer → Tenant** conserva el seller OAuth y los intentos de pago de ZN-08A. Los cobros SaaS usan una cuenta plataforma ZIGO, credenciales, intentos, referencias externas y eventos separados; nunca usan el access token del tenant.

## Catálogo y órdenes

`NetworkCommercialProduct` es el catálogo Network-only para PLAN, MODULE, OPERATION_PACK, ADDON, DOMAIN y SERVICE. Cada compra crea un `TenantSaasOrder` con precio, moneda, periodicidad y capacidad congelados. DOMAIN y SERVICE pueden existir como borrador, pero no se publican hasta contar con un adaptador de activación.

El tenant envía únicamente el UUID del producto y una clave de compra ligada a sesión. El servidor resuelve catálogo activo, precio, moneda y contenido. Una orden queda `PENDING_PAYMENT`; el retorno del navegador jamás cambia su estado.

## Pago y activación

Checkout Pro se crea con `PlatformPaymentProvider` y la cuenta receptora de ZIGO. El webhook compartido distingue el propósito mediante una referencia opaca persistida; ZN-08A y ZN-08B validan firmas y credenciales independientes. Para SaaS se recupera el pago desde Mercado Pago y se comprueban referencia externa, monto, moneda y cuenta receptora.

Sólo un pago real `APPROVED` lleva la orden a `PAID` y llama a `TenantSaasActivationService` dentro de una transacción:

- PLAN: crea o renueva la suscripción mediante `SubscriptionService`.
- MODULE/ADDON: concede el entitlement relacionado una sola vez.
- OPERATION_PACK: crea un allowance auditable ligado a la orden.
- DOMAIN/SERVICE: permanecen FUTURE y no se activan.

La activación termina en `ACTIVATED` y es idempotente. Un evento duplicado no duplica suscripción, entitlement ni allowance. Un monto/moneda/cuenta divergente o una orden expirada no activa nada.

## Allowances de operaciones

El límite disponible es el límite base de la suscripción más los `tenant_operation_allowances` activos. Cada pack conserva tenant, suscripción, orden, cantidad y vigencia. `UsageEvent` sigue append-only: comprar capacidad no reescribe ni reinicia consumo histórico.

## Renovaciones

V1 es manual: el tenant crea una nueva orden y paga con Checkout Pro. Un plan igual extiende la vigencia mensual o anual; un cambio de plan cierra la suscripción vigente y crea la nueva mediante el servicio central. No se almacenan tarjetas ni se hacen cargos recurrentes.

## Superficies

- Tenant: `/admin/marketplace`, `/admin/compras`, `/admin/plan`.
- Network superadmin: `/network/catalog`, `/network/saas-orders`.
- Edge de pagos Stage: `payments-stage.zigo-envios.com`, conservando el webhook de ZN-08A.1.

La consola Network puede crear y actualizar catálogo; el tenant sólo puede consultar productos activos y comprar. No existe endpoint para marcar pago o activar manualmente.

## Variables de entorno

```dotenv
ZIGO_MP_PLATFORM_ENABLED=false
ZIGO_MP_PLATFORM_ACCESS_TOKEN=
ZIGO_MP_PLATFORM_ACCOUNT_ID=
ZIGO_MP_PLATFORM_WEBHOOK_SECRET=
ZIGO_MP_PLATFORM_WEBHOOK_URL=
```

En Stage, `ZIGO_MP_PLATFORM_WEBHOOK_URL` debe apuntar al webhook HTTPS canónico. Las credenciales plataforma no son las credenciales OAuth seller y no deben aparecer en vistas, logs o repositorio.

## Riesgos y trabajo futuro

- Validar nuevamente el contrato vigente de Checkout Pro antes de Stage real.
- Definir política fiscal/IVA, refund y revocación posterior a activación antes de habilitarlos.
- Definir adaptadores reales para DOMAIN y SERVICE antes de publicar esos productos.
- En múltiples instancias, webhook y activación requieren la misma base compartida y locks transaccionales efectivos.
- Métricas MRR/ARR pueden derivarse de órdenes activadas, pero no constituyen contabilidad fiscal.

No se implementan CFDI, descuentos, wallet, refunds, provisioning de dominio, pagos Driver ni cobros recurrentes.
