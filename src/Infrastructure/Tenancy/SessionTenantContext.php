<?php

namespace Sodeker\LaravelCasbin\Infrastructure\Tenancy;

use Sodeker\LaravelCasbin\Domain\Contracts\TenantContextInterface;

/**
 * Tenant activo tomado de la sesión (`session('tenant_id')`).
 *
 * Implementación por defecto: reproduce exactamente el comportamiento que el
 * middleware y el helper `can()` tenían fijo hasta v1.1.0, para que actualizar
 * el paquete no cambie nada en las apps que ya funcionan así.
 *
 * No sirve cuando el tenant se resuelve por petición (p. ej. en la URL) y el
 * usuario puede trabajar en varios tenants a la vez: ahí la app debe registrar
 * su propia implementación de TenantContextInterface.
 */
class SessionTenantContext implements TenantContextInterface
{
    public function currentTenantId(): int|string|null
    {
        $tenantId = session('tenant_id');

        return is_int($tenantId) || is_string($tenantId) ? $tenantId : null;
    }
}
