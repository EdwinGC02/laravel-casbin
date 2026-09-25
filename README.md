# Laravel Casbin (RBAC multi-tenant con dominios)

Paquete Composer para Laravel que integra [Casbin](https://casbin.org/) con un modelo **RBAC with domains/tenants**: los permisos de un rol pertenecen a un tenant, así que el mismo rol puede existir en varias empresas con permisos distintos en cada una.

## Características

- Modelo Casbin base `RBAC with domains/tenants`, con el dominio como parte de la política.
- **Lectura** de permisos: contrato `PermissionServiceInterface`, middleware de ruta y helper global.
- **Escritura** de políticas: contrato `TenantRolePolicyWriterInterface`, que exige el tenant en cada operación.
- Resolución del tenant sustituible por la app (`TenantContextInterface`).
- Caché de chequeos con TTL configurable y llave extensible.
- Persistencia sobre el `DatabaseManager` de Laravel (`LaravelDatabaseAdapter`): crear enforcers no abre conexiones PDO adicionales.
- Publicación de estructura `casbin/`, configuración, modelo y migraciones.
- Interruptor `CASBIN_ENABLED=false` para entornos sin políticas.



## Requisitos

- PHP 8.2+
- Laravel 12 (`illuminate/support ^12.0`)
- Una conexión de base de datos para Casbin declarada en `config/database.php`. Por convención del ecosistema es `landlord`.



## Instalación

El paquete no está en Packagist. En el `composer.json` de la app, declara el repositorio y pide el paquete:

```json
{
  "repositories": [
    { "type": "vcs", "url": "git@github.com:EdwinGC02/laravel-casbin.git" }
  ]
}
```

```bash
composer require sodeker/laravel-casbin
```

El acceso al repositorio es por SSH: la máquina (o el contenedor) que ejecuta Composer necesita una clave cargada en el agente. Si falla con `Permission denied (publickey)`, comprueba `ssh-add -l`.

## Publicación de archivos

```bash
php artisan vendor:publish --tag=casbin            # estructura completa casbin/
php artisan vendor:publish --tag=casbin-structure  # solo factory, middleware, modelo y README
php artisan vendor:publish --tag=casbin-config     # config/casbin.php
php artisan vendor:publish --tag=casbin-migrations # casbin/migrations
```

Las migraciones publicadas son una **plantilla de referencia**, no el esquema definitivo de la app: muévelas a donde tu flujo de despliegue las espere y ajústalas a tus tablas. El paquete no las ejecuta.

### Estructura publicada

```text
app-tuya/
├─ config/
│  └─ casbin.php
└─ casbin/
   ├─ Authorization/CasbinEnforcerFactory.php
   ├─ Middleware/CasbinPermissionMiddleware.php
   ├─ Services/
   │  ├─ CasbinSyncService.php
   │  ├─ ModulesWithPermissionsService.php
   │  └─ UiPermissionService.php
   ├─ Seeders/*.php
   ├─ migrations/*.php
   ├─ README.md
   └─ model.conf
```



## Configuración (`config/casbin.php`)


| Clave           | Env                    | Por defecto                      | Para qué                                                                               |
| --------------- | ---------------------- | -------------------------------- | -------------------------------------------------------------------------------------- |
| `enabled`       | `CASBIN_ENABLED`       | `true`                           | Con `false` el middleware deja pasar y `can()` devuelve `true`. No usar en producción. |
| `connection`    | `CASBIN_CONNECTION`    | `landlord`                       | Conexión donde vive `casbin_rule`.                                                     |
| `model`         | —                      | `base_path('casbin/model.conf')` | Ruta del modelo.                                                                       |
| `tenant_prefix` | `CASBIN_TENANT_PREFIX` | `tenant:`                        | Prefijo del dominio. Cambiarlo obliga a migrar los datos de `casbin_rule`.             |
| `cache_ttl`     | `CASBIN_CACHE_TTL`     | `300`                            | Segundos que vive en caché cada chequeo. Con `0` no se cachea.                         |


```env
CASBIN_ENABLED=true
CASBIN_CONNECTION=landlord
CASBIN_TENANT_PREFIX=tenant:
CASBIN_CACHE_TTL=300
```



## El modelo: por qué el tenant va en la política

```
r = sub, dom, obj, act
p = sub, dom, obj, act
g = _, _, _
m = g(r.sub, p.sub, r.dom) && r.dom == p.dom && keyMatch(r.obj, p.obj) && keyMatch(r.act, p.act)
```

- Sujeto: `user:{id}`
- Dominio: `tenant:{tenantId}`
- Objeto: el módulo (`users`, `roles`, `products`…)
- Acción: `view`, `create`, `edit`, `delete`…

Ejemplos en `casbin_rule`:

```
p, Contador, tenant:10, satConcepts, view      # el rol Contador ve conceptos en el tenant 10
g, user:15, Contador, tenant:10                # el usuario 15 es Contador en el tenant 10
```

El `dom` está en `p`, no solo en `g`. Eso es lo que permite que `Contador` tenga permisos sobre SAT en el tenant 10 y no en el 11, y es la razón por la que **toda escritura debe acotar el dominio**: una política sin dominio no autoriza a nadie, y un borrado sin dominio borra el rol en todas las empresas.

## Proteger rutas

```php
Route::get('/usuarios', [UserController::class, 'index'])
    ->middleware('casbin:users,view');

Route::post('/usuarios', [UserController::class, 'store'])
    ->middleware('casbin:users,create');
```

Alias equivalentes: `casbin` y `permission`.

## Resolver el tenant de la petición

El middleware y el helper preguntan por el tenant activo a `TenantContextInterface`. La implementación por defecto lo lee de la sesión:

```php
// Sodeker\LaravelCasbin\Infrastructure\Tenancy\SessionTenantContext
session('tenant_id');
```

**Reemplázala si tu app resuelve el tenant por petición** —por ejemplo con el tenant en la URL— y el usuario puede tener varias pestañas abiertas en tenants distintos. La sesión de Laravel es una sola por navegador: quien la escribe último decide contra qué tenant se autoriza en todas las pestañas.

```php
// En un ServiceProvider de la app
use Sodeker\LaravelCasbin\Domain\Contracts\TenantContextInterface;

$this->app->bind(TenantContextInterface::class, RequestTenantContext::class);
```

```php
final class RequestTenantContext implements TenantContextInterface
{
    public function currentTenantId(): int|string|null
    {
        // El tenant del prefijo /t/{slug}/ de esta petición, con la sesión como respaldo.
        return $this->fromUrl() ?? session('tenant_id');
    }
}
```



## Consultar permisos

Por el contrato, que es lo recomendado:

```php
use Sodeker\LaravelCasbin\Domain\Contracts\PermissionServiceInterface;

$allowed = app(PermissionServiceInterface::class)->can($userId, $tenantId, 'users', 'edit');
```

Por el enforcer, cuando necesitas la API completa de Casbin:

```php
$allowed = app(\Casbin\Enforcer::class)->enforce("user:$userId", "tenant:$tenantId", 'users', 'view');
```

Con el helper global, que resuelve usuario y tenant por su cuenta:

```php
can('users', 'view');
```

El helper devuelve `false` si no hay usuario autenticado o no hay tenant resuelto (salvo con `CASBIN_ENABLED=false`, donde devuelve `true`).

## Escribir políticas

Usa el writer, no SQL propio. Es el contrato que impide el error de aislamiento: cada operación exige el tenant, y la única global se llama por su nombre.

```php
use Sodeker\LaravelCasbin\Domain\Contracts\TenantRolePolicyWriterInterface;

public function __construct(
    private readonly TenantRolePolicyWriterInterface $policies,
) {}
```


| Método                                                 | Qué hace                                                                                               |
| ------------------------------------------------------ | ------------------------------------------------------------------------------------------------------ |
| `replaceRolePolicies($roleCode, $tenantId, $policies)` | Deja las políticas del rol **en ese tenant** exactamente como se indican. No toca ningún otro dominio. |
| `removeRolePolicies($roleCode, $tenantId)`             | Quita las del rol en ese tenant; conserva las de los demás.                                            |
| `policiesForRole($roleCode, $tenantId)`                | Devuelve pares `['object' => …, 'action' => …]`.                                                       |
| `forgetRole($roleCode)`                                | El rol dejó de existir: borra sus `p` y sus `g` en **todos** los tenants.                              |
| `grantRoleToUser($userId, $roleCode, $tenantId)`       | Asigna el rol al usuario en ese tenant (`g`).                                                          |
| `revokeUserRoles($userId, $tenantId)`                  | Le quita todos sus roles en ese tenant.                                                                |


```php
// Guardar lo que envió el formulario de roles del tenant activo.
$this->policies->replaceRolePolicies($roleCode, $tenantId, [
    ['object' => 'satConcepts', 'action' => 'view'],
    ['object' => 'satConcepts', 'action' => 'edit'],
]);
```

Detalles que conviene conocer:

- Sin tenant lanza `MissingTenantDomainException`. Es a propósito: escribir una política sin dominio es siempre un error de programación.
- **Conserva las políticas comodín** (`object = '*'`) del rol en ese dominio. Son de siembra —el acceso total de los roles privilegiados— y no se gestionan desde un formulario.
- Escribe por query builder, no por el enforcer: un rol con cientos de permisos serían cientos de consultas. Si el enforcer ya estaba resuelto en la petición, lo recarga para que un `can()` posterior no responda con la política anterior.
- **No invalida el caché de chequeos.** Eso es de la app, que es la que sabe con qué sello construye sus llaves.



## Caché de los chequeos

Cada `can()` se cachea `casbin.cache_ttl` segundos **entre peticiones**. Amortigua las ráfagas: armar un menú hace decenas de chequeos en una sola petición.

La contrapartida es que un cambio de permisos tarda hasta el TTL en notarse. Para que se vea de inmediato, añade a la llave un sello de versión que cambie con cada modificación:

```php
use Sodeker\LaravelCasbin\Application\Services\PermissionService;

final class VersionAwarePermissionService extends PermissionService
{
    protected function cacheKey(int|string $userId, int|string $tenantId, string $resource, string $action): string
    {
        $version = $this->versionForTenant($tenantId);

        return "perm:v$version:$userId:$tenantId:$resource:$action";
    }
}
```

Cada cambio de versión deja huérfanas las entradas anteriores, que expiran solas por TTL, y el permiso nuevo se ve en el siguiente chequeo. Registra la subclase sobre `PermissionServiceInterface` en un provider de la app.

## Puesta en marcha en una app nueva

1. Instalar y publicar configuración, modelo y migraciones.
2. Declarar la conexión de Casbin en `.env`.
3. Crear `casbin_rule` y las tablas de catálogo (módulos, acciones, permisos por rol **y tenant**).
4. Reemplazar `TenantContextInterface` si el tenant no vive en la sesión.
5. Escribir políticas y asignaciones con `TenantRolePolicyWriterInterface`.
6. Proteger rutas con `casbin:<modulo>,<accion>`.
7. Exponer los permisos a la interfaz con `PermissionServiceInterface::can()`, recordando que la validación real es la del backend.

Guía paso a paso para proteger un módulo: `[IMPLEMENTATION.md](IMPLEMENTATION.md)`. Qué cambió en cada versión y qué exige actualizar: `[CHANGELOG.md](CHANGELOG.md)`.

## Notas de operación

- `casbin_rule` se crea en la conexión de `CASBIN_CONNECTION`.
- Usa siempre el mismo formato de dominio en políticas y asignaciones. Si cambias `CASBIN_TENANT_PREFIX`, migra los datos existentes.
- En procesos de vida larga (colas, Octane) el enforcer singleton mantiene la política en memoria. Los cambios hechos por fuera del writer no se reflejan hasta reiniciar el worker o llamar `loadPolicy()`.
- Las plantillas de `casbin/` solo se publican si no existen: actualizar el paquete no reescribe las copias que ya viven en la app.

