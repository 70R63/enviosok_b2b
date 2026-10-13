# ENFA-01: foundation de conexiones entre tenants

## Modelo y convenciones

Se reutilizan el namespace `App\Domain\Network`, el prefijo de tablas
`network_`, `network_tenants` y el catálogo existente `local_shipping_services`.
No se crean tenants ni se precargan relaciones para empresas concretas.

`ProviderConnection` representa una relación direccional por consumer, provider
y capability. Una sola fila por combinación durante todo su ciclo de vida:
se reactiva o actualiza, no se insertan duplicados inactivos para conservar historia.
La auditoría/historial de cambios queda para una fase posterior.

`network_provider_connections` contiene ID, consumer_tenant_id,
provider_tenant_id, capability, status (active/inactive), valid_from y valid_to
opcionales y timestamps. El estado inicial es inactive.

`network_provider_connection_services` contiene ID, connection_id,
provider_tenant_id, capability (únicamente LOCAL_SHIPPING),
local_shipping_service_id y timestamps. Es una lista explícita de permisos:
una relación sin filas autorizadas no muestra servicios. No existe permiso
implícito para todo el catálogo ni herencia de permisos de otro consumer.

Los campos redundantes provider/capability en la lista permiten FK compuestas
que comprueban en base de datos tanto el dueño del servicio como la capability
de la conexión. No se guardan relaciones en JSON.

## Constraints e índices

- FK consumer y provider a network_tenants; eliminación restringida.
- CHECK consumer diferente de provider.
- CHECK status active/inactive y capability no vacía en mayúsculas.
- Capability sensible a mayúsculas mediante ascii_bin en MySQL/MariaDB.
- CHECK valid_from <= valid_to cuando ambos existen.
- UNIQUE consumer/provider/capability, incluso en estado inactivo.
- UNIQUE id/provider/capability para la FK compuesta de autorización.
- Índices consumer/capability/status y provider.
- UNIQUE id/tenant_id añadido a local_shipping_services, sin alterar registros.
- UNIQUE connection/service para impedir permisos duplicados.
- FK connection/provider/capability; eliminación restringida mientras existan
  permisos. Se retira CASCADE para compatibilidad con CHECK en MySQL moderno.
- FK service/provider al catálogo local: impide autorizar otro dueño y cambiar
  el dueño de un servicio autorizado; eliminación del servicio restringida.
- CHECK capability LOCAL_SHIPPING en la lista de servicios.
- Índice service/provider para su FK.

La migración usa CREATE TABLE con CHECK explícitos porque SQLite no permite
añadirlos mediante ALTER TABLE. Soporta SQLite y MySQL/MariaDB con InnoDB.
Los CHECK se generan en SQLite, MySQL >= 8.0.16 y MariaDB >= 10.2.1;
se omiten en versiones anteriores, sin retirar ninguna FK o UNIQUE. La versión
de MariaDB se detecta aunque utilice el driver mysql de Laravel 9 o el prefijo
de compatibilidad 5.5.5-. Una versión no identificable aborta antes de crear tablas.
El rollback elimina las dos tablas nuevas y únicamente el índice añadido al
catálogo existente. No elimina servicios, cobertura ni tenants.

## Servicio y seguridad

`ProviderConnectionService` ofrece:

- authorizedConnections(consumer, capability): relaciones activas vigentes.
- canConsume(consumer, provider, capability): autorización explícita.
- currentConnections(capability): consumer exclusivamente desde TenantContext.
- authorizedLocalServices(consumer, provider): catálogo autorizado, publicado,
  activo, vigente y perteneciente explícitamente al provider.

Los métodos con modelos Tenant son API interna para callers de dominio que
resuelven las entidades server-side. No deben construirse desde tenant_id
proveniente del navegador. No hay endpoint de configuración o consumo en esta
fase. ProviderConnection bloquea mass assignment; configurarlo requiere código
server-side explícito (forceFill/forceCreate), no payloads HTTP.

La vigencia es [valid_from, valid_to), con extremos nulos sin límite. Consumer
y provider deben existir y tener estado active. El servicio consulta su estado
persistido, no depende de atributos de status potencialmente obsoletos en memoria.
Los límites iguales se pueden guardar, pero nunca autorizan consumo.

ProviderConnection valida en cada saving (creación y actualización) IDs positivos
y distintos, capability canónica, estado e intervalo de fechas. FK valida la
existencia de los IDs; UNIQUE rechaza duplicados incluso concurrentes. Estas
reglas del modelo también se aplican mediante forceCreate/forceFill; SQL directo,
updates masivos y eventos desactivados pueden eludirlas. El servicio deniega
relaciones self-provider, fuera de vigencia o con estado/capability inválidos;
comprueba también el intervalo y la longitud exacta de los identificadores para
evitar las comparaciones PAD SPACE de ascii_bin en MySQL/MariaDB.

La autorización de relación no sustituye subscriptions/entitlements del producto;
las futuras integraciones deben aplicar ambos controles. No añade memberships ni
acceso administrativo. No modifica TenantContext, cobertura, tarifas, operaciones,
drivers, tracking o POD. El catálogo retornado es interno; una futura respuesta
pública debe seleccionar los campos permitidos y evitar exponer costos operativos.

## Alcance y pendientes

ENFA-01 termina en persistencia y consultas de autorización. No implementa
cotización, envíos, operaciones, guía, integración al cotizador ni cancelación.
Agregar providers adicionales sólo necesita registros y permisos nuevos.
Configurar relaciones reales, interfaz administrativa, auditoría y consumo del
provider se decidirán en fases posteriores.

Las migraciones se verificaron en SQLite en memoria y en una instancia temporal
local de MariaDB 10.4.32 con datadir propio y bind 127.0.0.1:43317. Se comprobó
@@datadir antes de crear una base desechable; nunca se utilizó el servidor habitual
ni STAGE/PRD. El proceso temporal se detuvo al terminar. Falta ejecutar en MySQL
real y en las versiones objetivo de cada ambiente, sin acceder a PRD.

## Pruebas

NetworkProviderConnectionTest ejecuta la migración real sobre un esquema SQLite
en memoria y activa foreign_keys. Cubre relación válida, self-provider rechazado,
inactividad, vigencia futura/vencida, capability incorrecta, duplicados, servicios
autorizados/publicados/vigentes, ausencia de relación/contexto, TenantContext
inmutable, aislamiento de consumers, múltiples providers, FK, reasignación de
dueño, tenants suspendidos y rollback/reaplicación.

Verificación local (PHP 8.2.12, PHPUnit 11.5.56):

- `php vendor/phpunit/phpunit/phpunit tests/Feature/NetworkProviderConnectionTest.php`:
  OK, 27 pruebas y 124 assertions tras la validación de compatibilidad.
- `php vendor/phpunit/phpunit/phpunit --filter 'Network(Foundation|SubscriptionFoundation|LocalShipping|TenantAdmin|Auth)Test'`:
  OK, 54 pruebas y 274 assertions. Repetido con
  `--display-phpunit-deprecations` para identificar el único aviso existente:
  metadata en doc-comment de TenantLogisticsEngineTest::test_distance_tiers_and_overage.
- `php -l`: los cuatro archivos PHP nuevos sin errores de sintaxis.

Se instalaron las dependencias de composer.lock con `--no-scripts` para ejecutar
pruebas; composer.json y composer.lock permanecen intactos. La clave APP_KEY de
prueba y SQLite :memory: se definieron sólo en el proceso; no se creó ni editó .env.

## Revisión SQL final

| Construcción | Compatibilidad y decisión |
| --- | --- |
| CHECK | Dependiente de versión; opcional según VERSION(). Complementado por modelo y consultas. |
| FK compuestas | InnoDB; columnas BIGINT UNSIGNED compatibles con los IDs existentes; capability con mismo tipo/collation en padre e hijo. Índices completos, sin prefijos. |
| UNIQUE/índices compuestos | Disponibles en MySQL 5.7/MariaDB; capability ASCII 64 evita el índice largo de utf8mb4 en motores antiguos. Mayor índice nuevo: consumer/capability/status, 152 bytes con utf8mb4 anterior; ahora 92 bytes con ambos strings ASCII. |
| Enum/string | No se usa ENUM SQL. VARCHAR ASCII binario para status/capability; reglas en el modelo. No requiere cambio de ENUM por nueva capability. |
| Timestamps | TIMESTAMP NULL DEFAULT NULL explícito evita inicialización automática heredada. Sin precisión fraccional ni defaults de expresiones. Persisten límites de rango de TIMESTAMP (2038 en MySQL 5.7) y conversión según timezone de sesión. |
| Rollback | Drop permisos, drop conexiones, drop índice del catálogo. Verificado con permisos existentes; conserva tenants y servicios. No requiere ALTER DROP CHECK. |
| Laravel 9 | Migration anónima, DB::statement/selectOne, Blueprint::index/unique/dropUnique y Schema::table/dropIfExists existentes en Laravel 9. No depende del driver mariadb moderno. Runtime de estas pruebas: Laravel 12 del composer.lock actual; no se ejecutó un framework Laravel 9 separado. |

La prueba de generación SQL cubre versiones simuladas MySQL 5.7.44, 8.0.15,
8.0.16, MariaDB 10.1.48 y 10.4.32. No demuestra ejecución en esos servidores.
La prueba real en MariaDB 10.4.32 pasó 25 comprobaciones: creación,
autorización/catálogo, FK, UNIQUE, CHECK, eliminación restringida, consultas con
checks desactivados (incluidos case/trailing spaces) y up/down/up/down.
El script de esa comprobación quedó en el directorio temporal del sistema:
`enfa01-sql-validation.php`; no forma parte de la aplicación.

Riesgos restantes: en servidores sin CHECK, SQL directo puede persistir filas de
negocio inválidas, pero no deben autorizar consumo mediante este servicio.
Los padres deben usar InnoDB y los tipos previstos por las migraciones existentes;
FK desactivadas manualmente invalidan la garantía de integridad. DDL en MySQL
implica commits; un fallo intermedio puede requerir recuperación manual antes de
reintentar. No se conocen ni se consultaron versiones de STAGE/PRD.

Referencias oficiales:

- [MySQL: CHECK y restricciones con acciones de FK](https://dev.mysql.com/doc/refman/8.0/en/create-table-check-constraints.html).
- [MariaDB: constraints](https://mariadb.com/docs/server/reference/sql-statements/data-definition/constraint).
- [Laravel 9: migraciones](https://laravel.com/docs/9.x/migrations).
