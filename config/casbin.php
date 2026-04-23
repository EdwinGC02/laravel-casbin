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
    */
    'tenant_prefix' => env('CASBIN_TENANT_PREFIX', 'tenant:'),
];