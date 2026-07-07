<?php

namespace Sodeker\LaravelCasbin\Infrastructure\Casbin;

use Casbin\Model\Model;
use Casbin\Persist\Adapter;
use Casbin\Persist\AdapterHelper;
use Casbin\Persist\BatchAdapter;
use Casbin\Persist\UpdatableAdapter;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Adaptador de persistencia Casbin sobre la conexión gestionada por Laravel.
 *
 * Reemplaza a CasbinAdapter\Database\Adapter (leeqvip/database), cuyo
 * constructor abre una conexión PDO propia por cada instancia, fuera del
 * pool del DatabaseManager de Laravel. Con muchas instancias en un mismo
 * proceso (p. ej. `route:list` instanciando controladores que inyectan
 * PermissionServiceInterface) eso agotaba max_connections de la base de
 * datos. Este adaptador reutiliza la conexión nombrada que ya administra
 * Laravel, por lo que crear enforcers no abre conexiones adicionales.
 *
 * Replica la semántica del adaptador original (mismas columnas, mismo
 * criterio de normalización de reglas y mismas operaciones de autosave).
 */
class LaravelDatabaseAdapter implements Adapter, BatchAdapter, UpdatableAdapter
{
    use AdapterHelper;

    protected const COLUMNS = ['ptype', 'v0', 'v1', 'v2', 'v3', 'v4', 'v5'];

    protected string $connectionName;

    protected string $table;

    public function __construct(string $connectionName, string $table = 'casbin_rule')
    {
        $this->connectionName = $connectionName;
        $this->table = $table;
    }

    public function loadPolicy(Model $model): void
    {
        foreach ($this->query()->get(static::COLUMNS) as $row) {
            $this->loadPolicyArray($this->filterRule((array) $row), $model);
        }
    }

    public function savePolicy(Model $model): void
    {
        foreach (['p', 'g'] as $sec) {
            foreach ($model[$sec] ?? [] as $ptype => $ast) {
                foreach ($ast->policy as $rule) {
                    $this->query()->insert($this->toRow($ptype, $rule));
                }
            }
        }
    }

    public function addPolicy(string $sec, string $ptype, array $rule): void
    {
        $this->query()->insert($this->toRow($ptype, $rule));
    }

    public function addPolicies(string $sec, string $ptype, array $rules): void
    {
        $rows = array_map(fn (array $rule): array => $this->toRow($ptype, $rule), $rules);
        $this->query()->insert($rows);
    }

    public function removePolicy(string $sec, string $ptype, array $rule): void
    {
        $query = $this->query()->where('ptype', $ptype);
        foreach ($rule as $key => $value) {
            $query->where('v' . $key, $value);
        }
        $query->delete();
    }

    public function removePolicies(string $sec, string $ptype, array $rules): void
    {
        $this->connection()->transaction(function () use ($sec, $ptype, $rules): void {
            foreach ($rules as $rule) {
                $this->removePolicy($sec, $ptype, $rule);
            }
        });
    }

    public function removeFilteredPolicy(string $sec, string $ptype, int $fieldIndex, string ...$fieldValues): void
    {
        $this->filteredQuery($ptype, $fieldIndex, $fieldValues)->delete();
    }

    public function updatePolicy(string $sec, string $ptype, array $oldRule, array $newPolicy): void
    {
        $query = $this->query()->where('ptype', $ptype);
        foreach ($oldRule as $key => $value) {
            $query->where('v' . $key, $value);
        }

        $update = [];
        foreach ($newPolicy as $key => $value) {
            $update['v' . $key] = $value;
        }

        $query->update($update);
    }

    public function updatePolicies(string $sec, string $ptype, array $oldRules, array $newRules): void
    {
        $this->connection()->transaction(function () use ($sec, $ptype, $oldRules, $newRules): void {
            foreach ($oldRules as $i => $oldRule) {
                $this->updatePolicy($sec, $ptype, $oldRule, $newRules[$i]);
            }
        });
    }

    public function updateFilteredPolicies(string $sec, string $ptype, array $newPolicies, int $fieldIndex, string ...$fieldValues): array
    {
        $oldRules = [];

        $this->connection()->transaction(function () use ($sec, $ptype, $newPolicies, $fieldIndex, $fieldValues, &$oldRules): void {
            $rows = $this->filteredQuery($ptype, $fieldIndex, $fieldValues)->get(static::COLUMNS);
            foreach ($rows as $row) {
                $rule = (array) $row;
                unset($rule['ptype']);
                $oldRules[] = $this->filterRule($rule);
            }

            $this->filteredQuery($ptype, $fieldIndex, $fieldValues)->delete();
            $this->addPolicies($sec, $ptype, $newPolicies);
        });

        return $oldRules;
    }

    protected function connection(): ConnectionInterface
    {
        return DB::connection($this->connectionName);
    }

    protected function query(): Builder
    {
        return $this->connection()->table($this->table);
    }

    protected function filteredQuery(string $ptype, int $fieldIndex, array $fieldValues): Builder
    {
        $query = $this->query()->where('ptype', $ptype);
        foreach ($fieldValues as $offset => $value) {
            if ($value !== '') {
                $query->where('v' . ($fieldIndex + $offset), $value);
            }
        }

        return $query;
    }

    /**
     * Fila lista para insertar: ptype + columnas v0..vN según la regla.
     */
    protected function toRow(string $ptype, array $rule): array
    {
        $row = ['ptype' => $ptype];
        foreach ($rule as $key => $value) {
            $row['v' . $key] = $value;
        }

        return $row;
    }

    /**
     * Normaliza una fila leída de BD: valores posicionales sin los
     * vacíos/null del final (mismo criterio que el adaptador original).
     */
    protected function filterRule(array $rule): array
    {
        $rule = array_values($rule);

        $i = count($rule) - 1;
        for (; $i >= 0; $i--) {
            if ($rule[$i] !== '' && !is_null($rule[$i])) {
                break;
            }
        }

        return array_slice($rule, 0, $i + 1);
    }
}
