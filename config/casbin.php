<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Casbin habilitado/deshabilitado
    |--------------------------------------------------------------------------
    |
    | Si CASBIN_ENABLED=false, el middleware y helper permitirán acceso sin
    | validar políticas. No usar en producción.
    |
    */
    'enabled' => filter_var(env('CASBIN_ENABLED', 'true'), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Conexión de base de datos para Casbin
    |--------------------------------------------------------------------------
    |
    | Todas las consultas de Casbin deben ir a landlord.
    |
    */
    'connection' => env('CASBIN_CONNECTION', 'landlord'),

    /*
    |--------------------------------------------------------------------------
    | Ruta del modelo Casbin
    |--------------------------------------------------------------------------
    */
    'model' => base_path('casbin/model.conf'),

    /*
    |--------------------------------------------------------------------------
    | Prefijo de dominio (tenant)
    |--------------------------------------------------------------------------
    |
    | Cada politica pertenece a un dominio: tenant_prefix . tenant_id. Es lo
    | que aisla los permisos de un mismo rol entre tenants distintos.
    |
    */
    'tenant_prefix' => env('CASBIN_TENANT_PREFIX', 'tenant:'),

    /*
    |--------------------------------------------------------------------------
    | Caché de los chequeos
    |--------------------------------------------------------------------------
    |
    | Segundos que vive en caché el resultado de cada can(). Amortigua las
    | ráfagas de chequeos de una misma petición (armar un menú hace decenas).
    |
    | Ojo: el caché es entre peticiones, así que un cambio de permisos tarda
    | hasta este TTL en notarse salvo que la app incluya un sello de versión en
    | la llave (ver PermissionService::cacheKey()). Con 0 no se cachea nada.
    |
    */
    'cache_ttl' => (int) env('CASBIN_CACHE_TTL', 300),
];