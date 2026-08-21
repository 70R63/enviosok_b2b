# IA-03B — ZIGO Product Shell & Access Model

## Productos visibles

ZIGO Platform es la experiencia del Tenant Admin para suscripciones con capacidades operativas B2C, QUOTES, SHIPPING, TRACKING, LOCAL_SHIPPING o DRIVER. ZIGO AI Workspace es la experiencia del mismo Tenant Admin cuando la suscripción vigente incluye AI_CORE y no incluye módulos operativos.

No existen tenants, autenticación, roles, billing, suscripciones, AI Core, base de datos o rutas de login duplicados. La experiencia se resuelve en cada request desde el tenant activo, la suscripción vigente y su snapshot de entitlements.

## Navegación y acceso

ZIGO Platform conserva su navegación operativa y muestra ZIGO AI cuando AI_CORE está habilitado. ZIGO AI muestra Inicio, Mis agentes, Crear agente, Equipo, Plan, Facturación, Configuración, Soporte y Servicios ZIGO; no presenta enlaces logísticos. Las URLs operativas también están protegidas por una política de workspace y fallan cerradas.

El API Hub actual pertenece a la superficie operativa de ZIGO Platform. Una integración API futura para agentes requerirá un entitlement específico `AI_API`; dicho entitlement y esa integración no forman parte de IA-03B.

Después del pago, la suscripción y su snapshot determinan la experiencia. Un upgrade de AI-only a ZIGO Platform ocurre al habilitar un módulo operativo en la suscripción vigente; no requiere mover datos ni crear otro dominio.

## Límites

El branding básico del agente permanece incluido. Conversations, Knowledge, canales funcionales, Webchat runtime, providers AI y white-label completo todavía no están construidos. No se muestran enlaces falsos para esas capacidades.

`resolveForPresentation()` existe únicamente para renderizar layout y dashboard en esquemas legacy de pruebas que todavía no contienen las tablas comerciales. Nunca autoriza rutas o escrituras. Su clasificación visual conservadora en esos esquemas permanece como una limitación P2; la autorización usa siempre `resolve()`.
