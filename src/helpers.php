<?php

use Illuminate\Support\Facades\Config;
use Sodeker\LaravelCasbin\Domain\Contracts\PermissionServiceInterface;
use Sodeker\LaravelCasbin\Domain\Contracts\TenantContextInterface;

if (!function_exists('can')) {
    function can(string $resource, string $action): bool
    {
        if (!Config::get('casbin.enabled', true)) {
            return true;
        }

        $user = auth()->user();
        // El tenant lo resuelve la app, no la sesión (ver TenantContextInterface).
        $tenantId = app(TenantContextInterface::class)->currentTenantId();

        if (!$user || !$tenantId) {
            return false;
        }

        return app(PermissionServiceInterface::class)
            ->can($user->id, $tenantId, $resource, $action);
    }
}
