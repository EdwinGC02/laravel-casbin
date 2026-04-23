<?php

namespace App\Casbin\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CasbinPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $conn = DB::connection(config('casbin.connection', 'landlord'));
        $domain = config('casbin.tenant_prefix', 'tenant:') . '1';

        $rows = [
            ['ptype' => 'p', 'v0' => 'Admin', 'v1' => $domain, 'v2' => '*', 'v3' => '*'],
            ['ptype' => 'g', 'v0' => 'user:1', 'v1' => 'Admin', 'v2' => $domain],
        ];

        foreach ($rows as $row) {
            $conn->table('casbin_rule')->updateOrInsert(
                [
                    'ptype' => $row['ptype'],
                    'v0' => $row['v0'],
                    'v1' => $row['v1'],
                    'v2' => $row['v2'] ?? null,
                    'v3' => $row['v3'] ?? null,
                ],
                $row
            );
        }
    }
}
