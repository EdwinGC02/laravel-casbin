<?php

namespace Sodeker\LaravelCasbin\Infrastructure\Casbin;

use Casbin\Enforcer;
use Casbin\Model\Model;
use Illuminate\Support\Facades\Config;

/**
 * Construye un Enforcer nuevo con la política cargada desde la base de datos.
 *
 * Cada llamada devuelve un enforcer fresco (misma semántica que en v1.0.x),
 * pero desde v1.1.0 la persistencia usa LaravelDatabaseAdapter, que reutiliza
 * la conexión del DatabaseManager de Laravel en lugar de abrir una conexión
 * PDO propia por instancia. Para compartir un único enforcer por proceso,
 * resolver el singleton del contenedor: app(\Casbin\Enforcer::class).
 */
class EnforcerFactory
{
    public static function make(): Enforcer
    {
        $connectionName = Config::get('casbin.connection', 'landlord');

        $model = new Model();
        $model->loadModel(Config::get('casbin.model'));

        $adapter = new LaravelDatabaseAdapter($connectionName);

        $enforcer = new Enforcer($model, $adapter);
        $enforcer->enableAutoSave(true);

        return $enforcer;
    }
}