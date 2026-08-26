# IA-15 · Planes comerciales, recurrencia y Cost Guard

Agentes IA usa el catálogo y la autoridad SaaS de Network. Los planes AI_TRIAL,
AI_INICIAL, AI_CRECIMIENTO, AI_PRO y AI_ENTERPRISE se relacionan con AI_CORE y
sus capacidades IA-12; los add-ons incrementan capacidades mediante el mismo
servicio de provisioning. El seeder es idempotente y conserva productos Stage
anteriores para no alterar snapshots históricos.

Los precios y capacidades provienen de `network_commercial_products`, planes y
snapshots de compra; se muestran más IVA usando `zigo_onboarding.tax_rate`.
Los precios de Stage son configurables/provisionales. El anual representa doce
meses por el precio de diez.

Las suscripciones admiten trialing/active/past_due/grace/suspended/canceled y
campos de proveedor para futura recurrencia Mercado Pago. El pago verificado,
no la URL de retorno, sigue siendo la autoridad de activación.

El Cost Guard añade tarifas versionadas, presupuestos por tenant/globales y un
ledger idempotente por Runtime Run. Simulation e internal tests no se reservan
como consumo billable; los Runtime LIVE se bloquean cuando el presupuesto está
agotado y se reconcilian con el uso real. Las tarifas y presupuestos son sólo
Network interno y no se muestran al cliente.

Trial sin tarjeta, expiración y reconciliación recurrente quedan sujetos al
pipeline existente de onboarding/pagos; no se crean tenants, subscriptions ni
Motores de cobro paralelos. IA-15 no implementa checkout nuevo, facturación CFDI,
Marketing ni una landing logística.

## Recurring subscriptions (Block B)

`RecurringSubscriptionService` utiliza el adapter `MercadoPagoRecurringSubscriptionProvider`
para representar las ofertas Network con `preapproval_plan` y `preapproval`.
La mensualidad usa frecuencia 1 mes y la anual 12 meses; el identificador del
plan remoto se conserva en metadata del producto y el de la suscripción en la
suscripción local. El retorno del navegador no activa nada: sólo un webhook o
una reconciliación server-side (`GET /preapproval/{id}`) puede actualizar el
estado local. Los eventos recurrentes se deduplican mediante
`PlatformPaymentEvent` (provider + event key). Estados `authorized/approved`,
`paused` y `canceled` se mapean explícitamente a `active`, `past_due` y
`canceled`; estados pendientes conservan el estado local hasta nueva evidencia.
