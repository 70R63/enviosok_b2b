# ZN-UX-01 — ZIGO Experience Foundation & Design System

## Experiencias y ownership

- **ZIGO Customer / White Label:** experiencia del cliente final. Hereda `brand_name`, logo y tokens `primary`, `secondary` y `accent` del tenant.
- **Tenant Admin:** consola operativa del tenant. Mantiene su branding contextual y organización de CRM.
- **ZIGO Driver:** producto propiedad de ZIGO Platform. Su marca principal nunca se white-labeliza; el tenant se presenta como contexto mediante “Operando para”. Puede comercializarse con ZIGO CRM o de forma standalone junto con Dispatch Console y API Hub/Webhooks.
- **ZIGO Network:** consola interna ZIGO/INNOTECH. En esta fase sólo comparte fundamentos seguros; Launchpad, Network Map, Control Center y DevOps no se rediseñan.

## Design system

La fuente única está en `public/css/zigo-design-system.css` y los componentes Blade en `resources/views/components/zigo`. Los tokens incluyen tipografía, escala de espacios, radios, sombras, superficies, primary/secondary/accent, estados semánticos, muted, borders, focus y disabled. Los tenants sólo sobrescriben `--tenant-primary`, `--tenant-secondary` y `--tenant-accent`; no se crea CSS por tenant.

Breakpoints: base mobile-first; `640px` habilita formularios de dos columnas y mayor espacio; `768px` adapta Driver para tablet; `900px` convierte Admin en sidebar; `1024px` habilita mayor densidad y tres columnas. El contenido usa ancho máximo de 1200–1280 px.

Accesibilidad: foco de 3 px, controles con mínimo táctil de 44 px, labels reales, errores con `role=alert`, estados con texto/icono además de color, navegación semántica, reduced motion y campos de móvil a 16 px para evitar zoom involuntario.

## Contrato URL aprobado

| Experiencia | Contrato |
|---|---|
| Público tenant | `/`, `/registro`, `/login`, `/cotizar`, `/rastreo`, `/rastreo/{tracking}` |
| Customer | `/app`, `/app/cotizar`, `/app/envios`, `/app/envios/{shipment}`, `/app/saldo`, `/app/perfil` |
| Tenant Admin | `/admin`, `/admin/operations`, `/admin/dispatch/pickups`, `/admin/drivers`, `/admin/users`, `/admin/plan`, `/admin/configuracion`, `/admin/configuracion/entregas`; `/admin/configuracion/pagos` es futuro |
| Driver actual | `/driver/*` durante foundation |
| Driver futuro | `https://driver.zigo-envios.com/` |
| Network | `/network/*` |

Las rutas futuras no se implementan en ZN-UX-01.

## Driver support y contacto

La entrada de ayuda separa soporte operativo del tenant (dirección, destinatario, paquete y vehículo) de soporte de plataforma ZIGO (aplicación y acceso). “Otro” deberá pedir primero el ámbito. No se implementa chat.

Contacto futuro: “Llamar destinatario” y “Mensaje” sólo durante assignment activo. Una primera iteración puede usar `tel:`; después se evaluarán number masking y mensajería controlada. Al completar la entrega, la PII continúa inaccesible. No se integra proveedor de telecomunicaciones en esta fase.

## Preparación PWA

- Nombre visible e instalable: **ZIGO Driver**; `short_name`: **ZIGO Driver**.
- Theme color: ZIGO Navy `#0B2445`; background: `#F1F5FA`.
- Icon strategy: símbolo ZIGO propio, legible en 192×192 y 512×512, variantes maskable con safe zone de 20%, sin branding tenant.
- `start_url` futuro: `/driver/`; `display`: `standalone`; scope ligado al host Driver.
- Requisitos ZN-UX-02: HTTPS, manifest enlazado, iconos PNG, service worker seguro, install UX y validación de installability.
- Operaciones críticas no serán offline inicialmente. El cache futuro limitará assets versionados y nunca pondrá en cola confirmaciones POD, intentos, GPS ni transiciones de estado.

## Componentes y adopción

Blade ofrece button, icon button, card, badge, alert, field/input/select/textarea, checkbox/radio, uploader, table, pagination, tabs, dialog, empty/loading/skeleton, metric, timeline, action sheet, navigation, page header y breadcrumbs. Los estilos también exponen clases de compatibilidad (`.btn`, `.card`, `.chip`, `.table-wrap`) para adopción progresiva sin duplicar CSS.

## Fuera de alcance

No se modifican pricing, usage, payments, earnings, POD, attempts, dispatch, tracking, isolation, subscriptions ni entitlements. Tampoco se añaden migraciones, pagos/wallet, marketplace, payouts, chat, WhatsApp, push, offline delivery, app nativa, GPS continuo, IA ni optimización de rutas.
