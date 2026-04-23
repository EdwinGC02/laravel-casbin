<?php

use Illuminate\Support\Facades\Config;
use Sodeker\LaravelCasbin\Domain\Contracts\PermissionServiceInterface;

if (!function_exists('can')) {
    function can(string $resource, string $action): bool
    {
        if (!Config::get('casbin.enabled', true)) {
            return true;
        }

        $user = auth()->user();
        $tenantId = session('tenant_id');

        if (!$user || !$tenantId) {
            return false;
        }

        return app(PermissionServiceInterface::class)
            ->can($user->id, $tenantId, $resource, $action);
    }
}