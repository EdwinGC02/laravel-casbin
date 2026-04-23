<?php

namespace App\Casbin\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PermissionsTableSeeder extends Seeder
{
    public function run(): void
    {
        $conn = DB::connection(config('casbin.connection', 'landlord'));
        $now = Carbon::now();

        $permissionsByModule = [
            'dashboard' => ['view'],
            'roles' => ['view', 'edit', 'create', 'delete'],
            'users' => ['view', 'edit', 'create'],
            'products' => ['view', 'edit', 'create', 'delete'],
            'projects' => ['view', 'edit', 'create', 'delete'],
            'resolutions' => ['view', 'edit', 'create', 'delete'],
            'accountingDocuments' => ['view', 'edit', 'duplicate', 'cancel', 'print'],
        ];

        foreach ($permissionsByModule as $moduleCode => $actions) {
            $module = $conn->table('modules_permissions')->where('code', $moduleCode)->first();
            if (! $module) {
                continue;
            }

            foreach ($actions as $action) {
                $conn->table('permissions')->updateOrInsert(
                    ['module_id' => $module->id, 'action' => $action],
                    [
                        'uuid' => Str::ulid(),
                        'name' => ucfirst($action),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }
    }
}
