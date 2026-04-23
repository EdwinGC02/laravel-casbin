<?php

namespace App\Casbin\Services;

use App\Casbin\Authorization\CasbinEnforcerFactory;
use Illuminate\Support\Facades\DB;

class CasbinSyncService
{
    /**
     * Sincroniza políticas (p) para un tenant.
     */
    public function sync(): void
    {
        $tenantId = (int) session('tenant_id', 1);
        $this->syncPoliciesByDomain($tenantId);
    }

    public function syncPoliciesByDomain(int|string $tenantId): void
    {
        $domain = config('casbin.tenant_prefix', 'tenant:') . $tenantId;
        $conn = DB::connection(config('casbin.connection', 'landlord'));
        $conn->table('casbin_rule')
            ->where('ptype', 'p')
            ->where('v1', $domain)
            ->delete();

        $enforcer = CasbinEnforcerFactory::make();
        $permissions = $conn->table('role_permissions as rp')
            ->join('roles as r', 'r.id', '=', 'rp.role_id')
            ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
            ->join('modules_permissions as mp', 'mp.id', '=', 'p.module_id')
            ->where('rp.status', 1)
            ->select([
                'r.code as role_code',
                'mp.code as module_code',
                'p.action as action',
            ])
            ->get();

        foreach ($permissions as $perm) {
            $enforcer->addPermissionForUser(
                $perm->role_code,
                $domain,
                $perm->module_code,
                $perm->action
            );
        }
    }

    /**
     * Sincroniza políticas (p) de un rol para un tenant.
     */
    public function syncPoliciesForRole(int $roleId, int|string $tenantId): void
    {
        $domain = config('casbin.tenant_prefix', 'tenant:') . $tenantId;
        $conn = DB::connection(config('casbin.connection', 'landlord'));
        $role = $conn->table('roles')->where('id', $roleId)->first();
        if (! $role) {
            return;
        }

        $enforcer = CasbinEnforcerFactory::make();
        $existing = $conn->table('casbin_rule')
            ->where('ptype', 'p')
            ->where('v0', $role->code)
            ->where('v1', $domain)
            ->get(['v2', 'v3']);

        foreach ($existing as $row) {
            $enforcer->deletePermissionForUser($role->code, $domain, $row->v2, $row->v3);
        }

        $permissions = $conn->table('role_permissions as rp')
            ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
            ->join('modules_permissions as mp', 'mp.id', '=', 'p.module_id')
            ->where('rp.role_id', $roleId)
            ->where('rp.status', 1)
            ->select('mp.code as module_code', 'p.action as action')
            ->get();

        foreach ($permissions as $perm) {
            $enforcer->addPermissionForUser($role->code, $domain, $perm->module_code, $perm->action);
        }
    }

    public function removePoliciesForRole(string $roleCode, int|string $tenantId): void
    {
        $domain = config('casbin.tenant_prefix', 'tenant:') . $tenantId;
        $enforcer = CasbinEnforcerFactory::make();
        $conn = DB::connection(config('casbin.connection', 'landlord'));
        $rows = $conn->table('casbin_rule')
            ->where('ptype', 'p')
            ->where('v0', $roleCode)
            ->where('v1', $domain)
            ->get(['v2', 'v3']);

        foreach ($rows as $row) {
            $enforcer->deletePermissionForUser($roleCode, $domain, $row->v2, $row->v3);
        }
    }

    public function syncUserRole(string $userUuid, ?int $roleId): void
    {
        $tenantId = (int) session('tenant_id', 1);
        if ($roleId === null) {
            $this->clearRolesForUser($userUuid, $tenantId);
            return;
        }

        $this->assignRoleToUser($userUuid, $roleId, $tenantId);
    }

    public function assignRoleToUser(string $userUuid, int $roleId, int|string $tenantId): void
    {
        $conn = DB::connection(config('casbin.connection', 'landlord'));
        $userId = $conn->table('users')->where('uuid', $userUuid)->value('id');
        if ($userId === null) {
            return;
        }
        $roleCode = $conn->table('roles')->where('id', $roleId)->value('code');
        if ($roleCode === null) {
            return;
        }

        $this->clearRolesForUserByDomain($userId, $tenantId);
        $this->assignRoleToUserByDomain($userId, $roleCode, $tenantId);
    }

    public function assignRoleToUserByDomain(int|string $userId, string $roleCode, int|string $tenantId): void
    {
        $domain = config('casbin.tenant_prefix', 'tenant:') . $tenantId;
        CasbinEnforcerFactory::make()->addRoleForUser('user:' . $userId, $roleCode, $domain);
    }

    public function clearRolesForUserByDomain(int|string $userId, int|string $tenantId): void
    {
        $domain = config('casbin.tenant_prefix', 'tenant:') . $tenantId;
        $enforcer = CasbinEnforcerFactory::make();
        $sub = 'user:' . $userId;

        foreach ($enforcer->getRolesForUserInDomain($sub, $domain) as $role) {
            $enforcer->deleteRoleForUser($sub, $role, $domain);
        }
    }

    public function clearRolesForUser(string $userUuid, int|string $tenantId): void
    {
        $conn = DB::connection(config('casbin.connection', 'landlord'));
        $userId = $conn->table('users')->where('uuid', $userUuid)->value('id');
        if ($userId === null) {
            return;
        }

        $this->clearRolesForUserByDomain($userId, $tenantId);
    }

    public function syncUserRoles(): void
    {
        $tenantId = (int) session('tenant_id', 1);
        $conn = DB::connection(config('casbin.connection', 'landlord'));
        $users = $conn->table('users')
            ->join('roles', 'users.role_id', '=', 'roles.id')
            ->select('users.id as user_id', 'roles.code as role_code')
            ->get();

        foreach ($users as $user) {
            $this->clearRolesForUserByDomain($user->user_id, $tenantId);
            $this->assignRoleToUserByDomain($user->user_id, $user->role_code, $tenantId);
        }
    }
}
