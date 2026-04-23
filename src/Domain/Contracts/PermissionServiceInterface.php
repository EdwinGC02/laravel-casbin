<?php

namespace Sodeker\LaravelCasbin\Domain\Contracts;

interface PermissionServiceInterface
{
    public function can(int|string $userId, int|string $tenantId, string $resource, string $action): bool;
}