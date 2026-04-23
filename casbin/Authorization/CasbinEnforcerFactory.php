<?php

namespace App\Casbin\Authorization;

use Casbin\Enforcer;
use Casbin\Model\Model;
use CasbinAdapter\Database\Adapter;
use Illuminate\Support\Facades\DB;

class CasbinEnforcerFactory
{
    public static function make(): Enforcer
    {
        $connectionName = config('casbin.connection', 'landlord');
        $connection = DB::connection($connectionName)->getConfig();
        $driver = $connection['driver'] ?? 'mysql';

        $dbConfig = [
            'type' => $driver,
            'hostname' => $connection['host'] ?? '127.0.0.1',
            'database' => $connection['database'] ?? '',
            'username' => $connection['username'] ?? '',
            'password' => $connection['password'] ?? '',
            'hostport' => $connection['port'] ?? ($driver === 'pgsql' ? 5432 : 3306),
            'charset' => $connection['charset'] ?? 'utf8mb4',
            'prefix' => $connection['prefix'] ?? '',
        ];

        $model = new Model();
        $model->loadModel(base_path('casbin/model.conf'));

        $adapter = new Adapter($dbConfig);
        $enforcer = new Enforcer($model, $adapter);
        $enforcer->enableAutoSave(true);

        return $enforcer;
    }
}
