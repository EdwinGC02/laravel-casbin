# Changelog

Todos los cambios notables del paquete se documentan en este archivo.
Formato basado en [Keep a Changelog](https://keepachangelog.com/es/) y versionado [SemVer](https://semver.org/lang/es/).

## [v1.2.0] - 2026-09-24

### Corregido

- **Los permisos de un rol se sobrescribían entre tenants.** El modelo aísla por dominio en la lectura (`m = g(r.sub, p.sub, r.dom) && r.dom == p.dom && …`), pero el paquete no ofrecía ninguna forma de escribir políticas: cada app armaba su propio SQL contra `casbin_rule`, y ahí se perdía el aislamiento. Un `delete` al que se le olvida filtrar por `v1` borra las políticas del rol en TODOS los dominios; un `insert` en bucle sobre los tenants activos las replica en todos. El resultado observable era que editar un rol compartido desde un tenant reescribía en silencio los permisos que ese mismo rol tenía configurados en otro, y un usuario perdía accesos sobre apps que solo su tenant tiene contratadas.
- **La plantilla publicable `casbin/Services/CasbinSyncService.php` codificaba ese error.** Reconstruía un dominio completo a partir de `role_permissions`, que no tenía tenant, de modo que cualquier sincronización propagaba a un tenant lo último que se guardó en otro.

### Añadido

- `Sodeker\LaravelCasbin\Domain\Contracts\TenantRolePolicyWriterInterface` y su implementación `Application\Services\TenantRolePolicyWriter`: escritura de políticas **acotada al dominio por contrato**. `replaceRolePolicies()`, `removeRolePolicies()`, `policiesForRole()`, `grantRoleToUser()` y `revokeUserRoles()` exigen el tenant; sin él lanzan `MissingTenantDomainException`. La única operación global se llama `forgetRole()` y se lee como lo que es: el rol dejó de existir. Se registra como singleton en el provider.
- `Domain\Contracts\TenantContextInterface` con la implementación por defecto `Infrastructure\Tenancy\SessionTenantContext`: el middleware `casbin`/`permission` y el helper `can()` ya no leen `session('tenant_id')` a la fuerza, se lo preguntan a este contrato. Las apps donde el tenant se resuelve por petición —en la URL, con varias pestañas abiertas en tenants distintos— deben reemplazar el binding: la sesión de Laravel es una sola por navegador y autorizaba contra el tenant de la última pestaña que la escribió.
- `casbin.cache_ttl` (`CASBIN_CACHE_TTL`, 300 s por defecto): el TTL del caché de chequeos dejó de estar fijo en el código. Con `0` no se cachea.
- `PermissionService::cacheKey()` como método protegido: permite añadir un sello de versión de permisos a la llave sin reescribir `can()`, que es lo que hace falta para que un cambio de permisos se note antes de que expire el TTL.

### Cambiado

- La plantilla publicable de la migración `role_permissions` pasa a llevar `tenant_id`, con única `(tenant_id, role_id, permission_id)` e índice `(tenant_id, role_id)`. La llave natural es el TRÍO, no el par: `roles` es una tabla global —el mismo rol puede estar asociado a varios tenants— y cada tenant contrata apps distintas.
- `casbin/Services/CasbinSyncService.php` reescrito: recibe el writer por constructor, exige tenant en todas sus operaciones y filtra `role_permissions` por `tenant_id`. **Ya no se instancia con `new`**: se resuelve del contenedor (`app(CasbinSyncService::class)`).
- `casbin/Seeders/RolePermissionSeeder.php` y `casbin/Seeders/UserRoleCasbinSeeder.php` alineados con lo anterior.

### Notas de compatibilidad

- **`src/` es aditivo**: `PermissionServiceInterface::can()`, el helper `can()`, el middleware `casbin`/`permission`, `EnforcerFactory::make()` y `LaravelDatabaseAdapter` mantienen firma y comportamiento observable. El tenant se sigue resolviendo desde la sesión mientras la app no reemplace `TenantContextInterface`.
- **Las plantillas publicadas no se actualizan solas.** Los cambios en `casbin/` solo afectan a publicaciones nuevas; las copias que ya viven en una app hay que actualizarlas a mano si se usan.
- **La app consumidora tiene trabajo propio.** Que el paquete ofrezca una escritura segura no arregla el dato existente: hay que añadir `tenant_id` a `role_permissions` con su migración, repartir las filas actuales por tenant y limpiar las políticas que el modelo anterior replicó en dominios ajenos. En Suite eso son una migración de landlord y el comando `role:resync-policies`.
- Al escribir, el writer **conserva las políticas comodín** (`v2 = '*'`) del rol en ese dominio: son de siembra, no se gestionan desde un formulario de roles, y borrarlas dejaría a los roles privilegiados sin acceso.

## [v1.1.0] - 2026-07-07

### Corregido

- **Agotamiento de conexiones de base de datos** (`FATAL: sorry, too many clients already`). En v1.0.x, `PermissionService::__construct()` creaba un `Enforcer` propio por cada inyección de `PermissionServiceInterface`, usando `CasbinAdapter\Database\Adapter` (leeqvip/database), que abre una **conexión PDO cruda por instancia, fuera del pool de Laravel**, y carga la política completa. En apps con decenas de controladores que inyectan el contrato, procesos que instancian controladores en masa (p. ej. `php artisan route:list` con las rutas sin cachear, que instancia un controlador por ruta para listar sus middlewares) acumulaban 90+ conexiones idle y agotaban `max_connections` de Postgres.

### Cambiado

- Nuevo `Sodeker\LaravelCasbin\Infrastructure\Casbin\LaravelDatabaseAdapter`: adaptador de persistencia Casbin (`Adapter` + `BatchAdapter` + `UpdatableAdapter`) sobre el query builder de Laravel. Reutiliza la conexión de `config('casbin.connection')` gestionada por el DatabaseManager — crear enforcers **no abre conexiones adicionales**. Replica la semántica del adaptador anterior (mismas columnas de `casbin_rule`, misma normalización de reglas).
- `EnforcerFactory::make()` construye el enforcer con `LaravelDatabaseAdapter`. Conserva su semántica: cada llamada devuelve un enforcer fresco con la política recién cargada.
- `PermissionService` ya no construye el enforcer en el constructor: lo resuelve de forma perezosa desde el contenedor (`app(\Casbin\Enforcer::class)`, el singleton que registra el provider) en el primer chequeo. Instanciar el servicio no toca la base de datos.
- `PermissionServiceInterface` pasa de `bind` a `singleton` en el provider (el servicio es sin estado; los resultados ya se cachean 300 s).
- La plantilla publicable `casbin/Authorization/CasbinEnforcerFactory.php` ahora delega en `EnforcerFactory::make()` del paquete (solo afecta publicaciones nuevas; las copias ya publicadas en apps no cambian).

### Notas de compatibilidad

- **API pública sin cambios**: `PermissionServiceInterface::can()`, el helper `can()`, el middleware `casbin`/`permission` y `EnforcerFactory::make()` mantienen firma y comportamiento observable.
- La dependencia `casbin/database-adapter` se conserva en `composer.json` por compatibilidad con copias de `casbin/` ya publicadas en apps consumidoras que referencien `CasbinAdapter\Database\Adapter`.
- En procesos de vida larga (colas, Octane) el enforcer singleton mantiene la política en memoria; los cambios de política hechos por fuera del enforcer (SQL directo a `casbin_rule`) no se reflejan hasta reiniciar el worker o llamar `loadPolicy()`. En PHP-FPM el singleton vive solo durante la petición, como antes. El caché de resultados de 300 s en `PermissionService` no cambia.

## [v1.0.1] - anterior

- Ajuste de versión requerida de Laravel (`illuminate/support ^12.0`).

## [v1.0.0] - inicial

- RBAC multi-tenant con dominios sobre Casbin, middleware `casbin`/`permission`, helper `can()`, publicación de estructura `casbin/` y configuración.
