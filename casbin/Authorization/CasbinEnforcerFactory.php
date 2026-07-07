<?php

namespace App\Casbin\Authorization;

use Casbin\Enforcer;
use Sodeker\LaravelCasbin\Infrastructure\Casbin\EnforcerFactory;

/**
 * Delegado al factory del paquete sodeker/laravel-casbin: devuelve un
 * enforcer fresco cuya persistencia reutiliza la conexión del
 * DatabaseManager de Laravel (no abre conexiones PDO propias).
 */
class CasbinEnforcerFactory
{
    public static function make(): Enforcer
    {
        return EnforcerFactory::make();
    }
}
