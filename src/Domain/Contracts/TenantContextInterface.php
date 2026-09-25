<?php

namespace Sodeker\LaravelCasbin\Domain\Contracts;

/**
 * Tenant sobre el que se evalúan los permisos de la petición en curso.
 *
 * El paquete no decide cómo se resuelve el tenant: lo pregunta. La
 * implementación por defecto (SessionTenantContext) lo lee de la sesión, que
 * es lo que hacía el paquete de forma fija hasta v1.1.0.
 *
 * Las apps que resuelven el tenant por otra vía deben reemplazar este binding.
 * Es obligatorio, por ejemplo, cuando el tenant viaja en la URL y el usuario
 * puede tener varias pestañas abiertas en tenants distintos: la sesión de
 * Laravel es UNA sola por navegador, así que leer `session('tenant_id')`
 * autoriza contra el tenant de la última pestaña que escribió, no contra el de
 * la petición. En Suite se reemplaza por el tenant del prefijo `/t/{slug}/`.
 */
interface TenantContextInterface
{
    /**
     * Identificador del tenant activo, o null si no hay ninguno resuelto.
     */
    public function currentTenantId(): int|string|null;
}
