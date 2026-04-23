<?php

namespace App\Casbin\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ModulesTableSeeder extends Seeder
{
    public function run(): void
    {
        $conn = DB::connection(config('casbin.connection', 'landlord'));
        $now = Carbon::now();

        $modules = [
            ['code' => 'dashboard', 'name' => 'Dashboard', 'status' => 1],
            ['code' => 'roles', 'name' => 'Roles', 'status' => 1],
            ['code' => 'users', 'name' => 'Usuarios', 'status' => 1],
            ['code' => 'accountingDocuments', 'name' => 'Documentos', 'status' => 1],
            ['code' => 'products', 'name' => 'Productos', 'status' => 1],
            ['code' => 'projects', 'name' => 'Proyectos', 'status' => 1],
            ['code' => 'resolutions', 'name' => 'Resoluciones', 'status' => 1],
        ];

        foreach ($modules as $module) {
            $conn->table('modules_permissions')->updateOrInsert(
                ['code' => $module['code']],
                [
                    'uuid' => $conn->table('modules_permissions')->where('code', $module['code'])->value('uuid') ?? Str::ulid(),
                    'name' => $module['name'],
                    'status' => $module['status'],
                    'created_at' => $conn->table('modules_permissions')->where('code', $module['code'])->value('created_at') ?? $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
