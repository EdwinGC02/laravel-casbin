# Changelog

Todos los cambios notables del paquete se documentan en este archivo.
Formato basado en [Keep a Changelog](https://keepachangelog.com/es/) y versionado [SemVer](https://semver.org/lang/es/).

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
