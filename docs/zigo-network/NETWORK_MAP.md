# ZIGO Network Platform

## Visión
Plataforma logística SaaS 360 de INNOTECH. Network Control Center es una consola interna ZIGO, nunca el futuro Tenant Admin.

## Arquitectura general
`config/zigo_network_map.php` es la fuente versionada de nodos, estados, capacidades y conexiones. `NetworkMapRegistry` valida y alimenta Launchpad (a dónde entrar), Topology (cómo se conecta) y Dashboard (qué está pasando).

## Launchpad
`/network`: aplicaciones agrupadas, estado técnico, detalle y accesos administrativos reales. Un nodo sin producto disponible abre detalle, no una URL ficticia.

## Topology
`/network/topology`: núcleo, capas y conexiones clicables. CURRENT es sólido, FUTURE discontinuo y EXPERIMENTAL punteado.

## Dashboard
`/network/dashboard`: conserva tenants/planes/módulos activos; operaciones del mes es `N/A` hasta Usage. Los conteos técnicos derivan del registry.

## Leyenda de estados
- LIVE: funcional actualmente.
- PARTIAL: funcionalidad real incompleta para Network.
- EXPERIMENTAL: piloto controlado, no productivo.
- PLANNED: planeado sin implementación.
- EXTERNAL: dependencia externa.
- DISABLED: existe pero está deshabilitado.

## Nodos actuales
Control, B2C/B2B, CRM, Support, Invoicing, Shipping, Tracking, Xperta, Estafeta, API y DevOps tienen evidencia en rutas/servicios. El registry documenta su grado real y sus límites.

## Conexiones CURRENT
Tenant/Plan/Module, canales → Shipping, Shipping → Xperta/Estafeta/Tracking y Core → CRM/Support/Invoicing/API/DevOps.

## Conexiones FUTURE
ZIGO Local/Driver/POD/GPS, Commerce, Marketing, Warehouse, Billing SaaS y ZIGO AI. FUTURE nunca implica funcionalidad actual.

## Conexiones EXPERIMENTAL
AI Vision → Dimension AI → Shipping, siempre con validación humana.

## ZIGO AI
Línea PLANNED dividida en AI Conversational y AI Vision. El asistente deberá identificarse como virtual, nunca fingir identidad humana.

## AI Conversational
WhatsApp, Webchat, Bot Builder y Human Handoff. Futuro: completar datos, cotizar, iniciar guía, tracking, soporte, facturación, Commerce y CRM. Bot Builder configurará nombre, canal, tono, horario, bienvenida, handoff y permisos (`QUOTE_SHIPMENT`, `CREATE_SHIPMENT`, `GET_TRACKING`, `CREATE_TICKET`, `GET_INVOICE`, `GET_BALANCE`, `COMMERCE`).

### FUTURE AI PILOT — Xperta / operador B2B
WhatsApp → AI Conversational → AI Vision → dirección → datos faltantes → Shipping → cotización → validación humana inicial → guía/PDF → Tracking → Human Handoff. No implementado.

## AI Vision
Address OCR, Document AI y Dimension AI. Una fotografía arbitraria no garantiza medidas exactas: se requerirán referencia conocida, varias tomas, AR/depth cuando exista, confidence score y confirmación humana.

## Comercio
Marketplace interno ZIGO de cajas, cinta, sobres, etiquetas y embalaje; no otra plataforma Laravel.

## Marketing
Evolución de landings/prospectos hacia campañas, redes, promociones y crecimiento tenant.

## GPS
Plataforma externa o futura integración; no módulo propio actual.

## Warehouse
Futuro: recepción, inventario, fulfillment, preparación, despacho y cross docking.

## ZIGO Local
Proveedor futuro de última milla conectado a Driver, POD y GPS.

## Proveedores
Xperta tiene integración real. Estafeta tiene auth, guía, rastreo y pricing local; auditorías señalan coverage/quote moderno incompleto, por eso es PARTIAL.

## Platform Environments vs Tenant Environments
`DEVELOPMENT`, `STAGE` y `PRODUCTION` son ambientes internos de la plataforma INNOTECH/ZIGO, gobernados por DevOps. Stage valida releases con datos de prueba antes de producción; no debe compartir datos productivos sensibles. La consola deberá identificar el ambiente de forma muy visible para reducir errores operativos.

En ZN-02 se contempla `network-stage.zigo-envios.com` para Network Stage y `network.zigo-envios.com` para Network Production. Esta fase no configura DNS, cookies ni hosting.

`TENANT_PRODUCTION` y `TENANT_SANDBOX` son ambientes comerciales futuros por tenant. El sandbox podrá ser opcional (`rapidex-sandbox.zigo-envios.com` o `sandbox-envios.rapidex.com`), mientras producción podrá usar dominio ZIGO o propio. **STAGE interno nunca es el SANDBOX del cliente.** No se implementan dominios ni aislamiento de datos en ZN-01B.1.

DevOps evidencia actualmente deployments, releases, health, smoke/validaciones, rollback y alertas. Feature flags, incidentes y observabilidad consolidada permanecen como capacidades futuras.

## Regla Tenant != Empresa
Tenant es unidad SaaS; Empresa es entidad operativa legacy. No son equivalentes y ZN-01B no agrega `tenant_id`.

## Regla B2C/B2B = canales
B2C y B2B son canales, no tenants.

## Roadmap ZN
| Fase | Objetivo | Estado |
|---|---|---|
| ZN-00 | Auditoría | COMPLETED |
| ZN-01A | Network Foundation | COMPLETED |
| ZN-01B | Launchpad + Map + Dashboard | CURRENT |
| ZN-01C | Plan → Modules, Tenant → Plan, Construye tu ZIGO | COMPLETED |
| ZN-02 | TenantDomain + Branding + White Label | COMPLETED |
| ZN-03 | Tenant Admin + Memberships | COMPLETED |
| ZN-04 | Subscription + Entitlements + Usage Foundation | CURRENT |
| ZN-03 | Tenant Admin + Memberships | PLANNED |
| ZN-04 | Subscription + Entitlements | PLANNED |
| ZN-05 | Primer canal tenant-aware | PLANNED |
| ZN-06 | ZIGO Local | PLANNED |
| ZN-07 | Driver + Tracking + POD | PLANNED |
| ZN-08 | Billing SaaS | PLANNED |
| ZN-09 | B2B V2 Multi-Tenant | PLANNED |
| ZN-10 | Commerce | PLANNED |
| ZN-11 | AI Conversational | PLANNED |
| ZN-12 | AI Vision | PLANNED |
| ZN-13 | Marketing | PLANNED |
| ZN-14 | GPS | PLANNED |
| ZN-15 | Warehouse / Fulfillment | PLANNED |

No hay que esperar ZN-15 para vender: el primer tenant rentable debe llegar tras Foundation, White Label, Tenant Administration y Entitlements.

## Próximos hitos
ZN-01C debe formalizar Tenant → Plan y Plan → Modules sin billing, usage ni tenancy operativa. Un futuro Tenant Admin (ej. `rapidex.zigo-envios.com/admin`) nunca verá otros tenants, márgenes/configuración global, DevOps, proveedores ajenos, Billing global ni este mapa interno.

## Construye tu ZIGO — ZN-01C
La primera configuración comercial relaciona `Plan → Modules` mediante `network_plan_modules` y `Tenant → Current Plan` mediante `network_tenants.current_plan_id`. `current_plan_id` es una asignación comercial nullable; **no** representa Subscription, recurrencia, cobro ni entitlement runtime. Subscription y Entitlements reemplazarán/complementarán esta lectura en ZN-04.

La lista efectiva mostrada hoy para un tenant proviene exclusivamente de los módulos con `is_included=true` en su plan. `limit_value` e `included_operations` son configuración comercial, no medición de Usage.

Un **Network Map Node no equivale a Commercial Module**. El mapa puede contener arquitectura, proveedores, ambientes y roadmap —por ejemplo ZIGO AI— sin que exista todavía un producto vendible en `network_modules`. AI Conversational y AI Vision sólo deberán incorporarse al catálogo cuando exista una decisión explícita de comercialización.

## ZN-02 White Label Foundation
Flujo implementado: `HTTP HOST → TenantDomain → Tenant active → TenantContext request-scoped → Branding → Current Plan → Modules incluidos`. `TenantContext` no representa al usuario autenticado: el tenant operativo se resuelve principalmente por host.

`ZigoDomainResolver` continúa protegiendo portales internos ZIGO (CRM, B2B, Soporte, API y DevOps). `TenantDomainResolver` es independiente y sólo atiende dominios white-label explícitamente registrados. Aunque infraestructura disponga de wildcard `*.zigo-envios.com` y Wildcard SSL, un hostname desconocido nunca se infiere por slug: responde 404. Dominios `pending` o `disabled` no resuelven; tenants `inactive` o `suspended` tampoco operan en esta fase.

Los dominios soportan `subdomain|custom` y `production|sandbox`, pero Sandbox, validación DNS/CNAME, SSL y provisioning automático siguen pendientes. Stage es ambiente interno ZIGO y no equivale a Tenant Sandbox. Network Stage futuro: `network-stage.zigo-envios.com`; Network Production futuro: `network.zigo-envios.com`. No se modifica DNS, SiteGround ni `SESSION_DOMAIN`.

### Prueba local
1. Agregar manualmente en el archivo hosts de Windows: `127.0.0.1 cliente-piloto.zigo.local` (esta aplicación nunca lo modifica).
2. Registrar `cliente-piloto.zigo.local` en Network como `verified` para un tenant `active`.
3. Abrir `http://cliente-piloto.zigo.local:8000/white-label`.

Branding almacena rutas de PNG/JPG/WEBP mediante el disco `public`, nunca base64 ni SVG. Para servir archivos localmente puede requerirse ejecutar manualmente `php artisan storage:link`; ZN-02 no lo ejecuta. El preview es sólo un shell visual y no cotiza, rastrea ni crea guías reales.

## ZN-03 Tenant Admin & Memberships

El acceso administrativo tenant sigue el flujo `Host -> TenantDomain verified -> TenantContext -> TenantMembership active -> /admin`. El host es la autoridad: el cliente nunca selecciona ni envía un `tenant_id` para decidir autorización. Un usuario puede pertenecer a varios tenants mediante `network_tenant_memberships`, con un rol distinto en cada uno.

`User` continúa siendo la identidad global existente y no recibe `tenant_id`. `TenantMembership` expresa pertenencia y rol (`owner`, `admin`, `operator`, `support`, `billing`, `viewer`) exclusivamente dentro de un tenant. Por tanto, un rol global no equivale a un rol tenant: `sysadmin` protege ZIGO Network, mientras que `owner` y `admin` permiten administrar el tenant pero jamás conceden acceso a `/network`.

La consola `/admin` usa el branding, plan y módulos incluidos del tenant resuelto. No implementa operación B2C/B2B real, Usage, Billing ni Entitlements. Las sesiones continúan host-only porque no se modifica `SESSION_DOMAIN`: no existe SSO entre `clienteA.zigo.local` y `clienteB.zigo.local`.

Las invitaciones se difieren a ZN-03B. Cuando se incorporen deberán usar tokens hasheados, expirables y de un solo uso; ZN-03 sólo agrega usuarios ya existentes por email y nunca crea contraseñas provisionales.

### Frontera de administración por host

Tres superficies pueden compartir segmentos de URL sin compartir autoridad. **ZIGO Network Admin** vive en `/network` y exige `network.auth + network.superadmin`. **Tenant Admin** vive en `/admin` sobre un `TenantDomain` verificado y exige `tenant.resolve + tenant.auth + TenantMembership`. **Legacy Internal Admin** conserva `/admin/incidencias` y `/admin/conciliacion-saldo`, pero pertenece al portal interno B2C y exige adicionalmente `zigo.portal:b2c,strict`, autenticación y roles globales.

El modo `strict` mantiene la verificación exacta del host B2C incluso cuando el routing por subdominios general está desactivado para compatibilidad local. Por ello, compartir el path `/admin` nunca habilita endpoints internos desde un dominio tenant; esos intentos responden 404 antes de evaluar la sesión o el rol.

## ZN-04 Subscription, Entitlements y Usage

El flujo SaaS es `Tenant -> Subscription -> Plan -> Entitlement Snapshot -> Usage`. Un **Plan** es una plantilla comercial editable; una **Subscription** es la asignación histórica de ese plan para un tenant y periodo. `network_tenants.current_plan_id` permanece sólo como compatibilidad: cuando existe una Subscription vigente, `Subscription.plan_id` es la fuente preferida; sin ella, Tenant Admin puede usar el plan anterior como fallback explícito. Esta deuda debe retirarse después de migrar todos los tenants.

`PlanModule` configura la plantilla, mientras `Entitlement` congela sólo módulos incluidos, código, habilitación y límite al crear la Subscription. Cambiar posteriormente el Plan no modifica el snapshot. El límite contractual `included_operations` también se congela como `Subscription.operations_limit`; no debe confundirse con Usage.

Usage usa eventos append-only con métrica, cantidad, fecha e idempotency key única por tenant/métrica. `operations` es la única métrica contractual inicial; `quotes`, `shipments` y `tracking_queries` quedan catalogadas sin integración operativa. Los totales se limitan a `current_period_start..current_period_end`; se calculan remaining, percentage y over-limit, pero ZN-04 no bloquea automáticamente al exceder el límite.

Tenant status y Subscription status son independientes. `trial`, `active`, `past_due` y `grace` operan; `canceled` opera hasta el final del periodo; `suspended` y `ended` muestran una vista segura. Esto no cambia automáticamente `Tenant.status`. Login y logout permanecen disponibles para una suscripción suspendida, mientras las vistas operativas usan `tenant.subscription`.

Los cambios de estado dejan `network_subscription_events` append-only. No hay borrado de historial, proration, renovación automática, pagos, facturas ni webhooks. Un cambio de plan futuro debe terminar la Subscription actual y crear otra con un snapshot nuevo; nunca debe mutar silenciosamente el snapshot vigente.
