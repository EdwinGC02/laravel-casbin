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

        $key = "perm:$userId:$tenantId:$resource:$action";
        $tenantPrefix = Config::get('casbin.tenant_prefix', 'tenant:');

        return Cache::remember($key, 300, function () use ($userId, $tenantId, $resource, $action, $tenantPrefix) {
            return $this->enforcer()->enforce(
                "user:$userId",
                $tenantPrefix . $tenantId,
                $resource,
                $action
            );
        });
    }

    protected function enforcer(): Enforcer
    {
        return $this->enforcer ??= app(Enforcer::class);
    }
}