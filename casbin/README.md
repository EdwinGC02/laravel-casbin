# Casbin (publicado por paquete)

Estructura base publicada por `edwingc02/laravel-casbin` con convención estandar para apps.

## Modelo activo

- RBAC with domains/tenants: `r = sub, dom, obj, act`.
- Política `p`: `rol, dominio, modulo, accion`.
- Relación `g`: `usuario, rol, dominio`.

## Conexión de base de datos

Todas las operaciones de Casbin deben usar la conexión configurada en:

- `config('casbin.connection')` (default: `landlord`).

## Estructura

- `Authorization/CasbinEnforcerFactory.php`
- `Middleware/CasbinPermissionMiddleware.php`
- `Services/CasbinSyncService.php`
- `Services/UiPermissionService.php`
- `Services/ModulesWithPermissionsService.php`
- `Seeders/*.php`
- `migrations/*.php`
- `model.conf`
