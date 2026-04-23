<?php

namespace Sodeker\LaravelCasbin\Application\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Sodeker\LaravelCasbin\Domain\Contracts\PermissionServiceInterface;
use Sodeker\LaravelCasbin\Infrastructure\Casbin\EnforcerFactory;

class PermissionService implements PermissionServiceInterface
{
    protected $enforcer;

    public function __construct()
    {
        $this->enforcer = EnforcerFactory::make();
    }

    public function can(int|string $userId, int|string $tenantId, string $resource, string $action): bool
    {
        if (!Config::get('casbin.enabled', true)) {
            return true;
        }

        $key = "perm:$userId:$tenantId:$resource:$action";
        $tenantPrefix = Config::get('casbin.tenant_prefix', 'tenant:');

        return Cache::remember($key, 300, function () use ($userId, $tenantId, $resource, $action, $tenantPrefix) {
            return $this->enforcer->enforce(
                "user:$userId",
                $tenantPrefix . $tenantId,
                $resource,
                $action
            );
        });
    }
}