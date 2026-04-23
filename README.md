# Laravel Casbin (RBAC Multi-tenant con dominios)

Paquete Composer para Laravel que integra [Casbin](https://casbin.org/) usando un modelo **RBAC with domains/tenants**.  
Está pensado para centralizar permisos por rol en entornos multi-tenant, donde el mismo usuario puede tener permisos distintos según el tenant (dominio).

## Características

- Modelo Casbin base: `RBAC with domains/tenants`.
- Publicación automática de:
  - Estructura completa `casbin/` estilo eConnect
  - Configuración `config/casbin.php`
  - Modelo `casbin/model.conf` (domain/multi-tenant)
  - Migraciones en `casbin/migrations`
- Middleware listo para rutas: `casbin` (y alias `permission`).
- Soporte para desactivar validación con `CASBIN_ENABLED=false`.
- Conexión de base de datos configurable, por defecto y recomendada: `landlord`.
- Sin SQL crudo en el paquete (uso del adapter oficial de Casbin para BD).

## Requisitos

- PHP 8.1+
- Laravel 10+
- Una conexión `landlord` configurada en `config/database.php` de la app que instala el paquete.

## Instalación

### 1) Repositorio privado (perfil personal)

Mientras el paquete esté privado en tu usuario personal, en la app consumidora agrega en `composer.json`:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "git@github.com:EdwinGC02/laravel-casbin.git"
    }
  ]
}
```

Luego instala:

```bash
composer require EdwinGC02/laravel-casbin
```

### 2) Cuando lo muevas a la organización Sodeker

Si el repositorio mantiene el mismo nombre y solo cambia el owner, actualiza el `url` del repositorio VCS y el comando a:

```bash
composer require sodeker/laravel-casbin
```

> Recomendación: al moverlo a la organización, publica una nueva versión (tag) para mantener trazabilidad.

## Publicación de archivos del paquete

Después de instalar, publica todo con:

```bash
php artisan vendor:publish --tag=casbin
php artisan vendor:publish --tag=casbin-structure
php artisan vendor:publish --tag=casbin-config
php artisan vendor:publish --tag=casbin-migrations
```

Luego copia o ejecuta esas migraciones según tu flujo de despliegue (si usas migraciones desde `database/migrations`, puedes moverlas o referenciarlas desde tus comandos internos).

## Estructura publicada en la app

```text
app-tuya/
├─ config/
│  └─ casbin.php
└─ casbin/
   ├─ Authorization/
   │  └─ CasbinEnforcerFactory.php
   ├─ Middleware/
   │  └─ CasbinPermissionMiddleware.php
   ├─ Services/
   │  ├─ CasbinSyncService.php
   │  ├─ ModulesWithPermissionsService.php
   │  └─ UiPermissionService.php
   ├─ Seeders/
   │  ├─ CasbinPermissionsSeeder.php
   │  ├─ ModulesTableSeeder.php
   │  ├─ PermissionsTableSeeder.php
   │  ├─ RolePermissionSeeder.php
   │  └─ UserRoleCasbinSeeder.php
   ├─ migrations/
   │  ├─ 2026_02_20_152122_create_modules_permissions_table.php
   │  ├─ 2026_02_20_152632_create_permissions_table.php
   │  ├─ 2026_02_20_152853_create_role_permissions_table.php
   │  └─ 2026_02_20_153001_create_casbin_rule_table.php
   ├─ README.md
   └─ model.conf
```

Para migrar de inmediato:

```bash
php artisan migrate
```

## Configuración (`config/casbin.php`)

El paquete publica esta configuración base:

- `enabled`: controla si Casbin valida permisos.
- `connection`: conexión de BD para Casbin (por defecto `landlord`).
- `model`: ruta del archivo de modelo.
- `tenant_prefix`: prefijo para el dominio tenant (por defecto `tenant:`).

Ejemplo típico:

```php
return [
    'enabled' => filter_var(env('CASBIN_ENABLED', 'true'), FILTER_VALIDATE_BOOLEAN),
    'connection' => env('CASBIN_CONNECTION', 'landlord'),
    'model' => base_path('casbin/model.conf'),
    'tenant_prefix' => env('CASBIN_TENANT_PREFIX', 'tenant:'),
];
```

## Variables de entorno

En `.env` de cada app:

```env
CASBIN_ENABLED=true
CASBIN_CONNECTION=landlord
CASBIN_TENANT_PREFIX=tenant:
```

### Desactivar Casbin temporalmente

Para desarrollo/pruebas sin tablas o sin políticas:

```env
CASBIN_ENABLED=false
```

Con esto:
- El middleware `casbin` permite el paso.
- El helper `can()` devuelve `true`.
- El servicio de permisos no bloquea acceso.

## Modelo RBAC con dominios (multi-tenant)

Este paquete usa:

- **request**: `(sub, dom, obj, act)`
- **policy (`p`)**: `(rol, dominio, recurso, acción)`
- **grouping (`g`)**: `(usuario, rol, dominio)`

Formato recomendado:

- Sujeto: `user:{id}`
- Dominio (tenant): `tenant:{tenantId}`
- Recurso: `users`, `roles`, `projects`, etc.
- Acción: `view`, `create`, `edit`, `delete`, etc.

## Estructura de datos en `casbin_rule`

Ejemplos:

- Política: `p, Admin, tenant:10, users, view`
- Asignación de rol en dominio: `g, user:15, Admin, tenant:10`

## Uso en rutas

Registra middleware por módulo y acción:

```php
Route::get('/usuarios', [UserController::class, 'index'])
    ->middleware('casbin:users,view');

Route::post('/usuarios', [UserController::class, 'store'])
    ->middleware('casbin:users,create');
```

Alias disponibles:

- `casbin`
- `permission`

## Uso en código (servicio)

```php
use Sodeker\LaravelCasbin\Domain\Contracts\PermissionServiceInterface;

$allowed = app(PermissionServiceInterface::class)->can(
    auth()->id(),
    session('tenant_id'),
    'users',
    'edit'
);
```

## Uso en código (Enforcer)

```php
use Casbin\Enforcer;

$enforcer = app(Enforcer::class);

$allowed = $enforcer->enforce(
    'user:' . auth()->id(),
    'tenant:' . session('tenant_id'),
    'users',
    'view'
);
```

## Helper global

Incluye helper:

```php
can('users', 'view');
```

Este helper toma:
- `auth()->user()`
- `session('tenant_id')`

Si cualquiera no existe, retorna `false` (excepto cuando `CASBIN_ENABLED=false`, donde retorna `true`).

## Flujo recomendado por app que instala el paquete

1. Instalar con Composer.
2. Publicar `config`, `model` y `migrations`.
3. Configurar `.env` con conexión `landlord`.
4. Migrar tabla `casbin_rule`.
5. Insertar políticas (`p`) y relaciones (`g`) en `casbin_rule` según tu lógica de negocio.
6. Proteger rutas con middleware `casbin`.
7. Usar `can()` o el servicio de permisos para UI y lógica de aplicación.

## Versionado del paquete

Como paquete versionado:

- Usa tags semánticos (`v1.0.0`, `v1.1.0`, etc.).
- Documenta cambios en un changelog.
- Evita cambios rompientes sin incrementar versión mayor.

## Notas de operación

- La tabla `casbin_rule` se crea en la conexión definida por `CASBIN_CONNECTION` (por defecto `landlord`).
- Para mantener consistencia multi-tenant, usa siempre el mismo formato de dominio `tenant:{id}` en políticas y asignaciones.
- Si cambias `CASBIN_TENANT_PREFIX`, asegúrate de que tus datos en `casbin_rule` usen ese prefijo.
