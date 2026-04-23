<?php

namespace App\Casbin\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $conn = DB::connection(config('casbin.connection', 'landlord'));
        $roleIds = [1, 2];
        $permissions = $conn->table('permissions')->pluck('id');

        foreach ($roleIds as $roleId) {
            foreach ($permissions as $permissionId) {
                $conn->table('role_permissions')->updateOrInsert(
                    ['role_id' => $roleId, 'permission_id' => $permissionId],
                    [
                        'uuid' => Str::ulid(),
                        'status' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }
}
