# ENFA release gate — evidencia y alcance

Base original verificada: `b8fa885e98160e0d93ea5d384ff12d31cdd8268c`, rama `feature/enfa-local-provider-network`. Los cambios ENFA-10 se conservaron íntegros. Sin commit/push/deploy, sin .env ni ambientes externos.

| Ejecución | Total | Passed | Failures | Errors | Skipped | Assertions |
|---|---:|---:|---:|---:|---:|---:|
| Base aislada (mismo smoke) | 705 | 684 | 21 | 0 | 0 | 3976 |
| RC original al iniciar revisión | 712 | 691 | 21 | 0 | 0 | 4031 |
| RC final con cuatro verificaciones nuevas | 716 | 695 | 21 | 0 | 0 | 4100 |
| Gate ENFA dedicado (subconjunto) | 377 | 377 | 0 | 0 | 0 | 1336 |
| Safety (subconjunto) | 2 | 2 | 0 | 0 | 0 | 58 |
| Seguridad focal (subconjunto) | 2 | 2 | 0 | 0 | 0 | 11 |
| Nacional/Xperta/Estafeta/checkout (subconjunto) | 92 | 92 | 0 | 0 | 0 | 444 |

Una deprecación PHPUnit previa por metadata en doc-comment permanece; no es un error nuevo. Los subconjuntos no se suman al smoke.

- `baseline.xml/txt`: copia aislada obtenida con git archive; no se modificó la rama/worktree original.
- `rc.xml/txt`: smoke RC inicial de revisión (antes de agregar verificaciones).
- `rc-final.xml/txt`: smoke final ejecutado secuencialmente; mismo filtro original.
- `comparison.json/txt` y `failure-NN-base/rc.txt`: comparación individual del tipo/assertion/esperado/real y SHA-256 del mensaje/stack normalizado. Las fuentes crudas son los JUnit y logs.
- `functional-gates.md`: gates A–T y pruebas exactas ejecutadas; `enfa-final.xml/txt` son la última ejecución dedicada.
- `safety.xml/txt`, `security.xml/txt`, `national.xml/txt`: verificaciones focales adicionales.
- `migrations.txt`: aplicación/rollback/reaplicación de seis migraciones reales en esquema equivalente vacío de MariaDB 10.4.32, once tablas y sus FK/índices, usuario sin TRIGGER. No usa datos productivos.
- `sql-e2e.txt`: contratación/pago fixture/operación/driver/tracking/POD/payable/liquidación parcial concurrente hasta SETTLED en MariaDB desechable.
- `route-probe.txt` y `route-probe-base.txt`: mismo alias faltante `tenant.driver.support.store`; los otros aliases existen en ambos. No se corrigió soporte.
- `change-test-map.md` y `front-current-files.txt`: inventario completo desde c1700cd9 al RC actual, con suites y fallos relacionados.
- `input-hashes.json` y `preservation.json`: SHA-256 inicial/final de los archivos productivos y tests ya existentes en el working tree; todos permanecen iguales durante esta revisión.
- `git-status.txt`, `git-diff-stat.txt`: estado final del repositorio; diff --stat sólo cuenta archivos tracked, mientras el inventario incluye nuevos/untracked.
- `parallel-attempt.xml/txt`: intento descartado como medición final por colisión de Storage::fake entre dos procesos del mismo workspace. Se conserva; no es uno de los 21 ni se ocultó editando tests. Repetición secuencial final: cero errores.

Comando smoke, idéntico en base y RC, sin --exclude:

    php vendor/phpunit/phpunit/phpunit --filter 'Network|LocalShipping|Driver|Tracking|Pod|POD|ApiHub|B2c|B2C|Guest|GuideRecovery|Pricing|Xperta|Estafeta|TenantLogistics' --colors=never --log-junit <destino.xml>

Comando ENFA, solicitado como gate separado:

    php vendor/phpunit/phpunit/phpunit --filter 'NetworkProvider|NetworkTenantProvider' --colors=never --log-junit <destino.xml>

Reproducción del comparador: `php tests/Support/analyze_provider_release_gate.php`. Requiere la copia base temporal del proceso documentado y los dos XML. El archivo de localización de base se mantiene en TEMP, no en configuración de aplicación. Raíces/UUID/CSRF/horas/filemtime son los únicos datos volátiles normalizados; el contenido del CSS fue comparado por SHA-256.

Decisión: **READY FOR STAGE WITH BASELINE EXCEPTIONS**. Los 21 fallos continúan abiertos y se describen individualmente en `../ENFA-RELEASE-BASELINE-EXCEPTIONS.md`. Cero nuevos/materialmente cambiados/críticos ENFA; E2E/safety/migrations PASS. La instancia SQL desechable fue detenida al terminar; no se accedió a STAGE/PRD.
