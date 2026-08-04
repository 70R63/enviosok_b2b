# ZIGO DevOps Enterprise Runbook

## Probador Xperta / Estafeta Stage

El probador visual usa exclusivamente `config/zigo_devops_integrations.php`, sección `xperta_stage`, alimentada por variables `ZIGO_DEVOPS_XPERTA_STAGE_*`. Nunca lee ni modifica `services.xperta`, `XPERTA_ENVIRONMENT`, credenciales PRD o el token operacional. Su caché es `zigo_devops:xperta_stage:token` y su lock `zigo_devops:xperta_stage:lock`.

Para habilitarlo, configure todas las variables Stage documentadas en `.env.example`, use una URL HTTPS no productiva y active `ZIGO_DEVOPS_XPERTA_STAGE_TESTER_ENABLED=true`. El backend bloquea configuración incompleta, HTTP, hosts identificados como PRD y servicios fuera de `ZIGO_DEVOPS_XPERTA_STAGE_SERVICES`. Solo sysadmin ejecuta; admin y soporte conservan acceso de lectura. Para rollback, desactive la bandera y limpie la caché de configuración. No cambie `XPERTA_ENVIRONMENT`.

## Arquitectura

El Centro de Control amplía el flujo existente Upload → Validate → Deploy → Rollback → Health → Historial. No modifica contratos públicos ni habilita production. Los módulos enterprise leen `zigo_deployments`, archivos, logs y health existentes.

Tablas nuevas:

- `zigo_devops_releases`: release activa e historial por ambiente.
- `zigo_devops_alerts`: alertas locales, sin notificaciones externas.
- `zigo_devops_audits`: acciones con IP y user-agent hasheados.

## Releases y comparación

Un deployment `success` registra una release y marca la anterior como `superseded`. Nunca se elimina historial. Stage y PRD se consideran sincronizados cuando commit o SHA coinciden. Sin evidencia Git segura se reporta `diverged` o `unknown`; no se inventan cantidades ahead/behind.

## Drift

```bash
php artisan zigo:devops-drift --environment=stage --verify-files --json
```

El comando es read-only y excluye `.env`, `storage`, `vendor`, `node_modules` y `.git`. Los reportes quedan en `storage/app/private/devops-drift-reports`.

## Alertas y auditoría

Se registran fallos de deployment/health, rollback y migraciones. Acknowledge requiere sysadmin. La auditoría cubre upload, validate, deploy, rollback, health y acknowledge. No almacena IP, user-agent, tokens o secretos en claro.

## Health y estado de plataforma

El dashboard solo consume checks persistidos; nunca ejecuta requests al abrir. CRM, B2B y Soporte usan sus URLs de portal. Una URL ausente produce `skipped` con “Portal no configurado”.

## Reportes y permisos

- sysadmin: operación completa, alertas, drift, comparación y reportes.
- admin: lectura.
- soporte: lectura, health y alertas; sin deploy/rollback.

Los reportes filtran ambiente, estado, usuario, fecha, branch y commit, y resumen éxito, fallos, rollbacks, duración, migraciones, health y alertas.

## Promoción y rollback

Stage → PRD está preparado pero no operativo. Requiere release Stage exitosa, paquete validado, health sin fallos, drift limpio, production habilitado y confirmación. Con `ZIGO_DEVOPS_ALLOW_PRODUCTION=false` no aparece acción operativa. El rollback actual restaura archivos y no revierte migraciones.

## Operación diaria

```bash
php artisan zigo:devops-status --environment=stage --json
php artisan zigo:devops-releases --environment=stage --json
php artisan zigo:devops-drift --environment=stage
```

Revisar Resumen, Health, Alertas y Comparación antes de cualquier deployment. Production debe permanecer bloqueado en esta fase.

## Limitaciones

Solo administra ZIGO. No hay promoción automática, notificaciones externas ni soporte multiaplicación. La comparación Git avanzada queda pendiente hasta definir repositorios y comandos allowlisted por ambiente.
