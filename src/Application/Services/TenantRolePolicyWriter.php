<?php

namespace Sodeker\LaravelCasbin\Application\Services;

use Casbin\Enforcer;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Sodeker\LaravelCasbin\Domain\Contracts\TenantRolePolicyWriterInterface;
use Sodeker\LaravelCasbin\Domain\Exceptions\MissingTenantDomainException;

/**
 * Escribe políticas de Casbin sobre `casbin_rule` acotando siempre el dominio.
 *
 * Va directo al query builder en lugar de pasar por el Enforcer a propósito:
 * `addPermissionForUser()` carga la política completa en memoria y escribe
 * regla por regla, lo que en un rol con cientos de permisos son cientos de
 * consultas. Aquí cada operación es un DELETE acotado por `v1` más un INSERT
 * por lotes, dentro de una transacción.
 *
 * Después de escribir se recarga el enforcer si ya estaba resuelto en el
 * contenedor, para que un `can()` posterior en la misma petición vea la
 * política nueva y no la que se cargó al principio.
 *
 * El caché de resultados de PermissionService no se toca aquí: invalidarlo es
 * de la app, que es la que sabe con qué sello de versión construye sus llaves.
 */
class TenantRolePolicyWriter implements TenantRolePolicyWriterInterface
{
    /**
     * Objeto reservado a las políticas comodín.
     *
     * Las políticas `*` (acceso total de los roles privilegiados) no se
     * gestionan desde el formulario de roles: las siembra la app. Reemplazar
     * los permisos de un rol no las borra, porque no son parte de lo que el
     * formulario envía y perderlas dejaría al rol privilegiado sin acceso.
     */
    protected const WILDCARD = '*';

    protected const TABLE = 'casbin_rule';

    public function replaceRolePolicies(string $roleCode, int|string $tenantId, iterable $policies): int
    {
        $roleCode = $this->requireRoleCode($roleCode);
        $domain = $this->domain($tenantId);
        $rows = $this->normalizePolicies($roleCode, $domain, $policies);

        $inserted = $this->connection()->transaction(function () use ($roleCode, $domain, $rows): int {
            $this->managedPolicies($roleCode, $domain)->delete();

            if ($rows === []) {
                return 0;
            }

            $this->query()->insert($rows);

            return count($rows);
        });

        $this->refreshLoadedEnforcer();

        return $inserted;
    }

    public function removeRolePolicies(string $roleCode, int|string $tenantId): int
    {
        $roleCode = $this->requireRoleCode($roleCode);
        $domain = $this->domain($tenantId);

        $deleted = $this->managedPolicies($roleCode, $domain)->delete();

        $this->refreshLoadedEnforcer();

        return $deleted;
    }

    public function policiesForRole(string $roleCode, int|string $tenantId): array
    {
        $roleCode = $this->requireRoleCode($roleCode);
        $domain = $this->domain($tenantId);

        return $this->managedPolicies($roleCode, $domain)
            ->orderBy('v2')
            ->orderBy('v3')
            ->get(['v2', 'v3'])
            ->map(static fn ($row): array => [
                'object' => (string) $row->v2,
                'action' => (string) $row->v3,
            ])
            ->all();
    }

    public function forgetRole(string $roleCode): int
    {
        $roleCode = $this->requireRoleCode($roleCode);

        $deleted = $this->connection()->transaction(function () use ($roleCode): int {
            $policies = $this->query()
                ->where('ptype', 'p')
                ->where('v0', $roleCode)
                ->delete();

            $assignments = $this->query()
                ->where('ptype', 'g')
                ->where('v1', $roleCode)
                ->delete();

            return $policies + $assignments;
        });

        $this->refreshLoadedEnforcer();

        return $deleted;
    }

    public function grantRoleToUser(int|string $userId, string $roleCode, int|string $tenantId): void
    {
        $roleCode = $this->requireRoleCode($roleCode);
        $domain = $this->domain($tenantId);
        $subject = $this->subject($userId);

        $this->query()->updateOrInsert(
            ['ptype' => 'g', 'v0' => $subject, 'v1' => $roleCode, 'v2' => $domain],
            ['ptype' => 'g', 'v0' => $subject, 'v1' => $roleCode, 'v2' => $domain],
        );

        $this->refreshLoadedEnforcer();
    }

    public function revokeUserRoles(int|string $userId, int|string $tenantId): int
    {
        $domain = $this->domain($tenantId);

        $deleted = $this->query()
            ->where('ptype', 'g')
            ->where('v0', $this->subject($userId))
            ->where('v2', $domain)
            ->delete();

        $this->refreshLoadedEnforcer();

        return $deleted;
    }

    /**
     * Políticas del rol en el dominio que SÍ gestiona este writer: las comodín
     * quedan fuera tanto del borrado como de la lectura.
     */
    protected function managedPolicies(string $roleCode, string $domain): Builder
    {
        return $this->query()
            ->where('ptype', 'p')
            ->where('v0', $roleCode)
            ->where('v1', $domain)
            ->where('v2', '!=', static::WILDCARD);
    }

    /**
     * Filas listas para insertar, sin duplicados y sin comodines.
     *
     * @param  iterable<array-key, array<array-key, string|null>>  $policies
     * @return array<int, array<string, string>>
     */
    protected function normalizePolicies(string $roleCode, string $domain, iterable $policies): array
    {
        $rows = [];

        foreach ($policies as $policy) {
            $object = trim((string) ($policy['object'] ?? $policy[0] ?? ''));
            $action = trim((string) ($policy['action'] ?? $policy[1] ?? ''));

            if ($object === '' || $action === '' || $object === static::WILDCARD) {
                continue;
            }

            $rows[$object . '|' . $action] = [
                'ptype' => 'p',
                'v0' => $roleCode,
                'v1' => $domain,
                'v2' => $object,
                'v3' => $action,
            ];
        }

        return array_values($rows);
    }

    /**
     * Dominio del tenant. Sin tenant no hay política que escribir.
     */
    protected function domain(int|string $tenantId): string
    {
        $tenantId = trim((string) $tenantId);

        if ($tenantId === '' || $tenantId === '0') {
            throw MissingTenantDomainException::forWrite();
        }

        return Config::get('casbin.tenant_prefix', 'tenant:') . $tenantId;
    }

    protected function requireRoleCode(string $roleCode): string
    {
        $roleCode = trim($roleCode);

        if ($roleCode === '') {
            throw new \InvalidArgumentException('No se puede escribir una política de Casbin sin código de rol.');
        }

        return $roleCode;
    }

    protected function subject(int|string $userId): string
    {
        return 'user:' . $userId;
    }

    protected function connection(): ConnectionInterface
    {
        return DB::connection(Config::get('casbin.connection', 'landlord'));
    }

    protected function query(): Builder
    {
        return $this->connection()->table(static::TABLE);
    }

    /**
     * El enforcer carga la política en memoria al resolverse. Si ya está
     * resuelto en esta petición, se recarga para que no siga respondiendo con
     * la política anterior a esta escritura.
     */
    protected function refreshLoadedEnforcer(): void
    {
        if (! app()->resolved(Enforcer::class)) {
            return;
        }

        app(Enforcer::class)->loadPolicy();
    }
}
