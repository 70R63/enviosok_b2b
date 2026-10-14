# ENFA — STAGE runbook (sin ejecutar en esta entrega)

Usar este documento como registro de ejecución: anotar responsable, fecha, commit exacto y evidencia por paso. Detener activación ante FAIL. WARN exige revisión humana documentada. No contiene IDs ni secretos reales. ENFA-10 aún no tiene commit: no desplegar un working tree anónimo.

## PRE-DEPLOY

1. Revisar `ENFA-10-release-candidate.md`, `ENFA-10-validation.md` y `ENFA-RELEASE-BASELINE-EXCEPTIONS.md`. Gate local: READY FOR STAGE WITH BASELINE EXCEPTIONS; los 21 fallos siguen abiertos y no se corrigen ni se ocultan para desplegar ENFA. Revisar logos/nombres reales y acordar responsables de operación, finanzas y REVIEW. Cualquier fallo nuevo/material/crítico invalida el gate.
2. Obtener backup consistente de DB, archivos privados POD/guías y branding. Verificar restauración en entorno aislado y conservar versión de código/config anterior. Registrar ventana y responsable de reversión.
3. Validar repositorio/rama/commit aprobado: `pwd`, `git branch --show-current`, `git status`, `git rev-parse HEAD`, `git log -10 --oneline`. Rama esperada feature/enfa-local-provider-network; base ENFA-09 b8fa885e. El commit ENFA-10 debe ser creado/aprobado en otro proceso; esta entrega no hace commit.
4. Confirmar PHP CLI y web 8.2+, extensiones exigidas por composer.lock (incluyendo PDO mysql, GD para imágenes y las extensiones de la aplicación). `php -v`, `composer check-platform-reqs`. Composer actual exige Laravel 12; no usar instrucciones históricas Laravel 9 como certificación del runtime.
5. Registrar versión MariaDB (`SELECT VERSION()` por herramienta autorizada), esquema y `php artisan migrate:status`. Verificar tablas padre InnoDB/columnas de FK, privilegios de migración CREATE/ALTER/INDEX/REFERENCES, sin necesidad de TRIGGER. No cambiar privilegios productivos para esta prueba.
6. Verificar cron real del host. No asumirlo por `schedule:list`: comprobar última ejecución, usuario, PHP, cwd, mutex/cache y salida. Debe invocar `php artisan schedule:run` cada minuto. No duplicar jobs con crons específicos.
7. Storage: permisos de escritura de storage/bootstrap/cache, disco public y enlace `public/storage`, persistencia de branding/guide-assets; disco local privado para POD sin exposición pública. Verificar límites de upload y lectura de PNG/JPEG/WebP en runtime.
8. Mail: transporte autorizado y destinatario de pruebas, entrega de recuperación guest; no activar CRM externo para obtener notificaciones. No copiar secretos a logs/checklist.
9. Permisos: acceso Network sysadmin + 2FA en host correcto; memberships provider owner/admin/operator y driver; tenant activo/subscription/entitlements LOCAL_SHIPPING y DRIVER. No asignar sysadmin al conductor u operador para facilitar pruebas.

## DEPLOY

10. Desplegar únicamente código del commit aprobado por el procedimiento del hosting. Este runbook no autoriza a este agente a acceder a STAGE.
11. Si corresponde, `composer install --no-dev --prefer-dist --optimize-autoloader` usando composer.lock; nunca composer update. Ejecutar check-platform-reqs. Mantener instalación fuera de ventana de tráfico si modifica vendor activo.
12. Limpiar/regenerar caches de configuración/rutas/vistas según hosting (`php artisan optimize:clear`, luego `config:cache`, `route:cache`, `view:cache` cuando sean compatibles con las rutas actuales). Revisar códigos de salida; no ocultar errores. Reiniciar workers persistentes por el mecanismo existente.
13. `php artisan migrate:status`; revisar `php artisan migrate --pretend`, backup y luego `php artisan migrate --force`. Confirmar las seis migraciones 2026_10_13 en orden 100000→150000. ENFA-10 no añade otra. Si falla DDL, inspeccionar cambios parciales antes de repetir.
14. Confirmar `php artisan schedule:list`. Ejecutar una vez `network:provider-fulfillment:retry --limit=100`, `network:provider-crm:deliver --limit=100`, `network:provider-payables:reconcile --limit=100`. Antes de ejecutar, entender que recovery procesa fuentes históricas de ese ambiente. Registrar conteos antes/después y exits. Repetir controladamente para comprobar idempotencia; no duplicar compra ni payable. CRM disabled debe permanecer sin entrega externa/retry infinito.
15. Observar una ejecución real de cron y logs. Si no hay cron, NO activar conexión; escalar al responsable del hosting. Readiness scheduler.host WARN no sustituye esta evidencia.

## CONFIG (ZIGO → ENFA, nombres sólo para localizar registros)

16. En Network identificar tenant consumer ZIGO y provider ENFA existentes por nombre y confirmar su identidad/dominio en administración. No crear tenants por suposición. Registrar internamente UUID/slugs reales, sin publicarlos en esta documentación.
17. Verificar dominios consumer verificados y host provider correcto, memberships activas y subscription vigente con capacidad disponible. Login por rol: owner/admin para dispatch; operator para decisiones; driver para ejecución. Mantener usuario sysadmin financiero separado.
18. En branding tenant provider cargar nombre visible y logo válido por infraestructura TenantBranding. En consumer verificar nombre y logo. Confirmar almacenamiento y fallback a nombre/icono sin imagen. No añadir asset ENFA al código.
19. Crear/validar servicio local provider: propietario correcto, activo/publicado/vigente, MXN y FLAT o PACKAGE_FLAT; nombre y SLA reales. No publicar nacional como local ni reutilizar tarifas nacionales.
20. Crear/validar zonas origen/destino y filas de CP oficiales del provider, activas/vigentes. Registrar una ruta cubierta y dos negativas: origen fuera, destino fuera. No ampliar cobertura para hacer pasar la prueba.
21. Configurar package rules por servicio o generales del mismo provider con peso/dimensiones permitidos. Configurar provider rate activo/vigente, tipo de paquete correcto; no usar base_price del catálogo como sustituto.
22. En pricing comercial configurar regla explícita para carrierKey consumer/provider mostrado en Network; segmento anonymous/b2c según canal, package_type y margen/cuota/mínimo acordados. Verificar neto/IVA/total cliente y costo provider; no copiar regla ESTAFETA.
23. Crear/guardar ProviderConnection **inactive**, capability LOCAL_SHIPPING, fechas válidas y lista explícita de servicios. Verificar que no aparecen opciones locales mientras permanece inactiva.
24. Mantener CRM ausente o adapter disabled; no crear key/integración externa. INTERNAL/TENANT_UI debe funcionar sin API entitlement.
25. Ejecutar (sustituir sólo placeholders con identificadores verificados):

    php artisan network:provider-network:check --consumer=slug:CONSUMER --provider=slug:PROVIDER --for-activation

    Debe terminar exit 0 sin FAIL. Leer todos los WARN, especialmente cron. FAIL bloquea activación. No activar para ignorar warnings sin resolver infraestructura.
26. Activar desde Network; el servidor repite readiness. Ejecutar comando anterior sin `--for-activation` y confirmar PASS de conexión/infraestructura/CRM disabled/settlement manual. Repetir guardado no debe duplicar permisos/conexión.
27. Publicación landing es opcional e independiente: añadir sólo UUID provider aprobado a `provider_network.public_partner_uuids` mediante configuración revisada del servidor y regenerar cache. Vacío mantiene socios privados. La conexión debe seguir activa para ese consumer. Ver sección separada “Socios logísticos locales”, nombre/logo, “Envíos locales”, “Disponible según cobertura”. El cotizador decide cobertura; publicación no cambia precio/ranking. No habilitar publicidad.

## E2E EN STAGE (a ejecutar por operador autorizado)

28. Ruta cubierta: cotizar paquete permitido desde host consumer como cliente y como guest. Confirmar provider visible y opción nacional disponible cuando su tarifa lo permita. No mostrar IDs, costos ni margen en HTML.
29. Origen fuera de cobertura, luego destino fuera: provider local no aparece; nacional sigue disponible. Probar sin conexión activa y comprobar equivalencia nacional. Reactivar sólo tras readiness.
30. Seleccionar quote local y abrir checkout. Ver precio/IVA acordados, nombre provider; no depender de nombre para identificar contrato. Manipular otra sesión/usuario/consumer debe denegarse. No volver a seleccionar una oferta nacional accidentalmente.
31. Completar pago de prueba autorizado por proceso STAGE. No usar credenciales/cargos reales improvisados. Sin pago VERIFIED no debe haber operación logística. Tras confirmación: una contratación, fulfillment, shipment provider y READY_FOR_PICKUP. Reintento/reload no duplica. Confirmar origen de recolección sin otro formulario ni compra.
32. Abrir detalle/guía y descargar PDF: consumer branding/logo, “Operado por [provider]”, folio, origen, destino y QR/referencia segura. Leer QR y comprobar destino sin bearer de recuperación ni IDs internos. Revisar visualmente una etiqueta 101.6×152.4 mm, nombres largos, sin logo y con logo. No botón Cancelar.
33. Provider host → `/admin/solicitudes-provider`. Operator ve sólo solicitudes propias, acepta; repetir no duplica. Customer ve “Recolección confirmada”. Provider ajeno y membership revocada no acceden.
34. Owner/admin asigna driver del mismo tenant, activo/disponible, con DRIVER. Driver consumer/ajeno no sirve. Conductor autorizado ejecuta PICKED_UP → IN_TRANSIT → OUT_FOR_DELIVERY. Customer/guest ven timeline amigable sin login provider, IDs, notas internas ni costos.
35. Registrar POD según requisitos: receptor, foto/firma si corresponde y entrega. DELIVERED debe requerir POD, no atajo de tracking. Customer ve “Entregado”, evidencia privada propia; token o tenant ajeno no puede verla. Coordenadas de POD son puntuales, no GPS live.
36. Network settlement: verificar PAYABLE y neto congelado del snapshot aunque tarifas actuales cambien. Entrega NO paga automáticamente. Conciliar externamente pago de prueba autorizado y registrar asignación neta parcial con referencia/key únicas; saldo reduce, sigue PAYABLE. Registrar resto, queda SETTLED. Repetir submit no duplica; exceso/moneda incorrecta se rechazan. No se ejecuta transferencia.
37. Recuperación guest: cerrar sesión, solicitar correo autorizado, abrir enlace en host consumer y descargar misma guía/POD. Enlace expirado/ajeno no funciona; PDF no incluye bearer.
38. Compra independiente de prueba: provider REJECTED. Compra preservada, sin refund/cancelación/dispatch, operación requiere atención, payable REVIEW; customer “Estamos revisando tu envío”. Motivo privado no se publica. Asignación debe rechazarse.
39. Otra compra independiente: aceptar/asignar y ejecutar DELIVERY_FAILED desde conductor. Auditar intento, mantener compra y costo, payable REVIEW; proyección segura, sin cancelación/refund. No resolver REVIEW borrando historia.
40. Con CRM disabled comprobar inbox/dispatch/tracking/POD/payable y logs sin HTTP externo ni outbox fallido infinito. Ejecutar recovery de nuevo y comprobar unicidad. Validar Xperta/nacional con proceso de prueba existente, sin cambiar implementación ni credenciales.
41. Registrar resultado y decisión de activación; si cualquier ownership, precio, acceso guest, entrega/POD o ledger falla, desactivar conexión y abrir incidente conservando evidencia.

## ROLLBACK

42. Desactivar ProviderConnection primero desde Network. Retirar UUID de publicación y reconstruir cache; comprobar cero opciones nuevas/landing de socio.
43. Inventariar compras verificadas sin fulfillment, solicitudes en operación, POD y obligaciones pendientes. Desactivación no cancela envíos ni deuda; puede bloquear recovery de compras aún sin bridge. Asignar atención humana y conciliar antes de revertir código.
44. Preservar contratos/snapshots, operaciones/tracking/POD y ledger/auditoría. No borrar tablas, pagos ni evidencia; no emitir refund automático.
45. Revertir código sólo si es necesario y compatible con historial existente. No ejecutar migrate:rollback genérico: API/ledger con historia bloquean down; DDL puede ser parcial. Planificar migración de datos con responsables técnicos y financieros si se requiere otra reversión de esquema.
46. Verificar nacional independiente, guest de compras históricas y reconciliación. Backup restore sólo tras analizar pérdida de actividad posterior y decidir con responsable. Documentar cierre/acciones pendientes, sin secretos.


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
Repetir validación local antes de entregar el commit por el proceso autorizado:

    php vendor/phpunit/phpunit/phpunit --filter 'Network|LocalShipping|Driver|Tracking|Pod|POD|ApiHub|B2c|B2C|Guest|GuideRecovery|Pricing|Xperta|Estafeta|TenantLogistics' --colors=never --log-junit <ruta-RC.xml>

Comparar contra el XML del mismo comando en copia aislada de b8fa885e. Mantener los 21 fallos originales; no usar excludes ni cambiar assertions. Ejecutar las suites del mismo workspace secuencialmente porque Storage::fake comparte directorios. Ejecutar `NetworkProviderReleaseGateSafetyTest`, `NetworkProviderReleaseGateSecurityTest` y el gate ENFA; no basta que disminuya el número total de fallos.

Para migration gate usar únicamente una instancia MariaDB 10.4 desechable verificada por @@datadir: `php tests/Support/provider_release_migrations.php <puerto-local-mayor-30000> <datadir-temporal>`. El runner rechaza instancias no temporales y crea base/usuario nuevos sin TRIGGER; aplica fixtures padre equivalentes y migraciones reales. No ejecutar este script contra STAGE/PRD ni adaptar sus guards para hacerlo. Para STAGE seguir el procedimiento normal de respaldo/migrate del runbook, sin rollback destructivo.