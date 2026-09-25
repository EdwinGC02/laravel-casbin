<?php

namespace App\Casbin\Services;

use Illuminate\Support\Facades\DB;
use Sodeker\LaravelCasbin\Domain\Contracts\TenantRolePolicyWriterInterface;

/**
 * Sincroniza la tabla relacional de permisos (`role_permissions`) hacia las
 * políticas de Casbin (`casbin_rule`).
 *
 * Todo aquí es POR TENANT, sin excepción. `role_permissions` lleva `tenant_id`
 * justamente para eso: `roles` es una tabla global, el mismo rol puede estar
 * asociado a varios tenants, y cada tenant debe poder darle permisos distintos.
 * Si la fuente de verdad no tuviera tenant, reconstruir un dominio a partir de
 * ella replicaría en todos los tenants lo último que se guardó en uno, y editar
 * un rol desde un tenant borraría en silencio los permisos que ese mismo rol
 * tenía configurados en otro.
 *
 * Las escrituras van por TenantRolePolicyWriterInterface, que exige el tenant
 * en cada operación.
 */
class CasbinSyncService
{
    public function __construct(
        private readonly TenantRolePolicyWriterInterface $policyWriter,
    ) {}

    /**
     * Reconstruye las políticas de TODOS los roles del tenant indicado.
     */
    public function syncPoliciesByDomain(int|string $tenantId): void
    {
        $roleCodes = $this->connection()
            ->table('role_permissions as rp')
            ->join('roles as r', 'r.id', '=', 'rp.role_id')
            ->where('rp.tenant_id', $tenantId)
            ->where('rp.status', 1)
            ->distinct()
            ->pluck('r.code');

        foreach ($roleCodes as $roleCode) {
            $this->policyWriter->replaceRolePolicies(
                (string) $roleCode,
                $tenantId,
                $this->policiesFor((string) $roleCode, $tenantId),
            );
        }
    }

    /**
     * Reconstruye las políticas de un rol en un tenant.
     */
    public function syncPoliciesForRole(int $roleId, int|string $tenantId): void
    {
        $roleCode = $this->connection()->table('roles')->where('id', $roleId)->value('code');

        if ($roleCode === null) {
            return;
        }

        $this->policyWriter->replaceRolePolicies(
            (string) $roleCode,
            $tenantId,
            $this->policiesFor((string) $roleCode, $tenantId),
        );
    }

    /**
     * Quita las políticas del rol en ese tenant. Las de los demás quedan.
     */
    public function removePoliciesForRole(string $roleCode, int|string $tenantId): void
    {
        $this->policyWriter->removeRolePolicies($roleCode, $tenantId);
    }

    /**
     * El rol se eliminó: se va de todos los tenants.
     */
    public function forgetRole(string $roleCode): void
    {
        $this->policyWriter->forgetRole($roleCode);
    }

    public function assignRoleToUser(string $userUuid, int $roleId, int|string $tenantId): void
    {
        $userId = $this->connection()->table('users')->where('uuid', $userUuid)->value('id');
        if ($userId === null) {
            return;
        }

        $roleCode = $this->connection()->table('roles')->where('id', $roleId)->value('code');
        if ($roleCode === null) {
            return;
        }

        $this->assignRoleToUserByDomain($userId, (string) $roleCode, $tenantId);
    }

    public function assignRoleToUserByDomain(int|string $userId, string $roleCode, int|string $tenantId): void
    {
        $this->policyWriter->revokeUserRoles($userId, $tenantId);
        $this->policyWriter->grantRoleToUser($userId, $roleCode, $tenantId);
    }

    public function clearRolesForUserByDomain(int|string $userId, int|string $tenantId): void
    {
        $this->policyWriter->revokeUserRoles($userId, $tenantId);
    }

    public function clearRolesForUser(string $userUuid, int|string $tenantId): void
    {
        $userId = $this->connection()->table('users')->where('uuid', $userUuid)->value('id');
        if ($userId === null) {
            return;
        }

        $this->policyWriter->revokeUserRoles($userId, $tenantId);
    }

    /**
     * Pares objeto/acción del rol en ese tenant, leídos de la tabla relacional.
     *
     * @return array<int, array{object: string, action: string}>
     */
    private function policiesFor(string $roleCode, int|string $tenantId): array
    {
        return $this->connection()
            ->table('role_permissions as rp')
            ->join('roles as r', 'r.id', '=', 'rp.role_id')
            ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
            ->join('modules_permissions as mp', 'mp.id', '=', 'p.module_id')
            ->where('r.code', $roleCode)
            ->where('rp.tenant_id', $tenantId)
            ->where('rp.status', 1)
            ->get(['mp.code as module_code', 'p.action as action'])
            ->map(static fn ($row): array => [
                'object' => (string) $row->module_code,
                'action' => (string) $row->action,
            ])
            ->all();
    }

    private function connection()
    {
        return DB::connection(config('casbin.connection', 'landlord'));
    }
}
