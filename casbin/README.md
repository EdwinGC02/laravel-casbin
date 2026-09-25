# Casbin (publicado por paquete)

Estructura base publicada por `sodeker/laravel-casbin` con la convención estándar para apps del ecosistema.

## Modelo activo

- RBAC with domains/tenants: `r = sub, dom, obj, act`.
- Política `p`: `rol, dominio, modulo, accion`.
- Relación `g`: `usuario, rol, dominio`.

El dominio (`tenant:{id}`) es parte de la política, no solo de la relación: el mismo rol puede tener permisos distintos en cada tenant.

## Conexión de base de datos

Todas las operaciones de Casbin usan la conexión de `config('casbin.connection')` (por defecto `landlord`).

## Estructura

- `Authorization/CasbinEnforcerFactory.php` — delega en `EnforcerFactory::make()` del paquete.
- `Middleware/CasbinPermissionMiddleware.php`
- `Services/CasbinSyncService.php` — sincroniza `role_permissions` → `casbin_rule`, **por tenant**. Se resuelve del contenedor (recibe el writer por constructor), no con `new`.
- `Services/UiPermissionService.php`
- `Services/ModulesWithPermissionsService.php`
- `Seeders/*.php`
- `migrations/*.php` — plantilla de referencia; `role_permissions` lleva `tenant_id`.
- `model.conf`

## Escritura de políticas

No escribas `casbin_rule` a mano. Inyecta `Sodeker\LaravelCasbin\Domain\Contracts\TenantRolePolicyWriterInterface`: exige el tenant en cada operación, que es lo que impide que editar un rol en un tenant afecte a los demás.

Estos archivos son **plantillas**: solo se publican si no existen, así que actualizar el paquete no reescribe tu copia.
