# IA-13 — Commercial Marketplace / Agentes IA V1

IA-13 convierte Agentes IA en un producto comercial del catálogo SaaS existente. Reutiliza `NetworkCommercialProduct`, `TenantSaasOrder`, el pipeline de pago y `TenantSaasActivationService`; no crea tenants, subscriptions ni tablas AI paralelas.

## Producto y ampliaciones

El producto base visible es **Agentes IA**. Su metadata de catálogo marca `ai_product=true`, `ai_kind=base` y declara las capacidades de IA-12. La activación asegura un único entitlement `AI_CORE` en la Subscription vigente y aplica los límites mediante `AiCapacityProvisioningService`.

Los add-ons usan `ai_kind=addon` y `ai_addons`, por ejemplo:

- Agente adicional: `MAX_AGENTS +1`.
- WhatsApp: `MAX_WHATSAPP_CHANNELS +1`.
- Conversaciones: incremento configurable de `MONTHLY_CONVERSATIONS`.

Cada add-on se registra con una clave derivada de la orden (`order:{order_id}:{capability}`), conserva historial y se suma a la autoridad de capacidad de IA-12. Repetir la activación de una misma orden no duplica filas ni capacidad.

## Tenant existente y AI_CORE manual

Un cliente logístico conserva su plan y Subscription; Agentes IA se agrega sobre esa Subscription. No se sustituye `ZIGO Esencial` por un plan AI ni se crea un segundo Tenant.

Si existe un `AI_CORE` manual sin orden comercial, el Marketplace muestra Agentes IA activo con origen administrativo y sin inventar precio histórico. Las ampliaciones comerciales siguen disponibles. Un Tenant futuro AI-only podrá reutilizar el mismo producto y activador cuando IA-14 incorpore su funnel, sin que el catálogo dependa de logística.

## Marketplace y Compras

La vista multiproducto separa productos contratados, ampliaciones AI y otros productos. El menú visible es **Marketplace**; **Compras** permanece separado. Las órdenes y compras se consultan siempre con `TenantContext`, y el flujo existente conserva sus controles de pago, ownership e idempotencia.

La activación exige una orden pagada/aprobada y bloquea la orden. Estados failed, cancelled o no pagados no aplican capacidades. Los callbacks repetidos encuentran una orden ya activada y no vuelven a provisionar.

## Precios y Stage

Los precios vienen del catálogo, nunca de Blade o controllers. El `AiCommercialCatalogSeeder` es idempotente y está limitado a entornos no productivos. Sus importes son provisionales de Stage y **no constituyen la tarifa comercial final**.

## Límites y seguridad

La aplicación usa exclusivamente `AiCapacityService` y `AiCapacityProvisioningService` de IA-12. El cliente no aporta `tenant_id`, `subscription_id` ni `entitlement_id` como autoridad. Las órdenes, entitlements y capacidades se resuelven server-side y permanecen tenant-scoped.

IA-13 no implementa billing nuevo, Mercado Pago nuevo, checkout paralelo, Marketing ni landing pública AI. Marketing queda preparado como otra familia de productos del catálogo; el funnel público AI-only pertenece a IA-14.
