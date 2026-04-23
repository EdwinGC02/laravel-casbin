<?php

namespace App\Casbin\Services;

use Illuminate\Support\Facades\DB;

class ModulesWithPermissionsService
{
    public function list(): array
    {
        $modules = DB::connection(config('casbin.connection', 'landlord'))
            ->table('modules_permissions')
            ->where('status', '1')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $permissions = DB::connection(config('casbin.connection', 'landlord'))
            ->table('permissions')
            ->orderBy('module_id')
            ->orderBy('action')
            ->get(['id', 'module_id', 'action']);

        $permissionsByModule = [];
        foreach ($permissions as $p) {
            $permissionsByModule[$p->module_id][] = [
                'id' => $p->id,
                'action' => $p->action,
            ];
        }

        $result = [];
        foreach ($modules as $module) {
            $actions = [];
            $permissionIds = [];
            foreach ($permissionsByModule[$module->id] ?? [] as $p) {
                $actions[] = $p['action'];
                $permissionIds[$p['action']] = $p['id'];
            }

            $result[] = [
                'id' => $module->id,
                'code' => $module->code,
                'name' => $module->name,
                'actions' => $actions,
                'permissionIds' => $permissionIds,
            ];
        }

        return $result;
    }
}
