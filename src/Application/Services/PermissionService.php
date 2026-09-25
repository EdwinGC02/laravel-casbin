<?php

namespace Sodeker\LaravelCasbin\Application\Services;

use Casbin\Enforcer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Sodeker\LaravelCasbin\Domain\Contracts\PermissionServiceInterface;

class PermissionService implements PermissionServiceInterface
{
    /**
     * Se resuelve de forma perezosa desde el contenedor (singleton del
     * provider). En v1.0.x el constructor creaba un enforcer propio por
     * cada inyección del servicio — con su propia conexión PDO y carga
     * completa de políticas —, lo que agotaba las conexiones de la base
     * de datos en procesos que instancian muchos controladores (p. ej.
     * `php artisan route:list` con las rutas sin cachear). Instanciar
     * este servicio ya no toca la base de datos.
     *
     * @var Enforcer|null
     */
    protected $enforcer;

    public function can(int|string $userId, int|string $tenantId, string $resource, string $action): bool
    {
        if (!Config::get('casbin.enabled', true)) {
            return true;
        }

        $tenantPrefix = Config::get('casbin.tenant_prefix', 'tenant:');

        $check = function () use ($userId, $tenantId, $resource, $action, $tenantPrefix) {
            return $this->enforcer()->enforce(
                "user:$userId",
                $tenantPrefix . $tenantId,
                $resource,
                $action
            );
        };

        $ttl = $this->cacheTtl();

        if ($ttl <= 0) {
            return $check();
        }

        return Cache::remember(
            $this->cacheKey($userId, $tenantId, $resource, $action),
            $ttl,
            $check
        );
    }

    /**
     * Llave de caché del chequeo.
     *
     * Aislada en un método para que la app pueda añadirle su propio sello de
     * versión sin reescribir `can()`. Hace falta porque el resultado se cachea
     * entre peticiones: sin sello, un cambio de permisos tarda hasta el TTL en
     * notarse. El patrón es extender esta clase y devolver la llave con la
     * versión de permisos del tenant, de modo que cada cambio deje huérfanas
     * las entradas anteriores (expiran solas) y el permiso nuevo se vea en el
     * siguiente chequeo.
     */
    protected function cacheKey(int|string $userId, int|string $tenantId, string $resource, string $action): string
    {
        return "perm:$userId:$tenantId:$resource:$action";
    }

    /**
     * Segundos que vive cada resultado en caché (`casbin.cache_ttl`).
     *
     * Amortigua las ráfagas: armar un menú hace decenas de chequeos en una
     * misma petición. Con 0 el caché queda deshabilitado.
     */
    protected function cacheTtl(): int
    {
        return (int) Config::get('casbin.cache_ttl', 300);
    }

    protected function enforcer(): Enforcer
    {
        return $this->enforcer ??= app(Enforcer::class);
    }
}
