<?php

namespace Sodeker\LaravelCasbin\Domain\Contracts;

/**
 * Escritura de políticas de Casbin acotada a UN tenant.
 *
 * Es la contraparte de escritura de PermissionServiceInterface. El modelo del
 * paquete (RBAC with domains) aísla los permisos por dominio en la LECTURA
 * —`m = ... && r.dom == p.dom && ...`—, pero hasta v1.1.0 el paquete no
 * ofrecía ninguna forma de escribirlos: cada app armaba su propio SQL contra
 * `casbin_rule`, y ahí es donde se pierde el aislamiento. Un `delete()` al que
 * se le olvida filtrar por `v1` borra las políticas del rol en TODOS los
 * tenants, y un `insert` en bucle sobre la lista de tenants las replica en
 * todos: el resultado es que editar un rol desde un tenant reescribe la
 * configuración de los demás sin que nadie lo note.
 *
 * Toda operación de esta interfaz exige el tenant. La única que actúa sobre
 * todos los dominios se llama `forgetRole()` y se lee como lo que es: el rol
 * dejó de existir.
 */
interface TenantRolePolicyWriterInterface
{
    /**
     * Deja las políticas del rol en ESE tenant exactamente como se indican.
     *
     * Borra las que tenía en ese dominio y escribe las nuevas. No toca ningún
     * otro dominio, así que dos tenants pueden tener el mismo rol con permisos
     * distintos.
     *
     * @param  iterable<array-key, array{object?: string, action?: string, 0?: string, 1?: string}>  $policies
     *                                                                                              Pares objeto/acción: `['object' => 'thirdParties', 'action' => 'view']`
     *                                                                                              o su forma posicional `['thirdParties', 'view']`.
     * @return int Políticas escritas.
     */
    public function replaceRolePolicies(string $roleCode, int|string $tenantId, iterable $policies): int;

    /**
     * Quita todas las políticas del rol en ESE tenant. El rol sigue existiendo
     * y conserva las de los demás tenants.
     *
     * @return int Políticas borradas.
     */
    public function removeRolePolicies(string $roleCode, int|string $tenantId): int;

    /**
     * Políticas del rol en ESE tenant, como pares `['object' => ..., 'action' => ...]`.
     *
     * @return array<int, array{object: string, action: string}>
     */
    public function policiesForRole(string $roleCode, int|string $tenantId): array;

    /**
     * El rol dejó de existir: borra sus políticas (`p`) y sus asignaciones a
     * usuarios (`g`) en TODOS los tenants.
     *
     * Es la única operación global y por eso tiene nombre propio: quien la
     * llama está diciendo que el rol se eliminó, no que se editó.
     *
     * @return int Filas borradas.
     */
    public function forgetRole(string $roleCode): int;

    /**
     * Asigna el rol al usuario en ESE tenant (`g`).
     */
    public function grantRoleToUser(int|string $userId, string $roleCode, int|string $tenantId): void;

    /**
     * Quita al usuario todos sus roles en ESE tenant (`g`). Los de los demás
     * tenants quedan intactos.
     *
     * @return int Asignaciones borradas.
     */
    public function revokeUserRoles(int|string $userId, int|string $tenantId): int;
}
